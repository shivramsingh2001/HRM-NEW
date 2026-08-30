<?php
// app/Notifications/TaskAssignedNotification.php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $assignedBy;

    public function __construct(Task $task, User $assignedBy)
    {
        $this->task = $task;
        $this->assignedBy = $assignedBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => '📋 New Task Assigned',
            'message' => $this->assignedBy->name  . ' assigned you a task: ' . $this->task->title,
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_code' => $this->task->task_code,
            'task_title' => $this->task->title,
            'priority' => $this->task->priority,
            'deadline_date' => $this->task->deadline_date,
            'assigned_by_name' => $this->assignedBy->name,
            'assigned_by_id' => $this->assignedBy->id,
            'created_at' => now()->toDateTimeString()
        ];
    }
}