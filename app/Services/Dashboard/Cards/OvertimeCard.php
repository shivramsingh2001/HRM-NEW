<?php

namespace App\Services\Dashboard\Cards;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard — Overtime card: approved hours and pending requests in one month, top three.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class OvertimeCard
{
    /** Overtime card: approved hours and pending requests in one month, and who did the most. */
    public function forMonth(Carbon $month): array
    {
        $between = [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $hours = 'COALESCE(overtime_requests.approved_hours, overtime_requests.overtime_hours)';

        $top = \App\Models\OvertimeRequest::join('users', 'users.id', '=', 'overtime_requests.user_id')
            ->where('overtime_requests.status', 'approved')
            ->whereBetween('overtime_requests.date', $between)
            ->groupBy('users.id', 'users.name', 'users.employee_id')
            ->selectRaw("users.id, users.name, users.employee_id, SUM({$hours}) as hours")
            ->orderByDesc('hours')
            ->limit(3)
            ->get();

        return [
            'month_label' => $month->format('M Y'),
            'approved_hours' => (float) \App\Models\OvertimeRequest::where('status', 'approved')
                ->whereBetween('date', $between)->sum(DB::raw($hours)),
            'approved_count' => \App\Models\OvertimeRequest::where('status', 'approved')->whereBetween('date', $between)->count(),
            'pending' => \App\Models\OvertimeRequest::where('status', 'pending')->whereBetween('date', $between)->count(),
            'top' => $top,
        ];
    }
}
