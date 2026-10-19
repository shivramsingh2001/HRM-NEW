<?php

namespace App\Services\Attendance;

use App\Models\UserWeekoffs;
use App\Services\AttendanceSummaryService;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Floating week-offs (plan C1, 2026-10-17): an employee gets N days off a month
 * on any day instead of fixed days (Company Policies → Floating week-offs, or
 * per employee on Employee 360 → Policies).
 *
 * allocateMonth() looks at the month's days up to yesterday and turns the days
 * the employee did not come — no clock-in, not a holiday, not on approved
 * leave, not already a week-off, not hand-marked by an admin — into week-offs,
 * in date order, until the month's count is used (optionally at most M in one
 * Mon–Sun week). Each is an ordinary one-day date_based `user_weekoffs` row, so
 * every screen, report, API and payroll engine reads it as a week-off;
 * `floating_weekoff_days` remembers which rows are floating so the next run can
 * move them (e.g. a leave approved later for one of those days frees its slot
 * for the next missed day). The 5th+ missed day stays Absent.
 *
 * Count: the policy in force on the 1st of the month, pro-rated for a mid-month
 * joiner / leaver (rounded). Payroll-locked months are never touched.
 * Run nightly by `weekoffs:allocate-floating`.
 */
class FloatingWeekOffAllocator
{
    public function __construct(private PolicyResolver $policies)
    {
    }

    /**
     * @return array{added: string[], removed: string[], skipped: ?string}
     */
    public function allocateMonth(int $tenantId, int $userId, string $yearMonth): array
    {
        $result = ['added' => [], 'removed' => [], 'skipped' => null];
        if (app(PeriodLockService::class)->isLocked($tenantId, $yearMonth)) {
            $result['skipped'] = 'locked';

            return $result;
        }

        $monthRange = [$yearMonth . '-01', Carbon::parse($yearMonth . '-01')->endOfMonth()->toDateString()];
        $result['removed'] = $this->releaseWorkedPlannedDays($tenantId, $userId, $monthRange);
        $existing = DB::table('floating_weekoff_days')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('source', 'auto')->whereBetween('date', $monthRange)
            ->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $planned = $this->plannedDates($tenantId, $userId, $monthRange[0], $monthRange[1]);
        $wanted = $this->wantedDays($tenantId, $userId, $yearMonth, $existing->pluck('user_weekoff_id')->filter()->all(), $planned);

        DB::transaction(function () use ($tenantId, $userId, $existing, $wanted, &$result) {
            foreach ($existing as $date => $row) {
                if (! in_array($date, $wanted, true)) {
                    UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($row->user_weekoff_id)->delete();
                    DB::table('floating_weekoff_days')->where('id', $row->id)->delete();
                    $result['removed'][] = $date;
                }
            }
            foreach ($wanted as $date) {
                if ($existing->has($date)) {
                    continue;
                }
                $off = UserWeekoffs::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId, 'user_id' => $userId, 'off_type' => 'date_based',
                    'start_date' => $date, 'end_date' => $date, 'status' => 1, 'description' => 'Floating week-off',
                    'created_by' => \Illuminate\Support\Facades\Auth::id() ?? $userId,   // nightly run: no actor
                ]);
                DB::table('floating_weekoff_days')->insert(['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $date,
                    'user_weekoff_id' => $off->id, 'created_at' => now(), 'updated_at' => now()]);
                $result['added'][] = $date;
            }
        });

        if ($result['added'] || $result['removed']) {
            try {
                app(AttendanceSummaryService::class)->updateMonthlySummary($userId, $yearMonth, $tenantId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /**
     * The month's days that should be floating week-offs now (Y-m-d, in order).
     *
     * @param  int[]  $floatingRowIds  the user_weekoffs rows this allocator wrote — not "already a week-off"
     * @param  string[]  $plannedDates  approved days planned ahead — they use the count (and their week) first
     * @return string[]
     */
    public function wantedDays(int $tenantId, int $userId, string $yearMonth, array $floatingRowIds = [], array $plannedDates = []): array
    {
        $policy = $this->policies->forUserMonth($tenantId, $userId, $yearMonth);
        if (! $policy->floatingWeekoffsEnabled || $policy->floatingWeekoffsPerMonth <= 0) {
            return [];
        }

        [$from, $to] = $this->window($tenantId, $userId, $yearMonth);
        if ($from === null) {
            return [];
        }
        $quota = $this->quota($policy->floatingWeekoffsPerMonth, $yearMonth, $tenantId, $userId) - count($plannedDates);
        $perWeek = $policy->floatingWeekoffsMaxPerWeek;

        $wanted = [];
        $weeks = [];
        foreach ($plannedDates as $date) {
            $weeks[Carbon::parse($date)->format('o-W')] = ($weeks[Carbon::parse($date)->format('o-W')] ?? 0) + 1;
        }
        foreach ($this->missedDays($tenantId, $userId, $from, $to, $floatingRowIds) as $date) {
            if (count($wanted) >= $quota) {
                break;
            }
            $week = Carbon::parse($date)->format('o-W');
            if ($perWeek > 0 && ($weeks[$week] ?? 0) >= $perWeek) {
                continue;
            }
            $weeks[$week] = ($weeks[$week] ?? 0) + 1;
            $wanted[] = $date;
        }

        return $wanted;
    }

    /** The month's count, pro-rated for the days employed (joining / leaving date), rounded. */
    public function quota(int $perMonth, string $yearMonth, int $tenantId, int $userId): int
    {
        $start = Carbon::parse($yearMonth . '-01');
        $end = $start->copy()->endOfMonth();
        [$joined, $left] = $this->employment($tenantId, $userId);
        $first = $joined && $joined->gt($start) ? $joined : $start;
        $last = $left && $left->lt($end) ? $left : $end;
        if ($first->gt($last)) {
            return 0;
        }
        $days = $first->diffInDays($last) + 1;

        return (int) round($perMonth * $days / $start->daysInMonth);
    }

    /** [first, last] day that can be judged: within employment and before today. @return array{0:?string,1:?string} */
    private function window(int $tenantId, int $userId, string $yearMonth): array
    {
        $start = Carbon::parse($yearMonth . '-01');
        $end = Carbon::yesterday()->min($start->copy()->endOfMonth());
        [$joined, $left] = $this->employment($tenantId, $userId);
        $from = $joined && $joined->gt($start) ? $joined : $start;
        $to = $left && $left->lt($end) ? $left : $end;

        return $from->gt($to) ? [null, null] : [$from->toDateString(), $to->toDateString()];
    }

    /** @return array{0:?Carbon,1:?Carbon} joining / leaving date */
    private function employment(int $tenantId, int $userId): array
    {
        $job = DB::table('user_job_details')->where('tenant_id', $tenantId)->where('user_id', $userId)->first(['joining_date', 'leaving_date']);
        $parse = fn ($d) => ! empty($d) && strtotime((string) $d) ? Carbon::parse($d)->startOfDay() : null;

        return [$parse($job->joining_date ?? null), $parse($job->leaving_date ?? null)];
    }

    /**
     * Days in [$from, $to] the employee did not come and that are not already
     * accounted for (holiday, approved leave, week-off, hand-marked day).
     *
     * @return string[]
     */
    private function missedDays(int $tenantId, int $userId, string $from, string $to, array $floatingRowIds): array
    {
        $attendance = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)->whereBetween('date', [$from, $to])
            ->get(['date', 'clock_in', 'attendance_type', 'marked_by'])->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $weekoffs = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)
            ->whereNotIn('id', $floatingRowIds ?: [0])->get();
        $leaves = DB::table('leaves')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'approved')
            ->where('start_date', '<=', $to)->whereRaw('COALESCE(end_date, start_date) >= ?', [$from])->get(['start_date', 'end_date']);
        $holidays = $this->holidayDates($tenantId, $from, $to);

        $out = [];
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $ds = $d->toDateString();
            $row = $attendance->get($ds);
            $worked = $row && ! empty($row->clock_in);
            $handMarked = $row && (($row->attendance_type ?? null) === 'manual' || ! empty($row->marked_by));
            $onLeave = $leaves->contains(fn ($l) => $ds >= substr((string) $l->start_date, 0, 10) && $ds <= substr((string) ($l->end_date ?: $l->start_date), 0, 10));
            if ($worked || $handMarked || $onLeave || isset($holidays[$ds]) || WeekOffPredicate::isWeekOff($weekoffs, $d)) {
                continue;
            }
            $out[] = $ds;
        }

        return $out;
    }

    /** @return array<string,bool> */
    private function holidayDates(int $tenantId, string $from, string $to): array
    {
        $dates = [];
        $rows = DB::table('holidays')->where('tenant_id', $tenantId)->where('status', 1)->where('start_date', '<=', $to)
            ->whereRaw('COALESCE(NULLIF(end_date, \'\'), start_date) >= ?', [$from])->get(['start_date', 'end_date']);
        foreach ($rows as $h) {
            $end = Carbon::parse($h->end_date ?: $h->start_date);
            for ($d = Carbon::parse($h->start_date); $d->lte($end); $d->addDay()) {
                $dates[$d->toDateString()] = true;
            }
        }

        return $dates;
    }

    /**
     * A user's floating week-off days between $from and $to — always paid under the sandwich rule.
     *
     * @return string[]
     */
    public static function floatingDates(int $tenantId, int $userId, string $from, string $to): array
    {
        if ($tenantId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('floating_weekoff_days')) {
            return [];
        }

        return DB::table('floating_weekoff_days')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'approved')
            ->whereBetween('date', [$from, $to])->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    /** Approved days the employee planned ahead in [$from, $to]. @return string[] */
    public function plannedDates(int $tenantId, int $userId, string $from, string $to): array
    {
        return DB::table('floating_weekoff_days')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('source', 'planned')->where('status', 'approved')->whereBetween('date', [$from, $to])
            ->orderBy('date')->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    /**
     * A planned day the employee came in and worked after all is not a day off:
     * give its slot back (status cancelled, week-off row removed).
     *
     * @return string[] dates released
     */
    private function releaseWorkedPlannedDays(int $tenantId, int $userId, array $monthRange): array
    {
        $rows = DB::table('floating_weekoff_days as f')
            ->join('attendances as a', fn ($j) => $j->on('a.user_id', '=', 'f.user_id')->on('a.date', '=', 'f.date'))
            ->where('f.tenant_id', $tenantId)->where('f.user_id', $userId)->where('f.source', 'planned')->where('f.status', 'approved')
            ->whereBetween('f.date', $monthRange)->where('f.date', '<', now()->toDateString())->whereNotNull('a.clock_in')
            ->get(['f.id', 'f.date', 'f.user_weekoff_id']);
        foreach ($rows as $row) {
            UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($row->user_weekoff_id)->delete();
            DB::table('floating_weekoff_days')->where('id', $row->id)
                ->update(['status' => 'cancelled', 'user_weekoff_id' => null, 'decision_remarks' => 'Worked that day — slot given back', 'updated_at' => now()]);
        }

        return $rows->map(fn ($r) => substr((string) $r->date, 0, 10))->all();
    }

    /** Users of a tenant the nightly run must look at: floating on (company or override) or holding floating days. */
    public function usersToAllocate(int $tenantId, string $yearMonth): Collection
    {
        $active = DB::table('users')->where('tenant_id', $tenantId)->where('status', 1)->where('role', '!=', 'admin')->pluck('id');
        $holding = DB::table('floating_weekoff_days')->where('tenant_id', $tenantId)
            ->whereBetween('date', [$yearMonth . '-01', Carbon::parse($yearMonth . '-01')->endOfMonth()->toDateString()])->distinct()->pluck('user_id');

        return $active->merge($holding)->unique()->values()
            ->filter(fn ($id) => $holding->contains($id) || $this->policies->forUserMonth($tenantId, (int) $id, $yearMonth)->floatingWeekoffsEnabled);
    }
}
