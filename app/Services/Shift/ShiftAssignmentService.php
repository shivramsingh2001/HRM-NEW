<?php

namespace App\Services\Shift;

use App\Models\ShiftAssignment;
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
        private ShiftMaterializer $materializer
    ) {
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

            $this->materializer->regenerateFrom($assignment->tenant_id, $assignment->user_id, $endDate->copy()->addDay());

            return $assignment;
        });
    }
}
