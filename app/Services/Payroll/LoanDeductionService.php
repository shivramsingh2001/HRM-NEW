<?php

namespace App\Services\Payroll;

use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "how much loan should be deducted from this
 * employee's payroll for this month, and what happens to the loan ledger
 * when that amount is actually collected."
 *
 * Replaces the two previously-duplicated raw-SQL implementations
 * (MonthlyPayrollController::calculateLoanDeductions /
 * PayrollAttendanceContextBuilder::loanDeduction) and, unlike both of them,
 * considers EMI *and* lumpsum loans — the old `repayment_type = 'emi'`
 * filter excluded lumpsum repayments from payroll entirely.
 */
class LoanDeductionService
{
    /**
     * Itemized loan_repayments to collect in this user's payroll for this
     * month, oldest due_date first: this month's instalments PLUS every
     * earlier instalment still unpaid / part-paid (overdue — e.g. a month
     * whose payroll ran before the loan was active, or whose net pay could
     * not cover the EMI). Each item carries the parent loan's number/type so
     * the Edit Payroll screen can show "Loan #LN-... (EMI) — ₹5,000" rows,
     * and `is_overdue` / `month` for the "overdue from Jun" label.
     *
     * @return Collection<int, array{loan_id:int, loan_number:string, type:string, repayment_id:int, month:string, due_date:string, total_amount:float, already_paid:float, balance_due:float, status:string, is_overdue:bool}>
     */
    public function dueItems(int $userId, int $tenantId, string $yearMonth, ?string $kind = null): Collection
    {
        $loans = Loan::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', Loan::STATUS_ACTIVE)
            // $kind: 'loan' | 'salary_advance' (Loans & Advances) — null = both.
            ->when($kind, fn ($q) => $q->where('loan_kind', $kind))
            // Deliberately no repayment_type filter -- EMI and lumpsum loans
            // are both eligible for payroll deduction.
            ->with(['repayments' => function ($query) use ($tenantId, $yearMonth) {
                $query->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('month', '<=', $yearMonth) // this month + overdue earlier months
                    ->whereIn('status', [LoanRepayment::STATUS_PENDING, LoanRepayment::STATUS_PARTIAL, LoanRepayment::STATUS_OVERDUE])
                    ->orderBy('due_date');
            }])
            ->get();

        return $loans
            ->flatMap(function (Loan $loan) use ($yearMonth) {
                return $loan->repayments->map(function (LoanRepayment $repayment) use ($loan, $yearMonth) {
                    $totalAmount = (float) $repayment->total_amount;
                    $alreadyPaid = (float) $repayment->paid_amount;

                    return [
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'kind' => $loan->loan_kind ?? Loan::KIND_LOAN,
                        'advance_month' => $loan->advance_month,
                        'type' => $loan->repayment_type,
                        'repayment_id' => $repayment->id,
                        'month' => (string) $repayment->month,
                        'due_date' => (string) $repayment->due_date,
                        'total_amount' => round($totalAmount, 2),
                        'already_paid' => round($alreadyPaid, 2),
                        'balance_due' => round(max(0, $totalAmount - $alreadyPaid), 2),
                        'status' => $repayment->status,
                        'is_overdue' => (string) $repayment->month < $yearMonth,
                    ];
                });
            })
            ->filter(fn ($item) => $item['balance_due'] > 0)
            ->sortBy('due_date')
            ->values();
    }

    /**
     * Sum of dueItems()'s balance_due -- what's owed this month across every
     * active loan (EMI + lumpsum), before any admin adjustment.
     */
    public function totalDue(int $userId, int $tenantId, string $yearMonth, ?string $kind = null): float
    {
        return round($this->dueItems($userId, $tenantId, $yearMonth, $kind)->sum('balance_due'), 2);
    }

    /**
     * Allocate $amount across the due items (overdue instalments first, then
     * this month's), oldest due_date first: fully pays an item if there's
     * enough left, partially pays (status='partial') if not, and leaves later
     * items untouched once the amount runs out. Never touches a FUTURE month's
     * row and never applies more than each item's own balance_due (overpayment
     * is capped, not carried forward -- see 'unapplied_excess'). Each payslip's
     * share is recorded in loan_repayment_allocations.
     *
     * Safe to call twice for the same $monthlyPayrollId: it first reverses
     * whatever that payslip previously applied, then re-allocates the new
     * amount, so editing-and-resaving a payslip doesn't double-deduct.
     *
     * Does not open its own transaction -- the caller (payroll generation /
     * payroll edit save) is expected to already be inside one, matching the
     * contract the code this replaces already had.
     *
     * @return array{applied_total: float, shortfall: float, unapplied_excess: float, items: array}
     */
    public function applyDeduction(
        int $userId,
        int $tenantId,
        string $yearMonth,
        float $amount,
        ?int $monthlyPayrollId,
        ?int $processedBy,
        string $paymentMode = LoanRepayment::PAYMENT_MODE_SALARY_DEDUCTION,
        ?string $kind = null
    ): array {
        if ($monthlyPayrollId) {
            // Only this kind's earlier share: the salary advance and the loan EMIs of one payslip are applied separately.
            $this->revokeForPayroll($monthlyPayrollId, $tenantId, $kind);
        }

        $amount = round(max(0, $amount), 2);
        $items = $this->dueItems($userId, $tenantId, $yearMonth, $kind);

        $remaining = $amount;
        $appliedTotal = 0.0;
        $result = [];

        foreach ($items as $item) {
            if ($remaining <= 0) {
                $result[] = $item + ['allocated' => 0.0];
                continue;
            }

            $allocation = round(min($remaining, $item['balance_due']), 2);
            if ($allocation > 0) {
                $this->allocateToRepayment($item, $allocation, $tenantId, $yearMonth, $monthlyPayrollId, $processedBy, $paymentMode);
            }

            $remaining = round($remaining - $allocation, 2);
            $appliedTotal = round($appliedTotal + $allocation, 2);
            $result[] = $item + ['allocated' => $allocation];
        }

        $totalDue = round($items->sum('balance_due'), 2);
        $shortfall = round(max(0, $totalDue - $appliedTotal), 2);
        $unappliedExcess = round(max(0, $amount - $totalDue), 2);

        return [
            'applied_total' => $appliedTotal,
            'shortfall' => $shortfall,
            'unapplied_excess' => $unappliedExcess,
            'items' => $result,
        ];
    }

    /**
     * Rolls back whatever a specific monthly_payroll_id previously applied:
     * restores each affected loan_repayments row's paid_amount/status and
     * puts the loan's remaining_amount back (reopening it if this payslip
     * had auto-closed it). Called at the top of applyDeduction() before
     * reallocating, and directly by the payroll-edit controller action when
     * the admin disables the "deduct" toggle on an already-processed
     * payslip.
     */
    public function revokeForPayroll(int $monthlyPayrollId, int $tenantId, ?string $kind = null): void
    {
        // $kind limits the undo to salary advances or to loans (null = everything this payslip collected).
        $kindLoanIds = $kind
            ? Loan::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('loan_kind', $kind)->pluck('id')->all()
            : null;

        // 1. Shares recorded in loan_repayment_allocations: undo exactly this payslip's share, so a
        //    part-payment another payslip made on the same instalment stays.
        $allocations = Schema::hasTable('loan_repayment_allocations')
            ? DB::table('loan_repayment_allocations')->where('tenant_id', $tenantId)->where('monthly_payroll_id', $monthlyPayrollId)
                ->when($kindLoanIds !== null, fn ($q) => $q->whereIn('loan_id', $kindLoanIds ?: [0]))->get()
            : collect();
        $handled = [];

        foreach ($allocations as $allocation) {
            $repayment = LoanRepayment::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->find($allocation->loan_repayment_id);
            $loan = Loan::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->find($allocation->loan_id);
            $amount = (float) $allocation->amount;

            if ($loan) {
                $loan->remaining_amount = round((float) $loan->remaining_amount + $amount, 2);
                if ($loan->status === Loan::STATUS_CLOSED && $loan->remaining_amount > 0) {
                    $loan->status = Loan::STATUS_ACTIVE;
                    $loan->closed_date = null;
                }
                $loan->save();
            }

            if ($repayment) {
                $paid = round(max(0, (float) $repayment->paid_amount - $amount), 2);
                $repayment->paid_amount = $paid;
                $repayment->status = $paid <= 0 ? LoanRepayment::STATUS_PENDING
                    : ($paid >= (float) $repayment->total_amount ? LoanRepayment::STATUS_PAID : LoanRepayment::STATUS_PARTIAL);
                // Point at the payslip that still holds a share (if any).
                $other = DB::table('loan_repayment_allocations')->where('loan_repayment_id', $repayment->id)
                    ->where('monthly_payroll_id', '!=', $monthlyPayrollId)->orderByDesc('id')->first();
                $repayment->monthly_payroll_id = $other->monthly_payroll_id ?? null;
                $repayment->salary_month = $other->salary_month ?? null;
                if ($paid <= 0) {
                    $repayment->paid_date = null;
                    $repayment->is_auto_deducted = false;
                }
                $repayment->save();
                $handled[] = $repayment->id;
            }
        }
        if ($allocations->isNotEmpty()) {
            DB::table('loan_repayment_allocations')->where('tenant_id', $tenantId)->where('monthly_payroll_id', $monthlyPayrollId)->delete();
        }

        // 2. Rows applied before allocations were recorded: the old whole-row reset.
        $repayments = LoanRepayment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('monthly_payroll_id', $monthlyPayrollId)
            ->whereNotIn('id', $handled ?: [0])
            ->when($kindLoanIds !== null, fn ($q) => $q->whereIn('loan_id', $kindLoanIds ?: [0]))
            ->get();

        foreach ($repayments as $repayment) {
            $loan = Loan::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->find($repayment->loan_id);

            if ($loan) {
                $wasClosedByThisPayroll = $loan->status === Loan::STATUS_CLOSED
                    && (float) $loan->remaining_amount <= 0;

                $loan->remaining_amount = round((float) $loan->remaining_amount + (float) $repayment->paid_amount, 2);

                if ($wasClosedByThisPayroll && $loan->remaining_amount > 0) {
                    $loan->status = Loan::STATUS_ACTIVE;
                    $loan->closed_date = null;
                }

                $loan->save();
            }

            $repayment->paid_amount = 0;
            $repayment->status = LoanRepayment::STATUS_PENDING;
            $repayment->paid_date = null;
            $repayment->is_auto_deducted = false;
            $repayment->monthly_payroll_id = null;
            $repayment->save();
        }
    }

    private function allocateToRepayment(
        array $item,
        float $allocation,
        int $tenantId,
        string $yearMonth,
        ?int $monthlyPayrollId,
        ?int $processedBy,
        string $paymentMode
    ): void {
        $repayment = LoanRepayment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->find($item['repayment_id']);
        if (! $repayment) {
            return;
        }

        $newPaid = round((float) $repayment->paid_amount + $allocation, 2);
        $isFullyPaid = $newPaid >= (float) $repayment->total_amount;

        $repayment->paid_amount = $newPaid;
        $repayment->status = $isFullyPaid ? LoanRepayment::STATUS_PAID : LoanRepayment::STATUS_PARTIAL;
        $repayment->paid_date = $repayment->paid_date ?? now();
        $repayment->payment_mode = $paymentMode;
        $repayment->is_auto_deducted = true;
        $repayment->salary_month = $yearMonth;
        $repayment->monthly_payroll_id = $monthlyPayrollId;
        $repayment->processed_by = $processedBy;
        $repayment->save();

        // This payslip's share — what revokeForPayroll() undoes.
        if ($monthlyPayrollId && Schema::hasTable('loan_repayment_allocations')) {
            DB::table('loan_repayment_allocations')->insert([
                'tenant_id' => $tenantId,
                'loan_id' => $item['loan_id'],
                'loan_repayment_id' => $repayment->id,
                'monthly_payroll_id' => $monthlyPayrollId,
                'salary_month' => $yearMonth,
                'amount' => $allocation,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $loan = Loan::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->find($item['loan_id']);
        if (! $loan) {
            return;
        }

        $newRemaining = round((float) $loan->remaining_amount - $allocation, 2);
        $loan->remaining_amount = max(0, $newRemaining);

        if ($newRemaining <= 0) {
            $loan->status = Loan::STATUS_CLOSED;
            $loan->closed_date = now();

            // loan_applications has never existed in the schema — guard so closing a loan can't crash payroll.
            if ($loan->loan_application_id && Schema::hasTable('loan_applications')) {
                DB::table('loan_applications')
                    ->where('id', $loan->loan_application_id)
                    ->update(['status' => 'closed']);
            }
        }

        $loan->save();
    }
}
