<?php

namespace App\Services\Dashboard;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\EmployeeKpiScore;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Project;
use App\Models\Shift;
use App\Models\Task;
use App\Models\User;
use App\Models\UserBasicDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Manager dashboard data — the manager's own day plus their team (reportees).
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2);
 * DashboardController::index() renders client.dashboard.manager with build().
 */
class ManagerDashboard
{
    /**
     * Manager Dashboard - Using JOIN queries
     */
    public function build(): array
    {
        $user = Auth::user();
        $today = Carbon::today();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $startOfMonth = Carbon::now()->startOfMonth();

        // Get team members (any reporting head)
        $teamIds = User::managedBy($user->id)
            ->pluck('id')
            ->toArray();

        $teamSize = count($teamIds);

        // Team attendance today
        $teamPresent = Attendance::whereIn('user_id', $teamIds)
            ->whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->count();

        // Team on leave today
        $teamOnLeave = $this->getTeamOnLeaveToday($teamIds);
        $teamAbsent = max(0, $teamSize - $teamPresent - $teamOnLeave);

        // ============ FEATURE FLAGS (gate the extra widgets — same pattern
        // as employeeDashboard(), team-scoped instead of self-scoped) ============
        $featureService = app(\App\Services\FeatureService::class);
        $showOvertime = $featureService->enabledForCurrentTenant('overtime');
        $showWfhTravel = $featureService->enabledForCurrentTenant('wfh_travel');
        $showMeetings = $featureService->enabledForCurrentTenant('meetings');
        $showRegularization = $featureService->enabledForCurrentTenant('regularization');
        $showExpenses = $featureService->enabledForCurrentTenant('expense_management');
        $showTasks = $featureService->enabledForCurrentTenant('task_single') || $featureService->enabledForCurrentTenant('task_group');
        $showHolidays = $featureService->enabledForCurrentTenant('holiday');
        $showAnnouncements = $featureService->enabledForCurrentTenant('announcements');
        $showProjects = $featureService->enabledForCurrentTenant('project_management');
        $showPerformance = $featureService->enabledForCurrentTenant('kpi_performance');

        // ============ PROJECTS (company-wide, same anatomy as the Admin
        // Dashboard's Total Projects KPI card — config('rbac.php')'s manager
        // 'projects' => ['view', ...] grant is unscoped, same visibility as
        // admin/hr, so no team-filtering is applied here) ============
        $totalProjects = 0;
        $ongoingProjects = 0;
        $completedProjects = 0;
        if ($showProjects) {
            $totalProjects = Project::count();
            $ongoingProjects = Project::where('status', 'ongoing')->count();
            $completedProjects = Project::where('status', 'completed')->count();
        }

        // ============ PENDING APPROVALS — only modules a manager can actually
        // act on (config('rbac.php') 'manager' role: leave/attendance/expenses/
        // overtime/requests/offboarding approve => team scope; loans/payroll/
        // company-wide modules are admin/hr-only and deliberately excluded) ============
        $pendingLeaveRequests = Leave::whereIn('user_id', $teamIds)
            ->where('status', 'pending')
            ->count();

        $pendingRegularizations = 0;
        if ($showRegularization) {
            $pendingRegularizations = AttendanceRegularization::whereIn('user_id', $teamIds)
                ->where('status', 'pending')
                ->count();
        }

        $pendingOvertimeRequests = 0;
        if ($showOvertime) {
            $pendingOvertimeRequests = \App\Models\OvertimeRequest::whereIn('user_id', $teamIds)
                ->where('status', 'pending')
                ->count();
        }

        $pendingExpenseApprovals = 0;
        if ($showExpenses) {
            $pendingExpenseApprovals = Expense::whereIn('user_id', $teamIds)
                ->where('status', 'pending')
                ->count();
        }

        $pendingWfhTravelRequests = 0;
        if ($showWfhTravel) {
            $pendingWfhTravelRequests = \App\Models\Request::whereIn('user_id', $teamIds)
                ->where('status', 'PENDING')
                ->count();
        }

        $pendingOffboardingRequests = \App\Models\OffboardingRequest::whereIn('employee_id', $teamIds)
            ->where('status', 'pending_approval')
            ->count();

        // Team shift swaps / changes waiting for this manager (same scope as Shift Requests → Approvals).
        $pendingShiftRequests = 0;
        $teamShiftRequests = collect();
        if (app(\App\Services\FeatureService::class)->enabledForCurrentTenant('custom_shift')) {
            $shiftApprovals = app(\App\Services\Shift\ShiftRequestPresenter::class)->approvalQuery($user);
            $pendingShiftRequests = (clone $shiftApprovals)->count();
            $teamShiftRequests = $shiftApprovals->with(['requester:id,name', 'counterpart:id,name', 'items:id,shift_request_id,date'])
                ->orderBy('id')->limit(3)->get();
        }

        $totalPendingApprovals = $pendingLeaveRequests + $pendingRegularizations + $pendingOvertimeRequests
            + $pendingExpenseApprovals + $pendingWfhTravelRequests + $pendingOffboardingRequests + $pendingShiftRequests;

        // Kept for back-compat with the old "Task Approvals" card wording —
        // count of team tasks marked completed, awaiting the manager's review.
        $pendingTaskApprovals = 0;
        if ($showTasks) {
            $pendingTaskApprovals = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
                ->whereIn('task_assigns.assigned_to', $teamIds)
                ->where('tasks.status', 'completed')
                ->distinct('tasks.id')
                ->count('tasks.id');
        }

        // Team members list
        $teamMembers = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->whereIn('users.id', $teamIds)
            ->where('users.status', 1)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name as designation_name',
                'departments.name as department_name'
            )
            ->orderBy('users.name')
            ->get();

        // Photos for the employee cells of the Recent Team Leaves / WFH / Regularizations cards
        // (all team ids, so an inactive member's old request still gets their photo).
        $teamProfileImages = UserBasicDetail::whereIn('user_id', $teamIds)->pluck('profile_image', 'user_id');

        // Recent team leaves
        $recentTeamLeaves = Leave::leftJoin('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
            ->whereIn('leaves.user_id', $teamIds)
            ->select(
                'leaves.*',
                'users.id as user_id',
                'users.employee_id as employee_id',
                'users.name as user_name',
                'users.email as user_email',
                'leave_types.name as leave_type_name'
            )
            ->orderBy('leaves.created_at', 'desc')
            ->limit(5)
            ->get();

        // Team tasks
        $teamTasks = Task::leftJoin('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
            ->leftJoin('users', 'task_assigns.assigned_to', '=', 'users.id')
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->whereIn('task_assigns.assigned_to', $teamIds)
            ->select(
                'tasks.id',
                'tasks.title',
                'tasks.description',
                'tasks.deadline_date',
                'tasks.task_date',
                'tasks.status',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.employee_id as user_employee_id',
                'projects.name as project_name'
            )
            ->orderBy('tasks.created_at', 'desc')
            ->limit(8)
            ->get();

        // ============ TEAM TASK STATUS BREAKDOWN (funnel widget, same anatomy
        // as the employee dashboard's Task Overview) ============
        $teamTaskStatusCounts = collect();
        $totalTeamTasks = 0;
        if ($showTasks) {
            $teamTaskStatusCounts = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
                ->whereIn('task_assigns.assigned_to', $teamIds)
                ->select('tasks.status', DB::raw('count(distinct tasks.id) as cnt'))
                ->groupBy('tasks.status')
                ->pluck('cnt', 'status');
            $totalTeamTasks = (int) $teamTaskStatusCounts->sum();
        }

        // ============ TEAM ATTENDANCE TREND (cumulative-by-day this month so
        // far, aggregated across the whole team — same chart anatomy as the
        // employee dashboard's per-user trend, but count-based not per-user) ============
        $attendanceByDate = Attendance::whereIn('user_id', $teamIds)
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereDate('date', '<=', $today)
            ->whereNotNull('clock_in')
            ->select('date', DB::raw('count(distinct user_id) as cnt'))
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $teamLeaveRowsThisMonth = Leave::whereIn('user_id', $teamIds)
            ->where('status', 'approved')
            ->where(function ($query) use ($startOfMonth, $today) {
                $query->whereBetween('start_date', [$startOfMonth, $today])
                    ->orWhereBetween('end_date', [$startOfMonth, $today])
                    ->orWhere(function ($q) use ($startOfMonth, $today) {
                        $q->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $today);
                    });
            })
            ->get();

        $attendanceTrendLabels = [];
        $attendanceTrendPresent = [];
        $attendanceTrendAbsent = [];
        $attendanceTrendLeave = [];
        $trendDate = $startOfMonth->copy();
        while ($trendDate <= $today) {
            $dateString = $trendDate->format('Y-m-d');
            $presentCount = (int) ($attendanceByDate->get($dateString)->cnt ?? 0);

            $leaveCount = 0;
            foreach ($teamLeaveRowsThisMonth as $leaveRow) {
                if ($trendDate->between(Carbon::parse($leaveRow->start_date), Carbon::parse($leaveRow->end_date ?? $leaveRow->start_date))) {
                    $leaveCount++;
                }
            }

            $attendanceTrendLabels[] = $trendDate->day;
            $attendanceTrendPresent[] = $presentCount;
            $attendanceTrendLeave[] = $leaveCount;
            $attendanceTrendAbsent[] = max(0, $teamSize - $presentCount - $leaveCount);

            $trendDate->addDay();
        }

        // ============ RECENT TEAM OVERTIME / WFH-TRAVEL / REGULARIZATION ============
        $recentTeamOvertime = collect();
        if ($showOvertime) {
            $recentTeamOvertime = \App\Models\OvertimeRequest::whereIn('user_id', $teamIds)
                ->leftJoin('users', 'overtime_requests.user_id', '=', 'users.id')
                ->select('overtime_requests.*', 'users.name as user_name', 'users.employee_id as user_employee_id')
                ->orderByDesc('overtime_requests.date')
                ->limit(5)
                ->get();
        }

        $recentTeamRequests = collect();
        if ($showWfhTravel) {
            $recentTeamRequests = \App\Models\Request::whereIn('user_id', $teamIds)
                ->with(['requestType', 'user:id,name,employee_id'])
                ->latest()
                ->limit(5)
                ->get();
        }

        $recentTeamRegularizations = collect();
        if ($showRegularization) {
            $recentTeamRegularizations = AttendanceRegularization::whereIn('user_id', $teamIds)
                ->leftJoin('users', 'attendance_regularizations.user_id', '=', 'users.id')
                ->select('attendance_regularizations.*', 'users.name as user_name', 'users.employee_id as user_employee_id')
                ->orderByDesc('attendance_regularizations.date')
                ->limit(5)
                ->get();
        }

        // ============ TEAM PERFORMANCE (this month's already-rolled-up KPI
        // scores — never recomputed here, same as the employee dashboard) ============
        $teamKpiAvg = null;
        $teamTopPerformer = null;
        if ($showPerformance && $teamSize > 0) {
            $teamKpiRows = EmployeeKpiScore::whereIn('user_id', $teamIds)
                ->whereMonth('reporting_month', $currentMonth)
                ->whereYear('reporting_month', $currentYear)
                ->get();

            if ($teamKpiRows->isNotEmpty()) {
                $teamKpiAvg = round($teamKpiRows->avg('overall_score'));
                $best = $teamKpiRows->sortByDesc('overall_score')->first();
                $teamTopPerformer = optional(User::find($best->user_id))->name;
            }
        }

        // ============ UPCOMING HOLIDAYS / RECENT ANNOUNCEMENTS (org-wide,
        // same queries as the employee dashboard — not team-scoped) ============
        $upcomingHolidays = collect();
        if ($showHolidays) {
            $upcomingHolidays = Holiday::where('start_date', '>=', $today)
                ->where('status', 1)
                ->orderBy('start_date')
                ->limit(4)
                ->get();
        }

        $recentAnnouncements = collect();
        if ($showAnnouncements) {
            $recentAnnouncements = Announcement::leftJoin('users', 'announcements.user_id', '=', 'users.id')
                ->select('announcements.*', 'users.name as user_name')
                ->where('announcements.status', 1)
                ->notExpired()
                ->orderBy('announcements.created_at', 'desc')
                ->limit(4)
                ->get();
        }

        $data = [
            'page_title' => 'Manager Dashboard',
            'user' => $user,
            'team_size' => $teamSize,
            'team_present' => $teamPresent,
            'team_absent' => $teamAbsent,
            'team_on_leave' => $teamOnLeave,
            'pending_leave_requests' => $pendingLeaveRequests,
            'pending_regularizations' => $pendingRegularizations,
            'pending_overtime_requests' => $pendingOvertimeRequests,
            'pending_expense_approvals' => $pendingExpenseApprovals,
            'pending_wfh_travel_requests' => $pendingWfhTravelRequests,
            'pending_offboarding_requests' => $pendingOffboardingRequests,
            'pending_shift_requests' => $pendingShiftRequests,
            'team_shift_requests' => $teamShiftRequests,
            'pending_task_approvals' => $pendingTaskApprovals,
            'total_pending_approvals' => $totalPendingApprovals,
            'team_members' => $teamMembers,
            'recent_team_leaves' => $recentTeamLeaves,
            'team_tasks' => $teamTasks,
            'team_task_status_counts' => $teamTaskStatusCounts,
            'total_team_tasks' => $totalTeamTasks,
            'attendance_trend_labels' => $attendanceTrendLabels,
            'attendance_trend_present' => $attendanceTrendPresent,
            'attendance_trend_absent' => $attendanceTrendAbsent,
            'attendance_trend_leave' => $attendanceTrendLeave,
            'recent_team_overtime' => $recentTeamOvertime,
            'recent_team_requests' => $recentTeamRequests,
            'recent_team_regularizations' => $recentTeamRegularizations,
            'team_profile_images' => $teamProfileImages,
            'team_kpi_avg' => $teamKpiAvg,
            'team_top_performer' => $teamTopPerformer,
            'upcoming_holidays' => $upcomingHolidays,
            'recent_announcements' => $recentAnnouncements,
            'show_overtime' => $showOvertime,
            'show_wfh_travel' => $showWfhTravel,
            'show_meetings' => $showMeetings,
            'show_regularization' => $showRegularization,
            'show_expenses' => $showExpenses,
            'show_tasks' => $showTasks,
            'show_holidays' => $showHolidays,
            'show_announcements' => $showAnnouncements,
            'show_projects' => $showProjects,
            'total_projects' => $totalProjects,
            'ongoing_projects' => $ongoingProjects,
            'completed_projects' => $completedProjects,
            'show_performance' => $showPerformance,
        ];

        return $data;
    }

    private function getTeamOnLeaveToday($teamIds)
    {
        $today = Carbon::today()->format('Y-m-d');

        return Leave::whereIn('user_id', $teamIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();
    }
}
