<?php

namespace App\Services\Shift;

use App\Models\User;
use App\Models\UserWeekoffs;
use App\Models\WeekoffSwap;
use App\Models\WeekoffSwapEmployee;
use App\Services\Attendance\PeriodLockService;
use App\Services\AttendanceSummaryService;
use App\Services\AuditLogger;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Week-off swap (plan A1, 2026-10-17). Employees work one of their week-offs
 * (the worked day, e.g. Sat 17 Oct) and get another day off instead (the
 * replacement day, e.g. Mon 19 Oct) — chosen once for everybody, editable per
 * employee.
 *
 * The replacement day is written as an ordinary one-day date_based
 * `user_weekoffs` row, so every screen, report, API and payroll engine already
 * reads it as a week-off; the day's shift is dropped through
 * ShiftMaterializer::rebuildDate() (a day off has no shift) inside
 * ShiftChangeRecorder::track(), so it is logged and a past day is re-graded.
 * The worked day is left as it is — it is paid once, as it always was.
 * Cancelling deletes that row and rebuilds the day's shift again.
 *
 * A replacement day is always paid under the sandwich rule
 * (SandwichRule::offDayPay(..., alwaysPaid: replacementDates())).
 */
class WeekOffSwapService
{
    public function __construct(
        private ShiftMaterializer $materializer,
        private ShiftChangeRecorder $recorder,
    ) {
    }

    /**
     * Employees whose week-off falls on $workedDate, each with whether they
     * clocked in that day and whether $replacementDate can be their day off.
     *
     * @return array<int, array{user_id:int, name:string, employee_id:?string, worked:bool, eligible:bool, reason:?string}>
     */
    public function candidates(int $tenantId, string $workedDate, ?string $replacementDate = null): array
    {
        $users = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 1)->where('role', '!=', 'admin')
            ->orderBy('name')->get(['id', 'name', 'employee_id']);
        $weekoffs = $this->weekoffsByUser($tenantId, $users->pluck('id')->all());
        $worked = DB::table('attendances')->where('tenant_id', $tenantId)->where('date', $workedDate)->whereNotNull('clock_in')
            ->pluck('user_id')->flip();
        $day = Carbon::parse($workedDate);

        $out = [];
        foreach ($users as $u) {
            if (! WeekOffPredicate::isWeekOff($weekoffs->get($u->id, collect()), $day)) {
                continue;
            }
            $reason = $replacementDate ? $this->ineligibleReason($tenantId, (int) $u->id, $workedDate, $replacementDate, $weekoffs->get($u->id, collect())) : null;
            $out[] = ['user_id' => (int) $u->id, 'name' => $u->name, 'employee_id' => $u->employee_id,
                'worked' => isset($worked[$u->id]), 'eligible' => $reason === null, 'reason' => $reason];
        }

        return $out;
    }

    /**
     * Save a swap. $items = [['user_id' => .., 'replacement_date' => ?Y-m-d], …]
     * (no date = the swap's own replacement day). Employees that cannot take it
     * are skipped with a reason; the rest get their day off.
     *
     * @return array{swap: ?WeekoffSwap, applied: int, skipped: array<int, array{user_id:int, name:string, reason:string}>}
     */
    public function create(int $tenantId, int $actorId, string $workedDate, string $replacementDate, ?string $reason, array $items): array
    {
        $userIds = array_values(array_unique(array_map(fn ($i) => (int) $i['user_id'], $items)));
        $names = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $userIds)->where('status', 1)->pluck('name', 'id');
        $weekoffs = $this->weekoffsByUser($tenantId, $userIds);

        $apply = [];
        $skipped = [];
        foreach ($items as $item) {
            $userId = (int) $item['user_id'];
            $day = ($item['replacement_date'] ?? null) ?: $replacementDate;
            $why = ! isset($names[$userId]) ? 'Not an active employee of this company'
                : (! WeekOffPredicate::isWeekOff($weekoffs->get($userId, collect()), Carbon::parse($workedDate))
                    ? 'The worked day is not their week-off'
                    : $this->ineligibleReason($tenantId, $userId, $workedDate, $day, $weekoffs->get($userId, collect())));
            if ($why !== null) {
                $skipped[] = ['user_id' => $userId, 'name' => $names[$userId] ?? "#{$userId}", 'reason' => $why];
            } else {
                $apply[$userId] = $day;
            }
        }

        if ($apply === []) {
            return ['swap' => null, 'applied' => 0, 'skipped' => $skipped];
        }

        $swap = DB::transaction(function () use ($tenantId, $actorId, $workedDate, $replacementDate, $reason, $apply) {
            $swap = WeekoffSwap::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'worked_date' => $workedDate, 'replacement_date' => $replacementDate,
                'reason' => $reason, 'status' => 'active', 'created_by' => $actorId,
            ]);

            $this->recorder->track($tenantId, array_keys($apply), min($apply), max($apply),
                ['source' => 'weekoff_swap', 'reason' => $reason ?: "Week-off swap: worked {$workedDate}"],
                function () use ($swap, $tenantId, $actorId, $workedDate, $apply) {
                    foreach ($apply as $userId => $day) {
                        $off = UserWeekoffs::withoutGlobalScopes()->create([
                            'tenant_id' => $tenantId, 'user_id' => $userId, 'off_type' => 'date_based',
                            'start_date' => $day, 'end_date' => $day, 'status' => 1, 'created_by' => $actorId,
                            'description' => 'Week-off swap: worked ' . Carbon::parse($workedDate)->format('d M Y'),
                        ]);
                        WeekoffSwapEmployee::withoutGlobalScopes()->create([
                            'tenant_id' => $tenantId, 'weekoff_swap_id' => $swap->id, 'user_id' => $userId,
                            'worked_date' => $workedDate, 'replacement_date' => $day, 'user_weekoff_id' => $off->id, 'status' => 'active',
                        ]);
                        $this->materializer->rebuildDate($tenantId, $userId, $day);   // a day off has no shift
                    }
                });

            return $swap;
        });

        $this->refreshSummaries($tenantId, $apply);
        app(AuditLogger::class)->record('tenant_user', $actorId, $tenantId, 'weekoff_swap.created', 'weekoff_swaps', $swap->id, [],
            ['worked_date' => $workedDate, 'replacement_date' => $replacementDate, 'employees' => $apply, 'skipped' => $skipped]);

        return ['swap' => $swap, 'applied' => count($apply), 'skipped' => $skipped];
    }

    /** Cancel a whole swap, or only $userId's part of it. Returns how many employees got their shift back. */
    public function cancel(WeekoffSwap $swap, int $actorId, ?int $userId = null): int
    {
        $rows = WeekoffSwapEmployee::withoutGlobalScopes()->where('tenant_id', $swap->tenant_id)->where('weekoff_swap_id', $swap->id)
            ->where('status', 'active')->when($userId, fn ($q) => $q->where('user_id', $userId))->get();
        if ($rows->isEmpty()) {
            return 0;
        }
        $tenantId = (int) $swap->tenant_id;
        $days = $rows->mapWithKeys(fn ($r) => [(int) $r->user_id => $r->replacement_date->toDateString()])->all();

        DB::transaction(function () use ($swap, $rows, $tenantId, $actorId, $days) {
            $this->recorder->track($tenantId, array_keys($days), min($days), max($days),
                ['source' => 'weekoff_swap_cancel', 'reason' => 'Week-off swap cancelled'],
                function () use ($rows, $tenantId, $actorId) {
                    foreach ($rows as $row) {
                        UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($row->user_weekoff_id)->delete();
                        $row->update(['status' => 'cancelled', 'cancelled_by' => $actorId, 'cancelled_at' => now()]);
                        $this->materializer->rebuildDate($tenantId, (int) $row->user_id, $row->replacement_date->toDateString());
                    }
                });

            $stillActive = WeekoffSwapEmployee::withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->where('weekoff_swap_id', $swap->id)->where('status', 'active')->exists();
            if (! $stillActive) {
                $swap->update(['status' => 'cancelled', 'cancelled_by' => $actorId, 'cancelled_at' => now()]);
            }
        });

        $this->refreshSummaries($tenantId, $days);
        app(AuditLogger::class)->record('tenant_user', $actorId, $tenantId, 'weekoff_swap.cancelled', 'weekoff_swaps', $swap->id, ['employees' => $days], []);

        return count($days);
    }

    /**
     * An employee's swap replacement days between $from and $to (Y-m-d) —
     * always-paid days for the sandwich rule.
     *
     * @return string[]
     */
    public static function replacementDates(int $tenantId, int $userId, string $from, string $to): array
    {
        if ($tenantId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('weekoff_swap_employees')) {
            return [];
        }

        return DB::table('weekoff_swap_employees')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'active')
            ->whereBetween('replacement_date', [$from, $to])->pluck('replacement_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
    }

    /** Why $replacementDate cannot be $userId's day off, or null when it can. */
    private function ineligibleReason(int $tenantId, int $userId, string $workedDate, string $replacementDate, Collection $weekoffs): ?string
    {
        if ($replacementDate === $workedDate) {
            return 'The day off must differ from the worked day';
        }
        if (WeekOffPredicate::isWeekOff($weekoffs, Carbon::parse($replacementDate))) {
            return 'Already a week-off on ' . Carbon::parse($replacementDate)->format('d M');
        }
        $holiday = DB::table('holidays')->where('tenant_id', $tenantId)->where('status', 1)
            ->where('start_date', '<=', $replacementDate)->whereRaw('COALESCE(NULLIF(end_date, \'\'), start_date) >= ?', [$replacementDate])->exists();
        if ($holiday) {
            return 'A holiday on ' . Carbon::parse($replacementDate)->format('d M');
        }
        $onLeave = DB::table('leaves')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'approved')
            ->where('start_date', '<=', $replacementDate)->whereRaw('COALESCE(end_date, start_date) >= ?', [$replacementDate])->exists();
        if ($onLeave) {
            return 'On approved leave on ' . Carbon::parse($replacementDate)->format('d M');
        }
        $already = DB::table('weekoff_swap_employees')->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('worked_date', $workedDate)->where('status', 'active')->exists();
        if ($already) {
            return 'Already swapped for this worked day';
        }
        if (app(PeriodLockService::class)->isLocked($tenantId, Carbon::parse($replacementDate)->format('Y-m'))) {
            return 'Payroll for ' . Carbon::parse($replacementDate)->format('M Y') . ' is locked';
        }

        return null;
    }

    /** @return Collection<int|string, Collection<int, UserWeekoffs>> user_id => their active week-off rows */
    private function weekoffsByUser(int $tenantId, array $userIds): Collection
    {
        return UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('user_id', $userIds)->where('status', 1)
            ->get()->toBase()->groupBy('user_id');
    }

    /** The monthly attendance summary (payroll reads its day breakdown) of each replacement day's month. */
    private function refreshSummaries(int $tenantId, array $days): void
    {
        foreach ($days as $userId => $day) {
            try {
                app(AttendanceSummaryService::class)->updateMonthlySummary((int) $userId, substr($day, 0, 7), $tenantId);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
