<?php

namespace App\Services\Dashboard\Cards;

use Carbon\Carbon;

/**
 * Dashboard — Meetings card: today's meetings (or the next few) and minutes not finalised.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class MeetingCard
{
    /** Meetings card: today's meetings (or the next few), and completed meetings whose minutes are not finalized. */
    public function build(): array
    {
        $today = Carbon::today()->toDateString();
        $columns = ['id', 'title', 'meeting_date', 'start_time', 'end_time', 'meeting_type', 'status'];

        $todays = \App\Models\Meeting::whereDate('meeting_date', $today)->where('status', '!=', 'cancelled')
            ->orderBy('start_time')->limit(5)->get($columns);
        $upcoming = $todays->isEmpty()
            ? \App\Models\Meeting::whereDate('meeting_date', '>', $today)->where('status', 'scheduled')
                ->orderBy('meeting_date')->orderBy('start_time')->limit(3)->get($columns)
            : collect();

        return [
            'today' => $todays,
            'upcoming' => $upcoming,
            'today_count' => \App\Models\Meeting::whereDate('meeting_date', $today)->where('status', '!=', 'cancelled')->count(),
            'minutes_pending' => \App\Models\Meeting::where('status', 'completed')
                ->where(fn ($q) => $q->whereNull('mom_status')->orWhere('mom_status', '!=', 'finalized'))->count(),
        ];
    }
}
