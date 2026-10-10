<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Loan EMIs and salary advances on a payslip — how much is due, fitting them
 * into net pay (advance first, then EMIs) and writing the loan ledger. Shared
 * by both payroll engines and Edit Payroll; moved out of
 * MonthlyPayrollController unchanged (code-quality plan, Phase 1). The ledger
 * itself is LoanDeductionService.
 */
class PayrollLoanApplier
{
    public function __construct(private LoanDeductionService $loanDeductionService) {}

    // =========================================================================

    public function calculateLoanDeductions(int $userId, string $yearMonth, ?int $tenantId = null, ?string $kind = \App\Models\Loan::KIND_LOAN): float
    {
        if (! $tenantId) {
            return 0.0;
        }

        return $this->loanDeductionService->totalDue($userId, $tenantId, $yearMonth, $kind);
    }

    /**
     * capLoanAndAdvance() applied to a PayrollCalculationEngine result:
     * rewrites the two line items, the result's loan / advance amounts and
     * the totals; a line capped to 0 is dropped from the payslip.
     */
    public function capDynamicLoanAndAdvance(array &$result, int $userId, string $yearMonth): void
    {
        $advance = (float) ($result['salary_advance_deduction'] ?? 0);
        $loan = (float) ($result['loan_deduction'] ?? 0);
        if ($loan <= 0 && $advance <= 0) {
            return;
        }

        $nonLoanDeductions = $result['total_deductions'] - $loan - $advance;
        [$cappedAdvance, $cappedLoan] = $this->capLoanAndAdvance($result['gross_earnings'] - $nonLoanDeductions, $advance, $loan);
        if ($cappedAdvance >= $advance && $cappedLoan >= $loan) {
            return;
        }

        Log::channel('daily')->warning('Loan / advance deduction capped at net pay (dynamic engine)', [
            'employee_id' => $userId, 'month' => $yearMonth,
            'advance_due' => $advance, 'advance_capped_to' => $cappedAdvance,
            'loan_due' => $loan, 'loan_capped_to' => $cappedLoan,
        ]);

        $result['total_deductions'] = round($nonLoanDeductions + $cappedAdvance + $cappedLoan, 2);
        $result['net_payable'] = round($result['gross_earnings'] - $result['total_deductions'], 2);
        $result['loan_deduction'] = $cappedLoan;
        $result['salary_advance_deduction'] = $cappedAdvance;

        foreach ($result['line_items'] as &$li) {
            if ($li['code'] === 'loan_deduction') {
                $li['amount'] = $cappedLoan;
            } elseif ($li['code'] === 'salary_advance_deduction') {
                $li['amount'] = $cappedAdvance;
            }
        }
        unset($li);
        $result['line_items'] = array_values(array_filter($result['line_items'],
            fn ($li) => ! in_array($li['code'], ['loan_deduction', 'salary_advance_deduction'], true) || $li['amount'] > 0));
    }

    /**
     * Fit the salary advance and the loan EMIs into what's left of the
     * payslip ($available = gross − every other deduction): the ADVANCE first
     * (it is that month's salary already paid out), then the loan EMIs. What
     * doesn't fit stays due and is recovered in the next payroll.
     *
     * @return array{0: float, 1: float} [advance applied, loan applied]
     */
    public function capLoanAndAdvance(float $available, float $advanceDue, float $loanDue): array
    {
        $available = max(0.0, round($available, 2));
        $advance = round(min(max(0.0, $advanceDue), $available), 2);
        $loan = round(min(max(0.0, $loanDue), max(0.0, $available - $advance)), 2);

        return [$advance, $loan];
    }

    /**
     * Applies $deductedAmount against this month's due loan installments
     * (EMI and lumpsum both) via LoanDeductionService, oldest-due-first,
     * full/partial per item. Does NOT open its own DB transaction -- the
     * caller already has one open.
     */
    public function updateLoanRepayments(int $userId, string $yearMonth, float $deductedAmount, ?int $tenantId = null, ?int $monthlyPayrollId = null, ?string $kind = \App\Models\Loan::KIND_LOAN): void
    {
        if (! $tenantId) {
            return;
        }
        if ($deductedAmount <= 0) {
            // Nothing to collect now — still undo what this payslip collected for this kind before.
            if ($monthlyPayrollId) {
                $this->loanDeductionService->revokeForPayroll($monthlyPayrollId, $tenantId, $kind);
            }

            return;
        }

        $result = $this->loanDeductionService->applyDeduction(
            $userId,
            $tenantId,
            $yearMonth,
            $deductedAmount,
            $monthlyPayrollId,
            Auth::id(),
            \App\Models\LoanRepayment::PAYMENT_MODE_SALARY_DEDUCTION,
            $kind
        );

        if ($result['applied_total'] > 0) {
            Log::channel('daily')->info('Loan Repayment Processed', [
                'user_id' => $userId,
                'month' => $yearMonth,
                'applied_total' => $result['applied_total'],
                'shortfall' => $result['shortfall'],
                'unapplied_excess' => $result['unapplied_excess'],
            ]);
        }
    }
}
