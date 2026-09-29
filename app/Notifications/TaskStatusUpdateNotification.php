<?php
// app/Notifications/TaskStatusUpdateNotification.php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusUpdateNotification extends Notification
{
    use Queueable;

    protected $task;
    protected $actionBy;
    protected $oldStatus;
    protected $newStatus;
    protected $remarks;
    protected $title;
    protected $message;

    /**
     * $title/$message are the exact wording the push used
     * (TaskNotificationService::statusMessage), so the bell list and the push
     * never disagree. Built here when not given.
     */
    public function __construct(Task $task, User $actionBy, $oldStatus, $newStatus, $remarks = null, ?string $title = null, ?string $message = null)
    {
        $this->task = $task;
        $this->actionBy = $actionBy;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->remarks = $remarks;

        if ($title === null || $message === null) {
            [$title, $message] = TaskNotificationService::statusMessage((string) $task->title, (string) $actionBy->name, $newStatus, $remarks);
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
        // A group member's own "completed" isn't the task completing — type
        // follows the whole task's status there (approval decisions excepted).
        $typeStatus = $this->newStatus;
        if ($this->task->task_mode === 'group' && ! in_array($this->newStatus, ['approved', 'rejected'], true)) {
            $typeStatus = $this->task->status;
        }

        $updateType = match ($typeStatus) {
            'approved' => 'task_approved',
            'rejected' => 'task_rejected',
            'completed' => 'task_completed',
            default => 'task_status_updated',
        };

        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $updateType,
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'task_code' => $this->task->task_code,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'new_status_label' => TaskNotificationService::statusLabel($this->newStatus),
            'remarks' => $this->remarks,
            'action_by_name' => $this->actionBy->name,
            'action_by_id' => $this->actionBy->id,
            'created_at' => now()->toDateTimeString()
        ];
    }
}
