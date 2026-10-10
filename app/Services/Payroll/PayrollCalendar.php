<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Holiday and week-off dates for the legacy payroll engine — moved out of
 * MonthlyPayrollController (code-quality plan, Phase 1) and switched from raw
 * SQL to the query builder with the same results. Every query carries an
 * explicit tenant_id; no company = no dates (the raw `tenant_id = NULL`
 * matched nothing, and the builder would turn a null into `IS NULL`).
 */
class PayrollCalendar
{
    /**
     * Every holiday date (Y-m-d) in the range. Each holiday is stored as a
     * single-date record, so we just take its start_date.
     */
    public function getAllHolidayDates(string $startDate, string $endDate, ?int $tenantId = null): array
    {
        if ($tenantId === null) {
            return [];
        }

        $dates = DB::table('holidays')
            ->where('tenant_id', $tenantId)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->pluck('start_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->all();

        return array_unique($dates);
    }

    /**
     * Every week-off date for one employee in the range, sorted. Supports
     * three off_type values: day_based (every e.g. Sunday), date_based (one
     * date), range_based (e.g. a company shutdown).
     */
    public function getAllWeekoffDates(int $userId, string $startDate, string $endDate, ?int $tenantId = null): array
    {
        if ($tenantId === null) {
            return [];
        }

        $weekoffConfigs = DB::table('user_weekoffs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->get();

        if ($weekoffConfigs->isEmpty()) {
            return [];
        }

        $weekoffDates = [];
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        foreach ($weekoffConfigs as $config) {
            // Recurring weekly day (e.g. every Sunday)
            if ($config->off_type === 'day_based' && ! empty($config->day_name)) {
                $dayName = strtolower($config->day_name);
                $cur = $start->copy();
                while ($cur <= $end) {
                    if (strtolower($cur->format('l')) === $dayName) {
                        $weekoffDates[] = $cur->format('Y-m-d');
                    }
                    $cur->addDay();
                }
            }

            // Specific single date
            if ($config->off_type === 'date_based' && ! empty($config->off_date)) {
                $offDate = Carbon::parse($config->off_date);
                if ($offDate >= $start && $offDate <= $end) {
                    $weekoffDates[] = $offDate->format('Y-m-d');
                }
            }

            // Date range (e.g. company shutdown)
            if (
                $config->off_type === 'range_based'
                && ! empty($config->start_date)
                && ! empty($config->end_date)
            ) {
                $rangeStart = Carbon::parse($config->start_date)->max($start);
                $rangeEnd = Carbon::parse($config->end_date)->min($end);
                $cur = $rangeStart->copy();
                while ($cur <= $rangeEnd) {
                    $weekoffDates[] = $cur->format('Y-m-d');
                    $cur->addDay();
                }
            }
        }

        $weekoffDates = array_unique($weekoffDates);
        sort($weekoffDates);

        return $weekoffDates;
    }
}
