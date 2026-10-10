<?php

namespace App\Services\Payroll;

use App\Models\User;
use App\Models\UserPayroll;
use App\Services\Attendance\LatePolicyService;
use App\Services\Attendance\PolicyResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Computes the Late Arrival / Early Leaving payroll deduction for one
 * employee/month — a single, engine-agnostic calculator called identically
 * by both PayrollCalculationEngine (dynamic) and MonthlyPayrollController
 * (legacy), which is what guarantees they can never compute a different
 * amount for the same inputs.
 *
 * Deliberately independent of the attendance-status action
 * (late_attendance_action/early_attendance_action): this class never writes
 * to `attendances` — it only reads LatePolicyService::excessCounts(), which
 * is itself a pure, side-effect-free read. That's what makes "payroll
 * deduction without touching attendance status" safe by construction.
 */
class LateEarlyDeductionCalculator
{
    public function __construct(
        private LatePolicyService $latePolicy,
        private PolicyResolver $policies,
        private PayrollCalculationEngine $dynamicEngine,
    ) {
    }

    /**
     * @return array{
     *     daily_rate: float,
     *     late_excess_days: int,
     *     early_excess_days: int,
     *     late_deduction_amount: float,
     *     early_deduction_amount: float,
     *     total_deduction_amount: float,
     * }
     */
    public function calculate(User $employee, int $tenantId, string $yearMonth): array
    {
        $excess = $this->latePolicy->excessCounts($employee->id, $tenantId, $yearMonth);
        $calendarDays = Carbon::createFromFormat('Y-m', $yearMonth)->daysInMonth;

        [$basic, $divisorMode, $fixedDays] = $this->resolveRateInputs($employee, $tenantId, $yearMonth);
        $divisor = $divisorMode === 'fixed_working_days' ? max(1, $fixedDays) : max(1, $calendarDays);
        $dailyRate = $basic / $divisor;

        // Each excess day is priced with the deduction rule in force ON that
        // day — a change saved mid-month applies from its date.
        $late = 0.0;
        foreach ($excess['lateExcessDays'] as $day) {
            if ($day->lateDeductionEnabled) {
                $late += $this->amountForExcess(1, $dailyRate, $day->lateDeductionMode, $day->lateDeductionAmount, $day->lateDeductionMultiplier);
            }
        }
        $early = 0.0;
        foreach ($excess['earlyExcessDays'] as $day) {
            if ($day->earlyDeductionEnabled) {
                $early += $this->amountForExcess(1, $dailyRate, $day->earlyDeductionMode, $day->earlyDeductionAmount, $day->earlyDeductionMultiplier);
            }
        }
        $late = round($late, 2);
        $early = round($early, 2);

        $result = [
            'daily_rate' => round($dailyRate, 2),
            'late_excess_days' => $excess['lateExcess'],
            'early_excess_days' => $excess['earlyExcess'],
            'late_deduction_amount' => $late,
            'early_deduction_amount' => $early,
            'total_deduction_amount' => round($late + $early, 2),
        ];

        // Soft guard rail — observational only, no behavior change.
        if ($basic > 0 && $result['total_deduction_amount'] > 0.5 * $basic) {
            Log::channel('daily')->warning('Late/Early deduction exceeds 50% of basic salary', [
                'user_id' => $employee->id,
                'tenant_id' => $tenantId,
                'year_month' => $yearMonth,
                'basic_salary' => $basic,
                'result' => $result,
            ]);
        }

        return $result;
    }

    /**
     * Per-excess-day deduction amount for one rule, keyed by its selected
     * mode. Fixed Amount uses the entered rupee amount verbatim (per excess
     * day, same "per occurrence" framing as the other three modes); Half Day
     * / Full Day hardcode 0.5x / 1.0x of the daily rate; Custom Multiplier
     * uses the admin-entered multiplier (e.g. 0.3x, 1.5x).
     */
    private function amountForExcess(int $excessDays, float $dailyRate, string $mode, ?float $amount, float $multiplier): float
    {
        if ($excessDays <= 0) {
            return 0.0;
        }

        return match ($mode) {
            'fixed_amount' => round($excessDays * (float) ($amount ?? 0), 2),
            'half_day' => round($excessDays * $dailyRate * 0.5, 2),
            'full_day' => round($excessDays * $dailyRate * 1.0, 2),
            default => round($excessDays * $dailyRate * $multiplier, 2), // custom_multiplier
        };
    }

    /**
     * Basic salary + divisor config, engine-agnostic.
     *
     * 1) legacy UserPayroll->payrollMaster if an assignment exists (works
     *    for legacy tenants and for dynamic tenants that still carry a
     *    shadow UserPayroll row from before cutover — cutover never deletes
     *    it);
     * 2) else the dynamic structure's raw (pre-proration) 'basic' catalog
     *    component amount, divisor defaults to 'calendar_days'/26 — the
     *    same fallback literal already used throughout
     *    MonthlyPayrollController's own overtime-rate code.
     *
     * @return array{0: float, 1: string, 2: int}
     */
    private function resolveRateInputs(User $employee, int $tenantId, string $yearMonth): array
    {
        $monthEnd = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth()->toDateString();

        $userPayroll = UserPayroll::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->forUser($employee->id)
            ->effective($monthEnd)
            ->orderByDesc('effective_from')
            ->first();

        if ($userPayroll && $userPayroll->payrollMaster) {
            return [
                (float) $userPayroll->basic_salary,
                $userPayroll->payrollMaster->ot_rate_divisor_mode ?? 'calendar_days',
                (int) ($userPayroll->payrollMaster->ot_fixed_working_days ?? 26),
            ];
        }

        return [
            $this->dynamicEngine->rawComponentAmount($employee, $tenantId, $yearMonth, 'basic'),
            'calendar_days',
            26,
        ];
    }
}
