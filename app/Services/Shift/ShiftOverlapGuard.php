<?php

namespace App\Services\Shift;

use App\Models\Shift;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Would this shift overlap one the employee already works?" — the rule that
 * makes several shifts per day safe. Compares real time windows
 * (ShiftWindow::window), so an overnight shift on D-1 that runs into D, or a
 * shift on D that runs into D+1, is caught too. Back-to-back shifts
 * (one ends 14:00, the next starts 14:00) are allowed.
 */
class ShiftOverlapGuard
{
    /**
     * Dates (Y-m-d) on which $shift would overlap an existing user_shifts row,
     * mapped to the name of the shift it clashes with.
     *
     * @param  string[]  $dates
     * @param  int[]  $ignoreUserShiftIds  rows being replaced (e.g. the row being edited)
     * @return array<string,string>
     */
    public function conflicts(int $tenantId, int $userId, Shift $shift, array $dates, array $ignoreUserShiftIds = []): array
    {
        if ($dates === []) {
            return [];
        }

        sort($dates);
        $from = Carbon::parse($dates[0])->subDay()->toDateString();
        $to = Carbon::parse(end($dates))->addDay()->toDateString();

        $rows = DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->when($ignoreUserShiftIds !== [], fn ($q) => $q->whereNotIn('id', $ignoreUserShiftIds))
            ->get(['id', 'date', 'shift_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $shifts = Shift::withoutGlobalScopes()->whereIn('id', $rows->pluck('shift_id')->unique())->get()->keyBy('id');
        $byDate = $rows->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        $conflicts = [];
        foreach ($dates as $date) {
            [$start, $end] = ShiftWindow::window($date, $shift);

            foreach ([-1, 0, 1] as $offset) {
                $other = Carbon::parse($date)->addDays($offset)->toDateString();

                foreach ($byDate->get($other, collect()) as $row) {
                    $existing = $shifts->get($row->shift_id);
                    if (!$existing) {
                        continue;
                    }

                    [$exStart, $exEnd] = ShiftWindow::window($other, $existing);
                    if ($start->lt($exEnd) && $exStart->lt($end)) {
                        $conflicts[$date] = $existing->name;
                        continue 3;
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * "Overlaps Night Shift on 2026-10-03, 2026-10-04 …" for an error message.
     *
     * @param  array<string,string>  $conflicts
     */
    public static function describe(array $conflicts, int $limit = 3): string
    {
        $parts = [];
        foreach (array_slice($conflicts, 0, $limit, true) as $date => $name) {
            $parts[] = "{$name} on {$date}";
        }

        $more = count($conflicts) - count($parts);

        return implode(', ', $parts) . ($more > 0 ? " and {$more} more day(s)" : '');
    }
}
