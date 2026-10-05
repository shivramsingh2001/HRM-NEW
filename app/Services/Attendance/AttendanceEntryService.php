<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendancePunch;
use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\AttendanceSummaryService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single funnel for programmatic writes to `attendances`.
 *
 * Every path (manual mark, regularization approval, and — via logExternalWrite() —
 * clock-in/out, fingerprint, auto clock-out) records through here so that each
 * change is:
 *   - atomic (one transaction),
 *   - complete (tenant_id, shift snapshot, derived status filled in),
 *   - audited (one attendance_logs row with actor / source / before / after),
 *   - reflected downstream (LatePolicyService + AttendanceSummaryService refresh).
 */
class AttendanceEntryService
{
    /** Columns compared for the before/after audit diff. */
    private const AUDITED = [
        'clock_in', 'clock_out', 'total_hours', 'worked_hours',
        'late_minutes', 'early_departure_minutes', 'overtime_minutes',
        'attendance_status', 'effective_status', 'day_fraction',
        'scheduled_shift_start', 'scheduled_shift_end', 'shift_id', 'branch_id',
        'is_regularized', 'regularized_by', 'regularization_id', 'marked_by',
        'attendance_type', 'status', 'remarks',
    ];

    public function __construct(
        private AttendanceCalculator $calc,
        private LatePolicyService $latePolicy,
        private AttendanceSummaryService $summary,
        private LeaveService $leaveService,
        private AttendanceDayResolver $resolver,
    ) {
    }

    /**
     * Atomic upsert of one (tenant,user,date) attendance row + one audit-log row
     * + downstream recompute. Returns the fresh row.
     *
     * @param array<string,mixed> $columns  attendance columns to set
     */
    public function record(int $userId, int $tenantId, string $date, array $columns, AuditContext $ctx): Attendance
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        $tenantId = $tenantId ?: (int) (User::withoutGlobalScopes()->whereKey($userId)->value('tenant_id'));

        // Tier 2 / T2-E — refuse writes into a payroll-locked month unless the
        // caller explicitly overrides (admin path, with a reason on the ctx).
        $ym = Carbon::parse($date)->format('Y-m');
        if (! $ctx->override && app(PeriodLockService::class)->isLocked($tenantId, $ym)) {
            throw new \App\Exceptions\PeriodLockedException($ym);
        }

        return DB::transaction(function () use ($userId, $tenantId, $date, $columns, $ctx) {
            $existing = Attendance::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
                ->first();
            $before = $existing ? $existing->only(self::AUDITED) : [];

            // Stamp the shift snapshot when clock times are present and it's missing.
            if ((array_key_exists('clock_in', $columns) || array_key_exists('clock_out', $columns))
                && empty($columns['scheduled_shift_start'])
                && empty($existing?->scheduled_shift_start)) {
                $shift = app(TenantShiftResolver::class)->forUserDate($userId, $tenantId, $date);
                if ($shift) {
                    $columns += [
                        'shift_id' => $shift->id,
                        'scheduled_shift_start' => $shift->start_time,
                        'scheduled_shift_end' => $shift->end_time,
                    ];
                }
                if (empty($columns['branch_id']) && empty($existing?->branch_id)) {
                    $branch = UserJobDetail::where('user_id', $userId)->where('tenant_id', $tenantId)->value('office_branch');
                    if ($branch) {
                        $columns['branch_id'] = $branch;
                    }
                }
            }

            // Tier 1 / W4 — clock_in_utc / clock_out_utc / tz are stamped by the
            // Attendance model's saving hook, covering every writer uniformly.

            try {
                $row = Attendance::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $date],
                    $columns
                );
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) != 1062) {
                    throw $e;
                }
                // Lost a race for the unique key — the row now exists; update it.
                $row = Attendance::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
                    ->firstOrFail();
                $row->forceFill($columns)->save();
            }

            $this->writeAuditLog($row, $before, $ctx);

            // Months this write can affect: the row's own month, plus the
            // clock-out month for a shift that ran past midnight.
            $months = [Carbon::parse($date)->format('Y-m')];
            if (! empty($columns['clock_out'])) {
                try {
                    $months[] = Carbon::parse($columns['clock_out'])->format('Y-m');
                } catch (\Throwable $e) {
                    // non-parseable clock_out — ignore, the row month still covers it
                }
            }
            foreach (array_unique($months) as $ym) {
                $this->refreshMonth($userId, $tenantId, $ym);
            }

            // Company Policies → Overtime → automatic mode: (re)calculate this day's overtime.
            // Never blocks the attendance write; the nightly overtime:auto-calculate catches up.
            try {
                app(AutoOvertimeService::class)->syncDay($userId, $tenantId, $date);
            } catch (\Throwable $e) {
                report($e);
            }

            $fresh = $row->fresh();
            $this->emitDomainEvent($fresh, $ctx);

            return $fresh;
        });
    }

    /**
     * Hand-set an employee's day for one date, or a whole date range.
     * Preserves the response shape TeamController::markAttendance relies on.
     *
     * @param array{
     *   user_id:int, tenant_id:int, date:string, end_date?:?string,
     *   status:AttendanceStatus, clock_in?:?string, clock_out?:?string,
     *   remarks?:?string, leave_type_id?:?int
     * } $input
     * @return array{attendance:Attendance, leave:?\App\Models\Leave, is_update:bool, shift:?object, marked:int}
     */
    public function markStatus(array $input, User $actor): array
    {
        /** @var AttendanceStatus $status */
        $status = $input['status'];
        $userId = (int) $input['user_id'];
        $tenantId = (int) $input['tenant_id'];
        $start = Carbon::parse($input['date'])->startOfDay();
        $end = !empty($input['end_date']) ? Carbon::parse($input['end_date'])->startOfDay() : $start->copy();

        $ctx = new AuditContext(
            actorId: $actor->id,
            actorRole: $actor->role,
            source: 'manual',
            reason: $input['remarks'] ?? ('Marked by ' . $actor->name . ' (' . $status->value . ')'),
        );

        $lastAttendance = null;
        $lastLeave = null;
        $lastShift = null;
        $isUpdate = false;
        $marked = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->format('Y-m-d');
            $shift = app(TenantShiftResolver::class)->forUserDate($userId, $tenantId, $date);
            $jobDetail = UserJobDetail::where('user_id', $userId)->where('tenant_id', $tenantId)->first();

            $attrs = [
                'attendance_status' => $status->value,
                'attendance_type' => 'manual',
                'marked_by' => $actor->id,
                'is_regularized' => 0,
                'status' => 1,
                'day_fraction' => $status->dayFraction(),
                'effective_status' => null,
                'policy_note' => null,
                'shift_id' => $shift->id ?? null,
                'scheduled_shift_start' => $shift->start_time ?? null,
                'scheduled_shift_end' => $shift->end_time ?? null,
                'branch_id' => $jobDetail->office_branch ?? null,
                'clock_in' => null,
                'clock_out' => null,
                'clock_in_address' => 'Marked by ' . $actor->name,
                'clock_out_address' => null,
                'worked_hours' => 0,
                'total_hours' => null,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'overtime_minutes' => 0,
                // Multi-shift numbers come from punches, which a hand-set day replaces.
                'shift_count' => null,
                'extra_shift_minutes' => 0,
                'remarks' => $ctx->reason,
            ];

            if ($status->requiresClockTimes() && !empty($input['clock_in']) && !empty($input['clock_out'])) {
                $clockIn = Carbon::parse($date . ' ' . $input['clock_in']);
                $clockOut = $this->calc->resolveClockOut($clockIn, Carbon::parse($date . ' ' . $input['clock_out']), $shift);
                $seconds = $this->calc->workedSeconds($clockIn, $clockOut);
                $attrs['clock_in'] = $clockIn->format('Y-m-d H:i:s');
                $attrs['clock_out'] = $clockOut->format('Y-m-d H:i:s');
                $attrs['clock_out_address'] = 'Marked by ' . $actor->name;
                $attrs['worked_hours'] = $this->calc->decimalHours($seconds);
                $attrs['total_hours'] = $this->calc->formatDuration($seconds);
                if ($shift) {
                    $attrs['late_minutes'] = $this->calc->lateMinutes(
                        $shift, Carbon::parse($date . ' ' . $shift->start_time), $clockIn
                    );
                }
            }

            $existingBefore = Attendance::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)->exists();

            $attendance = $this->record($userId, $tenantId, $date, $attrs, $ctx);

            // The admin's decision is authoritative for the whole day: void the
            // day's punches (soft — kept for audit) so a later punch's rollup
            // can no longer overwrite it, and drop the per-shift breakdown.
            // After the write, so a period-lock refusal leaves punches alone.
            AttendancePunch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->where('date', $date)->where('status', 'active')
                ->update([
                    'status' => 'void',
                    'voided_by' => $actor->id,
                    'voided_at' => now(),
                    'void_reason' => 'Superseded by manual attendance marking',
                ]);
            \App\Models\AttendanceShiftSegment::withoutGlobalScopes()->where('attendance_id', $attendance->id)->delete();

            // Every shift that day (primary and additional) is settled.
            DB::table('user_shifts')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
                ->update(['status' => 'complete', 'updated_at' => now()]);

            $leave = null;
            if ($status->isLeaveKind() && !empty($input['leave_type_id'])) {
                $leave = $this->leaveService->createApproved([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'leave_type_id' => (int) $input['leave_type_id'],
                    'start_date' => $date,
                    'end_date' => $date,
                    'start_session' => $status->leaveSession(),
                    'end_session' => $status->leaveSession(),
                    'leave_count' => $status === AttendanceStatus::OnLeave ? 1.0 : 0.5,
                    'reason' => $input['remarks'] ?? 'Marked from attendance',
                    'applied_by' => $actor->id,
                    'deduct_balance' => true,
                ]);
            }

            $lastAttendance = $attendance;
            $lastLeave = $leave ?: $lastLeave;
            $lastShift = $shift;
            $isUpdate = $isUpdate || $existingBefore;
            $marked++;
        }

        return [
            'attendance' => $lastAttendance,
            'leave' => $lastLeave,
            'is_update' => $isUpdate,
            'shift' => $lastShift,
            'marked' => $marked,
        ];
    }

    /**
     * Materialise an approved regularization.
     *
     * Times are written as regularized PUNCHES (the punches they replace are
     * voided, never deleted) and the day is re-derived through
     * AttendanceRollupService — so the attendance row, its sessions and the
     * punch log agree, and the next punch/rollup can no longer silently undo
     * the correction (the old direct row write was overwritten that way).
     *
     * Multi-shift: only the punches of the shift the request names
     * (user_shift_id, default the primary shift) are replaced; other shifts
     * that day are kept. Overnight: an out time on/before the in time (or, for
     * a night shift, an early-morning time) lands on the next day.
     *
     * Requests without any time (full_day / wfh_not_marked / technical_issue
     * on a day with no punches) keep the flags-only row write.
     */
    public function applyRegularization(AttendanceRegularization $reg, User $approver): Attendance
    {
        $userId = (int) $reg->user_id;
        $tenantId = (int) ($reg->tenant_id ?: User::withoutGlobalScopes()->whereKey($userId)->value('tenant_id'));
        $date = Carbon::parse($reg->date)->format('Y-m-d');

        if (Carbon::parse($date)->gt(Carbon::today())) {
            throw new \RuntimeException('Cannot regularize a future date.');
        }

        $resolver = app(TenantShiftResolver::class);
        $instances = $resolver->instancesForUserDate($userId, $tenantId, $date);
        $target = $reg->user_shift_id
            ? $instances->first(fn ($i) => (int) $i['user_shift_id'] === (int) $reg->user_shift_id)
            : null;
        $target ??= $instances->first();
        $shift = $target['shift'] ?? $resolver->forUserDate($userId, $tenantId, $date);
        $jobDetail = UserJobDetail::where('user_id', $userId)->where('tenant_id', $tenantId)->first();

        $ctx = new AuditContext(
            actorId: $approver->id,
            actorRole: $approver->role,
            source: 'regularization',
            reason: 'Regularization #' . $reg->id . ' approved',
        );

        $flags = [
            'regularization_id' => $reg->id,
            'is_regularized' => 1,
            'regularized_by' => $approver->id,
            'regularized_at' => now(),
            'marked_by' => $approver->id,
            'status' => 1,
            'effective_status' => null,
            'policy_note' => null,
        ];

        $clockIn = filled($reg->in_time) ? $this->regularizedTime($date, $reg->in_time, $shift, 'in', null) : null;
        $clockOut = filled($reg->out_time) ? $this->regularizedTime($date, $reg->out_time, $shift, 'out', $clockIn) : null;

        return DB::transaction(function () use ($reg, $approver, $userId, $tenantId, $date, $instances, $target, $shift, $jobDetail, $ctx, $flags, $clockIn, $clockOut) {
            $punches = $this->punchesOfShift($userId, $tenantId, $date, $instances, $target);
            $existing = Attendance::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)->first();

            // Keep whichever side the request does not change.
            $firstIn = $punches->where('direction', 'in')->sortBy('punched_at')->first();
            $lastOut = $punches->where('direction', 'out')->sortByDesc('punched_at')->first();
            $onlyShift = $instances->count() <= 1;
            $clockIn ??= $firstIn ? Carbon::parse($firstIn->punched_at)
                : (($onlyShift && !empty($existing?->clock_in)) ? Carbon::parse($existing->clock_in) : null);
            $clockOut ??= $lastOut ? Carbon::parse($lastOut->punched_at)
                : (($onlyShift && !empty($existing?->clock_out)) ? Carbon::parse($existing->clock_out) : null);

            if (!$clockIn && !$clockOut) {
                // Nothing to time — flags only, as before.
                return $this->record($userId, $tenantId, $date, $flags + [
                    'shift_id' => $shift->id ?? null,
                    'scheduled_shift_start' => $shift->start_time ?? null,
                    'scheduled_shift_end' => $shift->end_time ?? null,
                    'branch_id' => $jobDetail->office_branch ?? null,
                ], $ctx);
            }

            $voidReason = 'Replaced by regularization #' . $reg->id;
            foreach ($punches as $p) {
                $p->forceFill([
                    'status' => 'void',
                    'voided_by' => $approver->id,
                    'voided_at' => now(),
                    'void_reason' => $voidReason,
                ])->save();
            }

            $tz = app(TimezoneResolver::class)->forUser($userId, $tenantId);
            $base = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'date' => $date,
                'timezone' => $tz,
                'source' => 'manual',
                'method' => 'regularization',
                'actor_id' => $approver->id,
                'actor_role' => $approver->role,
                'reason' => $voidReason,
                'status' => 'active',
                'session_seq' => 1,
                'regularization_id' => $reg->id,
                'is_regularized' => 1,
                'user_shift_id' => $target['user_shift_id'] ?? null,
                'attendance_location_id' => $jobDetail->office_branch ?? null,
            ];

            $in = $clockIn ? AttendancePunch::create($base + [
                'direction' => 'in',
                'punched_at' => $clockIn->format('Y-m-d H:i:s'),
                'punched_at_utc' => $this->calc->toUtc($clockIn->format('Y-m-d H:i:s'), $tz),
                'address' => 'Regularized by ' . $approver->name,
                'original_punch_id' => $firstIn->id ?? null,
            ]) : null;
            $out = $clockOut ? AttendancePunch::create($base + [
                'direction' => 'out',
                'punched_at' => $clockOut->format('Y-m-d H:i:s'),
                'punched_at_utc' => $this->calc->toUtc($clockOut->format('Y-m-d H:i:s'), $tz),
                'address' => 'Regularized by ' . $approver->name,
                'original_punch_id' => $lastOut->id ?? null,
                'paired_punch_id' => $in->id ?? null,
            ]) : null;
            if ($in && $out) {
                $in->forceFill(['paired_punch_id' => $out->id])->save();
            }

            $row = app(AttendanceRollupService::class)->recompute($userId, $tenantId, $date, $ctx);

            // Same day classification the direct write used, on the primary
            // shift's work (multi-shift: 2nd+ shift hours are overtime).
            $attrs = $flags;
            if (!empty($row->clock_in) && !empty($row->clock_out)) {
                $daySeconds = (int) round(((float) $row->worked_hours) * 3600);
                $primaryHours = $this->calc->primaryWorkedSeconds($row, $daySeconds) / 3600;
                $expected = (int) $this->calc->expectedWorkSeconds($row->scheduled_shift_start ? [
                    'start_time' => $row->scheduled_shift_start, 'end_time' => $row->scheduled_shift_end,
                ] : null);
                $policy = app(PolicyResolver::class)->forUserDate($tenantId, $userId, $date);
                $baseStatus = $this->resolver->classifyWorked($primaryHours, $expected, $policy);
                $attrs['attendance_status'] = ($baseStatus === 'present' && $policy->isLate((int) $row->late_minutes)) ? 'late' : $baseStatus;
                $attrs['day_fraction'] = $baseStatus === 'half_day' ? 0.50 : ($baseStatus === 'absent' ? 0.00 : 1.00);
            }

            return $this->record($userId, $tenantId, $date, $attrs, $ctx);
        });
    }

    /**
     * The day's active punches belonging to the shift being regularized: all
     * of them on a one-shift day; on a multi-shift day those matched to the
     * target shift (for the primary also those with no / an unknown shift).
     */
    private function punchesOfShift(int $userId, int $tenantId, string $date, $instances, ?array $target)
    {
        $punches = AttendancePunch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('date', $date)->where('status', 'active')
            ->get();

        if ($instances->count() <= 1 || !$target) {
            return $punches;
        }

        $known = $instances->pluck('user_shift_id')->filter()->map(fn ($id) => (int) $id)->all();
        $targetId = (int) $target['user_shift_id'];
        $isPrimary = !$target['is_additional'];

        return $punches->filter(function ($p) use ($targetId, $isPrimary, $known) {
            $id = (int) $p->user_shift_id;

            return $id === $targetId || ($isPrimary && !in_array($id, $known, true));
        })->values();
    }

    /**
     * A regularized wall-clock time as an instant. Night shift: an in time in
     * the early-morning part (on/before the shift's end) and an out time
     * before the shift's start fall on the next day; any out time on/before
     * the in time is the next day too.
     */
    private function regularizedTime(string $date, string $time, $shift, string $direction, ?Carbon $clockIn): Carbon
    {
        $at = Carbon::parse($date . ' ' . $time);
        $overnight = $shift && \App\Support\ShiftWindow::isOvernight($shift);
        $minutes = \App\Support\ShiftWindow::toMinutes($time);

        if ($direction === 'in') {
            return ($overnight && $minutes <= \App\Support\ShiftWindow::toMinutes($shift->end_time)) ? $at->addDay() : $at;
        }

        if ($clockIn) {
            return $at->lte($clockIn) ? $at->addDay() : $at;
        }

        return ($overnight && $minutes < \App\Support\ShiftWindow::toMinutes($shift->start_time)) ? $at->addDay() : $at;
    }
    /**
     * For paths that own their row write (mobile clock-in/out, fingerprint,
     * auto clock-out) — write only the audit-log row for a change already saved.
     *
     * @param array<string,mixed> $before  snapshot of self::AUDITED before the change
     */
    public function logExternalWrite(Attendance $row, array $before, AuditContext $ctx, string $eventType = 'manual_adjustment'): void
    {
        $this->writeAuditLog($row->refresh(), $before, $ctx, $eventType);
    }

    /** Column snapshot suitable for logExternalWrite()'s $before argument. */
    public function snapshot(?Attendance $row): array
    {
        return $row ? $row->only(self::AUDITED) : [];
    }

    // ------------------------------------------------------------------

    /**
     * Run the late-policy + monthly-summary recompute for one month, or defer it
     * to the queue when async recompute is enabled (Tier 1 / W2). Deferring
     * marks the summary row stale synchronously so a read before the job lands
     * still self-heals.
     */
    private function refreshMonth(int $userId, int $tenantId, string $ym): void
    {
        if (config('attendance.async_recompute')) {
            DB::table('attendance_summaries')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('year_month', $ym)
                ->update(['stale_at' => now()]);

            \App\Jobs\RecalculateAttendanceMonth::dispatch($userId, $tenantId, $ym)->afterCommit();

            return;
        }

        $this->latePolicy->recalculateMonth($userId, $tenantId, $ym);
        $this->summary->updateMonthlySummary($userId, $ym, $tenantId);
    }

    /**
     * Tier 2 / T2-C — announce the write so webhooks (and any internal listener)
     * can react. Event name follows the audit source.
     */
    private function emitDomainEvent(Attendance $row, AuditContext $ctx): void
    {
        $name = match ($ctx->source) {
            'regularization' => 'attendance.regularized',
            'clock_in' => 'attendance.clock_in',
            'clock_out' => 'attendance.clock_out',
            'biometric' => 'attendance.marked',
            default => 'attendance.marked',
        };

        try {
            event(new \App\Events\AttendanceDomainEvent($name, (int) $row->tenant_id, [
                'attendance_id' => $row->id,
                'user_id' => $row->user_id,
                'date' => (string) $row->date,
                'attendance_status' => $row->attendance_status,
                'effective_status' => $row->effective_status,
                'worked_hours' => $row->worked_hours,
                'is_regularized' => (bool) $row->is_regularized,
                'actor_id' => $ctx->actorId,
                'source' => $ctx->source,
            ]));

            if ((int) ($row->late_minutes ?? 0) > 0 && $name === 'attendance.marked') {
                event(new \App\Events\AttendanceDomainEvent('attendance.clock_in_late', (int) $row->tenant_id, [
                    'attendance_id' => $row->id,
                    'user_id' => $row->user_id,
                    'date' => (string) $row->date,
                    'late_minutes' => (int) $row->late_minutes,
                ]));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('emitDomainEvent failed: ' . $e->getMessage());
        }
    }

    private function writeAuditLog(Attendance $row, array $before, AuditContext $ctx, string $eventType = 'manual_adjustment'): void
    {
        $after = $row->only(self::AUDITED);
        $changedBefore = [];
        $changedAfter = [];
        foreach (self::AUDITED as $col) {
            $b = $before[$col] ?? null;
            $a = $after[$col] ?? null;
            if ((string) $b !== (string) $a) {
                $changedBefore[$col] = $b;
                $changedAfter[$col] = $a;
            }
        }

        AttendanceLog::create([
            'tenant_id' => $row->tenant_id,
            'user_id' => $row->user_id,
            'actor_id' => $ctx->actorId,
            'actor_role' => $ctx->actorRole,
            'source' => $ctx->source,
            'attendance_id' => $row->id,
            'event_type' => $eventType,
            'event_time' => now(),
            'verification_method' => 'system',
            'user_agent' => 'AttendanceEntryService',
            'reason' => $ctx->reason ? mb_substr($ctx->reason, 0, 500) : null,
            'before' => $changedBefore ?: null,
            'after' => $changedAfter ?: null,
            'raw_data' => json_encode(['source' => $ctx->source, 'actor_id' => $ctx->actorId]),
        ]);
    }
}
