<?php

namespace App\Services\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The one place "what is this employee's status on this day" is decided.
 *
 * Extracted from AttendanceSummaryService so every consumer (summary writer,
 * team screens, reports, payroll, performance) can classify a day the same way
 * instead of re-implementing the precedence with subtle drift.
 *
 * Pure: no DB. Callers pass the already-loaded context for the day.
 */
class AttendanceDayResolver
{
    public function __construct(private AttendanceCalculator $calc = new AttendanceCalculator())
    {
    }

    /**
     * @param iterable $dayRows      attendance rows for this (user, date) — normally 1
     * @param iterable $leaves       approved leave rows overlapping the period (objects with
     *                               start_date, end_date, start_session, end_session, id)
     * @param array    $leaveDetail  [leave_id => 'paid'|'unpaid'|'mixed']
     * @param bool     $isHoliday    a tenant holiday falls on this date
     * @param bool     $isWeekoff    the user's week-off pattern hits this date
     * @param string   $date         'Y-m-d'
     *
     * @return array{
     *   token:string, worked_hours:float, worked_seconds:int, late_minutes:int,
     *   early_departure_minutes:int, overtime_minutes:int, expected_seconds:int,
     *   day_fraction:float, is_manual:bool, is_regularized:bool, has_row:bool
     * }
     */
    public function resolve(
        iterable $dayRows,
        iterable $leaves,
        array $leaveDetail,
        bool $isHoliday,
        bool $isWeekoff,
        string $date,
        ?AttendancePolicySnapshot $policy = null
    ): array {
        $policy ??= AttendancePolicySnapshot::default();
        $agg = $this->aggregate($dayRows);
        $today = Carbon::today();
        $current = Carbon::parse($date);

        $mk = fn (string $token, float $fraction) => [
            'token' => $token,
            'worked_hours' => $agg['worked_hours'],
            'worked_seconds' => $agg['worked_seconds'],
            'late_minutes' => $agg['late_minutes'],
            'early_departure_minutes' => $agg['early_departure_minutes'],
            'overtime_minutes' => $agg['overtime_minutes'],
            'expected_seconds' => $agg['expected_seconds'],
            'day_fraction' => $fraction,
            'is_manual' => $agg['is_manual'],
            'is_regularized' => $agg['is_regularized'],
            'has_row' => $agg['has_row'],
        ];

        // Future day, nothing done yet.
        if ($current->gt($today) && !$agg['has_row']) {
            return $mk('upcoming', 1.00);
        }

        // Tenant holiday / week-off pattern — a worked holiday still reads as
        // "holiday"; the caller decides how to count holiday_work.
        if ($isHoliday) {
            return $mk('holiday', 0.00);
        }
        if ($isWeekoff) {
            return $mk('week_off', 0.00);
        }

        // A per-employee holiday / week-off / leave / absent set by hand.
        $persisted = $agg['persisted_status'];
        if ($persisted === 'holiday') {
            return $mk('holiday', 0.00);
        }
        if ($persisted === 'weekoff') {
            return $mk('week_off', 0.00);
        }
        if ($persisted === 'on_leave') {
            $detail = $this->leaveDetailFor($current, $leaves, $leaveDetail);
            return $mk($detail === 'unpaid' ? 'unpaid_leave' : 'paid_leave', 0.00);
        }
        if ($persisted === 'first_half_leave') {
            return $mk('first_half_leave', 0.50);
        }
        if ($persisted === 'second_half_leave') {
            return $mk('second_half_leave', 0.50);
        }
        if ($persisted === 'absent') {
            return $mk('absent', 0.00);
        }

        // Completed work — LatePolicyService's effective_status wins for the
        // present / half_day / late distinction.
        if ($agg['completed'] && $agg['worked_hours'] > 0) {
            $token = match ($agg['effective_status']) {
                'half_day' => 'half_day',
                'present' => 'present',
                'late' => 'late',
                'overtime' => 'overtime',
                'early_departure' => 'early_departure',
                'absent' => 'absent',
                default => $this->classifyWorked($agg['classify_hours'], $agg['expected_seconds'], $policy),
            };
            $fraction = in_array($token, ['half_day'], true) ? 0.50 : ($token === 'absent' ? 0.00 : 1.00);
            return $mk($token, $fraction);
        }

        // Punched in but never out.
        if ($agg['has_clock_in'] && !$agg['has_clock_out']) {
            return $mk('checked_in_only', 1.00);
        }

        // Approved leave covering this date (before treating an empty row as absent).
        foreach ($leaves as $leave) {
            if (!$current->betweenIncluded(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                continue;
            }
            $half = $this->leaveHalfToken($leave);
            if ($half) {
                return $mk($half, 0.50);
            }
            $detail = $leaveDetail[$leave->id] ?? 'paid';
            return $mk($detail === 'unpaid' ? 'unpaid_leave' : 'paid_leave', 0.00);
        }

        return $mk('absent', 0.00);
    }

    /**
     * Worked-hours -> present/half_day/absent against a scheduled expectation,
     * falling back to an absolute-hours ladder when no shift is known.
     */
    public function classifyWorked(float $workedHours, int $expectedSeconds, ?AttendancePolicySnapshot $policy = null): string
    {
        return ($policy ?? AttendancePolicySnapshot::default())
            ->classify($workedHours, $expectedSeconds);
    }

    // ------------------------------------------------------------------

    private function aggregate(iterable $rows): array
    {
        $totalSeconds = 0;
        $classifySeconds = 0;
        $late = 0;
        $overtime = 0;
        $early = 0;
        $expected = 0;
        $completed = false;
        $hasClockIn = false;
        $hasClockOut = false;
        $hasRow = false;
        $isManual = false;
        $isRegularized = false;
        $effectiveStatus = null;
        $persistedStatus = null;
        $max = AttendanceCalculator::MAX_SESSION_SECONDS;

        foreach ($rows as $entry) {
            $hasRow = true;
            if (!empty($entry->clock_in)) {
                $hasClockIn = true;
            }
            if (!empty($entry->clock_out)) {
                $hasClockOut = true;
            }

            if (!empty($entry->clock_in) && !empty($entry->clock_out)) {
                $seconds = $this->calc->workedSeconds(
                    Carbon::parse($entry->clock_in),
                    Carbon::parse($entry->clock_out)
                );
                // Multi-shift day: first-in → last-out spans the gap between
                // shifts; the punched sessions' own total is the work done.
                if ((int) ($entry->shift_count ?? 0) > 1 && isset($entry->worked_hours)) {
                    $seconds = (int) round(((float) $entry->worked_hours) * 3600);
                }
                if ($seconds > $max) {
                    Log::warning('AttendanceDayResolver: session exceeds 48h, clamping', [
                        'entry_id' => $entry->id ?? null,
                    ]);
                    $seconds = $max;
                }
                if ($seconds > 0) {
                    $totalSeconds += $seconds;
                    // Multi-shift: only the primary shift's work classifies the day.
                    $classifySeconds += $this->calc->primaryWorkedSeconds($entry, $seconds);
                    $completed = true;
                }
            }

            $late += abs((int) ($entry->late_minutes ?? 0));
            $overtime += abs((int) ($entry->overtime_minutes ?? 0));
            $early += abs((int) ($entry->early_departure_minutes ?? 0));

            if (!empty($entry->scheduled_shift_start) && !empty($entry->scheduled_shift_end)) {
                $expected = max($expected, $this->calc->expectedWorkSeconds([
                    'start_time' => $entry->scheduled_shift_start,
                    'end_time' => $entry->scheduled_shift_end,
                ]));
            }

            if (!empty($entry->is_regularized)) {
                $isRegularized = true;
            }

            if (!empty($entry->effective_status)) {
                $effectiveStatus = $entry->effective_status;
            }

            $rowIsManual = (($entry->attendance_type ?? null) === 'manual') || !empty($entry->marked_by ?? null);
            if ($rowIsManual) {
                $isManual = true;
            }
            $rowStatus = $rowIsManual
                ? ($entry->attendance_status ?? null)
                : ($entry->effective_status ?? null);
            if (!empty($rowStatus)) {
                $persistedStatus = $rowStatus;
            }
        }

        return [
            'worked_hours' => $this->calc->decimalHours($totalSeconds),
            'worked_seconds' => $totalSeconds,
            'classify_hours' => $this->calc->decimalHours($classifySeconds),
            'late_minutes' => $late,
            'overtime_minutes' => $overtime,
            'early_departure_minutes' => $early,
            'expected_seconds' => $expected,
            'completed' => $completed,
            'has_clock_in' => $hasClockIn,
            'has_clock_out' => $hasClockOut,
            'has_row' => $hasRow,
            'is_manual' => $isManual,
            'is_regularized' => $isRegularized,
            'effective_status' => $effectiveStatus,
            'persisted_status' => $persistedStatus,
        ];
    }

    private function leaveHalfToken($leave): ?string
    {
        $s = $leave->start_session ?? null;
        $e = $leave->end_session ?? null;
        if ($s === 'session1' && $e === 'session1') {
            return 'first_half_leave';
        }
        if ($s === 'session2' && $e === 'session2') {
            return 'second_half_leave';
        }
        // numeric variants used in older rows
        if ((string) $s === '1' && (string) $e === '1') {
            return 'first_half_leave';
        }
        if ((string) $s === '2' && (string) $e === '2') {
            return 'second_half_leave';
        }
        return null;
    }

    private function leaveDetailFor(Carbon $date, iterable $leaves, array $leaveDetail): string
    {
        foreach ($leaves as $leave) {
            if ($date->betweenIncluded(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                return $leaveDetail[$leave->id] ?? 'paid';
            }
        }
        return 'paid';
    }
}
