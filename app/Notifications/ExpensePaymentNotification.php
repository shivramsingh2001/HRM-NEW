<?php
// app/Notifications/ExpensePaymentNotification.php

namespace App\Notifications;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExpensePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $expense;
    protected $payment;
    protected $action;
    protected $remarks;
    protected $paymentAmount;

    public function __construct(Expense $expense, ExpensePayment $payment = null, $action = 'payment_created', $remarks = null)
    {
        $this->expense = $expense;
        $this->payment = $payment;
        $this->action = $action;
        $this->remarks = $remarks;
        $this->paymentAmount = $payment ? $payment->amount : 0;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $employee = $this->expense->user;
        $requirementType = ucfirst($this->expense->requirement_type);
        
        $baseData = [
            'type' => 'expense_payment',
            'action' => $this->action,
            'expense_id' => $this->expense->id,
            'expense_number' => $this->expense->expense_number,
            'requirement_type' => $this->expense->requirement_type,
            'amount' => $this->expense->amount,
            'payment_amount' => $this->paymentAmount,
            'employee_id' => $employee->id ?? null,
            'employee_name' => $employee->name ?? 'Unknown',
            'employee_code' => $employee->employee_id ?? 'N/A',
            'description' => $this->expense->description,
            'created_at' => now()->toDateTimeString()
        ];

        switch ($this->action) {
            case 'payment_created':
                $baseData['title'] = '💰 New ' . $requirementType . ' Payment';
                $baseData['message'] = 'Payment of ₹' . number_format($this->paymentAmount, 2) . 
                    ' has been processed for ' . $employee->name . "'s " . $requirementType . ' request (' . 
                    $this->expense->expense_number . ')';
                if ($this->payment) {
                    $baseData['payment_mode'] = $this->payment->payment_mode;
                    $baseData['payment_date'] = $this->payment->payment_date;
                    $baseData['reference_number'] = $this->payment->reference_number;
                }
                break;

            case 'payment_updated':
                $baseData['title'] = '💰 Payment Updated';
                $baseData['message'] = 'Payment for ' . $requirementType . ' request (' . 
                    $this->expense->expense_number . ') has been updated to ₹' . 
                    number_format($this->paymentAmount, 2);
                if ($this->payment) {
                    $baseData['payment_mode'] = $this->payment->payment_mode;
                    $baseData['payment_date'] = $this->payment->payment_date;
                }
                break;

            case 'payment_deleted':
                $baseData['title'] = '🗑️ Payment Deleted';
                $baseData['message'] = 'Payment of ₹' . number_format($this->paymentAmount, 2) . 
                    ' for ' . $requirementType . ' request (' . $this->expense->expense_number . 
                    ') has been deleted';
                break;

            case 'expense_fully_paid':
                $baseData['title'] = '✅ ' . $requirementType . ' Fully ' . 
                    ($requirementType === 'Reimbursement' ? 'Reimbursed' : 'Paid');
                $baseData['message'] = $requirementType . ' request (' . $this->expense->expense_number . 
                    ') has been fully ' . ($requirementType === 'Reimbursement' ? 'reimbursed' : 'paid') . 
                    '. Total amount: ₹' . number_format($this->expense->amount, 2);
                break;

            case 'payment_reminder':
                $totalPaid = $this->expense->payments()->sum('amount');
                $remainingAmount = $this->expense->amount - $totalPaid;
                $baseData['title'] = '⏰ ' . $requirementType . ' Payment Reminder';
                $baseData['message'] = ucfirst($requirementType) . ' request (' . $this->expense->expense_number . 
                    ') has pending amount of ₹' . number_format($remainingAmount, 2);
                $baseData['remaining_amount'] = $remainingAmount;
                break;
        }

        if ($this->remarks) {
            $baseData['remarks'] = $this->remarks;
        }

        return $baseData;
    }
}