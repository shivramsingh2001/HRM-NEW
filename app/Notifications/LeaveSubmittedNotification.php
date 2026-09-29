<?php
// app/Notifications/LeaveSubmittedNotification.php

namespace App\Notifications;

use App\Models\Leave;
use App\Services\LeaveNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveSubmittedNotification extends Notification
{
    use Queueable;

    protected $leave;
    protected $title;
    protected $message;

    /** $title/$message = the push wording (LeaveNotificationService::message); built when not given. */
    public function __construct(Leave $leave, ?string $title = null, ?string $message = null)
    {
        $this->leave = $leave;

        if ($title === null || $message === null) {
            [$title, $message] = LeaveNotificationService::message('submitted', $leave);
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
            'type' => 'leave_submitted',
            'leave_id' => $this->leave->id,
            'leave_number' => $this->leave->leave_id,
            'employee_name' => $this->leave->user->name ?? 'Unknown',
            'leave_type' => $this->leave->leaveType->name ?? 'Leave',
            'start_date' => $this->leave->start_date,
            'end_date' => $this->leave->end_date ?? $this->leave->start_date,
            'total_days' => LeaveNotificationService::dayCount($this->leave),
            'message' => $this->message,
            'created_at' => now()->toDateTimeString()
        ];
    }
}
