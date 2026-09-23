<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Project Reports: Summary, Progress, Task & Employee Performance,
 * Timeline/Overdue. Follows AttendanceReportController's established idiom
 * (raw DB::table queries, hand-computed per-status counts, SanitizesCsv for
 * export) rather than introducing a new reporting pattern.
 */
class ProjectReportController extends Controller
{
    use SanitizesCsv;

    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }

    /** Shared dropdown data every Project Report filter bar needs. */
    private function filterOptions(): array
    {
        $tenantId = $this->tenantId();

        return [
            'allProjects' => DB::table('projects')->where('tenant_id', $tenantId)->select('id', 'name', 'project_code')->orderBy('name')->get(),
            'allManagers' => DB::table('users')->whereIn('role', ['admin', 'hr', 'manager'])->where('tenant_id', $tenantId)->select('id', 'name')->orderBy('name')->get(),
            'allEmployees' => DB::table('users')->where('tenant_id', $tenantId)->where('status', 1)->select('id', 'name', 'employee_id')->orderBy('name')->get(),
            'allDepartments' => Department::where('tenant_id', $tenantId)->select('id', 'name')->orderBy('name')->get(),
            'allBranches' => DB::table('company_branches')->where('tenant_id', $tenantId)->select('id', 'name')->orderBy('name')->get(),
        ];
    }

    /** Applies the common project_head/status/priority/branch/department/date filters shared by all 4 reports. */
    private function applyCommonFilters($query, Request $request, string $dateColumn = 'deadline_date')
    {
        $tenantId = $this->tenantId();
        $query->where('projects.tenant_id', $tenantId);

        if ($request->filled('project_id')) {
            $query->where('projects.id', $request->project_id);
        }
        if ($request->filled('project_head')) {
            $query->where('projects.project_head', $request->project_head);
        }
        if ($request->filled('status')) {
            $query->where('projects.status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('projects.priority', $request->priority);
        }
        if ($request->filled('date_from')) {
            $query->whereDate("projects.{$dateColumn}", '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate("projects.{$dateColumn}", '<=', $request->date_to);
        }
        if ($request->filled('branch_id') || $request->filled('department_id')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('user_job_details')
                    ->whereColumn('user_job_details.user_id', 'projects.project_head')
                    ->when($request->filled('branch_id'), fn ($q) => $q->where('user_job_details.branch_id', $request->branch_id))
                    ->when($request->filled('department_id'), fn ($q) => $q->where('user_job_details.department', $request->department_id));
            });
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('projects.name', 'like', "%{$search}%")
                    ->orWhere('projects.project_code', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))->from('users')
                            ->whereColumn('users.id', 'projects.project_head')
                            ->where(function ($u) use ($search) {
                                $u->where('users.name', 'like', "%{$search}%")
                                    ->orWhere('users.employee_id', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query;
    }

    /** Left-join the project head's branch, so every report that goes through
     *  applyCommonFilters() can also show/export a Branch column. */
    private function joinProjectHeadBranch($query)
    {
        return $query
            ->leftJoin('user_job_details as pr_ujd', 'projects.project_head', '=', 'pr_ujd.user_id')
            ->leftJoin('company_branches as pr_branch', 'pr_ujd.branch_id', '=', 'pr_branch.id');
    }

    // ==================== 1. Project Summary Report ====================

    public function summary(Request $request)
    {
        $projects = $this->buildSummaryRows($request);

        return view('client.report.project.summary', array_merge(
            ['projects' => $projects],
            $this->filterOptions()
        ));
    }

    public function summaryExport(Request $request)
    {
        $projects = $this->buildSummaryRows($request);

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $this->writeCsvRow($handle, ['Project Summary Report — generated ' . now()->format('d M Y H:i')]);
        $this->writeCsvRow($handle, []);
        $this->writeCsvRow($handle, ['Code', 'Name', 'Manager', 'Branch', 'Status', 'Priority', 'Start Date', 'Deadline', 'Team Size', 'Tasks Total', 'Tasks Completed', 'Progress %', 'Budget', 'Spent']);

        foreach ($projects as $p) {
            $this->writeCsvRow($handle, [
                $p->project_code, $p->name, $p->manager_name, $p->branch_name ?? '—', ucfirst($p->status), ucfirst($p->priority),
                optional($p->start_date)->format('d M Y'), optional($p->deadline_date)->format('d M Y'),
                $p->team_size, $p->tasks_total, $p->tasks_completed, $p->progress_percentage,
                $p->budget !== null ? number_format((float) $p->budget, 2) : '', number_format((float) $p->spent, 2),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="project-summary-report-' . now()->format('Y-m-d') . '.csv"');
    }

    private function buildSummaryRows(Request $request)
    {
        $query = DB::table('projects')
            ->leftJoin('users', 'projects.project_head', '=', 'users.id');
        $query = $this->joinProjectHeadBranch($query);
        $query->select(
                'projects.id', 'projects.project_code', 'projects.name', 'projects.status', 'projects.priority',
                'projects.start_date', 'projects.deadline_date', 'projects.progress_percentage', 'projects.budget',
                'users.name as manager_name', 'pr_branch.name as branch_name'
            )
            ->selectRaw('(SELECT COUNT(*) FROM project_assigns WHERE project_id = projects.id AND status = 1) as team_size')
            ->selectRaw('(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id) as tasks_total')
            ->selectRaw("(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status IN ('completed','approved')) as tasks_completed")
            ->selectRaw("(SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id = projects.id AND status = 'approved') as spent");

        $this->applyCommonFilters($query, $request);

        return $query->orderByDesc('projects.created_at')->get()->map(function ($p) {
            $p->start_date = $p->start_date ? \Carbon\Carbon::parse($p->start_date) : null;
            $p->deadline_date = $p->deadline_date ? \Carbon\Carbon::parse($p->deadline_date) : null;
            return $p;
        });
    }

    // ==================== 2. Project Progress Report ====================

    public function progress(Request $request)
    {
        $projects = $this->buildProgressRows($request);

        return view('client.report.project.progress', array_merge(
            ['projects' => $projects],
            $this->filterOptions()
        ));
    }

    public function progressExport(Request $request)
    {
        $projects = $this->buildProgressRows($request);

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $this->writeCsvRow($handle, ['Project Progress Report — generated ' . now()->format('d M Y H:i')]);
        $this->writeCsvRow($handle, []);
        $this->writeCsvRow($handle, ['Code', 'Name', 'Branch', 'Progress %', 'Completed', 'In Progress', 'Pending', 'Overdue Tasks', 'Days Remaining/Overdue', 'Latest Update']);

        foreach ($projects as $p) {
            $this->writeCsvRow($handle, [
                $p->project_code, $p->name, $p->branch_name ?? '—', $p->progress_percentage, $p->tasks_completed, $p->tasks_in_progress,
                $p->tasks_pending, $p->tasks_overdue, $p->days_label, $p->latest_update_summary,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="project-progress-report-' . now()->format('Y-m-d') . '.csv"');
    }

    private function buildProgressRows(Request $request)
    {
        $query = DB::table('projects');
        $query = $this->joinProjectHeadBranch($query);
        $query->select('projects.id', 'projects.project_code', 'projects.name', 'projects.status', 'projects.deadline_date', 'projects.progress_percentage', 'pr_branch.name as branch_name')
            ->selectRaw("(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status IN ('completed','approved')) as tasks_completed")
            ->selectRaw("(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status = 'in_progress') as tasks_in_progress")
            ->selectRaw("(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status = 'pending') as tasks_pending")
            ->selectRaw("(SELECT COUNT(*) FROM tasks WHERE project_id = projects.id AND status NOT IN ('completed','approved','cancelled') AND deadline_date < CURDATE()) as tasks_overdue");

        $this->applyCommonFilters($query, $request);

        $projects = $query->orderByDesc('projects.progress_percentage')->get();

        $projectIds = $projects->pluck('id');
        $latestUpdates = DB::table('project_updates')
            ->whereIn('project_id', $projectIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->first());

        return $projects->map(function ($p) use ($latestUpdates) {
            $deadline = $p->deadline_date ? \Carbon\Carbon::parse($p->deadline_date) : null;
            if ($deadline) {
                $diff = \Carbon\Carbon::today()->diffInDays($deadline, false);
                $p->days_label = $diff >= 0 ? "{$diff} day(s) remaining" : (abs($diff) . ' day(s) overdue');
            } else {
                $p->days_label = 'No deadline';
            }

            $latest = $latestUpdates->get($p->id);
            $p->latest_update_summary = $latest ? \Illuminate\Support\Str::limit($latest->completed_work ?: $latest->notes ?: '—', 80) : '—';

            return $p;
        });
    }

    // ==================== 3. Project Task and Employee Performance Report ====================

    public function taskPerformance(Request $request)
    {
        $rows = $this->buildTaskPerformanceRows($request);

        return view('client.report.project.task-performance', array_merge(
            ['rows' => $rows],
            $this->filterOptions()
        ));
    }

    public function taskPerformanceExport(Request $request)
    {
        $rows = $this->buildTaskPerformanceRows($request);

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $this->writeCsvRow($handle, ['Project Task & Employee Performance Report — generated ' . now()->format('d M Y H:i')]);
        $this->writeCsvRow($handle, []);
        $this->writeCsvRow($handle, ['Employee', 'Employee ID', 'Branch', 'Project', 'Total Tasks', 'Completed', 'In Progress', 'Pending', 'Overdue', 'Completion Rate %']);

        foreach ($rows as $r) {
            $rate = $r->total > 0 ? round(($r->completed / $r->total) * 100, 1) : 0;
            $this->writeCsvRow($handle, [$r->employee_name, $r->employee_code, $r->branch_name ?? '—', $r->project_name, $r->total, $r->completed, $r->in_progress, $r->pending, $r->overdue, $rate]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="project-task-employee-performance-' . now()->format('Y-m-d') . '.csv"');
    }

    private function buildTaskPerformanceRows(Request $request)
    {
        $tenantId = $this->tenantId();

        $query = DB::table('task_assigns')
            ->join('tasks', 'task_assigns.task_id', '=', 'tasks.id')
            ->join('users', 'task_assigns.assigned_to', '=', 'users.id')
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->leftJoin('user_job_details as tp_ujd', 'users.id', '=', 'tp_ujd.user_id')
            ->leftJoin('company_branches as tp_branch', 'tp_ujd.branch_id', '=', 'tp_branch.id')
            ->where('tasks.tenant_id', $tenantId);

        if ($request->filled('project_id')) {
            $query->where('tasks.project_id', $request->project_id);
        }
        if ($request->filled('employee_id')) {
            $query->where('task_assigns.assigned_to', $request->employee_id);
        }
        if ($request->filled('priority')) {
            $query->where('tasks.priority', $request->priority);
        }
        if ($request->filled('status')) {
            $query->where('tasks.status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->where('tp_ujd.department', $request->department_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('tp_ujd.branch_id', $request->branch_id);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.employee_id', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('tasks.deadline_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('tasks.deadline_date', '<=', $request->date_to);
        }

        return $query->select(
                'users.id as user_id', 'users.name as employee_name', 'users.employee_id as employee_code',
                'tp_branch.name as branch_name',
                'projects.id as project_id', 'projects.name as project_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tasks.status IN ('completed','approved') THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN tasks.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN tasks.status = 'pending' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN tasks.status NOT IN ('completed','approved','cancelled','rejected') AND tasks.deadline_date < CURDATE() THEN 1 ELSE 0 END) as overdue")
            )
            ->groupBy('users.id', 'users.name', 'users.employee_id', 'tp_branch.name', 'projects.id', 'projects.name')
            ->orderByDesc('total')
            ->get();
    }

    // ==================== 4. Project Timeline / Overdue Report ====================

    public function timeline(Request $request)
    {
        $projects = $this->buildTimelineRows($request);

        return view('client.report.project.timeline', array_merge(
            ['projects' => $projects],
            $this->filterOptions()
        ));
    }

    public function timelineExport(Request $request)
    {
        $projects = $this->buildTimelineRows($request);

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        $this->writeCsvRow($handle, ['Project Timeline / Overdue Report — generated ' . now()->format('d M Y H:i')]);
        $this->writeCsvRow($handle, []);
        $this->writeCsvRow($handle, ['Code', 'Name', 'Manager', 'Branch', 'Status', 'Start Date', 'Deadline', 'Overdue?', 'Days', 'Next Milestone']);

        foreach ($projects as $p) {
            $this->writeCsvRow($handle, [
                $p->project_code, $p->name, $p->manager_name, $p->branch_name ?? '—', ucfirst($p->status),
                optional($p->start_date)->format('d M Y'), optional($p->deadline_date)->format('d M Y'),
                $p->is_overdue ? 'Yes' : 'No', $p->days_label, $p->next_milestone,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="project-timeline-overdue-report-' . now()->format('Y-m-d') . '.csv"');
    }

    private function buildTimelineRows(Request $request)
    {
        $query = DB::table('projects')
            ->leftJoin('users', 'projects.project_head', '=', 'users.id');
        $query = $this->joinProjectHeadBranch($query);
        $query->select(
                'projects.id', 'projects.project_code', 'projects.name', 'projects.status',
                'projects.start_date', 'projects.deadline_date', 'users.name as manager_name', 'pr_branch.name as branch_name'
            );

        $this->applyCommonFilters($query, $request);

        $projects = $query->orderBy('projects.deadline_date')->get();

        $projectIds = $projects->pluck('id');
        $nextMilestones = DB::table('project_milestones')
            ->whereIn('project_id', $projectIds)
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->get()
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->first());

        return $projects->map(function ($p) use ($nextMilestones) {
            $p->start_date = $p->start_date ? \Carbon\Carbon::parse($p->start_date) : null;
            $deadline = $p->deadline_date ? \Carbon\Carbon::parse($p->deadline_date) : null;
            $p->deadline_date = $deadline;

            $p->is_overdue = $deadline
                && \Carbon\Carbon::today()->gt($deadline)
                && in_array($p->status, ['ongoing', 'pending', 'hold']);

            if ($deadline) {
                $diff = \Carbon\Carbon::today()->diffInDays($deadline, false);
                $p->days_label = $diff >= 0 ? "{$diff} day(s) remaining" : (abs($diff) . ' day(s) overdue');
            } else {
                $p->days_label = 'No deadline';
            }

            $milestone = $nextMilestones->get($p->id);
            $p->next_milestone = $milestone ? ($milestone->title . ' (' . \Carbon\Carbon::parse($milestone->due_date)->format('d M Y') . ')') : '—';

            return $p;
        });
    }
}
