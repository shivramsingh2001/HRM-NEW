<?php

namespace App\Services\Expense;

use App\Exceptions\DuplicatePaymentBatch;
use App\Exceptions\ExpenseBatchException;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpensePaymentBatch;
use App\Models\ExpenseStatusHistory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paying expenses — one code path for a single payment and for a whole batch.
 *
 * A payment run is a VOUCHER (ExpensePaymentBatch): mode / date / reference are
 * entered once and each selected expense becomes one payment line. A
 * single-expense payment is just a one-line voucher, so record() delegates here.
 *
 * Money-safety rules (everything inside ONE transaction per call):
 *  - Lock order is always  batch -> expenses (ascending id) -> payments -> employee
 *    balance rows (via ExpenseLedgerService). A fixed order means two concurrent
 *    runs that touch overlapping expenses queue up instead of deadlocking.
 *  - Batches are ALL-OR-NOTHING: every line is validated against the LOCKED rows
 *    first; if any is invalid nothing is posted and every problem is reported at once.
 *  - The outstanding amount is recomputed from POSTED payments inside the lock
 *    (authoritative). `expenses.paid_amount` is a cache rewritten from it, so a drifted
 *    cache can never permit an overpayment.
 *  - Payments are never deleted: a mistake is VOIDED (status=voided + a reversing
 *    ledger entry + a reason). Voided payments stop counting everywhere because
 *    Expense::payments() is posted-only.
 *  - A client idempotency key (unique on the batch) turns a retry/double-click into a no-op.
 *  - Integer paise throughout (App\Support\Money).
 *
 * Authorization is the caller's RBAC scope, applied per expense owner through the
 * `$canManageOwner(int $ownerUserId): bool` predicate (the owner is only known
 * after the rows are locked). Notifications are sent by the controller after commit.
 */
class ExpensePaymentService
{
    public const MAX_BATCH_LINES = 200;

    /** Only these can be paid out through a voucher. */
    private const PAYABLE_TYPES = [Expense::TYPE_ADVANCE, Expense::TYPE_REIMBURSEMENT];

    public function __construct(
        private ExpenseLedgerService $ledger,
        private ExpenseService $expenses,
        private AuditLogger $audit,
    ) {
    }

    // =================================================================
    //  Single payment (a one-line voucher)
    // =================================================================

    /**
     * Record one payment.
     *
     * @param  'direct'|'advance'|'reimbursement'  $type
     * @param  array{payment_date:string, amount:mixed, payment_mode:string, reference_number?:?string, bank_name?:?string, paid_to?:?string, remarks?:?string, expense_id?:mixed, direct_user_id?:mixed, expense_type?:mixed}  $data
     * @param  callable(int): bool  $canManageOwner
     * @return array{duplicate: bool, batch: ExpensePaymentBatch, payment: ExpensePayment, expense?: Expense, message?: string, data?: array}
     *
     * @throws ExpenseException
     */
    public function record(User $actor, string $type, array $data, ?string $idempotencyKey, callable $canManageOwner): array
    {
        $cents = Money::toCents($data['amount'] ?? 0);
        if ($cents <= 0) {
            throw new ExpenseException('Amount must be greater than zero.', 422);
        }

        if ($idempotencyKey && ($existing = ExpensePaymentBatch::where('idempotency_key', $idempotencyKey)->first())) {
            return $this->duplicateSingle($existing);
        }

        $meta = Arr::only($data, ['payment_date', 'payment_mode', 'reference_number', 'bank_name', 'paid_to', 'remarks']);

        try {
            [$result, $direct] = DB::transaction(function () use ($actor, $type, $data, $meta, $cents, $idempotencyKey, $canManageOwner) {
                $direct = $type === 'direct';

                if ($direct) {
                    $employee = User::where('id', $data['direct_user_id'] ?? 0)->where('tenant_id', $actor->tenant_id)->first();
                    if (! $employee) {
                        throw new ExpenseException('Selected employee was not found.', 404);
                    }
                    if (! $canManageOwner((int) $employee->id)) {
                        throw new ExpenseException('You are not authorized to pay this employee.', 403);
                    }

                    $expense = $this->expenses->createAdvanceOnBehalf(
                        $actor, $employee, $cents, (string) $meta['payment_date'],
                        isset($data['expense_type']) ? (int) $data['expense_type'] : null,
                        $meta['remarks'] ?? null
                    );
                    $expenseId = $expense->id;
                    $expectType = Expense::TYPE_ADVANCE;
                } else {
                    $expenseId = (int) ($data['expense_id'] ?? 0);
                    $expectType = $type;
                }

                return [
                    $this->postBatch($actor, [['expense_id' => $expenseId, 'cents' => $cents]], $meta, $idempotencyKey, $canManageOwner, $expectType),
                    $direct,
                ];
            }, 3);
        } catch (DuplicatePaymentBatch $dup) {
            return $this->duplicateSingle($dup->batch);
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isDuplicateKey($e) && ($existing = ExpensePaymentBatch::where('idempotency_key', $idempotencyKey)->first())) {
                return $this->duplicateSingle($existing);
            }

            throw $e;
        }

        /** @var ExpensePayment $payment */
        $payment = $result['payments']->first();
        $expense = $result['expenses']->first();
        $isAdvance = $expense->requirement_type === Expense::TYPE_ADVANCE;

        return [
            'duplicate' => false,
            'batch' => $result['batch'],
            'payment' => $payment,
            'expense' => $expense,
            'message' => $direct
                ? 'Direct payment added successfully and credited to employee balance'
                : ($isAdvance ? 'Advance payment processed successfully' : 'Reimbursement payment processed successfully'),
            'data' => $this->paymentData($payment, $expense, $result['batch'], $direct ? 'direct' : $expense->requirement_type) + [
                'total_paid' => (float) $expense->paid_amount,
                'remaining_amount' => $expense->remaining_amount,
                'is_fully_paid' => $expense->is_fully_paid,
            ],
        ];
    }

    // =================================================================
    //  Batches
    // =================================================================

    /**
     * Dry run: validate a selection exactly as payBatch() would, without writing or
     * locking anything, and summarise it. Uses the same validator as the real thing,
     * so the preview cannot disagree with the posting (apart from a race in between,
     * which payBatch re-checks under lock).
     *
     * @param  array<int, array{expense_id:mixed, amount:mixed}>  $lines
     * @param  callable(int): bool  $canManageOwner
     * @return array{valid: bool, lines: array, employees: array, total: float, count: int, errors: array}
     */
    public function preview(User $actor, array $lines, callable $canManageOwner): array
    {
        [$normalized, $formErrors] = $this->normalizeLines($lines);

        $ids = array_column($normalized, 'expense_id');
        $expenses = $ids ? Expense::whereIn('id', $ids)->with('user:id,name,employee_id')->get()->keyBy('id') : collect();
        [$checked, $errors] = $this->validateLines($normalized, $expenses, $canManageOwner, null);
        $errors = array_merge($formErrors, $errors);

        $employees = [];
        foreach ($checked as $line) {
            $key = $line['user_id'];
            $employees[$key] ??= ['user_id' => $key, 'name' => $line['employee_name'], 'employee_id' => $line['employee_code'], 'count' => 0, 'cents' => 0];
            $employees[$key]['count']++;
            $employees[$key]['cents'] += $line['cents'];
        }

        $totalCents = array_sum(array_column($checked, 'cents'));

        return [
            'valid' => $errors === [] && $checked !== [],
            'lines' => array_map(fn ($l) => Arr::except($l, ['cents']) + ['amount' => Money::fromCents($l['cents'])], $checked),
            'employees' => array_values(array_map(fn ($e) => ['user_id' => $e['user_id'], 'name' => $e['name'], 'employee_id' => $e['employee_id'], 'count' => $e['count'], 'amount' => Money::fromCents($e['cents'])], $employees)),
            'total' => Money::fromCents($totalCents),
            'count' => count($checked),
            'errors' => $errors,
        ];
    }

    /**
     * "Pay ₹X to this employee": spread an amount over their approved advances /
     * reimbursements that still have something to pay, OLDEST FIRST. Uses the cached
     * paid_amount only to *suggest* lines; payBatch() re-validates them under lock.
     *
     * @param  callable(int): bool  $canManageOwner
     * @return array{lines: array<int, array>, allocated: float, unallocated: float}
     */
    public function allocateFifo(User $actor, int $userId, mixed $amount, callable $canManageOwner): array
    {
        if (! $canManageOwner($userId)) {
            throw new ExpenseException('You are not authorized to pay this employee.', 403);
        }

        $remaining = Money::toCents($amount);
        if ($remaining <= 0) {
            throw new ExpenseException('Amount must be greater than zero.', 422);
        }

        $payable = Expense::payable()->where('user_id', $userId)->whereIn('requirement_type', self::PAYABLE_TYPES)
            ->orderBy('created_at')->orderBy('id')->get();

        $lines = [];
        foreach ($payable as $expense) {
            if ($remaining <= 0) {
                break;
            }

            $outstanding = Money::toCents($expense->amount) - Money::toCents($expense->paid_amount);
            $take = min($outstanding, $remaining);
            if ($take <= 0) {
                continue;
            }

            $lines[] = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'requirement_type' => $expense->requirement_type,
                'outstanding' => Money::fromCents($outstanding),
                'amount' => Money::fromCents($take),
            ];
            $remaining -= $take;
        }

        return [
            'lines' => $lines,
            'allocated' => Money::fromCents(Money::toCents($amount) - $remaining),
            'unallocated' => Money::fromCents($remaining),
        ];
    }

    /**
     * Post a voucher: one payment per line, all-or-nothing.
     *
     * @param  array<int, array{expense_id:mixed, amount:mixed}>  $lines
     * @param  array{payment_date:string, payment_mode:string, reference_number?:?string, bank_name?:?string, paid_to?:?string, remarks?:?string}  $meta
     * @param  callable(int): bool  $canManageOwner
     * @return array{duplicate: bool, batch: ExpensePaymentBatch, payments: Collection, expenses: Collection}
     *
     * @throws ExpenseBatchException  one or more lines invalid (nothing was posted)
     */
    public function payBatch(User $actor, array $lines, array $meta, ?string $idempotencyKey, callable $canManageOwner): array
    {
        [$normalized, $formErrors] = $this->normalizeLines($lines);
        if ($formErrors) {
            throw $this->batchException($formErrors);
        }

        if ($idempotencyKey && ($existing = ExpensePaymentBatch::where('idempotency_key', $idempotencyKey)->first())) {
            return $this->duplicateBatch($existing);
        }

        try {
            return DB::transaction(fn () => $this->postBatch($actor, $normalized, $meta, $idempotencyKey, $canManageOwner, null), 3);
        } catch (DuplicatePaymentBatch $dup) {
            return $this->duplicateBatch($dup->batch);
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isDuplicateKey($e) && ($existing = ExpensePaymentBatch::where('idempotency_key', $idempotencyKey)->first())) {
                return $this->duplicateBatch($existing);
            }

            throw $e;
        }
    }

    // =================================================================
    //  Change / void
    // =================================================================

    /**
     * Edit a posted payment's DETAILS (date, mode, reference, bank, paid-to, remarks).
     * The AMOUNT is fixed once posted: changing it means void + re-post, which keeps
     * the ledger an honest append-only history (and removes the old fragile
     * "apply the difference" logic).
     *
     * @param  callable(int): bool  $canManageOwner
     * @return array{expense: Expense, payment: ExpensePayment, oldAmount: float, isDirect: bool}
     */
    public function update(User $actor, int $paymentId, array $data, callable $canManageOwner): array
    {
        return DB::transaction(function () use ($actor, $paymentId, $data, $canManageOwner) {
            [$expense, $payment] = $this->lockExpenseAndPayment($paymentId);

            if (! $canManageOwner((int) $expense->user_id)) {
                throw new ExpenseException('You are not authorized to change this payment.', 403);
            }
            if ($payment->isVoided()) {
                throw new ExpenseException('A voided payment cannot be edited.', 409);
            }
            if ($payment->payment_mode === 'payroll') {
                throw new ExpenseException('This payment was made through payroll and cannot be edited here.', 409);
            }
            if (Money::toCents($data['amount'] ?? $payment->amount) !== Money::toCents($payment->amount)) {
                throw new ExpenseException(
                    'The amount of a posted payment cannot be edited. Void this payment and record a new one.', 422
                );
            }

            $old = Arr::only($payment->getAttributes(), ['payment_date', 'payment_mode', 'reference_number', 'bank_name', 'paid_to', 'remarks']);

            $payment->update([
                'payment_date' => $data['payment_date'],
                'payment_mode' => $data['payment_mode'],
                'reference_number' => $data['reference_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'paid_to' => $data['paid_to'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->auditExpense($actor, $expense, 'expenses.payment_updated',
                ['payment_id' => $payment->id] + array_map('strval', array_map(fn ($v) => $v ?? '', $old)),
                ['payment_id' => $payment->id, 'payment_mode' => $payment->payment_mode, 'reference_number' => (string) $payment->reference_number]);

            return [
                'expense' => $expense,
                'payment' => $payment,
                'oldAmount' => (float) $payment->amount,
                'isDirect' => (bool) $expense->is_direct_payment,
            ];
        }, 3);
    }

    /**
     * Void one payment: reverse its ledger effect, keep the row (status=voided) with
     * who/when/why, and recompute what the expense has been paid.
     *
     * @param  callable(int): bool  $canManageOwner
     * @return array{expense: Expense, payment: ExpensePayment, amount: string}
     */
    public function voidPayment(User $actor, int $paymentId, string $reason, callable $canManageOwner, bool $viaPayroll = false): array
    {
        $reason = $this->cleanReason($reason);

        return DB::transaction(function () use ($actor, $paymentId, $reason, $canManageOwner, $viaPayroll) {
            $row = ExpensePayment::whereKey($paymentId)->first(['id', 'batch_id']);
            if (! $row) {
                throw new ExpenseException('Payment not found.', 404);
            }

            // batch -> expense -> payment: the same order voidBatch() uses.
            $batch = $row->batch_id ? ExpensePaymentBatch::whereKey($row->batch_id)->lockForUpdate()->first() : null;
            [$expense, $payment] = $this->lockExpenseAndPayment($paymentId);

            if (! $canManageOwner((int) $expense->user_id)) {
                throw new ExpenseException('You are not authorized to void this payment.', 403);
            }
            if ($payment->isVoided()) {
                throw new ExpenseException('This payment is already voided.', 409);
            }
            if ($payment->payment_mode === 'payroll' && ! $viaPayroll) {
                throw new ExpenseException('This payment was made through payroll. Reopen that payslip to reverse it.', 409);
            }

            $this->voidLine($actor, $expense, $payment, $reason, $batch?->voucher_number);

            if ($batch && ! ExpensePayment::where('batch_id', $batch->id)->posted()->exists()) {
                $batch->update(['status' => ExpensePaymentBatch::STATUS_VOIDED, 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => 'All lines voided individually. Last: ' . $reason]);
            }

            return ['expense' => $expense, 'payment' => $payment, 'amount' => (string) $payment->amount];
        }, 3);
    }

    /**
     * Void a whole voucher — every posted line is reversed together or none is
     * (e.g. refused if an employee has already spent an advance from this run).
     *
     * @param  callable(int): bool  $canManageOwner
     * @return array{batch: ExpensePaymentBatch, expenses: Collection, voided: int}
     */
    public function voidBatch(User $actor, int $batchId, string $reason, callable $canManageOwner, bool $viaPayroll = false): array
    {
        $reason = $this->cleanReason($reason);

        return DB::transaction(function () use ($actor, $batchId, $reason, $canManageOwner, $viaPayroll) {
            $batch = ExpensePaymentBatch::whereKey($batchId)->lockForUpdate()->first();
            if (! $batch) {
                throw new ExpenseException('Voucher not found.', 404);
            }
            if ($batch->isVoided()) {
                throw new ExpenseException("Voucher {$batch->voucher_number} is already voided.", 409);
            }
            if ($batch->payment_mode === 'payroll' && ! $viaPayroll) {
                throw new ExpenseException("Voucher {$batch->voucher_number} was paid through payroll. Reopen that payslip to reverse it.", 409);
            }

            $expenseIds = ExpensePayment::where('batch_id', $batch->id)->posted()->distinct()->pluck('expense_id')->all();
            sort($expenseIds);

            $expenses = Expense::whereIn('id', $expenseIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $payments = ExpensePayment::where('batch_id', $batch->id)->posted()->orderBy('expense_id')->orderBy('id')->lockForUpdate()->get();

            // All-or-nothing authorization: one out-of-scope employee blocks the whole void.
            foreach ($payments as $payment) {
                if (! $canManageOwner((int) $expenses[$payment->expense_id]->user_id)) {
                    throw new ExpenseException("You are not authorized to void voucher {$batch->voucher_number}: it includes an employee outside your scope.", 403);
                }
            }

            foreach ($payments as $payment) {
                $expense = $expenses[$payment->expense_id];
                try {
                    $this->voidLine($actor, $expense, $payment, $reason, $batch->voucher_number);
                } catch (ExpenseException $e) {
                    throw new ExpenseException("Voucher {$batch->voucher_number} cannot be voided — {$expense->expense_number}: " . $e->getMessage(), $e->httpStatus());
                }
            }

            $batch->update(['status' => ExpensePaymentBatch::STATUS_VOIDED, 'voided_by' => $actor->id, 'voided_at' => now(), 'void_reason' => $reason]);

            $this->audit->record('tenant_user', $actor->id, (int) $batch->tenant_id, 'expenses.batch_voided', 'ExpensePaymentBatch', (int) $batch->id,
                ['status' => 'posted'], ['status' => 'voided', 'voucher' => $batch->voucher_number, 'reason' => $reason, 'lines' => $payments->count()]);

            return ['batch' => $batch, 'expenses' => $expenses, 'voided' => $payments->count()];
        }, 3);
    }

    // =================================================================
    //  Internals
    // =================================================================

    /**
     * The transactional core. MUST run inside a DB transaction.
     *
     * @param  array<int, array{expense_id:int, cents:int}>  $lines  already normalized
     * @param  string|null  $expectType  when set every line must be this requirement type (single-payment path)
     */
    private function postBatch(User $actor, array $lines, array $meta, ?string $key, callable $can, ?string $expectType): array
    {
        $this->assertMeta($meta);

        $ids = array_column($lines, 'expense_id');
        sort($ids);

        // 1. lock expenses in ascending id order
        $expenses = Expense::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // 2. idempotency, AFTER the locks: a concurrent twin waits for the first to commit, then lands here
        if ($key && ($existing = ExpensePaymentBatch::where('idempotency_key', $key)->first())) {
            throw new DuplicatePaymentBatch($existing);
        }

        // 3. validate every line against the LOCKED rows; report all problems at once
        [$checked, $errors] = $this->validateLines($lines, $expenses, $can, $expectType, ! empty($meta['via_payroll']));
        if ($errors) {
            throw $this->batchException($errors, count($lines));
        }

        $totalCents = array_sum(array_column($checked, 'cents'));
        $tenantId = (int) $actor->tenant_id;

        // 4. the voucher
        $batch = ExpensePaymentBatch::create([
            'tenant_id' => $tenantId,
            'voucher_number' => $this->nextVoucherNumber($tenantId),
            'payment_date' => $meta['payment_date'],
            'payment_mode' => $meta['payment_mode'],
            'reference_number' => $meta['reference_number'] ?? null,
            'bank_name' => $meta['bank_name'] ?? null,
            'remarks' => $meta['remarks'] ?? null,
            'total_amount' => Money::fromCents($totalCents),
            'line_count' => count($checked),
            'status' => ExpensePaymentBatch::STATUS_POSTED,
            'idempotency_key' => $key,
            'created_by' => $actor->id,
        ]);

        $voucher = $batch->voucher_number;
        $note = "Voucher {$voucher}" . (! empty($meta['remarks']) ? " — {$meta['remarks']}" : '');

        // 5. one payment per line, in id order (matches the lock order)
        $payments = collect();
        foreach ($checked as $line) {
            /** @var Expense $expense */
            $expense = $expenses[$line['expense_id']];
            $cents = $line['cents'];
            $isAdvance = $expense->requirement_type === Expense::TYPE_ADVANCE;

            $payment = ExpensePayment::create([
                'tenant_id' => $expense->tenant_id ?: $tenantId,
                'expense_id' => $expense->id,
                'batch_id' => $batch->id,
                'payment_date' => $meta['payment_date'],
                'amount' => Money::fromCents($cents),
                'payment_mode' => $meta['payment_mode'],
                'reference_number' => $meta['reference_number'] ?? null,
                'bank_name' => $meta['bank_name'] ?? null,
                'paid_to' => $meta['paid_to'] ?? null,
                'paid_by' => $actor->id,
                'created_by' => $actor->id,
                'remarks' => $meta['remarks'] ?? null,
                'status' => ExpensePayment::STATUS_POSTED,
            ]);

            $isAdvance
                ? $this->ledger->creditAdvance($expense, $cents, $note)
                : $this->ledger->payReimbursement($expense, $cents, $actor->id, $note);

            $newPaid = $line['paid_cents'] + $cents;
            $isFullyPaid = $newPaid === Money::toCents($expense->amount);

            $updates = ['paid_amount' => Money::fromCents($newPaid)];
            if ($isFullyPaid) {
                $updates['status'] = Expense::STATUS_COMPLETE;
                $this->history($expense, Expense::STATUS_COMPLETE, $actor,
                    ($isAdvance ? 'Fully paid' : 'Fully reimbursed') . " via voucher {$voucher}. Total: ₹" . Money::format($newPaid));
            } else {
                $this->history($expense, Expense::STATUS_APPROVED, $actor,
                    ($isAdvance ? 'Partial payment of ₹' : 'Partial reimbursement of ₹') . Money::format($cents)
                    . " via voucher {$voucher}. Remaining: ₹" . Money::format(Money::toCents($expense->amount) - $newPaid));
            }
            $expense->update($updates);

            $this->auditExpense($actor, $expense, 'expenses.payment_created', [], [
                'payment_id' => $payment->id, 'voucher' => $voucher, 'amount' => (string) Money::fromCents($cents),
                'type' => $expense->requirement_type, 'fully_paid' => $isFullyPaid,
            ]);

            $payments->push($payment);
        }

        $this->audit->record('tenant_user', $actor->id, $tenantId, 'expenses.batch_paid', 'ExpensePaymentBatch', (int) $batch->id, [], [
            'voucher' => $voucher, 'total' => (string) Money::fromCents($totalCents), 'lines' => count($checked), 'mode' => $meta['payment_mode'],
        ]);

        return ['duplicate' => false, 'batch' => $batch, 'payments' => $payments, 'expenses' => $expenses->filter(fn ($e) => in_array($e->id, $ids, true))->values()->keyBy('id')];
    }

    /**
     * ONE validator for preview() and the real posting.
     *
     * @param  array<int, array{expense_id:int, cents:int}>  $lines
     * @param  Collection<int, Expense>  $expenses  keyed by id (locked when called from postBatch)
     * @return array{0: array<int, array>, 1: array<int, array{expense_id:int, expense_number:?string, message:string}>}
     */
    private function validateLines(array $lines, Collection $expenses, callable $can, ?string $expectType, bool $viaPayroll = false): array
    {
        $paid = $expenses->isEmpty() ? collect() : ExpensePayment::whereIn('expense_id', $expenses->keys())->posted()
            ->selectRaw('expense_id, SUM(amount) AS total')->groupBy('expense_id')->pluck('total', 'expense_id');

        $checked = [];
        $errors = [];

        foreach ($lines as $line) {
            /** @var Expense|null $expense */
            $expense = $expenses->get($line['expense_id']);

            // `status` is the HTTP code a ONE-line voucher (a plain single payment) reports, so the
            // single-payment API/UI keeps its historical 404 / 403 / 400 behaviour.
            $fail = function (string $message, int $status = 400) use (&$errors, $line, $expense) {
                $errors[] = ['expense_id' => $line['expense_id'], 'expense_number' => $expense?->expense_number, 'message' => $message, 'status' => $status];
            };

            if (! $expense) {
                $fail('Expense not found.', 404);
                continue;
            }
            if (! $can((int) $expense->user_id)) {
                $fail('You are not authorized to pay this expense.', 403);
                continue;
            }

            $label = $expense->requirement_type === Expense::TYPE_ADVANCE ? 'advance' : 'reimbursement';

            if ($expectType !== null && $expense->requirement_type !== $expectType) {
                $fail('Selected expense is not a ' . $expectType . ' request.');
                continue;
            }
            if (! in_array($expense->requirement_type, self::PAYABLE_TYPES, true)) {
                $fail('Only advances and reimbursements can be paid through a voucher.');
                continue;
            }
            if ($expense->status !== Expense::STATUS_APPROVED) {
                $fail("Only approved {$label}s can have payment records.");
                continue;
            }

            // A reimbursement routed through payroll is paid ONLY by the payslip (ExpenseReimbursementPayrollService);
            // a voucher line for it would pay it twice.
            if ($expense->isRoutedThroughPayroll() && ! $viaPayroll) {
                $fail('This reimbursement is being paid through payroll (' . ($expense->payroll_target_month ?: 'next payslip') . '). Release it to the voucher route first.', 409);
                continue;
            }
            if ($viaPayroll && ! $expense->isRoutedThroughPayroll()) {
                $fail('This reimbursement is not routed through payroll.', 409);
                continue;
            }

            $paidCents = Money::toCents($paid[$expense->id] ?? 0);   // authoritative: posted payments
            $remaining = Money::toCents($expense->amount) - $paidCents;

            if ($line['cents'] > $remaining) {
                $fail('Payment amount exceeds remaining balance. Remaining: ₹' . Money::format($remaining));
                continue;
            }

            $checked[] = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'user_id' => (int) $expense->user_id,
                'employee_name' => $expense->user?->name,
                'employee_code' => $expense->user?->employee_id,
                'requirement_type' => $expense->requirement_type,
                'outstanding' => Money::fromCents($remaining),
                'cents' => $line['cents'],
                'paid_cents' => $paidCents,
            ];
        }

        return [$checked, $errors];
    }

    /**
     * @param  array<int, array{expense_id:mixed, amount:mixed}>  $lines
     * @return array{0: array<int, array{expense_id:int, cents:int}>, 1: array}
     */
    private function normalizeLines(array $lines): array
    {
        $errors = [];

        if ($lines === []) {
            $errors[] = ['expense_id' => 0, 'expense_number' => null, 'message' => 'Select at least one expense to pay.'];
        }
        if (count($lines) > self::MAX_BATCH_LINES) {
            $errors[] = ['expense_id' => 0, 'expense_number' => null, 'message' => 'A voucher can hold at most ' . self::MAX_BATCH_LINES . ' lines. Split the selection.'];
            $lines = array_slice($lines, 0, self::MAX_BATCH_LINES);
        }

        $normalized = [];
        $seen = [];
        foreach ($lines as $line) {
            $id = (int) ($line['expense_id'] ?? 0);
            $cents = Money::toCents($line['amount'] ?? 0);

            if (isset($seen[$id])) {
                $errors[] = ['expense_id' => $id, 'expense_number' => null, 'message' => 'The same expense appears twice in this voucher.'];
                continue;
            }
            $seen[$id] = true;

            if ($id <= 0) {
                $errors[] = ['expense_id' => 0, 'expense_number' => null, 'message' => 'A line is missing its expense.'];
                continue;
            }
            if ($cents <= 0) {
                $errors[] = ['expense_id' => $id, 'expense_number' => null, 'message' => 'Amount must be greater than zero.'];
                continue;
            }

            $normalized[] = ['expense_id' => $id, 'cents' => $cents];
        }

        return [$normalized, $errors];
    }

    /**
     * @param  int  $lineCount  lines in the request. Only a genuine ONE-line voucher (a plain single
     *                          payment) reports the line's own 404/403/400; any real batch is a 422.
     */
    private function batchException(array $errors, int $lineCount = 1): ExpenseBatchException
    {
        if (count($errors) === 1 && $lineCount === 1) {
            return new ExpenseBatchException($errors[0]['message'], $errors, $errors[0]['status'] ?? 422);
        }

        if (count($errors) === 1) {
            return new ExpenseBatchException($errors[0]['message'], $errors, 422);
        }

        return new ExpenseBatchException(
            count($errors) . ' line(s) cannot be paid. Nothing was posted — fix them and try again.', $errors, 422
        );
    }

    private function assertMeta(array $meta): void
    {
        if (empty($meta['payment_date']) || empty($meta['payment_mode'])) {
            throw new ExpenseException('Payment date and payment mode are required.', 422);
        }
    }

    /**
     * Gapless per-tenant voucher number. The upsert takes (and holds until commit) a row
     * lock on this tenant's counter, so two vouchers being posted at once queue up here
     * — and a rolled-back posting gives its number back.
     */
    private function nextVoucherNumber(int $tenantId): string
    {
        DB::statement(
            'INSERT INTO expense_voucher_sequences (tenant_id, last_number, created_at, updated_at) VALUES (?, 1, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()',
            [$tenantId]
        );

        $n = (int) DB::table('expense_voucher_sequences')->where('tenant_id', $tenantId)->value('last_number');

        return 'PV-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    /** Reverse ONE posted payment (caller already holds all the locks). */
    private function voidLine(User $actor, Expense $expense, ExpensePayment $payment, string $reason, ?string $voucher): void
    {
        $cents = Money::toCents($payment->amount);

        $expense->requirement_type === Expense::TYPE_REIMBURSEMENT
            ? $this->ledger->reverseReimbursement($expense, $cents, $actor->id)
            : $this->ledger->reverseAdvance($expense, $cents, $actor->id);

        $payment->update([
            'status' => ExpensePayment::STATUS_VOIDED,
            'voided_by' => $actor->id,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        $paidCents = Money::toCents($expense->payments()->sum('amount'));   // posted only — this line no longer counts
        $updates = ['paid_amount' => Money::fromCents($paidCents)];
        $where = $voucher ? " (voucher {$voucher})" : '';

        if ($expense->is_direct_payment && $paidCents === 0) {
            // A direct payment exists only because money was handed over; if it is voided the on-behalf
            // advance must not linger as something payable.
            $updates += ['status' => Expense::STATUS_CANCELLED, 'rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => 'Direct payment voided: ' . $reason];
            $this->history($expense, Expense::STATUS_CANCELLED, $actor, "Direct payment voided{$where}: {$reason}");
        } elseif ($expense->status === Expense::STATUS_COMPLETE && $paidCents < Money::toCents($expense->amount)) {
            $updates['status'] = Expense::STATUS_APPROVED;
            $this->history($expense, Expense::STATUS_APPROVED, $actor, "Payment of ₹" . Money::format($cents) . " voided{$where}: {$reason}. Status reverted to approved");
        } else {
            $this->history($expense, $expense->status, $actor, "Payment of ₹" . Money::format($cents) . " voided{$where}: {$reason}");
        }

        $expense->update($updates);

        $this->auditExpense($actor, $expense, 'expenses.payment_voided',
            ['payment_id' => $payment->id, 'amount' => (string) $payment->amount, 'status' => 'posted'],
            ['payment_id' => $payment->id, 'status' => 'voided', 'reason' => $reason, 'voucher' => (string) $voucher]);
    }

    /** Lock order is always expense -> payment. @return array{0: Expense, 1: ExpensePayment} */
    private function lockExpenseAndPayment(int $paymentId): array
    {
        $expenseId = ExpensePayment::whereKey($paymentId)->value('expense_id');
        if (! $expenseId) {
            throw new ExpenseException('Payment not found.', 404);
        }

        $expense = Expense::whereKey($expenseId)->lockForUpdate()->first();
        $payment = ExpensePayment::whereKey($paymentId)->lockForUpdate()->first();

        if (! $expense || ! $payment) {
            throw new ExpenseException('Payment not found.', 404);
        }

        return [$expense, $payment];
    }

    private function cleanReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new ExpenseException('Please give a reason (at least 3 characters).', 422);
        }

        return mb_substr($reason, 0, 500);
    }

    private function duplicateSingle(ExpensePaymentBatch $batch): array
    {
        return ['duplicate' => true, 'batch' => $batch, 'payment' => ExpensePayment::where('batch_id', $batch->id)->orderBy('id')->firstOrFail()];
    }

    private function duplicateBatch(ExpensePaymentBatch $batch): array
    {
        $payments = ExpensePayment::where('batch_id', $batch->id)->orderBy('id')->get();

        return [
            'duplicate' => true,
            'batch' => $batch,
            'payments' => $payments,
            'expenses' => Expense::whereIn('id', $payments->pluck('expense_id'))->get()->keyBy('id'),
        ];
    }

    private function paymentData(ExpensePayment $payment, Expense $expense, ExpensePaymentBatch $batch, string $type): array
    {
        $payment->load('payer');

        return [
            'id' => $payment->id,
            'voucher_number' => $batch->voucher_number,
            'batch_id' => $batch->id,
            'payment_date' => $payment->payment_date,
            'amount' => $payment->amount,
            'payment_mode' => $payment->payment_mode,
            'reference_number' => $payment->reference_number,
            'bank_name' => $payment->bank_name,
            'paid_to' => $payment->paid_to,
            'remarks' => $payment->remarks,
            'expense_number' => $expense->expense_number,
            'employee_name' => $expense->user?->name,
            'payer_name' => $payment->payer ? $payment->payer->name : null,
            'payment_type' => $type,
        ];
    }

    private function history(Expense $expense, string $status, User $actor, string $remarks): void
    {
        ExpenseStatusHistory::create([
            'tenant_id' => $expense->tenant_id,
            'expense_id' => $expense->id,
            'status' => $status,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }

    private function auditExpense(User $actor, Expense $expense, string $action, array $old, array $new): void
    {
        $this->audit->record('tenant_user', $actor->id, (int) $expense->tenant_id, $action, 'Expense', (int) $expense->id, $old, $new);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000' && str_contains((string) $e->getMessage(), 'idempotency_key');
    }
}
