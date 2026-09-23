<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single implementation of "is this date a week-off for this user", replacing
 * the two near-identical copies that used to live in ShiftController
 * (isWeekOffOn / checkDateIsWeekOff).
 */
class WeekOffPredicate
{
    public static function isWeekOff(Collection $userWeekoffs, Carbon $date): bool
    {
        $dateString = $date->format('Y-m-d');
        $dayName = $date->format('l');

        foreach ($userWeekoffs as $weekoff) {
            if (($weekoff->status ?? 1) != 1) {
                continue;
            }

            if ($weekoff->off_type === 'day_based') {
                if (strtolower($weekoff->day_name ?? '') !== strtolower($dayName)) {
                    continue;
                }
                if ($weekoff->start_date && $dateString < Carbon::parse($weekoff->start_date)->format('Y-m-d')) {
                    continue;
                }
                if ($weekoff->end_date && $dateString > Carbon::parse($weekoff->end_date)->format('Y-m-d')) {
                    continue;
                }
                return true;
            }

            if ($weekoff->off_type === 'date_based') {
                if ($weekoff->start_date && $weekoff->end_date
                    && $dateString >= Carbon::parse($weekoff->start_date)->format('Y-m-d')
                    && $dateString <= Carbon::parse($weekoff->end_date)->format('Y-m-d')) {
                    return true;
                }
            }
        }

        return false;
    }
}
