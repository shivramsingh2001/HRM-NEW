<?php
// app/Notifications/ExpenseStatusChangedNotification.php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExpenseStatusChangedNotification extends Notification
{
    use Queueable;

    protected $expense;
    protected $status;
    protected $remarks;

    public function __construct(Expense $expense, $status, $remarks = null)
    {
        $this->expense = $expense;
        $this->status = $status;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $statusText = $this->status === 'approved' ? 'approved' : 'rejected';
        
        return [
            'title'=>'Expense'. $this->status,
            'message' => 'Your expense of ₹' . number_format($this->amount, 2) . ' has been '. $statusText.'.',
            'type' => 'expense_' . $this->status,
            'expense_id' => $this->expense->id,
            'expense_number' => $this->expense->expense_number ?? 'N/A',
            'status' => $this->status,
            'date' => $this->date,
            'amount' => $this->expense->amount,
            'remarks' => $this->remarks,
            'created_at' => now()->toDateTimeString()
        ];
    }
}