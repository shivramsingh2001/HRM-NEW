<?php
// app/Notifications/TaskStatusUpdateNotification.php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class TaskStatusUpdateNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $actionBy;
    protected $oldStatus;
    protected $newStatus;
    protected $remarks;

    public function __construct(Task $task, User $actionBy, $oldStatus, $newStatus, $remarks = null)
    {
        $this->task = $task;
        $this->actionBy = $actionBy;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->remarks = $remarks;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        // Determine the type of status update
        $updateType = 'task_status_updated';
        $action = 'updated';
        
        if ($this->newStatus === 'approved') {
            $updateType = 'task_approved';
            $action = 'approved';
        } elseif ($this->newStatus === 'rejected') {
            $updateType = 'task_rejected';
            $action = 'rejected';
        } elseif ($this->newStatus === 'completed') {
            $updateType = 'task_completed';
            $action = 'completed';
        }

        return [
            'title'=>'🔄 Task Status Updated',
            'message' => 'Task "' . $this->task->title . '" ' . $action . ' by ' . $this->actionBy->name,
            'type' => $updateType,
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_code' => $this->task->task_code,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'remarks' => $this->remarks,
            'action_by_name' => $this->actionBy->name,
            'action_by_id' => $this->actionBy->id,
            'created_at' => now()->toDateTimeString()
        ];
    }
}