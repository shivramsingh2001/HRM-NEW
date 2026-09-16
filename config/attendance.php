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

    /*
    |--------------------------------------------------------------------------
    | Async downstream recompute (Tier 1 / W2)
    |--------------------------------------------------------------------------
    |
    | When true, a write through AttendanceEntryService no longer runs the
    | late-policy + monthly-summary recompute inside the request — it queues
    | RecalculateAttendanceMonth instead and stamps attendance_summaries.stale_at.
    | Requires a running queue worker on the "attendance" queue. Leave false
    | until that worker exists; the summary read path self-heals stale rows.
    |
    */
    'async_recompute' => env('ATTENDANCE_ASYNC_RECOMPUTE', false),

    /*
    |--------------------------------------------------------------------------
    | Monthly-summary read-through (Tier 1 / W3)
    |--------------------------------------------------------------------------
    |
    | When true, screens that show a monthly rollup read the persisted
    | attendance_summaries row (via AttendanceSummaryService::getMonthly) instead
    | of recomputing from raw rows on every request. Output keys are unchanged.
    | Leave false until `php artisan attendance:summary-diff` confirms parity.
    |
    */
    'summary_readthrough' => env('ATTENDANCE_SUMMARY_READTHROUGH', false),

    /*
    |--------------------------------------------------------------------------
    | Payroll attendance read-through + period lock (Tier 2 / T2-E)
    |--------------------------------------------------------------------------
    |
    | payroll_readthrough: monthly payroll sources its attendance figures from
    |   AttendanceSummaryService::getMonthly (via PayrollDaysService) instead of
    |   its own raw-SQL recompute. Same return keys. Validate with
    |   `php artisan attendance:payroll-days-diff` before enabling.
    |
    | period_autolock: when a MonthlyPayroll row moves to processed/paid, lock
    |   that tenant+month so attendance edits require an explicit override.
    |
    */
    'payroll_readthrough' => env('ATTENDANCE_PAYROLL_READTHROUGH', false),
    'period_autolock' => env('ATTENDANCE_PERIOD_AUTOLOCK', false),

];
