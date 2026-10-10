<?php

namespace App\Services\Shift;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\UserShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The write path for Permanent/Flexible shift assignments. Creates/closes
 * `shift_assignments` history rows and delegates cache materialization to
 * ShiftMaterializer. See docs/modules.md "Shift Management" for the design.
 */
class ShiftAssignmentService
{
    public function __construct(
        private ShiftAssignmentValidator $validator,
        private ShiftMaterializer $materializer,
        private ShiftOverlapGuard $overlapGuard
    ) {
    }

    /**
     * Assign an ADDITIONAL shift (2nd+ shift on the same days) — Permanent
     * (open-ended, $endDate null) or Flexible (date range). Never replaces or
     * supersedes the primary shift or other additional shifts; overlaps are
     * rejected up front by the caller via additionalConflicts().
     */
    public function assignAdditional(
        int $tenantId,
        int $userId,
        int $shiftId,
        string $type,
        Carbon $startDate,
        ?Carbon $endDate,
        int $createdBy
    ): array {
        return DB::transaction(function () use ($tenantId, $userId, $shiftId, $type, $startDate, $endDate, $createdBy) {
            $assignment = ShiftAssignment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'type' => $type,
                'is_additional' => 1,
                'start_date' => $startDate->toDateString(),
                'end_date' => $type === 'permanent' ? null : ($endDate ?? $startDate)->toDateString(),
                'status' => 'active',
                'source' => 'manual',
                'created_by' => $createdBy,
            ]);

            $result = $type === 'permanent'
                ? $this->materializer->materializePermanentHorizon($assignment)
                : $this->materializer->materializeFlexible($assignment, $startDate, $endDate ?? $startDate);

            return ['assignment' => $assignment, 'materialized' => $result];
        });
    }

    /**
     * Dates an additional shift would cover that overlap a shift the user
     * already works: [date => clashing shift name]. A Permanent additional
     * shift is checked over the cached horizon.
     */
    public function additionalConflicts(int $tenantId, int $userId, Shift $shift, string $type, Carbon $startDate, ?Carbon $endDate): array
    {
        $to = $type === 'permanent'
            ? Carbon::today()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS)
            : ($endDate ?? $startDate)->copy();

        $dates = [];
        for ($d = $startDate->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $this->overlapGuard->conflicts($tenantId, $userId, $shift, $dates);
    }
    /**
     * Assign a Permanent (open-ended) shift. Auto-supersedes the user's
     * existing active Permanent, if any — never a hard conflict.
     */
    public function assignPermanent(
        int $tenantId,
        int $userId,
        int $shiftId,
        Carbon $startDate,
        int $createdBy,
        ?string $weekOffType = null,
        ?array $weekOffDays = null,
        ?array $weekOffDates = null
    ): array {
        return DB::transaction(function () use ($tenantId, $userId, $shiftId, $startDate, $createdBy, $weekOffType, $weekOffDays, $weekOffDates) {
            // A standing assignment (Permanent or Rotating) is replaced by the new one.
            $previous = $this->validator->findActiveStanding($tenantId, $userId);
            if ($previous?->type === 'rotating') {
                $this->materializer->clearRotationDaysOff($previous, $startDate);
            }

            $new = ShiftAssignment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'type' => 'permanent',
                'start_date' => $startDate->toDateString(),
                'end_date' => null,
                'status' => 'active',
                'source' => 'manual',
                'week_off_type' => $weekOffType,
                'week_off_days' => $weekOffDays,
                'week_off_dates' => $weekOffDates,
                'created_by' => $createdBy,
            ]);

            if ($previous) {
                $previous->update([
                    'end_date' => $startDate->copy()->subDay()->toDateString(),
                    'status' => 'superseded',
                    'superseded_by_id' => $new->id,
                    'ended_by' => $createdBy,
                    'ended_at' => now(),
                ]);
            }

            $result = $this->materializer->materializePermanentHorizon($new);

            return ['assignment' => $new, 'superseded' => $previous, 'materialized' => $result];
        });
    }

    /**
     * Assign a Flexible (per-date/range) shift — today's existing assign
     * behaviour, unchanged, with a shift_assignments history row added.
     * Allowed to overlap an active Permanent (the override case). Overlap
     * with another active Flexible requires $overrideExisting, matching the
     * legacy assignShift flag; the old Flexible row is closed (not split).
     */
    public function assignFlexible(
        int $tenantId,
        int $userId,
        int $shiftId,
        Carbon $startDate,
        Carbon $endDate,
        int $createdBy,
        bool $overrideExisting = false,
        bool $applyToFutureOnly = false,
        ?string $weekOffType = null,
        ?array $weekOffDays = null,
        ?array $weekOffDates = null
    ): array {
        return DB::transaction(function () use (
            $tenantId, $userId, $shiftId, $startDate, $endDate, $createdBy,
            $overrideExisting, $applyToFutureOnly, $weekOffType, $weekOffDays, $weekOffDates
        ) {
            $overlapping = $this->validator->overlappingActiveFlexible($tenantId, $userId, $startDate, $endDate);

            if ($overrideExisting) {
                foreach ($overlapping as $old) {
                    $old->update([
                        'end_date' => $startDate->copy()->subDay()->toDateString(),
                        'status' => 'superseded',
                        'ended_by' => $createdBy,
                        'ended_at' => now(),
                    ]);
                }
            }

            $new = ShiftAssignment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'type' => 'flexible',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'source' => 'manual',
                'week_off_type' => $weekOffType,
                'week_off_days' => $weekOffDays,
                'week_off_dates' => $weekOffDates,
                'created_by' => $createdBy,
            ]);

            if ($overlapping->isNotEmpty()) {
                foreach ($overlapping as $old) {
                    $old->update(['superseded_by_id' => $new->id]);
                }
            }

            $result = $this->materializer->materializeFlexible($new, $startDate, $endDate, $overrideExisting, $applyToFutureOnly);

            return ['assignment' => $new, 'materialized' => $result];
        });
    }

    /**
     * Assign a rotating pattern (open-ended, like Permanent). Replaces the
     * employee's active Permanent / Rotating assignment from $startDate.
     * $anchorDate = the date that counts as day 1 of the cycle (defaults to
     * $startDate; staggered teams use different anchors). With
     * $replaceWeeklyOffs the employee's hand-entered weekly offs (e.g. every
     * Sunday) stop from $startDate, because the pattern's own days off take over.
     *
     * @return array{assignment: ShiftAssignment, superseded: ?ShiftAssignment, materialized: array, weekly_offs_ended: int}
     */
    public function assignRotating(
        int $tenantId,
        int $userId,
        \App\Models\ShiftRotationPattern $pattern,
        Carbon $startDate,
        int $createdBy,
        ?Carbon $anchorDate = null,
        bool $replaceWeeklyOffs = true
    ): array {
        return DB::transaction(function () use ($tenantId, $userId, $pattern, $startDate, $createdBy, $anchorDate, $replaceWeeklyOffs) {
            $previous = $this->validator->findActiveStanding($tenantId, $userId);
            if ($previous?->type === 'rotating') {
                $this->materializer->clearRotationDaysOff($previous, $startDate);
            }

            $ended = 0;
            if ($replaceWeeklyOffs) {
                $weekly = \App\Models\UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
                    ->where('off_type', 'day_based')->where('status', 1)->whereNull('shift_assignment_id')
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate->toDateString()))
                    ->get();
                foreach ($weekly as $w) {
                    $w->start_date && Carbon::parse($w->start_date)->gte($startDate)
                        ? $w->update(['status' => 0])
                        : $w->update(['end_date' => $startDate->copy()->subDay()->toDateString()]);
                    $ended++;
                }
            }

            $firstShift = collect($pattern->stepMap())->filter()->first();
            $new = ShiftAssignment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $firstShift,
                'type' => 'rotating',
                'start_date' => $startDate->toDateString(),
                'end_date' => null,
                'status' => 'active',
                'source' => 'rotation',
                'rotation_pattern_id' => $pattern->id,
                'rotation_anchor_date' => ($anchorDate ?? $startDate)->toDateString(),
                'notes' => $ended ? "Weekly offs replaced by the pattern's days off" : null,
                'created_by' => $createdBy,
            ]);

            if ($previous) {
                $previous->update([
                    'end_date' => $startDate->copy()->subDay()->toDateString(),
                    'status' => 'superseded',
                    'superseded_by_id' => $new->id,
                    'ended_by' => $createdBy,
                    'ended_at' => now(),
                ]);
            }

            $result = $this->materializer->materializeRotatingHorizon($new);

            return ['assignment' => $new, 'superseded' => $previous, 'materialized' => $result, 'weekly_offs_ended' => $ended];
        });
    }

    /**
     * Change ONE day's main shift as a one-day exception (roster day edit,
     * direct swap / change, approved shift request). Creates a Flexible
     * assignment with is_override = 1 covering just that date — it never
     * closes or shortens the employee's other assignments, beats them for its
     * date, and is re-applied last when the cache is rebuilt, so the change
     * survives an end / supersede of the main shift. An earlier override of the
     * same day is superseded (kept in history).
     *
     * Callers check ShiftChangeGuard / ShiftOverlapGuard first.
     *
     * @return array{assignment: ShiftAssignment, previous_shift_id: ?int, previous_assignment_id: ?int}
     */
    public function applyDayOverride(
        int $tenantId,
        int $userId,
        Carbon $date,
        int $shiftId,
        int $createdBy,
        string $source = 'day_override',
        ?string $reason = null,
        ?int $shiftRequestId = null,
        ?string $status = null
    ): array {
        return DB::transaction(function () use ($tenantId, $userId, $date, $shiftId, $createdBy, $source, $reason, $shiftRequestId, $status) {
            $ds = $date->toDateString();

            $row = UserShift::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('date', $ds)
                ->where('is_additional', 0)
                ->lockForUpdate()
                ->first();

            $new = ShiftAssignment::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'type' => 'flexible',
                'is_override' => 1,
                'start_date' => $ds,
                'end_date' => $ds,
                'status' => 'active',
                'source' => $source,
                'shift_request_id' => $shiftRequestId,
                'reason' => $reason ? mb_substr($reason, 0, 500) : null,
                'created_by' => $createdBy,
            ]);

            ShiftAssignment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('is_override', 1)
                ->where('is_additional', 0)
                ->where('status', 'active')
                ->where('start_date', $ds)
                ->where('id', '!=', $new->id)
                ->update(['status' => 'superseded', 'superseded_by_id' => $new->id, 'ended_by' => $createdBy, 'ended_at' => now()]);

            $this->materializer->writeDay($new, $ds, $status);

            return [
                'assignment' => $new,
                'previous_shift_id' => $row ? (int) $row->shift_id : null,
                'previous_assignment_id' => $row?->shift_assignment_id ? (int) $row->shift_assignment_id : null,
            ];
        });
    }

    /**
     * Undo a one-day override (a shift request reverted): cancel it and
     * rebuild that day from whatever wins now. $fallbackShiftId restores a
     * hand-made day that had no assignment behind it.
     */
    public function cancelDayOverride(ShiftAssignment $override, int $endedBy, ?string $reason = null, ?int $fallbackShiftId = null): void
    {
        DB::transaction(function () use ($override, $endedBy, $reason, $fallbackShiftId) {
            $override->update([
                'status' => 'cancelled',
                'ended_by' => $endedBy,
                'ended_at' => now(),
                'notes' => $reason ? mb_substr($reason, 0, 255) : $override->notes,
            ]);

            // An earlier one-day change of the same day that this one replaced comes back.
            ShiftAssignment::withoutGlobalScopes()
                ->where('tenant_id', $override->tenant_id)
                ->where('user_id', $override->user_id)
                ->where('is_override', 1)
                ->where('status', 'superseded')
                ->where('superseded_by_id', $override->id)
                ->update(['status' => 'active', 'superseded_by_id' => null, 'ended_by' => null, 'ended_at' => null]);

            $this->materializer->rebuildDate(
                (int) $override->tenant_id,
                (int) $override->user_id,
                $override->start_date->toDateString(),
                $fallbackShiftId
            );
        });
    }

    /**
     * Explicitly stop a Permanent assignment (the "End Permanent Shift" UI
     * action) — closes the history row and clears future cache days, letting
     * whatever still-active Flexible/nothing take over from there.
     */
    public function endPermanent(ShiftAssignment $assignment, Carbon $endDate, int $endedBy, ?string $reason = null): ShiftAssignment
    {
        return DB::transaction(function () use ($assignment, $endDate, $endedBy, $reason) {
            $assignment->update([
                'end_date' => $endDate->toDateString(),
                'status' => 'ended',
                'ended_by' => $endedBy,
                'ended_at' => now(),
                'notes' => $reason,
            ]);

            if ($assignment->type === 'rotating') {
                $this->materializer->clearRotationDaysOff($assignment, $endDate->copy()->addDay());
            }

            // An additional shift only owns its own rows — drop them after the end date.
            if ($assignment->is_additional) {
                UserShift::withoutGlobalScopes()
                    ->where('tenant_id', $assignment->tenant_id)
                    ->where('shift_assignment_id', $assignment->id)
                    ->where('date', '>', $endDate->toDateString())
                    ->delete();

                return $assignment;
            }

            $this->materializer->regenerateFrom($assignment->tenant_id, $assignment->user_id, $endDate->copy()->addDay());

            return $assignment;
        });
    }
}
