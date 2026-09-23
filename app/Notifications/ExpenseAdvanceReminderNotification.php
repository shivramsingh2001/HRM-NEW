<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** "You still hold ₹X of unspent advance, the oldest is N days old — please settle it." Scalars only (queue-safe). */
class ExpenseAdvanceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $totalFormatted, private int $oldestDays)
    {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'expense_advance_reminder',
            'action' => 'advance_reminder',
            'title' => '⏰ Unsettled advance',
            'message' => "You still hold ₹{$this->totalFormatted} of unspent advance; the oldest is {$this->oldestDays} day(s) old. "
                . 'Please submit your settlement or return the balance.',
            'total' => $this->totalFormatted,
            'oldest_days' => $this->oldestDays,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
