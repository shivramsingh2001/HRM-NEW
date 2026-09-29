<?php
// app/Services/AttendanceNotificationService.php

namespace App\Services;

use App\Models\User;
use App\Notifications\AttendanceNotification;
use Illuminate\Support\Facades\Log;

class AttendanceNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when employee clocks in.
     *
     * $punch (the attendance_punches row just captured) gives the time and
     * place of THIS clock-in — with multiple punches per day the attendance
     * row's clock_in is the day's first punch, not this one.
     */
    public function notifyClockIn($attendance, $employee, $punch = null)
    {
        try {
            if (!$attendance) {
                return false;
            }

            // Get all recipients
            $recipients = $this->getAttendanceRecipients($employee->id);

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for clock-in notification', [
                    'user_id' => $employee->id
                ]);
                return false;
            }

            $at = $punch->punched_at ?? $attendance->clock_in;
            $clockInTime = \Carbon\Carbon::parse($at)->format('h:i A');
            $session = (int) ($punch->session_seq ?? 1);

            $data = [
                'attendance_id' => $attendance->id,
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $attendance->date,
                'clock_in_time' => $clockInTime,
                'clock_in_address' => $punch->address ?? $attendance->clock_in_address,
                'session' => $session,
                'type' => 'clock_in'
            ];

            $title = '🟢 Employee Clocked In';
            $body = $employee->name . ($session > 1 ? ' clocked in again at ' : ' clocked in at ') . $clockInTime
                . ($session > 1 ? ' (session ' . $session . ')' : '');

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database (same wording as the push)
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'clock_in', null, $title, $body));
            }

            Log::info('Clock-in notifications sent', [
                'user_id' => $employee->id,
                'recipient_count' => count($recipients)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send clock-in notifications', [
                'error' => $e->getMessage(),
                'user_id' => $employee->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when employee clocks out
     */
    public function notifyClockOut($attendance, $employee, $punch = null)
    {
        try {
            if (!$attendance) {
                return false;
            }

            // Get all recipients
            $recipients = $this->getAttendanceRecipients($employee->id);

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for clock-out notification', [
                    'user_id' => $employee->id
                ]);
                return false;
            }

            $at = $punch->punched_at ?? $attendance->clock_out;
            $clockOutTime = $at ? \Carbon\Carbon::parse($at)->format('h:i A') : '—';
            $totalHours = $attendance->total_hours ?: '0:00';
            $session = (int) ($punch->session_seq ?? 1);

            $data = [
                'attendance_id' => $attendance->id,
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $attendance->date,
                'clock_in_time' => $attendance->clock_in ? \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A') : null,
                'clock_out_time' => $clockOutTime,
                'total_hours' => $totalHours,
                'clock_out_address' => $punch->address ?? $attendance->clock_out_address,
                'session' => $session,
                'type' => 'clock_out'
            ];

            $title = '🔴 Employee Clocked Out';
            // total_hours is the whole day's worked time (all sessions so far).
            $body = $employee->name . ' clocked out at ' . $clockOutTime . ' (Worked today: ' . $totalHours . ')';

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database (same wording as the push)
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'clock_out', null, $title, $body));
            }

            Log::info('Clock-out notifications sent', [
                'user_id' => $employee->id,
                'recipient_count' => count($recipients)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send clock-out notifications', [
                'error' => $e->getMessage(),
                'user_id' => $employee->id
            ]);
            return false;
        }
    }

    /**
     * Send notification for late clock-in
     */
    public function notifyLateClockIn($attendance, $employee, $expectedTime)
    {
        try {
            $recipients = $this->getAttendanceRecipients($employee->id);

            if ($recipients->isEmpty()) {
                return false;
            }

            $clockInTime = \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A');
            
            $data = [
                'attendance_id' => $attendance->id,
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $attendance->date,
                'clock_in_time' => $clockInTime,
                'expected_time' => $expectedTime,
                'type' => 'late_clock_in'
            ];

            $title = '⚠️ Late Clock-In Alert';
            $body = $employee->name . ' clocked in late at ' . $clockInTime . ' (Expected: ' . $expectedTime . ')';

            foreach ($recipients as $recipient) {
                $this->sendNotification($recipient, $title, $body, array_merge($data, ['recipient_role' => $recipient->role]));
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'late_clock_in', $expectedTime, $title, $body));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send late clock-in notifications', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get all recipients for attendance notifications
     */
    private function getAttendanceRecipients($employeeId)
    {
        $recipients = collect();
        $tenantId = User::withoutGlobalScopes()->whereKey($employeeId)->value('tenant_id');

        // 1. Get Reporting Heads
        $recipients = $recipients->merge($this->getReportingHeads($employeeId));

        // 2. Get all HR users, 3. all Admin users — of the employee's own company
        foreach (['hr', 'admin'] as $role) {
            foreach ($this->getUsersByRole($role, $tenantId) as $user) {
                $recipients->push($user);
            }
        }

        // An HR/admin/manager clocking in isn't notified about themselves.
        return $recipients->unique('id')->reject(fn ($u) => $u->id == $employeeId)->values();
    }

    /**
     * Get all reporting heads for an employee (multi reporting-head support).
     */
    private function getReportingHeads($employeeId)
    {
        return User::find($employeeId)?->reportingHeads ?? collect();
    }

    /**
     * Get all users by role
     */
    private function getUsersByRole($role, $tenantId)
    {
        // Explicit tenant filter: never rely only on the request-bound global scope.
        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->where('status', 1)
            ->get();
    }

    /**
     * Core method to send FCM notification
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];
        
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        if (!is_array($tokens) || empty($tokens)) {
            Log::info('User has no valid FCM tokens', ['user_id' => $user->id]);
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;
            
            if (empty($token)) {
                continue;
            }
            
            $result = $this->firebaseService->sendToDevice(
                $token,
                $title,
                $body,
                $data
            );

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}