<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Pure pairing/aggregation algorithm for a day's punches. No DB.
 *
 * Turns an arbitrary (possibly out-of-order) list of `in`/`out` punches into
 * a session list plus the day-level scalars (first clock-in, last clock-out,
 * total worked seconds) that AttendanceRollupService feeds into the existing
 * AttendanceEntryService::record() funnel — everything downstream of that
 * (LatePolicyService, AttendanceDayResolver, AttendanceSummaryService,
 * payroll) keeps consuming those same day-level scalars unchanged.
 *
 * For a tenant with allow_multiple_punches=0, the capture-time state machine
 * (AttendancePunchService) never allows more than one session to accumulate,
 * so this always degrades to exactly the single-pair case — one derivation
 * path serves both modes.
 */
class PunchSessionCalculator
{
    public function __construct(private AttendanceCalculator $calc = new AttendanceCalculator())
    {
    }

    /**
     * @param  Collection  $punches  active punches for one (user,date), any order —
     *                               each item needs ->direction ('in'|'out') and ->punched_at
     * @param  mixed  $shift  optional shift for cross-midnight resolution
     *
     * @return array{
     *   sessions: array<int,array{in:Carbon,out:Carbon,worked_seconds:int,in_punch:mixed,out_punch:mixed}>,
     *   first_clock_in: ?Carbon,
     *   last_clock_out: ?Carbon,
     *   worked_seconds: int,
     *   session_count: int,
     *   open_session: bool,
     *   open_in: ?Carbon,
     *   leading_orphan_outs: array<int,mixed>,
     *   orphaned_ins: array<int,mixed>,
     *   needs_review: bool,
     * }
     */
    public function pairSessions(Collection $punches, $shift = null): array
    {
        $sorted = $punches
            ->sortBy(fn ($p) => Carbon::parse($p->punched_at)->getTimestamp())
            ->values();

        $sessions = [];
        $leadingOrphanOuts = [];
        $orphanedIns = [];
        $openIn = null;

        foreach ($sorted as $punch) {
            $at = Carbon::parse($punch->punched_at);

            if ($punch->direction === 'in') {
                if ($openIn !== null) {
                    // Two `in`s with no `out` between them — the earlier one
                    // needs review; the later one is closer to when work
                    // actually began, so it becomes the new session start.
                    $orphanedIns[] = $openIn['punch'];
                }
                $openIn = ['at' => $at, 'punch' => $punch];
                continue;
            }

            // direction === 'out'
            if ($openIn === null) {
                // Biometric replay / clock skew — no in-progress session to
                // close. Excluded from worked-hours math, never dropped.
                $leadingOrphanOuts[] = $punch;
                continue;
            }

            $resolvedOut = $this->calc->resolveClockOut($openIn['at'], $at, $shift);
            $sessions[] = [
                'in' => $openIn['at'],
                'out' => $resolvedOut,
                'worked_seconds' => $this->calc->workedSeconds($openIn['at'], $resolvedOut),
                'in_punch' => $openIn['punch'],
                'out_punch' => $punch,
            ];
            $openIn = null;
        }

        $openSession = $openIn !== null;
        $workedSeconds = array_sum(array_column($sessions, 'worked_seconds'));

        $firstClockIn = $sessions[0]['in'] ?? ($openSession ? $openIn['at'] : null);
        $firstInPunch = $sessions[0]['in_punch'] ?? ($openSession ? $openIn['punch'] : null);

        // The day's "clock_out" is the FINAL session's out — null while a
        // session is still open, even if earlier sessions already closed.
        // This preserves `clock_out IS NULL` as "still clocked in today"
        // everywhere that invariant is relied on (mobile lookups, biometric
        // debounce, reports), for both single- and multi-session days.
        $lastClockOut = (!$openSession && count($sessions)) ? $sessions[count($sessions) - 1]['out'] : null;
        $lastOutPunch = (!$openSession && count($sessions)) ? $sessions[count($sessions) - 1]['out_punch'] : null;

        return [
            'sessions' => $sessions,
            'first_clock_in' => $firstClockIn,
            'first_in_punch' => $firstInPunch,
            'last_clock_out' => $lastClockOut,
            'last_out_punch' => $lastOutPunch,
            'worked_seconds' => $workedSeconds,
            'session_count' => count($sessions) + ($openSession ? 1 : 0),
            'open_session' => $openSession,
            'open_in' => $openSession ? $openIn['at'] : null,
            'leading_orphan_outs' => $leadingOrphanOuts,
            'orphaned_ins' => $orphanedIns,
            'needs_review' => !empty($leadingOrphanOuts) || !empty($orphanedIns),
        ];
    }

    /**
     * Day-level scalars derived from pairSessions() output, ready to merge
     * into AttendanceEntryService::record()'s $columns array. Late minutes
     * are measured from the FIRST clock-in, early-departure from the LAST
     * clock-out — for a single-session day (allow_multiple_punches=0) this is
     * exactly today's behaviour; for a multi-session day it's the natural
     * generalisation.
     *
     * @param  array  $paired  pairSessions() output
     */
    public function summarize(array $paired, $shift = null, ?CarbonInterface $scheduledStart = null, ?CarbonInterface $scheduledEnd = null): array
    {
        $workedSeconds = $paired['worked_seconds'];

        $lateMinutes = ($shift && $scheduledStart && $paired['first_clock_in'])
            ? $this->calc->lateMinutes($shift, $scheduledStart, $paired['first_clock_in'])
            : 0;

        $earlyMinutes = (!$paired['open_session'] && $shift && $scheduledEnd && $paired['last_clock_out'])
            ? $this->calc->earlyDepartureMinutes($shift, $scheduledEnd, $paired['last_clock_out'])
            : 0;

        $overtimeMinutes = 0;
        if (!$paired['open_session'] && $shift && $scheduledEnd && $paired['last_clock_out']
            && $paired['last_clock_out']->greaterThan($scheduledEnd)) {
            $overtimeMinutes = (int) $scheduledEnd->diffInMinutes($paired['last_clock_out']);
        }

        // Byte-identical to the existing single-session mobile flow: late is
        // decided at clock-in, then overridden by early/overtime once the
        // last session of the day closes; otherwise the late/present call
        // stands. A day with no clock-in yet (shouldn't happen once a punch
        // is stored, but defensively) falls back to 'present'.
        $status = $lateMinutes > 0 ? 'late' : 'present';
        if (!$paired['open_session'] && $paired['last_clock_out']) {
            if ($earlyMinutes > 0) {
                $status = 'early_departure';
            } elseif ($overtimeMinutes > 0) {
                $status = 'overtime';
            }
        }

        return [
            'clock_in' => $paired['first_clock_in']?->format('Y-m-d H:i:s'),
            'clock_out' => $paired['last_clock_out']?->format('Y-m-d H:i:s'),
            'worked_hours' => $this->calc->decimalHours($workedSeconds),
            'total_hours' => $this->calc->formatDuration($workedSeconds),
            'late_minutes' => $lateMinutes,
            'early_departure_minutes' => $earlyMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'attendance_status' => $paired['first_clock_in'] ? $status : null,
            'session_count' => $paired['session_count'],
        ];
    }
}
