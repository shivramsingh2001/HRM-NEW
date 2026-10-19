<?php

namespace App\Services\Attendance;

use App\Models\FloatingWeekoffDay;
use App\Models\User;
use App\Models\UserWeekoffs;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditLogger;
use App\Services\Shift\ShiftChangeRecorder;
use App\Services\Shift\ShiftMaterializer;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Floating week-offs planned ahead (plan C2, 2026-10-19). An employee on
 * floating week-offs (FloatingWeekOffAllocator) asks for a particular day as one
 * of the month's days off; a manager / HR / admin approves — through the
 * "floating_weekoff" approval workflow when one is configured
 * (FloatingWeekOffApprovalHandler), else the single approver; never your own.
 *
 * An approved day is a one-day date_based `user_weekoffs` row ("Floating
 * week-off (planned)") and the day's shift is dropped (ShiftMaterializer::
 * rebuildDate() inside ShiftChangeRecorder::track()). It uses the month's count
 * (and its week's, when capped) before the nightly fill of missed days. Worked
 * after all → the nightly run gives the slot back. Cancelling an approved
 * future day removes it and brings the shift back.
 */
class FloatingWeekOffRequestService
{
    public function __construct(
        private PolicyResolver $policies,
        private FloatingWeekOffAllocator $allocator,
    ) {}

    /**
     * The month's count and how much of it is used.
     *
     * @return array{enabled:bool, quota:int, used:int, pending:int, per_week:int}
     */
    public function monthStatus(int $tenantId, int $userId, string $yearMonth): array
    {
        $policy = $this->policies->forUserMonth($tenantId, $userId, $yearMonth);
        $rows = $this->monthRows($tenantId, $userId, $yearMonth);

        return [
            'enabled' => $policy->floatingWeekoffsEnabled,
            'quota' => $policy->floatingWeekoffsEnabled ? $this->allocator->quota($policy->floatingWeekoffsPerMonth, $yearMonth, $tenantId, $userId) : 0,
            'used' => $rows->where('status', 'approved')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
            'per_week' => $policy->floatingWeekoffsMaxPerWeek,
        ];
    }

    /** Why $date cannot be planned as a floating week-off, or null when it can. */
    public function reason(int $tenantId, int $userId, string $date, ?int $ignoreId = null): ?string
    {
        $ym = substr($date, 0, 7);
        $status = $this->monthStatus($tenantId, $userId, $ym);
        $day = Carbon::parse($date);
        $taken = $this->monthRows($tenantId, $userId, $ym)->whereIn('status', ['approved', 'pending'])->where('id', '!=', $ignoreId);
        $weekoffs = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)->get();

        return match (true) {
            ! $status['enabled'] => 'You are not on floating week-offs',
            $date < now()->toDateString() => 'Pick today or a later day',
            $taken->contains(fn ($r) => $r->date->toDateString() === $date) => 'Already a floating week-off (or asked for)',
            WeekOffPredicate::isWeekOff($weekoffs, $day) => 'Already a week-off',
            $this->isHoliday($tenantId, $date) => 'A holiday',
            $this->onLeave($tenantId, $userId, $date) => 'On approved leave',
            $this->clockedIn($tenantId, $userId, $date) => 'Already clocked in that day',
            $taken->count() >= $status['quota'] => "All {$status['quota']} floating week-off(s) of ".$day->format('M Y').' are used or asked for',
            $status['per_week'] > 0 && $taken->filter(fn ($r) => $r->date->format('o-W') === $day->format('o-W'))->count() >= $status['per_week'] => "At most {$status['per_week']} floating week-off(s) in one week",
            app(PeriodLockService::class)->isLocked($tenantId, $ym) => 'Payroll for '.$day->format('M Y').' is locked',
            default => null,
        };
    }

    public function request(User $user, string $date, ?string $reason): FloatingWeekoffDay
    {
        $tenantId = (int) $user->tenant_id;
        $why = $this->reason($tenantId, (int) $user->id, $date);
        if ($why !== null) {
            throw new RuntimeException($why);
        }
        // A rejected / cancelled ask for the same day makes way for the new one (one row per day).
        FloatingWeekoffDay::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('date', $date)
            ->whereIn('status', ['rejected', 'cancelled'])->delete();
        $row = FloatingWeekoffDay::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'user_id' => $user->id, 'date' => $date, 'source' => 'planned', 'status' => 'pending', 'reason' => $reason,
        ]);
        app(AuditLogger::class)->record('tenant_user', $user->id, $tenantId, 'floating_weekoff.requested', 'floating_weekoff_days', $row->id, [], ['date' => $date]);

        return $row;
    }

    /** Approve / reject (workflow when configured). The caller has checked the actor's scope. */
    public function decide(FloatingWeekoffDay $row, User $actor, string $action, ?string $remarks = null): FloatingWeekoffDay
    {
        if ($row->status !== 'pending') {
            throw new RuntimeException("This request is already {$row->status}.");
        }
        if (\App\Support\SelfApproval::blocks($actor, (int) $row->user_id)) {
            throw new RuntimeException(\App\Support\SelfApproval::MESSAGE);
        }
        if ($action === 'approved' && ($why = $this->reason((int) $row->tenant_id, (int) $row->user_id, $row->date->toDateString(), (int) $row->id)) !== null) {
            throw new RuntimeException($why);
        }

        $flow = app(ApprovalService::class)->decide('floating_weekoff', $row, $actor, $action, $remarks);
        if ($flow === null) {
            $action === 'approved' ? $this->applyApproved($row, (int) $actor->id, $remarks) : $this->applyRejected($row, (int) $actor->id, $remarks);
        }

        return $row->fresh();
    }

    /** Final approval: the day becomes a week-off and loses its shift. */
    public function applyApproved(FloatingWeekoffDay $row, ?int $actorId, ?string $remarks = null): void
    {
        $tenantId = (int) $row->tenant_id;
        $userId = (int) $row->user_id;
        $date = $row->date->toDateString();

        DB::transaction(fn () => app(ShiftChangeRecorder::class)->track($tenantId, [$userId], $date, $date,
            ['source' => 'floating_weekoff', 'reason' => 'Floating week-off planned'.($row->reason ? ": {$row->reason}" : '')],
            function () use ($row, $tenantId, $userId, $date, $actorId, $remarks) {
                $off = UserWeekoffs::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId, 'user_id' => $userId, 'off_type' => 'date_based', 'start_date' => $date, 'end_date' => $date,
                    'status' => 1, 'description' => 'Floating week-off (planned)', 'created_by' => $actorId ?? $userId,
                ]);
                $row->update(['status' => 'approved', 'user_weekoff_id' => $off->id, 'decided_by' => $actorId, 'decided_at' => now(), 'decision_remarks' => $remarks]);
                app(ShiftMaterializer::class)->rebuildDate($tenantId, $userId, $date);
            }));
        $this->afterChange($tenantId, $userId, $date);
        app(AuditLogger::class)->record('tenant_user', $actorId, $tenantId, 'floating_weekoff.approved', 'floating_weekoff_days', $row->id, [], ['date' => $date]);
    }

    public function applyRejected(FloatingWeekoffDay $row, ?int $actorId, ?string $remarks = null): void
    {
        $row->update(['status' => 'rejected', 'decided_by' => $actorId, 'decided_at' => now(), 'decision_remarks' => $remarks]);
        app(AuditLogger::class)->record('tenant_user', $actorId, (int) $row->tenant_id, 'floating_weekoff.rejected', 'floating_weekoff_days', $row->id, [], ['remarks' => $remarks]);
    }

    /**
     * Withdraw: the employee's own pending ask, or an approved day from today on
     * (by the employee or an approver who may decide for them — checked by the
     * caller). An approved day gets its shift back.
     */
    public function cancel(FloatingWeekoffDay $row, User $actor): void
    {
        $date = $row->date->toDateString();
        if ($row->source !== 'planned' || ! in_array($row->status, ['pending', 'approved'], true)) {
            throw new RuntimeException('Only a planned floating week-off that is pending or approved can be cancelled.');
        }
        if ($row->status === 'approved' && $date < now()->toDateString()) {
            throw new RuntimeException('A day that has passed cannot be cancelled.');
        }
        $tenantId = (int) $row->tenant_id;
        $userId = (int) $row->user_id;

        if ($row->status === 'pending') {
            $row->update(['status' => 'cancelled', 'decided_by' => $actor->id, 'decided_at' => now()]);

            return;
        }
        DB::transaction(fn () => app(ShiftChangeRecorder::class)->track($tenantId, [$userId], $date, $date,
            ['source' => 'floating_weekoff_cancel', 'reason' => 'Floating week-off cancelled'],
            function () use ($row, $tenantId, $userId, $date, $actor) {
                UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($row->user_weekoff_id)->delete();
                $row->update(['status' => 'cancelled', 'user_weekoff_id' => null, 'decided_by' => $actor->id, 'decided_at' => now()]);
                app(ShiftMaterializer::class)->rebuildDate($tenantId, $userId, $date);
            }));
        $this->afterChange($tenantId, $userId, $date);
    }

    private function monthRows(int $tenantId, int $userId, string $yearMonth)
    {
        return FloatingWeekoffDay::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereBetween('date', [$yearMonth.'-01', Carbon::parse($yearMonth.'-01')->endOfMonth()->toDateString()])->get();
    }

    /** A past / today's day changed: let the month's automatic days re-balance and refresh the summary. */
    private function afterChange(int $tenantId, int $userId, string $date): void
    {
        try {
            $this->allocator->allocateMonth($tenantId, $userId, substr($date, 0, 7));
            app(\App\Services\AttendanceSummaryService::class)->updateMonthlySummary($userId, substr($date, 0, 7), $tenantId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function isHoliday(int $tenantId, string $date): bool
    {
        return DB::table('holidays')->where('tenant_id', $tenantId)->where('status', 1)->where('start_date', '<=', $date)
            ->whereRaw('COALESCE(NULLIF(end_date, \'\'), start_date) >= ?', [$date])->exists();
    }

    private function onLeave(int $tenantId, int $userId, string $date): bool
    {
        return DB::table('leaves')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'approved')
            ->where('start_date', '<=', $date)->whereRaw('COALESCE(end_date, start_date) >= ?', [$date])->exists();
    }

    private function clockedIn(int $tenantId, int $userId, string $date): bool
    {
        return DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)->whereNotNull('clock_in')->exists();
    }
}
