<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Feature B — per-tenant monthly late-arrival allowance.
 *
 * Sole writer of attendances.effective_status / day_fraction / policy_note.
 *
 * recalculateMonth() is deterministic and idempotent: it derives everything
 * from the raw attendance rows + the tenant policy, so it can be re-run any
 * number of times (after a clock-in, after a regularization that clears a
 * late, nightly) and always converges to the same result.
 */
class LatePolicyService
{
    public function __construct(
        private AttendanceCalculator $calc,
        private PolicyResolver $policies,
    ) {
    }

    /**
     * Recompute effective_status / day_fraction for every attendance row of one
     * user in one calendar month.
     */
    public function recalculateMonth(int $userId, int $tenantId, string $yearMonth): void
    {
        // Each day is graded with the policy in force on that day (classifyRows),
        // so a policy change applies from its effective date onward and never
        // re-grades days before it. The month policy is only a fallback.
        $policy = $this->policies->forUserMonth($tenantId, $userId, $yearMonth);
        $rows = $this->monthRows($tenantId, $userId, $yearMonth);

        DB::transaction(function () use ($rows, $policy) {
            $this->applyRows($rows, $policy);
        });
    }

    /**
     * Pure, side-effect-free: how many late/early days this user-month
     * exceeded its allowance by — independent of whether either
     * attendance-action is even enabled. Safe to call from payroll
     * regardless (zero writes to `attendances`), which is what lets the
     * payroll deduction and the attendance-status action stay fully
     * independent by construction.
     *
     * Each excess day is judged — and later priced by the payroll deduction —
     * with the policy in force on that day (`lateExcessDays` / `earlyExcessDays`
     * hold those day policies), so a rule change mid-month applies from its date.
     *
     * @return array{lateSeen:int, earlySeen:int, lateExcess:int, earlyExcess:int, lateExcessDays:array, earlyExcessDays:array}
     */
    public function excessCounts(int $userId, int $tenantId, string $yearMonth): array
    {
        $policy = $this->policies->forUserMonth($tenantId, $userId, $yearMonth);
        $rows = $this->monthRows($tenantId, $userId, $yearMonth);
        $classified = $this->classifyRows($rows, $policy);

        $lateSeen = 0;
        $earlySeen = 0;
        $lateExcessDays = [];
        $earlyExcessDays = [];

        foreach ($classified as $c) {
            $day = $c['policy'];
            $date = substr((string) $c['row']->date, 0, 10);
            if ($c['is_late']) {
                $lateSeen++;
                if ($lateSeen > $day->monthlyLateAllowance) {
                    $lateExcessDays[$date] = $day;
                }
            }
            if ($c['is_early']) {
                $earlySeen++;
                if ($earlySeen > $day->monthlyEarlyAllowance) {
                    $earlyExcessDays[$date] = $day;
                }
            }
        }

        return [
            'lateSeen' => $lateSeen,
            'earlySeen' => $earlySeen,
            'lateExcess' => count($lateExcessDays),
            'earlyExcess' => count($earlyExcessDays),
            'lateExcessDays' => $lateExcessDays,
            'earlyExcessDays' => $earlyExcessDays,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    private function monthRows(int $tenantId, int $userId, string $yearMonth)
    {
        $start = Carbon::parse($yearMonth . '-01')->startOfMonth()->format('Y-m-d');
        $end = Carbon::parse($yearMonth . '-01')->endOfMonth()->format('Y-m-d');

        return DB::table('attendances')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('clock_in')
            ->get();
    }

    /**
     * Per-row late/early classification, shared by applyRows() (writes
     * attendance-status side effects) and excessCounts() (read-only, for
     * payroll) — the single source of truth for "which days count, and
     * which of those exceed the monthly allowance," so the two toggles can
     * never disagree about which days are in excess.
     *
     * Reuses the existing attendances.late_minutes/early_departure_minutes
     * columns (already computed once, correctly, against the shift's own
     * grace at punch time) re-checked against the tenant policy's second
     * grace gate — exactly how isLate() already works today, just with a
     * swappable second-gate value (graceMinutesFor()).
     *
     * @param  \Illuminate\Support\Collection  $rows
     * @return array<int, array{row:object, base:string, fraction:float, is_late:bool, is_early:bool}>
     */
    private function classifyRows($rows, AttendancePolicySnapshot $policy): array
    {
        $shiftGrace = DB::table('shifts')
            ->whereIn('id', $rows->pluck('shift_id')->filter()->unique())
            ->pluck('grace_minutes', 'id');

        return $rows->mapWithKeys(function ($row) use ($policy, $shiftGrace) {
            // Grading a single day (Day Classification on/off, present / half-day
            // ratios) follows the policy in force ON that day, so switching Day
            // Classification off mid-month applies from that date. The monthly
            // late / early allowance below stays anchored to the month's policy.
            // Late Arrival / Early Leaving rules (grace, allowance, action,
            // payroll deduction) likewise come from the day's own policy, so
            // a change saved on the 9th applies from the 9th — not next month.
            $dayPolicy = $this->policies->forUserDate((int) $row->tenant_id, (int) $row->user_id, substr((string) $row->date, 0, 10));
            [$base, $fraction] = $this->baseStatus($row, $dayPolicy);

            // A row baseStatus() already resolved to something other than
            // present/late/overtime (manual, leave, holiday, weekoff,
            // absent, half_day) never gets late/early evaluated — matches
            // today's exact pass-through behaviour.
            $isPassthrough = ! in_array($base, ['present', 'late', 'overtime'], true);
            $grace = $isPassthrough ? null : $dayPolicy->graceMinutesFor($shiftGrace[$row->shift_id] ?? null);

            $isLate = ! $isPassthrough && (($base === 'late') || ((int) ($row->late_minutes ?? 0)) > $grace);
            $isEarly = ! $isPassthrough && ((int) ($row->early_departure_minutes ?? 0)) > $grace;

            return [$row->id => [
                'row' => $row,
                'base' => $base,
                'fraction' => $fraction,
                'policy' => $dayPolicy,
                'is_late' => $isLate && ! $row->is_regularized,
                'is_early' => $isEarly && ! $row->is_regularized,
            ]];
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection  $rows
     */
    private function applyRows($rows, AttendancePolicySnapshot $policy): void
    {
        $classified = $this->classifyRows($rows, $policy);
        $lateSeen = 0;
        $earlySeen = 0;

        foreach ($classified as $c) {
            $row = $c['row'];
            $effective = $c['base'];
            $fraction = $c['fraction'];
            $note = null;
            // The day's own rules; the running counts ($lateSeen / $earlySeen) stay monthly.
            $policy = $c['policy'];

            // A regularized row is no longer late/early — it does not
            // consume either allowance and is never downgraded (already
            // reflected in is_late/is_early via classifyRows()).
            if ($c['is_late']) {
                $lateSeen++;

                if ($policy->lateAttendanceAction !== 'none' && $lateSeen > $policy->monthlyLateAllowance) {
                    [$effective, $fraction] = $this->outcomeFor($policy->lateAttendanceAction);
                    $note = "late #{$lateSeen} exceeds monthly allowance of {$policy->monthlyLateAllowance} → {$policy->lateAttendanceAction}";
                } elseif ($policy->lateAttendanceAction !== 'none') {
                    $note = "late #{$lateSeen} within monthly allowance of {$policy->monthlyLateAllowance}";
                }
            }

            // Early check only changes attendance-status if the day wasn't
            // already downgraded by the late rule above (a day has one
            // effective_status; late wins on a same-day conflict — a day
            // that's in excess on both rules converges to the more severe
            // outcome naturally, since downgrade only ever moves
            // present→half_day→absent, never back). The early EXCESS COUNT
            // is still tracked independently either way in excessCounts() —
            // the payroll deduction never consults $effective.
            if ($c['is_early']) {
                $earlySeen++;

                if ($effective === $c['base'] && $policy->earlyAttendanceAction !== 'none' && $earlySeen > $policy->monthlyEarlyAllowance) {
                    [$effective, $fraction] = $this->outcomeFor($policy->earlyAttendanceAction);
                    $note = ($note ? $note.' | ' : '')."early-leaving #{$earlySeen} exceeds monthly allowance of {$policy->monthlyEarlyAllowance} → {$policy->earlyAttendanceAction}";
                } elseif ($policy->earlyAttendanceAction !== 'none') {
                    $note = ($note ? $note.' | ' : '')."early-leaving #{$earlySeen} within monthly allowance of {$policy->monthlyEarlyAllowance}";
                }
            }

            DB::table('attendances')
                ->where('id', $row->id)
                ->update([
                    'effective_status' => $effective,
                    'day_fraction' => $fraction,
                    'policy_note' => $note,
                    'updated_at' => now(),
                ]);
        }
    }

    /** @return array{0:string,1:float} */
    private function outcomeFor(string $action): array
    {
        return match ($action) {
            'half_day' => ['half_day', 0.50],
            'absent' => ['absent', 0.00],
            default => ['present', 1.00],
        };
    }

    /**
     * Raw day status + day fraction for a single attendance row, before the
     * late allowance is applied.
     *
     * @return array{0:string,1:float}  [status, day_fraction]
     */
    private function baseStatus($row, AttendancePolicySnapshot $policy): array
    {
        $status = $row->attendance_status;

        // A row set by hand (admin / HR / manager via the mark-attendance
        // screen) is authoritative — never reclassify it from worked hours.
        $isManual = ($row->attendance_type === 'manual') || !empty($row->marked_by);
        if ($isManual) {
            return match ($status) {
                'on_leave', 'absent', 'holiday', 'weekoff' => [$status, 0.00],
                'half_day', 'first_half_leave', 'second_half_leave' => [$status, 0.50],
                default => [$status ?: 'present', 1.00],
            };
        }

        // Auto-captured leave markings pass straight through too.
        if ($status === 'on_leave') {
            return ['on_leave', 0.00];
        }
        if (in_array($status, ['first_half_leave', 'second_half_leave'], true)) {
            return [$status, 0.50];
        }
        if ($status === 'absent') {
            return ['absent', 0.00];
        }

        $hasWork = $row->clock_in && $row->clock_out;

        if (!$hasWork) {
            // Incomplete / open row — leave the raw status, no worked fraction.
            return [$status ?: 'absent', $status === 'absent' ? 0.00 : 1.00];
        }

        $seconds = $this->calc->workedSeconds(Carbon::parse($row->clock_in), Carbon::parse($row->clock_out));
        $seconds = min($seconds, AttendanceCalculator::MAX_SESSION_SECONDS);
        // Multi-shift day: the primary shift alone decides present/half/absent.
        $seconds = $this->calc->primaryWorkedSeconds($row, $seconds);
        $hours = $seconds / 3600;

        $expected = 0;
        if ($row->scheduled_shift_start && $row->scheduled_shift_end) {
            $expected = $this->calc->expectedWorkSeconds([
                'start_time' => $row->scheduled_shift_start,
                'end_time' => $row->scheduled_shift_end,
            ]);
        }

        $class = $policy->classify($hours, $expected);

        if ($class === 'absent') {
            return ['absent', 0.00];
        }
        if ($class === 'half_day') {
            return ['half_day', 0.50];
        }

        // Full day worked — was the arrival late (past the grace window)?
        $isLate = ($status === 'late') || $policy->isLate((int) ($row->late_minutes ?? 0));

        return [$isLate ? 'late' : ($status === 'overtime' ? 'overtime' : 'present'), 1.00];
    }
}
