<?php

use Carbon\Carbon;

if (! function_exists('clock_out_time')) {
    /**
     * A clock-out time for display, marked when it falls on a later calendar
     * day than the attendance date: "09:00 AM (+1 day)" for a day clocked in
     * at 06:30 and out at 09:00 the next morning. Null / empty → $empty.
     */
    function clock_out_time($clockOut, $date, string $format = 'h:i A', string $empty = '—'): string
    {
        if (empty($clockOut) || str_starts_with((string) $clockOut, '0000-00-00')) {
            return $empty;
        }

        $out = Carbon::parse($clockOut);
        $days = $date ? (int) Carbon::parse($date)->startOfDay()->diffInDays($out->copy()->startOfDay(), false) : 0;

        return $out->format($format) . ($days > 0 ? ' (+' . $days . ' day' . ($days > 1 ? 's' : '') . ')' : '');
    }
}
