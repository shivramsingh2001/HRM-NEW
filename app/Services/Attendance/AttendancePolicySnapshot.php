<?php

namespace App\Services\Attendance;

use Carbon\CarbonInterface;

/**
 * An immutable, resolved attendance policy for one (tenant, date).
 *
 * Every classifier consults a snapshot instead of config()/constants, so the
 * rules can vary per tenant and per effective_from without the day-status logic
 * being duplicated. Built by App\Services\Attendance\PolicyResolver.
 */
final class AttendancePolicySnapshot
{
    public function __construct(
        public readonly ?int $tenantId,
        public readonly string $effectiveFrom,
        public readonly float $presentRatio = 0.90,
        public readonly float $halfDayRatio = 0.50,
        public readonly float $fallbackPresentHours = 8.0,
        public readonly float $fallbackHalfHours = 4.0,
        public readonly ?float $fullDayMinHours = null,
        public readonly float $overtimeAfterHours = 9.0,
        public readonly float $overtimeMultiplier = 1.0,
        public readonly int $graceMinutes = 0,
        public readonly int $roundingMinutes = 0,
        public readonly bool $lateHalfdayEnabled = false,
        public readonly int $monthlyLateAllowance = 30,
        public readonly ?float $minRestHours = null,
        public readonly ?float $maxDailyHours = null,
        public readonly bool $sandwichLeave = false,
    ) {
    }

    /**
     * The config-only default, identical to the pre-policy-engine behaviour.
     */
    public static function default(): self
    {
        return new self(
            tenantId: null,
            effectiveFrom: '2000-01-01',
            presentRatio: (float) config('attendance.ratio.present', 0.90),
            halfDayRatio: (float) config('attendance.ratio.half', 0.50),
            fallbackPresentHours: (float) config('attendance.fallback_hours.present', 8),
            fallbackHalfHours: (float) config('attendance.fallback_hours.half', 4),
            overtimeAfterHours: (float) config('attendance.overtime_after_hours', 9),
        );
    }

    /**
     * @param  array<string,mixed>|object  $row  a row from `attendance_policies`
     */
    public static function fromRow($row): self
    {
        $r = (array) $row;
        $get = fn (string $k, $default) => array_key_exists($k, $r) && $r[$k] !== null ? $r[$k] : $default;

        return new self(
            tenantId: $r['tenant_id'] ?? null,
            effectiveFrom: (string) $get('effective_from', '2000-01-01'),
            presentRatio: (float) $get('present_ratio', 0.90),
            halfDayRatio: (float) $get('half_day_ratio', 0.50),
            fallbackPresentHours: (float) $get('fallback_present_hours', 8),
            fallbackHalfHours: (float) $get('fallback_half_hours', 4),
            fullDayMinHours: ($r['full_day_min_hours'] ?? null) !== null ? (float) $r['full_day_min_hours'] : null,
            overtimeAfterHours: (float) $get('overtime_after_hours', 9),
            overtimeMultiplier: (float) $get('overtime_multiplier', 1.0),
            graceMinutes: (int) $get('grace_minutes', 0),
            roundingMinutes: (int) $get('rounding_minutes', 0),
            lateHalfdayEnabled: (bool) $get('late_halfday_enabled', false),
            monthlyLateAllowance: (int) $get('monthly_late_allowance', 30),
            minRestHours: ($r['min_rest_hours'] ?? null) !== null ? (float) $r['min_rest_hours'] : null,
            maxDailyHours: ($r['max_daily_hours'] ?? null) !== null ? (float) $r['max_daily_hours'] : null,
            sandwichLeave: (bool) $get('sandwich_leave', false),
        );
    }

    /**
     * worked-hours -> present | half_day | absent, against a scheduled
     * expectation; falls back to the absolute-hours ladder with no shift.
     *
     * Byte-for-byte the logic previously copy-pasted into
     * AttendanceDayResolver / LatePolicyService / AutoClockOutCommand.
     */
    public function classify(float $workedHours, int $expectedSeconds): string
    {
        if ($expectedSeconds > 0) {
            $ratio = ($workedHours * 3600) / $expectedSeconds;
            if ($ratio >= $this->presentRatio) {
                return 'present';
            }
            return $ratio >= $this->halfDayRatio ? 'half_day' : 'absent';
        }

        if ($workedHours >= $this->fallbackPresentHours) {
            return 'present';
        }
        return $workedHours >= $this->fallbackHalfHours ? 'half_day' : 'absent';
    }

    /**
     * Was the arrival late once the grace window is allowed for?
     */
    public function isLate(int $lateMinutes): bool
    {
        return $lateMinutes > $this->graceMinutes;
    }

    /**
     * Hours worked beyond the overtime threshold (raw, before any multiplier).
     */
    public function overtimeHours(float $workedHours): float
    {
        return $workedHours > $this->overtimeAfterHours
            ? round($workedHours - $this->overtimeAfterHours, 2)
            : 0.0;
    }

    /**
     * Snap a punch to the nearest `rounding_minutes`. A 0 setting is a no-op.
     */
    public function roundClock(CarbonInterface $time): CarbonInterface
    {
        if ($this->roundingMinutes <= 0) {
            return $time;
        }

        $step = $this->roundingMinutes * 60;
        $ts = $time->getTimestamp();
        $rounded = (int) (round($ts / $step) * $step);

        return $time->copy()->setTimestamp($rounded);
    }
}
