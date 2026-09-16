<?php
// app/Notifications/MeetingNotification.php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class MeetingNotification extends Notification
{
    use Queueable;

    protected $meeting;
    protected $action;
    protected $remarks;

    public function __construct(Meeting $meeting, $action = 'created', $remarks = null)
    {
        $this->meeting = $meeting;
        $this->action = $action;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $creator = $this->meeting->creator;
        
        $baseData = [
            'type' => 'meeting',
            'action' => $this->action,
            'meeting_id' => $this->meeting->id,
            'meeting_code' => $this->meeting->meeting_id,
            'title' => $this->meeting->title,
            'meeting_date' => $this->meeting->meeting_date,
            'meeting_date_formatted' => Carbon::parse($this->meeting->meeting_date)->format('d M Y'),
            'start_time' => $this->meeting->start_time,
            'end_time' => $this->meeting->end_time,
            'meeting_type' => $this->meeting->meeting_type,
            'location' => $this->meeting->location,
            'virtual_link' => $this->meeting->virtual_meeting_link,
            'created_by' => $creator->id ?? null,
            'created_by_name' => $creator->name ?? 'Unknown',
            'status' => $this->meeting->status,
            'created_at' => $this->meeting->created_at->toDateTimeString()
        ];

        switch ($this->action) {
            case 'created':
                $baseData['title'] = '📅 New Meeting Scheduled';
                $baseData['message'] = $creator->name . ' scheduled a meeting: "' . 
                    $this->meeting->title . '" on ' . 
                    Carbon::parse($this->meeting->meeting_date)->format('d M Y') . 
                    ' at ' . $this->meeting->start_time;
                break;
                
            case 'updated':
                $baseData['title'] = '✏️ Meeting Updated';
                $baseData['message'] = 'Meeting "' . $this->meeting->title . '" has been updated.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
                
            case 'rescheduled':
                $baseData['title'] = '🔄 Meeting Rescheduled';
                $baseData['message'] = 'Meeting "' . $this->meeting->title . '" has been rescheduled to ' .
                    Carbon::parse($this->meeting->meeting_date)->format('d M Y') . ' at ' . $this->meeting->start_time . '.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;

            case 'cancelled':
                $baseData['title'] = '❌ Meeting Cancelled';
                $baseData['message'] = 'Meeting "' . $this->meeting->title . '" scheduled for ' . 
                    Carbon::parse($this->meeting->meeting_date)->format('d M Y') . 
                    ' has been cancelled.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
                
            case 'reminder':
                $baseData['title'] = '⏰ Meeting Reminder';
                $baseData['message'] = 'Reminder: Meeting "' . $this->meeting->title . '" starts in ' . 
                    $this->remarks . ' minutes at ' . $this->meeting->start_time;
                break;
                
            case 'minutes_added':
                $baseData['title'] = '📝 Meeting Minutes Added';
                $baseData['message'] = 'Meeting minutes have been added for "' . 
                    $this->meeting->title . '".';
                break;
                
            case 'completed':
                $baseData['title'] = '✅ Meeting Completed';
                $baseData['message'] = 'Meeting "' . $this->meeting->title . '" has been marked as completed.';
                break;
        }

        return $baseData;
    }
}