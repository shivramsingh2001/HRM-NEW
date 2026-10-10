<?php

namespace App\Services\Payroll\Legacy;

use App\Models\LeaveType;
use App\Models\UserPayroll;
use App\Services\Payroll\PayrollCalendar;
use App\Services\Payroll\PayrollLoanApplier;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The legacy fixed-column payroll engine's calculation (day-based and
 * hour-based), moved out of MonthlyPayrollController unchanged (code-quality
 * plan, Phase 1). calculate() returns everything a payslip needs; it writes
 * nothing — PayslipWriter::createLegacy() persists it. The helpers are public
 * because Edit Payroll (PayrollEditService) and the tests reuse them.
 *
 * Companies on the dynamic engine use PayrollCalculationEngine instead.
 */
class LegacyPayrollCalculator
{
    public function __construct(
        private PayrollCalendar $calendar,
        private PayrollLoanApplier $loans,
    ) {}

    /**
     * Calculate one employee's month on the legacy fixed-column engine. Pure
     * calculation — PayslipWriter::createLegacy() persists the result.
     *
     * @return array{columns: array, earnings: array, employee_deductions: array, employer_contributions: array,
     *               earning_names: array, advance_deduction: float, loan_deduction: float}
     */
    public function calculate(
        $employee,
        ?int $tenantId,
        string $yearMonth,
        string $startDate,
        string $endDate,
        bool $includeOvertime = true,
        bool $includeLoanDeductions = true
    ) {
        // Use the salary structure that was actually effective during the
        // month being processed — not whichever row is flagged is_current
        // *today*. Without this, re-running or backfilling a historical
        // month after a raise would silently apply today's salary instead
        // of the one in effect back then.
        $userPayroll = UserPayroll::forUser($employee->id)
            ->effective($endDate)
            ->orderByDesc('effective_from')
            ->first();

        if (! $userPayroll) {
            throw new \Exception('No active payroll assignment found.');
        }

        $payrollMaster = $userPayroll->payrollMaster;
        $calculationType = $payrollMaster->payroll_calculation_type ?? 'day_based';
        $workingHoursPerDay = $payrollMaster->working_hours_per_day ?? 8;

        Log::channel('daily')->info('===== PAYROLL CALCULATION START =====', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'year_month' => $yearMonth,
            'calculation_type' => $calculationType,
            'working_hrs_per_day' => $workingHoursPerDay,
        ]);

        // Get ALL holiday and weekoff dates (raw SQL -- tenant_id scoped manually)
        $holidayDates = $this->calendar->getAllHolidayDates($startDate, $endDate, $tenantId);
        $weekoffDates = $this->calendar->getAllWeekoffDates($employee->id, $startDate, $endDate, $tenantId);

        Log::channel('daily')->info('DEBUG: weekoff/holiday results', [
            'employee_id' => $employee->id,
            'weekoff_count' => count($weekoffDates),
            'weekoff_dates' => $weekoffDates,
            'holiday_count' => count($holidayDates),
            'holiday_dates' => $holidayDates,
        ]);

        // Classify every day with priority + half-day logic
        $dayBreakdown = $this->calculateDayBreakdownWithPriority(
            $employee->id,
            $startDate,
            $endDate,
            $holidayDates,
            $weekoffDates,
            $tenantId
        );

        $presentDays = $dayBreakdown['present_days'];   // float e.g. 10.5
        $halfDays = $dayBreakdown['half_days'];
        $paidLeaves = $dayBreakdown['paid_leaves'];
        $unpaidLeaves = $dayBreakdown['unpaid_leaves'];
        $paidHolidays = $dayBreakdown['paid_holidays'];
        $paidWeekoffs = $dayBreakdown['paid_weekoffs'];
        $totalHolidays = $dayBreakdown['total_holidays'];
        $totalWeekoffs = $dayBreakdown['total_weekoffs'];
        $absentDays = $dayBreakdown['absent_days'];

        $calendarDays = Carbon::createFromFormat('Y-m', $yearMonth)->daysInMonth;
        $workingDays = max(1, $calendarDays - $totalHolidays - $totalWeekoffs);

        // Initialise
        $earnings = [];
        $employeeDeductions = [];
        $employerContributions = [];
        $payableDays = 0.0;
        $prorationFactor = 0.0;
        $expectedHours = 0.0;
        $actualWorkedHours = 0.0;
        $hourlyRate = 0.0;
        $overtimeHoursCalculated = 0.0;
        $approvedOvertimeHours = 0.0;
        $overtimeAmount = 0.0;
        $overtimeRateApplied = 0.0;

        // Get attendance summary (uses raw SQL)
        $attendanceSummary = $this->calculateAttendanceSummary($employee->id, $startDate, $endDate, $tenantId, (float) $workingHoursPerDay);

        // =====================================================================
        // HOUR-BASED CALCULATION - FIXED VERSION
        // =====================================================================
        if ($calculationType === 'hour_based') {

            // 1. Get actual worked hours from attendance
            $totalActualHours = $attendanceSummary['total_working_hours'];

            // 2. Expected total hours based on working days
            $expectedTotalHours = $workingDays * $workingHoursPerDay;

            // 3. IMPORTANT FIX: Add paid leave hours to payable hours
            //    Paid leaves should be compensated even though no actual work was done
            $paidLeaveHours = $paidLeaves * $workingHoursPerDay;
            $totalPayableHours = $totalActualHours + $paidLeaveHours;

            // 4. Calculate hourly rate
            $hourlyRate = $this->overtimeHourlyRate(
                $userPayroll->basic_salary, 'hour_based', $workingHoursPerDay, $calendarDays, $workingDays,
                $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days', $payrollMaster->ot_fixed_working_days ?? 26
            );

            // 5. Proration factor based on payable hours (capped at 100%)
            $prorationFactor = $expectedTotalHours == 0
                ? 0.0
                : min(1.0, $totalPayableHours / $expectedTotalHours);

            // 6. Basic salary = payable hours × hourly rate
            $basicSalary = $totalPayableHours * $hourlyRate;

            // 7. Cap at full month salary (in case of OT or rounding)
            $basicSalary = min($basicSalary, $userPayroll->basic_salary);

            // Store for audit
            $expectedHours = $expectedTotalHours;
            $actualWorkedHours = $totalActualHours;

            // Log the calculation for debugging
            Log::channel('daily')->info('Hour-Based Payroll Calculation (Fixed)', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => $yearMonth,
                'actual_worked_hours' => $totalActualHours,
                'paid_leave_days' => $paidLeaves,
                'paid_leave_hours' => $paidLeaveHours,
                'total_payable_hours' => $totalPayableHours,
                'expected_hours' => $expectedTotalHours,
                'hourly_rate' => round($hourlyRate, 2),
                'proration_factor' => round($prorationFactor, 4),
                'basic_salary' => round($basicSalary, 2),
                'full_month_salary' => $userPayroll->basic_salary,
                'working_days' => $workingDays,
                'hours_per_day' => $workingHoursPerDay,
            ]);

            // Overtime from approved requests
            $overtimeData = $this->getApprovedOvertimeHours($employee->id, $yearMonth, $tenantId);
            $approvedOvertimeHours = $overtimeData['total_hours'];

            if ($includeOvertime && $approvedOvertimeHours > 0) {
                $overtimeSettings = $this->getOvertimeSettings($tenantId, (int) $employee->id);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                // Company Policies → Overtime rate: hourly × multiplier, or a fixed amount per hour.
                $overtimeRateApplied = app(\App\Services\Payroll\OvertimePayService::class)->ratePerHour((int) $tenantId, (int) $employee->id, $hourlyRate);
                $overtimeAmount = $approvedOvertimeHours * $overtimeRateApplied;

                Log::channel('daily')->info('Overtime Calculation (Hour-Based - Fixed)', [
                    'employee_id' => $employee->id,
                    'approved_hours' => $approvedOvertimeHours,
                    'rate_multiplier' => $rateMultiplier,
                    'hourly_rate' => round($hourlyRate, 2),
                    'overtime_rate' => round($overtimeRateApplied, 2),
                    'overtime_amount' => round($overtimeAmount, 2),
                    'request_count' => $overtimeData['request_count'],
                ]);
            }

            // Overtime hours calculated from attendance records
            $overtimeHoursCalculated = $attendanceSummary['overtime_hours'];

            // Payable days (for display purposes only)
            $payableDays = $presentDays + $paidLeaves + $paidHolidays + $paidWeekoffs;

            // Earnings - prorated based on proration factor
            $earnings = [
                'basic' => round($basicSalary, 2),
                'hra' => round($userPayroll->hra * $prorationFactor, 2),
                'conveyence' => round($userPayroll->conveyence * $prorationFactor, 2),
                'medical' => round($userPayroll->medical_allowance * $prorationFactor, 2),
                'children' => round($userPayroll->children_allowance * $prorationFactor, 2),
                'post' => round($userPayroll->post_allowance * $prorationFactor, 2),
                'lta' => round($userPayroll->leave_travel_allowance * $prorationFactor, 2),
                'incentive' => round($userPayroll->monthly_incentive * $prorationFactor, 2),
                'special' => round($userPayroll->special_allowance * $prorationFactor, 2),
                'overtime' => round($overtimeAmount, 2),
            ];

            // Get deductions and employer contributions
            $deductionResult = $this->calculateDeductions($userPayroll, $prorationFactor);
            $employeeDeductions = $deductionResult['employee_deductions'];
            $employerContributions = $deductionResult['employer_contributions'];

            // =====================================================================
            // DAY-BASED CALCULATION (unchanged)
            // =====================================================================
        } else {

            $payableDays = $presentDays + $paidLeaves + $paidHolidays + $paidWeekoffs;

            $prorationFactor = $calendarDays == 0
                ? 0.0
                : min(1.0, $payableDays / $calendarDays);

            $earnings = $this->calculateEarnings($userPayroll, $prorationFactor);

            // Get deductions and employer contributions
            $deductionResult = $this->calculateDeductions($userPayroll, $prorationFactor);
            $employeeDeductions = $deductionResult['employee_deductions'];
            $employerContributions = $deductionResult['employer_contributions'];

            // Overtime from approved requests
            $overtimeData = $this->getApprovedOvertimeHours($employee->id, $yearMonth, $tenantId);
            $approvedOvertimeHours = $overtimeData['total_hours'];

            if ($includeOvertime && $approvedOvertimeHours > 0) {
                $overtimeSettings = $this->getOvertimeSettings($tenantId, (int) $employee->id);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                $hourlyRate = $this->overtimeHourlyRate(
                    $userPayroll->basic_salary, 'day_based', $workingHoursPerDay, $calendarDays, $workingDays,
                    $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days', $payrollMaster->ot_fixed_working_days ?? 26
                );
                $overtimeRateApplied = app(\App\Services\Payroll\OvertimePayService::class)->ratePerHour((int) $tenantId, (int) $employee->id, $hourlyRate);
                $overtimeAmount = $approvedOvertimeHours * $overtimeRateApplied;

                Log::channel('daily')->info('Overtime Calculation (Day-Based)', [
                    'employee_id' => $employee->id,
                    'approved_hours' => $approvedOvertimeHours,
                    'rate_multiplier' => $rateMultiplier,
                    'hourly_rate' => round($hourlyRate, 2),
                    'overtime_rate' => round($overtimeRateApplied, 2),
                    'overtime_amount' => round($overtimeAmount, 2),
                    'request_count' => $overtimeData['request_count'],
                ]);
            }

            $overtimeHoursCalculated = $approvedOvertimeHours;
            $earnings['overtime'] = round($overtimeAmount, 2);
        }

        // Shift allowance (Shifts → allowance per day / per hour) for the shifts actually worked.
        $shiftAllowance = app(\App\Services\Payroll\ShiftAllowanceCalculator::class)
            ->calculate((int) $tenantId, (int) $employee->id, $yearMonth);
        if ($shiftAllowance['amount'] > 0) {
            $earnings['shift_allowance'] = $shiftAllowance['amount'];
        }

        // =====================================================================
        // LOAN DEDUCTIONS -- always computed (for display via
        // loan_deduction_computed) even when $includeLoanDeductions is
        // false; only applied to totals/ledger when the flag is on.
        // =====================================================================
        $loanDeductionDue = $this->loans->calculateLoanDeductions($employee->id, $yearMonth, $tenantId);
        $loanDeductions = $includeLoanDeductions ? $loanDeductionDue : 0.0;
        // Salary advance against this month (Loans & Advances) — its own line, recovered before loan EMIs.
        $advanceDue = $this->loans->calculateLoanDeductions($employee->id, $yearMonth, $tenantId, \App\Models\Loan::KIND_SALARY_ADVANCE);
        $advanceDeduction = $includeLoanDeductions ? $advanceDue : 0.0;

        // Bulk generation has no per-employee UI to interactively resolve a
        // shortfall the way Edit Payroll does -- so instead of allowing a
        // negative net pay, cap the deductions at what's actually available
        // (advance first, then EMIs) and let this get flagged for review.
        $availableForLoan = max(0, array_sum($earnings) - array_sum($employeeDeductions));
        [$cappedAdvance, $cappedLoan] = $this->loans->capLoanAndAdvance($availableForLoan, $advanceDeduction, $loanDeductions);
        if ($cappedLoan < $loanDeductions || $cappedAdvance < $advanceDeduction) {
            Log::channel('daily')->warning('Loan / advance deduction capped during bulk generation', [
                'employee_id' => $employee->id,
                'month' => $yearMonth,
                'advance_due' => $advanceDeduction, 'advance_capped_to' => $cappedAdvance,
                'loan_due' => $loanDeductions, 'loan_capped_to' => $cappedLoan,
            ]);
        }
        $loanDeductions = $cappedLoan;
        $advanceDeduction = $cappedAdvance;

        if ($advanceDeduction > 0) {
            $employeeDeductions['advance'] = $advanceDeduction;
        }

        if ($loanDeductions > 0) {
            $employeeDeductions['loan'] = $loanDeductions;

            Log::channel('daily')->info('Loan Deductions Applied', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'month' => $yearMonth,
                'total_loan_deduction' => $loanDeductions,
            ]);
        }

        // =====================================================================
        // LATE ARRIVAL / EARLY LEAVING DEDUCTIONS -- independent of whether
        // the tenant's attendance-status action (half day/absent) is even
        // enabled; see App\Services\Payroll\LateEarlyDeductionCalculator.
        // =====================================================================
        $lateEarly = app(\App\Services\Payroll\LateEarlyDeductionCalculator::class)
            ->calculate($employee, $tenantId, $yearMonth);

        if ($lateEarly['late_deduction_amount'] > 0) {
            $employeeDeductions['late'] = $lateEarly['late_deduction_amount'];
        }
        if ($lateEarly['early_deduction_amount'] > 0) {
            $employeeDeductions['early'] = $lateEarly['early_deduction_amount'];
        }

        // =====================================================================
        // TOTALS
        // =====================================================================
        $grossEarnings = array_sum($earnings);
        $totalEmployeeDeductions = array_sum($employeeDeductions);
        $totalEmployerContributions = array_sum($employerContributions);
        $netPayable = $grossEarnings - $totalEmployeeDeductions;

        Log::channel('daily')->info('Payroll Calculation Summary:', [
            'calculation_type' => $calculationType,
            'calendar_days' => $calendarDays,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'paid_leaves' => $paidLeaves,
            'unpaid_leaves' => $unpaidLeaves,
            'paid_holidays' => $paidHolidays,
            'paid_weekoffs' => $paidWeekoffs,
            'payable_days' => $payableDays,
            'proration_factor' => $prorationFactor,
            'absent_days' => $absentDays,
            'expected_hours' => $expectedHours,
            'actual_worked_hours' => $actualWorkedHours,
            'hourly_rate' => round($hourlyRate, 2),
            'loan_deduction' => $loanDeductions,
            'gross_earnings' => $grossEarnings,
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
            'total_deductions' => $totalEmployeeDeductions,
            'net_payable' => $netPayable,
        ]);

        // =====================================================================
        // PAYSLIP COLUMNS -- persisted by PayslipWriter::createLegacy()
        // =====================================================================
        $columns = [
            'user_id' => $employee->id,
            'employee_payroll_id' => $userPayroll->id,
            'payroll_month' => $yearMonth,
            'processing_date' => now(),
            'total_working_days' => $calculationType === 'hour_based' ? $workingDays : $calendarDays,
            'payable_days' => $payableDays,
            'present_days' => $presentDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'paid_leaves' => $paidLeaves,
            'unpaid_leaves' => $unpaidLeaves,
            'holidays' => $totalHolidays,
            'week_offs' => $paidWeekoffs,
            'overtime_hours' => $calculationType === 'hour_based'
                ? $attendanceSummary['overtime_hours']
                : $approvedOvertimeHours,
            'overtime_hours_calculated' => round($overtimeHoursCalculated, 2),
            'overtime_rate' => round($overtimeRateApplied, 2),
            'overtime_amount' => $earnings['overtime'] ?? 0,
            'shift_allowance_amount' => $earnings['shift_allowance'] ?? 0,
            'shift_allowance_days' => $shiftAllowance['days'],
            'expected_hours' => round($expectedHours, 2),
            'actual_worked_hours' => round($actualWorkedHours, 2),
            'hourly_rate' => round($hourlyRate, 2),
            'basic_salary' => $earnings['basic'],
            'hra' => $earnings['hra'],
            'conveyence' => $earnings['conveyence'],
            'medical_allowance' => $earnings['medical'],
            'children_allowance' => $earnings['children'] ?? 0,
            'post_allowance' => $earnings['post'] ?? 0,
            'leave_travel_allowance' => $earnings['lta'] ?? 0,
            'monthly_incentive' => $earnings['incentive'] ?? 0,
            'special_allowance' => $earnings['special'] ?? 0,
            // Employee Deductions (deducted from salary)
            'provident_fund' => $employeeDeductions['pf'] ?? 0,
            'esi' => $employeeDeductions['esi'] ?? 0,
            'professional_tax' => $employeeDeductions['pt'] ?? 0,
            'tds' => $employeeDeductions['tds'] ?? 0,
            'loan_deduction' => $employeeDeductions['loan'] ?? 0,
            'loan_deduction_enabled' => $includeLoanDeductions,
            'loan_deduction_computed' => $loanDeductionDue,
            'salary_advance_deduction' => $employeeDeductions['advance'] ?? 0,
            'salary_advance_deduction_computed' => $advanceDue,
            'late_deduction' => $employeeDeductions['late'] ?? 0,
            'early_deduction' => $employeeDeductions['early'] ?? 0,
            'other_deductions' => $employeeDeductions['other'] ?? 0,
            // Employer Contributions (STORED but NOT deducted from salary)
            'employer_provident_fund' => $employerContributions['employer_pf'] ?? 0,
            'employer_esi' => $employerContributions['employer_esi'] ?? 0,
            // Totals
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalEmployeeDeductions,
            'net_payable' => $netPayable,
            'payment_status' => 'pending',
            'processed_by' => Auth::id(),
            'remarks' => $this->generateRemarks(
                $presentDays,
                $halfDays,
                $absentDays,
                $paidLeaves,
                $paidHolidays,
                $paidWeekoffs
            ),
        ];

        return [
            'columns' => $columns,
            'earnings' => $earnings,
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
            'earning_names' => ['shift_allowance' => $shiftAllowance['label']],
            'advance_deduction' => $advanceDeduction,
            'loan_deduction' => $loanDeductions,
        ];
    }

    /**
     * Calculate attendance status based on shift timings.
     *
     * Delegates to the same App\Services\Attendance\PolicyResolver /
     * AttendancePolicySnapshot::classify() the Attendance module itself
     * uses, instead of the hardcoded 60%/20%/2h/6h thresholds this method
     * used to carry — those diverged from the tenant's real, configurable
     * attendance_policies row, so the same day could be classified
     * differently by Payroll and by Attendance. $tenantId/$date are used
     * only to resolve which policy is in force; when either is missing
     * (defensive — both call sites always have them) this falls back to
     * AttendancePolicySnapshot::default(), the same values this method's
     * old hardcoded fallback ladder approximated.
     */
    public function getAttendanceStatusByShift($totalHours, $attendance = null, ?int $tenantId = null, ?string $date = null, ?int $userId = null)
    {
        $policy = ($tenantId && $date)
            ? ($userId
                ? app(\App\Services\Attendance\PolicyResolver::class)->forUserDate($tenantId, $userId, $date)
                : app(\App\Services\Attendance\PolicyResolver::class)->forTenantDate($tenantId, $date))
            : \App\Services\Attendance\AttendancePolicySnapshot::default();

        $expectedSeconds = 0;
        if ($attendance && isset($attendance->scheduled_shift_start) && $attendance->scheduled_shift_start &&
            isset($attendance->scheduled_shift_end) && $attendance->scheduled_shift_end) {
            // Overnight shifts (e.g. 22:00 to 06:00) end on the next day.
            $expectedSeconds = ShiftWindow::spanMinutes($attendance->scheduled_shift_start, $attendance->scheduled_shift_end) * 60;
        }

        return match ($policy->classify((float) ($totalHours ?? 0), $expectedSeconds)) {
            'present' => 'Present',
            'half_day' => 'Half Day',
            default => 'Absent',
        };
    }

    /**
     * Classify every calendar day in the month.
     *
     * Priority order (highest wins):
     *   1. Attendance record exists with shift-based calculation
     *        >= 60% of shift -> full present (1.0)
     *        20-60% of shift -> half day (0.5)
     *        < 20% of shift -> no credit, fall through
     *   2. Holiday
     *   3. Weekoff
     *   4. Approved leave (paid / unpaid)
     *   5. Absent
     */
    public function calculateDayBreakdownWithPriority(
        int $userId,
        string $startDate,
        string $endDate,
        array $holidayDates,
        array $weekoffDates,
        ?int $tenantId = null
    ): array {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Fast lookup sets (O(1) membership check, no ambiguity)
        $holidaySet = array_flip($holidayDates);
        $weekoffSet = array_flip($weekoffDates);

        // ------------------------------------------------------------------
        // Step 1 -- Attendance via raw SQL (tenant_id scoped)
        // Include scheduled_shift_start/end for shift-based calculation
        // ------------------------------------------------------------------
        $attendanceRows = DB::select("
            SELECT
                DATE_FORMAT(date, '%Y-%m-%d') AS att_date,
                total_hours,
                worked_hours,
                scheduled_shift_start,
                scheduled_shift_end
            FROM attendances
            WHERE user_id   = ?
              AND tenant_id = ?
              AND status    = 1
              AND date BETWEEN ? AND ?
        ", [$userId, $tenantId, $startDate, $endDate]);

        $attendanceSet = []; // date => 'present' | 'half_day'
        foreach ($attendanceRows as $row) {
            $dateStr = $row->att_date;

            // Calculate total hours using worked_hours first, fallback to total_hours
            $hours = null;
            if ($row->worked_hours !== null && $row->worked_hours !== '') {
                $hours = (float) $row->worked_hours;
            } else {
                $hours = $this->parseHours($row->total_hours ?? '0');
            }

            // Create attendance object for shift-based calculation
            $attendanceObj = new \stdClass;
            $attendanceObj->scheduled_shift_start = $row->scheduled_shift_start;
            $attendanceObj->scheduled_shift_end = $row->scheduled_shift_end;

            // Use shift-based status calculation
            $status = $this->getAttendanceStatusByShift($hours, $attendanceObj, $tenantId, $dateStr, $userId);

            if ($status === 'Present') {
                $attendanceSet[$dateStr] = 'present';
            } elseif ($status === 'Half Day') {
                $attendanceSet[$dateStr] = 'half_day';
            }
            // If 'Absent', no entry -> falls through
        }

        // ------------------------------------------------------------------
        // Step 2 -- Approved leaves via raw SQL (tenant_id scoped)
        // ------------------------------------------------------------------
        // Tenant-configurable unpaid-leave detection: leave_types.is_unpaid,
        // backfilled from the code='lwp' marker (see
        // 2026_09_12_201126_add_is_unpaid_to_leave_types_table). Replaces the
        // old `$unpaidLeaveTypeIds = [3]` hardcode, which only ever matched
        // one specific tenant's LWP row and silently misclassified every
        // other tenant's unpaid leave as paid.
        $unpaidLeaveTypeIds = LeaveType::where('tenant_id', $tenantId)
            ->where('is_unpaid', true)
            ->pluck('id')
            ->all();

        if (empty($unpaidLeaveTypeIds)) {
            throw new \Exception(
                'No leave type is configured as unpaid (Leave Without Pay) for this company. '.
                'Please mark the correct leave type as unpaid in Leave Type settings before running payroll.'
            );
        }

        $leaveRows = DB::select("
            SELECT
                DATE_FORMAT(start_date, '%Y-%m-%d') AS leave_start,
                DATE_FORMAT(end_date,   '%Y-%m-%d') AS leave_end,
                leave_type
            FROM leaves
            WHERE user_id   = ?
              AND tenant_id = ?
              AND status    = 'approved'
              AND (
                    start_date BETWEEN ? AND ?
                 OR end_date   BETWEEN ? AND ?
                 OR (start_date <= ? AND end_date >= ?)
              )
        ", [
            $userId, $tenantId,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
        ]);

        $leaveSet = []; // date => 'paid_leave' | 'unpaid_leave'
        foreach ($leaveRows as $leave) {
            $leaveStart = Carbon::parse($leave->leave_start)->max($start);
            $leaveEnd = Carbon::parse($leave->leave_end)->min($end);
            $isPaid = ! in_array($leave->leave_type, $unpaidLeaveTypeIds);
            $leaveType = $isPaid ? 'paid_leave' : 'unpaid_leave';

            $curLeave = $leaveStart->copy();
            while ($curLeave <= $leaveEnd) {
                $leaveSet[$curLeave->format('Y-m-d')] = $leaveType;
                $curLeave->addDay();
            }
        }

        // ------------------------------------------------------------------
        // Step 3 -- Classify every calendar day in ONE pass, explicit priority
        //   1. Attendance (present / half_day) - SHIFT BASED
        //   2. Holiday
        //   3. Weekoff
        //   4. Leave (paid / unpaid)
        //   5. Absent
        // ------------------------------------------------------------------
        $allDates = [];
        $cur = $start->copy();
        while ($cur <= $end) {
            $dateStr = $cur->format('Y-m-d');

            if (array_key_exists($dateStr, $attendanceSet)) {
                $allDates[$dateStr] = $attendanceSet[$dateStr];
            } elseif (array_key_exists($dateStr, $holidaySet)) {
                $allDates[$dateStr] = 'holiday';
            } elseif (array_key_exists($dateStr, $weekoffSet)) {
                $allDates[$dateStr] = 'weekoff';
            } elseif (array_key_exists($dateStr, $leaveSet)) {
                $allDates[$dateStr] = $leaveSet[$dateStr];
            } else {
                $allDates[$dateStr] = 'absent';
            }

            $cur->addDay();
        }

        // ------------------------------------------------------------------
        // VERIFICATION -- catch silent mismatches immediately
        // ------------------------------------------------------------------
        $actualWeekoffCount = count(array_filter($allDates, fn ($t) => $t === 'weekoff'));
        $expectedWeekoffCount = count(
            array_filter($weekoffDates, fn ($d) => $d >= $startDate && $d <= $endDate)
        );

        if ($actualWeekoffCount !== $expectedWeekoffCount) {
            Log::channel('daily')->error('WEEKOFF CLASSIFICATION MISMATCH', [
                'user_id' => $userId,
                'expected' => $expectedWeekoffCount,
                'actual' => $actualWeekoffCount,
                'weekoff_dates_in' => $weekoffDates,
                'all_dates' => $allDates,
            ]);
        }

        // ------------------------------------------------------------------
        // Tally
        // ------------------------------------------------------------------
        $presentDays = 0.0;
        $paidLeaves = 0.0;
        $unpaidLeaves = 0.0;
        $holidayCount = 0;
        $weekoffCount = 0;
        $absentDays = 0;
        $halfDayDates = [];

        foreach ($allDates as $date => $type) {
            switch ($type) {
                case 'present':
                    $presentDays += 1.0;
                    break;
                case 'half_day':
                    $presentDays += 0.5;
                    $halfDayDates[] = $date;
                    break;
                case 'paid_leave':
                    $paidLeaves += 1.0;
                    break;
                case 'unpaid_leave':
                    $unpaidLeaves += 1.0;
                    break;
                case 'holiday':
                    $holidayCount++;
                    break;
                case 'weekoff':
                    $weekoffCount++;
                    break;
                case 'absent':
                    $absentDays++;
                    break;
            }
        }

        // ------------------------------------------------------------------
        // Sandwich rule -- group consecutive non-working days and evaluate
        // both boundaries.
        //
        // BUG FIX: this used to run `SELECT sandwich FROM tenants`, but that
        // column does not exist anywhere in the schema (confirmed via
        // information_schema) -- the setting lives on attendance_policies
        // (sandwich_leave) instead, resolved via the tenant-configurable
        // PolicyResolver used elsewhere in the attendance module. The old
        // query threw a QueryException on every single call, which was
        // silently swallowed by store()'s per-employee try/catch -- meaning
        // day-based payroll processing failed for every employee, every
        // time, with the failure only visible as a per-employee error
        // message an admin could easily miss.
        // ------------------------------------------------------------------
        // Shared with the dynamic engine (PayrollDaysService) — see
        // App\Services\Attendance\SandwichRule. Paid leave counts as "worked"
        // next to a run of days off; the rule is read for the day it applies to.
        $policies = app(\App\Services\Attendance\PolicyResolver::class);
        $sandwichEnabled = $policies->forUserDate((int) $tenantId, (int) $userId, Carbon::parse($startDate)->toDateString())->sandwichLeave;
        $offDayPay = \App\Services\Attendance\SandwichRule::offDayPay(
            $allDates,
            fn (string $date) => $policies->forUserDate((int) $tenantId, (int) $userId, $date)->sandwichLeave
        );

        $paidHolidayDates = [];
        $paidWeekoffDates = [];
        foreach ($offDayPay as $date => $paid) {
            if ($paid) {
                $allDates[$date] === 'holiday' ? $paidHolidayDates[] = $date : $paidWeekoffDates[] = $date;
            }
        }

        Log::channel('daily')->info('Sandwich Rule Result', [
            'user_id' => $userId,
            'sandwich_enabled' => $sandwichEnabled,
            'groups_evaluated' => count($offDayPay),
            'paid_holidays' => count($paidHolidayDates),
            'paid_weekoffs' => count($paidWeekoffDates),
            'paid_holiday_dates' => $paidHolidayDates,
            'paid_weekoff_dates' => $paidWeekoffDates,
        ]);

        return [
            'present_days' => $presentDays,
            'half_days' => count($halfDayDates),
            'paid_leaves' => $paidLeaves,
            'unpaid_leaves' => $unpaidLeaves,
            'total_holidays' => $holidayCount,
            'total_weekoffs' => $weekoffCount,
            'paid_holidays' => count($paidHolidayDates),
            'paid_weekoffs' => count($paidWeekoffDates),
            'absent_days' => $absentDays,
            'all_days' => $allDates,
        ];
    }

    // =========================================================================

    public function calculateAttendanceSummary(
        int $userId,
        string $startDate,
        string $endDate,
        ?int $tenantId = null,
        float $stdHoursPerDay = 8.0
    ): array {
        // Tier 2 / T2-E — source the figures from the one attested rollup so the
        // payslip always matches the attendance module. Same return keys.
        if (config('attendance.payroll_readthrough')) {
            $yearMonth = Carbon::parse($startDate)->format('Y-m');
            $pd = app(\App\Services\Attendance\PayrollDaysService::class)
                ->forMonth($userId, $yearMonth, $tenantId);

            return [
                'present_days' => $pd['present_days'] + ($pd['half_days'] * 0.5),
                'full_days' => (int) $pd['present_days'],
                'half_days' => (int) $pd['half_days'],
                'absent_by_hours' => (int) $pd['absent_days'],
                'total_working_hours' => round($pd['actual_worked_hours'], 2),
                'overtime_hours' => round($pd['overtime_hours'], 2),
            ];
        }

        $rows = DB::select("
            SELECT DATE_FORMAT(date, '%Y-%m-%d') AS att_date, total_hours, worked_hours, scheduled_shift_start, scheduled_shift_end
            FROM   attendances
            WHERE  user_id   = ?
              AND  tenant_id = ?
              AND  status    = 1
              AND  date BETWEEN ? AND ?
        ", [$userId, $tenantId, $startDate, $endDate]);

        $fullDays = 0;
        $halfDays = 0;
        $absentByHours = 0;
        $totalWorkingHours = 0.0;
        $overtimeHours = 0.0;

        foreach ($rows as $row) {
            // Calculate total hours using worked_hours first
            $hours = null;
            if ($row->worked_hours !== null && $row->worked_hours !== '') {
                $hours = (float) $row->worked_hours;
            } else {
                $hours = $this->parseHours($row->total_hours ?? '0');
            }

            // Create attendance object for shift-based calculation
            $attendanceObj = new \stdClass;
            $attendanceObj->scheduled_shift_start = $row->scheduled_shift_start;
            $attendanceObj->scheduled_shift_end = $row->scheduled_shift_end;

            $status = $this->getAttendanceStatusByShift($hours, $attendanceObj, $tenantId, $row->att_date, $userId);

            if ($status === 'Present') {
                $fullDays++;
                $totalWorkingHours += $hours;
                if ($hours > $stdHoursPerDay) {
                    $overtimeHours += ($hours - $stdHoursPerDay);
                }
            } elseif ($status === 'Half Day') {
                $halfDays++;
                $totalWorkingHours += $hours;
            } else {
                $absentByHours++;
                // < 20% shift or < 2 hours: do not add to working hours
            }
        }

        return [
            'present_days' => $fullDays + ($halfDays * 0.5),
            'full_days' => $fullDays,
            'half_days' => $halfDays,
            'absent_by_hours' => $absentByHours,
            'total_working_hours' => round($totalWorkingHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
        ];
    }

    // =========================================================================

    public function getApprovedOvertimeHours(int $userId, string $yearMonth, ?int $tenantId = null): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');

        // Marked "not eligible for overtime" on their profile: nothing is paid,
        // neither approved requests nor additional-shift hours.
        if ($tenantId && ! app(\App\Services\EmployeePolicyService::class)->overtimeEligible((int) $tenantId, $userId)) {
            return ['total_hours' => 0.0, 'details' => [], 'request_count' => 0, 'extra_shift_hours' => 0.0];
        }

        $rows = DB::select("
            SELECT
                DATE_FORMAT(date, '%Y-%m-%d') AS ot_date,
                COALESCE(approved_hours, overtime_hours) AS hours,
                reason
            FROM overtime_requests
            WHERE user_id   = ?
              AND tenant_id = ?
              AND status    = 'approved'
              AND date BETWEEN ? AND ?
        ", [$userId, $tenantId, $startDate, $endDate]);

        $totalHours = 0.0;
        $details = [];

        foreach ($rows as $row) {
            $totalHours += (float) $row->hours;
            $details[] = [
                'date' => $row->ot_date,
                'hours' => $row->hours,
                'reason' => $row->reason,
            ];
        }

        // Multi-shift: 2nd+ shift hours are automatic overtime, on top of requests.
        $extraShift = app(\App\Services\Attendance\ExtraShiftOvertime::class)
            ->forPeriod($userId, (int) $tenantId, $startDate, $endDate);
        $totalHours += $extraShift['total_hours'];
        $details = array_merge($details, $extraShift['details']);

        return [
            'total_hours' => round($totalHours, 2),
            'details' => $details,
            'request_count' => count($rows),
            'extra_shift_hours' => $extraShift['total_hours'],
        ];
    }

    /**
     * Fetch overtime settings for the tenant.
     * Tenant-specific row takes priority over a global (null tenant) fallback.
     */
    public function getOvertimeSettings(?int $tenantId, ?int $userId = null): ?object
    {
        $settings = DB::selectOne('
            SELECT *
            FROM   overtime_settings
            WHERE  tenant_id = ? OR tenant_id IS NULL
            ORDER BY (tenant_id IS NULL) ASC
            LIMIT 1
        ', [$tenantId]);

        // An employee's custom overtime values (Employee 360 → Policies) win over the company's.
        return ($tenantId && $userId)
            ? app(\App\Services\EmployeePolicyService::class)->overtime($tenantId, $userId, $settings)
            : $settings;
    }

    /**
     * Single source of truth for the hourly rate used to price overtime, so
     * the live edit-payroll preview can never drift from what generation
     * actually persists. Mirrors the two branches inline above exactly:
     *  - day_based:  (basic_salary / daysDivisor) / workingHoursPerDay
     *  - hour_based: basic_salary / (workingDays * workingHoursPerDay)
     *
     * $divisorMode controls what "daysDivisor" means for day_based payroll
     * only (hour_based already divides by actual working days, not calendar
     * days, so it isn't affected): 'calendar_days' (default — the original,
     * unchanged behavior) divides by the number of days in the month;
     * 'fixed_working_days' divides by a fixed count ($fixedWorkingDays,
     * commonly 26) instead, matching the statutory-OT-rate convention some
     * tenants expect. Per-tenant, via payroll_masters.ot_rate_divisor_mode —
     * defaults to 'calendar_days' for every existing master, so no tenant's
     * computed OT rate changes unless they explicitly opt in.
     */
    public function overtimeHourlyRate(
        float $basicSalary,
        string $calculationType,
        float $workingHoursPerDay,
        int $calendarDays,
        float $workingDays,
        string $divisorMode = 'calendar_days',
        int $fixedWorkingDays = 26
    ): float {
        return app(\App\Services\Payroll\OvertimePayService::class)->hourlyRate(
            $basicSalary, $calculationType, $workingHoursPerDay, $calendarDays, $workingDays, $divisorMode, $fixedWorkingDays
        );
    }

    /**
     * Price a batch of overtime hours (any mix of just-approved / still-pending
     * requests -- approval doesn't change the hours) at this payslip's real
     * hourly rate × the tenant's overtime_settings.rate_multiplier. Shared by
     * the Edit Payroll "non-approved overtime" preview and by update()'s
     * server-side recompute after auto-approving, so both can never disagree.
     */
    public function overtimeAmountForHours(
        float $hours,
        ?UserPayroll $userPayroll,
        string $payrollMonth,
        $totalWorkingDaysBasis,
        ?int $tenantId
    ): array {
        if ($hours <= 0 || ! $userPayroll) {
            return ['amount' => 0.0, 'rate' => 0.0];
        }

        $calcType = ($userPayroll->payrollMaster
            && $userPayroll->payrollMaster->payroll_calculation_type === 'hour_based')
            ? 'hour_based' : 'day_based';
        $workingHoursPerDay = $userPayroll->payrollMaster->working_hours_per_day ?? 8;
        $calendarDays = Carbon::createFromFormat('Y-m', $payrollMonth)->daysInMonth;
        $workingDaysBasis = $totalWorkingDaysBasis ?: $calendarDays;

        $hourlyRate = $this->overtimeHourlyRate(
            $userPayroll->basic_salary, $calcType, $workingHoursPerDay, $calendarDays, $workingDaysBasis,
            $userPayroll->payrollMaster->ot_rate_divisor_mode ?? 'calendar_days',
            $userPayroll->payrollMaster->ot_fixed_working_days ?? 26
        );
        $rate = app(\App\Services\Payroll\OvertimePayService::class)->ratePerHour((int) $tenantId, (int) $userPayroll->user_id, $hourlyRate);

        return ['amount' => round($hours * $rate, 2), 'rate' => $rate];
    }

    // =========================================================================

    public function calculateEarnings($userPayroll, float $prorationFactor): array
    {
        $prorationFactor = max(0.0, min(1.0, $prorationFactor));

        return [
            'basic' => round($userPayroll->basic_salary * $prorationFactor, 2),
            'hra' => round($userPayroll->hra * $prorationFactor, 2),
            'conveyence' => round($userPayroll->conveyence * $prorationFactor, 2),
            'medical' => round($userPayroll->medical_allowance * $prorationFactor, 2),
            'children' => round($userPayroll->children_allowance * $prorationFactor, 2),
            'post' => round($userPayroll->post_allowance * $prorationFactor, 2),
            'lta' => round($userPayroll->leave_travel_allowance * $prorationFactor, 2),
            'incentive' => round($userPayroll->monthly_incentive * $prorationFactor, 2),
            'special' => round($userPayroll->special_allowance * $prorationFactor, 2),
        ];
    }

    /**
     * Calculate employee deductions and employer contributions separately
     * Employee deductions are deducted from salary (PF, ESI, PT, TDS)
     * Employer contributions are NOT deducted (Employer PF, Employer ESI)
     */
    public function calculateDeductions($userPayroll, float $prorationFactor): array
    {
        $prorationFactor = max(0.0, min(1.0, $prorationFactor));

        // Employee deductions (deducted from salary)
        $employeeDeductions = [
            'pf' => round($userPayroll->provident_fund * $prorationFactor, 2),
            'esi' => round($userPayroll->esi * $prorationFactor, 2),
            'pt' => round($userPayroll->professional_tax * $prorationFactor, 2),
            'tds' => round($userPayroll->tds * $prorationFactor, 2),
            'other' => 0,
        ];

        // Employer contributions (STORED but NOT deducted from salary)
        $employerContributions = [
            'employer_pf' => round($userPayroll->employer_provident_fund * $prorationFactor, 2),
            'employer_esi' => round($userPayroll->employer_esi * $prorationFactor, 2),
        ];

        return [
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
        ];
    }

    /**
     * Parse a time string that may be "HH:MM" or a plain decimal float.
     */
    public function parseHours($timeString): float
    {
        if (empty($timeString)) {
            return 0.0;
        }
        if (strpos($timeString, ':') !== false) {
            [$h, $m] = explode(':', $timeString);

            return (float) $h + ((float) $m / 60);
        }

        return (float) $timeString;
    }

    // =========================================================================

    public function generateRemarks(
        float $present,
        int $halfDays,
        int $absent,
        float $leaves,
        int $holidays,
        int $weekoffs
    ): string {
        $parts = [];
        if ($present > 0) {
            $parts[] = "Present: {$present} days";
        }
        if ($halfDays > 0) {
            $parts[] = "Half days: {$halfDays}";
        }
        if ($absent > 0) {
            $parts[] = "Absent: {$absent} days";
        }
        if ($leaves > 0) {
            $parts[] = "Paid leaves: {$leaves} days";
        }
        if ($holidays > 0) {
            $parts[] = "Holidays: {$holidays} days";
        }
        if ($weekoffs > 0) {
            $parts[] = "Week offs: {$weekoffs} days";
        }

        if ($present == 0 && $leaves == 0) {
            $parts[] = 'NOTE: No salary paid -- zero attendance and no paid leaves';
        }

        return implode(', ', $parts);
    }
}
