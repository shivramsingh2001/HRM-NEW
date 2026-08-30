<?php
// app/Notifications/OvertimeNotification.php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class OvertimeNotification extends Notification
{
    use Queueable;

    protected $overtimeRequest;
    protected $action;
    protected $remarks;

    public function __construct(OvertimeRequest $overtimeRequest, $action = 'submitted', $remarks = null)
    {
        $this->overtimeRequest = $overtimeRequest;
        $this->action = $action;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $user = $this->overtimeRequest->user;
        
        $baseData = [
            'type' => 'overtime',
            'action' => $this->action,
            'overtime_request_id' => $this->overtimeRequest->id,
            'user_id' => $user->id ?? null,
            'employee_name' => $user->name ?? 'Unknown',
            'employee_id' => $user->employee_id ?? 'N/A',
            'date' => $this->overtimeRequest->date,
            'date_formatted' => Carbon::parse($this->overtimeRequest->date)->format('d M Y'),
            'overtime_hours' => $this->overtimeRequest->overtime_hours,
            'approved_hours' => $this->overtimeRequest->approved_hours,
            'reason' => $this->overtimeRequest->reason,
            'status' => $this->overtimeRequest->status,
            'created_at' => $this->overtimeRequest->created_at->toDateTimeString()
        ];

        // Add action-specific messages
        switch ($this->action) {
            case 'submitted':
                $baseData['title'] = '⏰ New Overtime Request Submitted';
                $baseData['message'] = $user->name . ' requested ' . 
                    number_format($this->overtimeRequest->overtime_hours, 1) . 
                    ' hours of overtime on ' . 
                    Carbon::parse($this->overtimeRequest->date)->format('d M Y');
                // $baseData['urgency'] = $this->getUrgencyLevel($this->overtimeRequest->overtime_hours);
                break;
                
            case 'approved':
                $baseData['title'] = '✅ Overtime Request Approved';
                $baseData['message'] = 'Your overtime request for ' . 
                    number_format($this->overtimeRequest->approved_hours ?? $this->overtimeRequest->overtime_hours, 1) . 
                    ' hours on ' . Carbon::parse($this->overtimeRequest->date)->format('d M Y') . 
                    ' has been approved.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
                
            case 'rejected':
                $baseData['title'] = '❌ Overtime Request Rejected';
                $baseData['message'] = 'Your overtime request for ' . 
                    number_format($this->overtimeRequest->overtime_hours, 1) . 
                    ' hours on ' . Carbon::parse($this->overtimeRequest->date)->format('d M Y') . 
                    ' has been rejected.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
                
            case 'updated':
                $baseData['title'] = '✏️ Overtime Request Updated';
                $baseData['message'] = $user->name . ' updated their overtime request for ' . 
                    Carbon::parse($this->overtimeRequest->date)->format('d M Y') . 
                    ' to ' . number_format($this->overtimeRequest->overtime_hours, 1) . ' hours.';
                break;
                
            case 'cancelled':
                $baseData['title'] = '🗑️ Overtime Request Cancelled';
                $baseData['message'] = $user->name . ' cancelled their overtime request for ' . 
                    Carbon::parse($this->overtimeRequest->date)->format('d M Y');
                break;
                
            case 'reminder':
                $baseData['title'] = '⏰ Pending Overtime Request Reminder';
                $baseData['message'] = 'You have a pending overtime request from ' . 
                    $user->name . ' for ' . number_format($this->overtimeRequest->overtime_hours, 1) . 
                    ' hours. Please review it.';
                break;
        }

        return $baseData;
    }
    
    /**
     * Get urgency level based on overtime hours
     */
    // private function getUrgencyLevel($hours)
    // {
    //     if ($hours >= 4) {
    //         return 'high';
    //     } elseif ($hours >= 2) {
    //         return 'medium';
    //     }
    //     return 'low';
    // }
}