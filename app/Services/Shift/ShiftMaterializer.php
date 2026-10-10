<?php

namespace App\Services\Shift;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns every `user_shifts` write driven by a `shift_assignments` row.
 * `user_shifts` is kept as a generated per-day cache so every existing reader
 * (TenantShiftResolver, CheckMissedCheckIns, the mobile/AI shift-plan
 * endpoints, roster grid, etc.) keeps working unmodified — this class is
 * simply what keeps that cache correct.
 *
 * The Flexible-beats-Permanent priority rule (see docs/modules.md "Shift
 * Management") is enforced here, at write time: Flexible materialization
 * always overwrites whatever is cached for its dates; Permanent
 * materialization skips any date an active Flexible assignment already owns.
 * TenantShiftResolver itself never changes.
 *
 * Additional shifts (multi-shift Phase 2): an assignment with is_additional = 1
 * writes extra rows (user_shifts.is_additional = 1) next to the day's primary
 * row and never replaces it — see materializeAdditional(). Every primary
 * read/write below filters is_additional = 0, so additional rows are invisible
 * to the Permanent/Flexible priority logic.
 */
class ShiftMaterializer
{
    public function __construct(private ShiftOverlapGuard $overlapGuard)
    {
    }

    /** How far into the future an open-ended Permanent assignment is cached. */
    public const PERMANENT_HORIZON_DAYS = 120;

    /**
     * Materialize a Flexible assignment's exact date range. Mirrors the
     * legacy per-date assign loop (duplicate-key races, override/future-only
     * semantics) so response counts stay identical to before this feature;
     * additionally stamps shift_assignment_id and always wins over whatever
     * a Permanent assignment previously cached for the same dates.
     */
    public function materializeFlexible(
        ShiftAssignment $assignment,
        Carbon $startDate,
        Carbon $endDate,
        bool $overrideExisting = false,
        bool $applyToFutureOnly = false
    ): array {
        if ($assignment->is_additional) {
            return $this->materializeAdditional($assignment, $startDate, $endDate);
        }

        $userId = $assignment->user_id;
        $tenantId = $assignment->tenant_id;
        $shiftId = $assignment->shift_id;

        $userWeekoffs = UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->get();

        $existing = UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('is_additional', 0)
            ->get()
            ->keyBy('date');

        // Flexible always wins over whatever a Permanent assignment cached —
        // that override happens unconditionally, independent of
        // $overrideExisting, which only gates collisions with another
        // Flexible assignment's cached days (see ShiftAssignmentValidator).
        $assignmentIds = $existing->pluck('shift_assignment_id')->filter()->unique()->values();
        $permanentAssignmentIds = $assignmentIds->isEmpty()
            ? collect()
            : ShiftAssignment::withoutGlobalScopes()->whereIn('id', $assignmentIds)->where('type', 'permanent')->pluck('id');

        $assigned = 0;
        $skipped = 0;
        $alreadyAssigned = 0;
        $duplicateSkipped = 0;
        $weekOffSkipped = 0;

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateString = $current->toDateString();

            if (WeekOffPredicate::isWeekOff($userWeekoffs, $current)) {
                $weekOffSkipped++;
                $current->addDay();
                continue;
            }

            $row = $existing->get($dateString);

            if ($row) {
                $shouldUpdate = $row->shift_assignment_id && $permanentAssignmentIds->contains($row->shift_assignment_id);

                if (!$shouldUpdate && $overrideExisting) {
                    if ($applyToFutureOnly) {
                        $shouldUpdate = $current->isFuture() || $current->isToday();
                    } else {
                        $shouldUpdate = true;
                    }
                }

                if ($shouldUpdate) {
                    $row->update([
                        'shift_id' => $shiftId,
                        'shift_assignment_id' => $assignment->id,
                        'status' => 'upcoming',
                    ]);
                    $assigned++;
                } else {
                    $alreadyAssigned++;
                }

                $current->addDay();
                continue;
            }

            if ($overrideExisting || $current->isFuture() || $current->isToday()) {
                try {
                    UserShift::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'shift_id' => $shiftId,
                        'shift_assignment_id' => $assignment->id,
                        'date' => $dateString,
                        'status' => 'upcoming',
                        'created_by' => $assignment->created_by,
                    ]);
                    $assigned++;
                    $existing->put($dateString, (object) ['placeholder' => true]);
                } catch (\Illuminate\Database\QueryException $e) {
                    if (($e->errorInfo[1] ?? null) == 1062) {
                        $duplicateSkipped++;
                    } else {
                        throw $e;
                    }
                }
            } else {
                $skipped++;
            }

            $current->addDay();
        }

        Log::info('Flexible shift materialization result', [
            'assignment_id' => $assignment->id,
            'user_id' => $userId,
            'assigned' => $assigned,
            'skipped' => $skipped,
            'already_assigned' => $alreadyAssigned,
            'duplicate_skipped' => $duplicateSkipped,
            'week_off_skipped' => $weekOffSkipped,
        ]);

        return [
            'assigned' => $assigned,
            'skipped' => $skipped,
            'already_assigned' => $alreadyAssigned,
            'duplicate_skipped' => $duplicateSkipped,
            'week_off_skipped' => $weekOffSkipped,
        ];
    }

    /**
     * Top up an open-ended Permanent assignment's cache to today+horizon.
     * Skips week-offs and any date an active Flexible assignment already
     * owns (Flexible always wins).
     */
    public function materializePermanentHorizon(ShiftAssignment $assignment, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $today = Carbon::today();
        $horizonEnd = Carbon::today()->addDays(self::PERMANENT_HORIZON_DAYS);

        if (!$from) {
            $from = $assignment->start_date->gt($today) ? $assignment->start_date->copy() : $today->copy();
        }
        if (!$to) {
            $candidateEnd = $assignment->end_date ? $assignment->end_date->copy() : $horizonEnd->copy();
            $to = $candidateEnd->lt($horizonEnd) ? $candidateEnd : $horizonEnd->copy();
        }

        if ($from->gt($to)) {
            return ['assigned' => 0, 'skipped_flexible_owned' => 0];
        }

        if ($assignment->is_additional) {
            return $this->materializeAdditional($assignment, $from, $to) + ['skipped_flexible_owned' => 0];
        }

        $userId = $assignment->user_id;
        $tenantId = $assignment->tenant_id;
        $shiftId = $assignment->shift_id;

        $userWeekoffs = UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->get();

        $flexibleAssignments = ShiftAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('type', 'flexible')
            ->where('is_additional', 0)
            ->where('status', 'active')
            ->where('start_date', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $from->toDateString());
            })
            ->get();

        $existing = UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('is_additional', 0)
            ->get()
            ->keyBy('date');

        $assigned = 0;
        $skippedFlexibleOwned = 0;

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateString = $current->toDateString();

            if (WeekOffPredicate::isWeekOff($userWeekoffs, $current)) {
                $current->addDay();
                continue;
            }

            if ($this->dateOwnedByFlexible($flexibleAssignments, $dateString)) {
                $skippedFlexibleOwned++;
                $current->addDay();
                continue;
            }

            $row = $existing->get($dateString);

            try {
                if ($row) {
                    $row->update([
                        'shift_id' => $shiftId,
                        'shift_assignment_id' => $assignment->id,
                        'status' => 'upcoming',
                    ]);
                } else {
                    UserShift::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'shift_id' => $shiftId,
                        'shift_assignment_id' => $assignment->id,
                        'date' => $dateString,
                        'status' => 'upcoming',
                        'created_by' => $assignment->created_by,
                    ]);
                }
                $assigned++;
            } catch (\Illuminate\Database\QueryException $e) {
                if (($e->errorInfo[1] ?? null) == 1062) {
                    UserShift::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->where('date', $dateString)
                        ->where('is_additional', 0)
                        ->update([
                            'shift_id' => $shiftId,
                            'shift_assignment_id' => $assignment->id,
                            'status' => 'upcoming',
                        ]);
                    $assigned++;
                } else {
                    throw $e;
                }
            }

            $current->addDay();
        }

        return ['assigned' => $assigned, 'skipped_flexible_owned' => $skippedFlexibleOwned];
    }

    /**
     * After an assignment ends/is superseded, rebuild the cache from
     * $fromDate to the horizon so whichever assignment now wins (another
     * still-active Flexible, or nothing) is reflected. Only touches rows this
     * class ever wrote (shift_assignment_id IS NOT NULL) — a manual single-day
     * override via updateUserShift is left alone.
     */
    public function regenerateFrom(int $tenantId, int $userId, Carbon $fromDate): void
    {
        $horizonEnd = Carbon::today()->addDays(self::PERMANENT_HORIZON_DAYS);
        if ($fromDate->gt($horizonEnd)) {
            return;
        }

        UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$fromDate->toDateString(), $horizonEnd->toDateString()])
            ->whereNotNull('shift_assignment_id')
            ->where('is_additional', 0)
            ->delete();

        $stillActive = ShiftAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_additional', 0)
            ->where('status', 'active')
            ->where('start_date', '<=', $horizonEnd->toDateString())
            ->where(function ($q) use ($fromDate) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $fromDate->toDateString());
            })
            // permanent / rotating first, flexible overwrites second, one-day
            // overrides (roster day edits, swaps, approved change requests) last.
            ->orderByRaw("FIELD(type, 'permanent', 'rotating', 'flexible')")
            ->orderBy('is_override')
            ->orderBy('id')
            ->get();

        foreach ($stillActive as $assignment) {
            if ($assignment->type === 'permanent') {
                $this->materializePermanentHorizon($assignment, $fromDate->copy(), $horizonEnd->copy());
            } elseif ($assignment->type === 'rotating') {
                $this->materializeRotatingHorizon($assignment, $fromDate->copy(), $horizonEnd->copy());
            } else {
                $rangeStart = $assignment->start_date->gt($fromDate) ? $assignment->start_date->copy() : $fromDate->copy();
                $rangeEnd = $assignment->end_date && $assignment->end_date->lt($horizonEnd)
                    ? $assignment->end_date->copy()
                    : $horizonEnd->copy();
                if ($rangeStart->lte($rangeEnd)) {
                    $this->materializeFlexible($assignment, $rangeStart, $rangeEnd, overrideExisting: true);
                }
            }
        }
    }

    /**
     * Roll every active Permanent assignment, for every custom-shifts tenant,
     * forward so the cache always extends to today+horizon. Run daily by
     * `shift:roll-permanent-horizon` — this is what removes the need to ever
     * manually re-assign a standing shift.
     */
    public function rollHorizon(): array
    {
        $totals = ['tenants' => 0, 'assignments' => 0, 'rows' => 0];

        $tenantIds = DB::table('tenants')->where('custom_shifts_enabled', 1)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $assignments = ShiftAssignment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('type', ['permanent', 'rotating'])
                ->where('status', 'active')
                ->get();

            if ($assignments->isEmpty()) {
                continue;
            }

            $totals['tenants']++;

            foreach ($assignments as $assignment) {
                $totals['assignments']++;
                $result = $assignment->type === 'rotating'
                    ? $this->materializeRotatingHorizon($assignment)
                    : $this->materializePermanentHorizon($assignment);
                $totals['rows'] += $result['assigned'];
            }
        }

        return $totals;
    }

    /**
     * Write an additional (2nd+) shift for every date in [from, to]: an extra
     * user_shifts row with is_additional = 1, never touching the primary row.
     * Skips week-offs, dates this assignment already covers, and dates where
     * the shift would overlap one the employee already works
     * (ShiftOverlapGuard) — callers pre-check overlaps so this is a safety net.
     */
    public function materializeAdditional(ShiftAssignment $assignment, Carbon $from, Carbon $to): array
    {
        $result = ['assigned' => 0, 'skipped' => 0, 'already_assigned' => 0, 'duplicate_skipped' => 0, 'week_off_skipped' => 0, 'overlap_skipped' => 0];

        $shift = Shift::withoutGlobalScopes()->find($assignment->shift_id);
        if (!$shift || $from->gt($to)) {
            return $result;
        }

        $userWeekoffs = UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('user_id', $assignment->user_id)
            ->where('status', 1)
            ->get();

        $alreadyCovered = UserShift::withoutGlobalScopes()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('user_id', $assignment->user_id)
            ->where('shift_assignment_id', $assignment->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (WeekOffPredicate::isWeekOff($userWeekoffs, $d)) {
                $result['week_off_skipped']++;
            } elseif ($alreadyCovered->has($d->toDateString())) {
                $result['already_assigned']++;
            } else {
                $dates[] = $d->toDateString();
            }
        }

        $conflicts = $this->overlapGuard->conflicts($assignment->tenant_id, $assignment->user_id, $shift, $dates);

        foreach ($dates as $date) {
            if (isset($conflicts[$date])) {
                $result['overlap_skipped']++;
                continue;
            }

            UserShift::create([
                'tenant_id' => $assignment->tenant_id,
                'user_id' => $assignment->user_id,
                'shift_id' => $assignment->shift_id,
                'shift_assignment_id' => $assignment->id,
                'is_additional' => 1,
                'date' => $date,
                'status' => 'upcoming',
                'created_by' => $assignment->created_by,
            ]);
            $result['assigned']++;
        }

        return $result;
    }

    /**
     * Rotating pattern (e.g. 7×Morning, 7×Evening, 7×Night, or 4 on / 3 off):
     * like a Permanent assignment it is open-ended and cached to the rolling
     * horizon; each day's shift is the pattern step for
     * (date − rotation_anchor_date) mod cycle. A pattern day off is written as
     * a one-day date_based week-off tagged with this assignment, so attendance
     * and payroll treat it as a week-off. Days owned by a Flexible assignment
     * or a one-day override, and hand-entered week-offs, are left alone.
     */
    public function materializeRotatingHorizon(ShiftAssignment $assignment, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $today = Carbon::today();
        $horizonEnd = Carbon::today()->addDays(self::PERMANENT_HORIZON_DAYS);
        $from ??= $assignment->start_date->gt($today) ? $assignment->start_date->copy() : $today->copy();
        if ($from->lt($assignment->start_date)) {
            $from = $assignment->start_date->copy();
        }
        if (! $to) {
            $to = $assignment->end_date && $assignment->end_date->lt($horizonEnd) ? $assignment->end_date->copy() : $horizonEnd->copy();
        }
        $result = ['assigned' => 0, 'days_off' => 0, 'skipped_flexible_owned' => 0];

        $pattern = \App\Models\ShiftRotationPattern::withoutGlobalScopes()->with('steps')->find($assignment->rotation_pattern_id);
        if (! $pattern || $from->gt($to)) {
            return $result;
        }
        $map = $pattern->stepMap();
        $cycle = count($map);
        $anchor = ($assignment->rotation_anchor_date ?? $assignment->start_date)->copy();
        $tenantId = (int) $assignment->tenant_id;
        $userId = (int) $assignment->user_id;

        $manualWeekoffs = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('status', 1)->whereNull('shift_assignment_id')->get();
        $flexible = ShiftAssignment::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('type', 'flexible')->where('is_additional', 0)->where('status', 'active')
            ->where('start_date', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from->toDateString()))
            ->get();
        $rows = UserShift::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->where('is_additional', 0)
            ->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $offRows = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('shift_assignment_id', $assignment->id)
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->get()->keyBy(fn ($w) => Carbon::parse($w->start_date)->toDateString());

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $ds = $d->toDateString();
            $off = $offRows->get($ds);

            if ($this->dateOwnedByFlexible($flexible, $ds)) {
                $off?->delete();
                $result['skipped_flexible_owned']++;
                continue;
            }
            if (WeekOffPredicate::isWeekOff($manualWeekoffs, $d)) {
                continue;
            }

            $shiftId = $map[\App\Models\ShiftRotationPattern::cycleIndex($anchor, $d, $cycle)];
            $row = $rows->get($ds);

            if ($shiftId === null) {
                // Pattern day off: no shift that day, a week-off instead.
                if ($row) {
                    $row->delete();
                }
                if (! $off) {
                    UserWeekoffs::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'shift_assignment_id' => $assignment->id,
                        'off_type' => 'date_based',
                        'start_date' => $ds,
                        'end_date' => $ds,
                        'description' => 'Rotation day off',
                        'status' => 1,
                        'created_by' => $assignment->created_by,
                    ]);
                }
                $result['days_off']++;
                continue;
            }

            $off?->delete();
            $values = ['shift_id' => $shiftId, 'shift_assignment_id' => $assignment->id, 'status' => 'upcoming'];
            if ($row) {
                $row->update($values);
            } else {
                UserShift::create($values + ['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $ds, 'created_by' => $assignment->created_by]);
            }
            $result['assigned']++;
        }

        return $result;
    }

    /** Remove the pattern days off a rotating assignment generated from $from on (rotation ended / replaced). */
    public function clearRotationDaysOff(ShiftAssignment $assignment, Carbon $from): int
    {
        return UserWeekoffs::withoutGlobalScopes()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('shift_assignment_id', $assignment->id)
            ->where('start_date', '>=', $from->toDateString())
            ->delete();
    }

    /** The shift a rotating assignment gives on $date (null = pattern day off). */
    public function rotationShiftOn(ShiftAssignment $assignment, string $date): ?int
    {
        $pattern = \App\Models\ShiftRotationPattern::withoutGlobalScopes()->with('steps')->find($assignment->rotation_pattern_id);
        if (! $pattern) {
            return null;
        }
        $map = $pattern->stepMap();

        return $map[\App\Models\ShiftRotationPattern::cycleIndex(($assignment->rotation_anchor_date ?? $assignment->start_date)->copy(), Carbon::parse($date), count($map))];
    }

    /**
     * Write a one-day override assignment's primary row for $date (update the
     * day's primary row in place, or create it). Unlike materializeFlexible it
     * does not skip week-offs or ask about existing rows — the caller has
     * already decided this day changes. $status null keeps a past row's status
     * and marks today / future rows 'upcoming'.
     */
    public function writeDay(ShiftAssignment $assignment, string $date, ?string $status = null, ?int $shiftId = null): UserShift
    {
        $row = UserShift::withoutGlobalScopes()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('user_id', $assignment->user_id)
            ->where('date', $date)
            ->where('is_additional', 0)
            ->first();

        $future = $date >= Carbon::today()->toDateString();
        $values = [
            'shift_id' => $shiftId ?? $assignment->shift_id,
            'shift_assignment_id' => $assignment->id,
            'status' => $status ?? ($future || ! $row ? 'upcoming' : $row->status),
        ];
        if ($future) {
            // A changed future shift gets its own missed check-in alert.
            $values['missed_checkin_notified'] = 0;
        }

        if ($row) {
            $row->update($values);

            return $row;
        }

        return UserShift::create($values + [
            'tenant_id' => $assignment->tenant_id,
            'user_id' => $assignment->user_id,
            'date' => $date,
            'created_by' => $assignment->created_by,
        ]);
    }

    /**
     * Rebuild ONE day's primary row from whichever active assignment wins it
     * now: the newest one-day override, else the newest Flexible covering the
     * day (not on a week-off), else the active Permanent (not on a week-off).
     * Used when an override is cancelled (request reverted). When nothing wins
     * and $fallbackShiftId is given (the day had a hand-made row with no
     * assignment before the override), that shift is put back as such a row.
     */
    public function rebuildDate(int $tenantId, int $userId, string $date, ?int $fallbackShiftId = null): ?UserShift
    {
        UserShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('date', $date)
            ->where('is_additional', 0)
            ->delete();

        $covering = ShiftAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_additional', 0)
            ->where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->orderByDesc('id')
            ->get();

        $isWeekOff = WeekOffPredicate::isWeekOff(
            UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)->get(),
            Carbon::parse($date)
        );

        $winner = $covering->firstWhere('is_override', true)
            ?? ($isWeekOff ? null : ($covering->first(fn ($a) => $a->type === 'flexible' && ! $a->is_override)
                ?? $covering->first(fn ($a) => in_array($a->type, ['permanent', 'rotating'], true))));

        if ($winner && $winner->type === 'rotating') {
            $shiftId = $this->rotationShiftOn($winner, $date);

            return $shiftId ? $this->writeDay($winner, $date, null, $shiftId) : null;
        }
        if ($winner) {
            return $this->writeDay($winner, $date);
        }

        if ($fallbackShiftId) {
            return UserShift::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'shift_id' => $fallbackShiftId,
                'date' => $date,
                'status' => 'upcoming',
            ]);
        }

        return null;
    }

    private function dateOwnedByFlexible($flexibleAssignments, string $dateString): bool
    {
        foreach ($flexibleAssignments as $fa) {
            $start = $fa->start_date->format('Y-m-d');
            $end = $fa->end_date ? $fa->end_date->format('Y-m-d') : null;
            if ($start <= $dateString && (!$end || $end >= $dateString)) {
                return true;
            }
        }

        return false;
    }
}
