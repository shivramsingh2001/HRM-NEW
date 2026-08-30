<?php
// app/Services/ExpensePaymentNotificationService.php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Notifications\ExpensePaymentNotification;
use Illuminate\Support\Facades\Log;

class ExpensePaymentNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when payment is created
     */
    public function notifyPaymentCreated(Expense $expense, ExpensePayment $payment, $remarks = null)
    {
        try {
            // Determine recipients based on requirement type
            $recipients = $this->getPaymentRecipients($expense);
            
            if (empty($recipients)) {
                Log::warning('No recipients found for payment creation', [
                    'expense_id' => $expense->id,
                    'payment_id' => $payment->id
                ]);
                return false;
            }

            $employee = $expense->user;
            $requirementType = ucfirst($expense->requirement_type);
            
            $title = '💰 New ' . $requirementType . ' Payment';
            $body = 'Payment of ₹' . number_format($payment->amount, 2) . 
                ' has been processed for ' . $employee->name . "'s " . $requirementType . ' request';

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'requirement_type' => $expense->requirement_type,
                'payment_id' => $payment->id,
                'payment_amount' => $payment->amount,
                'payment_mode' => $payment->payment_mode,
                'payment_date' => $payment->payment_date,
                'reference_number' => $payment->reference_number,
                'employee_name' => $employee->name,
                'employee_id' => $employee->employee_id,
                'type' => 'expense_payment',
                'action' => 'payment_created'
            ];

            // Send to each recipient
            foreach ($recipients as $recipient) {
                $this->sendNotification(
                    $recipient,
                    $title,
                    $body,
                    array_merge($data, ['recipient_role' => $recipient->role, 'remarks' => $remarks])
                );

                // Store in database
                $recipient->notify(new ExpensePaymentNotification($expense, $payment, 'payment_created', $remarks));
            }

            // Check if expense is now fully paid
            $totalPaid = $expense->payments()->sum('amount');
            if (abs($totalPaid - $expense->amount) < 0.01) {
                $this->notifyExpenseFullyPaid($expense);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment creation notifications', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id,
                'payment_id' => $payment->id ?? null
            ]);
            return false;
        }
    }

    /**
     * Send notification when payment is updated
     */
    public function notifyPaymentUpdated(Expense $expense, ExpensePayment $payment, $oldAmount, $remarks = null)
    {
        try {
            $employee = $expense->user;
            $requirementType = ucfirst($expense->requirement_type);
            
            // Notify the employee
            $title = '✏️ Payment Updated';
            $body = 'Your ' . $requirementType . ' payment has been updated from ₹' . 
                number_format($oldAmount, 2) . ' to ₹' . number_format($payment->amount, 2);

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'requirement_type' => $expense->requirement_type,
                'payment_id' => $payment->id,
                'old_amount' => $oldAmount,
                'new_amount' => $payment->amount,
                'payment_mode' => $payment->payment_mode,
                'payment_date' => $payment->payment_date,
                'type' => 'expense_payment',
                'action' => 'payment_updated'
            ];

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new ExpensePaymentNotification($expense, $payment, 'payment_updated', $remarks));

            // Notify admins and HR about the update
            $adminRecipients = $this->getAdminAndHRUsers();
            $adminTitle = '✏️ Payment Updated - ' . $employee->name;
            $adminBody = 'Payment for ' . $requirementType . ' request (' . $expense->expense_number . 
                ') of ' . $employee->name . ' updated from ₹' . number_format($oldAmount, 2) . 
                ' to ₹' . number_format($payment->amount, 2);

            foreach ($adminRecipients as $admin) {
                $this->sendNotification($admin, $adminTitle, $adminBody, $data);
                $admin->notify(new ExpensePaymentNotification($expense, $payment, 'payment_updated', $remarks));
            }

            // Check if expense is now fully paid
            $totalPaid = $expense->payments()->sum('amount');
            if (abs($totalPaid - $expense->amount) < 0.01) {
                $this->notifyExpenseFullyPaid($expense);
            }

        
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment update notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id,
                'payment_id' => $payment->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when payment is deleted
     */
    public function notifyPaymentDeleted(Expense $expense, $paymentAmount, $remarks = null)
    {
        try {
            $employee = $expense->user;
            $requirementType = ucfirst($expense->requirement_type);
            
            // Notify the employee
            $title = '🗑️ Payment Deleted';
            $body = 'A payment of ₹' . number_format($paymentAmount, 2) . ' for your ' . 
                $requirementType . ' request has been deleted';

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'requirement_type' => $expense->requirement_type,
                'deleted_amount' => $paymentAmount,
                'type' => 'expense_payment',
                'action' => 'payment_deleted'
            ];

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new ExpensePaymentNotification($expense, null, 'payment_deleted', $remarks));

            // Notify admins and HR
            $adminRecipients = $this->getAdminAndHRUsers();
            $adminTitle = '🗑️ Payment Deleted - ' . $employee->name;
            $adminBody = 'Payment of ₹' . number_format($paymentAmount, 2) . ' for ' . 
                $requirementType . ' request (' . $expense->expense_number . ') of ' . 
                $employee->name . ' has been deleted';

            foreach ($adminRecipients as $admin) {
                $this->sendNotification($admin, $adminTitle, $adminBody, $data);
                $admin->notify(new ExpensePaymentNotification($expense, null, 'payment_deleted', $remarks));
            }


            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment deletion notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when expense is fully paid
     */
    public function notifyExpenseFullyPaid(Expense $expense)
    {
        try {
            $employee = $expense->user;
            $requirementType = ucfirst($expense->requirement_type);
            $actionText = $requirementType === 'Reimbursement' ? 'reimbursed' : 'paid';
            
            $title = '✅ ' . $requirementType . ' Fully ' . ucfirst($actionText);
            $body = 'Your ' . $requirementType . ' request (' . $expense->expense_number . 
                ') has been fully ' . $actionText . '. Total amount: ₹' . number_format($expense->amount, 2);

            $data = [
                'expense_id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'requirement_type' => $expense->requirement_type,
                'total_amount' => $expense->amount,
                'type' => 'expense_payment',
                'action' => 'expense_fully_paid'
            ];

            $this->sendNotification($employee, $title, $body, $data);
            $employee->notify(new ExpensePaymentNotification($expense, null, 'expense_fully_paid'));

            // Notify admins and HR
            $adminRecipients = $this->getAdminAndHRUsers();
            $adminTitle = '✅ ' . $requirementType . ' Fully ' . ucfirst($actionText) . ' - ' . $employee->name;
            $adminBody = $requirementType . ' request (' . $expense->expense_number . ') of ' . 
                $employee->name . ' has been fully ' . $actionText . '. Amount: ₹' . 
                number_format($expense->amount, 2);

            foreach ($adminRecipients as $admin) {
                $this->sendNotification($admin, $adminTitle, $adminBody, $data);
                $admin->notify(new ExpensePaymentNotification($expense, null, 'expense_fully_paid'));
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send expense fully paid notification', [
                'error' => $e->getMessage(),
                'expense_id' => $expense->id
            ]);
            return false;
        }
    }

   

    /**
     * Core method to send FCM notification
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        // Get tokens
        $tokens = $user->fcm_tokens ?? [];
        
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        if (!is_array($tokens) || empty($tokens)) {
            Log::info('User has no valid FCM tokens', [
                'user_id' => $user->id,
                'user_role' => $user->role
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
     * Get recipients for payment notifications
     */
    private function getPaymentRecipients(Expense $expense)
    {
        $recipients = collect();

        // 1. The employee who submitted the expense
        $employee = User::find($expense->user_id);
        if ($employee) {
            $recipients->push($employee);
        }

        // 2. Reporting Head (if exists and not the same as employee)
        $reportingHead = $this->getReportingHead($expense->user_id);
        if ($reportingHead && $reportingHead->id != $expense->user_id) {
            $recipients->push($reportingHead);
        }

        // 3. All Admin and HR users
        $adminHRUsers = $this->getAdminAndHRUsers();
        foreach ($adminHRUsers as $user) {
            $recipients->push($user);
        }

        return $recipients->unique('id')->values();
    }

    /**
     * Get all Admin and HR users
     */
    private function getAdminAndHRUsers()
    {
        return User::whereIn('role', ['admin', 'hr'])
            ->where('status', 1)
            ->get();
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
}