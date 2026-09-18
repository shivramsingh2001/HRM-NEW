<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectUpdatePostedNotification extends Notification
{
    use Queueable;

    protected $project;
    protected $update;
    protected $postedBy;

    public function __construct(Project $project, ProjectUpdate $update, User $postedBy)
    {
        $this->project = $project;
        $this->update = $update;
        $this->postedBy = $postedBy;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title' => '📝 Project Update Posted',
            'message' => $this->postedBy->name . ' posted an update on "' . $this->project->name . '"',
            'type' => 'project_updated',
            'project_id' => $this->project->id,
            'project_code' => $this->project->project_code,
            'update_id' => $this->update->id,
            'posted_by_name' => $this->postedBy->name,
            'posted_by_id' => $this->postedBy->id,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
