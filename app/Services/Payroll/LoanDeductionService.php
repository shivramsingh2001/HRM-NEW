<?php

namespace App\Services\Payroll;

use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Itemized loan_repayments due for this user+month, oldest due_date
     * first. Each item also carries the parent loan's number/type so the
     * Edit Payroll screen can show "Loan #LN-... (EMI) — ₹5,000" style rows.
     *
     * @return Collection<int, array{loan_id:int, loan_number:string, type:string, repayment_id:int, due_date:string, total_amount:float, already_paid:float, balance_due:float, status:string}>
     */
    public function dueItems(int $userId, int $tenantId, string $yearMonth): Collection
    {
        $loans = Loan::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', Loan::STATUS_ACTIVE)
            // Deliberately no repayment_type filter -- EMI and lumpsum loans
            // are both eligible for payroll deduction.
            ->with(['repayments' => function ($query) use ($tenantId, $yearMonth) {
                $query->withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('month', $yearMonth)
                    ->whereIn('status', [LoanRepayment::STATUS_PENDING, LoanRepayment::STATUS_PARTIAL])
                    ->orderBy('due_date');
            }])
            ->get();

        return $loans
            ->flatMap(function (Loan $loan) {
                return $loan->repayments->map(function (LoanRepayment $repayment) use ($loan) {
                    $totalAmount = (float) $repayment->total_amount;
                    $alreadyPaid = (float) $repayment->paid_amount;

                    return [
                        'loan_id' => $loan->id,
                        'loan_number' => $loan->loan_number,
                        'type' => $loan->repayment_type,
                        'repayment_id' => $repayment->id,
                        'due_date' => (string) $repayment->due_date,
                        'total_amount' => round($totalAmount, 2),
                        'already_paid' => round($alreadyPaid, 2),
                        'balance_due' => round(max(0, $totalAmount - $alreadyPaid), 2),
                        'status' => $repayment->status,
                    ];
                });
            })
            ->sortBy('due_date')
            ->values();
    }

    /**
     * Sum of dueItems()'s balance_due -- what's owed this month across every
     * active loan (EMI + lumpsum), before any admin adjustment.
     */
    public function totalDue(int $userId, int $tenantId, string $yearMonth): float
    {
        return round($this->dueItems($userId, $tenantId, $yearMonth)->sum('balance_due'), 2);
    }

    /**
     * Allocate $amount across this month's due items, oldest due_date
     * first: fully pays an item if there's enough left, partially pays
     * (status='partial') if not, and leaves later items untouched once the
     * amount runs out. Never reaches into a different month's row and never
     * applies more than each item's own balance_due (overpayment is capped,
     * not carried forward -- see 'unapplied_excess' in the return value).
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
        string $paymentMode = LoanRepayment::PAYMENT_MODE_SALARY_DEDUCTION
    ): array {
        if ($monthlyPayrollId) {
            $this->revokeForPayroll($monthlyPayrollId, $tenantId);
        }

        $amount = round(max(0, $amount), 2);
        $items = $this->dueItems($userId, $tenantId, $yearMonth);

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
    public function revokeForPayroll(int $monthlyPayrollId, int $tenantId): void
    {
        $repayments = LoanRepayment::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('monthly_payroll_id', $monthlyPayrollId)
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

            if ($loan->loan_application_id) {
                DB::table('loan_applications')
                    ->where('id', $loan->loan_application_id)
                    ->update(['status' => 'closed']);
            }
        }

        $loan->save();
    }
}
