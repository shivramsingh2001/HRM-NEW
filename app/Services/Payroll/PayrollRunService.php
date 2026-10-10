<?php

namespace App\Services\Payroll;

use App\Models\MonthlyPayroll;
use App\Models\PayrollComponent;
use App\Models\User;
use App\Services\Payroll\Legacy\LegacyPayrollCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process Payroll for a month — the batch behind monthly-payrolls.store:
 * period lock, which engine the company is on, who is eligible, the
 * already-processed / force-reprocess rules, the payroll run record and one
 * payslip per employee (a failing employee is reported, the rest continue).
 * Moved out of MonthlyPayrollController::store() unchanged (code-quality plan,
 * Phase 1); the controller validates and turns a PayrollRunException into the
 * same redirect + message as before.
 */
class PayrollRunService
{
    public function __construct(
        private LegacyPayrollCalculator $calculator,
        private PayslipWriter $payslips,
        private LoanDeductionService $loanDeductionService,
    ) {}

    /** Legacy fixed-column engine: calculate + save one employee's payslip. */
    public function processLegacy(
        $employee,
        ?int $tenantId,
        string $yearMonth,
        string $startDate,
        string $endDate,
        bool $includeOvertime = true,
        bool $includeLoanDeductions = true
    ): MonthlyPayroll {
        $calc = $this->calculator->calculate($employee, $tenantId, $yearMonth, $startDate, $endDate, $includeOvertime, $includeLoanDeductions);

        return $this->payslips->createLegacy($calc, $employee, $yearMonth, $tenantId, $includeLoanDeductions);
    }

    /** Dynamic component engine (companies with payroll_dynamic_ui_enabled): calculate + save one payslip. */
    public function processDynamic($employee, ?int $tenantId, string $yearMonth, ?int $payrollRunId, bool $includeLoanDeductions = true): MonthlyPayroll
    {
        return $this->payslips->createDynamic($employee, $tenantId, $yearMonth, $payrollRunId, $includeLoanDeductions);
    }

    /**
     * Process $yearMonth for every eligible employee ('all') or the selected ones.
     *
     * @return array{processed: int, errors: string[]}
     *
     * @throws PayrollRunException refused (locked month, nobody eligible, already processed …) or failed
     */
    public function run(
        ?int $tenantId,
        string $yearMonth,
        string $selection,
        array $selectedIds,
        bool $includeOvertime,
        bool $includeLoanDeductions,
        bool $forceReprocess
    ): array {
        try {
            DB::beginTransaction();

            $date = Carbon::createFromFormat('Y-m', $yearMonth);
            $startDate = $date->copy()->startOfMonth()->format('Y-m-d');
            $endDate = $date->copy()->endOfMonth()->format('Y-m-d');

            // Payroll rebuild — Phase 8 cutover: a tenant flagged onto the
            // dynamic engine is switched over completely for new runs (no
            // hybrid per-employee mix), so eligibility comes from having a
            // dynamic structure instead of a legacy UserPayroll assignment.
            // Existing legacy data/screens are never touched either way.

            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $yearMonth)) {
                throw new PayrollRunException($yearMonth.' is locked. Reopen the period before processing payroll for it.');
            }

            $dynamicEngineEnabled = $tenantId
                ? (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled')
                : false;

            if ($dynamicEngineEnabled) {
                $employeeQuery = User::where('status', 1)
                    ->whereHas('currentDynamicPayrollStructure');
            } else {
                $employeeQuery = User::where('status', 1)
                    ->whereHas('userPayrolls', fn ($q) => $q->where('is_current', true))
                    ->with([
                        'userPayrolls' => fn ($q) => $q->where('is_current', true)->latest(),
                        'userPayrolls.payrollMaster',
                    ]);
            }

            if ($selection === 'selected') {
                $employeeQuery->whereIn('id', $selectedIds);
            }

            $employees = $employeeQuery->get();

            if ($employees->isEmpty()) {
                throw new PayrollRunException('No eligible employees found with active payroll assignments.');
            }

            // Company Policies → Overtime, automatic mode: bring this month's calculated overtime
            // up to date before it is paid (no-op in request mode).
            if ($tenantId) {
                app(\App\Services\Attendance\AutoOvertimeService::class)
                    ->syncMonth((int) $tenantId, $yearMonth, $employees->pluck('id')->all());
            }

            $processedCount = 0;
            $errors = [];
            $existingEmployees = [];
            $newEmployees = [];

            foreach ($employees as $employee) {
                $exists = MonthlyPayroll::where('user_id', $employee->id)
                    ->where('payroll_month', $yearMonth)
                    ->exists();

                if ($exists) {
                    $existingEmployees[] = $employee;
                } else {
                    $newEmployees[] = $employee;
                }
            }

            // Warn about duplicates -- use a dedicated confirm route instead of
            // injecting HTML into flash (XSS-safe approach)
            if (count($existingEmployees) > 0 && ! $forceReprocess) {
                $names = collect($existingEmployees)->pluck('name')->implode(', ');
                throw new PayrollRunException(
                    'The following employees already have payroll for this month: '.e($names).'. Submit again with force_reprocess to overwrite.',
                    'warning',
                    ['existing_employee_ids' => collect($existingEmployees)->pluck('id')->toArray()]
                );
            }

            if ($forceReprocess && count($existingEmployees) > 0) {
                $existingIds = collect($existingEmployees)->pluck('id')->toArray();
                $toReplace = MonthlyPayroll::whereIn('user_id', $existingIds)
                    ->where('payroll_month', $yearMonth)
                    ->get();

                // Never silently replace a payroll that's already been
                // processed/paid -- same policy destroy() already enforces,
                // applied here too so force_reprocess can't be used as a
                // back door around it. Block the whole batch with an
                // itemized list rather than partially reprocessing.
                $blocked = $toReplace->reject(fn (MonthlyPayroll $mp) => auth()->user()->can('delete', $mp));
                if ($blocked->isNotEmpty()) {
                    $names = $blocked->map(fn (MonthlyPayroll $mp) => optional($mp->user)->name.' ('.$mp->payment_status.')')->implode(', ');

                    throw new PayrollRunException('Cannot force-reprocess -- already processed/paid: '.e($names).'. Deselect them or reopen those payslips first.');
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
                    ['tenant_id' => $tenantId, 'year_month' => $yearMonth],
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
                        ? $this->processDynamic(
                            $employee,
                            $tenantId,
                            $yearMonth,
                            $payrollRunId,
                            $includeLoanDeductions
                        )
                        : $this->processLegacy(
                            $employee,
                            $tenantId,
                            $yearMonth,
                            $startDate,
                            $endDate,
                            $includeOvertime,
                            $includeLoanDeductions
                        );

                    // Both engines return the saved payslip or throw (caught below).
                    $processedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Employee {$employee->name} (ID: {$employee->employee_id}): ".$e->getMessage();
                    Log::error("Payroll processing error for employee {$employee->id}: ".$e->getMessage());
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

            return ['processed' => $processedCount, 'errors' => $errors];
        } catch (PayrollRunException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Monthly payroll store error: '.$e->getMessage());

            throw new PayrollRunException('Failed to process payroll: '.$e->getMessage());
        }
    }
}
