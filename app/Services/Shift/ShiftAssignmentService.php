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
            $previous = $this->validator->findActivePermanent($tenantId, $userId);

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
