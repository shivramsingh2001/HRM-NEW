<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\UserPayroll;
use App\Models\User;
use App\Models\Holiday;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\PayrollComponent;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MonthlyPayrollController extends Controller
{
    // =========================================================================
    // ATTENDANCE HOUR RULES - SHIFT BASED
    //   >= 60% of shift -> full present day (counts as 1.0)
    //   20% - 60% of shift -> half day (counts as 0.5)
    //   < 20% of shift -> treated as absent (no attendance credit)
    //   Fallback: >= 6 hours -> full day, >= 2 hours -> half day, < 2 hours -> absent
    // =========================================================================
    private const FULL_DAY_MIN_HOURS = 6;
    private const HALF_DAY_MIN_HOURS = 2;

    /**
     * Calculate attendance status based on shift timings
     * Priority 1: scheduled_shift_start/end from attendance record
     * Priority 2: hours-based fallback (2hrs=Absent, 2-6hrs=Half Day, 6hrs+=Present)
     */
    private function getAttendanceStatusByShift($totalHours, $attendance = null)
    {
        // PRIORITY 1: Use scheduled shift times from attendance record
        if ($attendance && isset($attendance->scheduled_shift_start) && $attendance->scheduled_shift_start && 
            isset($attendance->scheduled_shift_end) && $attendance->scheduled_shift_end) {
            $shiftStart = Carbon::parse($attendance->scheduled_shift_start);
            $shiftEnd = Carbon::parse($attendance->scheduled_shift_end);
            
            // Handle overnight shifts (e.g., 22:00 to 06:00)
            if ($shiftEnd->lessThan($shiftStart)) {
                $shiftEnd->addDay();
            }
            
            $expectedHours = $shiftStart->diffInHours($shiftEnd);
            
            if ($expectedHours > 0 && $totalHours !== null) {
                $percentage = ($totalHours / $expectedHours) * 100;
                
                // < 20% = Absent, 20-60% = Half Day, >= 60% = Present
                if ($percentage < 20) {
                    return 'Absent';
                } elseif ($percentage < 60) {
                    return 'Half Day';
                } else {
                    return 'Present';
                }
            }
        }
        
        // PRIORITY 2: Hours-based fallback
        if ($totalHours === null || $totalHours < 2) {
            return 'Absent';
        } elseif ($totalHours < 6) {
            return 'Half Day';
        } else {
            return 'Present';
        }
    }

    /**
     * Display monthly payroll list with filters.
     * NOTE: Eloquent (MonthlyPayroll / User) is auto tenant-scoped via the
     *       BelongsToTenant trait, so no explicit tenant_id needed here.
     */
    public function index(Request $request)
    {
        try {
            $query = MonthlyPayroll::with(['user', 'processor']);

            if ($request->filled('month')) {
                $query->where('payroll_month', $request->month);
            }
            if ($request->filled('status')) {
                $query->where('payment_status', $request->status);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $monthlyPayrolls = $query->orderBy('payroll_month', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            $users = User::where('status', 1)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']);

            $months = MonthlyPayroll::select('payroll_month')
                ->distinct()
                ->orderBy('payroll_month', 'desc')
                ->pluck('payroll_month');

            return view('client.payroll.monthly-payroll.index', compact('monthlyPayrolls', 'users', 'months'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll index error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payroll data: ' . $e->getMessage());
        }
    }

    /**
     * Show form to process new monthly payroll
     */
    public function create()
    {
        try {
            $months = [];
            for ($i = 0; $i < 12; $i++) {
                $date = now()->subMonths($i);
                $months[$date->format('Y-m')] = $date->format('F Y');
            }

            $employees = User::where('status', 1)
                ->where('role', '!=', 'admin')
                ->whereHas('userPayrolls', fn($q) => $q->where('is_current', true))
                ->with([
                    'userPayrolls'                    => fn($q) => $q->where('is_current', true),
                    'userPayrolls.payrollMaster',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel',
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'email']);

            return view('client.payroll.monthly-payroll.create', compact('months', 'employees'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll create error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading form: ' . $e->getMessage());
        }
    }

    /**
     * Process monthly payroll for one or more employees.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payroll_month'          => 'required|date_format:Y-m',
            'employee_selection'     => 'required|in:all,selected',
            'selected_employees'     => 'required_if:employee_selection,selected|array',
            'selected_employees.*'   => 'exists:users,id',
            'include_overtime'       => 'nullable|boolean',
            'include_loan_deductions' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $date      = Carbon::createFromFormat('Y-m', $request->payroll_month);
            $startDate = $date->copy()->startOfMonth()->format('Y-m-d');
            $endDate   = $date->copy()->endOfMonth()->format('Y-m-d');

            $employeeQuery = User::where('status', 1)
                ->whereHas('userPayrolls', fn($q) => $q->where('is_current', true))
                ->with([
                    'userPayrolls'              => fn($q) => $q->where('is_current', true)->latest(),
                    'userPayrolls.payrollMaster',
                ]);

            if ($request->employee_selection === 'selected') {
                $employeeQuery->whereIn('id', $request->selected_employees);
            }

            $employees = $employeeQuery->get();

            if ($employees->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'No eligible employees found with active payroll assignments.')
                    ->withInput();
            }

            $processedCount    = 0;
            $errors            = [];
            $existingEmployees = [];
            $newEmployees      = [];

            foreach ($employees as $employee) {
                $exists = MonthlyPayroll::where('user_id', $employee->id)
                    ->where('payroll_month', $request->payroll_month)
                    ->exists();

                if ($exists) {
                    $existingEmployees[] = $employee;
                } else {
                    $newEmployees[] = $employee;
                }
            }

            // Warn about duplicates -- use a dedicated confirm route instead of
            // injecting HTML into flash (XSS-safe approach)
            if (count($existingEmployees) > 0 && !$request->has('force_reprocess')) {
                $names = collect($existingEmployees)->pluck('name')->implode(', ');
                DB::rollBack();
                return redirect()->back()
                    ->with('warning', 'The following employees already have payroll for this month: ' . e($names) . '. Submit again with force_reprocess to overwrite.')
                    ->with('existing_employee_ids', collect($existingEmployees)->pluck('id')->toArray())
                    ->withInput();
            }

            if ($request->has('force_reprocess') && count($existingEmployees) > 0) {
                $existingIds = collect($existingEmployees)->pluck('id')->toArray();
                MonthlyPayroll::whereIn('user_id', $existingIds)
                    ->where('payroll_month', $request->payroll_month)
                    ->delete();
            }

            $allEmployees = array_merge($newEmployees, $existingEmployees);

            foreach ($allEmployees as $employee) {
                try {
                    $result = $this->processEmployeeMonthlyPayroll(
                        $employee,
                        $request->payroll_month,
                        $startDate,
                        $endDate,
                        (bool) ($request->include_overtime       ?? false),
                        (bool) ($request->include_loan_deductions ?? false)
                    );

                    if ($result) {
                        $processedCount++;
                    } else {
                        $errors[] = "Employee {$employee->name} (ID: {$employee->employee_id}): Processing failed";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Employee {$employee->name} (ID: {$employee->employee_id}): " . $e->getMessage();
                    Log::error("Payroll processing error for employee {$employee->id}: " . $e->getMessage());
                }
            }

            DB::commit();

            $message = $processedCount > 0 ? "Successfully processed {$processedCount} employee(s). " : '';

            if (count($errors) > 0) {
                return redirect()
                    ->route('monthly-payrolls.index', ['month' => $request->payroll_month])
                    ->with('warning', $message . 'Encountered ' . count($errors) . ' error(s).')
                    ->with('errors', $errors);
            }

            return redirect()
                ->route('monthly-payrolls.index', ['month' => $request->payroll_month])
                ->with('success', $message ?: "Payroll processed successfully for {$processedCount} employees.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Monthly payroll store error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to process payroll: ' . $e->getMessage())
                ->withInput();
        }
    }

    // =========================================================================
    // CORE PAYROLL PROCESSING - FIXED VERSION
    // =========================================================================

    /**
     * Calculate and persist payroll for a single employee for one month.
     */
    private function processEmployeeMonthlyPayroll(
        $employee,
        string $yearMonth,
        string $startDate,
        string $endDate,
        bool $includeOvertime       = true,
        bool $includeLoanDeductions = true
    ) {
        $userPayroll = $employee->currentPayroll;

        if (!$userPayroll) {
            throw new \Exception("No active payroll assignment found.");
        }

        $payrollMaster      = $userPayroll->payrollMaster;
        $calculationType    = $payrollMaster->payroll_calculation_type ?? 'day_based';
        $workingHoursPerDay = $payrollMaster->working_hours_per_day    ?? 8;
        $tenantId           = session('tenant_id');

        Log::channel('daily')->info('===== PAYROLL CALCULATION START =====', [
            'employee_id'      => $employee->id,
            'employee_name'    => $employee->name,
            'year_month'       => $yearMonth,
            'calculation_type' => $calculationType,
            'working_hrs_per_day' => $workingHoursPerDay,
        ]);

        // Get ALL holiday and weekoff dates (raw SQL -- tenant_id scoped manually)
        $holidayDates = $this->getAllHolidayDates($startDate, $endDate, $tenantId);
        $weekoffDates = $this->getAllWeekoffDates($employee->id, $startDate, $endDate, $tenantId);
        
        Log::channel('daily')->info('DEBUG: weekoff/holiday results', [
            'employee_id'   => $employee->id,
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

        $presentDays   = $dayBreakdown['present_days'];   // float e.g. 10.5
        $halfDays      = $dayBreakdown['half_days'];
        $paidLeaves    = $dayBreakdown['paid_leaves'];
        $unpaidLeaves  = $dayBreakdown['unpaid_leaves'];
        $paidHolidays  = $dayBreakdown['paid_holidays'];
        $paidWeekoffs  = $dayBreakdown['paid_weekoffs'];
        $totalHolidays = $dayBreakdown['total_holidays'];
        $totalWeekoffs = $dayBreakdown['total_weekoffs'];
        $absentDays    = $dayBreakdown['absent_days'];

        $calendarDays = Carbon::createFromFormat('Y-m', $yearMonth)->daysInMonth;
        $workingDays  = max(1, $calendarDays - $totalHolidays - $totalWeekoffs);

        // Initialise
        $earnings                 = [];
        $employeeDeductions       = [];
        $employerContributions    = [];
        $payableDays              = 0.0;
        $prorationFactor          = 0.0;
        $expectedHours            = 0.0;
        $actualWorkedHours        = 0.0;
        $hourlyRate               = 0.0;
        $overtimeHoursCalculated  = 0.0;
        $approvedOvertimeHours    = 0.0;
        $overtimeAmount           = 0.0;
        $overtimeRateApplied      = 0.0;

        // Get attendance summary (uses raw SQL)
        $attendanceSummary = $this->calculateAttendanceSummary($employee->id, $startDate, $endDate, $tenantId);

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
            $hourlyRate = $userPayroll->basic_salary / max(1, $expectedTotalHours);
            
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
                'employee_id'         => $employee->id,
                'employee_name'       => $employee->name,
                'month'               => $yearMonth,
                'actual_worked_hours' => $totalActualHours,
                'paid_leave_days'     => $paidLeaves,
                'paid_leave_hours'    => $paidLeaveHours,
                'total_payable_hours' => $totalPayableHours,
                'expected_hours'      => $expectedTotalHours,
                'hourly_rate'         => round($hourlyRate, 2),
                'proration_factor'    => round($prorationFactor, 4),
                'basic_salary'        => round($basicSalary, 2),
                'full_month_salary'   => $userPayroll->basic_salary,
                'working_days'        => $workingDays,
                'hours_per_day'       => $workingHoursPerDay,
            ]);

            // Overtime from approved requests
            $overtimeData = $this->getApprovedOvertimeHours($employee->id, $yearMonth, $tenantId);
            $approvedOvertimeHours = $overtimeData['total_hours'];

            if ($includeOvertime && $approvedOvertimeHours > 0) {
                $overtimeSettings = $this->getOvertimeSettings($tenantId);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                $overtimeRateApplied = $hourlyRate * $rateMultiplier;
                $overtimeAmount = $approvedOvertimeHours * $overtimeRateApplied;

                Log::channel('daily')->info('Overtime Calculation (Hour-Based - Fixed)', [
                    'employee_id'      => $employee->id,
                    'approved_hours'   => $approvedOvertimeHours,
                    'rate_multiplier'  => $rateMultiplier,
                    'hourly_rate'      => round($hourlyRate, 2),
                    'overtime_rate'    => round($overtimeRateApplied, 2),
                    'overtime_amount'  => round($overtimeAmount, 2),
                    'request_count'    => $overtimeData['request_count'],
                ]);
            }

            // Overtime hours calculated from attendance records
            $overtimeHoursCalculated = $attendanceSummary['overtime_hours'];

            // Payable days (for display purposes only)
            $payableDays = $presentDays + $paidLeaves + $paidHolidays + $paidWeekoffs;

            // Earnings - prorated based on proration factor
            $earnings = [
                'basic'      => round($basicSalary, 2),
                'hra'        => round($userPayroll->hra * $prorationFactor, 2),
                'conveyence' => round($userPayroll->conveyence * $prorationFactor, 2),
                'medical'    => round($userPayroll->medical_allowance * $prorationFactor, 2),
                'children'   => round($userPayroll->children_allowance * $prorationFactor, 2),
                'post'       => round($userPayroll->post_allowance * $prorationFactor, 2),
                'lta'        => round($userPayroll->leave_travel_allowance * $prorationFactor, 2),
                'incentive'  => round($userPayroll->monthly_incentive * $prorationFactor, 2),
                'special'    => round($userPayroll->special_allowance * $prorationFactor, 2),
                'overtime'   => round($overtimeAmount, 2),
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
                $overtimeSettings = $this->getOvertimeSettings($tenantId);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                $dailyRate = $userPayroll->basic_salary / max(1, $calendarDays);
                $hourlyRate = $dailyRate / max(1, $workingHoursPerDay);
                $overtimeRateApplied = $hourlyRate * $rateMultiplier;
                $overtimeAmount = $approvedOvertimeHours * $overtimeRateApplied;

                Log::channel('daily')->info('Overtime Calculation (Day-Based)', [
                    'employee_id'     => $employee->id,
                    'approved_hours'  => $approvedOvertimeHours,
                    'rate_multiplier' => $rateMultiplier,
                    'hourly_rate'     => round($hourlyRate, 2),
                    'overtime_rate'   => round($overtimeRateApplied, 2),
                    'overtime_amount' => round($overtimeAmount, 2),
                    'request_count'   => $overtimeData['request_count'],
                ]);
            }

            $overtimeHoursCalculated = $approvedOvertimeHours;
            $earnings['overtime'] = round($overtimeAmount, 2);
        }

        // =====================================================================
        // LOAN DEDUCTIONS
        // =====================================================================
        $loanDeductions = 0.0;

        if ($includeLoanDeductions) {
            $loanDeductions = $this->calculateLoanDeductions($employee->id, $yearMonth, $tenantId);

            if ($loanDeductions > 0) {
                $employeeDeductions['loan'] = $loanDeductions;

                Log::channel('daily')->info('Loan Deductions Applied', [
                    'employee_id'         => $employee->id,
                    'employee_name'       => $employee->name,
                    'month'               => $yearMonth,
                    'total_loan_deduction' => $loanDeductions,
                ]);
            }
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
            'calendar_days'    => $calendarDays,
            'working_days'     => $workingDays,
            'present_days'     => $presentDays,
            'half_days'        => $halfDays,
            'paid_leaves'      => $paidLeaves,
            'unpaid_leaves'    => $unpaidLeaves,
            'paid_holidays'    => $paidHolidays,
            'paid_weekoffs'    => $paidWeekoffs,
            'payable_days'     => $payableDays,
            'proration_factor' => $prorationFactor,
            'absent_days'      => $absentDays,
            'expected_hours'   => $expectedHours,
            'actual_worked_hours' => $actualWorkedHours,
            'hourly_rate'      => round($hourlyRate, 2),
            'loan_deduction'   => $loanDeductions,
            'gross_earnings'   => $grossEarnings,
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
            'total_deductions' => $totalEmployeeDeductions,
            'net_payable'      => $netPayable,
        ]);

        // =====================================================================
        // PERSIST -- Eloquent create() auto-sets tenant_id via BelongsToTenant
        // =====================================================================
        $monthlyPayroll = MonthlyPayroll::create([
            'user_id'                   => $employee->id,
            'employee_payroll_id'       => $userPayroll->id,
            'payroll_month'             => $yearMonth,
            'processing_date'           => now(),
            'total_working_days'        => $calculationType === 'hour_based' ? $workingDays : $calendarDays,
            'payable_days'              => $payableDays,
            'present_days'              => $presentDays,
            'half_days'                 => $halfDays,
            'absent_days'               => $absentDays,
            'paid_leaves'               => $paidLeaves,
            'unpaid_leaves'             => $unpaidLeaves,
            'holidays'                  => $totalHolidays,
            'week_offs'                 => $paidWeekoffs,
            'overtime_hours'            => $calculationType === 'hour_based'
                ? $attendanceSummary['overtime_hours']
                : $approvedOvertimeHours,
            'overtime_hours_calculated' => round($overtimeHoursCalculated, 2),
            'overtime_rate'             => round($overtimeRateApplied, 2),
            'overtime_amount'           => $earnings['overtime'] ?? 0,
            'expected_hours'            => round($expectedHours, 2),
            'actual_worked_hours'       => round($actualWorkedHours, 2),
            'hourly_rate'               => round($hourlyRate, 2),
            'basic_salary'              => $earnings['basic'],
            'hra'                       => $earnings['hra'],
            'conveyence'                => $earnings['conveyence'],
            'medical_allowance'         => $earnings['medical'],
            'children_allowance'        => $earnings['children']   ?? 0,
            'post_allowance'            => $earnings['post']       ?? 0,
            'leave_travel_allowance'    => $earnings['lta']        ?? 0,
            'monthly_incentive'         => $earnings['incentive']  ?? 0,
            'special_allowance'         => $earnings['special']    ?? 0,
            // Employee Deductions (deducted from salary)
            'provident_fund'            => $employeeDeductions['pf'] ?? 0,
            'esi'                       => $employeeDeductions['esi'] ?? 0,
            'professional_tax'          => $employeeDeductions['pt'] ?? 0,
            'tds'                       => $employeeDeductions['tds'] ?? 0,
            'loan_deduction'            => $employeeDeductions['loan'] ?? 0,
            'other_deductions'          => $employeeDeductions['other'] ?? 0,
            // Employer Contributions (STORED but NOT deducted from salary)
            'employer_provident_fund'   => $employerContributions['employer_pf'] ?? 0,
            'employer_esi'              => $employerContributions['employer_esi'] ?? 0,
            // Totals
            'gross_earnings'            => $grossEarnings,
            'total_deductions'          => $totalEmployeeDeductions,
            'net_payable'               => $netPayable,
            'payment_status'            => 'pending',
            'processed_by'              => Auth::id(),
            'remarks'                   => $this->generateRemarks(
                $presentDays,
                $halfDays,
                $absentDays,
                $paidLeaves,
                $paidHolidays,
                $paidWeekoffs
            ),
        ]);

        $this->savePayrollComponents($monthlyPayroll->id, $earnings, $employeeDeductions, $employerContributions);

        // Mark loan repayments paid -- NO inner transaction; we are already inside one
        if ($includeLoanDeductions && $loanDeductions > 0) {
            $this->updateLoanRepayments($employee->id, $yearMonth, $loanDeductions, $tenantId);
        }

        return $monthlyPayroll;
    }

    // =========================================================================
    // DAY BREAKDOWN -- with half-day + absent-by-hours logic
    // Uses raw SQL for attendance to avoid Eloquent global-scope overhead on
    // large SaaS datasets. tenant_id is applied manually in every raw query.
    // =========================================================================

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
    private function calculateDayBreakdownWithPriority(
        int    $userId,
        string $startDate,
        string $endDate,
        array  $holidayDates,
        array  $weekoffDates,
        ?int   $tenantId = null
    ): array {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
     
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
                $hours = (float)$row->worked_hours;
            } else {
                $hours = $this->parseHours($row->total_hours ?? '0');
            }
            
            // Create attendance object for shift-based calculation
            $attendanceObj = new \stdClass();
            $attendanceObj->scheduled_shift_start = $row->scheduled_shift_start;
            $attendanceObj->scheduled_shift_end = $row->scheduled_shift_end;
            
            // Use shift-based status calculation
            $status = $this->getAttendanceStatusByShift($hours, $attendanceObj);
            
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
        $unpaidLeaveTypeIds = [3]; // keep as-is per project requirement
     
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
            $leaveEnd   = Carbon::parse($leave->leave_end)->min($end);
            $isPaid     = !in_array($leave->leave_type, $unpaidLeaveTypeIds);
            $leaveType  = $isPaid ? 'paid_leave' : 'unpaid_leave';
     
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
        $cur      = $start->copy();
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
        $actualWeekoffCount = count(array_filter($allDates, fn($t) => $t === 'weekoff'));
        $expectedWeekoffCount = count(
            array_filter($weekoffDates, fn($d) => $d >= $startDate && $d <= $endDate)
        );
     
        if ($actualWeekoffCount !== $expectedWeekoffCount) {
            Log::channel('daily')->error('WEEKOFF CLASSIFICATION MISMATCH', [
                'user_id'          => $userId,
                'expected'         => $expectedWeekoffCount,
                'actual'           => $actualWeekoffCount,
                'weekoff_dates_in' => $weekoffDates,
                'all_dates'        => $allDates,
            ]);
        }
     
        // ------------------------------------------------------------------
        // Tally
        // ------------------------------------------------------------------
        $presentDays  = 0.0;
        $paidLeaves   = 0.0;
        $unpaidLeaves = 0.0;
        $holidayCount = 0;
        $weekoffCount = 0;
        $absentDays   = 0;
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
        // both boundaries. tenant_id scoped on the tenants lookup.
        // ------------------------------------------------------------------
        $sandwichRow = DB::selectOne(
            'SELECT sandwich FROM tenants WHERE id = ? LIMIT 1',
            [$tenantId]
        );
        $sandwichEnabled = $sandwichRow ? (bool) $sandwichRow->sandwich : true;
     
        $workedDates = array_keys(
            array_filter($allDates, fn($t) => $t === 'present' || $t === 'half_day')
        );
        $workedFlip = array_flip($workedDates);
     
        $nonWorkingTypes = ['holiday', 'weekoff'];
        $groups          = [];
        $currentGroup    = null;
        $sortedDates     = array_keys($allDates);
     
        foreach ($sortedDates as $date) {
            $type = $allDates[$date];
            if (in_array($type, $nonWorkingTypes)) {
                if ($currentGroup === null) {
                    $currentGroup = ['dates' => [], 'types' => []];
                }
                $currentGroup['dates'][] = $date;
                $currentGroup['types'][] = $type;
            } else {
                if ($currentGroup !== null) {
                    $groups[]     = $currentGroup;
                    $currentGroup = null;
                }
            }
        }
        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }
     
        $paidHolidayDates = [];
        $paidWeekoffDates = [];
     
        foreach ($groups as $group) {
            $firstDate = $group['dates'][0];
            $lastDate  = $group['dates'][count($group['dates']) - 1];
     
            $prevBoundary = Carbon::parse($firstDate)->subDay()->format('Y-m-d');
            $nextBoundary = Carbon::parse($lastDate)->addDay()->format('Y-m-d');
     
            $prevWorked = isset($workedFlip[$prevBoundary]);
            $nextWorked = isset($workedFlip[$nextBoundary]);
     
            $isPaid = !$sandwichEnabled ? true : ($prevWorked || $nextWorked);
     
            if ($isPaid) {
                foreach ($group['dates'] as $i => $date) {
                    $group['types'][$i] === 'holiday'
                        ? $paidHolidayDates[] = $date
                        : $paidWeekoffDates[] = $date;
                }
            }
        }
     
        Log::channel('daily')->info('Sandwich Rule Result', [
            'user_id'            => $userId,
            'sandwich_enabled'   => $sandwichEnabled,
            'groups_evaluated'   => count($groups),
            'paid_holidays'      => count($paidHolidayDates),
            'paid_weekoffs'      => count($paidWeekoffDates),
            'paid_holiday_dates' => $paidHolidayDates,
            'paid_weekoff_dates' => $paidWeekoffDates,
        ]);
     
        return [
            'present_days'   => $presentDays,
            'half_days'      => count($halfDayDates),
            'paid_leaves'    => $paidLeaves,
            'unpaid_leaves'  => $unpaidLeaves,
            'total_holidays' => $holidayCount,
            'total_weekoffs' => $weekoffCount,
            'paid_holidays'  => count($paidHolidayDates),
            'paid_weekoffs'  => count($paidWeekoffDates),
            'absent_days'    => $absentDays,
            'all_days'       => $allDates,
        ];
    }

    // =========================================================================
    // ATTENDANCE SUMMARY -- raw SQL, half-day aware, tenant_id scoped
    // =========================================================================

    private function calculateAttendanceSummary(
        int    $userId,
        string $startDate,
        string $endDate,
        ?int   $tenantId = null
    ): array {
        $rows = DB::select("
            SELECT total_hours, worked_hours, scheduled_shift_start, scheduled_shift_end
            FROM   attendances
            WHERE  user_id   = ?
              AND  tenant_id = ?
              AND  status    = 1
              AND  date BETWEEN ? AND ?
        ", [$userId, $tenantId, $startDate, $endDate]);

        $fullDays          = 0;
        $halfDays          = 0;
        $absentByHours     = 0;
        $totalWorkingHours = 0.0;
        $overtimeHours     = 0.0;
        $stdHoursPerDay    = 8.0;

        foreach ($rows as $row) {
            // Calculate total hours using worked_hours first
            $hours = null;
            if ($row->worked_hours !== null && $row->worked_hours !== '') {
                $hours = (float)$row->worked_hours;
            } else {
                $hours = $this->parseHours($row->total_hours ?? '0');
            }
            
            // Create attendance object for shift-based calculation
            $attendanceObj = new \stdClass();
            $attendanceObj->scheduled_shift_start = $row->scheduled_shift_start;
            $attendanceObj->scheduled_shift_end = $row->scheduled_shift_end;
            
            $status = $this->getAttendanceStatusByShift($hours, $attendanceObj);
            
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
            'present_days'        => $fullDays + ($halfDays * 0.5),
            'full_days'           => $fullDays,
            'half_days'           => $halfDays,
            'absent_by_hours'     => $absentByHours,
            'total_working_hours' => round($totalWorkingHours, 2),
            'overtime_hours'      => round($overtimeHours, 2),
        ];
    }

    // =========================================================================
    // LOAN DEDUCTIONS -- raw SQL, tenant_id scoped
    // =========================================================================

    private function calculateLoanDeductions(int $userId, string $yearMonth, ?int $tenantId = null): float
    {
        $activeLoans = DB::select("
            SELECT id, loan_number
            FROM   loans
            WHERE  user_id          = ?
              AND  tenant_id        = ?
              AND  status           = 'active'
              AND  repayment_type   = 'emi'
              AND  remaining_amount > 0
        ", [$userId, $tenantId]);

        if (empty($activeLoans)) {
            return 0.0;
        }

        $totalDeduction   = 0.0;
        $deductionsDetail = [];

        foreach ($activeLoans as $loan) {
            $repayment = DB::selectOne("
                SELECT id, emi_amount
                FROM   loan_repayments
                WHERE  loan_id   = ?
                  AND  tenant_id = ?
                  AND  month     = ?
                  AND  status    = 'pending'
                LIMIT 1
            ", [$loan->id, $tenantId, $yearMonth]);

            if ($repayment) {
                $totalDeduction     += $repayment->emi_amount;
                $deductionsDetail[]  = [
                    'loan_id'      => $loan->id,
                    'loan_number'  => $loan->loan_number,
                    'amount'       => $repayment->emi_amount,
                    'repayment_id' => $repayment->id,
                ];
            }
        }

        if ($totalDeduction > 0) {
            Log::channel('daily')->info('Loan Deductions Calculated', [
                'user_id'         => $userId,
                'month'           => $yearMonth,
                'total_deduction' => $totalDeduction,
                'details'         => $deductionsDetail,
            ]);
        }

        return round($totalDeduction, 2);
    }

    /**
     * Mark pending loan repayments as paid.
     * NOTE: Do NOT open a new DB transaction here -- the caller already has one open.
     *       All raw queries are tenant_id scoped.
     */
    private function updateLoanRepayments(int $userId, string $yearMonth, float $deductedAmount, ?int $tenantId = null): void
    {
        if ($deductedAmount <= 0) {
            return;
        }

        $pendingRepayments = DB::select("
            SELECT lr.id, lr.emi_amount, lr.loan_id
            FROM   loan_repayments lr
            INNER JOIN loans l ON l.id = lr.loan_id
            WHERE  l.user_id        = ?
              AND  l.tenant_id      = ?
              AND  l.status         = 'active'
              AND  l.repayment_type = 'emi'
              AND  lr.tenant_id     = ?
              AND  lr.month         = ?
              AND  lr.status        = 'pending'
        ", [$userId, $tenantId, $tenantId, $yearMonth]);

        if (empty($pendingRepayments)) {
            return;
        }

        foreach ($pendingRepayments as $repayment) {
            DB::update("
                UPDATE loan_repayments
                SET    status           = 'paid',
                       paid_amount      = ?,
                       paid_date        = ?,
                       payment_mode     = 'salary_deduction',
                       is_auto_deducted = 1,
                       salary_month     = ?,
                       remarks          = 'Auto deducted via payroll',
                       processed_by     = ?
                WHERE  id        = ?
                  AND  tenant_id = ?
            ", [$repayment->emi_amount, now(), $yearMonth, Auth::id(), $repayment->id, $tenantId]);

            $loan = DB::selectOne("
                SELECT id, loan_number, remaining_amount, loan_application_id
                FROM   loans
                WHERE  id        = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$repayment->loan_id, $tenantId]);

            if (!$loan) {
                continue;
            }

            $newRemaining = $loan->remaining_amount - $repayment->emi_amount;

            DB::update("
                UPDATE loans
                SET    remaining_amount = ?
                WHERE  id        = ?
                  AND  tenant_id = ?
            ", [max(0, $newRemaining), $loan->id, $tenantId]);

            if ($newRemaining <= 0) {
                DB::update("
                    UPDATE loans
                    SET    status      = 'closed',
                           closed_date = ?
                    WHERE  id        = ?
                      AND  tenant_id = ?
                ", [now(), $loan->id, $tenantId]);

                if ($loan->loan_application_id) {
                    DB::update("
                        UPDATE loan_applications
                        SET    status = 'closed'
                        WHERE  id        = ?
                          AND  tenant_id = ?
                    ", [$loan->loan_application_id, $tenantId]);
                }
            }

            Log::channel('daily')->info('Loan Repayment Processed', [
                'user_id'     => $userId,
                'loan_id'     => $loan->id,
                'loan_number' => $loan->loan_number,
                'amount'      => $repayment->emi_amount,
                'remaining'   => $newRemaining,
                'month'       => $yearMonth,
            ]);
        }
        // Outer transaction in processEmployeeMonthlyPayroll handles commit/rollback
    }

    // =========================================================================
    // HOLIDAY & WEEKOFF HELPERS -- raw SQL, tenant_id scoped
    // =========================================================================

    /**
     * Return every holiday date (as Y-m-d strings) within the given range.
     * Each holiday is stored as a single-date record, so we just pluck the date.
     */
    private function getAllHolidayDates(string $startDate, string $endDate, ?int $tenantId = null): array
    {
        $rows = DB::select("
            SELECT DATE_FORMAT(start_date, '%Y-%m-%d') AS holiday_date
            FROM   holidays
            WHERE  tenant_id  = ?
              AND  start_date BETWEEN ? AND ?
        ", [$tenantId, $startDate, $endDate]);

        return array_unique(array_column($rows, 'holiday_date'));
    }

    /**
     * Return every weekoff date for a specific employee within the given range.
     * Supports three off_type values: day_based, date_based, range_based.
     * tenant_id scoped.
     */
    private function getAllWeekoffDates(int $userId, string $startDate, string $endDate, ?int $tenantId = null): array
    {
        $weekoffConfigs = DB::select("
            SELECT *
            FROM   user_weekoffs
            WHERE  user_id   = ?
              AND  tenant_id = ?
              AND  status    = 1
        ", [$userId, $tenantId]);

        if (empty($weekoffConfigs)) {
            return [];
        }

        $weekoffDates = [];
        $start        = Carbon::parse($startDate);
        $end          = Carbon::parse($endDate);

        foreach ($weekoffConfigs as $config) {
            // Recurring weekly day (e.g. every Sunday)
            if ($config->off_type === 'day_based' && !empty($config->day_name)) {
                $dayName = strtolower($config->day_name);
                $cur     = $start->copy();
                while ($cur <= $end) {
                    if (strtolower($cur->format('l')) === $dayName) {
                        $weekoffDates[] = $cur->format('Y-m-d');
                    }
                    $cur->addDay();
                }
            }

            // Specific single date
            if ($config->off_type === 'date_based' && !empty($config->off_date)) {
                $offDate = Carbon::parse($config->off_date);
                if ($offDate >= $start && $offDate <= $end) {
                    $weekoffDates[] = $offDate->format('Y-m-d');
                }
            }

            // Date range (e.g. company shutdown)
            if (
                $config->off_type === 'range_based'
                && !empty($config->start_date)
                && !empty($config->end_date)
            ) {
                $rangeStart = Carbon::parse($config->start_date)->max($start);
                $rangeEnd   = Carbon::parse($config->end_date)->min($end);
                $cur        = $rangeStart->copy();
                while ($cur <= $rangeEnd) {
                    $weekoffDates[] = $cur->format('Y-m-d');
                    $cur->addDay();
                }
            }
        }

        $weekoffDates = array_unique($weekoffDates);
        sort($weekoffDates);

        return $weekoffDates;
    }

    // =========================================================================
    // OVERTIME HELPER -- raw SQL, tenant_id scoped
    // =========================================================================

    private function getApprovedOvertimeHours(int $userId, string $yearMonth, ?int $tenantId = null): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth()->format('Y-m-d');
        $endDate   = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->format('Y-m-d');

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
        $details    = [];

        foreach ($rows as $row) {
            $totalHours += (float) $row->hours;
            $details[]   = [
                'date'   => $row->ot_date,
                'hours'  => $row->hours,
                'reason' => $row->reason,
            ];
        }

        return [
            'total_hours'   => round($totalHours, 2),
            'details'       => $details,
            'request_count' => count($rows),
        ];
    }

    /**
     * Fetch overtime settings for the tenant.
     * Tenant-specific row takes priority over a global (null tenant) fallback.
     */
    private function getOvertimeSettings(?int $tenantId): ?object
    {
        return DB::selectOne("
            SELECT *
            FROM   overtime_settings
            WHERE  tenant_id = ? OR tenant_id IS NULL
            ORDER BY (tenant_id IS NULL) ASC
            LIMIT 1
        ", [$tenantId]);
    }

    // =========================================================================
    // EARNINGS & DEDUCTIONS
    // =========================================================================

    private function calculateEarnings($userPayroll, float $prorationFactor): array
    {
        $prorationFactor = max(0.0, min(1.0, $prorationFactor));

        return [
            'basic'      => round($userPayroll->basic_salary            * $prorationFactor, 2),
            'hra'        => round($userPayroll->hra                     * $prorationFactor, 2),
            'conveyence' => round($userPayroll->conveyence              * $prorationFactor, 2),
            'medical'    => round($userPayroll->medical_allowance       * $prorationFactor, 2),
            'children'   => round($userPayroll->children_allowance      * $prorationFactor, 2),
            'post'       => round($userPayroll->post_allowance          * $prorationFactor, 2),
            'lta'        => round($userPayroll->leave_travel_allowance  * $prorationFactor, 2),
            'incentive'  => round($userPayroll->monthly_incentive       * $prorationFactor, 2),
            'special'    => round($userPayroll->special_allowance       * $prorationFactor, 2),
        ];
    }

    /**
     * Calculate employee deductions and employer contributions separately
     * Employee deductions are deducted from salary (PF, ESI, PT, TDS)
     * Employer contributions are NOT deducted (Employer PF, Employer ESI)
     */
    private function calculateDeductions($userPayroll, float $prorationFactor): array
    {
        $prorationFactor = max(0.0, min(1.0, $prorationFactor));

        // Employee deductions (deducted from salary)
        $employeeDeductions = [
            'pf'  => round($userPayroll->provident_fund * $prorationFactor, 2),
            'esi' => round($userPayroll->esi * $prorationFactor, 2),
            'pt'  => round($userPayroll->professional_tax * $prorationFactor, 2),
            'tds' => round($userPayroll->tds * $prorationFactor, 2),
            'other' => 0,
        ];

        // Employer contributions (STORED but NOT deducted from salary)
        $employerContributions = [
            'employer_pf'  => round($userPayroll->employer_provident_fund * $prorationFactor, 2),
            'employer_esi' => round($userPayroll->employer_esi * $prorationFactor, 2),
        ];

        return [
            'employee_deductions' => $employeeDeductions,
            'employer_contributions' => $employerContributions,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Parse a time string that may be "HH:MM" or a plain decimal float.
     */
    private function parseHours($timeString): float
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

    private function generateRemarks(
        float $present,
        int   $halfDays,
        int   $absent,
        float $leaves,
        int   $holidays,
        int   $weekoffs
    ): string {
        $parts = [];
        if ($present > 0)   $parts[] = "Present: {$present} days";
        if ($halfDays > 0)  $parts[] = "Half days: {$halfDays}";
        if ($absent > 0)    $parts[] = "Absent: {$absent} days";
        if ($leaves > 0)    $parts[] = "Paid leaves: {$leaves} days";
        if ($holidays > 0)  $parts[] = "Holidays: {$holidays} days";
        if ($weekoffs > 0)  $parts[] = "Week offs: {$weekoffs} days";

        if ($present == 0 && $leaves == 0) {
            $parts[] = "NOTE: No salary paid -- zero attendance and no paid leaves";
        }

        return implode(', ', $parts);
    }

    /**
     * Save payroll components to database
     * Separates earnings, employee deductions, and employer contributions
     */
    private function savePayrollComponents(int $monthlyPayrollId, array $earnings, array $employeeDeductions, array $employerContributions = []): void
    {
        // Save earnings
        foreach ($earnings as $key => $amount) {
            if ($amount > 0) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name'     => $this->formatComponentName($key),
                    'component_type'     => 'earning',
                    'amount'             => $amount,
                    'is_taxable'         => in_array($key, ['basic', 'hra', 'special', 'incentive']),
                    'description'        => $this->formatComponentName($key) . ' for the month',
                ]);
            }
        }

        // Save employee deductions (deducted from salary)
        foreach ($employeeDeductions as $key => $amount) {
            if ($amount > 0 && !in_array($key, ['employer_pf', 'employer_esi'])) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name'     => $this->getDeductionName($key),
                    'component_type'     => 'deduction',
                    'amount'             => $amount,
                    'is_taxable'         => false,
                    'description'        => $this->getDeductionName($key) . ' for the month',
                ]);
            }
        }

        // Save employer contributions (INFORMATIONAL only - NOT deducted)
        foreach ($employerContributions as $key => $amount) {
            if ($amount > 0) {
                PayrollComponent::create([
                    'monthly_payroll_id' => $monthlyPayrollId,
                    'component_name'     => $this->getEmployerContributionName($key),
                    'component_type'     => 'employer_contribution',
                    'amount'             => $amount,
                    'is_taxable'         => false,
                    'description'        => $this->getEmployerContributionName($key) . ' for the month',
                ]);
            }
        }
    }

    private function getEmployerContributionName(string $key): string
    {
        return [
            'employer_pf'  => 'Employer PF Contribution',
            'employer_esi' => 'Employer ESI Contribution',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private function formatComponentName(string $key): string
    {
        return [
            'basic'      => 'Basic Salary',
            'hra'        => 'HRA',
            'conveyence' => 'Conveyance Allowance',
            'medical'    => 'Medical Allowance',
            'children'   => 'Children Allowance',
            'post'       => 'Post Allowance',
            'lta'        => 'Leave Travel Allowance',
            'incentive'  => 'Monthly Incentive',
            'special'    => 'Special Allowance',
            'overtime'   => 'Overtime',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private function getDeductionName(string $key): string
    {
        return [
            'pf'    => 'Provident Fund',
            'esi'   => 'ESI',
            'pt'    => 'Professional Tax',
            'tds'   => 'TDS',
            'loan'  => 'Loan Deduction',
            'other' => 'Other Deductions',
        ][$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    // =========================================================================
    // CRUD -- SHOW / EDIT / UPDATE / DELETE
    // =========================================================================

    public function show($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'components',
                'payrollMaster',
                'UserPayroll',
            ])->findOrFail($id);

            $bankDetails = DB::selectOne("
                SELECT *
                FROM   user_bank_details
                WHERE  user_id   = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$monthlyPayroll->user_id, session('tenant_id')]);
            
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();

            return view('client.payroll.monthly-payroll.show', compact('monthlyPayroll', 'bankDetails','userPayroll'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payroll record not found.');
        }
    }

    public function edit($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.payrolls' => fn($q) => $q->where('is_current', true),
            ])->findOrFail($id);

            if ($monthlyPayroll->payment_status !== 'pending') {
                return redirect()->route('monthly-payrolls.index')
                    ->with('error', 'Only pending payroll records can be edited.');
            }

            $months = [];
            for ($i = 0; $i < 12; $i++) {
                $date                              = now()->subMonths($i);
                $months[$date->format('Y-m')]      = $date->format('F Y');
            }
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();
            
            return view('client.payroll.monthly-payroll.edit', compact('monthlyPayroll', 'months','userPayroll'));
        } catch (\Exception $e) {
            Log::error('Payroll edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payroll record not found.');
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payroll_month'      => 'required|date_format:Y-m',
            'present_days'       => 'required|numeric|min:0',
            'paid_leaves'        => 'nullable|numeric|min:0',
            'overtime_hours'     => 'nullable|numeric|min:0',
            'basic_salary'       => 'required|numeric|min:0',
            'hra'                => 'required|numeric|min:0',
            'conveyence'         => 'required|numeric|min:0',
            'medical_allowance'  => 'required|numeric|min:0',
            'provident_fund'     => 'required|numeric|min:0',
            'esi'                => 'required|numeric|min:0',
            'professional_tax'   => 'required|numeric|min:0',
            'actual_worked_hours' => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if ($monthlyPayroll->payment_status !== 'pending') {
                return redirect()->back()->with('error', 'Only pending payroll records can be edited.');
            }

            // Check if hour-based
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();
                
            $isHourBased = $userPayroll && $userPayroll->payrollMaster 
                && $userPayroll->payrollMaster->payroll_calculation_type === 'hour_based';

            $grossEarnings = $request->basic_salary
                + $request->hra
                + $request->conveyence
                + $request->medical_allowance
                + ($request->children_allowance    ?? 0)
                + ($request->post_allowance        ?? 0)
                + ($request->leave_travel_allowance ?? 0)
                + ($request->monthly_incentive     ?? 0)
                + ($request->special_allowance     ?? 0)
                + ($request->overtime_amount       ?? 0);

            $totalDeductions = $request->provident_fund
                + $request->esi
                + $request->professional_tax
                + ($request->loan_deduction   ?? 0)
                + ($request->other_deductions ?? 0);

            $netPayable = $grossEarnings - $totalDeductions;

            $updateData = [
                'payroll_month'          => $request->payroll_month,
                'present_days'           => $request->present_days,
                'paid_leaves'            => $request->paid_leaves           ?? 0,
                'overtime_hours'         => $request->overtime_hours        ?? 0,
                'basic_salary'           => $request->basic_salary,
                'hra'                    => $request->hra,
                'conveyence'             => $request->conveyence,
                'medical_allowance'      => $request->medical_allowance,
                'children_allowance'     => $request->children_allowance    ?? 0,
                'post_allowance'         => $request->post_allowance        ?? 0,
                'leave_travel_allowance' => $request->leave_travel_allowance ?? 0,
                'monthly_incentive'      => $request->monthly_incentive     ?? 0,
                'special_allowance'      => $request->special_allowance     ?? 0,
                'overtime_amount'        => $request->overtime_amount       ?? 0,
                'provident_fund'         => $request->provident_fund,
                'esi'                    => $request->esi,
                'professional_tax'       => $request->professional_tax,
                'loan_deduction'         => $request->loan_deduction        ?? 0,
                'other_deductions'       => $request->other_deductions      ?? 0,
                'gross_earnings'         => $grossEarnings,
                'total_deductions'       => $totalDeductions,
                'net_payable'            => $netPayable,
                'remarks'                => $request->remarks
                    ? $monthlyPayroll->remarks . ' | Updated: ' . $request->remarks
                    : $monthlyPayroll->remarks,
            ];

            // If hour-based, also update actual_worked_hours
            if ($isHourBased && $request->has('actual_worked_hours')) {
                $updateData['actual_worked_hours'] = $request->actual_worked_hours;
            }

            $monthlyPayroll->update($updateData);

            PayrollComponent::where('monthly_payroll_id', $id)->delete();

            $earnings = [
                'basic'      => $request->basic_salary,
                'hra'        => $request->hra,
                'conveyence' => $request->conveyence,
                'medical'    => $request->medical_allowance,
                'children'   => $request->children_allowance    ?? 0,
                'post'       => $request->post_allowance        ?? 0,
                'lta'        => $request->leave_travel_allowance ?? 0,
                'incentive'  => $request->monthly_incentive     ?? 0,
                'special'    => $request->special_allowance     ?? 0,
                'overtime'   => $request->overtime_amount       ?? 0,
            ];

            $employeeDeductions = [
                'pf'    => $request->provident_fund,
                'esi'   => $request->esi,
                'pt'    => $request->professional_tax,
                'loan'  => $request->loan_deduction   ?? 0,
                'other' => $request->other_deductions ?? 0,
            ];

            $employerContributions = [
                'employer_pf'  => $monthlyPayroll->employer_provident_fund ?? 0,
                'employer_esi' => $monthlyPayroll->employer_esi ?? 0,
            ];

            $this->savePayrollComponents($id, $earnings, $employeeDeductions, $employerContributions);

            DB::commit();

            return redirect()->route('monthly-payrolls.show', $id)
                ->with('success', 'Payroll updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update payroll: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if ($monthlyPayroll->payment_status !== 'pending') {
                return redirect()->back()
                    ->with('error', 'Cannot delete payroll that is already ' . $monthlyPayroll->payment_status);
            }

            DB::beginTransaction();
            PayrollComponent::where('monthly_payroll_id', $id)->delete();
            $monthlyPayroll->delete();
            DB::commit();

            return redirect()->route('monthly-payrolls.index')
                ->with('success', 'Payroll record deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete payroll error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete payroll: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // STATUS MANAGEMENT
    // =========================================================================

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status'        => 'required|in:pending,processed,paid,cancelled',
            'payment_date'          => 'required_if:payment_status,paid|nullable|date',
            'payment_mode'          => 'nullable|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'remarks'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            $updateData = [
                'payment_status' => $request->payment_status,
                'remarks'        => $request->remarks
                    ? $monthlyPayroll->remarks . ' | ' . $request->remarks
                    : $monthlyPayroll->remarks,
            ];

            if ($request->payment_status === 'paid') {
                $updateData['payment_date']          = $request->payment_date ?? now();
                $updateData['payment_mode']          = $request->payment_mode;
                $updateData['transaction_reference'] = $request->transaction_reference;
            }

            $monthlyPayroll->update($updateData);

            return redirect()->back()->with('success', 'Payment status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Update status error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids'            => 'required|array',
            'ids.*'          => 'exists:monthly_payrolls,id',
            'payment_status' => 'required|in:pending,processed,paid,cancelled',
        ]);

        try {
            DB::beginTransaction();

            $updateData = [
                'payment_status' => $request->payment_status,
                'remarks'        => DB::raw("CONCAT(COALESCE(remarks,''), ' | Bulk updated on " . now()->toDateTimeString() . "')"),
            ];

            if ($request->payment_status === 'paid') {
                $updateData['payment_date'] = now();
            }

            MonthlyPayroll::whereIn('id', $request->ids)->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($request->ids) . ' payroll records updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // PDF -- PAYSLIPS (admin)
    // =========================================================================

    public function generatePayslip($id)
    {
        try {
            $tenantId       = session('tenant_id');
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'payrollMaster',
            ])->findOrFail($id);

            $bankDetails = DB::selectOne("
                SELECT *
                FROM   user_bank_details
                WHERE  user_id   = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$monthlyPayroll->user_id, $tenantId]);

            $company = DB::selectOne("
                SELECT * FROM tenants WHERE id = ? LIMIT 1
            ", [$tenantId]);

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails'    => $bankDetails,
                'company'        => $company,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'payslip_'
                . $monthlyPayroll->user->employee_id
                . '_' . $monthlyPayroll->payroll_month . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Generate payslip error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate payslip: ' . $e->getMessage());
        }
    }

    public function viewPayslip($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'payrollMaster',
            ])->findOrFail($id);

            $bankDetails = DB::selectOne("
                SELECT *
                FROM   user_bank_details
                WHERE  user_id   = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$monthlyPayroll->user_id, session('tenant_id')]);

            $company = [
                'name'     => config('app.name'),
                'address'  => 'Your Company Address',
                'location' => 'Your Location',
                'logo'     => public_path('assets/images/logo.png'),
            ];

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf-payslip', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails'    => $bankDetails,
                'company'        => $company,
            ]);

            $pdf->setPaper('A4', 'portrait');

            return $pdf->stream('payslip.pdf');
        } catch (\Exception $e) {
            Log::error('View payslip error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to view payslip: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PDF -- SALARY SLIPS (employee self-service)
    // =========================================================================

    public function mySalarySlips(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = MonthlyPayroll::with(['processor'])
                ->where('user_id', $authUser->id)
                ->whereIn('payment_status', ['paid', 'processed']);

            if ($request->filled('year')) {
                $query->whereRaw('LEFT(payroll_month, 4) = ?', [$request->year]);
            }
            if ($request->filled('month')) {
                $query->whereRaw('RIGHT(payroll_month, 2) = ?', [str_pad($request->month, 2, '0', STR_PAD_LEFT)]);
            }

            $salarySlips = $query->orderBy('payroll_month', 'desc')->paginate(20);

            $years = MonthlyPayroll::where('user_id', $authUser->id)
                ->whereIn(DB::raw('LOWER(payment_status)'), ['paid', 'processed'])
                ->selectRaw('DISTINCT LEFT(payroll_month, 4) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();

            $months = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December',
            ];

            $summary = [
                'total_earned'   => $salarySlips->sum('net_payable'),
                'total_slips'    => $salarySlips->total(),
                'average_salary' => $salarySlips->count() > 0
                    ? $salarySlips->sum('net_payable') / $salarySlips->count()
                    : 0,
                'last_slip_date' => $salarySlips->isNotEmpty()
                    ? $salarySlips->first()->payroll_month
                    : null,
            ];

            return view(
                'client.payroll.monthly-payroll.employee-salary-slips',
                compact('salarySlips', 'years', 'months', 'summary')
            );
        } catch (\Exception $e) {
            Log::error('My salary slips error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading salary slips: ' . $e->getMessage());
        }
    }

    public function downloadSalarySlip($id)
    {
        try {
            $authUser       = Auth::user();
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'processor',
            ])->findOrFail($id);

            if (
                $authUser->id !== $monthlyPayroll->user_id
                && !in_array($authUser->role, ['admin', 'hr'])
            ) {
                abort(403, 'Unauthorized access.');
            }

            if (!in_array($monthlyPayroll->payment_status, ['paid', 'processed'])) {
                return redirect()->back()
                    ->with('error', 'Salary slip is only available for paid or processed payrolls.');
            }

            $bankDetails = DB::selectOne("
                SELECT *
                FROM   user_bank_details
                WHERE  user_id   = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$monthlyPayroll->user_id, session('tenant_id')]);

            $company = DB::selectOne("
                SELECT * FROM tenants WHERE id = ? LIMIT 1
            ", [session('tenant_id')]);

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll'  => $monthlyPayroll,
                'bankDetails'     => $bankDetails,
                'company'         => $company,
                'isEmployeeView'  => true,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'salary_slip_'
                . $monthlyPayroll->user->employee_id
                . '_' . $monthlyPayroll->payroll_month . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Download salary slip error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download salary slip: ' . $e->getMessage());
        }
    }

    public function viewSalarySlip($id)
    {
        try {
            $authUser       = Auth::user();
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'processor',
            ])->findOrFail($id);

            if (
                $authUser->id !== $monthlyPayroll->user_id
                && !in_array($authUser->role, ['admin', 'hr'])
            ) {
                abort(403, 'Unauthorized access.');
            }

            if (!in_array($monthlyPayroll->payment_status, ['paid', 'processed'])) {
                return redirect()->back()
                    ->with('error', 'Salary slip is only available for paid or processed payrolls.');
            }

            $bankDetails = DB::selectOne("
                SELECT *
                FROM   user_bank_details
                WHERE  user_id   = ?
                  AND  tenant_id = ?
                LIMIT 1
            ", [$monthlyPayroll->user_id, session('tenant_id')]);

            $company = DB::selectOne("
                SELECT * FROM tenants WHERE id = ? LIMIT 1
            ", [session('tenant_id')]);

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll'  => $monthlyPayroll,
                'bankDetails'     => $bankDetails,
                'company'         => $company,
                'isEmployeeView'  => true,
            ]);

            $pdf->setPaper('A4', 'portrait');

            return $pdf->stream('salary_slip.pdf');
        } catch (\Exception $e) {
            Log::error('View salary slip error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to view salary slip: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // ESTIMATES (preview before processing)
    // =========================================================================

    public function calculateEstimates(Request $request)
    {
        try {
            $employeeIds  = $request->employee_ids;
            $payrollMonth = $request->payroll_month;

            $totalGross      = 0.0;
            $totalDeductions = 0.0;

            $employees = User::whereIn('id', $employeeIds)
                ->where('role', '!=', 'admin')
                ->with(['currentPayroll' => fn($q) => $q->where('is_current', true)])
                ->get();

            foreach ($employees as $employee) {
                if ($employee->currentPayroll) {
                    $payroll          = $employee->currentPayroll;
                    $totalGross      += $payroll->gross_salary       ?? 0;
                    $totalDeductions += ($payroll->provident_fund    ?? 0)
                        + ($payroll->esi               ?? 0)
                        + ($payroll->professional_tax  ?? 0);
                }
            }

            return response()->json([
                'success'        => true,
                'total_gross'    => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net'      => $totalGross - $totalDeductions,
                'working_days'   => Carbon::createFromFormat('Y-m', $payrollMonth)->daysInMonth,
                'proration_info' => 'Estimates are based on full-month gross. Actual values depend on attendance.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // EXPORT
    // =========================================================================

    public function export(Request $request)
    {
        try {
            $request->validate(['ids' => 'required|json']);

            $ids     = json_decode($request->ids, true);
            $payrolls = MonthlyPayroll::with(['user', 'processor'])
                ->whereIn('id', $ids)
                ->get();

            if ($payrolls->isEmpty()) {
                return redirect()->back()->with('error', 'No records found to export.');
            }

            return $this->buildCsvResponse($payrolls, 'payroll_export_' . date('Y-m-d_His') . '.csv', true);
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    public function exportSimple(Request $request)
    {
        try {
            $query = MonthlyPayroll::with(['user']);

            if ($request->filled('month'))   $query->where('payroll_month',  $request->month);
            if ($request->filled('status'))  $query->where('payment_status', $request->status);
            if ($request->filled('user_id')) $query->where('user_id',        $request->user_id);

            $payrolls = $query->orderBy('payroll_month', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($payrolls->isEmpty()) {
                return redirect()->back()->with('error', 'No records found to export with current filters.');
            }

            return $this->buildCsvResponse($payrolls, 'payroll_export_' . date('Y-m-d_His') . '.csv', false);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to export: ' . $e->getMessage());
        }
    }

    /**
     * Build a CSV download response.
     * $detailed = true  -> full columns (used by bulk export with selected IDs)
     * $detailed = false -> summary columns (used by filtered exportSimple)
     */
    private function buildCsvResponse($payrolls, string $filename, bool $detailed)
    {
        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        if ($detailed) {
            fputcsv($handle, [
                'Employee ID',
                'Employee Name',
                'Month',
                'Present Days',
                'Half Days',
                'Working Days',
                'Paid Leaves',
                'Overtime Hours',
                'Basic Salary',
                'HRA',
                'Conveyance',
                'Medical',
                'Gross Earnings',
                'PF Deduction',
                'ESI Deduction',
                'Professional Tax',
                'Loan Deduction',
                'Total Deductions',
                'Net Payable',
                'Status',
                'Payment Date',
                'Payment Mode',
            ]);
            foreach ($payrolls as $p) {
                fputcsv($handle, [
                    $p->user->employee_id ?? 'N/A',
                    $p->user->name        ?? 'N/A',
                    Carbon::createFromFormat('Y-m', $p->payroll_month)->format('F Y'),
                    $p->present_days,
                    $p->half_days         ?? 0,
                    $p->total_working_days,
                    $p->paid_leaves,
                    $p->overtime_hours,
                    $p->basic_salary,
                    $p->hra,
                    $p->conveyence,
                    $p->medical_allowance,
                    $p->gross_earnings,
                    $p->provident_fund,
                    $p->esi,
                    $p->professional_tax,
                    $p->loan_deduction,
                    $p->total_deductions,
                    $p->net_payable,
                    ucfirst($p->payment_status),
                    $p->payment_date,
                    $p->payment_mode ?? 'N/A',
                ]);
            }
        } else {
            fputcsv($handle, [
                'Employee Name',
                'Employee ID',
                'Email',
                'Month',
                'Total Working Days',
                'Payable Days',
                'Net Payable (INR)',
            ]);
            foreach ($payrolls as $p) {
                fputcsv($handle, [
                    $p->user->name        ?? 'N/A',
                    $p->user->employee_id ?? 'N/A',
                    $p->user->email       ?? 'N/A',
                    Carbon::createFromFormat('Y-m', $p->payroll_month)->format('F Y'),
                    $p->total_working_days,
                    $p->payable_days,
                    number_format($p->net_payable, 2),
                ]);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}