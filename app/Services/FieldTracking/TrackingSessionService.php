<?php

namespace App\Services\FieldTracking;

use App\Models\AttendancePunch;
use App\Models\AttendanceTrackingSession;

/**
 * Opens/closes attendance_tracking_sessions in reaction to punch events —
 * does not duplicate any pairing logic, AttendancePunchService/
 * PunchSessionCalculator already own that. Called once at the end of
 * AttendancePunchService::capture(), after its own idempotency/debounce/
 * open-session gating has already run, so a duplicate/rejected punch never
 * creates a duplicate session.
 */
class TrackingSessionService
{
    public function onPunchCaptured(AttendancePunch $punch): void
    {
        if ($punch->direction === 'in') {
            AttendanceTrackingSession::create([
                'tenant_id' => $punch->tenant_id,
                'user_id' => $punch->user_id,
                'date' => $punch->date,
                'punch_in_id' => $punch->id,
                'session_seq' => $punch->session_seq,
                'attendance_id' => $punch->attendance_id,
                'started_at' => $punch->punched_at,
                'status' => 'open',
            ]);

            return;
        }

        // direction === 'out': close the session opened by the paired punch-in.
        if (! $punch->paired_punch_id) {
            return;
        }

        AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $punch->tenant_id)
            ->where('punch_in_id', $punch->paired_punch_id)
            ->update([
                'punch_out_id' => $punch->id,
                'ended_at' => $punch->punched_at,
                'status' => 'closed',
                'close_reason' => 'clock_out',
            ]);
    }

    /**
     * Resolve the tracking session a just-captured punch belongs to — used by
     * clockIn/clockOut to attach the bookend GPS point to the right session.
     */
    public function resolveSessionForPunch(AttendancePunch $punch): ?AttendanceTrackingSession
    {
        $punchInId = $punch->direction === 'in' ? $punch->id : $punch->paired_punch_id;

        if (! $punchInId) {
            return null;
        }

        return AttendanceTrackingSession::withoutGlobalScopes()
            ->where('tenant_id', $punch->tenant_id)
            ->where('punch_in_id', $punchInId)
            ->first();
    }
}
