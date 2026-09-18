<?php
// app/Notifications/RequestNotification.php

namespace App\Notifications;

use App\Models\Request as RequestStore;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class RequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $request;
    protected $action;
    protected $remarks;

    public function __construct(RequestStore $request, $action = 'submitted', $remarks = null)
    {
        $this->request = $request;
        $this->action = $action;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $user = $this->request->user;
        $requestType = $this->request->requestType->type_name ?? 'Request';
        
        // Calculate duration
        $startDate = \Carbon\Carbon::parse($this->request->start_date);
        $endDate = \Carbon\Carbon::parse($this->request->end_date);
        $durationDays = $endDate->diffInDays($startDate) + 1;

        $baseData = [
            'type' => 'request',
            'action' => $this->action,
            'request_id' => $this->request->id,
            'user_id' => $user->id ?? null,
            'employee_name' => $user->name ?? 'Unknown',
            'employee_id' => $user->employee_id ?? 'N/A',
            'request_type' => $requestType,
            'request_type_id' => $this->request->request_type_id,
            'start_date' => $this->request->start_date,
            'end_date' => $this->request->end_date,
            'duration_days' => $durationDays,
            'reason' => $this->request->reason,
            'status' => $this->request->status,
            'applied_date' => $this->request->applied_date,
            'created_at' => $this->request->created_at->toDateTimeString()
        ];

        // Add action-specific messages
        switch ($this->action) {
            case 'submitted':
                $baseData['title'] = '📝 New Request Submitted';
                $baseData['message'] = $user->name . ' submitted a ' . $requestType . ' request from ' . 
                    date('d M Y', strtotime($this->request->start_date)) . ' to ' . 
                    date('d M Y', strtotime($this->request->end_date));
                break;
            case 'APPROVED':
                $baseData['title'] = '✅ Request Approved';
                $baseData['message'] = 'Your ' . $requestType . ' request has been approved.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
            case 'REJECTED':
                $baseData['title'] = '❌ Request Rejected';
                $baseData['message'] = 'Your ' . $requestType . ' request has been rejected.';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
            case 'CANCELLED':
                $baseData['title'] = '🚫 Request Cancelled';
                $baseData['message'] = $user->name . ' cancelled their ' . $requestType . ' request from ' .
                    date('d M Y', strtotime($this->request->start_date)) . ' to ' .
                    date('d M Y', strtotime($this->request->end_date));
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
        }

        return $baseData;
    }
}