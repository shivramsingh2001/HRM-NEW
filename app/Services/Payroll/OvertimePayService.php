<?php

namespace App\Services\Payroll;

use App\Services\Attendance\OvertimePolicyService;

/**
 * Prices overtime for both payroll engines (Company Policies → Overtime →
 * rate): "multiplier" = the employee's hourly rate × multiplier, "fixed" = a
 * flat amount per hour. Employee 360 overrides win (via OvertimePolicyService).
 */
class OvertimePayService
{
    public function __construct(private OvertimePolicyService $policy)
    {
    }

    /**
     * The employee's base hourly rate:
     *  - day_based:  (basic / daysDivisor) / workingHoursPerDay, daysDivisor = calendar days
     *    or payroll_masters.ot_fixed_working_days ('fixed_working_days' mode)
     *  - hour_based: basic / (workingDays × workingHoursPerDay)
     */
    public function hourlyRate(
        float $basicSalary,
        string $calculationType,
        float $workingHoursPerDay,
        int $calendarDays,
        float $workingDays,
        string $divisorMode = 'calendar_days',
        int $fixedWorkingDays = 26
    ): float {
        if ($calculationType === 'hour_based') {
            return $basicSalary / max(1, $workingDays * $workingHoursPerDay);
        }

        $daysDivisor = $divisorMode === 'fixed_working_days' ? max(1, $fixedWorkingDays) : max(1, $calendarDays);

        return ($basicSalary / $daysDivisor) / max(1, $workingHoursPerDay);
    }

    /** Overtime pay per hour for this employee, given their base hourly rate. */
    public function ratePerHour(int $tenantId, int $userId, float $hourlyRate): float
    {
        $s = $this->policy->forEmployee($tenantId, $userId);
        if (($s->rate_type ?? 'multiplier') === 'fixed' && (float) ($s->fixed_rate_per_hour ?? 0) > 0) {
            return round((float) $s->fixed_rate_per_hour, 2);
        }

        return round($hourlyRate * (float) ($s->rate_multiplier ?? 1.5), 2);
    }

    /** ['type' => multiplier|fixed, 'multiplier' => float, 'fixed' => ?float, 'label' => '×1.5' / '₹200/hour']. */
    public function describe(int $tenantId, int $userId): array
    {
        $s = $this->policy->forEmployee($tenantId, $userId);
        $fixed = ($s->rate_type ?? 'multiplier') === 'fixed' && (float) ($s->fixed_rate_per_hour ?? 0) > 0;
        $multiplier = (float) ($s->rate_multiplier ?? 1.5);

        return [
            'type' => $fixed ? 'fixed' : 'multiplier',
            'multiplier' => $multiplier,
            'fixed' => $fixed ? (float) $s->fixed_rate_per_hour : null,
            'label' => $fixed ? '₹' . number_format((float) $s->fixed_rate_per_hour, 2) . '/hour' : '×' . rtrim(rtrim(number_format($multiplier, 2), '0'), '.'),
        ];
    }
}
