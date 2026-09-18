<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Covers both "approaching" and "overdue" deadline events via a $kind discriminator. */
class ProjectDeadlineNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $kind;
    protected $days;

    public function __construct(Project $project, string $kind, int $days)
    {
        $this->project = $project;
        $this->kind = $kind;
        $this->days = $days;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $message = $this->kind === 'overdue'
            ? "\"{$this->project->name}\" is {$this->days} day(s) overdue."
            : "\"{$this->project->name}\" is due in {$this->days} day(s).";

        return [
            'title' => $this->kind === 'overdue' ? '🔴 Project Overdue' : '⏰ Project Deadline Approaching',
            'message' => $message,
            'type' => $this->kind === 'overdue' ? 'project_overdue' : 'project_deadline_approaching',
            'project_id' => $this->project->id,
            'project_code' => $this->project->project_code,
            'days' => $this->days,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
