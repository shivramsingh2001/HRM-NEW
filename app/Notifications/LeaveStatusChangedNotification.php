<?php
// app/Notifications/LeaveStatusChangedNotification.php

namespace App\Notifications;

use App\Models\Leave;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class LeaveStatusChangedNotification extends Notification
{
    use Queueable;

    protected $leave;
    protected $status;
    protected $remarks;

    public function __construct(Leave $leave, $status, $remarks = null)
    {
        $this->leave = $leave;
        $this->status = $status;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $start = \Carbon\Carbon::parse($this->leave->start_date);
        $end = \Carbon\Carbon::parse($this->leave->start_date ?? $this->leave->start_date);
        $totalDays = $end->diffInDays($start) + 1;
        
        $statusText = $this->status === 'approved' ? 'approved' : 'rejected';
        $statusColor = $this->status === 'approved' ? 'success' : 'danger';

        return [
            'title'=>'✅ Leave Approved',
            'type' => 'leave_' . $this->status,
            'leave_id' => $this->leave->id,
            'leave_number' => $this->leave->leave_id,
            'status' => $this->status,
            // 'status_text' => $statusText,
            // 'status_color' => $statusColor,
            'start_date' => $this->leave->start_date,
            'end_date' => $this->leave->start_date,
            'total_days' => $totalDays,
            'remarks' => $this->remarks,
            'message' => 'Your leave request for ' . $totalDays . ' day(s) has been'. $statusText.'.',
            'created_at' => now()->toDateTimeString()
        ];
    }
}