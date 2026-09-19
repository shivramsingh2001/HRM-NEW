<?php

namespace App\Services\Shift;

use App\Models\ShiftAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Conflict rules for creating/editing shift_assignments rows. See
 * docs/modules.md "Shift Management" for the full priority/conflict table.
 */
class ShiftAssignmentValidator
{
    /**
     * The user's current active Permanent assignment, if any. A new Permanent
     * assignment always auto-supersedes this one — it is never a hard
     * conflict, just information the caller uses to close the old row and
     * show an "will replace X" notice in the UI.
     */
    public function findActivePermanent(int $tenantId, int $userId): ?ShiftAssignment
    {
        return ShiftAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('type', 'permanent')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Active Flexible assignments for this user overlapping [start, end].
     * A Flexible request colliding with one of these is only allowed when
     * override_existing is set — matches today's assignShift behaviour.
     */
    public function overlappingActiveFlexible(int $tenantId, int $userId, Carbon $start, Carbon $end): Collection
    {
        return ShiftAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('type', 'flexible')
            ->where('status', 'active')
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $start->toDateString());
            })
            ->get();
    }
}
