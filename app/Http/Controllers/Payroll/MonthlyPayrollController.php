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
use App\Services\Payroll\LoanDeductionService;
use App\Services\Attendance\OvertimeApprovalService;
use App\Services\RbacService;
use App\Models\OvertimeRequest;
use App\Models\PayrollAuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Traits\AuthorizesByScope;
use App\Traits\ResolvesCurrentTenant;

class MonthlyPayrollController extends Controller
{
    use AuthorizesByScope, ResolvesCurrentTenant;

    private LoanDeductionService $loanDeductionService;
    private OvertimeApprovalService $overtimeApprovalService;
    private RbacService $rbacService;

    public function __construct(
        LoanDeductionService $loanDeductionService,
        OvertimeApprovalService $overtimeApprovalService,
        RbacService $rbacService
    ) {
        $this->loanDeductionService = $loanDeductionService;
        $this->overtimeApprovalService = $overtimeApprovalService;
        $this->rbacService = $rbacService;
    }

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
    private function getAttendanceStatusByShift($totalHours, $attendance = null, ?int $tenantId = null, ?string $date = null, ?int $userId = null)
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

            // Aggregate against the full filtered set BEFORE pagination --
            // summing the paginated Collection itself (the previous
            // behavior) only reflects whichever 15 rows are on the current
            // page, silently understating "Total Payroll"/"Paid Amount" for
            // any filter matching more than one page.
            $totalNet = (float) (clone $query)->sum('net_payable');
            $paidAmount = (float) (clone $query)->where('payment_status', 'paid')->sum('net_payable');

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

            return view('client.payroll.monthly-payroll.index', compact('monthlyPayrolls', 'users', 'months', 'totalNet', 'paidAmount'));
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

            // Payroll rebuild — Phase 8 cutover: a tenant flagged onto the
            // dynamic engine is switched over completely for new runs (no
            // hybrid per-employee mix), so eligibility comes from having a
            // dynamic structure instead of a legacy UserPayroll assignment.
            // Existing legacy data/screens are never touched either way.
            $tenantId = $this->currentTenantId();

            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $request->payroll_month)) {
                DB::rollBack();

                return redirect()->back()
                    ->with('error', $request->payroll_month . ' is locked. Reopen the period before processing payroll for it.')
                    ->withInput();
            }

            $dynamicEngineEnabled = $tenantId
                ? (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled')
                : false;

            if ($dynamicEngineEnabled) {
                $employeeQuery = User::where('status', 1)
                    ->whereHas('currentDynamicPayrollStructure');
            } else {
                $employeeQuery = User::where('status', 1)
                    ->whereHas('userPayrolls', fn($q) => $q->where('is_current', true))
                    ->with([
                        'userPayrolls'              => fn($q) => $q->where('is_current', true)->latest(),
                        'userPayrolls.payrollMaster',
                    ]);
            }

            if ($request->employee_selection === 'selected') {
                $employeeQuery->whereIn('id', $request->selected_employees);
            }

            $employees = $employeeQuery->get();

            if ($employees->isEmpty()) {
                return redirect()->back()
                    ->with('error', 'No eligible employees found with active payroll assignments.')
                    ->withInput();
            }

            // Company Policies → Overtime, automatic mode: bring this month's calculated overtime
            // up to date before it is paid (no-op in request mode).
            if ($tenantId) {
                app(\App\Services\Attendance\AutoOvertimeService::class)
                    ->syncMonth((int) $tenantId, $request->payroll_month, $employees->pluck('id')->all());
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
                $toReplace = MonthlyPayroll::whereIn('user_id', $existingIds)
                    ->where('payroll_month', $request->payroll_month)
                    ->get();

                // Never silently replace a payroll that's already been
                // processed/paid -- same policy destroy() already enforces,
                // applied here too so force_reprocess can't be used as a
                // back door around it. Block the whole batch with an
                // itemized list rather than partially reprocessing.
                $blocked = $toReplace->reject(fn (MonthlyPayroll $mp) => auth()->user()->can('delete', $mp));
                if ($blocked->isNotEmpty()) {
                    DB::rollBack();
                    $names = $blocked->map(fn (MonthlyPayroll $mp) => optional($mp->user)->name . ' (' . $mp->payment_status . ')')->implode(', ');

                    return redirect()->back()
                        ->with('error', 'Cannot force-reprocess -- already processed/paid: ' . e($names) . '. Deselect them or reopen those payslips first.')
                        ->withInput();
                }

                foreach ($toReplace as $mp) {
                    $this->loanDeductionService->revokeForPayroll($mp->id, $mp->tenant_id);
                    app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->revokeForPayroll($mp->id, $mp->tenant_id);
                    PayrollComponent::where('monthly_payroll_id', $mp->id)->delete();
                    $mp->delete();
                }
            }

            $allEmployees = array_merge($newEmployees, $existingEmployees);

            $payrollRunId = null;
            if ($dynamicEngineEnabled) {
                $period = \App\Models\PayrollPeriod::firstOrCreate(
                    ['tenant_id' => $tenantId, 'year_month' => $request->payroll_month],
                    ['status' => 'open']
                );
                $payrollRunId = \App\Models\PayrollRun::create([
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'run_number' => \App\Models\PayrollRun::where('payroll_period_id', $period->id)->count() + 1,
                    'status' => 'calculating',
                    'engine_version' => 'dynamic_v1',
                    'triggered_by' => Auth::id(),
                    'started_at' => now(),
                ])->id;
            }

            foreach ($allEmployees as $employee) {
                try {
                    $result = $dynamicEngineEnabled
                        ? $this->processEmployeeMonthlyPayrollDynamic(
                            $employee,
                            $request->payroll_month,
                            $payrollRunId,
                            (bool) ($request->include_loan_deductions ?? false)
                        )
                        : $this->processEmployeeMonthlyPayroll(
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

            if ($payrollRunId) {
                \App\Models\PayrollRun::whereKey($payrollRunId)->update([
                    'status' => 'calculated',
                    'completed_at' => now(),
                    'employees_included' => $processedCount,
                ]);
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
        // Use the salary structure that was actually effective during the
        // month being processed — not whichever row is flagged is_current
        // *today*. Without this, re-running or backfilling a historical
        // month after a raise would silently apply today's salary instead
        // of the one in effect back then.
        $userPayroll = UserPayroll::forUser($employee->id)
            ->effective($endDate)
            ->orderByDesc('effective_from')
            ->first();

        if (!$userPayroll) {
            throw new \Exception("No active payroll assignment found.");
        }

        $payrollMaster      = $userPayroll->payrollMaster;
        $calculationType    = $payrollMaster->payroll_calculation_type ?? 'day_based';
        $workingHoursPerDay = $payrollMaster->working_hours_per_day    ?? 8;
        $tenantId           = $this->currentTenantId();

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
                $overtimeSettings = $this->getOvertimeSettings($tenantId, (int) $employee->id);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                // Company Policies → Overtime rate: hourly × multiplier, or a fixed amount per hour.
                $overtimeRateApplied = app(\App\Services\Payroll\OvertimePayService::class)->ratePerHour((int) $tenantId, (int) $employee->id, $hourlyRate);
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
                $overtimeSettings = $this->getOvertimeSettings($tenantId, (int) $employee->id);
                $rateMultiplier = $overtimeSettings->rate_multiplier ?? 1.5;
                $hourlyRate = $this->overtimeHourlyRate(
                    $userPayroll->basic_salary, 'day_based', $workingHoursPerDay, $calendarDays, $workingDays,
                    $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days', $payrollMaster->ot_fixed_working_days ?? 26
                );
                $overtimeRateApplied = app(\App\Services\Payroll\OvertimePayService::class)->ratePerHour((int) $tenantId, (int) $employee->id, $hourlyRate);
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
        // LOAN DEDUCTIONS -- always computed (for display via
        // loan_deduction_computed) even when $includeLoanDeductions is
        // false; only applied to totals/ledger when the flag is on.
        // =====================================================================
        $loanDeductionDue = $this->calculateLoanDeductions($employee->id, $yearMonth, $tenantId);
        $loanDeductions = $includeLoanDeductions ? $loanDeductionDue : 0.0;
        // Salary advance against this month (Loans & Advances) — its own line, recovered before loan EMIs.
        $advanceDue = $this->calculateLoanDeductions($employee->id, $yearMonth, $tenantId, \App\Models\Loan::KIND_SALARY_ADVANCE);
        $advanceDeduction = $includeLoanDeductions ? $advanceDue : 0.0;

        // Bulk generation has no per-employee UI to interactively resolve a
        // shortfall the way Edit Payroll does -- so instead of allowing a
        // negative net pay, cap the deductions at what's actually available
        // (advance first, then EMIs) and let this get flagged for review.
        $availableForLoan = max(0, array_sum($earnings) - array_sum($employeeDeductions));
        [$cappedAdvance, $cappedLoan] = $this->capLoanAndAdvance($availableForLoan, $advanceDeduction, $loanDeductions);
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
                'employee_id'         => $employee->id,
                'employee_name'       => $employee->name,
                'month'               => $yearMonth,
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
            'loan_deduction_enabled'    => $includeLoanDeductions,
            'loan_deduction_computed'   => $loanDeductionDue,
            'salary_advance_deduction'  => $employeeDeductions['advance'] ?? 0,
            'salary_advance_deduction_computed' => $advanceDue,
            'late_deduction'            => $employeeDeductions['late'] ?? 0,
            'early_deduction'           => $employeeDeductions['early'] ?? 0,
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
        if ($includeLoanDeductions && $advanceDeduction > 0) {
            $this->updateLoanRepayments($employee->id, $yearMonth, $advanceDeduction, $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        }
        if ($includeLoanDeductions && $loanDeductions > 0) {
            $this->updateLoanRepayments($employee->id, $yearMonth, $loanDeductions, $tenantId, $monthlyPayroll->id);
        }

        return $monthlyPayroll;
    }

    // =========================================================================
    // Payroll rebuild — Phase 8 cutover: dynamic-engine counterpart
    // =========================================================================

    /**
     * Computes via PayrollCalculationEngine (the Phase 2 dynamic engine)
     * instead of this controller's legacy fixed-column math, but persists
     * into the exact same monthly_payrolls columns (plus payroll_run_id /
     * engine_version) so every existing payslip view, PDF, and export
     * keeps rendering unchanged. Only reached for a tenant flagged onto the
     * dynamic engine (see store()); never called on the legacy path.
     */
    private function processEmployeeMonthlyPayrollDynamic(
        $employee,
        string $yearMonth,
        ?int $payrollRunId,
        bool $includeLoanDeductions = true
    ) {
        $tenantId = $this->currentTenantId();

        $result = app(\App\Services\Payroll\PayrollCalculationEngine::class)
            ->calculate($employee, $tenantId, $yearMonth, $includeLoanDeductions);

        // Same auto-cap policy as the legacy path: bulk generation has no
        // per-employee UI to interactively resolve a shortfall, so cap the
        // applied deductions at what's actually available rather than
        // allowing a negative net pay — the SALARY ADVANCE first, then the
        // loan EMIs out of what's left (the rest stays due for next month).
        $this->capDynamicLoanAndAdvance($result, $employee->id, $yearMonth);

        $context = $result['context'];
        $earnings = collect($result['line_items'])->where('component_type', 'earning')->keyBy('code');
        $deductions = collect($result['line_items'])->where('component_type', 'deduction')->keyBy('code');
        $employer = collect($result['line_items'])->where('component_type', 'employer_contribution')->keyBy('code');

        $earningColumnMap = [
            'basic' => 'basic_salary', 'hra' => 'hra', 'conveyance' => 'conveyence',
            'medical_allowance' => 'medical_allowance', 'children_allowance' => 'children_allowance',
            'post_allowance' => 'post_allowance', 'leave_travel_allowance' => 'leave_travel_allowance',
            'monthly_incentive' => 'monthly_incentive', 'special_allowance' => 'special_allowance',
        ];
        $deductionColumnMap = [
            'pf_employee' => 'provident_fund', 'esi_employee' => 'esi',
            'pt' => 'professional_tax', 'tds' => 'tds',
        ];
        $employerColumnMap = [
            'pf_employer' => 'employer_provident_fund', 'esi_employer' => 'employer_esi',
        ];

        $columns = [
            'user_id' => $employee->id,
            'employee_payroll_id' => optional($employee->currentPayroll)->id, // may be null -- a dynamic-only assignment has no legacy UserPayroll row
            'payroll_run_id' => $payrollRunId,
            'engine_version' => 'dynamic_v1',
            'payroll_month' => $yearMonth,
            'processing_date' => now(),
            'total_working_days' => (int) round($context['calendar_days']),
            'payable_days' => $context['payable_days'],
            'present_days' => (int) round($context['present_days']),
            'half_days' => $context['half_days'],
            'absent_days' => (int) round($context['absent_days']),
            'paid_leaves' => $context['paid_leave_days'],
            'unpaid_leaves' => $context['unpaid_leave_days'],
            'holidays' => (int) round($context['holidays']),
            'week_offs' => (int) round($context['week_offs']),
            'overtime_hours' => $context['approved_overtime_hours'],
            'overtime_rate' => $result['overtime_rate'] ?? 0,
            'overtime_amount' => $result['overtime_amount'] ?? 0,
            'actual_worked_hours' => $context['actual_worked_hours'],
            'loan_deduction' => $result['loan_deduction'],
            'loan_deduction_enabled' => $includeLoanDeductions,
            'loan_deduction_computed' => $result['loan_deduction_due'],
            'salary_advance_deduction' => $result['salary_advance_deduction'] ?? 0,
            'salary_advance_deduction_computed' => $result['salary_advance_deduction_due'] ?? 0,
            'late_deduction' => $result['late_deduction'] ?? 0,
            'early_deduction' => $result['early_deduction'] ?? 0,
            'gross_earnings' => $result['gross_earnings'],
            'total_deductions' => $result['total_deductions'],
            'net_payable' => $result['net_payable'],
            'payment_status' => 'pending',
            'processed_by' => Auth::id(),
            'remarks' => 'Processed via dynamic payroll engine (Phase 8).',
        ];

        foreach ($earningColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($earnings->get($code))['amount'], 2) ?: 0;
        }
        foreach ($deductionColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($deductions->get($code))['amount'], 2) ?: 0;
        }
        foreach ($employerColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($employer->get($code))['amount'], 2) ?: 0;
        }

        $monthlyPayroll = MonthlyPayroll::create($columns);

        // Payslip line-item log — every component (including ones with no
        // fixed column, like arrears/bonus or a custom admin-created one)
        // shows up via the existing "additional components" section the
        // payslip views already render. employer_contribution lines are
        // skipped here (the legacy component_type enum only allows
        // earning/deduction) since they're already fully represented by the
        // employer_provident_fund/employer_esi fixed columns above.
        foreach ($result['line_items'] as $li) {
            if (! in_array($li['component_type'], ['earning', 'deduction', 'reimbursement'], true)) {
                continue;
            }

            PayrollComponent::create([
                'monthly_payroll_id' => $monthlyPayroll->id,
                'component_name' => $li['name'],
                'component_type' => $li['component_type'] === 'deduction' ? 'deduction' : 'earning',
                'amount' => $li['amount'],
                'is_taxable' => $li['is_taxable'],
            ]);
        }

        if ($includeLoanDeductions && ($result['salary_advance_deduction'] ?? 0) > 0) {
            $this->updateLoanRepayments($employee->id, $yearMonth, $result['salary_advance_deduction'], $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        }
        if ($includeLoanDeductions && $result['loan_deduction'] > 0) {
            $this->updateLoanRepayments($employee->id, $yearMonth, $result['loan_deduction'], $tenantId, $monthlyPayroll->id, \App\Models\Loan::KIND_LOAN);
        }

        // Expense reimbursements the engine put on this payslip: link them to it (so a recalculation re-reads exactly these).
        $this->linkExpenseReimbursements($monthlyPayroll, $result);

        // Now that this payslip is actually persisted, close the loop from
        // Phase 6: mark whatever arrears/bonus rows it just paid out.
        \App\Models\PayrollArrears::where('tenant_id', $tenantId)
            ->where('user_id', $employee->id)
            ->where('status', 'pending')
            ->update(['status' => 'included_in_payroll', 'target_monthly_payroll_id' => $monthlyPayroll->id]);

        $period = \App\Models\PayrollPeriod::where('tenant_id', $tenantId)->where('year_month', $yearMonth)->first();
        if ($period) {
            \App\Models\PayrollBonus::where('tenant_id', $tenantId)
                ->where('user_id', $employee->id)
                ->where('status', 'approved')
                ->where('target_payroll_period_id', $period->id)
                ->update(['status' => 'included_in_payroll']);
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
                'No leave type is configured as unpaid (Leave Without Pay) for this company. ' .
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
            'user_id'            => $userId,
            'sandwich_enabled'   => $sandwichEnabled,
            'groups_evaluated'   => count($offDayPay),
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
        ?int   $tenantId = null,
        float  $stdHoursPerDay = 8.0
    ): array {
        // Tier 2 / T2-E — source the figures from the one attested rollup so the
        // payslip always matches the attendance module. Same return keys.
        if (config('attendance.payroll_readthrough')) {
            $yearMonth = Carbon::parse($startDate)->format('Y-m');
            $pd = app(\App\Services\Attendance\PayrollDaysService::class)
                ->forMonth($userId, $yearMonth, $tenantId);

            return [
                'present_days'        => $pd['present_days'] + ($pd['half_days'] * 0.5),
                'full_days'           => (int) $pd['present_days'],
                'half_days'           => (int) $pd['half_days'],
                'absent_by_hours'     => (int) $pd['absent_days'],
                'total_working_hours' => round($pd['actual_worked_hours'], 2),
                'overtime_hours'      => round($pd['overtime_hours'], 2),
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

        $fullDays          = 0;
        $halfDays          = 0;
        $absentByHours     = 0;
        $totalWorkingHours = 0.0;
        $overtimeHours     = 0.0;

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
            'present_days'        => $fullDays + ($halfDays * 0.5),
            'full_days'           => $fullDays,
            'half_days'           => $halfDays,
            'absent_by_hours'     => $absentByHours,
            'total_working_hours' => round($totalWorkingHours, 2),
            'overtime_hours'      => round($overtimeHours, 2),
        ];
    }

    // =========================================================================
    // LOAN DEDUCTIONS -- delegates to LoanDeductionService (EMI + lumpsum)
    // =========================================================================

    private function calculateLoanDeductions(int $userId, string $yearMonth, ?int $tenantId = null, ?string $kind = \App\Models\Loan::KIND_LOAN): float
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
    private function capDynamicLoanAndAdvance(array &$result, int $userId, string $yearMonth): void
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
    private function capLoanAndAdvance(float $available, float $advanceDue, float $loanDue): array
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
    private function updateLoanRepayments(int $userId, string $yearMonth, float $deductedAmount, ?int $tenantId = null, ?int $monthlyPayrollId = null, ?string $kind = \App\Models\Loan::KIND_LOAN): void
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
        $details    = [];

        foreach ($rows as $row) {
            $totalHours += (float) $row->hours;
            $details[]   = [
                'date'   => $row->ot_date,
                'hours'  => $row->hours,
                'reason' => $row->reason,
            ];
        }

        // Multi-shift: 2nd+ shift hours are automatic overtime, on top of requests.
        $extraShift = app(\App\Services\Attendance\ExtraShiftOvertime::class)
            ->forPeriod($userId, (int) $tenantId, $startDate, $endDate);
        $totalHours += $extraShift['total_hours'];
        $details = array_merge($details, $extraShift['details']);

        return [
            'total_hours'   => round($totalHours, 2),
            'details'       => $details,
            'request_count' => count($rows),
            'extra_shift_hours' => $extraShift['total_hours'],
        ];
    }

    /**
     * Fetch overtime settings for the tenant.
     * Tenant-specific row takes priority over a global (null tenant) fallback.
     */
    private function getOvertimeSettings(?int $tenantId, ?int $userId = null): ?object
    {
        $settings = DB::selectOne("
            SELECT *
            FROM   overtime_settings
            WHERE  tenant_id = ? OR tenant_id IS NULL
            ORDER BY (tenant_id IS NULL) ASC
            LIMIT 1
        ", [$tenantId]);

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
    private function overtimeHourlyRate(
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
    private function overtimeAmountForHours(
        float $hours,
        ?UserPayroll $userPayroll,
        string $payrollMonth,
        $totalWorkingDaysBasis,
        ?int $tenantId
    ): array {
        if ($hours <= 0 || !$userPayroll) {
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
            // != 0, not > 0 -- a negative manual-adjustment component (e.g. a
            // clawback) must still get its own line-item row, since the
            // persisted totals (array_sum() over these same arrays) already
            // include it regardless. Skipping it here would leave the
            // line-item breakdown unable to reconcile to the persisted total.
            if ($amount != 0) {
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
            if ($amount != 0 && !in_array($key, ['employer_pf', 'employer_esi'])) {
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
            if ($amount != 0) {
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
            'advance' => 'Salary Advance Deduction',
            'late'  => 'Late Arrival Deduction',
            'early' => 'Early Leaving Deduction',
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
            ", [$monthlyPayroll->user_id, $this->currentTenantId()]);
            
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();

            // Policy alone only encodes the state precondition (not pending) --
            // the show() route itself only requires payroll,view, so also gate
            // on the same payroll,manage permission the reopen route enforces,
            // otherwise the button would appear for a viewer who'd just get a
            // permission-denied redirect on click.
            $canReopen = auth()->user()->can('reopen', $monthlyPayroll)
                && $this->rbacService->can(auth()->user(), 'payroll', 'manage');

            return view('client.payroll.monthly-payroll.show', compact('monthlyPayroll', 'bankDetails', 'userPayroll', 'canReopen'));
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

            if (auth()->user()->cannot('update', $monthlyPayroll)) {
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

            // What this payslip has to collect, INCLUDING what it already collected (its own share is
            // undone inside a transaction that is always rolled back — read-only).
            DB::beginTransaction();
            try {
                $this->loanDeductionService->revokeForPayroll($monthlyPayroll->id, $monthlyPayroll->tenant_id);
                $loanDueItems = $this->loanDeductionService->dueItems(
                    $monthlyPayroll->user_id,
                    $monthlyPayroll->tenant_id,
                    $monthlyPayroll->payroll_month,
                    \App\Models\Loan::KIND_LOAN
                );
                // Salary advances against this month — their own block on the edit screen.
                $advanceDueItems = $this->loanDeductionService->dueItems(
                    $monthlyPayroll->user_id,
                    $monthlyPayroll->tenant_id,
                    $monthlyPayroll->payroll_month,
                    \App\Models\Loan::KIND_SALARY_ADVANCE
                );
            } finally {
                DB::rollBack();
            }
            $loanDueTotal = round($loanDueItems->sum('balance_due'), 2);
            $advanceDueTotal = round($advanceDueItems->sum('balance_due'), 2);

            // ---- Overtime: approved breakdown + non-approved (pending) preview ----
            [$otYear, $otMonth] = explode('-', $monthlyPayroll->payroll_month);

            $approvedOvertimeRequests = OvertimeRequest::forMonth($otYear, $otMonth)
                ->approved()
                ->where('user_id', $monthlyPayroll->user_id)
                ->orderBy('date')
                ->get();

            $pendingOvertimeRequests = OvertimeRequest::forMonth($otYear, $otMonth)
                ->pending()
                ->where('user_id', $monthlyPayroll->user_id)
                ->orderBy('date')
                ->get();

            $canApproveOvertime = $this->rbacService->can(auth()->user(), 'overtime', 'approve');

            $overtimeRateInfo = app(\App\Services\Payroll\OvertimePayService::class)->describe((int) $monthlyPayroll->tenant_id, (int) $monthlyPayroll->user_id);
            $overtimeRateMultiplier = $overtimeRateInfo['multiplier'];
            $overtimeFixedRate = $overtimeRateInfo['fixed'];

            $pendingOvertimeHours = round(
                (float) $pendingOvertimeRequests->sum(fn ($r) => (float) $r->final_overtime_hours), 2
            );
            $pendingOvertimeCalc = $this->overtimeAmountForHours(
                $pendingOvertimeHours, $userPayroll, $monthlyPayroll->payroll_month,
                $monthlyPayroll->total_working_days, $monthlyPayroll->tenant_id
            );
            $pendingOvertimeRate = $pendingOvertimeCalc['rate'];
            $pendingOvertimeAmount = $pendingOvertimeCalc['amount'];

            // Dynamic-engine branch — additive, legacy tenants get $isDynamic
            // = false and the view falls through to its existing hardcoded
            // Earnings/Deductions/Employer fields completely unchanged. Gated
            // on THIS payroll row's own engine_version, not just the
            // tenant's current flag — a tenant can switch to the dynamic
            // engine after some of its payrolls were already created via
            // the legacy path, and editing those old rows must keep using
            // the legacy form (their data has no dynamic-engine lineage to
            // recompute against), not whatever the employee's structure
            // looks like today.
            $isDynamic = $monthlyPayroll->engine_version === 'dynamic_v1';
            $dynamicComponents = null;
            if ($isDynamic) {
                try {
                    $dynamicComponents = app(\App\Services\Payroll\PayrollCalculationEngine::class)
                        ->resolveEmployeeComponents($monthlyPayroll->user, $monthlyPayroll->tenant_id, $monthlyPayroll->payroll_month);
                } catch (\Throwable $e) {
                    // No PayrollEmployeeStructure effective for this employee/month
                    // (e.g. this payroll predates the dynamic engine for them) —
                    // fall back to the legacy field set rather than a 500.
                    Log::warning('Dynamic component resolve failed in edit(): ' . $e->getMessage());
                    $isDynamic = false;
                }
            }

            return view('client.payroll.monthly-payroll.edit', compact(
                'monthlyPayroll', 'months', 'userPayroll', 'loanDueItems', 'loanDueTotal', 'advanceDueItems', 'advanceDueTotal',
                'approvedOvertimeRequests', 'pendingOvertimeRequests', 'canApproveOvertime',
                'overtimeRateMultiplier', 'overtimeFixedRate', 'overtimeRateInfo', 'pendingOvertimeRate', 'pendingOvertimeHours', 'pendingOvertimeAmount',
                'isDynamic', 'dynamicComponents'
            ));
        } catch (\Exception $e) {
            Log::error('Payroll edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payroll record not found.');
        }
    }

    public function update(Request $request, $id)
    {
        // Dynamic-engine branch — additive, early-return guard so the
        // legacy validation/persistence logic below is completely untouched
        // for every other request. Gated on THIS payroll row's own
        // engine_version (see the matching comment in edit()) AND the
        // employee still having an effective PayrollEmployeeStructure for
        // this payroll's month (falls through to legacy otherwise).
        $monthlyPayrollForBranch = MonthlyPayroll::find($id);
        if ($monthlyPayrollForBranch && $monthlyPayrollForBranch->engine_version === 'dynamic_v1') {
            $hasStructure = \App\Models\PayrollEmployeeStructure::withoutGlobalScope('tenant')
                ->where('tenant_id', $monthlyPayrollForBranch->tenant_id)
                ->forUser($monthlyPayrollForBranch->user_id)
                ->effective(Carbon::createFromFormat('Y-m', $monthlyPayrollForBranch->payroll_month)->endOfMonth()->toDateString())
                ->exists();
            if ($hasStructure) {
                return $this->updateDynamic($request, $monthlyPayrollForBranch);
            }
        }

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
            'tds'                => 'nullable|numeric|min:0',
            'actual_worked_hours' => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
            'loan_deduction'              => 'nullable|numeric|min:0',
            'loan_deduction_enabled'      => 'nullable|boolean',
            'salary_advance_deduction'         => 'nullable|numeric|min:0',
            'salary_advance_deduction_enabled' => 'nullable|boolean',
            'confirm_negative_net_payable' => 'nullable|boolean',
            'include_pending_overtime'    => 'nullable|boolean',
            'pending_overtime_request_ids'   => 'nullable|array',
            'pending_overtime_request_ids.*' => 'integer|exists:overtime_requests,id',
        ]);

        // Preview (read-only) of the pending-overtime amount that would be
        // auto-approved and folded into this payslip, so the negative-net-pay
        // gate below sees the real total -- the actual approval + authoritative
        // recompute happen again, for real, inside the transaction further down.
        $pendingOvertimePreviewAmount = 0.0;
        if ($request->boolean('include_pending_overtime')) {
            $mpForPreview = MonthlyPayroll::find($id);
            if ($mpForPreview) {
                $requestedIdsForPreview = array_map('intval', (array) $request->input('pending_overtime_request_ids', []));
                [$pyYear, $pyMonth] = explode('-', $mpForPreview->payroll_month);
                $eligiblePreview = OvertimeRequest::forMonth($pyYear, $pyMonth)
                    ->pending()
                    ->where('user_id', $mpForPreview->user_id)
                    ->where('tenant_id', $mpForPreview->tenant_id)
                    ->whereIn('id', $requestedIdsForPreview)
                    ->get();
                $previewUserPayroll = $mpForPreview->user->payrolls()
                    ->where('is_current', 1)->where('status', 1)->first();
                $previewHours = round((float) $eligiblePreview->sum(fn ($r) => (float) $r->final_overtime_hours), 2);
                $pendingOvertimePreviewAmount = $this->overtimeAmountForHours(
                    $previewHours, $previewUserPayroll, $mpForPreview->payroll_month,
                    $mpForPreview->total_working_days, $mpForPreview->tenant_id
                )['amount'];
            }
        }

        // Computed here (not just inside the try block) so the
        // negative-net-pay confirmation gate below can run as part of
        // validation, before anything is persisted.
        $grossEarningsPreview = (float) $request->basic_salary
            + (float) $request->hra
            + (float) $request->conveyence
            + (float) $request->medical_allowance
            + (float) ($request->children_allowance ?? 0)
            + (float) ($request->post_allowance ?? 0)
            + (float) ($request->leave_travel_allowance ?? 0)
            + (float) ($request->monthly_incentive ?? 0)
            + (float) ($request->special_allowance ?? 0)
            + (float) ($request->overtime_amount ?? 0)
            + $pendingOvertimePreviewAmount;

        $loanDeductionEnabled = $request->boolean('loan_deduction_enabled', true);
        $requestedLoanDeduction = $loanDeductionEnabled ? (float) ($request->loan_deduction ?? 0) : 0.0;
        // Salary advance against this month — edited and applied separately from loan EMIs.
        $advanceEnabled = $request->boolean('salary_advance_deduction_enabled', true);
        $requestedAdvance = $advanceEnabled ? (float) ($request->salary_advance_deduction ?? 0) : 0.0;

        $totalDeductionsPreview = (float) $request->provident_fund
            + (float) $request->esi
            + (float) $request->professional_tax
            + (float) ($request->tds ?? 0)
            + $requestedLoanDeduction
            + $requestedAdvance
            + (float) ($request->other_deductions ?? 0);

        $netPayablePreview = round($grossEarningsPreview - $totalDeductionsPreview, 2);

        $validator->after(function ($v) use ($request, $netPayablePreview) {
            if ($netPayablePreview < 0 && ! $request->boolean('confirm_negative_net_payable')) {
                $v->errors()->add(
                    'loan_deduction',
                    'Net payable would be ₹' . number_format($netPayablePreview, 2) . ' (negative). '
                        . 'Reduce the loan deduction amount, or check "Proceed anyway" to confirm.'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // lockForUpdate() -- two concurrent saves of the same payslip
            // (e.g. two admins with the edit page open at once) could
            // otherwise interleave their component delete-then-recreate and
            // loan-ledger resync, corrupting either.
            $monthlyPayroll = MonthlyPayroll::lockForUpdate()->findOrFail($id);

            if (auth()->user()->cannot('update', $monthlyPayroll)) {
                DB::rollBack();

                return redirect()->back()->with('error', 'Only pending payroll records can be edited.');
            }

            if ($monthlyPayroll->tenant_id && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($monthlyPayroll->tenant_id, $monthlyPayroll->payroll_month)) {
                DB::rollBack();

                return redirect()->back()->with('error', $monthlyPayroll->payroll_month . ' is locked. Reopen the period before editing this payroll.');
            }

            // Check if hour-based
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();
                
            $isHourBased = $userPayroll && $userPayroll->payrollMaster
                && $userPayroll->payrollMaster->payroll_calculation_type === 'hour_based';

            // ---- Overtime: the "approved" hours/amount inputs stay exactly
            // what the payroll editor typed (fully editable, unchanged
            // behavior). If they opted to include non-approved overtime,
            // re-verify permission + the request IDs server-side (never trust
            // the checkbox/IDs from the client alone), auto-approve exactly
            // those requests via the same bulk-approve path the Overtime page
            // uses, and fold the *server-computed* delta on top. ----
            $overtimeHours  = (float) ($request->overtime_hours ?? 0);
            $overtimeAmount = (float) ($request->overtime_amount ?? 0);

            if ($request->boolean('include_pending_overtime')) {
                $requestedOvertimeIds = array_map('intval', (array) $request->input('pending_overtime_request_ids', []));
                [$otYear, $otMonth] = explode('-', $monthlyPayroll->payroll_month);
                $eligibleOvertimeIds = OvertimeRequest::forMonth($otYear, $otMonth)
                    ->pending()
                    ->where('user_id', $monthlyPayroll->user_id)
                    ->where('tenant_id', $monthlyPayroll->tenant_id)
                    ->whereIn('id', $requestedOvertimeIds)
                    ->pluck('id')
                    ->all();

                if (!empty($eligibleOvertimeIds)) {
                    $approvalResult = $this->overtimeApprovalService->bulkApprove(
                        auth()->user(), $monthlyPayroll->tenant_id, $eligibleOvertimeIds
                    );

                    if (!$approvalResult['authorized']) {
                        DB::rollBack();

                        return redirect()->back()
                            ->with('error', 'You do not have permission to approve overtime requests.')
                            ->withInput();
                    }

                    if ($approvalResult['approved_count'] > 0) {
                        $newlyApproved = OvertimeRequest::whereIn('id', $approvalResult['approved_ids'])->get();
                        $newlyApprovedHours = round(
                            (float) $newlyApproved->sum(fn ($r) => (float) $r->final_overtime_hours), 2
                        );
                        $newlyApprovedCalc = $this->overtimeAmountForHours(
                            $newlyApprovedHours, $userPayroll, $monthlyPayroll->payroll_month,
                            $monthlyPayroll->total_working_days, $monthlyPayroll->tenant_id
                        );

                        $overtimeHours  += $newlyApprovedHours;
                        $overtimeAmount += $newlyApprovedCalc['amount'];
                    }
                }
            }

            $grossEarnings = $request->basic_salary
                + $request->hra
                + $request->conveyence
                + $request->medical_allowance
                + ($request->children_allowance    ?? 0)
                + ($request->post_allowance        ?? 0)
                + ($request->leave_travel_allowance ?? 0)
                + ($request->monthly_incentive     ?? 0)
                + ($request->special_allowance     ?? 0)
                + $overtimeAmount;

            $totalDeductions = $request->provident_fund
                + $request->esi
                + $request->professional_tax
                + ($request->tds ?? 0)
                + $requestedLoanDeduction
                + $requestedAdvance
                + ($request->other_deductions ?? 0);

            $netPayable = $grossEarnings - $totalDeductions;

            $loanDueTotal = $this->loanDeductionService->totalDue(
                $monthlyPayroll->user_id,
                $monthlyPayroll->tenant_id,
                $monthlyPayroll->payroll_month,
                \App\Models\Loan::KIND_LOAN
            );
            $advanceDueTotal = $this->loanDeductionService->totalDue(
                $monthlyPayroll->user_id,
                $monthlyPayroll->tenant_id,
                $monthlyPayroll->payroll_month,
                \App\Models\Loan::KIND_SALARY_ADVANCE
            );

            $updateData = [
                'payroll_month'          => $request->payroll_month,
                'present_days'           => $request->present_days,
                'paid_leaves'            => $request->paid_leaves           ?? 0,
                'overtime_hours'         => $overtimeHours,
                'basic_salary'           => $request->basic_salary,
                'hra'                    => $request->hra,
                'conveyence'             => $request->conveyence,
                'medical_allowance'      => $request->medical_allowance,
                'children_allowance'     => $request->children_allowance    ?? 0,
                'post_allowance'         => $request->post_allowance        ?? 0,
                'leave_travel_allowance' => $request->leave_travel_allowance ?? 0,
                'monthly_incentive'      => $request->monthly_incentive     ?? 0,
                'special_allowance'      => $request->special_allowance     ?? 0,
                'overtime_amount'        => $overtimeAmount,
                'provident_fund'         => $request->provident_fund,
                'esi'                    => $request->esi,
                'professional_tax'       => $request->professional_tax,
                'tds'                    => $request->tds ?? 0,
                'loan_deduction'         => $requestedLoanDeduction,
                'loan_deduction_enabled' => $loanDeductionEnabled,
                'loan_deduction_computed' => $loanDueTotal,
                'salary_advance_deduction' => $requestedAdvance,
                'salary_advance_deduction_computed' => $advanceDueTotal,
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
                'overtime'   => $overtimeAmount,
            ];

            $employeeDeductions = [
                'pf'    => $request->provident_fund,
                'esi'   => $request->esi,
                'pt'    => $request->professional_tax,
                'tds'   => $request->tds ?? 0,
                'loan'  => $requestedLoanDeduction,
                'advance' => $requestedAdvance,
                'other' => $request->other_deductions ?? 0,
            ];

            $employerContributions = [
                'employer_pf'  => $monthlyPayroll->employer_provident_fund ?? 0,
                'employer_esi' => $monthlyPayroll->employer_esi ?? 0,
            ];

            $this->savePayrollComponents($id, $earnings, $employeeDeductions, $employerContributions);

            // Sync the loan ledger to match what was actually saved on this
            // payslip -- idempotent (applyDeduction()/revokeForPayroll()
            // both first reverse whatever this monthly_payroll_id previously
            // applied), so re-editing and resaving never double-deducts.
            // Advance and loan EMIs are synced separately (each undoes only its own earlier share).
            $this->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month, $requestedAdvance,
                $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
            $this->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month, $requestedLoanDeduction,
                $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_LOAN);

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

    /**
     * Dynamic-engine Edit/Update — entered only from update()'s early-return
     * guard above. Unlike the legacy branch, which trusts whatever numeric
     * values the client submits, this always recomputes through
     * PayrollCalculationEngine::calculate() (the same engine store() uses)
     * with the submitted day-count overrides, so Week Off/Absent/Holiday
     * edits actually flow through real per-component proration instead of
     * a blanket factor. Individual components can still be manually
     * overridden after that recompute (same "manual edit wins" precedent
     * the legacy form already has for its own fields).
     */
    private function updateDynamic(Request $request, MonthlyPayroll $monthlyPayroll)
    {
        if (auth()->user()->cannot('update', $monthlyPayroll)) {
            return redirect()->route('monthly-payrolls.index')
                ->with('error', 'Only pending payroll records can be edited.');
        }

        $validator = Validator::make($request->all(), [
            'present_days' => 'required|numeric|min:0',
            'paid_leaves' => 'nullable|numeric|min:0',
            'week_offs' => 'nullable|numeric|min:0',
            'holidays' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'loan_deduction_enabled' => 'nullable|boolean',
            'confirm_negative_net_payable' => 'nullable|boolean',
            'components' => 'nullable|array',
            'components.*' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $includeLoanDeductions = $request->boolean('loan_deduction_enabled', true);

        try {
            DB::beginTransaction();

            // Undo what this payslip already collected first: the engine only counts instalments
            // still unpaid, so without this a re-save found nothing due and dropped the deduction.
            $this->loanDeductionService->revokeForPayroll($monthlyPayroll->id, $monthlyPayroll->tenant_id);

            $result = app(\App\Services\Payroll\PayrollCalculationEngine::class)->calculate(
                $monthlyPayroll->user,
                $monthlyPayroll->tenant_id,
                $monthlyPayroll->payroll_month,
                $includeLoanDeductions,
                $this->dayOverridesFromRequest($data, $monthlyPayroll)
            );
            $this->capDynamicLoanAndAdvance($result, (int) $monthlyPayroll->user_id, $monthlyPayroll->payroll_month);

            $this->applyComponentOverrides($result, $data['components'] ?? []);

            if (! $request->boolean('confirm_negative_net_payable') && $result['net_payable'] < 0) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error',
                    'Net payable would be negative (₹' . number_format($result['net_payable'], 2) . '). Check "proceed anyway" to confirm.');
            }

            $this->applyCalculationResultToPayroll($monthlyPayroll, $result, $includeLoanDeductions);

            if ($request->filled('remarks')) {
                $monthlyPayroll->remarks = $request->remarks;
                $monthlyPayroll->save();
            }

            DB::commit();

            return redirect()->route('monthly-payrolls.show', $monthlyPayroll->id)
                ->with('success', 'Payroll updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Dynamic payroll update error: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'Failed to update payroll: ' . $e->getMessage());
        }
    }

    /**
     * Read-only live preview for the dynamic Edit form: recomputes through
     * the exact same PayrollCalculationEngine::calculate() call updateDynamic()
     * will use on submit, so what the HR user sees while editing Week
     * Off/Absent/Holiday can never drift from what actually gets saved —
     * no separate simplified client-side proration formula.
     */
    public function recalculatePreview(Request $request, $id)
    {
        $monthlyPayroll = MonthlyPayroll::findOrFail($id);

        $data = $request->validate([
            'present_days' => 'required|numeric|min:0',
            'paid_leaves' => 'nullable|numeric|min:0',
            'week_offs' => 'nullable|numeric|min:0',
            'holidays' => 'nullable|numeric|min:0',
            'loan_deduction_enabled' => 'nullable|boolean',
        ]);

        try {
            // Read-only: undo this payslip's own loan / advance share inside a transaction that is
            // always rolled back, so the preview counts them as due (same as the real save).
            DB::beginTransaction();
            try {
                $this->loanDeductionService->revokeForPayroll($monthlyPayroll->id, $monthlyPayroll->tenant_id);
                $result = app(\App\Services\Payroll\PayrollCalculationEngine::class)->calculate(
                    $monthlyPayroll->user,
                    $monthlyPayroll->tenant_id,
                    $monthlyPayroll->payroll_month,
                    $request->boolean('loan_deduction_enabled', true),
                    $this->dayOverridesFromRequest($data, $monthlyPayroll)
                );
                $this->capDynamicLoanAndAdvance($result, (int) $monthlyPayroll->user_id, $monthlyPayroll->payroll_month);
            } finally {
                DB::rollBack();
            }

            $grouped = collect($result['line_items'])->groupBy('component_type');

            return response()->json([
                'success' => true,
                'data' => [
                    'earnings' => $grouped->get('earning', collect())->values(),
                    'deductions' => $grouped->get('deduction', collect())->values(),
                    'employer_contributions' => $grouped->get('employer_contribution', collect())->values(),
                    'reimbursements' => $grouped->get('reimbursement', collect())->values(),
                    'gross_earnings' => $result['gross_earnings'],
                    'total_deductions' => $result['total_deductions'],
                    'net_payable' => $result['net_payable'],
                    'payable_days' => $result['context']['payable_days'],
                    'absent_days' => $result['context']['absent_days'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Recalculate preview error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to recalculate: ' . $e->getMessage()], 500);
        }
    }

    /**
     * present_days/paid_leave_days/week_offs/holidays for
     * PayrollAttendanceContextBuilder::build()'s $dayOverrides — unset
     * fields fall back to this payroll's current stored value rather than
     * 0, so submitting the form without touching every single day-count
     * field can't accidentally zero one out.
     */
    private function dayOverridesFromRequest(array $data, MonthlyPayroll $monthlyPayroll): array
    {
        return [
            'present_days' => (float) $data['present_days'],
            'paid_leave_days' => (float) ($data['paid_leaves'] ?? $monthlyPayroll->paid_leaves ?? 0),
            'week_offs' => (float) ($data['week_offs'] ?? $monthlyPayroll->week_offs ?? 0),
            'holidays' => (float) ($data['holidays'] ?? $monthlyPayroll->holidays ?? 0),
        ];
    }

    /**
     * Manual per-component amount overrides (submitted from the dynamic
     * Edit form's editable component fields, keyed by component code) take
     * precedence over the freshly recomputed amount for that one component
     * — same "manual edit wins" precedent the legacy form already
     * establishes for its own fields — then re-sums the affected totals.
     */
    private function applyComponentOverrides(array &$result, array $overrides): void
    {
        if (empty($overrides)) {
            return;
        }

        foreach ($result['line_items'] as &$li) {
            // A reimbursement line is fixed by the approved claim - payroll pays exactly that amount.
            if ($li['code'] !== \App\Services\Expense\ExpenseReimbursementPayrollService::CODE && array_key_exists($li['code'], $overrides)) {
                $li['amount'] = round((float) $overrides[$li['code']], 2);
            }
        }
        unset($li);

        $result['gross_earnings'] = round(collect($result['line_items'])->where('component_type', 'earning')->sum('amount'), 2);
        $result['total_deductions'] = round(collect($result['line_items'])->where('component_type', 'deduction')->sum('amount'), 2);
        $result['net_payable'] = round($result['gross_earnings'] - $result['total_deductions'], 2);

        // A manually edited loan / advance line is what the ledger must collect.
        $lines = collect($result['line_items'])->keyBy('code');
        if ($lines->has('loan_deduction')) {
            $result['loan_deduction'] = round((float) $lines['loan_deduction']['amount'], 2);
        }
        if ($lines->has('salary_advance_deduction')) {
            $result['salary_advance_deduction'] = round((float) $lines['salary_advance_deduction']['amount'], 2);
        }
    }

    /**
     * Shared "map a PayrollCalculationEngine::calculate() result onto a
     * MonthlyPayroll row" persistence, mirroring the column-mapping
     * processEmployeeMonthlyPayrollDynamic() uses for creation — kept as a
     * deliberately separate method (not a refactor of that one) so the
     * already-working bulk-generation path carries zero risk from this
     * Edit/Update addition; both simply agree on the same column shapes.
     */
    private function applyCalculationResultToPayroll(MonthlyPayroll $monthlyPayroll, array $result, bool $includeLoanDeductions): void
    {
        $context = $result['context'];
        $earnings = collect($result['line_items'])->where('component_type', 'earning')->keyBy('code');
        $deductions = collect($result['line_items'])->where('component_type', 'deduction')->keyBy('code');
        $employer = collect($result['line_items'])->where('component_type', 'employer_contribution')->keyBy('code');

        $earningColumnMap = [
            'basic' => 'basic_salary', 'hra' => 'hra', 'conveyance' => 'conveyence',
            'medical_allowance' => 'medical_allowance', 'children_allowance' => 'children_allowance',
            'post_allowance' => 'post_allowance', 'leave_travel_allowance' => 'leave_travel_allowance',
            'monthly_incentive' => 'monthly_incentive', 'special_allowance' => 'special_allowance',
        ];
        $deductionColumnMap = [
            'pf_employee' => 'provident_fund', 'esi_employee' => 'esi',
            'pt' => 'professional_tax', 'tds' => 'tds',
        ];
        $employerColumnMap = [
            'pf_employer' => 'employer_provident_fund', 'esi_employer' => 'employer_esi',
        ];

        $columns = [
            'total_working_days' => (int) round($context['calendar_days']),
            'payable_days' => $context['payable_days'],
            'present_days' => (int) round($context['present_days']),
            'half_days' => $context['half_days'],
            'absent_days' => (int) round($context['absent_days']),
            'paid_leaves' => $context['paid_leave_days'],
            'unpaid_leaves' => $context['unpaid_leave_days'],
            'holidays' => (int) round($context['holidays']),
            'week_offs' => (int) round($context['week_offs']),
            'overtime_hours' => $context['approved_overtime_hours'],
            'overtime_rate' => $result['overtime_rate'] ?? 0,
            'overtime_amount' => $result['overtime_amount'] ?? 0,
            'actual_worked_hours' => $context['actual_worked_hours'],
            'loan_deduction' => $result['loan_deduction'],
            'loan_deduction_enabled' => $includeLoanDeductions,
            'loan_deduction_computed' => $result['loan_deduction_due'],
            'salary_advance_deduction' => $result['salary_advance_deduction'] ?? 0,
            'salary_advance_deduction_computed' => $result['salary_advance_deduction_due'] ?? 0,
            'late_deduction' => $result['late_deduction'] ?? 0,
            'early_deduction' => $result['early_deduction'] ?? 0,
            'gross_earnings' => $result['gross_earnings'],
            'total_deductions' => $result['total_deductions'],
            'net_payable' => $result['net_payable'],
        ];

        foreach ($earningColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($earnings->get($code))['amount'], 2) ?: 0;
        }
        foreach ($deductionColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($deductions->get($code))['amount'], 2) ?: 0;
        }
        foreach ($employerColumnMap as $code => $column) {
            $columns[$column] = round((float) optional($employer->get($code))['amount'], 2) ?: 0;
        }

        $monthlyPayroll->fill($columns);
        $monthlyPayroll->save();

        // Replace the per-line-item log so the payslip/show view (which
        // iterates MonthlyPayroll->components) reflects the freshly
        // recomputed breakdown, not the stale set from before this edit.
        PayrollComponent::where('monthly_payroll_id', $monthlyPayroll->id)->delete();
        foreach ($result['line_items'] as $li) {
            if (! in_array($li['component_type'], ['earning', 'deduction', 'reimbursement'], true)) {
                continue;
            }

            PayrollComponent::create([
                'monthly_payroll_id' => $monthlyPayroll->id,
                'component_name' => $li['name'],
                'component_type' => $li['component_type'] === 'deduction' ? 'deduction' : 'earning',
                'amount' => $li['amount'],
                'is_taxable' => $li['is_taxable'],
            ]);
        }

        // Advance and loan EMIs synced separately (each undoes only its own earlier share; 0 = just undo).
        $this->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month,
            $includeLoanDeductions ? (float) ($result['salary_advance_deduction'] ?? 0) : 0.0,
            $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_SALARY_ADVANCE);
        $this->updateLoanRepayments($monthlyPayroll->user_id, $monthlyPayroll->payroll_month,
            $includeLoanDeductions ? (float) $result['loan_deduction'] : 0.0,
            $monthlyPayroll->tenant_id, $monthlyPayroll->id, \App\Models\Loan::KIND_LOAN);

        $this->linkExpenseReimbursements($monthlyPayroll, $result);
    }

    /**
     * Attach the payslip's `expense_reimbursement` lines to their expenses (idempotent revoke-then-relink, so a
     * recalculation that no longer includes one lets it go). Same shape as the loan-ledger sync above.
     */
    private function linkExpenseReimbursements(MonthlyPayroll $monthlyPayroll, array $result): void
    {
        $ids = collect($result['line_items'])
            ->where('code', \App\Services\Expense\ExpenseReimbursementPayrollService::CODE)
            ->pluck('source_id')->filter()->all();

        app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->applyToPayroll((int) $monthlyPayroll->id, (int) $monthlyPayroll->tenant_id, $ids);
    }

    public function destroy($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if (auth()->user()->cannot('delete', $monthlyPayroll)) {
                return redirect()->back()
                    ->with('error', 'Cannot delete payroll that is already ' . $monthlyPayroll->payment_status);
            }

            $tenantId = $monthlyPayroll->tenant_id;
            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $monthlyPayroll->payroll_month)) {
                return redirect()->back()
                    ->with('error', $monthlyPayroll->payroll_month . ' is locked. Reopen the period before deleting this payroll.');
            }

            DB::beginTransaction();
            // Reverse whatever loan-ledger allocation this payslip applied --
            // otherwise the loan_repayments row is left orphaned (FK nulled by
            // the cascade below) while still marked paid, permanently
            // desyncing the loan balance from any surviving payslip.
            $this->loanDeductionService->revokeForPayroll($monthlyPayroll->id, $tenantId);
            app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->revokeForPayroll((int) $monthlyPayroll->id, (int) $tenantId);   // reimbursements go back to the queue
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

    /**
     * Payroll Audit Phase 3 — H8. Deliberate, audited alternative to the
     * unrestricted force_reprocess bypass (C4) for correcting a
     * processed/paid payslip: flips payment_status back to 'pending' so the
     * normal, already-policy-gated edit()/update() flow becomes available
     * again. Nothing else needs to change here -- update() already
     * idempotently resyncs the loan ledger and re-validates any OT
     * auto-approval on every save, so the actual correction is handled
     * correctly by the existing edit flow once this unlocks it.
     */
    public function reopen(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if (auth()->user()->cannot('reopen', $monthlyPayroll)) {
                return redirect()->back()->with(
                    'error',
                    $monthlyPayroll->payment_status === 'pending'
                        ? 'This payroll is already pending -- nothing to reopen.'
                        : 'You do not have permission to reopen this payroll for correction.'
                );
            }

            $tenantId = $monthlyPayroll->tenant_id;
            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $monthlyPayroll->payroll_month)) {
                return redirect()->back()
                    ->with('error', $monthlyPayroll->payroll_month . ' is locked. Reopen the period before reopening this payroll for correction.');
            }

            $previousStatus = $monthlyPayroll->payment_status;

            DB::beginTransaction();
            $monthlyPayroll->update(['payment_status' => 'pending']);
            // A paid payslip's reimbursement payments are reversed so the payslip can be corrected and paid again.
            app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->onPayrollStatusChange($monthlyPayroll, $previousStatus, 'pending', Auth::user());

            PayrollAuditLog::create([
                'tenant_id' => $tenantId,
                'auditable_type' => MonthlyPayroll::class,
                'auditable_id' => $monthlyPayroll->id,
                'action' => 'reopened',
                'actor_id' => Auth::id(),
                'old_values' => ['payment_status' => $previousStatus],
                'new_values' => ['payment_status' => 'pending', 'reason' => $request->reason],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            DB::commit();

            app(\App\Services\PayrollNotificationService::class)->notifyStatusChange($monthlyPayroll, $previousStatus, 'pending');

            return redirect()->route('monthly-payrolls.edit', $id)
                ->with('success', 'Payroll reopened for correction -- make your changes and save.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reopen payroll error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to reopen payroll: ' . $e->getMessage());
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

            $previousStatus = $monthlyPayroll->payment_status;

            // One transaction: if a reimbursement on this payslip can no longer be paid, the payslip is NOT marked paid.
            DB::transaction(function () use ($monthlyPayroll, $updateData, $previousStatus, $request) {
                $monthlyPayroll->update($updateData);
                app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->onPayrollStatusChange($monthlyPayroll, $previousStatus, $request->payment_status, Auth::user());
            });

            $this->maybeLockPeriod($monthlyPayroll->tenant_id, $monthlyPayroll->payroll_month, $request->payment_status);

            app(\App\Services\PayrollNotificationService::class)->notifyStatusChange($monthlyPayroll->fresh(), $previousStatus, $request->payment_status);

            return redirect()->back()->with('success', 'Payment status updated successfully.');
        } catch (\App\Exceptions\ExpenseException $e) {
            return redirect()->back()->with('error', 'Cannot change the status: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Update status error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Tier 2 / T2-E — freeze a tenant's attendance for a month once its payroll
     * is processed or paid, so later edits require an explicit override.
     */
    private function maybeLockPeriod(?int $tenantId, ?string $payrollMonth, string $status): void
    {
        if (! config('attendance.period_autolock') || ! $tenantId || ! $payrollMonth) {
            return;
        }
        if (! in_array($status, ['processed', 'paid'], true)) {
            return;
        }

        try {
            app(\App\Services\Attendance\PeriodLockService::class)
                ->lock((int) $tenantId, $payrollMonth, \Illuminate\Support\Facades\Auth::id(), 'Payroll ' . $status);
            event(new \App\Events\AttendanceDomainEvent('attendance.month_finalised', (int) $tenantId, [
                'year_month' => $payrollMonth,
                'reason' => 'payroll ' . $status,
            ]));
        } catch (\Throwable $e) {
            Log::warning('maybeLockPeriod failed: ' . $e->getMessage());
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

            $affected = MonthlyPayroll::whereIn('id', $request->ids)
                ->get(['tenant_id', 'payroll_month'])->unique(fn ($r) => $r->tenant_id . $r->payroll_month);

            // The bulk update below is a query-builder update, so model events never fire - remember each
            // payslip's previous status and drive the reimbursement settlement explicitly.
            $before = MonthlyPayroll::whereIn('id', $request->ids)->orderBy('id')->get()->keyBy('id');
            $previousStatus = $before->map->payment_status->all();

            MonthlyPayroll::whereIn('id', $request->ids)->update($updateData);

            $expensePayroll = app(\App\Services\Expense\ExpenseReimbursementPayrollService::class);
            foreach ($before as $slipId => $slip) {
                $slip->refresh();
                $expensePayroll->onPayrollStatusChange($slip, $previousStatus[$slipId], $request->payment_status, Auth::user());
            }

            DB::commit();

            foreach ($affected as $row) {
                $this->maybeLockPeriod($row->tenant_id, $row->payroll_month, $request->payment_status);
            }

            // One push per employee can take a while for a big batch - send them after the response.
            $ids = $before->keys()->all();
            $newStatus = $request->payment_status;
            dispatch(function () use ($ids, $previousStatus, $newStatus) {
                $notifier = app(\App\Services\PayrollNotificationService::class);
                foreach (MonthlyPayroll::withoutGlobalScopes()->whereIn('id', $ids)->get() as $slip) {
                    $notifier->notifyStatusChange($slip, $previousStatus[$slip->id] ?? null, $newStatus);
                }
            })->afterResponse();

            return redirect()->back()->with('success', count($request->ids) . ' payroll records updated successfully.');
        } catch (\App\Exceptions\ExpenseException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Nothing was changed - a reimbursement on one of the payslips cannot be paid: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // PDF -- PAYSLIPS (admin)
    // =========================================================================

    public function generatePayslip($id)
    {
        try {
            $tenantId       = $this->currentTenantId();
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

            if (!$this->scopeCoversOwner($authUser, 'payroll', 'view', $monthlyPayroll->user_id)) {
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
            ", [$monthlyPayroll->user_id, $this->currentTenantId()]);

            $company = DB::selectOne("
                SELECT * FROM tenants WHERE id = ? LIMIT 1
            ", [$this->currentTenantId()]);

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

            if (!$this->scopeCoversOwner($authUser, 'payroll', 'view', $monthlyPayroll->user_id)) {
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
            ", [$monthlyPayroll->user_id, $this->currentTenantId()]);

            $company = DB::selectOne("
                SELECT * FROM tenants WHERE id = ? LIMIT 1
            ", [$this->currentTenantId()]);

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
            $includeLoans = $request->boolean('include_loans', true);
            $tenantId     = $this->currentTenantId();

            $totalGross      = 0.0;
            $totalDeductions = 0.0;
            $totalLoans      = 0.0;

            // Anchor to the requested payroll month's end date, not "today",
            // so previewing an estimate for a past/backfilled month reflects
            // the salary structure actually in effect during that month.
            $estimateAsOf = $payrollMonth
                ? Carbon::parse($payrollMonth . '-01')->endOfMonth()->toDateString()
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

                    $totalGross      += $grossForEmployee;
                    $totalDeductions += $statutoryDeductions + $loanForEmployee;
                    $totalLoans      += $loanForEmployee;
                }
            }

            return response()->json([
                'success'        => true,
                'total_gross'    => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_loans'    => $totalLoans,
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

    /**
     * Build a CSV download response.
     * $detailed = true  -> full columns (used by bulk export with selected IDs)
     * $detailed = false -> summary columns
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
                'Salary Advance Deduction',
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
                    $p->salary_advance_deduction ?? 0,
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