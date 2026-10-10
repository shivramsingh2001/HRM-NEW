<?php

namespace App\Services\Dashboard\Cards;

use App\Models\Holiday;
use Carbon\Carbon;

/**
 * Dashboard — Holidays for the dashboard calendar.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class HolidayCalendarCard
{
    /**
     * Flat "Y-m-d" => holiday name map, used to highlight holiday dates
     * in the attendance calendar regardless of which month is displayed.
     */
    public function build()
    {
        $holidays = Holiday::where('status', 1)->get(['name', 'start_date', 'end_date']);
        $map = [];

        foreach ($holidays as $holiday) {
            $current = Carbon::parse($holiday->start_date);
            $end = Carbon::parse($holiday->end_date ?? $holiday->start_date);

            while ($current->lte($end)) {
                $map[$current->format('Y-m-d')] = $holiday->name;
                $current->addDay();
            }
        }

        return $map;
    }
}
