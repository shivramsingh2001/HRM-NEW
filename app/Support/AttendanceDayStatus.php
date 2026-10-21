<?php

namespace App\Support;

/**
 * The graded status of a completed attendance day, in the mobile API's
 * day_status labels ("Present" / "Half Day" / "Absent" / leave labels).
 *
 * Same reading as the Team page (TeamAttendanceStatus::persistedDayStatus()):
 * a hand-marked row → attendance_status, any other row → effective_status, the
 * value LatePolicyService writes after the present ratio and the late /
 * early-leaving allowances. Null when the row has no graded status yet, so the
 * caller keeps its own hours-based fallback (legacy rows).
 */
class AttendanceDayStatus
{
    public static function label(?string $effectiveStatus, ?string $attendanceStatus, ?string $attendanceType, $markedBy): ?string
    {
        $isManual = $attendanceType === 'manual' || ! empty($markedBy);

        return match ($isManual ? $attendanceStatus : $effectiveStatus) {
            'present', 'late', 'overtime', 'early_departure' => 'Present',
            'half_day' => 'Half Day',
            'absent' => 'Absent',
            'on_leave' => 'Full Day Leave',
            'first_half_leave' => 'First Half Leave',
            'second_half_leave' => 'Second Half Leave',
            default => null,
        };
    }

    /** label() for an attendances row (null = not graded yet). */
    public static function forRow($attendance): ?string
    {
        return $attendance ? self::label($attendance->effective_status ?? null, $attendance->attendance_status ?? null,
            $attendance->attendance_type ?? null, $attendance->marked_by ?? null) : null;
    }

    /**
     * forRow(), or — for a clocked-out day that was never graded (e.g. old data
     * merged in before grading existed) — the worked hours classified against the
     * company's attendance policy (present / half-day thresholds, Day Classification
     * switch), the same fallback as the web Team page
     * (TeamAttendanceStatus::getAttendanceStatusByShift()).
     */
    public static function forRowOrHours($attendance): string
    {
        $graded = self::forRow($attendance);
        if ($graded !== null) {
            return $graded;
        }

        $seconds = $attendance->worked_hours !== null
            ? (int) round(((float) $attendance->worked_hours) * 3600)
            : max(0, \Carbon\Carbon::parse($attendance->clock_out)->diffInSeconds(\Carbon\Carbon::parse($attendance->clock_in), true));
        // Multi-shift day: the normal shift alone decides (extra shifts are paid separately).
        $seconds = app(\App\Services\Attendance\AttendanceCalculator::class)->primaryWorkedSeconds($attendance, $seconds);
        $expected = $attendance->scheduled_shift_start && $attendance->scheduled_shift_end
            ? ShiftWindow::spanMinutes($attendance->scheduled_shift_start, $attendance->scheduled_shift_end) * 60
            : 0; // 0 => the policy's absolute-hours fallback

        $policy = app(\App\Services\Attendance\PolicyResolver::class)
            ->forUserDate((int) $attendance->tenant_id, (int) $attendance->user_id, substr((string) $attendance->date, 0, 10));

        return match ($policy->classify($seconds / 3600, $expected)) {
            'present' => 'Present',
            'half_day' => 'Half Day',
            default => 'Absent',
        };
    }

    /** Summary / count key for a mobile day_status label (team-view counts, team-profile attendance_summary). */
    public static function summaryKey(string $label): string
    {
        return match ($label) {
            'Half Day' => 'half_day',
            'Absent' => 'absent',
            'Full Day Leave', 'First Half Leave', 'Second Half Leave' => 'on_leave',
            default => 'present',
        };
    }
}
