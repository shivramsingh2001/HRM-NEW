<?php
// app/Services/LeaveNotificationService.php

namespace App\Services;

use App\Models\Leave;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Notifications\LeaveSubmittedNotification;
use App\Notifications\LeaveStatusChangedNotification;
use Illuminate\Support\Facades\Log;

class LeaveNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when leave is submitted
     * Recipients: Reporting Head, All HR, All Admin
     */
    public function notifyLeaveSubmitted($leave)
    {
        try {
            // Get all recipients
            $recipients = $this->getLeaveSubmittedRecipients($leave->user_id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for leave submission', [
                    'leave_id' => $leave->id
                ]);
                return false;
            }

            $employee = $leave->user;
            
            // Calculate total days
            $start = \Carbon\Carbon::parse($leave->start_date);
            $end = \Carbon\Carbon::parse($leave->end_date ?? $leave->start_date);
            $totalDays = $end->diffInDays($start) + 1;

            $data = [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
                'total_days' => $totalDays,
                'type' => 'leave_submitted'
            ];

            $title = '📅 New Leave Request';
            $body = $employee->name . ' requested ' . $totalDays . ' day(s) of leave';

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new LeaveSubmittedNotification($leave));
            }

            Log::info('Leave submission notifications sent', [
                'leave_id' => $leave->id,
                'recipient_count' => count($recipients)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send leave submission notifications', [
                'error' => $e->getMessage(),
                'leave_id' => $leave->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when leave is approved
     */
    public function notifyLeaveApproved($leave, $remarks = null)
    {
        try {
            $employee = $leave->user;
            
            if (!$employee) {
                Log::warning('Employee not found for leave approval', [
                    'leave_id' => $leave->id
                ]);
                return false;
            }

            $start = \Carbon\Carbon::parse($leave->start_date);
            $end = \Carbon\Carbon::parse($leave->start_date ?? $leave->start_date);
            $totalDays = $end->diffInDays($start) + 1;

            $data = [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_id,
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'start_date' => $leave->start_date,
                'end_date' => $leave->start_date,
                'total_days' => $totalDays,
                'remarks' => $remarks,
                'type' => 'leave_approved'
            ];

            $title = '✅ Leave Approved';
            $body = 'Your leave request for ' . $totalDays . ' day(s) has been approved.';
            
            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new LeaveStatusChangedNotification($leave, 'approved', $remarks));

            Log::info('Leave approval notification sent', [
                'leave_id' => $leave->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send leave approval notification', [
                'error' => $e->getMessage(),
                'leave_id' => $leave->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when leave is rejected
     */
    public function notifyLeaveRejected($leave, $remarks = null)
    {
        try {
            $employee = $leave->user;
            
            if (!$employee) {
                Log::warning('Employee not found for leave rejection', [
                    'leave_id' => $leave->id
                ]);
                return false;
            }

            $start = \Carbon\Carbon::parse($leave->start_date);
            $end = \Carbon\Carbon::parse($leave->start_date ?? $leave->start_date);
            $totalDays = $end->diffInDays($start) + 1;

            $data = [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_id,
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'start_date' => $leave->start_date,
                'end_date' => $leave->start_date,
                'total_days' => $totalDays,
                'remarks' => $remarks,
                'type' => 'leave_rejected'
            ];

            $title = '❌ Leave Rejected';
            $body = 'Your leave request for ' . $totalDays . ' day(s) has been rejected.';
            
            if ($remarks) {
                $body .= ' Reason: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new LeaveStatusChangedNotification($leave, 'rejected', $remarks));

            Log::info('Leave rejection notification sent', [
                'leave_id' => $leave->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send leave rejection notification', [
                'error' => $e->getMessage(),
                'leave_id' => $leave->id
            ]);
            return false;
        }
    }

    /**
     * Core method to send FCM notification
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        // Get tokens - could be array or string
        $tokens = $user->fcm_tokens ?? [];
        
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        if (!is_array($tokens) || empty($tokens)) {
            Log::info('User has no valid FCM tokens', [
                'user_id' => $user->id,
                'token_type' => gettype($user->fcm_tokens)
            ]);
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

    /**
     * Get all recipients for leave submission
     */
    private function getLeaveSubmittedRecipients($employeeId)
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
}