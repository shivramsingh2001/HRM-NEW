<?php

namespace App\Services\Attendance;

use App\Models\Shift;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which shift applies to this user on this date".
 *
 * A tenant with `custom_shifts_enabled = 0` runs the whole company on one fixed
 * shift (`tenants.default_shift_id`); per-employee / per-date `user_shifts`
 * assignment is ignored. A tenant with `custom_shifts_enabled = 1` uses the
 * existing assign-shift chain.
 *
 * Everything is resolved with explicit `tenant_id` filters and
 * `withoutGlobalScopes()`, so this is safe to call from queued jobs and console
 * commands where `app('current_tenant')` is not bound.
 */
class TenantShiftResolver
{
    /** @var array<int,object|null> per-process cache of the tenant shift-policy row */
    private array $policyCache = [];

    /** @var array<int,\App\Models\Shift|null> per-process cache of resolved default shifts */
    private array $defaultShiftCache = [];

    /**
     * The shift a user's attendance for $date should be measured against, or null
     * when none can be resolved.
     */
    public function forUserDate(int $userId, int $tenantId, ?string $date = null): ?Shift
    {
        if (!$this->isCustomShifts($tenantId)) {
            return $this->defaultShift($tenantId);
        }

        $date = $date ?: now()->format('Y-m-d');

        // Per-date assignment (the only place a shift is actually assigned in the
        // existing feature — `user_job_details.shift_id` is never written).
        $shiftId = DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->value('shift_id');

        if (!$shiftId) {
            return null;
        }

        return Shift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->find($shiftId);
    }

    public function isCustomShifts(int $tenantId): bool
    {
        return (bool) ($this->policy($tenantId)->custom_shifts_enabled ?? false);
    }

    /**
     * The tenant's single fixed shift (used when custom shifts are off). Returned
     * regardless of its `status` so an admin can still edit a disabled row.
     */
    public function defaultShift(int $tenantId): ?Shift
    {
        if (array_key_exists($tenantId, $this->defaultShiftCache)) {
            return $this->defaultShiftCache[$tenantId];
        }

        $shiftId = $this->policy($tenantId)->default_shift_id ?? null;

        $shift = $shiftId
            ? Shift::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($shiftId)
            : null;

        return $this->defaultShiftCache[$tenantId] = $shift;
    }

    private function policy(int $tenantId): object
    {
        return $this->policyCache[$tenantId] ??= (DB::table('tenants')
            ->where('id', $tenantId)
            ->first(['custom_shifts_enabled', 'default_shift_id']) ?: (object) [
                'custom_shifts_enabled' => 0,
                'default_shift_id' => null,
            ]);
    }
}
