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
     * $receiver is optional and should be passed explicitly whenever the
     * caller already knows who this notification concerns — e.g. a group
     * task approval looping over each member. Without it, this falls back to
     * an arbitrary TaskAssign row for the task, which is only correct for a
     * genuinely 1:1 individual task (a group task has multiple rows, so
     * guessing "the" row is wrong).
     */
    public function notifyTaskStatusUpdate($task, $actionBy, $oldStatus, $newStatus, $remarks = null, $receiver = null)
    {
        try {
            $action = $newStatus === 'approved' ? 'approved' : ($newStatus === 'rejected' ? 'rejected' : 'updated');

            if ($receiver) {
                $action = $receiver->id == $actionBy->id ? 'updated' : $action;
            } else {
                $assign = TaskAssign::where('task_id', $task->id)
                    ->where(function ($q) use ($actionBy) {
                        $q->where('assigned_to', $actionBy->id)->orWhere('assigned_by', $actionBy->id);
                    })
                    ->first();
                if (!$assign) return false;

                if ($actionBy->id == $assign->assigned_to) {
                    // If assignee updated, notify assigner
                    $receiver = User::find($assign->assigned_by);
                    $action = 'updated';
                } else {
                    // If assigner approved/rejected, notify assignee
                    $receiver = User::find($assign->assigned_to);
                    $action = $newStatus === 'approved' ? 'approved' : 'rejected';
                }
            }

            if (!$receiver) return false;

            // Prepare notification data
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

            // Set title and body based on who performed the action
            if ($action === 'updated') {
                $title = '🔄 Task Status Updated';
                $body = $actionBy->name . ' updated task "' . $task->title . '" to ' . $newStatus;
            } elseif ($action === 'approved') {
                $title = '✅ Task Approved';
                $body = 'Your task "' . $task->title . '" has been approved.';
            } else {
                $title = '❌ Task Rejected';
                $body = 'Your task "' . $task->title . '" has been rejected.';
            }

            if ($remarks) {
                $body .= ' Remarks: ' . $remarks;
            }

            // Send FCM notification
            $this->sendNotification($receiver, $title, $body, $data);
            
            // Store in database
            $receiver->notify(new TaskStatusUpdateNotification($task, $actionBy, $oldStatus, $newStatus, $remarks));

            Log::info('Task status update notification sent', [
                'task_id' => $task->id,
                'receiver_id' => $receiver->id,
                'new_status' => $newStatus
            ]);

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