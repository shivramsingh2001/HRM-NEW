<?php

namespace App\Notifications;

use App\Models\AttendanceRegularization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AttendanceRegularizationNotification extends Notification
{
    use Queueable;

    protected $regularization;
    protected $action;
    protected $remarks;

    public function __construct(AttendanceRegularization $regularization, $action = 'submitted', $remarks = null)
    {
        $this->regularization = $regularization;
        $this->action = $action;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $user = $this->regularization->user;
        
        // Format request type display name
        $requestTypeDisplay = $this->getRequestTypeDisplay($this->regularization->request_type);
        
        // Format time details based on request type
        $timeDetails = $this->formatTimeDetails($this->regularization);

        $baseData = [
            'type' => 'attendance_regularization',
            'action' => $this->action,
            'regularization_id' => $this->regularization->id,
            'user_id' => $user->id ?? null,
            'employee_name' => $user->name ?? 'Unknown',
            'employee_id' => $user->employee_id ?? 'N/A',
            'date' => $this->regularization->date,
            'request_type' => $this->regularization->request_type,
            'request_type_display' => $requestTypeDisplay,
            'in_time' => $this->regularization->in_time,
            'out_time' => $this->regularization->out_time,
            'time_details' => $timeDetails,
            'reason' => $this->regularization->reason,
            'status' => $this->regularization->status,
            'created_at' => $this->regularization->created_at->toDateTimeString()
        ];

        // Add action-specific messages
        switch ($this->action) {
            case 'submitted':
                $baseData['message'] = 'New attendance regularization request from ' . ($user->name ?? 'Unknown');
                $baseData['title'] = '📝 New Regularization Request';
                break;
            case 'approved':
                $baseData['message'] = 'Your attendance regularization request for ' . date('d M Y', strtotime($this->regularization->date)) . ' has been approved.';
                $baseData['title'] = '✅ Regularization Approved';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
            case 'rejected':
                $baseData['message'] = 'Your attendance regularization request for ' . date('d M Y', strtotime($this->regularization->date)) . ' has been rejected.';
                $baseData['title'] = '❌ Regularization Rejected';
                if ($this->remarks) {
                    $baseData['remarks'] = $this->remarks;
                }
                break;
        }

        return $baseData;
    }

    /**
     * Get display name for request type
     */
    private function getRequestTypeDisplay($requestType)
    {
        $types = [
            'missed_punch_in' => 'Missed Punch In',
            'missed_punch_out' => 'Missed Punch Out',
            'wrong_punch_time' => 'Wrong Punch Time',
            'attendance' => 'Attendance Correction'
        ];
        
        return $types[$requestType] ?? ucfirst(str_replace('_', ' ', $requestType));
    }

    /**
     * Format time details based on request type
     */
    private function formatTimeDetails($regularization)
    {
        switch ($regularization->request_type) {
            case 'missed_punch_in':
                return 'In Time: ' . ($regularization->in_time ? date('h:i A', strtotime($regularization->in_time)) : 'N/A');
            case 'missed_punch_out':
                return 'Out Time: ' . ($regularization->out_time ? date('h:i A', strtotime($regularization->out_time)) : 'N/A');
            case 'wrong_punch_time':
                $details = [];
                if ($regularization->in_time) {
                    $details[] = 'Corrected In Time: ' . date('h:i A', strtotime($regularization->in_time));
                }
                if ($regularization->out_time) {
                    $details[] = 'Corrected Out Time: ' . date('h:i A', strtotime($regularization->out_time));
                }
                return implode(', ', $details);
            case 'attendance':
                return 'Full day attendance correction';
            default:
                return 'N/A';
        }
    }
}