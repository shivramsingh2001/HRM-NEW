<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Day-status thresholds
    |--------------------------------------------------------------------------
    |
    | A day is classified by comparing hours actually worked against the hours
    | the employee was scheduled to work (their shift span minus break).
    |
    |   worked / expected >= present_ratio  -> present
    |   worked / expected >= half_ratio     -> half_day
    |   otherwise                           -> absent
    |
    | When the employee has no scheduled shift for the day we cannot compute a
    | ratio, so the absolute "fallback_hours" ladder is used instead.
    |
    */

    'ratio' => [
        'present' => env('ATTENDANCE_PRESENT_RATIO', 0.90),
        'half'    => env('ATTENDANCE_HALF_RATIO', 0.50),
    ],

    'fallback_hours' => [
        'present' => env('ATTENDANCE_FALLBACK_PRESENT_HOURS', 8),
        'half'    => env('ATTENDANCE_FALLBACK_HALF_HOURS', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Overtime
    |--------------------------------------------------------------------------
    |
    | Hours worked beyond this count on a normal day are tallied as overtime in
    | the monthly summary (used only when a shift-based expectation is absent).
    |
    */
    'overtime_after_hours' => env('ATTENDANCE_OVERTIME_AFTER_HOURS', 9),

];
