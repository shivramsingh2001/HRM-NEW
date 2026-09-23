<?php

namespace App\Services\Expense;

use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpensePayrollLink;
use App\Models\ExpenseStatusHistory;
use App\Models\MonthlyPayroll;
use App\Models\PayrollAuditLog;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ExpenseFeatures;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pay an approved REIMBURSEMENT through payroll instead of a voucher (per company, behind the
 * `expense_payroll_link` feature — see App\Support\ExpenseFeatures).
 *
 * Life of a routed reimbursement (expense_payroll_links.status):
 *
 *   sendToPayroll()      queued    expenses.payout_channel = 'payroll', target month chosen; the voucher
 *                                  route refuses it from now on
 *   payroll generated /  linked    the engine adds one earning line per expense to the payslip
 *   edited                         (applyToPayroll); the link points at THAT payslip
 *   payslip -> paid      paid      settle(): one expense payment (mode 'payroll') per expense through the
 *                                  normal ExpensePaymentService (ledger, paid_amount, completion, audit)
 *   payslip reopened     linked    unsettle(): the payment is voided again, the payslip can be corrected
 *   release()            released  back to the voucher route (also removes its line from a pending payslip)
 *
 * Modelled on LoanDeductionService: apply is revoke-then-relink so editing/recalculating a payslip is
 * idempotent, and everything is keyed by the LINK (monthly_payroll_id), never by a blanket status, so a
 * recalculation re-reads exactly what this payslip owns.
 *
 * Turning the feature OFF stops NEW routing and stops queued items being picked up, but never orphans
 * anything: reimbursements already linked to a payslip keep their link until it is paid or reopened, and
 * settle/unsettle/revoke keep working.
 *
 * Lock order (extends the Expense-wide rule): payslip -> expense rows (ascending id) -> links -> payments -> balance.
 * (Payroll saves the payslip before calling in, so it already holds the payslip lock; release() takes it first for the same reason.)
 */
class ExpenseReimbursementPayrollService
{
    public const CODE = 'expense_reimbursement';

    public function __construct(private ExpensePaymentService $payments, private AuditLogger $audit)
    {
    }

    /** The payslip line name for one expense — unique per expense, so a single line can be found/removed exactly. */
    public static function lineName(?string $expenseNumber): string
    {
        return 'Expense reimbursement ' . $expenseNumber;
    }

    // =================================================================
    //  Routing (finance UI)
    // =================================================================

    /**
     * Send approved, still-unpaid reimbursements to payroll, all-or-nothing.
     *
     * @param  int[]  $expenseIds
     * @param  callable(int): bool  $canManageOwner  RBAC scope predicate on the claim's owner
     * @return array{sent: int, total: string, month: string, pending_payslips: string[]}  pending_payslips = employees who already
     *         have a still-pending payslip for the month: it picks the reimbursement up the next time it is saved/recalculated.
     *
     * @throws ExpenseException|ExpenseBatchException
     */
    public function sendToPayroll(User $actor, array $expenseIds, string $yearMonth, callable $canManageOwner): array
    {
        $tenantId = (int) $actor->tenant_id;

        if ($reason = ExpenseFeatures::payrollRouteBlockedReason($tenantId)) {
            throw new ExpenseException($reason, 403);
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new ExpenseException('Choose the payroll month as YYYY-MM.', 422);
        }

        $ids = array_values(array_unique(array_map('intval', $expenseIds)));
        sort($ids);
        if ($ids === []) {
            throw new ExpenseException('Select at least one reimbursement.', 422);
        }
        if (count($ids) > ExpensePaymentService::MAX_BATCH_LINES) {
            throw new ExpenseException('Select at most ' . ExpensePaymentService::MAX_BATCH_LINES . ' reimbursements at a time.', 422);
        }

        return DB::transaction(function () use ($actor, $ids, $yearMonth, $canManageOwner, $tenantId) {
            $expenses = Expense::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $postedPaid = ExpensePayment::whereIn('expense_id', $ids)->posted()->distinct()->pluck('expense_id')->flip();

            $errors = [];
            $pendingPayslips = [];
            foreach ($ids as $id) {
                $e = $expenses->get($id);
                $fail = function (string $m, int $status = 400) use (&$errors, $id, $e) {
                    $errors[] = ['expense_id' => $id, 'expense_number' => $e?->expense_number, 'message' => $m, 'status' => $status];
                };

                if (! $e) {
                    $fail('Expense not found.', 404);
                } elseif (! $canManageOwner((int) $e->user_id)) {
                    $fail('You are not authorized to manage this expense.', 403);
                } elseif ($e->requirement_type !== Expense::TYPE_REIMBURSEMENT) {
                    $fail('Only reimbursements can be paid through payroll (advances are paid by voucher).');
                } elseif ($e->status !== Expense::STATUS_APPROVED) {
                    $fail('Only approved reimbursements can be sent to payroll.');
                } elseif ($e->isRoutedThroughPayroll()) {
                    $fail('Already sent to payroll (' . ($e->payroll_target_month ?: '—') . ').', 409);
                } elseif ($postedPaid->has($id) || Money::toCents($e->paid_amount) > 0) {
                    $fail('Part of this reimbursement has already been paid by voucher — pay the rest by voucher.', 409);
                } elseif ($slip = $this->payslipFor($tenantId, (int) $e->user_id, $yearMonth)) {
                    if ($slip->payment_status !== 'pending') {
                        $fail("The {$yearMonth} payslip is already {$slip->payment_status} — choose a later month.", 409);
                    } else {
                        $pendingPayslips[(int) $e->user_id] = $e->user?->name ?? ('#' . $e->user_id);
                    }
                }
            }

            if ($errors) {
                throw new ExpenseBatchException(
                    count($errors) === 1 ? $errors[0]['message'] : count($errors) . ' reimbursement(s) cannot be sent to payroll. Nothing was changed.',
                    $errors, count($errors) === 1 ? $errors[0]['status'] : 422
                );
            }

            $totalCents = 0;
            foreach ($ids as $id) {
                $e = $expenses[$id];
                $cents = Money::toCents($e->amount);
                $totalCents += $cents;

                ExpensePayrollLink::create([
                    'tenant_id' => $tenantId, 'expense_id' => $e->id, 'user_id' => $e->user_id, 'target_month' => $yearMonth,
                    'amount' => Money::fromCents($cents), 'status' => ExpensePayrollLink::QUEUED, 'created_by' => $actor->id,
                ]);
                $e->update(['payout_channel' => Expense::CHANNEL_PAYROLL, 'payroll_target_month' => $yearMonth]);

                $this->history($e, $actor, "Sent to payroll — will be paid with the {$yearMonth} salary.");
                $this->audit->record('tenant_user', $actor->id, $tenantId, 'expenses.sent_to_payroll', 'Expense', (int) $e->id,
                    ['payout_channel' => null], ['payout_channel' => 'payroll', 'month' => $yearMonth, 'amount' => (string) Money::fromCents($cents)]);
            }

            return ['sent' => count($ids), 'total' => Money::format($totalCents), 'month' => $yearMonth, 'pending_payslips' => array_values($pendingPayslips)];
        }, 3);
    }

    /**
     * Give a reimbursement back to the voucher route. Allowed while it is queued, or on a payslip that is
     * still PENDING (its line is removed and the payslip totals corrected). Refused once paid — reopen the
     * payslip first.
     *
     * @param  callable(int): bool  $canManageOwner
     */
    public function release(User $actor, int $expenseId, string $reason, callable $canManageOwner): Expense
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new ExpenseException('Give a short reason (at least 3 characters).', 422);
        }
        $tenantId = (int) $actor->tenant_id;

        return DB::transaction(function () use ($actor, $expenseId, $reason, $canManageOwner, $tenantId) {
            // payslip -> expense -> link (the order settle() runs in). Peek at the link to learn which payslip, lock that
            // first, then lock for real and confirm nothing moved in between.
            $peek = ExpensePayrollLink::where('expense_id', $expenseId)->active()->first(['id', 'monthly_payroll_id']);
            $slip = $peek?->monthly_payroll_id
                ? MonthlyPayroll::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereKey($peek->monthly_payroll_id)->lockForUpdate()->first()
                : null;

            $expense = Expense::whereKey($expenseId)->lockForUpdate()->first();
            if (! $expense) {
                throw new ExpenseException('Expense not found.', 404);
            }
            if (! $canManageOwner((int) $expense->user_id)) {
                throw new ExpenseException('You are not authorized to manage this expense.', 403);
            }

            $link = ExpensePayrollLink::where('expense_id', $expense->id)->active()->lockForUpdate()->first();
            if (! $expense->isRoutedThroughPayroll() || ! $link) {
                throw new ExpenseException('This reimbursement is not routed through payroll.', 409);
            }
            if ((int) $link->monthly_payroll_id !== (int) ($peek?->monthly_payroll_id)) {
                throw new ExpenseException('Payroll just changed this reimbursement — refresh and try again.', 409);
            }
            if ($link->status === ExpensePayrollLink::PAID) {
                throw new ExpenseException('It was already paid with the salary. Reopen that payslip if the payment must be reversed.', 409);
            }

            if ($link->status === ExpensePayrollLink::LINKED) {
                if ($slip) {
                    if ($slip->payment_status !== 'pending') {
                        throw new ExpenseException("It is on the {$slip->payroll_month} payslip, which is {$slip->payment_status}. Reopen that payslip first.", 409);
                    }
                    $this->removeLineFromPayslip($slip, $expense, $link, $actor);
                }
            }

            $link->update(['status' => ExpensePayrollLink::RELEASED, 'monthly_payroll_id' => null, 'released_at' => now(), 'released_reason' => mb_substr($reason, 0, 255)]);
            $expense->update(['payout_channel' => null, 'payroll_target_month' => null]);

            $this->history($expense, $actor, "Released from payroll to the voucher route: {$reason}");
            $this->audit->record('tenant_user', $actor->id, $tenantId, 'expenses.released_from_payroll', 'Expense', (int) $expense->id,
                ['payout_channel' => 'payroll'], ['payout_channel' => null, 'reason' => $reason]);

            return $expense;
        }, 3);
    }

    // =================================================================
    //  Payroll engine side
    // =================================================================

    /**
     * What the payroll engine should add to this employee's payslip for the month:
     *   - reimbursements already LINKED to this employee's payslip for the month (a recalculation keeps them — even
     *     after the feature is switched off, so a payslip is never silently changed underneath finance), plus
     *   - QUEUED ones whose target month has arrived (only while the route is enabled).
     * Read-only — nothing is consumed here (calculate() is called for previews too).
     *
     * @return Collection<int, object{expense_id:int, expense_number:?string, amount:float}>
     */
    public function linesFor(int $tenantId, int $userId, string $yearMonth): Collection
    {
        $slipId = MonthlyPayroll::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('payroll_month', $yearMonth)->value('id');
        $queuedAllowed = ExpenseFeatures::payrollRouteEnabled($tenantId);

        if (! $slipId && ! $queuedAllowed) {
            return collect();
        }

        return ExpensePayrollLink::withoutGlobalScopes()
            ->join('expenses as e', 'e.id', '=', 'expense_payroll_links.expense_id')
            ->where('expense_payroll_links.tenant_id', $tenantId)
            ->where('expense_payroll_links.user_id', $userId)
            ->where('e.status', Expense::STATUS_APPROVED)
            ->whereNull('e.deleted_at')
            ->where(function ($q) use ($slipId, $queuedAllowed, $yearMonth) {
                if ($slipId) {
                    $q->orWhere(fn ($x) => $x->where('expense_payroll_links.status', ExpensePayrollLink::LINKED)->where('expense_payroll_links.monthly_payroll_id', $slipId));
                }
                if ($queuedAllowed) {
                    $q->orWhere(fn ($x) => $x->where('expense_payroll_links.status', ExpensePayrollLink::QUEUED)->where('expense_payroll_links.target_month', '<=', $yearMonth));
                }
            })
            ->orderBy('expense_payroll_links.expense_id')
            ->get(['expense_payroll_links.expense_id', 'e.expense_number', 'expense_payroll_links.amount'])
            ->map(fn ($r) => (object) ['expense_id' => (int) $r->expense_id, 'expense_number' => $r->expense_number, 'amount' => round((float) $r->amount, 2)]);
    }

    /**
     * Idempotent: (re)attach exactly these expenses to the payslip. Whatever the payslip owned before is
     * handed back first, so an edit that no longer includes a reimbursement really lets it go.
     *
     * @param  int[]  $expenseIds  the source_ids of the payslip's `expense_reimbursement` lines
     */
    public function applyToPayroll(int $monthlyPayrollId, int $tenantId, array $expenseIds): void
    {
        DB::transaction(function () use ($monthlyPayrollId, $tenantId, $expenseIds) {
            $slip = MonthlyPayroll::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereKey($monthlyPayrollId)->lockForUpdate()->first();
            if (! $slip) {
                return;
            }

            $this->revokeForPayroll($monthlyPayrollId, $tenantId);

            $ids = array_values(array_unique(array_map('intval', $expenseIds)));
            if ($ids === []) {
                return;
            }

            ExpensePayrollLink::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $slip->user_id)
                ->whereIn('expense_id', $ids)
                ->where('status', ExpensePayrollLink::QUEUED)
                ->update(['status' => ExpensePayrollLink::LINKED, 'monthly_payroll_id' => $slip->id, 'linked_at' => now()]);
        });
    }

    /** Hand a payslip's linked reimbursements back to the queue (the payslip is deleted / recalculated / cancelled). Paid links are history and stay. */
    public function revokeForPayroll(int $monthlyPayrollId, int $tenantId): int
    {
        return ExpensePayrollLink::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('monthly_payroll_id', $monthlyPayrollId)
            ->where('status', ExpensePayrollLink::LINKED)
            ->update(['status' => ExpensePayrollLink::QUEUED, 'monthly_payroll_id' => null, 'linked_at' => null]);
    }

    /**
     * The single entry point the payroll controller calls after ANY payment_status change (single, bulk, reopen).
     * Runs inside the caller's transaction so a failure rolls the status change back too.
     *
     * @throws ExpenseException  a reimbursement can no longer be paid (e.g. it was voided) — the payslip must not be marked paid
     */
    public function onPayrollStatusChange(MonthlyPayroll $slip, string $oldStatus, string $newStatus, ?User $actor = null): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        if ($newStatus === 'paid') {
            $this->settle($slip, $actor);

            return;
        }
        if ($oldStatus === 'paid') {
            $this->unsettle($slip, $actor, "Payslip {$slip->payroll_month} moved from paid to {$newStatus}");
        }
        if ($newStatus === 'cancelled') {
            $this->revokeForPayroll((int) $slip->id, (int) $slip->tenant_id);
        }
    }

    /** The payslip was paid: pay every reimbursement linked to it (one 'payroll' voucher for the payslip). */
    public function settle(MonthlyPayroll $slip, ?User $actor = null): void
    {
        $tenantId = (int) $slip->tenant_id;
        $links = $this->lockedLinks($tenantId, $slip, ExpensePayrollLink::LINKED);
        if ($links->isEmpty()) {
            return;
        }

        $actor = $actor ?? Auth::user() ?? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($slip->processed_by);
        if (! $actor) {
            throw new ExpenseException('Cannot record the reimbursement payment: no acting user.', 500);
        }

        $date = $slip->payment_date ? $slip->payment_date->toDateString() : now()->toDateString();
        $result = $this->payments->payBatch($actor,
            $links->map(fn ($l) => ['expense_id' => $l->expense_id, 'amount' => (string) $l->amount])->all(),
            [
                'payment_date' => $date, 'payment_mode' => 'payroll', 'reference_number' => 'PAYROLL-' . $slip->id,
                'remarks' => "Paid with the {$slip->payroll_month} salary", 'via_payroll' => true,
            ],
            null, fn () => true);

        $paymentByExpense = $result['payments']->keyBy('expense_id');
        foreach ($links as $link) {
            $link->update(['status' => ExpensePayrollLink::PAID, 'expense_payment_id' => $paymentByExpense[$link->expense_id]->id ?? null, 'paid_at' => now()]);
        }
    }

    /** The payslip is no longer paid: reverse the reimbursement payments; the links go back onto the payslip. */
    public function unsettle(MonthlyPayroll $slip, ?User $actor, string $reason): void
    {
        $tenantId = (int) $slip->tenant_id;
        $links = $this->lockedLinks($tenantId, $slip, ExpensePayrollLink::PAID);
        if ($links->isEmpty()) {
            return;
        }

        $actor = $actor ?? Auth::user() ?? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($slip->processed_by);
        if (! $actor) {
            throw new ExpenseException('Cannot reverse the reimbursement payment: no acting user.', 500);
        }

        $batchIds = ExpensePayment::whereIn('id', $links->pluck('expense_payment_id')->filter())->whereNotNull('batch_id')->distinct()->pluck('batch_id');
        foreach ($batchIds as $batchId) {
            $this->payments->voidBatch($actor, (int) $batchId, $reason, fn () => true, viaPayroll: true);
        }

        foreach ($links as $link) {
            $link->update(['status' => ExpensePayrollLink::LINKED, 'expense_payment_id' => null, 'paid_at' => null]);
        }
    }

    // =================================================================
    //  Read helpers for the UI
    // =================================================================

    /** @return Collection<int, ExpensePayrollLink> active link per expense id, with the payslip month when linked */
    public function activeLinks(array $expenseIds): Collection
    {
        if ($expenseIds === []) {
            return collect();
        }

        return ExpensePayrollLink::whereIn('expense_id', $expenseIds)->active()->with('monthlyPayroll:id,payroll_month,payment_status')->get()->keyBy('expense_id');
    }

    // =================================================================
    //  Internals
    // =================================================================

    /**
     * The payslip's links in one status, locked expense-first (ascending id) then link — the same order release() uses.
     * A plain read finds the candidates; the locking reads below re-check them.
     *
     * @return Collection<int, ExpensePayrollLink>
     */
    private function lockedLinks(int $tenantId, MonthlyPayroll $slip, string $status): Collection
    {
        $expenseIds = ExpensePayrollLink::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('monthly_payroll_id', $slip->id)
            ->where('status', $status)->orderBy('expense_id')->pluck('expense_id')->all();

        if ($expenseIds === []) {
            return collect();
        }

        Expense::whereIn('id', $expenseIds)->orderBy('id')->lockForUpdate()->get(['id']);

        return ExpensePayrollLink::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('monthly_payroll_id', $slip->id)
            ->where('status', $status)->orderBy('expense_id')->lockForUpdate()->get();
    }

    private function payslipFor(int $tenantId, int $userId, string $yearMonth): ?MonthlyPayroll
    {
        return MonthlyPayroll::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('payroll_month', $yearMonth)->first();
    }

    /** Take one reimbursement's line off a PENDING payslip and correct its totals. */
    private function removeLineFromPayslip(MonthlyPayroll $slip, Expense $expense, ExpensePayrollLink $link, User $actor): void
    {
        $removed = PayrollComponent::where('monthly_payroll_id', $slip->id)->where('component_name', self::lineName($expense->expense_number))->delete();
        if ($removed === 0) {
            return;   // the payslip never got the line (e.g. saved before the link) — nothing to correct
        }

        $amount = Money::toCents($link->amount);
        $old = ['gross_earnings' => (string) $slip->gross_earnings, 'net_payable' => (string) $slip->net_payable];
        $slip->gross_earnings = Money::fromCents(Money::toCents($slip->gross_earnings) - $amount);
        $slip->net_payable = Money::fromCents(Money::toCents($slip->net_payable) - $amount);
        $slip->save();

        PayrollAuditLog::create([
            'tenant_id' => $slip->tenant_id, 'auditable_type' => MonthlyPayroll::class, 'auditable_id' => $slip->id,
            'action' => 'updated', 'actor_id' => $actor->id, 'old_values' => $old,   // the action column is an enum; 'change' says what
            'new_values' => ['change' => 'expense_reimbursement_released', 'gross_earnings' => (string) $slip->gross_earnings, 'net_payable' => (string) $slip->net_payable, 'expense' => $expense->expense_number],
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
    }

    private function history(Expense $expense, User $actor, string $remarks): void
    {
        ExpenseStatusHistory::create([
            'tenant_id' => $expense->tenant_id, 'expense_id' => $expense->id, 'status' => $expense->status,
            'changed_by' => $actor->id, 'remarks' => $remarks,
        ]);
    }
}
