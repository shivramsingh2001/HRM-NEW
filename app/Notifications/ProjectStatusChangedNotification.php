<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Generic project-lifecycle DB notification (status change, milestone completed, risk raised, project created). */
class ProjectStatusChangedNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $type;
    protected $message;

    public function __construct(Project $project, string $type, string $message)
    {
        $this->project = $project;
        $this->type = $type;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Project Update',
            'message' => $this->message,
            'type' => $this->type,
            'project_id' => $this->project->id,
            'project_code' => $this->project->project_code,
            'project_name' => $this->project->name,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
