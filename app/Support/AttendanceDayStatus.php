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
}
