<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskApproval;
use App\Models\TaskUpdate;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskReportController extends Controller
{
    /* ============================================================
     |  REPORT 0 : TASK & PROJECT OVERVIEW
     ============================================================ */

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

    /* ============================================================
     |  ACCESS CONTROL / HELPERS
     ============================================================ */

    /**
     * Only manager / admin / hr can access task reports.
     */
    private function authorizeReportAccess()
    {
        $authUser = Auth::user();

        if (!in_array($authUser->role, ['manager', 'admin', 'hr'])) {
            abort(403, 'Unauthorized access to task reports.');
        }

        return $authUser;
    }

    /**
     * Users visible to logged-in user:
     *  - admin/hr : all active users
     *  - manager  : self + direct reporting members
     */
    private function getVisibleUserIds($authUser): array
    {
        if (in_array($authUser->role, ['admin', 'hr'])) {
            return User::where('status', 1)->pluck('id')->toArray();
        }

        $ids   = User::managedBy($authUser->id)->pluck('id')->toArray();
        $ids[] = $authUser->id;

        return array_values(array_unique($ids));
    }

    /**
     * Date range parser (defaults to current month).
     */
    private function resolveDateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfMonth();

        return [$from, $to];
    }

    /**
     * Projects dropdown (role-aware).
     */
    private function getProjectList($authUser)
    {
        $userId = $authUser->id;

        $query = Project::whereIn('projects.status', ['ongoing', 'pending']);

        if ($authUser->role !== 'admin' && $authUser->role !== 'hr') {
            $query->leftJoin('project_assigns', function ($join) use ($userId) {
                $join->on('projects.id', '=', 'project_assigns.project_id')
                    ->where('project_assigns.user_id', $userId)
                    ->where('project_assigns.status', 1);
            })
                ->where(function ($q) use ($userId) {
                    $q->where('projects.project_head', $userId)
                        ->orWhereNotNull('project_assigns.id');
                });
        }

        return $query->select('projects.id', 'projects.name', 'projects.project_code')
            ->distinct()
            ->orderBy('projects.name')
            ->get();
    }

    /**
     * Users dropdown (role-aware).
     */
    private function getAllUsersForFilter($authUser)
    {
        $query = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->select('users.id', 'users.name', 'users.employee_id', 'users.email')
            ->where('users.status', 1);

        if (in_array($authUser->role, ['admin', 'hr'])) {
            return $query->orderBy('users.name')->get();
        }

        $ids   = User::managedBy($authUser->id)->pluck('id')->toArray();
        $ids[] = $authUser->id;

        return $query->whereIn('users.id', $ids)->orderBy('users.name')->get();
    }

    /**
     * Build employee-monthly report data (shared by view + export).
     */
    private function buildEmployeeMonthlyData(Request $request, $authUser): array
    {
        [$from, $to] = $this->resolveDateRange($request);
        $visibleUserIds = $this->getVisibleUserIds($authUser);

        $filterUserId  = $request->filled('user_id')    && $request->user_id    != 'all' ? $request->user_id    : null;
        $filterProject = $request->filled('project_id') && $request->project_id != 'all' ? $request->project_id : null;

        $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image'
            )
            ->whereIn('users.id', $visibleUserIds)
            ->where('users.status', 1)
            ->when($filterUserId, fn ($q) => $q->where('users.id', $filterUserId))
            ->orderBy('users.name')
            ->get();

        $report = [];
        foreach ($users as $u) {
            $report[$u->id] = [
                'user'        => $u,
                'total'       => 0,
                'pending'     => 0,
                'in_progress' => 0,
                'hold'        => 0,
                'completed'   => 0,
                'approved'    => 0,
                'rejected'    => 0,
                'cancelled'   => 0,
            ];
        }

        if ($users->isNotEmpty()) {
            $userIds = $users->pluck('id')->toArray();

            $stats = DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $userIds)
                ->whereBetween('tasks.task_date', [$from->toDateString(), $to->toDateString()])
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('task_assigns.assigned_to', 'tasks.status')
                ->select(
                    'task_assigns.assigned_to as user_id',
                    'tasks.status',
                    DB::raw('COUNT(DISTINCT tasks.id) as total')
                )
                ->get();

            foreach ($stats as $row) {
                if (!isset($report[$row->user_id])) continue;
                $report[$row->user_id]['total'] += (int) $row->total;
                if (array_key_exists($row->status, $report[$row->user_id])) {
                    $report[$row->user_id][$row->status] = (int) $row->total;
                }
            }
        }

        $grandTotal = [
            'total'       => array_sum(array_column($report, 'total')),
            'pending'     => array_sum(array_column($report, 'pending')),
            'in_progress' => array_sum(array_column($report, 'in_progress')),
            'hold'        => array_sum(array_column($report, 'hold')),
            'completed'   => array_sum(array_column($report, 'completed')),
            'approved'    => array_sum(array_column($report, 'approved')),
            'rejected'    => array_sum(array_column($report, 'rejected')),
            'cancelled'   => array_sum(array_column($report, 'cancelled')),
        ];

        return [$report, $grandTotal, $from, $to];
    }

    /**
     * Build day-wise report data (shared by view + export).
     */
    private function buildDayWiseData(Request $request, $authUser): array
    {
        [$from, $to] = $this->resolveDateRange($request);
        $visibleUserIds = $this->getVisibleUserIds($authUser);

        $filterUserId  = $request->filled('user_id')    && $request->user_id    != 'all' ? $request->user_id    : null;
        $filterProject = $request->filled('project_id') && $request->project_id != 'all' ? $request->project_id : null;
        $filterStatus  = $request->filled('status')     && $request->status     != 'all' ? $request->status     : null;

        $rows = DB::table('task_assigns')
            ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
            ->whereIn('task_assigns.assigned_to', $visibleUserIds)
            ->whereBetween('tasks.task_date', [$from->toDateString(), $to->toDateString()])
            ->when($filterUserId,  fn ($q) => $q->where('task_assigns.assigned_to', $filterUserId))
            ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
            ->when($filterStatus,  fn ($q) => $q->where('tasks.status', $filterStatus))
            ->groupBy('tasks.task_date', 'tasks.status')
            ->select(
                'tasks.task_date as report_date',
                'tasks.status',
                DB::raw('COUNT(DISTINCT tasks.id) as total')
            )
            ->orderBy('tasks.task_date', 'desc')
            ->get();

        $pivot = [];
        foreach ($rows as $row) {
            $date = $row->report_date;
            if (!isset($pivot[$date])) {
                $pivot[$date] = [
                    'date'        => $date,
                    'total'       => 0,
                    'pending'     => 0,
                    'in_progress' => 0,
                    'hold'        => 0,
                    'completed'   => 0,
                    'approved'    => 0,
                    'rejected'    => 0,
                    'cancelled'   => 0,
                ];
            }
            $pivot[$date]['total'] += (int) $row->total;
            if (array_key_exists($row->status, $pivot[$date])) {
                $pivot[$date][$row->status] = (int) $row->total;
            }
        }

        // Fill zero rows for full date range
        $complete = [];
        foreach (Carbon::parse($from)->daysUntil($to) as $day) {
            $key = $day->toDateString();
            $complete[$key] = $pivot[$key] ?? [
                'date'        => $key,
                'total'       => 0,
                'pending'     => 0,
                'in_progress' => 0,
                'hold'        => 0,
                'completed'   => 0,
                'approved'    => 0,
                'rejected'    => 0,
                'cancelled'   => 0,
            ];
        }
        krsort($complete);
        $report = array_values($complete);

        $grandTotal = [
            'total'       => array_sum(array_column($report, 'total')),
            'pending'     => array_sum(array_column($report, 'pending')),
            'in_progress' => array_sum(array_column($report, 'in_progress')),
            'hold'        => array_sum(array_column($report, 'hold')),
            'completed'   => array_sum(array_column($report, 'completed')),
            'approved'    => array_sum(array_column($report, 'approved')),
            'rejected'    => array_sum(array_column($report, 'rejected')),
            'cancelled'   => array_sum(array_column($report, 'cancelled')),
        ];

        return [$report, $grandTotal, $from, $to];
    }

    /**
     * Build monthly task detail data (shared by view + export).
     */
    private function buildMonthlyDetailData(Request $request, $authUser, bool $paginate = true)
    {
        [$from, $to] = $this->resolveDateRange($request);
        $visibleUserIds = $this->getVisibleUserIds($authUser);

        $query = Task::query()
            ->select([
                'tasks.id',
                'tasks.task_code',
                'tasks.title',
                'tasks.description',
                'tasks.priority',
                'tasks.status',
                'tasks.task_date',
                'tasks.deadline_date',
                'tasks.task_mode',
                'tasks.file',
                'tasks.voice_file',
                'tasks.created_at',
                'projects.name as project_name',
                'projects.project_code',
            ])
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->whereBetween('tasks.task_date', [$from->toDateString(), $to->toDateString()])
            ->whereExists(function ($q) use ($visibleUserIds) {
                $q->select(DB::raw(1))
                    ->from('task_assigns')
                    ->whereColumn('task_assigns.task_id', 'tasks.id')
                    ->where(function ($sub) use ($visibleUserIds) {
                        $sub->whereIn('task_assigns.assigned_by', $visibleUserIds)
                            ->orWhereIn('task_assigns.assigned_to', $visibleUserIds);
                    });
            });

        if ($request->filled('status') && $request->status != 'all') {
            $query->where('tasks.status', $request->status);
        }
        if ($request->filled('priority') && $request->priority != 'all') {
            $query->where('tasks.priority', $request->priority);
        }
        if ($request->filled('project_id') && $request->project_id != 'all') {
            $query->where('tasks.project_id', $request->project_id);
        }
        if ($request->filled('assigned_to') && $request->assigned_to != 'all') {
            $query->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))->from('task_assigns')
                    ->whereColumn('task_assigns.task_id', 'tasks.id')
                    ->where('task_assigns.assigned_to', $request->assigned_to);
            });
        }
        if ($request->filled('assigned_by') && $request->assigned_by != 'all') {
            $query->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))->from('task_assigns')
                    ->whereColumn('task_assigns.task_id', 'tasks.id')
                    ->where('task_assigns.assigned_by', $request->assigned_by);
            });
        }

        $query->orderBy('tasks.task_date', 'desc')->orderBy('tasks.id', 'desc');

        $tasks = $paginate
            ? $query->paginate(25)->appends($request->query())
            : $query->get();

        $taskIds = $tasks->pluck('id')->toArray();

        // Assigners
        $assignersMap = DB::table('task_assigns')
            ->leftJoin('users', 'task_assigns.assigned_by', '=', 'users.id')
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->whereIn('task_assigns.task_id', $taskIds)
            ->select(
                'task_assigns.task_id',
                'users.id as user_id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image'
            )
            ->distinct()
            ->get()
            ->groupBy('task_id');

        // Assignees
        $assigneesMap = DB::table('task_assigns')
            ->leftJoin('users', 'task_assigns.assigned_to', '=', 'users.id')
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->whereIn('task_assigns.task_id', $taskIds)
            ->select(
                'task_assigns.task_id',
                'task_assigns.member_role',
                'task_assigns.individual_status',
                'task_assigns.individual_remarks',
                'task_assigns.started_at',
                'task_assigns.completed_at',
                'users.id as user_id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image'
            )
            ->get()
            ->groupBy('task_id');

        // Updates
        $updatesMap = TaskUpdate::select([
            'task_updates.id',
            'task_updates.task_id',
            'task_updates.status',
            'task_updates.remarks',
            'task_updates.deadline_extension',
            'task_updates.old_deadline',
            'task_updates.new_deadline',
            'task_updates.created_at',
            'users.name as updated_by_name',
            'users.email as updated_by_email',
            'user_basic_details.profile_image as updated_by_image',
        ])
            ->leftJoin('users', 'task_updates.updated_by', '=', 'users.id')
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->whereIn('task_updates.task_id', $taskIds)
            ->orderBy('task_updates.created_at', 'asc')
            ->get()
            ->groupBy('task_id');

        // Approvals
        $approvalsMap = TaskApproval::select([
            'task_approvals.id',
            'task_approvals.task_id',
            'task_approvals.status as approval_status',
            'task_approvals.remarks',
            'task_approvals.created_at',
            'approver.name as approved_by_name',
            'approver.email as approved_by_email',
            'user_basic_details.profile_image as approved_by_image',
        ])
            ->leftJoin('users as approver', 'task_approvals.approved_by', '=', 'approver.id')
            ->leftJoin('user_basic_details', 'approver.id', '=', 'user_basic_details.user_id')
            ->whereIn('task_approvals.task_id', $taskIds)
            ->orderBy('task_approvals.created_at', 'desc')
            ->get()
            ->groupBy('task_id');

        foreach ($tasks as $task) {
            $task->assigners    = $assignersMap->get($task->id, collect());
            $task->assignees    = $assigneesMap->get($task->id, collect());
            $task->updates      = $updatesMap->get($task->id, collect());
            $task->approvals    = $approvalsMap->get($task->id, collect());
            $task->update_count = $task->updates->count();
        }

        return [$tasks, $from, $to];
    }

    /* ============================================================
     |  REPORT 1 : EMPLOYEE MONTHLY
     ============================================================ */

    public function employeeMonthlyReport(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();
            [$from, $to] = $this->resolveDateRange($request);

            $visibleUserIds = $this->getVisibleUserIds($authUser);

            $filterUserId  = $request->filled('user_id') && $request->user_id != 'all' ? $request->user_id : null;
            $filterProject = $request->filled('project_id') && $request->project_id != 'all' ? $request->project_id : null;

            // ---- PAGINATED USERS ----
            $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.employee_id',
                    'user_basic_details.profile_image'
                )
                ->whereIn('users.id', $visibleUserIds)
                ->where('users.status', 1)
                ->when($filterUserId, fn ($q) => $q->where('users.id', $filterUserId))
                ->orderBy('users.name')
                ->paginate(20)
                ->appends($request->query());

            $userIds = collect($users->items())->pluck('id')->toArray();

            // ---- AGGREGATED STATS for the current page only ----
            $stats = empty($userIds) ? collect() : DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $userIds)
                ->whereBetween('tasks.task_date', [$from->toDateString(), $to->toDateString()])
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('task_assigns.assigned_to', 'tasks.status')
                ->select(
                    'task_assigns.assigned_to as user_id',
                    'tasks.status',
                    DB::raw('COUNT(DISTINCT tasks.id) as total')
                )
                ->get();

            // Build per-user map
            $statsMap = [];
            foreach ($stats as $row) {
                $statsMap[$row->user_id]['total'] = ($statsMap[$row->user_id]['total'] ?? 0) + (int) $row->total;
                $statsMap[$row->user_id][$row->status] = (int) $row->total;
            }

            // Attach stats to each user object
            foreach ($users as $u) {
                $u->stat_total       = $statsMap[$u->id]['total']       ?? 0;
                $u->stat_pending     = $statsMap[$u->id]['pending']     ?? 0;
                $u->stat_in_progress = $statsMap[$u->id]['in_progress'] ?? 0;
                $u->stat_hold        = $statsMap[$u->id]['hold']        ?? 0;
                $u->stat_completed   = $statsMap[$u->id]['completed']   ?? 0;
                $u->stat_approved    = $statsMap[$u->id]['approved']    ?? 0;
                $u->stat_rejected    = $statsMap[$u->id]['rejected']    ?? 0;
                $u->stat_cancelled   = $statsMap[$u->id]['cancelled']   ?? 0;
            }

            // ---- GLOBAL TOTALS (all matching users, not just current page) ----
            $allUserIds = User::whereIn('id', $visibleUserIds)
                ->where('status', 1)
                ->when($filterUserId, fn ($q) => $q->where('id', $filterUserId))
                ->pluck('id')
                ->toArray();

            $globalStats = empty($allUserIds) ? collect() : DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $allUserIds)
                ->whereBetween('tasks.task_date', [$from->toDateString(), $to->toDateString()])
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('tasks.status')
                ->select('tasks.status', DB::raw('COUNT(DISTINCT tasks.id) as total'))
                ->get();

            $grandTotal = [
                'total'       => $globalStats->sum('total'),
                'pending'     => $globalStats->where('status', 'pending')->sum('total'),
                'in_progress' => $globalStats->where('status', 'in_progress')->sum('total'),
                'hold'        => $globalStats->where('status', 'hold')->sum('total'),
                'completed'   => $globalStats->where('status', 'completed')->sum('total'),
                'approved'    => $globalStats->where('status', 'approved')->sum('total'),
                'rejected'    => $globalStats->where('status', 'rejected')->sum('total'),
                'cancelled'   => $globalStats->where('status', 'cancelled')->sum('total'),
            ];

            $projectList = $this->getProjectList($authUser);
            $allUsers    = $this->getAllUsersForFilter($authUser);

            return view('client.report.task.employee-monthly', compact(
                'users',
                'grandTotal',
                'from',
                'to',
                'authUser',
                'projectList',
                'allUsers'
            ));
        } catch (Exception $e) {
            Log::error('employeeMonthlyReport error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function exportEmployeeMonthlyCsv(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();
            [$report, , $from, $to] = $this->buildEmployeeMonthlyData($request, $authUser);

            $filename = 'employee-monthly-report_'
                . $from->format('Ymd') . '_to_' . $to->format('Ymd') . '.csv';

            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($report) {
                $out = fopen('php://output', 'w');

                // Optional UTF-8 BOM for Excel compatibility
                fputs($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'Employee', 'Employee ID', 'Email',
                    'Total', 'Pending', 'In Progress', 'Hold',
                    'Completed', 'Approved', 'Rejected', 'Cancelled',
                ]);

                foreach ($report as $r) {
                    $u = $r['user'];
                    fputcsv($out, [
                        $u->name,
                        $u->employee_id,
                        $u->email,
                        $r['total'],
                        $r['pending'],
                        $r['in_progress'],
                        $r['hold'],
                        $r['completed'],
                        $r['approved'],
                        $r['rejected'],
                        $r['cancelled'],
                    ]);
                }

                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('exportEmployeeMonthlyCsv error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Export failed.');
        }
    }

    /* ============================================================
     |  REPORT 2 : MONTHLY TASK DETAIL
     ============================================================ */

    public function monthlyTaskDetailReport(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();

            [$tasks, $from, $to] = $this->buildMonthlyDetailData($request, $authUser, true);

            $projectList = $this->getProjectList($authUser);
            $allUsers    = $this->getAllUsersForFilter($authUser);

            return view('client.report.task.monthly-task-detail', compact(
                'tasks',
                'from',
                'to',
                'authUser',
                'projectList',
                'allUsers'
            ));
        } catch (Exception $e) {
            Log::error('monthlyTaskDetailReport error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function exportMonthlyDetailCsv(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();
            [$tasks, $from, $to] = $this->buildMonthlyDetailData($request, $authUser, false);

            $filename = 'monthly-task-detail_'
                . $from->format('Ymd') . '_to_' . $to->format('Ymd') . '.csv';

            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($tasks) {
                $out = fopen('php://output', 'w');
                fputs($out, "\xEF\xBB\xBF"); // BOM

                fputcsv($out, [
                    'Task Code', 'Title', 'Description', 'Project', 'Project Code',
                    'Priority', 'Status', 'Task Mode', 'Task Date', 'Deadline',
                    'Assigned By', 'Assigned To', 'Member Statuses', 'Update Count',
                    'Last Update Status', 'Last Update Remarks', 'Last Update By', 'Last Update At',
                    'Approval Status', 'Approval Remarks', 'Approved By',
                    'Document File URL', 'Voice File URL',
                ]);

                foreach ($tasks as $t) {
                    $assignedBy = $t->assigners->pluck('name')->unique()->filter()->implode(', ') ?: '-';
                    $assignedTo = $t->assignees->pluck('name')->unique()->filter()->implode(', ') ?: '-';

                    $memberStatuses = $t->assignees
                        ->map(fn ($a) => ($a->name ?? '-') . ': ' . ucfirst(str_replace('_', ' ', $a->individual_status ?? '-')))
                        ->implode(' | ') ?: '-';

                    $lastUpdate   = $t->updates->sortByDesc('created_at')->first();
                    $lastApproval = $t->approvals->sortByDesc('created_at')->first();

                    $fileUrl      = !empty($t->file)       ? url($t->file)       : '';
                    $voiceFileUrl = !empty($t->voice_file) ? url($t->voice_file) : '';

                    fputcsv($out, [
                        $t->task_code,
                        $t->title,
                        strip_tags((string) $t->description),
                        $t->project_name ?? '-',
                        $t->project_code ?? '-',
                        ucfirst($t->priority),
                        ucfirst(str_replace('_', ' ', $t->status)),
                        $t->task_mode ?? 'individual',
                        $t->task_date,
                        $t->deadline_date,
                        $assignedBy,
                        $assignedTo,
                        $memberStatuses,
                        $t->update_count,
                        $lastUpdate->status ?? '-',
                        $lastUpdate->remarks ?? '-',
                        $lastUpdate->updated_by_name ?? '-',
                        $lastUpdate->created_at ?? '-',
                        $lastApproval->approval_status ?? '-',
                        $lastApproval->remarks ?? '-',
                        $lastApproval->approved_by_name ?? '-',
                        $fileUrl,
                        $voiceFileUrl,
                    ]);
                }

                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('exportMonthlyDetailCsv error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Export failed.');
        }
    }

    /* ============================================================
     |  REPORT 3 : DAY-WISE
     ============================================================ */

    public function dayWiseTaskReport(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();

            // Shared with exportDayWiseCsv() — one aggregation implementation, not two.
            [$allRows, $grandTotal, $from, $to] = $this->buildDayWiseData($request, $authUser);

            // ---- PAGINATE ----
            $perPage = 20;
            $page    = (int) $request->get('page', 1);
            $total   = count($allRows);
            $items   = array_slice($allRows, ($page - 1) * $perPage, $perPage);

            $report = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $projectList = $this->getProjectList($authUser);
            $allUsers    = $this->getAllUsersForFilter($authUser);

            return view('client.report.task.day-wise', compact(
                'report',
                'grandTotal',
                'from',
                'to',
                'authUser',
                'projectList',
                'allUsers'
            ));
        } catch (Exception $e) {
            Log::error('dayWiseTaskReport error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    public function exportDayWiseCsv(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();
            [$report, , $from, $to] = $this->buildDayWiseData($request, $authUser);

            $filename = 'day-wise-task-report_'
                . $from->format('Ymd') . '_to_' . $to->format('Ymd') . '.csv';

            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($report) {
                $out = fopen('php://output', 'w');
                fputs($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'Date',
                    'Total',
                    'Pending',
                    'In Progress',
                    'Hold',
                    'Completed',
                    'Approved',
                    'Rejected',
                    'Cancelled',
                ]);

                foreach ($report as $r) {
                    fputcsv($out, [
                        $r['date'],
                        $r['total'],
                        $r['pending'],
                        $r['in_progress'],
                        $r['hold'],
                        $r['completed'],
                        $r['approved'],
                        $r['rejected'],
                        $r['cancelled'],
                    ]);
                }

                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('exportDayWiseCsv error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Export failed.');
        }
    }


    /* ============================================================
     |  REPORT 4 : EMPLOYEE DATE-WISE (SINGLE DATE)
     ============================================================ */

    /**
     * Employee-wise task counts for a SINGLE selected date.
     */
    public function employeeDateWiseReport(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();

            // Single date: defaults to today
            $selectedDate = $request->filled('report_date')
                ? Carbon::parse($request->report_date)->toDateString()
                : Carbon::today()->toDateString();

            $visibleUserIds = $this->getVisibleUserIds($authUser);

            $filterUserId  = $request->filled('user_id')    && $request->user_id    != 'all' ? $request->user_id    : null;
            $filterProject = $request->filled('project_id') && $request->project_id != 'all' ? $request->project_id : null;

            // ---- PAGINATED USERS ----
            $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.employee_id',
                    'user_basic_details.profile_image'
                )
                ->whereIn('users.id', $visibleUserIds)
                ->where('users.status', 1)
                ->when($filterUserId, fn ($q) => $q->where('users.id', $filterUserId))
                ->orderBy('users.name')
                ->paginate(20)
                ->appends($request->query());

            $userIds = collect($users->items())->pluck('id')->toArray();

            // ---- STATS for the current page of users on the selected date ----
            $stats = empty($userIds) ? collect() : DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $userIds)
                ->whereDate('tasks.task_date', $selectedDate)
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('task_assigns.assigned_to', 'tasks.status')
                ->select(
                    'task_assigns.assigned_to as user_id',
                    'tasks.status',
                    DB::raw('COUNT(DISTINCT tasks.id) as total')
                )
                ->get();

            $statsMap = [];
            foreach ($stats as $row) {
                $statsMap[$row->user_id]['total'] = ($statsMap[$row->user_id]['total'] ?? 0) + (int) $row->total;
                $statsMap[$row->user_id][$row->status] = (int) $row->total;
            }

            foreach ($users as $u) {
                $u->stat_total       = $statsMap[$u->id]['total']       ?? 0;
                $u->stat_pending     = $statsMap[$u->id]['pending']     ?? 0;
                $u->stat_in_progress = $statsMap[$u->id]['in_progress'] ?? 0;
                $u->stat_hold        = $statsMap[$u->id]['hold']        ?? 0;
                $u->stat_completed   = $statsMap[$u->id]['completed']   ?? 0;
                $u->stat_approved    = $statsMap[$u->id]['approved']    ?? 0;
                $u->stat_rejected    = $statsMap[$u->id]['rejected']    ?? 0;
                $u->stat_cancelled   = $statsMap[$u->id]['cancelled']   ?? 0;
                $u->report_date      = $selectedDate;
            }

            // ---- GLOBAL TOTALS (all matching users, single date) ----
            $allUserIds = User::whereIn('id', $visibleUserIds)
                ->where('status', 1)
                ->when($filterUserId, fn ($q) => $q->where('id', $filterUserId))
                ->pluck('id')
                ->toArray();

            $globalStats = empty($allUserIds) ? collect() : DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $allUserIds)
                ->whereDate('tasks.task_date', $selectedDate)
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('tasks.status')
                ->select('tasks.status', DB::raw('COUNT(DISTINCT tasks.id) as total'))
                ->get();

            $grandTotal = [
                'total'       => $globalStats->sum('total'),
                'pending'     => $globalStats->where('status', 'pending')->sum('total'),
                'in_progress' => $globalStats->where('status', 'in_progress')->sum('total'),
                'hold'        => $globalStats->where('status', 'hold')->sum('total'),
                'completed'   => $globalStats->where('status', 'completed')->sum('total'),
                'approved'    => $globalStats->where('status', 'approved')->sum('total'),
                'rejected'    => $globalStats->where('status', 'rejected')->sum('total'),
                'cancelled'   => $globalStats->where('status', 'cancelled')->sum('total'),
            ];

            $projectList = $this->getProjectList($authUser);
            $allUsers    = $this->getAllUsersForFilter($authUser);

            // Reuse $from/$to variables so shared partials still work if any
            $from = Carbon::parse($selectedDate);
            $to   = Carbon::parse($selectedDate);

            return view('client.report.task.employee-date-wise', compact(
                'users',
                'grandTotal',
                'selectedDate',
                'from',
                'to',
                'authUser',
                'projectList',
                'allUsers'
            ));
        } catch (Exception $e) {
            Log::error('employeeDateWiseReport error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while generating the report.');
        }
    }

    /**
     * CSV export for the employee date-wise report.
     */
    public function exportEmployeeDateWiseCsv(Request $request)
    {
        try {
            $authUser = $this->authorizeReportAccess();

            $selectedDate = $request->filled('report_date')
                ? Carbon::parse($request->report_date)->toDateString()
                : Carbon::today()->toDateString();

            $visibleUserIds = $this->getVisibleUserIds($authUser);

            $filterUserId  = $request->filled('user_id')    && $request->user_id    != 'all' ? $request->user_id    : null;
            $filterProject = $request->filled('project_id') && $request->project_id != 'all' ? $request->project_id : null;

            $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.employee_id'
                )
                ->whereIn('users.id', $visibleUserIds)
                ->where('users.status', 1)
                ->when($filterUserId, fn ($q) => $q->where('users.id', $filterUserId))
                ->orderBy('users.name')
                ->get();

            $userIds = $users->pluck('id')->toArray();

            $stats = empty($userIds) ? collect() : DB::table('task_assigns')
                ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
                ->whereIn('task_assigns.assigned_to', $userIds)
                ->whereDate('tasks.task_date', $selectedDate)
                ->when($filterProject, fn ($q) => $q->where('tasks.project_id', $filterProject))
                ->groupBy('task_assigns.assigned_to', 'tasks.status')
                ->select(
                    'task_assigns.assigned_to as user_id',
                    'tasks.status',
                    DB::raw('COUNT(DISTINCT tasks.id) as total')
                )
                ->get();

            $statsMap = [];
            foreach ($stats as $row) {
                $statsMap[$row->user_id]['total'] = ($statsMap[$row->user_id]['total'] ?? 0) + (int) $row->total;
                $statsMap[$row->user_id][$row->status] = (int) $row->total;
            }

            $filename = 'employee-date-wise-task_' . str_replace('-', '', $selectedDate) . '.csv';

            $headers = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function () use ($users, $statsMap, $selectedDate) {
                $out = fopen('php://output', 'w');
                fputs($out, "\xEF\xBB\xBF");

                fputcsv($out, [
                    'Date', 'Employee', 'Employee ID', 'Email',
                    'Total', 'Pending', 'In Progress', 'Hold',
                    'Completed', 'Approved', 'Rejected', 'Cancelled',
                ]);

                foreach ($users as $u) {
                    $s = $statsMap[$u->id] ?? [];
                    fputcsv($out, [
                        $selectedDate,
                        $u->name,
                        $u->employee_id,
                        $u->email,
                        $s['total']       ?? 0,
                        $s['pending']     ?? 0,
                        $s['in_progress'] ?? 0,
                        $s['hold']        ?? 0,
                        $s['completed']   ?? 0,
                        $s['approved']    ?? 0,
                        $s['rejected']    ?? 0,
                        $s['cancelled']   ?? 0,
                    ]);
                }

                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (Exception $e) {
            Log::error('exportEmployeeDateWiseCsv error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Export failed.');
        }
    }
}
