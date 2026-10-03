<?php

namespace App\Services\Attendance;

use App\Models\Shift;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        // Only the day's PRIMARY row: 2nd+ shifts (is_additional = 1) don't
        // change what attendance/payroll measure against.
        $shiftId = $this->primaryRow($userId, $tenantId, $date)?->shift_id;

        if (!$shiftId) {
            return null;
        }

        return Shift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->find($shiftId);
    }

    /**
     * Every shift the user works on $date — the primary first, then any
     * additional shifts by start time. Each item: user_shift_id (null for the
     * fixed company shift), is_additional, shift, start/end (real instants,
     * end on the next day for an overnight shift).
     *
     * @return \Illuminate\Support\Collection<int,array{user_shift_id:?int,is_additional:bool,shift:Shift,start:\Carbon\Carbon,end:\Carbon\Carbon}>
     */
    public function instancesForUserDate(int $userId, int $tenantId, ?string $date = null): Collection
    {
        $date = Carbon::parse($date ?: now()->format('Y-m-d'))->format('Y-m-d');

        if (!$this->isCustomShifts($tenantId)) {
            $shift = $this->defaultShift($tenantId);

            return $shift ? collect([$this->instance(null, false, $shift, $date)]) : collect();
        }

        $rows = DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->get(['id', 'shift_id', 'is_additional']);

        $shifts = Shift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereIn('id', $rows->pluck('shift_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->filter(fn ($r) => $shifts->has($r->shift_id))
            ->map(fn ($r) => $this->instance((int) $r->id, (bool) $r->is_additional, $shifts->get($r->shift_id), $date))
            ->sortBy(fn ($i) => [$i['is_additional'] ? 1 : 0, $i['start']->getTimestamp()])
            ->values();
    }

    /**
     * The primary shift for $date as the array the legacy getUserShiftForDate()
     * helpers returned (shift_id/name/start_time/end_time/grace_minutes +
     * the primary user_shifts row id). Null when no shift applies.
     */
    public function detailsForUserDate(int $userId, int $tenantId, string $date): ?array
    {
        $shift = $this->forUserDate($userId, $tenantId, $date);

        if (!$shift) {
            return null;
        }

        return [
            'shift_id' => $shift->id,
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'grace_minutes' => $shift->grace_minutes ?? 0,
            'is_overnight' => ShiftWindow::isOvernight($shift),
            'user_shift_id' => $this->primaryRow($userId, $tenantId, $date)?->id,
        ];
    }

    /** How early before a shift's start a clock-in still counts for that shift. */
    public const EARLY_CLOCK_IN_MINUTES = 120;

    /**
     * Does the user work any additional (2nd+) shift between $date-1 and
     * $date+1? Only then is punch-to-shift matching needed — a one-shift user
     * keeps the long-standing date rules untouched.
     */
    public function hasAdditionalAround(int $userId, int $tenantId, string $date): bool
    {
        if (!$this->isCustomShifts($tenantId)) {
            return false;
        }

        $d = Carbon::parse($date);

        return DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_additional', 1)
            ->whereBetween('date', [$d->copy()->subDay()->toDateString(), $d->copy()->addDay()->toDateString()])
            ->exists();
    }

    /**
     * The shift a clock-in at $punchedAt belongs to: among the shifts the user
     * works on the punch's calendar day and the days either side, the one
     * whose window [start - EARLY_CLOCK_IN_MINUTES, end) contains the punch,
     * nearest start first. Null when the punch fits no shift.
     *
     * @return array{date:string,user_shift_id:?int,is_additional:bool,shift:Shift,start:Carbon,end:Carbon}|null
     */
    public function instanceForPunch(int $userId, int $tenantId, \Carbon\CarbonInterface $punchedAt): ?array
    {
        $best = null;
        $bestDistance = null;

        foreach ([-1, 0, 1] as $offset) {
            $date = Carbon::parse($punchedAt->format('Y-m-d'))->addDays($offset)->toDateString();

            foreach ($this->instancesForUserDate($userId, $tenantId, $date) as $instance) {
                $opensAt = $instance['start']->copy()->subMinutes(self::EARLY_CLOCK_IN_MINUTES);
                if ($punchedAt->lt($opensAt) || $punchedAt->gte($instance['end'])) {
                    continue;
                }

                $distance = abs($punchedAt->getTimestamp() - $instance['start']->getTimestamp());
                if ($bestDistance === null || $distance < $bestDistance) {
                    $best = $instance + ['date' => $date];
                    $bestDistance = $distance;
                }
            }
        }

        return $best;
    }

    /** The day's primary user_shifts row (id, shift_id), or null. */
    public function primaryRow(int $userId, int $tenantId, string $date): ?object
    {
        return DB::table('user_shifts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->where('is_additional', 0)
            ->first(['id', 'shift_id']);
    }

    private function instance(?int $userShiftId, bool $isAdditional, Shift $shift, string $date): array
    {
        [$start, $end] = ShiftWindow::window($date, $shift);

        return [
            'user_shift_id' => $userShiftId,
            'is_additional' => $isAdditional,
            'shift' => $shift,
            'start' => $start,
            'end' => $end,
        ];
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
