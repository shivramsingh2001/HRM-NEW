<?php
// app/Notifications/ExpenseSubmittedNotification.php

namespace App\Notifications;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExpenseSubmittedNotification extends Notification
{
    use Queueable;

    protected $expense;
    protected $employee;

    public function __construct(Expense $expense)
    {
        $this->expense = $expense;
        $this->employee = $expense->user;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'fcm']; // Store in DB and send via FCM
    }

    /**
     * Get the FCM representation (for push notification)
     */
    public function toFcm($notifiable)
    {
        $title = '💰 New Expense Submitted';
        $body = $this->employee->name . ' submitted an expense of ₹' . number_format($this->expense->amount, 2);
        
        $data = [
            'type' => 'expense_submitted',
            'expense_id' => (string) $this->expense->id,
            'expense_number' => $this->expense->expense_number ?? 'N/A',
            'employee_id' => (string) $this->employee->id,
            'employee_name' => $this->employee->name,
            'amount' => (string) $this->expense->amount,
            'date' => $this->expense->date,
            'recipient_role' => $notifiable->role,
            'click_action' => 'EXPENSE_DETAILS'
        ];

        return [
            'title' => $title,
            'body' => $body,
            'data' => $data
        ];
    }

    /**
     * Get the array representation for database storage.
     */
    public function toArray($notifiable)
    {
        $title = '💰 New Expense Submitted';
         
        return [
            'title' => $title,
            'message' => $this->employee->name . ' submitted an expense of ₹' . number_format($this->expense->amount, 2),
            'type' => 'expense_submitted',
            'expense_id' => $this->expense->id,
            'expense_number' => $this->expense->expense_number ?? 'N/A',
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'amount' => $this->expense->amount,
            'date' => $this->expense->date,
            'recipient_role' => $notifiable->role,
            'read_at' => null
        ];
    }
}