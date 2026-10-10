<?php

namespace App\Services\Payroll;

use App\Models\User;
use App\Models\UserPayroll;
use Carbon\Carbon;

/**
 * "Estimated payroll" shown on Process Payroll before running it — full-month
 * gross, statutory deductions and loans per employee (legacy salary
 * structures). Moved out of MonthlyPayrollController::calculateEstimates()
 * unchanged (code-quality plan, Phase 1).
 */
class PayrollEstimator
{
    public function __construct(private LoanDeductionService $loanDeductionService) {}

    public function estimate($employeeIds, $payrollMonth, bool $includeLoans, ?int $tenantId): array
    {
        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $totalLoans = 0.0;

        // Anchor to the requested payroll month's end date, not "today",
        // so previewing an estimate for a past/backfilled month reflects
        // the salary structure actually in effect during that month.
        $estimateAsOf = $payrollMonth
            ? Carbon::parse($payrollMonth.'-01')->endOfMonth()->toDateString()
            : now()->toDateString();

        $employees = User::whereIn('id', $employeeIds)
            ->where('role', '!=', 'admin')
            ->get();

        foreach ($employees as $employee) {
            $payroll = UserPayroll::forUser($employee->id)
                ->effective($estimateAsOf)
                ->orderByDesc('effective_from')
                ->first();

            if ($payroll) {
                $grossForEmployee = (float) ($payroll->gross_salary ?? 0);
                $statutoryDeductions = (float) ($payroll->provident_fund ?? 0)
                    + (float) ($payroll->esi ?? 0)
                    + (float) ($payroll->professional_tax ?? 0);

                $loanForEmployee = 0.0;
                if ($includeLoans && $tenantId) {
                    $loanDue = $this->loanDeductionService->totalDue($employee->id, $tenantId, $payrollMonth);
                    // Same auto-cap policy bulk generation uses: never let
                    // the estimate (or the eventual run) push an
                    // employee's net pay negative -- there's no
                    // per-employee UI in a bulk preview to ask them to
                    // adjust it interactively the way Edit Payroll does.
                    $loanForEmployee = min($loanDue, max(0, $grossForEmployee - $statutoryDeductions));
                }

                $totalGross += $grossForEmployee;
                $totalDeductions += $statutoryDeductions + $loanForEmployee;
                $totalLoans += $loanForEmployee;
            }
        }

        return [
            'success' => true,
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_loans' => $totalLoans,
            'total_net' => $totalGross - $totalDeductions,
            'working_days' => Carbon::createFromFormat('Y-m', $payrollMonth)->daysInMonth,
            'proration_info' => 'Estimates are based on full-month gross. Actual values depend on attendance.',
        ];
    }
}
