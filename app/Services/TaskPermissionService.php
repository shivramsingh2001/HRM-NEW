<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\User;

/**
 * Centralizes the Task & Project module's role-tier checks, previously
 * copy-pasted as in_array($authUser->role, [...]) across many controller
 * methods (a change to who counts as "elevated" required editing every call
 * site individually).
 *
 * isElevated() is used only by ProjectController (project-wide visibility /
 * delete rights) and stays a plain role check — projects and tasks are
 * separate RBAC modules with different grants (e.g. manager has full tasks
 * access but only 'view' on projects), so folding this into a single
 * RBAC-backed check risked conflating the two. isManagerTier() is used only
 * by TaskController and now delegates to RbacService: "manager tier" means
 * "can assign tasks to people other than themselves", which maps to holding
 * tasks:create at a scope broader than 'own'.
 */
class TaskPermissionService
{
    public function __construct(private RbacService $rbac)
    {
    }

    /** Admin/HR: full visibility and override rights across all tasks/projects. */
    public function isElevated(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr']);
    }

    /** Can this role assign/manage tasks for people other than themselves. */
    public function isManagerTier(User $user): bool
    {
        $scope = $this->rbac->scopeFor($user, 'tasks', 'create');

        return $scope !== null && $scope !== 'own';
    }

    /**
     * Whether the user may view/comment/attach on a specific task: elevated
     * roles, or a member of the task (assignee or assigner).
     */
    public function canAccessTask(User $user, int $taskId): bool
    {
        if ($this->isElevated($user)) {
            return true;
        }

        return TaskAssign::where('task_id', $taskId)
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)->orWhere('assigned_by', $user->id);
            })->exists();
    }

    /** Whether the user is the one who assigned this task (the only one who may edit/delete it). */
    public function isTaskAssigner(User $user, int $taskId): bool
    {
        return TaskAssign::where('task_id', $taskId)
            ->where('assigned_by', $user->id)
            ->exists();
    }

    /** Whether the user authored a comment/attachment, or is elevated (may delete either). */
    public function canManageOwnedResource(User $user, int $ownerId): bool
    {
        return $ownerId === $user->id || $this->isElevated($user);
    }
}
