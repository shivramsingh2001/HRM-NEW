<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Single implementation of shift-time math: "is this shift overnight", its
 * real start/end instants on a date, and its span. Replaces the copies of
 * "if end <= start then addDay()" that used to live in every controller.
 *
 * $shift may be an Eloquent model, stdClass or array exposing start_time /
 * end_time ("HH:MM" or "HH:MM:SS") and optionally is_overnight. When
 * is_overnight is present it wins; otherwise end <= start means overnight.
 */
class ShiftWindow
{
    public static function isOvernight($shift): bool
    {
        $flag = self::prop($shift, 'is_overnight');
        if ($flag !== null && $flag !== '') {
            return (bool) $flag;
        }

        $start = self::prop($shift, 'start_time');
        $end = self::prop($shift, 'end_time');
        if ($start === null || $end === null) {
            return false;
        }

        return self::toMinutes($end) <= self::toMinutes($start);
    }

    /**
     * Scheduled start/end instants of $shift on $date (Y-m-d). The end is on
     * the next day for an overnight shift.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(string $date, $shift, ?string $tz = null): array
    {
        $start = Carbon::parse($date . ' ' . self::prop($shift, 'start_time'), $tz);
        $end = Carbon::parse($date . ' ' . self::prop($shift, 'end_time'), $tz);

        if (self::isOvernight($shift)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * Minutes from start to end, crossing midnight when overnight. With
     * $overnight null the times decide (end <= start = next day).
     */
    public static function spanMinutes(?string $start, ?string $end, ?bool $overnight = null): int
    {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return 0;
        }

        $span = self::toMinutes($end) - self::toMinutes($start);
        $overnight ??= $span <= 0;

        if ($overnight && $span <= 0) {
            $span += 24 * 60;
        }

        return max(0, $span);
    }

    /**
     * Paid working hours (span minus break minutes), rounded to 2 dp.
     */
    public static function workingHours(?string $start, ?string $end, $breakMinutes = 0, ?bool $overnight = null): float
    {
        $minutes = self::spanMinutes($start, $end, $overnight) - (int) ($breakMinutes ?? 0);

        return round(max(0, $minutes) / 60, 2);
    }

    /**
     * Validation message when the times don't match the overnight checkbox,
     * null when they agree. A day shift must end after it starts; an overnight
     * shift ends on the next day, so its end time is on/before its start time.
     */
    public static function timesError(?string $start, ?string $end, bool $overnight): ?string
    {
        if (!$start || !$end) {
            return null;
        }

        $endsAfterStart = self::toMinutes($end) > self::toMinutes($start);

        if (!$overnight && !$endsAfterStart) {
            return "End time must be after start time. Tick 'Overnight shift' if the shift ends on the next day.";
        }
        if ($overnight && $endsAfterStart) {
            return 'An overnight shift ends on the next day, so its end time must be earlier than its start time.';
        }

        return null;
    }

    /**
     * "HH:MM" / "HH:MM:SS" -> minutes since midnight. 0 on garbage.
     */
    public static function toMinutes(?string $time): int
    {
        if ($time === null || !preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $m)) {
            return 0;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    private static function prop($obj, string $key)
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
