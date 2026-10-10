<?php

namespace App\Http\Controllers\Api\Attendance\Concerns;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the mobile attendance API controllers: the biometric-only block and the
 * shift info shown with a punch. Moved out of Api\Attendance\AttendanceController
 * unchanged (code-quality plan, Phase 4).
 */
trait MobileAttendanceHelpers
{
    private const BIOMETRIC_ONLY_MESSAGE = 'Your attendance is recorded by the biometric machine. Punch in/out from the app is disabled.';

    /**
     * Employee set to attendance type "biometric_only": punches come only from
     * the terminal, so app clock-in/out is refused. Only enforced while the
     * tenant still has the biometric add-on — if it is removed, the employee
     * falls back to app punching instead of having no way to mark attendance.
     */
    private function mobilePunchBlocked(int $userId): bool
    {
        $row = DB::table('users')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->where('users.id', $userId)
            ->select('users.tenant_id', 'user_job_details.attendance_type')
            ->first();

        if (! $row || $row->attendance_type !== 'biometric_only') {
            return false;
        }

        return app(\App\Services\FeatureService::class)
            ->enabled((int) $row->tenant_id, 'attendance_biometric');
    }

    /**
     * {user_shift_id, shift_id, name, start_time, end_time, is_additional} of
     * the shift a punch was matched to, or null (fixed company shift / none).
     */
    private function punchShiftInfo($punch): ?array
    {
        $userShiftId = $punch->user_shift_id ?? null;
        if (! $userShiftId) {
            return null;
        }

        $row = DB::table('user_shifts')
            ->join('shifts', 'shifts.id', '=', 'user_shifts.shift_id')
            ->where('user_shifts.id', $userShiftId)
            ->first(['user_shifts.id', 'user_shifts.is_additional', 'shifts.id as shift_id', 'shifts.name', 'shifts.start_time', 'shifts.end_time']);

        return $row ? [
            'user_shift_id' => (int) $row->id,
            'shift_id' => (int) $row->shift_id,
            'name' => $row->name,
            'start_time' => date('h:i A', strtotime($row->start_time)),
            'end_time' => date('h:i A', strtotime($row->end_time)),
            'is_additional' => (bool) $row->is_additional,
        ] : null;
    }
}
