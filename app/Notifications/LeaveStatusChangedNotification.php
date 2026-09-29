<?php
// app/Notifications/LeaveStatusChangedNotification.php

namespace App\Notifications;

use App\Models\Leave;
use App\Services\LeaveNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveStatusChangedNotification extends Notification
{
    use Queueable;

    protected $leave;
    protected $status;
    protected $remarks;
    protected $title;
    protected $message;

    /**
     * $title/$message are the exact push wording (LeaveNotificationService::message);
     * built here when not given. Previously the stored title was always
     * "Leave Approved", even for a rejection.
     */
    public function __construct(Leave $leave, $status, $remarks = null, ?string $title = null, ?string $message = null)
    {
        $this->leave = $leave;
        $this->status = $status;
        $this->remarks = $remarks;

        if ($title === null || $message === null) {
            [$title, $message] = LeaveNotificationService::message((string) $status, $leave, $remarks);
        }
        $this->title = $title;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'type' => 'leave_' . $this->status,
            'leave_id' => $this->leave->id,
            'leave_number' => $this->leave->leave_id,
            'status' => $this->status,
            'start_date' => $this->leave->start_date,
            'end_date' => $this->leave->end_date ?? $this->leave->start_date,
            'total_days' => LeaveNotificationService::dayCount($this->leave),
            'remarks' => $this->remarks,
            'message' => $this->message,
            'created_at' => now()->toDateTimeString()
        ];
    }
}
