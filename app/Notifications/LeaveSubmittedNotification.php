<?php
// app/Notifications/LeaveSubmittedNotification.php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class LeaveSubmittedNotification extends Notification
{
    use Queueable;

    protected $leave;

    public function __construct(Leave $leave)
    {
        $this->leave = $leave;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $start = \Carbon\Carbon::parse($this->leave->start_date);
        $end = \Carbon\Carbon::parse($this->leave->end_date ?? $this->leave->start_date);
        $totalDays = $end->diffInDays($start) + 1;

        return [
            'title' => '📅 New Leave Request',
            'type' => 'leave_submitted',
            'leave_id' => $this->leave->id,
            'leave_number' => $this->leave->leave_id,
            'employee_name' => $this->leave->user->name ?? 'Unknown',
            'leave_type' => $this->leave->leaveType->name ?? 'Leave',
            'start_date' => $this->leave->start_date,
            'end_date' => $this->leave->end_date,
            'total_days' => $totalDays,
            'message' => 'New leave request from ' . ($this->leave->user->name ?? 'Unknown'),
            'created_at' => now()->toDateTimeString()
        ];
    }
}