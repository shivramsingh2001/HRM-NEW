<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskReportController extends Controller
{
    /**
     * Task & Project completion report: overall stats, per-employee
     * workload, and per-project progress for the selected month.
     */
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $request->get('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);
        $departmentFilter = $request->get('department', '');

        $tasksQuery = Task::whereYear('task_date', $year)->whereMonth('task_date', $monthNum);

        $totalTasks = (clone $tasksQuery)->count();
        $completedTasks = (clone $tasksQuery)->whereIn('status', ['completed', 'approved'])->count();
        $overdueTasks = (clone $tasksQuery)->overdue()->count();
        $pendingTasks = (clone $tasksQuery)->where('status', 'pending')->count();
        $inProgressTasks = (clone $tasksQuery)->where('status', 'in_progress')->count();
        $rejectedTasks = (clone $tasksQuery)->where('status', 'rejected')->count();

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        // Per-employee workload — one row per assignee with their task counts.
        $workloadQuery = DB::table('task_assigns')
            ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
            ->join('users', 'task_assigns.assigned_to', '=', 'users.id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->where('tasks.tenant_id', $tenantId)
            ->whereYear('tasks.task_date', $year)
            ->whereMonth('tasks.task_date', $monthNum);

        if ($departmentFilter) {
            $workloadQuery->where('departments.id', $departmentFilter);
        }

        $workload = $workloadQuery
            ->select(
                'users.id as user_id',
                'users.name',
                'users.employee_id',
                'departments.name as department_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tasks.status IN ('completed','approved') THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN tasks.status NOT IN ('completed','approved','cancelled','rejected') AND tasks.deadline_date < CURDATE() THEN 1 ELSE 0 END) as overdue"),
                DB::raw("SUM(CASE WHEN tasks.status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN tasks.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            )
            ->groupBy('users.id', 'users.name', 'users.employee_id', 'departments.name')
            ->orderByDesc('total')
            ->get();

        // Per-project progress for projects active this month.
        $projects = DB::table('projects')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['ongoing', 'pending', 'hold'])
            ->select('id', 'project_code', 'name', 'progress_percentage', 'deadline_date', 'status')
            ->orderByDesc('progress_percentage')
            ->limit(10)
            ->get();

        $departments = Department::where('tenant_id', $tenantId)->select('id', 'name')->get();

        return view('client.report.task-project', compact(
            'totalTasks',
            'completedTasks',
            'overdueTasks',
            'pendingTasks',
            'inProgressTasks',
            'rejectedTasks',
            'completionRate',
            'workload',
            'projects',
            'departments',
            'month',
            'departmentFilter'
        ));
    }
}
