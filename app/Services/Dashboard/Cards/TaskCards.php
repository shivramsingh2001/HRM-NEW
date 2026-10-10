<?php

namespace App\Services\Dashboard\Cards;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard — Task KPI / chart numbers and the employees with the fewest tasks.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class TaskCards
{
    /** @param array|null $between [start, end] datetimes — only tasks created in that period (null = all) */
    public function statistics(?array $between = null)
    {
        $byStatus = Task::when($between, fn ($q) => $q->whereBetween('created_at', $between))
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $total_tasks = (int) $byStatus->sum();
        $pending_tasks = (int) ($byStatus['pending'] ?? 0);
        $in_progress_tasks = (int) ($byStatus['in_progress'] ?? 0);
        $completed_tasks = (int) ($byStatus['completed'] ?? 0);
        $approved_tasks = (int) ($byStatus['approved'] ?? 0);
        $rejected_tasks = (int) ($byStatus['rejected'] ?? 0);

        // Overdue tasks (deadline passed and not completed)
        $overdue_tasks = Task::where('deadline_date', '<', Carbon::today())
            ->whereNotIn('status', ['completed', 'approved'])
            ->count();

        // Monthly tasks
        $monthly_tasks = Task::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        return [
            'total_tasks' => $total_tasks,
            'pending_tasks' => $pending_tasks,
            'in_progress_tasks' => $in_progress_tasks,
            'completed_tasks' => $completed_tasks,
            'approved_tasks' => $approved_tasks,
            'rejected_tasks' => $rejected_tasks,
            'overdue_tasks' => $overdue_tasks,
            'monthly_tasks' => $monthly_tasks,
        ];
    }

    /** @param array|null $between only tasks created in that period count (employees with none still listed) */
    public function leastTasksEmployees(?array $between = null)
    {
        return User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('task_assigns', 'users.id', '=', 'task_assigns.assigned_to')
            ->leftJoin('tasks', function ($j) use ($between) {
                $j->on('task_assigns.task_id', '=', 'tasks.id');
                if ($between) {
                    $j->whereBetween('tasks.created_at', $between);
                }
            })
            ->select(
                'users.id',
                'users.name',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name as designation_name',
                'departments.name as department_name',
                DB::raw('COUNT(DISTINCT tasks.id) as total_tasks'),
                DB::raw('SUM(CASE WHEN tasks.status = "completed" THEN 1 ELSE 0 END) as completed_tasks')
            )
            ->where('users.status', 1)
            ->where('users.role', '!=', 'admin')
            ->groupBy(
                'users.id',
                'users.name',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name',
                'departments.name'
            )
            ->orderBy('total_tasks', 'asc')
            ->limit(3)
            ->get();
    }
}
