<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use App\Support\ShiftWindow;

/**
 * Pure time math for attendance. No tenant, no policy, no DB.
 *
 * Every attendance write path (mobile clockIn/clockOut, admin manual mark,
 * auto-clock-out, regularization) MUST route its duration / late / overtime
 * calculations through this class so the numbers are consistent and never
 * wrap at 24h.
 */
class AttendanceCalculator
{
    /**
     * A shift is "overnight" when its is_overnight flag is set or, without the
     * flag, when its end time is at or before its start time (e.g. 22:00 ->
     * 06:00). See App\Support\ShiftWindow.
     */
    public function isOvernight($shift): bool
    {
        return ShiftWindow::isOvernight($shift);
    }

    /**
     * Given a clock-in Carbon and a raw clock-out Carbon (same calendar day, or a
     * plain time attached to the attendance date), return the real clock-out,
     * advancing one day when the punch clearly crossed midnight or the shift is
     * overnight.
     */
    public function resolveClockOut(CarbonInterface $clockIn, CarbonInterface $rawOut, $shift = null): Carbon
    {
        $out = $rawOut->copy();

        if ($shift !== null && $this->isOvernight($shift) && $out->lessThanOrEqualTo($clockIn)) {
            $out->addDay();
        } elseif ($out->lessThanOrEqualTo($clockIn)) {
            $out->addDay();
        }

        return $out;
    }

    /**
     * Whole seconds worked between two instants (never negative).
     */
    public function workedSeconds(CarbonInterface $in, CarbonInterface $out): int
    {
        $seconds = $out->getTimestamp() - $in->getTimestamp();

        return $seconds > 0 ? $seconds : 0;
    }

    /**
     * Format a duration in seconds as "H:i:s" WITHOUT wrapping at 24h.
     * 26h05m -> "26:05:00" (gmdate() would return "02:05:00").
     */
    public function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    /**
     * Duration in decimal hours, rounded to 2 dp.
     */
    public function decimalHours(int $seconds): float
    {
        return round(max(0, $seconds) / 3600, 2);
    }

    /**
     * Tier 1 / W4 — a local wall-clock "Y-m-d H:i:s" string in $tz as a UTC
     * Carbon. Null for empty / zero / unparseable input.
     */
    public function toUtc(?string $localDateTime, string $tz): ?Carbon
    {
        if (empty($localDateTime) || str_starts_with((string) $localDateTime, '0000-00-00')) {
            return null;
        }
        try {
            return Carbon::parse($localDateTime, $tz)->utc();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tier 1 / W4 — the true instant of a punch on an attendance row: the
     * `{$field}_utc` column when present, else the legacy local string parsed in
     * the row's own `tz` (falling back to $tz). Returns a UTC Carbon or null.
     *
     * @param  object|array  $row  needs {$field}, optionally {$field}_utc and tz
     */
    public function instant($row, string $field, string $tz): ?Carbon
    {
        $get = fn ($k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);

        $utc = $get($field . '_utc');
        if (! empty($utc) && ! str_starts_with((string) $utc, '0000-00-00')) {
            try {
                return Carbon::parse($utc, 'UTC');
            } catch (\Throwable $e) {
                // fall through to the local string
            }
        }

        return $this->toUtc($get($field), $get('tz') ?: $tz);
    }

    /**
     * Minutes the clock-in is late versus the scheduled start, after the shift's
     * grace period. 0 when on time / early / no shift.
     */
    public function lateMinutes($shift, CarbonInterface $scheduledStart, CarbonInterface $clockIn): int
    {
        if ($shift === null) {
            return 0;
        }

        if ($clockIn->lessThanOrEqualTo($scheduledStart)) {
            return 0;
        }

        $grace = (int) ($this->prop($shift, 'grace_minutes') ?? 0);
        $late = (int) floor($scheduledStart->diffInSeconds($clockIn) / 60);

        return $late > $grace ? $late : 0;
    }

    /**
     * Minutes the clock-out is early versus the scheduled end, after the
     * shift's grace period. 0 when on time / late / no shift. Symmetric to
     * lateMinutes().
     */
    public function earlyDepartureMinutes($shift, CarbonInterface $scheduledEnd, CarbonInterface $clockOut): int
    {
        if ($shift === null) {
            return 0;
        }

        if ($clockOut->greaterThanOrEqualTo($scheduledEnd)) {
            return 0;
        }

        $grace = (int) ($this->prop($shift, 'grace_minutes') ?? 0);
        $early = (int) floor($clockOut->diffInSeconds($scheduledEnd) / 60);

        return $early > $grace ? $early : 0;
    }

    /**
     * The calendar date a punch should be attributed to. A night-shift punch
     * landing 00:00-05:59, before the shift's own start hour, is attributed to
     * the previous day. Lifted verbatim from the mobile clock-in "night shift
     * fix" so every punch source (mobile, web, manual, biometric, kiosk)
     * agrees on the same attendance date.
     */
    public function resolveAttendanceDate(CarbonInterface $punchedAt, $shift = null): string
    {
        $date = $punchedAt->format('Y-m-d');

        [$start, $end] = $this->shiftTimes($shift);
        if ($shift === null || $start === null || $end === null) {
            return $date;
        }

        $shiftStart = Carbon::parse($start);

        if (ShiftWindow::isOvernight($shift)) {
            $hour = (int) $punchedAt->format('H');
            $startHour = (int) $shiftStart->format('H');
            if ($hour >= 0 && $hour < 6 && $hour < $startHour) {
                return $punchedAt->copy()->subDay()->format('Y-m-d');
            }
        }

        return $date;
    }

    /**
     * Expected paid seconds for a shift (end - start, +24h when overnight),
     * minus its break_time (minutes). 0 when the shift is unknown.
     */
    public function expectedWorkSeconds($shift): int
    {
        [$start, $end] = $this->shiftTimes($shift);
        if ($start === null || $end === null) {
            return 0;
        }

        $span = ShiftWindow::spanMinutes($start, $end, ShiftWindow::isOvernight($shift));

        $break = (int) ($this->prop($shift, 'break_time') ?? 0);
        $span = max(0, $span - $break);

        return $span * 60;
    }

    /**
     * Seconds that decide present / half-day / absent for an attendance row.
     * Multi-shift: on a day with additional (2nd+) shift work, only the
     * primary shift's work counts — the day's worked_hours minus
     * extra_shift_minutes (the extra is paid as overtime instead). Any other
     * day keeps $daySeconds unchanged.
     *
     * @param  object|array  $row  needs worked_hours, extra_shift_minutes
     */
    public function primaryWorkedSeconds($row, int $daySeconds): int
    {
        $extra = (int) ($this->prop($row, 'extra_shift_minutes') ?? 0);
        if ($extra <= 0) {
            return $daySeconds;
        }

        $worked = (int) round(((float) ($this->prop($row, 'worked_hours') ?? 0)) * 3600);

        return max(0, $worked - $extra * 60);
    }

    // ---------------------------------------------------------------------

    /**
     * @return array{0: ?string, 1: ?string}  [start_time, end_time]
     */
    private function shiftTimes($shift): array
    {
        return [$this->prop($shift, 'start_time'), $this->prop($shift, 'end_time')];
    }

    private function prop($obj, string $key)
    {
        if (is_array($obj)) {
            return $obj[$key] ?? null;
        }
        if (is_object($obj)) {
            return $obj->{$key} ?? null;
        }
        return null;
    }
}
