<?php
// app/Services/AttendanceRegularizationNotificationService.php

namespace App\Services;

use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Notifications\AttendanceRegularizationNotification;
use Illuminate\Support\Facades\Log;

class AttendanceRegularizationNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when regularization request is submitted
     * Recipients: Reporting Head, All HR, All Admin
     */
    public function notifyRegularizationSubmitted($regularization)
    {
        try {
            // Get all recipients
            $recipients = $this->getRegularizationRecipients($regularization->user_id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for regularization submission', [
                    'regularization_id' => $regularization->id
                ]);
                return false;
            }

            $employee = $regularization->user;
            
            // Format request details
            $requestDetails = $this->formatRequestDetails($regularization);
            $requestTypeDisplay = $this->getRequestTypeDisplay($regularization->request_type);

            $data = [
                'regularization_id' => $regularization->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $regularization->date,
                'request_type' => $regularization->request_type,
                'request_type_display' => $requestTypeDisplay,
                'in_time' => $regularization->in_time,
                'out_time' => $regularization->out_time,
                'request_details' => $requestDetails,
                'reason' => $regularization->reason,
                'type' => 'regularization_submitted'
            ];

            $title = '📝 New Attendance Regularization Request';
            $body = $employee->name . ' submitted a ' . $requestTypeDisplay . ' request for ' . date('d M Y', strtotime($regularization->date));

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new AttendanceRegularizationNotification($regularization, 'submitted'));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send regularization submission notifications', [
                'error' => $e->getMessage(),
                'regularization_id' => $regularization->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when regularization request is approved
     */
    public function notifyRegularizationApproved($regularization, $remarks = null)
    {
        try {
            $employee = $regularization->user;
            
            if (!$employee) {
                Log::warning('Employee not found for regularization approval', [
                    'regularization_id' => $regularization->id
                ]);
                return false;
            }

            $requestDetails = $this->formatRequestDetails($regularization);
            $requestTypeDisplay = $this->getRequestTypeDisplay($regularization->request_type);

            $data = [
                'regularization_id' => $regularization->id,
                'date' => $regularization->date,
                'request_type' => $regularization->request_type,
                'request_type_display' => $requestTypeDisplay,
                'in_time' => $regularization->in_time,
                'out_time' => $regularization->out_time,
                'request_details' => $requestDetails,
                'remarks' => $remarks,
                'type' => 'regularization_approved'
            ];

            $title = '✅ Attendance Regularization Approved';
            $body = 'Your ' . $requestTypeDisplay . ' request for ' . date('d M Y', strtotime($regularization->date)) . ' has been approved.';
            
            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new AttendanceRegularizationNotification($regularization, 'approved', $remarks));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send regularization approval notification', [
                'error' => $e->getMessage(),
                'regularization_id' => $regularization->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when regularization request is rejected
     */
    public function notifyRegularizationRejected($regularization, $remarks = null)
    {
        try {
            $employee = $regularization->user;
            
            if (!$employee) {
                Log::warning('Employee not found for regularization rejection', [
                    'regularization_id' => $regularization->id
                ]);
                return false;
            }

            $requestDetails = $this->formatRequestDetails($regularization);
            $requestTypeDisplay = $this->getRequestTypeDisplay($regularization->request_type);

            $data = [
                'regularization_id' => $regularization->id,
                'date' => $regularization->date,
                'request_type' => $regularization->request_type,
                'request_type_display' => $requestTypeDisplay,
                'request_details' => $requestDetails,
                'remarks' => $remarks,
                'type' => 'regularization_rejected'
            ];

            $title = '❌ Attendance Regularization Rejected';
            $body = 'Your ' . $requestTypeDisplay . ' request for ' . date('d M Y', strtotime($regularization->date)) . ' has been rejected.';
            
            if ($remarks) {
                $body .= ' Reason: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new AttendanceRegularizationNotification($regularization, 'rejected', $remarks));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send regularization rejection notification', [
                'error' => $e->getMessage(),
                'regularization_id' => $regularization->id
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
     * Get all recipients for regularization request
     */
    private function getRegularizationRecipients($employeeId)
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
     * Get display name for request type
     */
    private function getRequestTypeDisplay($requestType)
    {
        $types = [
            'missed_punch_in' => 'Missed Punch In',
            'missed_punch_out' => 'Missed Punch Out',
            'wrong_punch_time' => 'Wrong Punch Time',
            'attendance' => 'Attendance Correction'
        ];
        
        return $types[$requestType] ?? ucfirst(str_replace('_', ' ', $requestType));
    }

    /**
     * Format request details for display
     */
    private function formatRequestDetails($regularization)
    {
        switch ($regularization->request_type) {
            case 'missed_punch_in':
                return 'In Time: ' . ($regularization->in_time ? date('h:i A', strtotime($regularization->in_time)) : 'N/A');
            case 'missed_punch_out':
                return 'Out Time: ' . ($regularization->out_time ? date('h:i A', strtotime($regularization->out_time)) : 'N/A');
            case 'wrong_punch_time':
                $details = [];
                if ($regularization->in_time) {
                    $details[] = 'Corrected In Time: ' . date('h:i A', strtotime($regularization->in_time));
                }
                if ($regularization->out_time) {
                    $details[] = 'Corrected Out Time: ' . date('h:i A', strtotime($regularization->out_time));
                }
                return implode(', ', $details);
            case 'attendance':
                return 'Full day attendance correction';
            default:
                return 'N/A';
        }
    }
}