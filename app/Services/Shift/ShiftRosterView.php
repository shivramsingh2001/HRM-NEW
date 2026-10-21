<?php

namespace App\Services\Shift;

use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;

/**
 * Shift Requests → "Shift Roster" tab: a 7-day grid of who works which shift.
 * An employee sees only their own row; a manager sees themselves + the people
 * who report to them; admin / HR see every active employee. Read-only — the
 * cells come straight from user_shifts, so approved swaps / changes show up.
 */
class ShiftRosterView
{
    public const PER_PAGE = 15;

    /**
     * @return array{dates: list<array{date:string,day:string,label:string,today:bool}>, rows: list<array>, pagination: array}
     */
    public function week(User $viewer, Carbon $start, ?string $search = null, int $page = 1): array
    {
        $start = $start->copy()->startOfDay();
        $end = $start->copy()->addDays(6);
        $dates = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        $users = $this->visibleUsers($viewer, $search)->paginate(self::PER_PAGE, ['*'], 'page', $page);
        $ids = collect($users->items())->pluck('id');

        $shifts = UserShift::with('shift:id,name,color_code,start_time,end_time,is_overnight')
            ->whereIn('user_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('is_additional')
            ->get()
            ->groupBy(fn ($s) => $s->user_id . '|' . substr((string) $s->date, 0, 10));

        $weekoffs = UserWeekoffs::whereIn('user_id', $ids)->where('status', 1)->get()->groupBy('user_id');
        $holidays = Holiday::where('status', 1)
            ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
            ->get(['name', 'start_date'])
            ->keyBy(fn ($h) => substr((string) $h->start_date, 0, 10));
        $leaves = Leave::whereIn('user_id', $ids)->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$start->toDateString()])
            ->get(['user_id', 'start_date', 'end_date']);

        $rows = collect($users->items())->map(function (User $u) use ($dates, $shifts, $weekoffs, $holidays, $leaves, $viewer) {
            $days = [];
            foreach ($dates as $d) {
                $ds = $d->toDateString();
                $onLeave = $leaves->first(fn ($l) => $l->user_id == $u->id
                    && substr((string) $l->start_date, 0, 10) <= $ds && substr((string) ($l->end_date ?: $l->start_date), 0, 10) >= $ds);
                $days[$ds] = [
                    'shifts' => ($shifts->get($u->id . '|' . $ds) ?? collect())->filter(fn ($s) => $s->shift)->map(fn ($s) => [
                        'name' => $s->shift->name,
                        'time' => substr((string) $s->shift->start_time, 0, 5) . ' – ' . substr((string) $s->shift->end_time, 0, 5),
                        'color' => $s->shift->color_code ?: '#0D6EFD',
                        'extra' => (bool) $s->is_additional,
                        'overnight' => (bool) $s->shift->is_overnight,
                    ])->values()->all(),
                    'week_off' => WeekOffPredicate::isWeekOff($weekoffs->get($u->id) ?? collect(), $d),
                    'holiday' => $holidays->get($ds)->name ?? null,
                    'leave' => (bool) $onLeave,
                ];
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee_id,
                'is_me' => $u->id === $viewer->id,
                'days' => $days,
            ];
        })->all();

        return [
            'dates' => $dates->map(fn ($d) => [
                'date' => $d->toDateString(),
                'day' => $d->format('D'),
                'label' => $d->format('d M'),
                'today' => $d->isToday(),
            ])->all(),
            'rows' => $rows,
            'pagination' => [
                'total' => $users->total(), 'current_page' => $users->currentPage(), 'last_page' => $users->lastPage(),
                'from' => $users->firstItem(), 'to' => $users->lastItem(),
            ],
        ];
    }

    /** Employee → self; manager → self + direct reports; admin / HR → everyone active. Viewer first. */
    private function visibleUsers(User $viewer, ?string $search)
    {
        $q = User::where('tenant_id', $viewer->tenant_id)->where('status', 1);

        if ($viewer->role === 'manager') {
            $q->where(fn ($w) => $w->where('id', $viewer->id)->orWhereHas('reportingHeads', fn ($r) => $r->where('reporting_head_id', $viewer->id)));
        } elseif (! in_array($viewer->role, ['admin', 'hr'], true)) {
            $q->where('id', $viewer->id);
        }

        if ($search) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('employee_id', 'like', "%{$search}%"));
        }

        return $q->orderByRaw('id = ? desc', [$viewer->id])->orderBy('name')->select(['id', 'name', 'employee_id']);
    }
}
