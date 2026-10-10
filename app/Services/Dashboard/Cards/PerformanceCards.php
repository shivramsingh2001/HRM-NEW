<?php

namespace App\Services\Dashboard\Cards;

use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard — Top performers of a month and the month picker.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class PerformanceCards
{
    /**
     * Top 5 performers for a given month, ranked by attendance regularity.
     * $month expects 'Y-m' (e.g. '2026-09'); defaults to the current month.
     */
    public function topPerformers($month = null)
    {
        $monthDate = $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now();
        $workingDays = max(1, $this->getWorkingDaysThisMonth($monthDate));

        $employees = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('attendances', function ($join) use ($monthDate) {
                $join->on('users.id', '=', 'attendances.user_id')
                    ->whereMonth('attendances.date', $monthDate->month)
                    ->whereYear('attendances.date', $monthDate->year)
                    ->whereNotNull('attendances.clock_in');
            })
            ->select(
                'users.id',
                'users.name',
                'users.employee_id',
                'user_basic_details.profile_image',
                DB::raw('COUNT(DISTINCT attendances.id) as present_days')
            )
            ->where('users.status', 1)
            ->where('users.role', '!=', 'admin')
            ->groupBy('users.id', 'users.name', 'users.employee_id', 'user_basic_details.profile_image')
            ->orderBy('present_days', 'desc')
            ->limit(5)
            ->get();

        return $employees->map(function ($employee) use ($workingDays) {
            $employee->performance_percentage = min(100, round(($employee->present_days / $workingDays) * 100));

            return $employee;
        });
    }

    /**
     * Last 6 months (including current) for the performance month filter dropdown.
     */
    public function monthOptions()
    {
        return collect(range(0, 5))->map(function ($i) {
            $date = Carbon::now()->subMonths($i);

            return ['value' => $date->format('Y-m'), 'label' => $date->format('M Y')];
        });
    }

    private function getWorkingDaysThisMonth($monthDate = null)
    {
        $monthDate = $monthDate ? $monthDate->copy() : Carbon::now();
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $holidays = Holiday::whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->where('status', 1)
            ->get();

        $workingDays = 0;
        $current = $startOfMonth->copy();

        while ($current <= $endOfMonth) {
            // Skip weekends (assuming Saturday and Sunday are weekends)
            if (! $current->isSaturday() && ! $current->isSunday()) {
                $isHoliday = false;
                foreach ($holidays as $holiday) {
                    if ($current->between($holiday->start_date, $holiday->end_date)) {
                        $isHoliday = true;
                        break;
                    }
                }
                if (! $isHoliday) {
                    $workingDays++;
                }
            }
            $current->addDay();
        }

        return $workingDays;
    }
}
