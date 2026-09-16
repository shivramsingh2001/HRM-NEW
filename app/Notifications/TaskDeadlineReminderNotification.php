<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDeadlineReminderNotification extends Notification
{
    use Queueable;

    protected $taskId;
    protected $taskCode;
    protected $taskTitle;
    protected $deadlineDate;
    protected $isOverdue;

    public function __construct(int $taskId, ?string $taskCode, string $taskTitle, string $deadlineDate, bool $isOverdue)
    {
        $this->taskId = $taskId;
        $this->taskCode = $taskCode;
        $this->taskTitle = $taskTitle;
        $this->deadlineDate = $deadlineDate;
        $this->isOverdue = $isOverdue;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => $this->isOverdue ? '⚠️ Task Overdue' : '⏰ Task Deadline Approaching',
            'message' => $this->isOverdue
                ? 'Task "' . $this->taskTitle . '" is overdue (was due ' . $this->deadlineDate . ').'
                : 'Task "' . $this->taskTitle . '" is due tomorrow (' . $this->deadlineDate . ').',
            'type' => 'task_deadline_reminder',
            'task_id' => $this->taskId,
            'task_code' => $this->taskCode,
            'task_title' => $this->taskTitle,
            'deadline_date' => $this->deadlineDate,
            'is_overdue' => $this->isOverdue,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
