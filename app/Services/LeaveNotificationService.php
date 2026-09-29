<?php
// app/Services/LeaveNotificationService.php

namespace App\Services;

use App\Models\Leave;
use App\Models\User;
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
            $totalDays = self::dayCount($leave);

            $data = [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_id,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date ?? $leave->start_date,
                'total_days' => $totalDays,
                'type' => 'leave_submitted'
            ];

            [$title, $body] = self::message('submitted', $leave);

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database (same wording as the push)
                $recipient->notify(new LeaveSubmittedNotification($leave, $title, $body));
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
        return $this->notifyDecision($leave, 'approved', $remarks);
    }

    /**
     * Send notification when a pending leave request is rejected
     */
    public function notifyLeaveRejected($leave, $remarks = null)
    {
        return $this->notifyDecision($leave, 'rejected', $remarks);
    }

    /**
     * Send notification when an already-approved leave is revoked/cancelled
     * (previously this was sent as "Leave Rejected").
     */
    public function notifyLeaveCancelled($leave, $remarks = null)
    {
        return $this->notifyDecision($leave, 'cancelled', $remarks);
    }

    /** Push + stored notification to the employee, both with the same wording. */
    private function notifyDecision($leave, string $status, $remarks = null): bool
    {
        try {
            $employee = $leave->user;

            if (!$employee) {
                Log::warning('Employee not found for leave ' . $status, [
                    'leave_id' => $leave->id
                ]);
                return false;
            }

            $data = [
                'leave_id' => $leave->id,
                'leave_number' => $leave->leave_id,
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date ?? $leave->start_date,
                'total_days' => self::dayCount($leave),
                'remarks' => $remarks,
                'type' => 'leave_' . $status
            ];

            [$title, $body] = self::message($status, $leave, $remarks);

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new LeaveStatusChangedNotification($leave, $status, $remarks, $title, $body));

            Log::info('Leave ' . $status . ' notification sent', [
                'leave_id' => $leave->id,
                'employee_id' => $employee->id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send leave ' . $status . ' notification', [
                'error' => $e->getMessage(),
                'leave_id' => $leave->id
            ]);
            return false;
        }
    }

    /**
     * Days on the request: the stored total_days (already accounts for half
     * days / sessions), else the inclusive start..end span.
     */
    public static function dayCount($leave): float
    {
        if ((float) ($leave->total_days ?? 0) > 0) {
            return (float) $leave->total_days;
        }

        $start = \Carbon\Carbon::parse($leave->start_date);
        $end = \Carbon\Carbon::parse($leave->end_date ?? $leave->start_date);

        return (float) (abs($end->diffInDays($start)) + 1);
    }

    /** [title, body] for a leave event — shared by the push and the stored notification. */
    public static function message(string $status, $leave, $remarks = null): array
    {
        $days = self::dayCount($leave);
        $daysText = rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . ($days == 1 ? '' : 's');
        $type = $leave->leaveType->name ?? 'leave';
        $start = \Carbon\Carbon::parse($leave->start_date);
        $end = \Carbon\Carbon::parse($leave->end_date ?? $leave->start_date);
        $dates = $start->isSameDay($end) ? $start->format('d M Y') : $start->format('d M') . ' – ' . $end->format('d M Y');

        [$title, $body] = match ($status) {
            'submitted' => ['📅 New Leave Request', ($leave->user->name ?? 'An employee') . " requested {$daysText} of {$type} ({$dates})."],
            'approved' => ['✅ Leave Approved', "Your {$type} request for {$daysText} ({$dates}) has been approved."],
            'rejected' => ['❌ Leave Rejected', "Your {$type} request for {$daysText} ({$dates}) has been rejected."],
            'cancelled' => ['🚫 Leave Cancelled', "Your approved {$type} for {$daysText} ({$dates}) has been cancelled and the balance restored."],
            default => ['📅 Leave Update', "Your {$type} request for {$daysText} ({$dates}) was updated."],
        };

        if ($remarks) {
            $body .= ($status === 'rejected' ? ' Reason: ' : ' Remarks: ') . $remarks;
        }

        return [$title, $body];
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
        $tenantId = User::withoutGlobalScopes()->whereKey($employeeId)->value('tenant_id');

        // 1. Get Reporting Head
        $recipients = $recipients->merge($this->getReportingHeads($employeeId));

        // 2. Get all HR users, 3. all Admin users — of the employee's own company
        foreach (['hr', 'admin'] as $role) {
            foreach ($this->getUsersByRole($role, $tenantId) as $user) {
                $recipients->push($user);
            }
        }

        // An HR/admin applying for their own leave isn't notified of it.
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
}