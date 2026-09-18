<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Notifications\ProjectDeadlineNotification;
use App\Notifications\ProjectStatusChangedNotification;
use App\Notifications\ProjectUpdatePostedNotification;
use Illuminate\Support\Facades\Log;

/**
 * Follows TaskNotificationService's exact shape: dual-channel send (FCM push
 * + a Laravel DB Notification per event), emoji-prefixed titles, never
 * throws. Recipients are always the project's manager + active team members
 * (via Project::teamMembers()), deduped.
 */
class ProjectNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    private function recipients(Project $project)
    {
        $members = $project->teamMembers()->wherePivot('status', 1)->get();
        if ($project->head) {
            $members->push($project->head);
        }

        return $members->unique('id');
    }

    public function notifyProjectCreated(Project $project): bool
    {
        try {
            $title = '🆕 New Project Created';
            $body = "You've been added to project \"{$project->name}\".";
            $data = ['project_id' => $project->id, 'project_code' => $project->project_code, 'type' => 'project_created'];

            foreach ($this->recipients($project) as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectStatusChangedNotification($project, 'project_created', $body));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send project created notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyStatusChanged(Project $project, string $oldStatus, string $newStatus, User $actionBy): bool
    {
        try {
            $title = '🔄 Project Status Updated';
            $body = "{$actionBy->name} changed \"{$project->name}\" from " . ucfirst($oldStatus) . ' to ' . ucfirst($newStatus) . '.';
            $data = [
                'project_id' => $project->id, 'project_code' => $project->project_code,
                'old_status' => $oldStatus, 'new_status' => $newStatus, 'type' => 'project_status_changed',
            ];

            foreach ($this->recipients($project) as $recipient) {
                if ($recipient->id === $actionBy->id) {
                    continue;
                }
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectStatusChangedNotification($project, 'project_status_changed', $body));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send project status changed notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyProjectUpdated(Project $project, ProjectUpdate $update, User $actionBy): bool
    {
        try {
            $title = '📝 Project Update Posted';
            $body = "{$actionBy->name} posted an update on \"{$project->name}\".";
            $data = ['project_id' => $project->id, 'update_id' => $update->id, 'type' => 'project_updated'];

            foreach ($this->recipients($project) as $recipient) {
                if ($recipient->id === $actionBy->id) {
                    continue;
                }
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectUpdatePostedNotification($project, $update, $actionBy));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send project update notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyMilestoneCompleted(Project $project, ProjectMilestone $milestone): bool
    {
        try {
            $title = '🎯 Milestone Completed';
            $body = "Milestone \"{$milestone->title}\" completed on \"{$project->name}\".";
            $data = ['project_id' => $project->id, 'milestone_id' => $milestone->id, 'type' => 'milestone_completed'];

            foreach ($this->recipients($project) as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectStatusChangedNotification($project, 'milestone_completed', $body));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send milestone completed notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyRiskRaised(Project $project, ProjectRisk $risk): bool
    {
        try {
            $emoji = $risk->type === 'blocker' ? '🚧' : '⚠️';
            $title = "{$emoji} " . ucfirst($risk->type) . ' Raised';
            $body = "{$risk->title} raised on \"{$project->name}\" (severity: " . ucfirst($risk->severity) . ').';
            $data = ['project_id' => $project->id, 'risk_id' => $risk->id, 'type' => 'project_risk_raised'];

            foreach ($this->recipients($project) as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectStatusChangedNotification($project, 'project_risk_raised', $body));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send risk raised notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyDeadlineApproaching(Project $project): bool
    {
        try {
            $daysLeft = (int) \Carbon\Carbon::today()->diffInDays($project->deadline_date, false);
            $title = '⏰ Project Deadline Approaching';
            $body = "\"{$project->name}\" is due in {$daysLeft} day(s).";
            $data = ['project_id' => $project->id, 'days_left' => $daysLeft, 'type' => 'project_deadline_approaching'];

            foreach ($this->recipients($project) as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectDeadlineNotification($project, 'approaching', $daysLeft));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send deadline approaching notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    public function notifyOverdue(Project $project): bool
    {
        try {
            $daysOverdue = (int) $project->deadline_date->diffInDays(\Carbon\Carbon::today());
            $title = '🔴 Project Overdue';
            $body = "\"{$project->name}\" is {$daysOverdue} day(s) overdue.";
            $data = ['project_id' => $project->id, 'days_overdue' => $daysOverdue, 'type' => 'project_overdue'];

            foreach ($this->recipients($project) as $recipient) {
                $this->sendNotification($recipient, $title, $body, $data);
                $recipient->notify(new ProjectDeadlineNotification($project, 'overdue', $daysOverdue));
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send overdue notification', ['error' => $e->getMessage(), 'project_id' => $project->id]);
            return false;
        }
    }

    /** Core method to send FCM notification — identical to TaskNotificationService's. */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];

        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($tokens) || empty($tokens)) {
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;

            if (empty($token)) {
                continue;
            }

            $result = $this->firebaseService->sendToDevice($token, $title, $body, $data);

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}
