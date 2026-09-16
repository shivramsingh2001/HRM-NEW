<?php

namespace App\Exceptions;

/**
 * Tier 2 / T2-E — thrown when an attendance write targets a month that payroll
 * has locked. Carries the ApiException taxonomy so /api/v1 renders it as
 * `attendance.period_locked` (409); legacy callers catch it as a RuntimeException.
 */
class PeriodLockedException extends ApiException
{
    public function __construct(string $yearMonth)
    {
        parent::__construct(
            'attendance.period_locked',
            "Attendance for {$yearMonth} is locked (payroll has been processed). Reopen the period to edit it.",
            409,
            ['year_month' => $yearMonth],
        );
    }
}
