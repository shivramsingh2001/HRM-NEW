<?php
// app/Services/RequestNotificationService.php

namespace App\Services;

use App\Models\Request as RequestStore;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Notifications\RequestNotification;
use Illuminate\Support\Facades\Log;

class RequestNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when request is submitted
     * Recipients: Reporting Head, All HR, All Admin
     */
    public function notifyRequestSubmitted($request)
    {
        try {
            // Get all recipients
            $recipients = $this->getRequestRecipients($request->user_id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for request submission', [
                    'request_id' => $request->id
                ]);
                return false;
            }

            $employee = $request->user;
            $requestType = $request->requestType->type_name ?? 'Request';
            
            // Calculate duration
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $durationDays = $endDate->diffInDays($startDate) + 1;

            $data = [
                'request_id' => $request->id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'request_type' => $requestType,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration_days' => $durationDays,
                'reason' => $request->reason,
                'type' => 'request_submitted'
            ];

            $title = '📝 New ' . $requestType . ' Request';
            $body = $employee->name . ' submitted a ' . $requestType . ' request for ' . $durationDays . ' day(s)';

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new RequestNotification($request, 'submitted'));
            }

            Log::info('Request submission notifications sent', [
                'request_id' => $request->id,
                'recipient_count' => count($recipients)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send request submission notifications', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when request is approved
     */
    public function notifyRequestApproved($request, $remarks = null)
    {
        try {
            $employee = $request->user;
            
            if (!$employee) {
                Log::warning('Employee not found for request approval', [
                    'request_id' => $request->id
                ]);
                return false;
            }

            $requestType = $request->requestType->type_name ?? 'Request';
            
            // Calculate duration
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $durationDays = $endDate->diffInDays($startDate) + 1;

            $data = [
                'request_id' => $request->id,
                'request_type' => $requestType,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration_days' => $durationDays,
                'remarks' => $remarks,
                'type' => 'request_approved'
            ];

            $title = '✅ ' . $requestType . ' Request Approved';
            $body = 'Your ' . $requestType . ' request for ' . $durationDays . ' day(s) has been approved.';
            
            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new RequestNotification($request, 'APPROVED', $remarks));

            Log::info('Request approval notification sent', [
                'request_id' => $request->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send request approval notification', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when request is rejected
     */
    public function notifyRequestRejected($request, $remarks = null)
    {
        try {
            $employee = $request->user;
            
            if (!$employee) {
                Log::warning('Employee not found for request rejection', [
                    'request_id' => $request->id
                ]);
                return false;
            }

            $requestType = $request->requestType->type_name ?? 'Request';
            
            // Calculate duration
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $durationDays = $endDate->diffInDays($startDate) + 1;

            $data = [
                'request_id' => $request->id,
                'request_type' => $requestType,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration_days' => $durationDays,
                'remarks' => $remarks,
                'type' => 'request_rejected'
            ];

            $title = '❌ ' . $requestType . ' Request Rejected';
            $body = 'Your ' . $requestType . ' request for ' . $durationDays . ' day(s) has been rejected.';
            
            if ($remarks) {
                $body .= ' Reason: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new RequestNotification($request, 'REJECTED', $remarks));

            Log::info('Request rejection notification sent', [
                'request_id' => $request->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send request rejection notification', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
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
     * Get all recipients for request submission
     */
    private function getRequestRecipients($employeeId)
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