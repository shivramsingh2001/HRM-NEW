<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ShiftWindow;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared helpers of the attendance report controllers (day status from hours / a
 * hand-set or policy-resolved status, a user's shift, minutes ⇄ HH:MM). Moved out of
 * AttendanceReportController unchanged (code-quality plan, Phase 3). NOTE: the Team
 * pages have their own, slightly different copy (TeamAttendanceStatus) — see docs.
 */
trait AttendanceReportHelpers
{
    private function getAttendanceStatusByShift($totalHours, $userId, $date, $attendance = null)
    {
        // A hand-set (admin/HR/manager) or late-policy-resolved status is
        // authoritative — never recompute it from worked hours.
        if ($attendance) {
            $persisted = $this->persistedDayStatus($attendance);
            if ($persisted !== null) {
                return $persisted;
            }
        }

        $tenantId = session('tenant_id');
        $shiftStart = null;
        $shiftEnd = null;
        $expectedHours = null;

        // ============================================================
        // PRIORITY 1: Use scheduled shift times from attendance record
        // ============================================================
        if ($attendance && isset($attendance->scheduled_shift_start) && $attendance->scheduled_shift_start &&
            isset($attendance->scheduled_shift_end) && $attendance->scheduled_shift_end) {
            // Overnight shifts (e.g. 22:00 to 06:00) end on the next day.
            $expectedHours = ShiftWindow::spanMinutes($attendance->scheduled_shift_start, $attendance->scheduled_shift_end) / 60;
        }

        // ============================================================
        // PRIORITY 2: If attendance doesn't have shift times, get from shifts table
        // ============================================================
        if ($expectedHours === null || $expectedHours <= 0) {
            $userShiftData = $this->getUserShiftForDate($userId, $date, $tenantId);

            if ($userShiftData) {
                $shift = DB::table('shifts')
                    ->where('id', $userShiftData['shift_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->first();

                if ($shift) {
                    // Overnight shifts (e.g. 22:00 to 06:00) end on the next day.
                    $expectedHours = ShiftWindow::spanMinutes($shift->start_time, $shift->end_time, ShiftWindow::isOvernight($shift)) / 60;
                }
            }
        }

        // ============================================================
        // PRIORITY 3: classify with the tenant's attendance policy (ratios +
        // Day Classification switch), same as Attendance summary and Payroll.
        // No shift found anywhere => the policy's absolute-hours fallback.
        // ============================================================
        $expectedSeconds = ($shiftStart && $shiftEnd && $expectedHours > 0)
            ? (int) $shiftStart->diffInSeconds($shiftEnd)
            : 0;
        $policy = app(\App\Services\Attendance\PolicyResolver::class)
            ->forUserDate((int) $tenantId, (int) $userId, Carbon::parse($date)->format('Y-m-d'));

        return match ($policy->classify((float) ($totalHours ?? 0), $expectedSeconds)) {
            'present' => 'Present',
            'half_day' => 'Halfday',
            default => 'Absent',
        };
    }

    /**
     * Display status for a row whose status was set by hand or resolved by the
     * late-allowance policy. Null => fall through to the hours-based calc.
     */
    private function persistedDayStatus($attendance): ?string
    {
        $isManual = (($attendance->attendance_type ?? null) === 'manual')
            || ! empty($attendance->marked_by ?? null);

        // An auto row clocked into but not out of stays "checked in only".
        if (! $isManual && ! empty($attendance->clock_in ?? null) && empty($attendance->clock_out ?? null)) {
            return null;
        }

        $status = $isManual
            ? ($attendance->attendance_status ?? null)
            : ($attendance->effective_status ?? null);

        return match ($status) {
            'present', 'late', 'overtime', 'early_departure' => 'Present',
            'half_day' => 'Halfday',
            'absent' => 'Absent',
            'on_leave' => 'Full Day Leave',
            'first_half_leave' => 'First Half Leave',
            'second_half_leave' => 'Second Half Leave',
            'holiday' => 'Holiday',
            'weekoff' => 'Week Off',
            default => null,
        };
    }

    /**
     * The hand-set / policy-resolved status as one of the report's own status
     * tokens (present, halfday, absent, full_day_leave, first_half_leave,
     * second_half_leave, holiday, week_off). Null => fall through to the
     * clock/holiday/leave/weekoff logic.
     */
    private function persistedReportToken($attendance): ?string
    {
        return match ($this->persistedDayStatus($attendance)) {
            'Present' => 'present',
            'Halfday' => 'halfday',
            'Absent' => 'absent',
            'Full Day Leave' => 'full_day_leave',
            'First Half Leave' => 'first_half_leave',
            'Second Half Leave' => 'second_half_leave',
            'Holiday' => 'holiday',
            'Week Off' => 'week_off',
            default => null,
        };
    }

    /**
     * Get user's shift for a specific date
     */
    private function getUserShiftForDate($userId, $date, $tenantId)
    {
        try {
            // The day's primary shift (fixed company shift when custom shifts are
            // off) — one implementation in TenantShiftResolver.
            return app(\App\Services\Attendance\TenantShiftResolver::class)
                ->detailsForUserDate((int) $userId, (int) $tenantId, $date);
        } catch (Exception $e) {
            Log::error('Error getting user shift: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Helper: Convert time to minutes
     */
    private function timeToMinutes($time)
    {
        if (empty($time)) {
            return 0;
        }

        if (strpos($time, ':') !== false) {
            $parts = explode(':', $time);
            $hours = (int) $parts[0];
            $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

            return ($hours * 60) + $minutes;
        }

        return (float) $time * 60;
    }

    /**
     * Helper: Convert minutes to time format
     */
    private function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
