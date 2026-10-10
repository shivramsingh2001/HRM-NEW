<?php

namespace App\Services\Payroll;

use App\Services\Attendance\PayrollDaysService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payroll rebuild — Phase 2.
 *
 * Builds the attendance/overtime/loan context a payslip calculation needs,
 * for one employee for one month. Deliberately does NOT re-derive day
 * classification (present/absent/leave/holiday/weekoff, the sandwich rule)
 * itself — it delegates entirely to PayrollDaysService, the codebase's
 * existing tenant-configurable "Tier 2" attendance source (which already
 * reads attendance_policies per tenant instead of the legacy payroll
 * controller's hardcoded 20%/60%/2h/6h thresholds). This is the "extract,
 * don't duplicate" half of the Phase 2 plan for the attendance side; only
 * the overtime-approval and loan-deduction lookups are ported here since
 * they're short, self-contained, and outside PayrollDaysService's scope.
 */
class PayrollAttendanceContextBuilder
{
    public function __construct(
        private PayrollDaysService $payrollDays,
        private LoanDeductionService $loanDeductionService
    ) {
    }

    /**
     * $dayOverrides (optional): present_days/paid_leave_days/week_offs/holidays
     * submitted from the Monthly Payroll Edit form — merged on top of the
     * attested attendance-derived values before payable_days/proration are
     * (re)computed, so an HR edit to Week Off/Absent/Holiday actually flows
     * through to the real per-component proration, not just a display field.
     */
    public function build(int $userId, int $tenantId, string $yearMonth, ?array $dayOverrides = null): array
    {
        $start = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();

        $days = $this->payrollDays->forMonth($userId, $yearMonth, $tenantId);

        if (! empty($dayOverrides)) {
            $days = array_merge($days, array_intersect_key(
                $dayOverrides,
                array_flip(['present_days', 'paid_leave_days', 'week_offs', 'holidays'])
            ));
            // A worked holiday / week-off is inside present_days AND holidays / week_offs — paid once.
            $days['payable_days'] = max(0, $days['present_days'] - ($days['worked_off_day_credit'] ?? 0)) + $days['paid_leave_days'] + $days['week_offs'] + $days['holidays'];
        }

        $overtime = $this->approvedOvertimeHours($userId, $tenantId, $start->toDateString(), $end->toDateString());
        // Multi-shift: 2nd+ shift hours are automatic overtime, on top of requests.
        $extraShift = app(\App\Services\Attendance\ExtraShiftOvertime::class)
            ->forPeriod($userId, $tenantId, $start->toDateString(), $end->toDateString());
        $employeePolicy = app(\App\Services\EmployeePolicyService::class);
        // Marked "not eligible for overtime" on their profile: nothing is paid,
        // neither approved requests nor additional-shift hours.
        if (! $employeePolicy->overtimeEligible($tenantId, $userId)) {
            $overtime = ['total_hours' => 0.0, 'request_count' => 0];
            $extraShift = ['total_hours' => 0.0, 'details' => []];
        }
        // An employee's custom overtime rate wins over the company's.
        $overtimeRateMultiplier = (float) ($employeePolicy->section($tenantId, $userId, 'overtime')['rate_multiplier']
            ?? $this->overtimeRateMultiplier($tenantId));
        $loanDeduction = $this->loanDeduction($userId, $tenantId, $yearMonth);
        // Salary advances against this month (Loans & Advances) — their own payslip line, deducted before loan EMIs.
        $salaryAdvanceDeduction = $this->loanDeductionService->totalDue($userId, $tenantId, $yearMonth, \App\Models\Loan::KIND_SALARY_ADVANCE);

        // start/end are always the first/last day of the same calendar
        // month (startOfMonth()/endOfMonth()), so this is just the month's
        // day count — computing it via diffInDays() on Carbon instances
        // with sub-second precision from startOfMonth/endOfMonth produced
        // floating-point artifacts (e.g. 30.999999999988) that then
        // silently corrupted every proration factor downstream.
        $calendarDays = $start->daysInMonth;

        return [
            'year_month' => $yearMonth,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'calendar_days' => $calendarDays,

            'payable_days' => $days['payable_days'],
            'present_days' => $days['present_days'],
            'half_days' => $days['half_days'],
            'absent_days' => $days['absent_days'],
            'paid_leave_days' => $days['paid_leave_days'],
            'unpaid_leave_days' => $days['unpaid_leave_days'],
            'holidays' => $days['holidays'],
            'week_offs' => $days['week_offs'],

            'actual_worked_hours' => $days['actual_worked_hours'],
            'attendance_overtime_hours' => $days['overtime_hours'],

            'approved_overtime_hours' => round($overtime['total_hours'] + $extraShift['total_hours'], 2),
            'extra_shift_overtime_hours' => $extraShift['total_hours'],
            'overtime_rate_multiplier' => $overtimeRateMultiplier,

            'loan_deduction_amount' => $loanDeduction,
            'salary_advance_deduction_amount' => $salaryAdvanceDeduction,

            // Ready-made proration factors, clamped to [0, 1]. Individual
            // components still choose which one applies via their own
            // proration_rule — this context just precomputes the inputs.
            'proration_by_payable_days' => $calendarDays > 0
                ? max(0.0, min(1.0, $days['payable_days'] / $calendarDays))
                : 0.0,
            'proration_by_lop_days' => $calendarDays > 0
                ? max(0.0, min(1.0, ($calendarDays - $days['unpaid_leave_days']) / $calendarDays))
                : 0.0,
        ];
    }

    /**
     * Ported directly from MonthlyPayrollController::getApprovedOvertimeHours
     * — short and self-contained enough that delegating would mean reaching
     * into a private method on an unrelated controller. Always uses
     * app('current_tenant') semantics via the passed-in $tenantId (the
     * caller is responsible for resolving it correctly), never session().
     */
    private function approvedOvertimeHours(int $userId, int $tenantId, string $startDate, string $endDate): array
    {
        $rows = DB::select("
            SELECT COALESCE(approved_hours, overtime_hours) AS hours
            FROM overtime_requests
            WHERE user_id = ? AND tenant_id = ? AND status = 'approved'
              AND date BETWEEN ? AND ?
        ", [$userId, $tenantId, $startDate, $endDate]);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row->hours;
        }

        return ['total_hours' => round($total, 2), 'request_count' => count($rows)];
    }

    /**
     * Tenant-specific overtime_settings row takes priority over the global
     * (tenant_id IS NULL) fallback row — same precedence as the legacy
     * controller. Defaults to 1.5x only if no settings row exists at all.
     */
    private function overtimeRateMultiplier(int $tenantId): float
    {
        $row = DB::selectOne("
            SELECT rate_multiplier FROM overtime_settings
            WHERE tenant_id = ? OR tenant_id IS NULL
            ORDER BY (tenant_id IS NULL) ASC
            LIMIT 1
        ", [$tenantId]);

        return $row ? (float) $row->rate_multiplier : 1.5;
    }

    /**
     * Delegates to LoanDeductionService::totalDue() -- read-only lookup, no
     * ledger mutation (that stays LoanDeductionService::applyDeduction()'s
     * responsibility, called from the payroll controller once a payslip is
     * actually persisted). Considers EMI and lumpsum loans both.
     */
    private function loanDeduction(int $userId, int $tenantId, string $yearMonth): float
    {
        return $this->loanDeductionService->totalDue($userId, $tenantId, $yearMonth, \App\Models\Loan::KIND_LOAN);
    }
}
