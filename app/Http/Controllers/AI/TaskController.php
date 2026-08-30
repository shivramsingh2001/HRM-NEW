<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\UserJobDetail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            // Check if user has valid role
            if (!in_array($authUser->role, ['admin', 'hr', 'manager', 'employee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Invalid role.'
                ], 200);
            }

            // Base query for tasks with essential relationships
            $query = Task::with([
                'project:id,name,project_code',
                'assignments.assignedBy:id,name,employee_id,email,role',
                'assignments.assignedTo:id,name,employee_id,email,role',
                'updates.updatedBy:id,name',
                'approvals.approvedBy:id,name'
            ]);

            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    // Admin/HR: See all tasks
                    // No additional filters
                    break;

                case 'manager':
                    // Manager: See tasks they created + tasks assigned to their team + tasks assigned to themselves
                    $query->where(function ($q) use ($authUser) {
                        // Tasks assigned by this manager
                        $q->whereHas('assignments', function ($subQ) use ($authUser) {
                            $subQ->where('assigned_by', $authUser->id);
                        })
                            // Tasks assigned to team members (users reporting to this manager)
                            ->orWhereHas('assignments', function ($subQ) use ($authUser) {
                                $subQ->whereIn('assigned_to', function ($innerQ) use ($authUser) {
                                    $innerQ->select('user_id')
                                        ->from('user_job_details')
                                        ->where('reporting_head', $authUser->id);
                                });
                            })
                            // Tasks assigned to manager themselves
                            ->orWhereHas('assignments', function ($subQ) use ($authUser) {
                                $subQ->where('assigned_to', $authUser->id);
                            });
                    });
                    break;

                case 'employee':
                    // Employee: See only tasks assigned to them
                    $query->whereHas('assignments', function ($q) use ($authUser) {
                        $q->where('assigned_to', $authUser->id);
                    });
                    break;
            }

            // Get all tasks (no pagination)
            $tasks = $query->orderBy('created_at', 'desc')->get();

            // Format the tasks
            $formattedTasks = $this->formatTasks($tasks, $authUser);

            // Get summary statistics
            $summary = $this->getTaskSummary($authUser);

            // Separate tasks by category for convenience
            $tasksByCategory = [
                'assigned_by_me' => $formattedTasks->where('is_assigned_by_me', true)->values(),
                'assigned_to_me' => $formattedTasks->where('is_assigned_to_me', true)->values(),
                'team_tasks' => $formattedTasks->where('user_relationship', 'team_task')->values(),
                'others' => $formattedTasks->where('user_relationship', 'other')->values(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Tasks fetched successfully',
                'data' => 
                    // 'all_tasks' => $formattedTasks,
                     $tasksByCategory,
                'summary' => $summary,
                
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'.$e->getMessage()
            ], 500);
        }
    }

    /**
     * Format tasks with all necessary information
     */
    private function formatTasks($tasks, $authUser)
    {
        return $tasks->map(function ($task) use ($authUser) {
            $assignment = $task->assignments->first();
            $assigner = $assignment?->assignedBy;
            $assignee = $assignment?->assignedTo;
            $latestUpdate = $task->updates->last();
            $approval = $task->approvals->first();

            // Determine user's relationship to this task
            $userRole = $this->getUserTaskRole($task, $authUser, $assigner, $assignee);

            // Calculate days remaining/overdue
            $deadlineStatus = $this->getDeadlineStatus($task);

            // Get update history
            $updateHistory = $task->updates->map(function ($update) {
                return [
                    'id' => $update->id,
                    'status' => $update->status,
                    'remarks' => $update->remarks,
                    'updated_by' => $update->updatedBy?->name,
                    'updated_at' => $update->created_at,
                ];
            })->values();

            return [
                'id' => $task->id,
                'task_code' => $task->task_code,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => $task->status,
                'task_date' => $task->task_date,
                'deadline_date' => $task->deadline_date,

                // Project info
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'code' => $task->project->project_code,
                ] : null,

                // Assignment info
                'assigned_by' => $assigner ? [
                    'id' => $assigner->id,
                    'name' => $assigner->name,
                    'employee_id' => $assigner->employee_id,
                    'email' => $assigner->email,
                    'role' => $assigner->role,
                ] : null,

                'assigned_to' => $assignee ? [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'employee_id' => $assignee->employee_id,
                    'email' => $assignee->email,
                    'role' => $assignee->role,
                ] : null,

                // User's relationship
                'user_relationship' => $userRole,
                'is_assigned_by_me' => $assigner && $assigner->id == $authUser->id,
                'is_assigned_to_me' => $assignee && $assignee->id == $authUser->id,

                // Files
                'file' => $task->file ? asset($task->file) : null,
                'voice_file' => $task->voice_file ? asset($task->voice_file) : null,

                // Status info
                'deadline_status' => $deadlineStatus,
                'days_remaining' => $this->getDaysRemaining($task),
                'is_overdue' => $deadlineStatus === 'overdue',

                // Latest update
                'latest_update' => $latestUpdate ? [
                    'status' => $latestUpdate->status,
                    'remarks' => $latestUpdate->remarks,
                    'updated_by' => $latestUpdate->updatedBy?->name,
                    'updated_at' => $latestUpdate->created_at,
                ] : null,

                // Update history
                'update_history' => $updateHistory,
                'total_updates' => $updateHistory->count(),

                // Approval info
                'approval' => $approval ? [
                    'status' => $approval->status,
                    'remarks' => $approval->remarks,
                    'approved_by' => $approval->approvedBy?->name,
                    'approved_at' => $approval->created_at,
                ] : null,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
            ];
        });
    }

    /**
     * Get user's role in this task
     */
    private function getUserTaskRole($task, $authUser, $assigner, $assignee)
    {
        if ($assigner && $assigner->id == $authUser->id) {
            return 'assigner';
        }

        if ($assignee && $assignee->id == $authUser->id) {
            return 'assignee';
        }

        // Check if user is in manager's team and this task is assigned to team member
        if ($authUser->role === 'manager' && $assignee) {
            $isInTeam = UserJobDetail::where('user_id', $assignee->id)
                ->where('reporting_head', $authUser->id)
                ->exists();

            if ($isInTeam) {
                return 'team_task';
            }
        }

        return 'other';
    }

    /**
     * Get deadline status
     */
    private function getDeadlineStatus($task)
    {
        if (in_array($task->status, ['completed', 'cancelled', 'approved'])) {
            return 'completed';
        }

        $today = now()->startOfDay();
        $deadline = \Carbon\Carbon::parse($task->deadline_date)->startOfDay();

        if ($today->gt($deadline)) {
            return 'overdue';
        } elseif ($today->eq($deadline)) {
            return 'due_today';
        } elseif ($today->diffInDays($deadline) <= 2) {
            return 'due_soon';
        } else {
            return 'on_track';
        }
    }

    /**
     * Get days remaining until deadline
     */
    private function getDaysRemaining($task)
    {
        if (in_array($task->status, ['completed', 'cancelled', 'approved'])) {
            return 0;
        }

        $today = now()->startOfDay();
        $deadline = \Carbon\Carbon::parse($task->deadline_date)->startOfDay();

        if ($today->gt($deadline)) {
            return -$today->diffInDays($deadline);
        } else {
            return $today->diffInDays($deadline);
        }
    }
    private function getTaskSummary($authUser)
    {
        $summary = [
            'total_tasks' => 0,
            'by_status' => [],
            'by_priority' => [],
            'overdue' => 0,
            'due_today' => 0,
            'due_soon' => 0,
        ];

        // Build base query for counts based on role
        $query = Task::query();

        switch ($authUser->role) {
            case 'admin':
            case 'hr':
                // Admin/HR: Count all tasks
                break;

            case 'manager':
                // Manager: Tasks they created + team tasks + their own tasks
                $query->where(function ($q) use ($authUser) {
                    $q->whereHas('assignments', function ($subQ) use ($authUser) {
                        $subQ->where('assigned_by', $authUser->id);
                    })
                        ->orWhereHas('assignments', function ($subQ) use ($authUser) {
                            $subQ->whereIn('assigned_to', function ($innerQ) use ($authUser) {
                                $innerQ->select('user_id')
                                    ->from('user_job_details')
                                    ->where('reporting_head', $authUser->id);
                            });
                        })
                        ->orWhereHas('assignments', function ($subQ) use ($authUser) {
                            $subQ->where('assigned_to', $authUser->id);
                        });
                });
                break;

            case 'employee':
                // Employee: Only tasks assigned to them
                $query->whereHas('assignments', function ($q) use ($authUser) {
                    $q->where('assigned_to', $authUser->id);
                });
                break;
        }

        $tasks = $query->get();

        $summary['total_tasks'] = $tasks->count();
        $summary['by_status'] = $tasks->groupBy('status')->map->count();
        $summary['by_priority'] = $tasks->groupBy('priority')->map->count();

        // Count overdue, due today, due soon
        foreach ($tasks as $task) {
            if (!in_array($task->status, ['completed', 'cancelled', 'approved'])) {
                $deadlineStatus = $this->getDeadlineStatus($task);
                if ($deadlineStatus === 'overdue') {
                    $summary['overdue']++;
                } elseif ($deadlineStatus === 'due_today') {
                    $summary['due_today']++;
                } elseif ($deadlineStatus === 'due_soon') {
                    $summary['due_soon']++;
                }
            }
        }

        return $summary;
    }
}
