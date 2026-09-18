<?php
// app/Services/OvertimeNotificationService.php

namespace App\Services;

use App\Models\OvertimeRequest;
use App\Models\OvertimeSetting;
use App\Models\User;
use App\Notifications\OvertimeNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OvertimeNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when overtime request is submitted
     * Recipients: Reporting Head, All HR, All Admin
     */
    public function notifyOvertimeSubmitted($overtimeRequest)
    {
        try {
            // Get all recipients
            $recipients = $this->getOvertimeRecipients($overtimeRequest->user_id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for overtime submission', [
                    'overtime_request_id' => $overtimeRequest->id
                ]);
                return false;
            }

            $employee = $overtimeRequest->user;
            
            $data = [
                'overtime_request_id' => $overtimeRequest->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $overtimeRequest->date,
                'date_formatted' => Carbon::parse($overtimeRequest->date)->format('d M Y'),
                'overtime_hours' => $overtimeRequest->overtime_hours,
                'reason' => $overtimeRequest->reason,
                'type' => 'overtime_submitted'
            ];

            $title = '⏰ New Overtime Request';
            $body = $employee->name . ' requested ' . 
                    number_format($overtimeRequest->overtime_hours, 1) . 
                    ' hours of overtime for ' . Carbon::parse($overtimeRequest->date)->format('d M Y');

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new OvertimeNotification($overtimeRequest, 'submitted'));
            }

            Log::info('Overtime submission notifications sent', [
                'overtime_request_id' => $overtimeRequest->id,
                'recipient_count' => count($recipients)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send overtime submission notifications', [
                'error' => $e->getMessage(),
                'overtime_request_id' => $overtimeRequest->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when overtime request is approved
     */
    public function notifyOvertimeApproved($overtimeRequest, $remarks = null)
    {
        try {
            $employee = $overtimeRequest->user;
            
            if (!$employee) {
                Log::warning('Employee not found for overtime approval', [
                    'overtime_request_id' => $overtimeRequest->id
                ]);
                return false;
            }

            $approvedHours = $overtimeRequest->approved_hours ?? $overtimeRequest->overtime_hours;
            $rateMultiplier = $this->getOvertimeRateMultiplier();

            $data = [
                'overtime_request_id' => $overtimeRequest->id,
                'date' => $overtimeRequest->date,
                'date_formatted' => Carbon::parse($overtimeRequest->date)->format('d M Y'),
                'requested_hours' => $overtimeRequest->overtime_hours,
                'approved_hours' => $approvedHours,
                'rate_multiplier' => $rateMultiplier,
                'remarks' => $remarks,
                'type' => 'overtime_approved'
            ];

            $title = '✅ Overtime Request Approved';
            $body = 'Your overtime request for ' . 
                    Carbon::parse($overtimeRequest->date)->format('d M Y') . 
                    ' has been approved for ' . number_format($approvedHours, 1) . ' hours.';
            
            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new OvertimeNotification($overtimeRequest, 'approved', $remarks));

            // Also notify HR and Admin about approval
            $this->notifyAdminsAboutApproval($overtimeRequest, $employee);

            Log::info('Overtime approval notification sent', [
                'overtime_request_id' => $overtimeRequest->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send overtime approval notification', [
                'error' => $e->getMessage(),
                'overtime_request_id' => $overtimeRequest->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when overtime request is rejected
     */
    public function notifyOvertimeRejected($overtimeRequest, $remarks = null)
    {
        try {
            $employee = $overtimeRequest->user;
            
            if (!$employee) {
                Log::warning('Employee not found for overtime rejection', [
                    'overtime_request_id' => $overtimeRequest->id
                ]);
                return false;
            }

            $data = [
                'overtime_request_id' => $overtimeRequest->id,
                'date' => $overtimeRequest->date,
                'date_formatted' => Carbon::parse($overtimeRequest->date)->format('d M Y'),
                'requested_hours' => $overtimeRequest->overtime_hours,
                'remarks' => $remarks,
                'type' => 'overtime_rejected'
            ];

            $title = '❌ Overtime Request Rejected';
            $body = 'Your overtime request for ' . 
                    Carbon::parse($overtimeRequest->date)->format('d M Y') . 
                    ' has been rejected.';
            
            if ($remarks) {
                $body .= ' Reason: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new OvertimeNotification($overtimeRequest, 'rejected', $remarks));

            Log::info('Overtime rejection notification sent', [
                'overtime_request_id' => $overtimeRequest->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send overtime rejection notification', [
                'error' => $e->getMessage(),
                'overtime_request_id' => $overtimeRequest->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when overtime request is updated
     */
    public function notifyOvertimeUpdated($overtimeRequest, $oldHours = null)
    {
        try {
            $employee = $overtimeRequest->user;
            
            if (!$employee) {
                return false;
            }

            // Notify the reporting head and admins about the update
            $recipients = $this->getOvertimeRecipients($overtimeRequest->user_id);
            
            $data = [
                'overtime_request_id' => $overtimeRequest->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $overtimeRequest->date,
                'date_formatted' => Carbon::parse($overtimeRequest->date)->format('d M Y'),
                'old_hours' => $oldHours,
                'new_hours' => $overtimeRequest->overtime_hours,
                'reason' => $overtimeRequest->reason,
                'type' => 'overtime_updated'
            ];

            $title = '✏️ Overtime Request Updated';
            $body = $employee->name . ' updated their overtime request for ' . 
                    Carbon::parse($overtimeRequest->date)->format('d M Y') . 
                    ' from ' . number_format($oldHours, 1) . ' to ' . 
                    number_format($overtimeRequest->overtime_hours, 1) . ' hours.';

            foreach ($recipients as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new OvertimeNotification($overtimeRequest, 'updated'));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send overtime update notification', [
                'error' => $e->getMessage(),
                'overtime_request_id' => $overtimeRequest->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when overtime request is cancelled
     */
    public function notifyOvertimeCancelled($overtimeRequest)
    {
        try {
            $employee = $overtimeRequest->user;
            
            if (!$employee) {
                return false;
            }

            // Notify the reporting head and admins
            $recipients = $this->getOvertimeRecipients($overtimeRequest->user_id);
            
            $data = [
                'overtime_request_id' => $overtimeRequest->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'date' => $overtimeRequest->date,
                'date_formatted' => Carbon::parse($overtimeRequest->date)->format('d M Y'),
                'overtime_hours' => $overtimeRequest->overtime_hours,
                'reason' => $overtimeRequest->reason,
                'type' => 'overtime_cancelled'
            ];

            $title = '🗑️ Overtime Request Cancelled';
            $body = $employee->name . ' cancelled their overtime request for ' . 
                    Carbon::parse($overtimeRequest->date)->format('d M Y');

            foreach ($recipients as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new OvertimeNotification($overtimeRequest, 'cancelled'));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send overtime cancellation notification', [
                'error' => $e->getMessage(),
                'overtime_request_id' => $overtimeRequest->id
            ]);
            return false;
        }
    }

    /**
     * Send reminder for pending overtime requests
     */
    // public function sendPendingApprovalReminders()
    // {
    //     try {
    //         $pendingRequests = OvertimeRequest::where('status', 'pending')
    //             ->where('created_at', '<=', Carbon::now()->subHours(24))
    //             ->get();

    //         $reminderCount = 0;

    //         foreach ($pendingRequests as $request) {
    //             $recipients = $this->getOvertimeRecipients($request->user_id);
                
    //             foreach ($recipients as $recipient) {
    //                 $recipient->notify(new OvertimeNotification($request, 'reminder'));
    //                 $reminderCount++;
    //             }
    //         }

    //         Log::info('Overtime pending reminders sent', ['count' => $reminderCount]);
    //         return $reminderCount;

    //     } catch (\Exception $e) {
    //         Log::error('Failed to send overtime reminders', ['error' => $e->getMessage()]);
    //         return 0;
    //     }
    // }

    /**
     * Notify admins about approval
     */
    private function notifyAdminsAboutApproval($overtimeRequest, $employee)
    {
        $admins = $this->getUsersByRole('admin');
        
        $data = [
            'overtime_request_id' => $overtimeRequest->id,
            'employee_name' => $employee->name,
            'employee_id' => $employee->employee_id,
            'date' => $overtimeRequest->date,
            'approved_hours' => $overtimeRequest->approved_hours ?? $overtimeRequest->overtime_hours,
            'type' => 'overtime_approved_admin'
        ];

        $title = '✅ Overtime Approved';
        $body = $employee->name . "'s overtime request has been approved for " . 
                number_format($overtimeRequest->approved_hours ?? $overtimeRequest->overtime_hours, 1) . ' hours.';

        foreach ($admins as $admin) {
            $this->sendNotification($admin, $title, $body, $data);
        }
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
     * Get all recipients for overtime request
     */
    private function getOvertimeRecipients($employeeId)
    {
        $recipients = collect();

        // 1. Get Reporting Heads
        $recipients = $recipients->merge($this->getReportingHeads($employeeId));

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
     * Get all reporting heads for an employee (multi reporting-head support).
     */
    private function getReportingHeads($employeeId)
    {
        return User::find($employeeId)?->reportingHeads ?? collect();
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
     * Get overtime rate multiplier from settings
     */
    private function getOvertimeRateMultiplier()
    {
        $setting = OvertimeSetting::first();
        
        return $setting->rate_multiplier ?? 1.5;
    }
}