<?php
// app/Services/TaskNotificationService.php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskAssign;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusUpdateNotification;
use Illuminate\Support\Facades\Log;

class TaskNotificationService
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Send notification when task is assigned
     */
    public function notifyTaskAssigned($task, $assignedTo, $assignedBy)
    {
        try {
            // A self-assigned task needs no "you assigned yourself" alert.
            if ($assignedBy && $assignedTo->id == $assignedBy->id) {
                return false;
            }

            $data = [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'priority' => $task->priority,
                'deadline' => $task->deadline_date,
                'assigned_by' => $assignedBy->name,
                'type' => 'task_assigned'
            ];

            $title = '📋 New Task Assigned';
            $body = $assignedBy->name . ' assigned you a task: ' . $task->title;

            $this->sendNotification($assignedTo, $title, $body, $data);
            $assignedTo->notify(new TaskAssignedNotification($task, $assignedBy));

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send task assigned notification', [
                'error' => $e->getMessage(),
                'task_id' => $task->id
            ]);
            return false;
        }
    }

    /**
     * Send notification when task status is updated (by assignee OR assigner).
     *
     * The wording always follows $newStatus — "approved"/"rejected" only ever
     * for an actual approval decision, never for In Progress / Hold / etc.
     *
     * $receiver should be passed whenever the caller knows who this concerns
     * (the assigner for an assignee's update; each member for an approval).
     * Without it the receivers are derived from the task's assignment rows by
     * what the status means: an approval decision goes to every assignee, any
     * other update goes to the assigner(s). Nobody is notified about their
     * own action.
     */
    public function notifyTaskStatusUpdate($task, $actionBy, $oldStatus, $newStatus, $remarks = null, $receiver = null, ?string $groupStatus = null)
    {
        try {
            $receivers = $receiver ? collect([$receiver]) : $this->receiversFor($task, $actionBy, $newStatus);
            $receivers = $receivers->filter(fn ($u) => $u && $u->id != $actionBy->id)->unique('id');
            if ($receivers->isEmpty()) {
                return false;
            }

            [$title, $body] = self::statusMessage($task->title, $actionBy->name, $newStatus, $remarks, $groupStatus);

            $data = [
                'task_id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'remarks' => $remarks,
                'action_by' => $actionBy->name,
                'type' => 'task_status_update'
            ];

            foreach ($receivers as $user) {
                // Push and the in-app (database) notification carry the same title/message.
                $this->sendNotification($user, $title, $body, $data);
                $user->notify(new TaskStatusUpdateNotification($task, $actionBy, $oldStatus, $newStatus, $remarks, $title, $body));

                Log::info('Task status update notification sent', [
                    'task_id' => $task->id,
                    'receiver_id' => $user->id,
                    'new_status' => $newStatus
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send task status update notification', [
                'error' => $e->getMessage(),
                'task_id' => $task->id
            ]);
            return false;
        }
    }

    /**
     * [title, body] for a status change — shared by the push and the stored
     * notification so both always say the same thing.
     */
    public static function statusMessage(string $taskTitle, string $actorName, ?string $newStatus, ?string $remarks = null, ?string $groupStatus = null): array
    {
        $label = self::statusLabel($newStatus);

        // A group member changing their own part: say so, plus where the whole task stands.
        [$title, $body] = $groupStatus !== null ? [
            $groupStatus === 'completed' ? '☑️ Group Task Completed' : '👥 Group Task Update',
            "{$actorName} marked their part of \"{$taskTitle}\" as {$label}. Group status: " . self::statusLabel($groupStatus)
                . ($groupStatus === 'completed' ? ' — waiting for your approval.' : '.'),
        ] : match ($newStatus) {
            'approved' => ['✅ Task Approved', "{$actorName} approved your task \"{$taskTitle}\"."],
            'rejected' => ['❌ Task Rejected', "{$actorName} rejected your task \"{$taskTitle}\". It has been reassigned to you for rework."],
            'completed' => ['☑️ Task Completed', "{$actorName} marked task \"{$taskTitle}\" as Completed. It is waiting for your approval."],
            'in_progress' => ['▶️ Task In Progress', "{$actorName} started working on task \"{$taskTitle}\" (In Progress)."],
            'hold' => ['⏸️ Task On Hold', "{$actorName} put task \"{$taskTitle}\" On Hold."],
            'cancelled' => ['🚫 Task Cancelled', "{$actorName} cancelled task \"{$taskTitle}\"."],
            'pending' => ['🔄 Task Status Updated', "{$actorName} moved task \"{$taskTitle}\" back to Pending."],
            default => ['🔄 Task Status Updated', "{$actorName} updated task \"{$taskTitle}\" to {$label}."],
        };

        if ($remarks) {
            $body .= ' Remarks: ' . $remarks;
        }

        return [$title, $body];
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'in_progress' => 'In Progress',
            'hold' => 'On Hold',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    /**
     * Who should hear about $actionBy moving $task to $newStatus, when the
     * caller didn't say: an approval decision → all assignees; anything else
     * → the assigner(s) of the actor's own assignment (or of the task).
     */
    private function receiversFor($task, $actionBy, ?string $newStatus)
    {
        $assigns = TaskAssign::where('task_id', $task->id)->get();

        if (in_array($newStatus, ['approved', 'rejected'], true)) {
            $ids = $assigns->pluck('assigned_to');
        } else {
            $own = $assigns->where('assigned_to', $actionBy->id);
            $ids = ($own->isNotEmpty() ? $own : $assigns)->pluck('assigned_by');
        }

        return User::whereIn('id', $ids->filter()->unique()->all())->get();
    }

    /**
     * Core method to send FCM notification
     */
    private function sendNotification($user, $title, $body, $data = [])
    {
        $tokens = $user->fcm_tokens ?? [];
        
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
            $tokens = is_array($decoded) ? $decoded : [];
        }
        
        if (!is_array($tokens) || empty($tokens)) {
            Log::info('User has no valid FCM tokens', ['user_id' => $user->id]);
            return false;
        }

        $successCount = 0;

        foreach ($tokens as $tokenData) {
            $token = is_array($tokenData) ? ($tokenData['token'] ?? '') : $tokenData;
            
            if (empty($token)) {
                continue;
            }
            
            $result = $this->firebaseService->sendToDevice(
                $token,
                $title,
                $body,
                $data
            );

            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        return $successCount > 0;
    }
}