<?php

namespace App\Support;

use App\Services\Attendance\FloatingWeekOffAllocator;
use App\Services\Shift\WeekOffSwapService;

/**
 * Days off the sandwich rule must always pay (SandwichRule::offDayPay(..., $alwaysPaid)):
 * a week-off swap's replacement day (a week-off was worked for it) and a
 * floating week-off (part of the employee's monthly count). Both payroll
 * engines ask here.
 */
class AlwaysPaidOffDays
{
    /** @return string[] Y-m-d */
    public static function dates(int $tenantId, int $userId, string $from, string $to): array
    {
        return array_values(array_unique(array_merge(
            WeekOffSwapService::replacementDates($tenantId, $userId, $from, $to),
            FloatingWeekOffAllocator::floatingDates($tenantId, $userId, $from, $to),
        )));
    }
}
