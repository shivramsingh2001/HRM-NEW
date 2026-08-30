<?php
// app/Services/AttendanceNotificationService.php

namespace App\Services;

use App\Models\User;
use App\Models\UserJobDetail;
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
     * Send notification when employee clocks in
     */
    public function notifyClockIn($attendance, $employee)
    {
        try {
            // Get all recipients
            $recipients = $this->getAttendanceRecipients($employee->id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for clock-in notification', [
                    'user_id' => $employee->id
                ]);
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
                'clock_in_address' => $attendance->clock_in_address,
                'type' => 'clock_in'
            ];

            $title = '🟢 Employee Clocked In';
            $body = $employee->name . ' clocked in at ' . $clockInTime;

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'clock_in'));
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
    public function notifyClockOut($attendance, $employee)
    {
        try {
            // Get all recipients
            $recipients = $this->getAttendanceRecipients($employee->id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for clock-out notification', [
                    'user_id' => $employee->id
                ]);
                return false;
            }

            $clockOutTime = \Carbon\Carbon::parse($attendance->clock_out)->format('h:i A');
            $totalHours = $attendance->total_hours;
            
            $data = [
                'attendance_id' => $attendance->id,
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $attendance->date,
                'clock_in_time' => \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A'),
                'clock_out_time' => $clockOutTime,
                'total_hours' => $totalHours,
                'clock_out_address' => $attendance->clock_out_address,
                'type' => 'clock_out'
            ];

            $title = '🔴 Employee Clocked Out';
            $body = $employee->name . ' clocked out at ' . $clockOutTime . ' (Total: ' . $totalHours . ')';

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'clock_out'));
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
            
            if (empty($recipients)) {
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
                $recipient->notify(new AttendanceNotification($attendance, $employee, 'late_clock_in', $expectedTime));
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

        // 1. Get Reporting Head
        $reportingHead = $this->getReportingHead($employeeId);
        if ($reportingHead) {
            $recipients->push($reportingHead);
        }

        // 2. Get all HR users
        $hrUsers = $this->getUsersByRole('hr');
        foreach ($hrUsers as $hr) {
            $recipients->push($hr);
        }

        // 3. Get all Admin users
        $adminUsers = $this->getUsersByRole('admin');
        foreach ($adminUsers as $admin) {
            $recipients->push($admin);
        }

        return $recipients->unique('id')->values();
    }

    /**
     * Get reporting head for an employee
     */
    private function getReportingHead($employeeId)
    {
        $jobDetail = UserJobDetail::where('user_id', $employeeId)->first();
        
        if (!$jobDetail || !$jobDetail->reporting_head) {
            return null;
        }

        return User::find($jobDetail->reporting_head);
    }

    /**
     * Get all users by role
     */
    private function getUsersByRole($role)
    {
        return User::where('role', $role)
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