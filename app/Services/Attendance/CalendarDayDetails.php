<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use Illuminate\Support\Collection;

/**
 * Extra per-day facts for the attendance calendars' hover card: the day's shift
 * and its timing, late / early / overtime minutes, clock-in / clock-out location,
 * and whether the day was regularized or marked by hand (and by whom).
 * Used by the /attendance calendar and the Team member profile calendar.
 */
class CalendarDayDetails
{
    /**
     * Sets ->details on every day record (objects with ->date) of one user.
     *
     * @param  Collection<int, \stdClass>  $records
     * @return Collection<int, \stdClass>
     */
    public function attach(Collection $records, int $userId): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $dates = $records->pluck('date')->filter()->sort()->values();
        $map = $this->forUserRange($userId, $dates->first(), $dates->last());

        return $records->each(function ($r) use ($map) {
            $r->details = $map[$r->date] ?? null;
        });
    }

    /** @return array<string, array<string, mixed>> date => details */
    public function forUserRange(int $userId, string $from, string $to): array
    {
        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->get(['id', 'date', 'shift_id', 'scheduled_shift_start', 'scheduled_shift_end', 'late_minutes',
                'early_departure_minutes', 'overtime_minutes', 'is_regularized', 'regularized_by', 'regularized_at',
                'marked_by', 'attendance_type', 'remarks', 'clock_in_address', 'clock_out_address',
                'check_in_distance', 'check_out_distance', 'location_verification'])
            ->keyBy(fn ($a) => substr((string) $a->date, 0, 10));

        // The day's main shift from the roster (covers absent / upcoming days too).
        $rosterShift = UserShift::where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->where('is_additional', 0)
            ->pluck('shift_id', 'date');

        $shiftIds = $attendances->pluck('shift_id')->merge($rosterShift->values())->filter()->unique();
        $shifts = $shiftIds->isEmpty() ? collect() : Shift::whereIn('id', $shiftIds)->get(['id', 'name', 'start_time', 'end_time'])->keyBy('id');

        $peopleIds = $attendances->pluck('regularized_by')->merge($attendances->pluck('marked_by'))->filter()->unique();
        $people = $peopleIds->isEmpty() ? collect() : User::whereIn('id', $peopleIds)->pluck('name', 'id');

        $out = [];
        $dates = $attendances->keys()->merge($rosterShift->keys())->unique();
        foreach ($dates as $date) {
            $a = $attendances->get($date);
            $shift = $shifts->get($a->shift_id ?? null) ?? $shifts->get($rosterShift->get($date));
            $start = $a->scheduled_shift_start ?? $shift->start_time ?? null;
            $end = $a->scheduled_shift_end ?? $shift->end_time ?? null;

            $out[$date] = [
                'shift_name' => $shift->name ?? null,
                'shift_time' => $start ? substr($start, 0, 5) . ($end ? ' – ' . substr($end, 0, 5) : '') : null,
                'late_minutes' => (int) ($a->late_minutes ?? 0),
                'early_minutes' => (int) ($a->early_departure_minutes ?? 0),
                'overtime_minutes' => (int) ($a->overtime_minutes ?? 0),
                'is_regularized' => (bool) ($a->is_regularized ?? false),
                'regularized_by' => $a && $a->regularized_by ? ($people[$a->regularized_by] ?? null) : null,
                'regularized_at' => $a->regularized_at ?? null,
                'marked_by' => $a && $a->marked_by ? ($people[$a->marked_by] ?? null) : null,
                'source' => $a->attendance_type ?? null,
                'remarks' => $a->remarks ?? null,
                'clock_in_address' => $a->clock_in_address ?? null,
                'clock_out_address' => $a->clock_out_address ?? null,
                'check_in_distance' => $a->check_in_distance ?? null,
                'check_out_distance' => $a->check_out_distance ?? null,
                'location_verification' => $a->location_verification ?? null,
            ];
        }

        return $out;
    }
}
