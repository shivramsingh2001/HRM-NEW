<?php
// app/Services/ExpenseNotificationService.php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseSubmittedNotification;
use App\Notifications\ExpenseStatusChangedNotification;
use Illuminate\Support\Facades\Log;

class ExpenseNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when expense is submitted
     * Recipients: Reporting Head, All HR, All Admin
     */
    public function notifyExpenseSubmitted($expense)
    {
        try {
            // Get all recipients
            $recipients = $this->getExpenseSubmittedRecipients($expense->user_id);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for expense submission', [
                    'expense_id' => $expense->id
                ]);
                return false;
            }

            $employee = $expense->user;
            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number ?? 'N/A',
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'amount' => $expense->amount,
                'date' => $expense->date,
                'type' => 'expense_submitted'
            ];

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    '💰 New Expense Submitted',
                    $employee->name . ' submitted an expense of ₹' . number_format($expense->amount, 2),
                    array_merge($data, ['recipient_role' => $recipient->role])
                );

                // Also store in database
                $recipient->notify(new ExpenseSubmittedNotification($expense));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send expense submission notifications', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when expense is approved
     * Recipient: Employee who submitted the expense
     */
    public function notifyExpenseApproved($expense, $remarks = null)
    {
        try {
            $employee = $expense->user;
            
            if (!$employee) {
                Log::warning('Employee not found for expense approval', [
                    'expense_id' => $expense->id
                ]);
                return false;
            }

            $approver = null;
            if ($expense->approved_by) {
                $approver = User::find($expense->approved_by);
            }

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number ?? 'N/A',
                'amount' => $expense->amount,
                'remarks' => $remarks,
                'approved_by' => $approver ? $approver->name : 'Manager',
                'type' => 'expense_approved'
            ];

            $title = '✅ Expense Approved';
            $body = 'Your expense of ₹' . number_format($expense->amount, 2) . ' has been approved.';
            
            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);

            // Store in database
            $employee->notify(new ExpenseStatusChangedNotification($expense, 'approved', $remarks));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send expense approval notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when expense is rejected
     * Recipient: Employee who submitted the expense
     */
    public function notifyExpenseRejected($expense, $remarks = null)
    {
        try {
            $employee = $expense->user;
            
            if (!$employee) {
                Log::warning('Employee not found for expense rejection', [
                    'expense_id' => $expense->id
                ]);
                return false;
            }

            $rejector = null;
            if ($expense->rejected_by) {
                $rejector = User::find($expense->rejected_by);
            }

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number ?? 'N/A',
                'amount' => $expense->amount,
                'remarks' => $remarks,
                'rejected_by' => $rejector ? $rejector->name : 'Manager',
                'type' => 'expense_rejected'
            ];

            $title = '❌ Expense Rejected';
            $body = 'Your expense of ₹' . number_format($expense->amount, 2) . ' has been rejected.';
            
            if ($remarks) {
                $body .= ' Reason: ' . $remarks;
            }

            $this->sendNotification($employee, $title, $body, $data);

            // Store in database
            $employee->notify(new ExpenseStatusChangedNotification($expense, 'rejected', $remarks));
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send expense rejection notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when payment is processed
     * Recipient: Employee who submitted the expense
     */
    public function notifyPaymentProcessed($expense, $payment)
    {
        try {
            $employee = $expense->user;
            
            if (!$employee) {
                Log::warning('Employee not found for payment notification', [
                    'expense_id' => $expense->id
                ]);
                return false;
            }

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number ?? 'N/A',
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'payment_mode' => $payment->payment_mode,
                'payment_date' => $payment->payment_date,
                'reference_number' => $payment->reference_number,
                'type' => 'payment_processed'
            ];

            $title = '💰 Payment Processed';
            $body = 'Your payment of ₹' . number_format($payment->amount, 2) . ' has been processed via ' . strtoupper($payment->payment_mode);

            $this->sendNotification($employee, $title, $body, $data);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

    /**
     * 🔥 FIXED: Core method to send FCM notification to a user
     * Handles both array and string formats for fcm_tokens
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        // Get tokens - could be array or string
        $tokens = $user->fcm_tokens;
        
        // If it's a string, try to decode it
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        // If it's not an array or empty, log and return
        if (!is_array($tokens) || empty($tokens)) {
            Log::info('User has no valid FCM tokens', [
                'user_id' => $user->id,
                'token_type' => gettype($user->fcm_tokens)
            ]);
            return false;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($tokens as $tokenData) {
            // Handle both formats: string token or array with 'token' key
            $token = '';
            
            if (is_string($tokenData)) {
                $token = $tokenData;
            } elseif (is_array($tokenData) && isset($tokenData['token'])) {
                $token = $tokenData['token'];
            } else {
                Log::warning('Invalid token format', [
                    'user_id' => $user->id,
                    'token_data' => json_encode($tokenData)
                ]);
                continue;
            }
            
            if (empty($token)) {
                continue;
            }
            
            $result = $this->firebaseService->sendToDevice(
                $token,
                $title,
                $body,
                $data
            );

            if (isset($result['success']) && $result['success']) {
                $successCount++;
            } else {
                $failureCount++;
                Log::warning('Failed to send to token', [
                    'user_id' => $user->id,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }
        }

        return [
            'success' => $successCount > 0,
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ];
    }

    /**
     * Get all recipients for expense submission
     */
    private function getExpenseSubmittedRecipients($employeeId)
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

        // Remove duplicates
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
}