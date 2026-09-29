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
        // Same wording as the push in ExpenseNotificationService. ($this->amount /
        // $this->date never existed — reading them threw, so the in-app
        // notification was never stored.)
        $approved = $this->status === 'approved';
        $message = 'Your expense of ₹' . number_format((float) $this->expense->amount, 2) . ' has been ' . ($approved ? 'approved' : 'rejected') . '.';
        if ($this->remarks) {
            $message .= ($approved ? ' Remarks: ' : ' Reason: ') . $this->remarks;
        }

        return [
            'title' => $approved ? '✅ Expense Approved' : '❌ Expense Rejected',
            'message' => $message,
            'type' => 'expense_' . $this->status,
            'expense_id' => $this->expense->id,
            'expense_number' => $this->expense->expense_number ?? 'N/A',
            'status' => $this->status,
            'date' => $this->expense->date ?? $this->expense->expense_date ?? null,
            'amount' => $this->expense->amount,
            'remarks' => $this->remarks,
            'created_at' => now()->toDateTimeString()
        ];
    }
}