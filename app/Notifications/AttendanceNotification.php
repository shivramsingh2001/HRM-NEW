<?php
// app/Notifications/AttendanceNotification.php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AttendanceNotification extends Notification
{
    use Queueable;

    protected $attendance;
    protected $employee;
    protected $type;
    protected $expectedTime;

    public function __construct(Attendance $attendance, User $employee, $type, $expectedTime = null)
    {
        $this->attendance = $attendance;
        $this->employee = $employee;
        $this->type = $type;
        $this->expectedTime = $expectedTime;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $clockInTime = $this->attendance->clock_in ? \Carbon\Carbon::parse($this->attendance->clock_in)->format('d M h:i A') : null;
        $clockOutTime = $this->attendance->clock_out ? \Carbon\Carbon::parse($this->attendance->clock_out)->format('d M h:i A') : null;

        $message = '';
        $title = '';

        switch ($this->type) {
            case 'clock_in':
                $title = 'Employee Clocked In';
                $message = $this->employee->name . ' clocked in at ' . $clockInTime;
                break;
            case 'clock_out':
                $title = 'Employee Clocked Out';
                $message = $this->employee->name . ' clocked out at ' . $clockOutTime . 
                          ' (Total: ' . ($this->attendance->total_hours ?? '0') . ')';
                break;
            case 'late_clock_in':
                $title = 'Late Clock-In Alert';
                $message = $this->employee->name . ' clocked in late at ' . $clockInTime . 
                          ' (Expected: ' . $this->expectedTime . ')';
                break;
        }

        return [
            'title' => $title,
            'message' => $message,
            'type' => 'attendance_' . $this->type,
            'attendance_id' => $this->attendance->id,
            'user_id' => $this->employee->id,
            'employee_name' => $this->employee->name,
            'employee_id' => $this->employee->employee_id,
            'date' => $this->attendance->date,
            'clock_in' => $clockInTime,
            'clock_out' => $clockOutTime,
            'total_hours' => $this->attendance->total_hours,
            'clock_in_address' => $this->attendance->clock_in_address,
            'clock_out_address' => $this->attendance->clock_out_address,
            'created_at' => now()->toDateTimeString()
        ];
    }
}