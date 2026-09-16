<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
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
     * Materialise an approved regularization into a complete `attendances` row.
     * Fixes the historical gaps (status left 'present', shift snapshot NULL,
     * is_regularized never persisted, no audit log).
     */
    public function applyRegularization(AttendanceRegularization $reg, User $approver): Attendance
    {
        $userId = (int) $reg->user_id;
        $tenantId = (int) ($reg->tenant_id ?: User::withoutGlobalScopes()->whereKey($userId)->value('tenant_id'));
        $date = Carbon::parse($reg->date)->format('Y-m-d');

        if (Carbon::parse($date)->gt(Carbon::today())) {
            throw new \RuntimeException('Cannot regularize a future date.');
        }

        $shift = app(TenantShiftResolver::class)->forUserDate($userId, $tenantId, $date);
        $jobDetail = UserJobDetail::where('user_id', $userId)->where('tenant_id', $tenantId)->first();

        $attrs = [
            'regularization_id' => $reg->id,
            'is_regularized' => 1,
            'regularized_by' => $approver->id,
            'regularized_at' => now(),
            'marked_by' => $approver->id,
            'status' => 1,
            'shift_id' => $shift->id ?? null,
            'scheduled_shift_start' => $shift->start_time ?? null,
            'scheduled_shift_end' => $shift->end_time ?? null,
            'branch_id' => $jobDetail->office_branch ?? null,
            'effective_status' => null,
            'policy_note' => null,
        ];

        $clockIn = $clockOut = null;
        if (!empty($reg->in_time)) {
            $clockIn = Carbon::parse($date . ' ' . $reg->in_time);
            $attrs['clock_in'] = $clockIn->format('Y-m-d H:i:s');
        }
        if (!empty($reg->out_time)) {
            $clockOut = $this->calc->resolveClockOut(
                $clockIn ?? Carbon::parse($date . ' ' . $reg->out_time),
                Carbon::parse($date . ' ' . $reg->out_time),
                $shift
            );
            $attrs['clock_out'] = $clockOut->format('Y-m-d H:i:s');
        }

        if ($clockIn && $clockOut) {
            $seconds = $this->calc->workedSeconds($clockIn, $clockOut);
            $attrs['worked_hours'] = $this->calc->decimalHours($seconds);
            $attrs['total_hours'] = $this->calc->formatDuration($seconds);
            $attrs['late_minutes'] = $shift
                ? $this->calc->lateMinutes($shift, Carbon::parse($date . ' ' . $shift->start_time), $clockIn)
                : 0;
            $expected = $shift ? $this->calc->expectedWorkSeconds([
                'start_time' => $shift->start_time, 'end_time' => $shift->end_time,
            ]) : 0;
            $worked = (float) $attrs['worked_hours'];
            $policy = app(PolicyResolver::class)->forTenantDate($tenantId, $date);
            $base = $this->resolver->classifyWorked($worked, (int) $expected, $policy);
            $attrs['attendance_status'] = ($base === 'present' && $policy->isLate((int) ($attrs['late_minutes'] ?? 0))) ? 'late' : $base;
            $attrs['day_fraction'] = $base === 'half_day' ? 0.50 : ($base === 'absent' ? 0.00 : 1.00);
        }

        $ctx = new AuditContext(
            actorId: $approver->id,
            actorRole: $approver->role,
            source: 'regularization',
            reason: 'Regularization #' . $reg->id . ' approved',
        );

        return $this->record($userId, $tenantId, $date, $attrs, $ctx);
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
