<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\Task;
use App\Models\Project;
use App\Models\Announcement;
use App\Models\Holiday;
use App\Models\Department;
use App\Models\AttendanceRegularization;
use App\Models\Designation;
use App\Models\ProjectAssign;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\UserWeekoffs;
use App\Models\Shift;
use App\Models\UserBasicDetail;
use App\Models\UserBankDetail;
use App\Models\UserExpenseBalance;
use App\Models\UserJobDetail;
use App\Models\MonthlyPayroll;
use App\Models\LoanRepayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\UserLocation;
use App\Models\EmployeeKpiScore;
use App\Services\RbacService;

class DashboardController extends Controller
{
    /**
     * Main dashboard entry point - redirects based on role
     */
    public function index()
    {
        $user = Auth::user();

        switch ($user->role) {
            case 'admin':
                return $this->adminDashboard();
            case 'hr':
                return $this->adminDashboard();
            case 'manager':
                return $this->managerDashboard();
            default:
                return $this->employeeDashboard();
        }
    }

    /**
     * Admin Dashboard - Using JOIN queries
     */
    private function adminDashboard()
    {
        $today = Carbon::today();

        // Employee Statistics
        $totalEmployees = User::where('status', 1)->where('role', "!=", 'admin')->count();
        $activeEmployees = User::where('status', 1)->where('role', "!=", 'admin')->count();
        $inactiveEmployees = User::where('status', 0)->where('role', "!=", 'admin')->count();
        $totalDepartments = Department::count();
        $totalProjects = Project::count();

        // Today's attendance
        $presentToday = Attendance::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->count();

        $onLeaveToday = $this->getEmployeesOnLeaveToday();

        // Pending requests
        $pendingLeaves = Leave::where('status', 'pending')->count();
        $pendingExpenses = Expense::where('status', 'pending')->count();

        // Department distribution
        $departmentDistribution = Department::leftJoin('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->leftJoin('users', 'user_job_details.user_id', '=', 'users.id')
            ->select(
                'departments.id',
                'departments.name',
                'departments.department_head',
                DB::raw('COUNT(DISTINCT users.id) as users_count')
            )
            ->where('users.status', 1)
            ->groupBy('departments.id', 'departments.name', 'departments.department_head')
            ->having('users_count', '>', 0)
            ->orderBy('users_count', 'desc')
            ->get();

        // Recent announcements
        $recentAnnouncements = Announcement::leftJoin('users', 'announcements.user_id', '=', 'users.id')
            ->select(
                'announcements.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('announcements.status', 1)
            ->notExpired()
            ->orderBy('announcements.created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent joinings
        $recentJoinings = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'users.created_at',
                'user_basic_details.profile_image',
                'designations.name as designation_name'
            )
            ->where('users.status', 1)
            ->where('users.role', "!=", "admin")
            ->orderBy('users.created_at', 'desc')
            ->limit(5)
            ->get();


        // Upcoming holidays
        $upcomingHolidays = Holiday::where('start_date', '>=', $today)
            ->where('status', 1)
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        // Upcoming birthdays
        $upcomingBirthdays = $this->getUpcomingBirthdays();

        // Monthly attendance chart data
        $attendanceChartData = $this->getMonthlyAttendanceChart();

        // Recent leaves
        $recentLeaves = Leave::leftJoin('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
            ->select(
                'leaves.*',
                'users.name as user_name',
                'leave_types.name as leave_type_name'
            )
            ->orderBy('leaves.created_at', 'desc')
            ->limit(5)
            ->get();

        // Projects
        $projects = Project::latest()->limit(5)->get();

        // Task Statistics
        $taskStats = $this->getTaskStatistics();

        // Expense Statistics with amounts
        $expenseStats = $this->getExpenseStatistics();

        // Most Regular Employees (Top 3)
        $mostRegularEmployees = $this->getMostRegularEmployees();

        // Most Regularization Requests
        $mostRegularizationRequests = $this->getMostRegularizationRequests();
        $monthlyRegularizationStats = $this->getMonthlyRegularizationStats();
        $todaysRegularizationRequests = $this->getTodaysRegularizationRequests();

        // Least Tasks Assigned Employees (Top 3)
        $leastTasksEmployees = $this->getLeastTasksEmployees();

        // Top 5 performers (attendance regularity) for the selected/current month
        $selectedPerformanceMonth = request('performance_month') ?: Carbon::now()->format('Y-m');
        $topPerformers = $this->getTopPerformers($selectedPerformanceMonth);
        $performanceMonthOptions = $this->getPerformanceMonthOptions();

        // Company overview trend (last 6 months): expense, payroll, headcount, attendance rate, tasks completed
        $companyOverviewTrend = $this->getCompanyOverviewTrend(6);

        // Holiday dates for the attendance calendar (flat "Y-m-d" => name map)
        $calendarHolidays = $this->getCalendarHolidays();

        // Working days this month
        $workingDays = $this->getWorkingDaysThisMonth();

        // Monthly joinings count
        $monthlyJoinings = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Department head count
        $departmentHeads = UserJobDetail::whereNotNull('department')->count();

        // Project statistics
        $activeProjects = Project::where('status', 'pending')->count();
        $completedProjects = Project::where('status', 'completed')->count();
        $ongoingProjects = Project::where('status', 'ongoing')->count();
        $onHoldProjects = Project::where('status', 'on_hold')->count();
        $cancelledProjects = Project::where('status', 'cancelled')->count();

        // Late attendance today
        $lateAttendance = Attendance::whereDate('date', $today)
            ->where('clock_in', '>', '10:00:00')
            ->count();

        // Early departure today
        $earlyDeparture = Attendance::whereDate('date', $today)
            ->where('clock_out', '<', '18:00:00')
            ->whereNotNull('clock_out')
            ->count();

        // Leave statistics
        $approvedLeaves = Leave::where('status', 'approved')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
        $rejectedLeaves = Leave::where('status', 'rejected')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        // Expense statistics counts
        $approvedExpenses = Expense::where('status', 'approved')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
        $rejectedExpenses = Expense::where('status', 'rejected')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        // Announcement statistics
        $totalAnnouncements = Announcement::where('status', 1)->count();
        $recentAnnouncementsCount = Announcement::where('status', 1)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // User roles distribution
        $adminCount = User::where('role', 'admin')->count();
        $hrCount = User::where('role', 'hr')->count();
        $managerCount = User::where('role', 'manager')->count();
        $employeeCount = User::where('role', 'employee')->count();

        // Gender distribution
        $maleCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->where('user_basic_details.gender', 'male')
            ->count();

        $femaleCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->where('user_basic_details.gender', 'female')
            ->count();

        $otherGenderCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->whereNotIn('user_basic_details.gender', ['male', 'female'])
            ->count();

        // Get project statistics
        $projectStats = $this->getProjectStatistics();
        $projectChartData = $this->getProjectChartData();

        // Color palette for charts
        $colorPalette = ['#3454d1', '#0d519e', '#1976d2', '#1e88e5', '#2196f3', '#42a5f5', '#64b5f6', '#90caf9'];

        // Merge all data
        $data = array_merge([
            'page_title' => 'Admin Dashboard',
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'total_departments' => $totalDepartments,
            'total_projects' => $totalProjects,
            'present_today' => $presentToday,
            'absent_today' => $totalEmployees - $presentToday - $onLeaveToday,
            'on_leave_today' => $onLeaveToday,
            'pending_leaves' => $pendingLeaves,
            'pending_expenses' => $pendingExpenses,
            'department_distribution' => $departmentDistribution,
            'recent_announcements' => $recentAnnouncements,
            'recent_joinings' => $recentJoinings,
            'upcoming_holidays' => $upcomingHolidays,
            'upcoming_birthdays' => $upcomingBirthdays,
            'attendance_chart_data' => $attendanceChartData,
            'recent_leaves' => $recentLeaves,
            'projects' => $projects,
            'colorPalette' => $colorPalette,
            'working_days' => $workingDays,
            'monthly_joinings' => $monthlyJoinings,
            'department_heads' => $departmentHeads,
            'active_projects' => $activeProjects,
            'completed_projects' => $completedProjects,
            'ongoing_projects' => $ongoingProjects,
            'on_hold_projects' => $onHoldProjects,
            'cancelled_projects' => $cancelledProjects,
            'late_attendance' => $lateAttendance,
            'early_departure' => $earlyDeparture,
            'approved_leaves' => $approvedLeaves,
            'rejected_leaves' => $rejectedLeaves,
            'approved_expenses' => $approvedExpenses,
            'rejected_expenses' => $rejectedExpenses,
            'total_announcements' => $totalAnnouncements,
            'recent_announcements_count' => $recentAnnouncementsCount,
            'admin_count' => $adminCount,
            'hr_count' => $hrCount,
            'manager_count' => $managerCount,
            'employee_count' => $employeeCount,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'other_gender_count' => $otherGenderCount,
        ], $taskStats, $expenseStats, $projectStats, $projectChartData, [
            'most_regular_employees' => $mostRegularEmployees,
            'least_tasks_employees' => $leastTasksEmployees,
            'most_regularization_requests' => $mostRegularizationRequests,
            'monthly_regularization_stats' => $monthlyRegularizationStats,
            'todays_regularization_requests' => $todaysRegularizationRequests,
            'top_performers' => $topPerformers,
            'performance_month_options' => $performanceMonthOptions,
            'selected_performance_month' => $selectedPerformanceMonth,
            'company_overview_trend' => $companyOverviewTrend,
            'calendar_holidays' => $calendarHolidays,
        ]);

        return view('client.dashboard.admin', $data);
    }


    /**
     * HR Dashboard - Using JOIN queries
     */
    private function hrDashboard()
    {
        $today = Carbon::today();

        $totalEmployees = User::where('status', 1)->where('role', "!=", 'admin')->count();

        // Gender distribution using JOIN
        $maleCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->where('user_basic_details.gender', 'm')
            ->count();

        $femaleCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->where('user_basic_details.gender', 'f')
            ->count();

        $otherCount = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->where('users.status', 1)
            ->whereNotIn('user_basic_details.gender', ['m', 'f'])
            ->count();

        // New joinings this month
        $newJoinings = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // On leave today
        $onLeaveToday = $this->getEmployeesOnLeaveToday();

        // Pending approvals
        $pendingLeaves = Leave::where('status', 'pending')->count();

        // Department wise employees using JOIN
        $departmentWise = Department::leftJoin('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->leftJoin('users', 'user_job_details.user_id', '=', 'users.id')
            ->select(
                'departments.id',
                'departments.name',
                DB::raw('COUNT(DISTINCT users.id) as users_count')
            )
            ->where('users.status', 1)
            ->groupBy('departments.id', 'departments.name')
            ->having('users_count', '>', 0)
            ->orderBy('users_count', 'desc')
            ->get();

        // Designation wise employees using JOIN
        $designationWise = Designation
            ::leftJoin('user_job_details', 'designations.id', '=', 'user_job_details.designation')
            ->leftJoin('users', 'user_job_details.user_id', '=', 'users.id')
            ->select(
                'designations.id',
                'designations.name',
                DB::raw('COUNT(DISTINCT users.id) as users_count')
            )
            ->where('users.status', 1)
            ->groupBy('designations.id', 'designations.name')
            ->having('users_count', '>', 0)
            ->orderBy('users_count', 'desc')
            ->get();

        // Upcoming holidays
        $upcomingHolidays = Holiday::where('start_date', '>=', $today)
            ->where('status', 1)
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        // Upcoming birthdays
        $upcomingBirthdays = $this->getUpcomingBirthdays();

        // Upcoming anniversaries
        $upcomingAnniversaries = $this->getUpcomingAnniversaries();

        // Recent joinings
        $recentJoinings = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'users.created_at',
                'user_basic_details.profile_image',
                'designations.name as designation_name'
            )
            ->where('users.status', 1)
            ->where('role', "!=", "admin")
            ->orderBy('users.created_at', 'desc')
            ->limit(5)
            ->get();


        // Recent leaves
        $recentLeaves = Leave::leftJoin('users', 'leaves.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
            ->select(
                'leaves.*',
                'users.name as user_name',
                'leave_types.name as leave_type_name'
            )
            ->orderBy('leaves.created_at', 'desc')
            ->limit(5)
            ->get();

        $data = [
            'page_title' => 'HR Dashboard',
            'total_employees' => $totalEmployees,
            'male_employees' => $maleCount,
            'female_employees' => $femaleCount,
            'other_employees' => $otherCount,
            'new_joinings' => $newJoinings,
            'on_leave_today' => $onLeaveToday,
            'pending_leaves' => $pendingLeaves,
            'department_wise' => $departmentWise,
            'designation_wise' => $designationWise,
            'upcoming_holidays' => $upcomingHolidays,
            'upcoming_birthdays' => $upcomingBirthdays,
            'upcoming_anniversaries' => $upcomingAnniversaries,
            'recent_joinings' => $recentJoinings,
            'recent_leaves' => $recentLeaves,
        ];

        return view('client.dashboard.hr', $data);
    }

    /**
     * Manager Dashboard - Using JOIN queries
     */
    private function managerDashboard()
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

        $totalPendingApprovals = $pendingLeaveRequests + $pendingRegularizations + $pendingOvertimeRequests
            + $pendingExpenseApprovals + $pendingWfhTravelRequests + $pendingOffboardingRequests;

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

        return view('client.dashboard.manager', $data);
    }

    /**
     * Employee Dashboard - Using JOIN queries
     */
    private function employeeDashboard()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // Get user job details
        $jobDetail = UserJobDetail::leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('users as reporting_heads', 'user_job_details.reporting_head', '=', 'reporting_heads.id')
            ->where('user_job_details.user_id', $user->id)
            ->select(
                'user_job_details.joining_date',
                'user_job_details.type', // office/remote
                'user_job_details.office_branch',
                'designations.name as designation_name',
                'departments.name as department_name',
                'reporting_heads.name as reporting_head_name'
            )
            ->first();

        // Get user basic details
        $basicDetail = UserBasicDetail::where('user_id', $user->id)
            ->first();

        // Today's attendance
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // Today's shift — honours the tenant's custom-shifts toggle (fixed
        // company shift when off, per-date assignment when on).
        $todayShift = app(\App\Services\Attendance\TenantShiftResolver::class)
            ->forUserDate((int) $user->id, (int) $user->tenant_id, $today->format('Y-m-d'));

        // ============ LEAVE BALANCE ============
        $leaveBalance = LeaveBalance::where('user_id', $user->id)
            ->sum('balance');

        // ============ PENDING LEAVES ============
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $leave_count = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereMonth('start_date', $currentMonth)
            ->whereYear('start_date', $currentYear)
            ->sum('leave_count');

        // ============ PENDING TASKS ============
        $pendingTasks = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
            ->where('task_assigns.assigned_to', $user->id)
            ->whereIn('tasks.status', ['pending', 'in_progress'])
            ->count();

        // ============ RECENT TASKS ============
        $recentTasks = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->where('task_assigns.assigned_to', $user->id)
            ->select(
                'tasks.id',
                'tasks.title',
                'tasks.description',
                'tasks.deadline_date',
                'tasks.status',
                'tasks.priority',
                'projects.name as project_name'
            )
            ->orderBy('tasks.created_at', 'desc')
            ->limit(5)
            ->get();
        $recentLeaves = Leave::where('user_id', $user->id)
            ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
            ->select(
                'leaves.*',
                'leave_types.name as leave_type_name'
            )
            ->orderBy('leaves.created_at', 'desc')
            ->limit(5)
            ->get();

        // ============ RECENT ANNOUNCEMENTS ============
        $recentAnnouncements = Announcement::leftJoin('users', 'announcements.user_id', '=', 'users.id')
            ->select(
                'announcements.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('announcements.status', 1)
            ->notExpired()
            ->orderBy('announcements.created_at', 'desc')
            ->limit(3)
            ->get();

        // ============ PROFILE COMPLETION ============
        $profileCompletion = $this->calculateProfileCompletion($user->id);

        // IMPROVED: Monthly attendance summary with week-offs AND holidays
        $month = Carbon::now();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Get user's week-offs
        $weekoffs = UserWeekoffs::where('user_id', $user->id)
            ->where('status', 1)
            ->get();

        // Get holidays for the month
        $holidays = Holiday::where('status', 1)
            ->where(function ($query) use ($startOfMonth, $today) {
                $query->whereBetween('start_date', [$startOfMonth, $today])
                    ->orWhereBetween('end_date', [$startOfMonth, $today])
                    ->orWhere(function ($q) use ($startOfMonth, $today) {
                        $q->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $today);
                    });
            })
            ->get();

        // Calculate working days up to today (excluding week-offs AND holidays)
        $workingDays = 0;
        $holidayDays = 0;
        $weekoffDays = 0;
        $currentDate = $startOfMonth->copy();

        while ($currentDate <= $today) {
            $dayName = $currentDate->format('l'); // Get full day name (Monday, Tuesday, etc.)
            $dateString = $currentDate->format('Y-m-d');

            // Check if this date is a holiday
            $isHoliday = false;
            foreach ($holidays as $holiday) {
                if ($currentDate->between($holiday->start_date, $holiday->end_date)) {
                    $isHoliday = true;
                    break;
                }
            }

            // Check if this date is a week-off
            $isWeekoff = false;
            foreach ($weekoffs as $weekoff) {
                if ($weekoff->off_type == 'date_based') {
                    // Date-based weekoff
                    if ($currentDate->between($weekoff->start_date, $weekoff->end_date)) {
                        $isWeekoff = true;
                        break;
                    }
                } else if ($weekoff->off_type == 'day_based') {
                    // Day-based weekoff
                    if ($weekoff->day_name == $dayName) {
                        $isWeekoff = true;
                        break;
                    }
                }
            }

            if ($isHoliday) {
                $holidayDays++;
            } else if ($isWeekoff) {
                $weekoffDays++;
            } else {
                // Not a holiday or week-off, count as working day
                $workingDays++;
            }

            $currentDate->addDay();
        }

        // Get actual present days up to today
        $monthlyAttendance = Attendance::where('user_id', $user->id)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereDate('date', '<=', $today)
            ->whereNotNull('clock_in')
            ->count();

        // Get approved leaves for the month
        $approvedLeaves = Leave::where('user_id', $user->id)
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

        // Calculate leave days
        $leaveDays = 0;
        $currentDate = $startOfMonth->copy();
        while ($currentDate <= $today) {
            $dateString = $currentDate->format('Y-m-d');

            foreach ($approvedLeaves as $leave) {
                if ($currentDate->between($leave->start_date, $leave->end_date)) {
                    // Check if it's half-day leave
                    if ($leave->start_session == 1 && $dateString == $leave->start_date) {
                        $leaveDays += 0.5; // First half leave
                    } else if ($leave->start_session == 2 && $dateString == $leave->start_date) {
                        $leaveDays += 0.5; // Second half leave
                    } else if ($leave->end_session == 1 && $dateString == $leave->end_date) {
                        $leaveDays += 0.5; // First half leave on end date
                    } else if ($leave->end_session == 2 && $dateString == $leave->end_date) {
                        $leaveDays += 0.5; // Second half leave on end date
                    } else {
                        $leaveDays += 1; // Full day leave
                    }
                    break;
                }
            }

            $currentDate->addDay();
        }

        // Calculate absent days (working days - present - leave days)
        $absentDays = $workingDays - $monthlyAttendance - $leaveDays;

        // ============ ATTENDANCE OVERVIEW TREND (cumulative Present/Absent/Leave, this month so far) ============
        $monthAttendanceRows = Attendance::where('user_id', $user->id)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->get()
            ->keyBy(fn($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $attendanceTrendLabels = [];
        $attendanceTrendPresent = [];
        $attendanceTrendAbsent = [];
        $attendanceTrendLeave = [];
        $cumPresent = 0;
        $cumAbsent = 0;
        $cumLeave = 0;
        $trendDate = $startOfMonth->copy();
        while ($trendDate <= $today) {
            $dateString = $trendDate->format('Y-m-d');
            $dayName = $trendDate->format('l');

            $isHolidayDay = false;
            foreach ($holidays as $holiday) {
                if ($trendDate->between($holiday->start_date, $holiday->end_date)) {
                    $isHolidayDay = true;
                    break;
                }
            }

            $isWeekoffDay = false;
            foreach ($weekoffs as $weekoff) {
                if ($weekoff->off_type == 'date_based' && $trendDate->between($weekoff->start_date, $weekoff->end_date)) {
                    $isWeekoffDay = true;
                    break;
                } elseif ($weekoff->off_type == 'day_based' && $weekoff->day_name == $dayName) {
                    $isWeekoffDay = true;
                    break;
                }
            }

            $isLeaveDay = false;
            foreach ($approvedLeaves as $leave) {
                if ($trendDate->between($leave->start_date, $leave->end_date)) {
                    $isLeaveDay = true;
                    break;
                }
            }

            if ($monthAttendanceRows->has($dateString)) {
                $cumPresent++;
            } elseif ($isLeaveDay) {
                $cumLeave++;
            } elseif (!$isHolidayDay && !$isWeekoffDay) {
                $cumAbsent++;
            }

            $attendanceTrendLabels[] = $trendDate->day;
            $attendanceTrendPresent[] = $cumPresent;
            $attendanceTrendAbsent[] = $cumAbsent;
            $attendanceTrendLeave[] = $cumLeave;

            $trendDate->addDay();
        }

        // Check if today is a holiday
        $isTodayHoliday = false;
        foreach ($holidays as $holiday) {
            if ($today->between($holiday->start_date, $holiday->end_date)) {
                $isTodayHoliday = true;
                break;
            }
        }

        // Check if today is a week-off
        $isTodayWeekoff = $this->isWeekoffForUser($user->id, $today);

        // Check if user is on leave today
        $isTodayOnLeave = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        // Get upcoming holidays
        $upcomingHolidays = Holiday::where('start_date', '>=', $today)
            ->where('status', 1)
            ->orderBy('start_date')
            ->limit(3)
            ->get();

        // Get upcoming week-offs
        $upcomingWeekoffs = $this->getUpcomingWeekoffs($user->id);

        // ============ TASK STATUS BREAKDOWN (for KPI card + pie chart) ============
        $taskStatusCounts = Task::join('task_assigns', 'tasks.id', '=', 'task_assigns.task_id')
            ->where('task_assigns.assigned_to', $user->id)
            ->select('tasks.status', DB::raw('count(*) as cnt'))
            ->groupBy('tasks.status')
            ->pluck('cnt', 'status');
        $totalTasksAssigned = (int) $taskStatusCounts->sum();
        $completedTasksCount = (int) ($taskStatusCounts->get('completed', 0));

        // ============ PENDING LEAVE REQUESTS (for KPI card footer) ============
        $pendingLeaveCount = Leave::where('user_id', $user->id)->where('status', 'pending')->count();

        // ============ WORKING HOURS TODAY (derived, no query) ============
        $workingHoursToday = null;
        if ($todayAttendance && $todayAttendance->clock_in) {
            $clockIn = Carbon::parse($todayAttendance->clock_in);
            $clockOutOrNow = $todayAttendance->clock_out ? Carbon::parse($todayAttendance->clock_out) : Carbon::now();
            $minutes = max(0, $clockIn->diffInMinutes($clockOutOrNow));
            $workingHoursToday = sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        // ============ FEATURE FLAGS (gate the extra widgets) ============
        $featureService = app(\App\Services\FeatureService::class);
        $showOvertime = $featureService->enabledForCurrentTenant('overtime');
        $showWfhTravel = $featureService->enabledForCurrentTenant('wfh_travel');
        $showMeetings = $featureService->enabledForCurrentTenant('meetings');
        $showPayroll = $featureService->enabledForCurrentTenant('payroll');
        $showAttendance = $featureService->enabledForCurrentTenant('attendance');
        $showTasks = $featureService->enabledForCurrentTenant('task_single') || $featureService->enabledForCurrentTenant('task_group');
        $showRegularization = $featureService->enabledForCurrentTenant('regularization');
        $showExpenses = $featureService->enabledForCurrentTenant('expense_management');
        $showHolidays = $featureService->enabledForCurrentTenant('holiday');
        $showAnnouncements = $featureService->enabledForCurrentTenant('announcements');
        $showPerformance = $featureService->enabledForCurrentTenant('kpi_performance');

        // ============ RECENT REGULARIZATION REQUESTS ============
        $recentRegularizations = collect();
        if ($showRegularization) {
            $recentRegularizations = AttendanceRegularization::where('user_id', $user->id)
                ->orderByDesc('date')
                ->limit(3)
                ->get();
        }

        // ============ PERFORMANCE (reads the already-rolled-up monthly KPI score — never recomputed here) ============
        $kpiScore = null;
        if ($showPerformance) {
            $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
                ->whereMonth('reporting_month', $currentMonth)
                ->whereYear('reporting_month', $currentYear)
                ->first();
        }

        // ============ EXPENSES ============
        $expenseBalance = null;
        $pendingExpenseCount = 0;
        $recentExpenses = collect();
        if ($showExpenses) {
            $expenseBalance = UserExpenseBalance::where('user_id', $user->id)->first();

            $pendingExpenseCount = Expense::where('user_id', $user->id)->where('status', 'pending')->count();

            $recentExpenses = Expense::where('expenses.user_id', $user->id)
                ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
                ->select('expenses.*', 'expense_types.name as expense_type_name')
                ->orderByDesc('expenses.date')
                ->limit(3)
                ->get();
        }

        // ============ OVERTIME THIS MONTH ============
        $overtimeThisMonth = null;
        $recentOvertime = collect();
        if ($showOvertime) {
            $overtimeQuery = \App\Models\OvertimeRequest::where('user_id', $user->id)
                ->whereMonth('date', $currentMonth)
                ->whereYear('date', $currentYear);

            $overtimeRows = (clone $overtimeQuery)->get();
            $overtimeThisMonth = [
                'approved_hours' => $overtimeRows->where('status', 'approved')->sum(fn($o) => $o->approved_hours ?? $o->overtime_hours),
                'pending_hours' => $overtimeRows->where('status', 'pending')->sum('overtime_hours'),
                'pending_count' => $overtimeRows->where('status', 'pending')->count(),
            ];
            $recentOvertime = \App\Models\OvertimeRequest::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->limit(3)
                ->get();
        }

        // ============ WFH / TRAVEL REQUESTS ============
        $myRequests = collect();
        $requestCounts = null;
        $wfhDaysThisMonth = 0;
        $travelDaysThisMonth = 0;
        if ($showWfhTravel) {
            $myRequests = \App\Models\Request::where('user_id', $user->id)
                ->with('requestType')
                ->latest()
                ->limit(3)
                ->get();

            $requestCounts = [
                'pending' => \App\Models\Request::where('user_id', $user->id)->where('status', 'PENDING')->count(),
                'approved_this_month' => \App\Models\Request::where('user_id', $user->id)
                    ->where('status', 'APPROVED')
                    ->whereMonth('start_date', $currentMonth)
                    ->whereYear('start_date', $currentYear)
                    ->count(),
            ];

            $approvedRequestsThisMonth = \App\Models\Request::where('user_id', $user->id)
                ->where('status', 'APPROVED')
                ->whereMonth('start_date', $currentMonth)
                ->whereYear('start_date', $currentYear)
                ->with('requestType')
                ->get();

            foreach ($approvedRequestsThisMonth as $reqRow) {
                $days = Carbon::parse($reqRow->start_date)->diffInDays(Carbon::parse($reqRow->end_date ?? $reqRow->start_date)) + 1;
                $typeName = $reqRow->requestType->type_name ?? '';
                if ($typeName === 'WFH') {
                    $wfhDaysThisMonth += $days;
                } elseif ($typeName === 'TRAVEL') {
                    $travelDaysThisMonth += $days;
                }
            }
        }

        // ============ UPCOMING MEETINGS ============
        $upcomingMeetings = collect();
        if ($showMeetings) {
            $upcomingMeetings = \App\Models\MeetingParticipant::where('user_id', $user->id)
                ->whereHas('meeting', function ($q) use ($today) {
                    $q->where('meeting_date', '>=', $today);
                })
                ->with('meeting')
                ->get()
                ->sortBy(fn($p) => $p->meeting?->meeting_date . ' ' . $p->meeting?->start_time)
                ->take(3)
                ->values();
        }

        // ============ LATEST PAYSLIP ============
        $latestPayslip = null;
        $payrollBreakdown = null;
        if ($showPayroll) {
            $latestPayslip = MonthlyPayroll::where('user_id', $user->id)
                ->orderByDesc('payroll_month')
                ->first();

            if ($latestPayslip) {
                $payrollBreakdown = [
                    'basic' => $latestPayslip->basic_salary,
                    'allowances' => max(0, $latestPayslip->gross_earnings - $latestPayslip->basic_salary - $latestPayslip->overtime_amount),
                    'deductions' => max(0, $latestPayslip->gross_earnings - $latestPayslip->net_payable),
                ];
            }
        }

        // ============ ATTENDANCE CALENDAR (per-day P/A/H/L/W status map, prev/current/next month) ============
        $calWindowStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $calWindowEnd = $today->copy()->addMonthNoOverflow()->endOfMonth();

        $calHolidays = Holiday::where('status', 1)
            ->where(function ($q) use ($calWindowStart, $calWindowEnd) {
                $q->whereBetween('start_date', [$calWindowStart, $calWindowEnd])
                    ->orWhereBetween('end_date', [$calWindowStart, $calWindowEnd])
                    ->orWhere(function ($qq) use ($calWindowStart, $calWindowEnd) {
                        $qq->where('start_date', '<=', $calWindowStart)->where('end_date', '>=', $calWindowEnd);
                    });
            })
            ->get();

        $calLeaves = Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where(function ($q) use ($calWindowStart, $calWindowEnd) {
                $q->whereBetween('start_date', [$calWindowStart, $calWindowEnd])
                    ->orWhereBetween('end_date', [$calWindowStart, $calWindowEnd])
                    ->orWhere(function ($qq) use ($calWindowStart, $calWindowEnd) {
                        $qq->where('start_date', '<=', $calWindowStart)->where('end_date', '>=', $calWindowEnd);
                    });
            })
            ->get();

        $calAttendanceRows = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$calWindowStart->format('Y-m-d'), $calWindowEnd->format('Y-m-d')])
            ->whereNotNull('clock_in')
            ->get()
            ->keyBy(fn($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $calendarStatusMap = [];
        $calWalk = $calWindowStart->copy();
        while ($calWalk <= $calWindowEnd) {
            $dateKey = $calWalk->format('Y-m-d');
            $dayName = $calWalk->format('l');

            $holidayName = null;
            foreach ($calHolidays as $h) {
                if ($calWalk->between($h->start_date, $h->end_date)) {
                    $holidayName = $h->name;
                    break;
                }
            }

            $isWeekoffDay = false;
            foreach ($weekoffs as $weekoff) {
                if ($weekoff->off_type == 'date_based' && $calWalk->between($weekoff->start_date, $weekoff->end_date)) {
                    $isWeekoffDay = true;
                    break;
                }
                if ($weekoff->off_type == 'day_based' && $weekoff->day_name == $dayName) {
                    $isWeekoffDay = true;
                    break;
                }
            }

            $isLeaveDay = false;
            foreach ($calLeaves as $lv) {
                if ($calWalk->between($lv->start_date, $lv->end_date)) {
                    $isLeaveDay = true;
                    break;
                }
            }

            if ($holidayName) {
                $calendarStatusMap[$dateKey] = ['code' => 'H', 'title' => $holidayName];
            } elseif ($isWeekoffDay) {
                $calendarStatusMap[$dateKey] = ['code' => 'W', 'title' => 'Week Off'];
            } elseif ($calAttendanceRows->has($dateKey)) {
                $calendarStatusMap[$dateKey] = ['code' => 'P', 'title' => 'Present'];
            } elseif ($isLeaveDay) {
                $calendarStatusMap[$dateKey] = ['code' => 'L', 'title' => 'On Leave'];
            } elseif ($calWalk->lt($today)) {
                $calendarStatusMap[$dateKey] = ['code' => 'A', 'title' => 'Absent'];
            }
            // future working days (no holiday/weekoff/leave/attendance yet) get no entry

            $calWalk->addDay();
        }

        // ============ RECENT ACTIVITY (merged from already-fetched data) ============
        $recentActivity = collect();

        foreach ($recentLeaves as $leave) {
            $recentActivity->push([
                'type' => 'Leave',
                'icon' => 'calendar',
                'title' => ($leave->leave_type_name ?? 'Leave') . ' — ' . \Carbon\Carbon::parse($leave->start_date)->format('d M'),
                'status' => $leave->status,
                'date' => $leave->created_at,
            ]);
        }

        foreach ($recentTasks as $task) {
            $recentActivity->push([
                'type' => 'Task',
                'icon' => 'check-square',
                'title' => $task->title,
                'status' => $task->status,
                'date' => $task->deadline_date,
            ]);
        }

        foreach ($myRequests as $reqRow) {
            $recentActivity->push([
                'type' => $reqRow->requestType->type_name ?? 'Request',
                'icon' => 'send',
                'title' => ($reqRow->requestType->type_name ?? 'Request') . ' — ' . \Carbon\Carbon::parse($reqRow->start_date)->format('d M'),
                'status' => strtolower($reqRow->status),
                'date' => $reqRow->applied_date ?? $reqRow->created_at,
            ]);
        }

        foreach ($recentOvertime as $ot) {
            $recentActivity->push([
                'type' => 'Overtime',
                'icon' => 'clock',
                'title' => ($ot->overtime_hours ?? 0) . ' hrs — ' . \Carbon\Carbon::parse($ot->date)->format('d M'),
                'status' => $ot->status,
                'date' => $ot->created_at,
            ]);
        }

        $recentActivity = $recentActivity
            ->sortByDesc(fn($item) => (string) $item['date'])
            ->take(6)
            ->values();

        // ============ PENDING APPROVALS (sum of the employee's own items still awaiting a decision) ============
        $pendingApprovalsCount = $pendingLeaveCount
            + ($requestCounts['pending'] ?? 0)
            + ($overtimeThisMonth['pending_count'] ?? 0);

        $data = [
            'page_title' => 'My Dashboard',
            'user' => $user,
            'designation' => $jobDetail->designation_name ?? 'N/A',
            'department' => $jobDetail->department_name ?? 'N/A',
            'reporting_head_name' => $jobDetail->reporting_head_name ?? null,
            'joining_date' => $jobDetail->joining_date ?? null,
            'profile_image' => $basicDetail->profile_image ?? null,
            'today_attendance' => $todayAttendance,
            'today_shift' => $todayShift,
            'is_today_holiday' => $isTodayHoliday,
            'is_today_weekoff' => $isTodayWeekoff,
            'is_today_on_leave' => $isTodayOnLeave,
            'today_status' => $isTodayHoliday ? 'Holiday' : ($isTodayWeekoff ? 'Week Off' : ($isTodayOnLeave ? 'On Leave' : 'Working Day')),
            'monthly_attendance' => [
                'present' => $monthlyAttendance,
                'absent' => max(0, round($absentDays, 1)), // Ensure not negative
                'leave_days' => round($leaveDays, 1),
                'holiday_days' => $holidayDays,
                'weekoff_days' => $weekoffDays,
                'percentage' => $workingDays > 0 ? round(($monthlyAttendance / $workingDays) * 100, 2) : 0,
                'working_days' => $workingDays,
                'total_days_month' => $month->daysInMonth,
                'days_remaining' => $month->daysInMonth - $today->day,
                'leave_count' => $leave_count,
            ],
            'leave_balance' => $leaveBalance,              // NOW DEFINED
            // 'pending_leaves' => $pendingLeaves,            // NOW DEFINED
            'pending_tasks' => $pendingTasks,              // NOW DEFINED
            'recent_tasks' => $recentTasks,                 // NOW DEFINED
            'upcoming_holidays' => $upcomingHolidays,
            'upcoming_weekoffs' => $upcomingWeekoffs,
            'recent_announcements' => $recentAnnouncements, // NOW DEFINED
            'profile_completion' => $profileCompletion,     // NOW DEFINED
            'holidays' => $holidays,
            'weekoffs' => $weekoffs,
            'recent_leaves' => $recentLeaves,
            'working_hours_today' => $workingHoursToday,
            'show_overtime' => $showOvertime,
            'show_wfh_travel' => $showWfhTravel,
            'show_meetings' => $showMeetings,
            'show_payroll' => $showPayroll,
            'show_attendance' => $showAttendance,
            'show_tasks' => $showTasks,
            'show_regularization' => $showRegularization,
            'show_performance' => $showPerformance,
            'kpi_score' => $kpiScore,
            'recent_regularizations' => $recentRegularizations,
            'expense_balance' => $expenseBalance,
            'pending_expense_count' => $pendingExpenseCount,
            'recent_expenses' => $recentExpenses,
            'show_expenses' => $showExpenses,
            'show_holidays' => $showHolidays,
            'show_announcements' => $showAnnouncements,
            'overtime_this_month' => $overtimeThisMonth,
            'recent_overtime' => $recentOvertime,
            'my_requests' => $myRequests,
            'request_counts' => $requestCounts,
            'upcoming_meetings' => $upcomingMeetings,
            'latest_payslip' => $latestPayslip,
            'recent_activity' => $recentActivity,
            'task_status_counts' => $taskStatusCounts,
            'total_tasks_assigned' => $totalTasksAssigned,
            'completed_tasks_count' => $completedTasksCount,
            'pending_leave_count' => $pendingLeaveCount,
            'pending_approvals_count' => $pendingApprovalsCount,
            'payroll_breakdown' => $payrollBreakdown,
            'attendance_trend_labels' => $attendanceTrendLabels,
            'attendance_trend_present' => $attendanceTrendPresent,
            'attendance_trend_absent' => $attendanceTrendAbsent,
            'attendance_trend_leave' => $attendanceTrendLeave,
            'calendar_status_map' => $calendarStatusMap,
            'wfh_days_this_month' => $wfhDaysThisMonth,
            'travel_days_this_month' => $travelDaysThisMonth,
        ];

        return view('client.dashboard.employee', $data);
    }

    private function isWeekoffForUser($userId, $date)
    {
        $dateObj = Carbon::parse($date);
        $dayName = $dateObj->format('l');

        return UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->where(function ($query) use ($date, $dayName) {
                $query->where(function ($q) use ($date) {
                    $q->where('off_type', 'date_based')
                        ->where('start_date', '<=', $date)
                        ->where('end_date', '>=', $date);
                })->orWhere(function ($q) use ($dayName) {
                    $q->where('off_type', 'day_based')
                        ->where('day_name', $dayName);
                });
            })
            ->exists();
    }

    private function getUpcomingWeekoffs($userId)
    {
        $today = Carbon::today();
        $upcomingWeekoffs = [];

        // Get all active weekoffs for the user
        $weekoffs = UserWeekoffs::where('user_id', $userId)
            ->where('status', 1)
            ->get();

        // Look ahead for the next 30 days
        for ($i = 1; $i <= 30; $i++) {
            $date = $today->copy()->addDays($i);
            $dayName = $date->format('l');

            $isWeekoff = false;
            foreach ($weekoffs as $weekoff) {
                if ($weekoff->off_type == 'date_based') {
                    if ($date->between($weekoff->start_date, $weekoff->end_date)) {
                        $isWeekoff = true;
                        break;
                    }
                } else if ($weekoff->off_type == 'day_based') {
                    if ($weekoff->day_name == $dayName) {
                        $isWeekoff = true;
                        break;
                    }
                }
            }

            if ($isWeekoff) {
                $upcomingWeekoffs[] = [
                    'date' => $date->format('Y-m-d'),
                    'day' => $date->format('l'),
                    'formatted' => $date->format('d M, Y')
                ];

                // Limit to 5 upcoming week-offs
                if (count($upcomingWeekoffs) >= 5) {
                    break;
                }
            }
        }

        return $upcomingWeekoffs;
    }

    /**
     * Helper method to check if a date is a holiday
     */
    private function isHoliday($date)
    {
        return Holiday::where('status', 1)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Helper method to get upcoming holidays
     */
    private function getUpcomingHolidays($userId = null)
    {
        $today = Carbon::today();

        return Holiday::where('start_date', '>=', $today)
            ->where('status', 1)
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(function ($holiday) {
                return [
                    'name' => $holiday->name,
                    'start_date' => $holiday->start_date,
                    'end_date' => $holiday->end_date,
                    'formatted' => Carbon::parse($holiday->start_date)->format('d M, Y'),
                    'days' => Carbon::parse($holiday->start_date)->diffInDays(Carbon::parse($holiday->end_date)) + 1
                ];
            });
    }

    // ==================== HELPER METHODS ====================

    private function getTodayAttendanceStats()
    {
        $today = Carbon::today();
        $total = User::where('status', 1)->where('role', "!=", 'admin')->count();
        $present = Attendance::whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->count();

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $total - $present,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
        ];
    }

    private function getEmployeesOnLeaveToday()
    {
        $today = Carbon::today()->format('Y-m-d');

        return Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();
    }

    private function getUpcomingBirthdays()
    {
        $today = Carbon::today();

        $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->whereNotNull('user_basic_details.dob')
            ->where('users.status', 1)
            ->select('users.id', 'users.name', 'user_basic_details.dob', 'user_basic_details.profile_image')
            ->get();

        $birthdays = [];

        foreach ($users as $user) {
            if ($user->dob) {
                $dob = Carbon::parse($user->dob);
                $nextBirthday = Carbon::create($today->year, $dob->month, $dob->day);

                if ($nextBirthday->lt($today)) {
                    $nextBirthday->addYear();
                }

                $daysUntil = $today->diffInDays($nextBirthday);

                if ($daysUntil <= 30) {
                    $birthdays[] = [
                        'user' => $user,
                        'date' => $nextBirthday,
                        'days_until' => $daysUntil,
                        'age' => $dob->age + ($nextBirthday->year - $today->year)
                    ];
                }
            }
        }

        usort($birthdays, function ($a, $b) {
            return $a['days_until'] - $b['days_until'];
        });

        return array_slice($birthdays, 0, 5);
    }

    private function getUpcomingAnniversaries()
    {
        $today = Carbon::today();

        $users = User::join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->whereNotNull('user_job_details.joining_date')
            ->where('users.status', 1)
            ->select('users.id', 'users.name', 'user_job_details.joining_date', 'user_basic_details.profile_image')
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->get();

        $anniversaries = [];

        foreach ($users as $user) {
            if ($user->joining_date) {
                $joining = Carbon::parse($user->joining_date);
                $nextAnniversary = Carbon::create($today->year, $joining->month, $joining->day);

                if ($nextAnniversary->lt($today)) {
                    $nextAnniversary->addYear();
                }

                $daysUntil = $today->diffInDays($nextAnniversary);
                $years = $nextAnniversary->year - $joining->year;

                if ($daysUntil <= 30) {
                    $anniversaries[] = [
                        'user' => $user,
                        'date' => $nextAnniversary,
                        'days_until' => $daysUntil,
                        'years' => $years
                    ];
                }
            }
        }

        usort($anniversaries, function ($a, $b) {
            return $a['days_until'] - $b['days_until'];
        });

        return array_slice($anniversaries, 0, 5);
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

    private function getMonthlyAttendanceChart()
{
    $today = Carbon::today();
    $totalEmployees = User::where('status', 1)->where('role', "!=", 'admin')->count();

    // Initialize the data structure
    $chartData = [
        'present' => [],
        'absent' => [],
        'days' => [], // ✅ ADD THIS - to store day names
        'average' => 0,
        'totalPresent' => 0,
        'totalAbsent' => 0
    ];

    // Get data for last 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = $today->copy()->subDays($i);
        
        // ✅ Get the day name (Mon, Tue, Wed, etc.)
        $dayName = $date->format('D'); // Returns: Mon, Tue, Wed, Thu, Fri, Sat, Sun
        $chartData['days'][] = $dayName;

        // Get present count for this day
        $presentCount = Attendance::whereDate('date', $date)
            ->whereNotNull('clock_in')
            ->count();

        // Get leave count for this day
        $leaveCount = Leave::where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->count();

        // Calculate absent (total - present - leave)
        $absentCount = $totalEmployees - $presentCount - $leaveCount;

        $chartData['present'][] = $presentCount;
        $chartData['absent'][] = max(0, $absentCount);

        $chartData['totalPresent'] += $presentCount;
        $chartData['totalAbsent'] += max(0, $absentCount);
    }

    // Calculate average attendance
    if ($totalEmployees > 0) {
        $chartData['average'] = round(
            ($chartData['totalPresent'] / ($totalEmployees * 7)) * 100
        );
    }

    return $chartData;
}

    private function calculateProfileCompletion($userId)
    {
        $score = 0;
        $total = 10;

        // Check basic details
        $basic = UserBasicDetail::where('user_id', $userId)->first();
        if ($basic) {
            if ($basic->father_name) $score++;
            if ($basic->mother_name) $score++;
            if ($basic->dob) $score++;
            if ($basic->gender) $score++;
            if ($basic->profile_image) $score++;
            if ($basic->aadhaar_no) $score++;
            if ($basic->pan_no) $score++;
        }

        // Check bank details
        $bank = UserBankDetail::where('user_id', $userId)->first();
        if ($bank && $bank->account_number) $score++;

        // Check job details
        $job = UserJobDetail::where('user_id', $userId)->first();
        if ($job) $score++;

        // Check location
        $location = UserLocation::where('user_id', $userId)->first();
        if ($location && $location->address) $score++;

        return [
            'percentage' => min(round(($score / $total) * 100, 2), 100),
            'completed' => $score,
            'total' => $total
        ];
    }
    // Add these methods to your DashboardController.php

    private function getTaskStatistics()
    {
        $total_tasks = Task::count();
        $pending_tasks = Task::where('status', 'pending')->count();
        $in_progress_tasks = Task::where('status', 'in_progress')->count();
        $completed_tasks = Task::where('status', 'completed')->count();
        $approved_tasks = Task::where('status', 'approved')->count();
        $rejected_tasks = Task::where('status', 'rejected')->count();

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

    private function getExpenseStatistics()
    {
        $authUser = Auth::user();
        $userId = $authUser->id;

        $currentBalance = UserExpenseBalance::sum('current_balance');
        $AdvanceBalance = UserExpenseBalance::sum('advance_balance');
        $SettlementBalance = UserExpenseBalance::sum('settlement_balance');
        $ReimbursementBalance = UserExpenseBalance::sum('reimbursement_balance');

        // Build the expenses query
        $expenseQuery = Expense::with(['user', 'expenseType', 'project', 'payments'])
            ->join('users', 'expenses.user_id', '=', 'users.id')
            ->leftJoin('expense_types', 'expenses.expense_type', '=', 'expense_types.id')
            ->leftJoin('user_job_details', 'expenses.user_id', '=', 'user_job_details.user_id')
            ->leftJoin('projects', 'expenses.project_id', '=', 'projects.id')
            ->select(
                'expenses.*',
                'users.name as user_name',
                'users.email as user_email',
                'users.employee_id',
                'expense_types.name as expense_type_name',
                'projects.name as project_name',
                'user_job_details.department',
                'user_job_details.designation',
                'user_job_details.reporting_head'
            );

        // Permission-based access
        if (app(RbacService::class)->scopeFor($authUser, 'expenses', 'view') !== 'company') {
            $expenseQuery->where(function ($q) use ($authUser, $userId) {
                $q->where('expenses.user_id', $userId)
                    ->orWhereIn('users.id', function ($sub) use ($authUser) {
                        $sub->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $authUser->id);
                    });
            });
        }
        // ==================== ENHANCED COUNT STATISTICS ====================

        // Base query for counts (without pagination)
        $countsQuery = $expenseQuery;

        $allExpenses = (clone $countsQuery)->get();

        // Separate by requirement type
        $advanceExpenses = $allExpenses->where('requirement_type', 'advance');
        $settlementExpenses = $allExpenses->where('requirement_type', 'settlement');
        $reimbursementExpenses = $allExpenses->where('requirement_type', 'reimbursement');

        // ==================== TOTAL COUNTS ====================
        $totalExpenses = $allExpenses->whereIn('requirement_type', ['advance','reimbursement'])->count();
        $totalAmount = $allExpenses->whereIn('requirement_type', ['advance','reimbursement'])->sum('amount');
        // $totalExpenses = $advanceExpenses->count() + $reimbursementExpenses->count();
        // $totalAmount = $advanceExpenses->sum('amount') + $reimbursementExpenses->sum('amount');

        $totalAdvanceCount = $advanceExpenses->count();
        $totalAdvanceAmount = $advanceExpenses->sum('amount');

        $totalSettlementCount = $settlementExpenses->count();
        $totalSettlementAmount = $settlementExpenses->sum('amount');

        $totalReimbursementCount = $reimbursementExpenses->count();
        $totalReimbursementAmount = $reimbursementExpenses->sum('amount');

        // ==================== PENDING COUNTS ====================
        $pendingAdvanceCount = $advanceExpenses->where('status', 'pending')->count();
        $pendingAdvanceAmount = $advanceExpenses->where('status', 'pending')->sum('amount');

        $pendingSettlementCount = $settlementExpenses->where('status', 'pending')->count();
        $pendingSettlementAmount = $settlementExpenses->where('status', 'pending')->sum('amount');

        $pendingReimbursementCount = $reimbursementExpenses->where('status', 'pending')->count();
        $pendingReimbursementAmount = $reimbursementExpenses->where('status', 'pending')->sum('amount');

        $pendingCount = $pendingAdvanceCount + $pendingReimbursementCount;
        $pendingAmount = $pendingAdvanceAmount + $pendingReimbursementAmount;

        // ==================== APPROVED COUNTS ====================
        $approvedAdvanceCount = $advanceExpenses->where('status', 'approved')->count();
        $approvedAdvanceAmount = $advanceExpenses->where('status', 'approved')->sum('amount');

        $approvedSettlementCount = $settlementExpenses->where('status', 'approved')->count();
        $approvedSettlementAmount = $settlementExpenses->where('status', 'approved')->sum('amount');

        $approvedReimbursementCount = $reimbursementExpenses->where('status', 'approved')->count();
        $approvedReimbursementAmount = $reimbursementExpenses->where('status', 'approved')->sum('amount');

        $approvedCount = $approvedAdvanceCount + $approvedReimbursementCount;
        $approvedAmount = $approvedAdvanceAmount + $approvedReimbursementAmount;

        // ==================== COMPLETED COUNTS ====================
        $completedAdvanceCount = $advanceExpenses->where('status', 'complete')->count();
        $completedAdvanceAmount = $advanceExpenses->where('status', 'complete')->sum('amount');

        $completedSettlementCount = $settlementExpenses->where('status', 'complete')->count();
        $completedSettlementAmount = $settlementExpenses->where('status', 'complete')->sum('amount');

        $completedReimbursementCount = $reimbursementExpenses->where('status', 'complete')->count();
        $completedReimbursementAmount = $reimbursementExpenses->where('status', 'complete')->sum('amount');

        $completedCount = $completedAdvanceCount + $completedReimbursementCount;
        $completedAmount = $completedAdvanceAmount + $completedReimbursementAmount;

        // ==================== CANCELLED COUNTS ====================
        $cancelledAdvanceCount = $advanceExpenses->where('status', 'cancelled')->count();

        $cancelledAdvanceAmount = $advanceExpenses->where('status', 'cancelled')->sum('amount');

        $cancelledSettlementCount = $settlementExpenses->where('status', 'cancelled')->count();
        $cancelledSettlementAmount = $settlementExpenses->where('status', 'cancelled')->sum('amount');

        $cancelledReimbursementCount = $reimbursementExpenses->where('status', 'cancelled')->count();
        $cancelledReimbursementAmount = $reimbursementExpenses->where('status', 'cancelled')->sum('amount');

        $cancelledCount = $cancelledAdvanceCount  + $cancelledReimbursementCount;
        $cancelledAmount = $cancelledAdvanceAmount + $cancelledReimbursementAmount;
        // Get paginated results
        $expenses = $expenseQuery
            ->orderBy('expenses.date', 'desc')
            ->orderBy('expenses.created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Transform file URLs
        $expenses->getCollection()->transform(function ($expense) {
            $expense->file = app(\App\Services\Expense\ExpenseAttachmentService::class)->url($expense->file, (int) $expense->id);
            return $expense;
        });


        // Get employees for filter dropdown
        $employeesQuery = User::where('status', 1)
            ->with(['jobDetails']);

        if (app(RbacService::class)->scopeFor($authUser, 'expenses', 'view') !== 'company') {
            $employeesQuery->where(function ($q) use ($authUser, $userId) {
                $q->managedBy($authUser->id)->orWhere('id', $authUser->id);
            });
        }

        return  [

            'currentBalance' => $currentBalance,
            'totalAdvanceTaken' => $AdvanceBalance,
            'totalSettlementDone' => $SettlementBalance,
            'totalReimbursementDone' => $ReimbursementBalance,
            // Combined totals
            'totalExpenses' => $totalExpenses,
            'totalAmount' => $totalAmount,

            // Advance specific totals (matching card variable names)
            'totalAdvanceCount' => $totalAdvanceCount,
            'totalAdvanceAmount' => $totalAdvanceAmount,

            // Settlement specific totals (matching card variable names)
            'totalSettlementCount' => $totalSettlementCount,
            'totalSettlementAmount' => $totalSettlementAmount,

            // Reimbursement specific totals (matching card variable names)
            'totalReimbursementCount' => $totalReimbursementCount,
            'totalReimbursementAmount' => $totalReimbursementAmount,

            // Pending with breakdown (matching card variable names)
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
            'pendingAdvanceCount' => $pendingAdvanceCount,
            'pendingAdvanceAmount' => $pendingAdvanceAmount,
            'pendingSettlementCount' => $pendingSettlementCount,
            'pendingSettlementAmount' => $pendingSettlementAmount,
            'pendingReimbursementCount' => $pendingReimbursementCount,
            'pendingReimbursementAmount' => $pendingReimbursementAmount,

            // Approved with breakdown (matching card variable names)
            'approvedCount' => $approvedCount,
            'approvedAmount' => $approvedAmount,
            'approvedAdvanceCount' => $approvedAdvanceCount,
            'approvedAdvanceAmount' => $approvedAdvanceAmount,
            'approvedSettlementCount' => $approvedSettlementCount,
            'approvedSettlementAmount' => $approvedSettlementAmount,
            'approvedReimbursementCount' => $approvedReimbursementCount,
            'approvedReimbursementAmount' => $approvedReimbursementAmount,

            // Completed with breakdown (matching card variable names)
            'completedCount' => $completedCount,
            'completedAmount' => $completedAmount,
            'completedAdvanceCount' => $completedAdvanceCount,
            'completedAdvanceAmount' => $completedAdvanceAmount,
            'completedSettlementCount' => $completedSettlementCount,
            'completedSettlementAmount' => $completedSettlementAmount,
            'completedReimbursementCount' => $completedReimbursementCount,
            'completedReimbursementAmount' => $completedReimbursementAmount,

            // Cancelled with breakdown (matching card variable names)
            'cancelledCount' => $cancelledCount,
            'cancelledAmount' => $cancelledAmount,
            'cancelledAdvanceCount' => $cancelledAdvanceCount,
            'cancelledAdvanceAmount' => $cancelledAdvanceAmount,
            'cancelledSettlementCount' => $cancelledSettlementCount,
            'cancelledSettlementAmount' => $cancelledSettlementAmount,
            'cancelledReimbursementCount' => $cancelledReimbursementCount,
            'cancelledReimbursementAmount' => $cancelledReimbursementAmount,
        ];
    }

    private function getMostRegularEmployees()
    {
        $working_days = $this->getWorkingDaysThisMonth();
        $today = Carbon::today();

        return User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('attendances', function ($join) use ($today) {
                $join->on('users.id', '=', 'attendances.user_id')
                    ->whereMonth('attendances.date', $today->month)
                    ->whereYear('attendances.date', $today->year)
                    ->whereNotNull('attendances.clock_in');
            })
            ->select(
                'users.id',
                'users.name',
                'user_basic_details.profile_image',
                'designations.name as designation_name',
                'departments.name as department_name',
                DB::raw('COUNT(DISTINCT attendances.id) as present_days')
            )
            ->where('users.status', 1)
            ->groupBy(
                'users.id',
                'users.name',
                'user_basic_details.profile_image',
                'designations.name',
                'departments.name'
            )
            ->orderBy('present_days', 'desc')
            ->limit(3)
            ->get();
    }

    /**
     * Top 5 performers for a given month, ranked by attendance regularity.
     * $month expects 'Y-m' (e.g. '2026-09'); defaults to the current month.
     */
    private function getTopPerformers($month = null)
    {
        $monthDate = $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : Carbon::now();
        $workingDays = max(1, $this->getWorkingDaysThisMonth($monthDate));

        $employees = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('attendances', function ($join) use ($monthDate) {
                $join->on('users.id', '=', 'attendances.user_id')
                    ->whereMonth('attendances.date', $monthDate->month)
                    ->whereYear('attendances.date', $monthDate->year)
                    ->whereNotNull('attendances.clock_in');
            })
            ->select(
                'users.id',
                'users.name',
                'users.employee_id',
                'user_basic_details.profile_image',
                DB::raw('COUNT(DISTINCT attendances.id) as present_days')
            )
            ->where('users.status', 1)
            ->where('users.role', '!=', 'admin')
            ->groupBy('users.id', 'users.name', 'users.employee_id', 'user_basic_details.profile_image')
            ->orderBy('present_days', 'desc')
            ->limit(5)
            ->get();

        return $employees->map(function ($employee) use ($workingDays) {
            $employee->performance_percentage = min(100, round(($employee->present_days / $workingDays) * 100));
            return $employee;
        });
    }

    /**
     * Last 6 months (including current) for the performance month filter dropdown.
     */
    private function getPerformanceMonthOptions()
    {
        return collect(range(0, 5))->map(function ($i) {
            $date = Carbon::now()->subMonths($i);
            return ['value' => $date->format('Y-m'), 'label' => $date->format('M Y')];
        });
    }

    /**
     * Company overview trend for the last $months months: monthly expense,
     * monthly payroll cost, cumulative headcount, attendance rate %, and
     * tasks completed. Used by the "Company Overview Trend" chart.
     */
    private function getCompanyOverviewTrend($months = 6)
    {
        $monthDates = collect(range($months - 1, 0))->map(function ($i) {
            return Carbon::now()->subMonths($i)->startOfMonth();
        })->values();

        $monthKeys = $monthDates->map(fn ($m) => $m->format('Y-m'));
        $labels = $monthDates->map(fn ($m) => $m->format('M Y'));

        $rangeStart = $monthDates->first()->copy()->startOfMonth();
        $rangeEnd = $monthDates->last()->copy()->endOfMonth();

        $expenseByMonth = Expense::whereBetween('date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->whereIn('status', ['approved', 'complete'])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $payrollByMonth = MonthlyPayroll::whereIn('payroll_month', $monthKeys->all())
            ->selectRaw('payroll_month, SUM(net_payable) as total')
            ->groupBy('payroll_month')
            ->pluck('total', 'payroll_month');

        // Loan repayments / other money-related transactions collected that month
        $loanByMonth = LoanRepayment::whereIn('salary_month', $monthKeys->all())
            ->selectRaw('salary_month, SUM(paid_amount) as total')
            ->groupBy('salary_month')
            ->pluck('total', 'salary_month');

        $expense = [];
        $payroll = [];
        $loanOther = [];

        foreach ($monthDates as $monthDate) {
            $key = $monthDate->format('Y-m');

            $expense[] = round((float) ($expenseByMonth[$key] ?? 0), 2);
            $payroll[] = round((float) ($payrollByMonth[$key] ?? 0), 2);
            $loanOther[] = round((float) ($loanByMonth[$key] ?? 0), 2);
        }

        return [
            'labels' => $labels->all(),
            'expense' => $expense,
            'payroll' => $payroll,
            'loan_other' => $loanOther,
        ];
    }

    /**
     * Flat "Y-m-d" => holiday name map, used to highlight holiday dates
     * in the attendance calendar regardless of which month is displayed.
     */
    private function getCalendarHolidays()
    {
        $holidays = Holiday::where('status', 1)->get(['name', 'start_date', 'end_date']);
        $map = [];

        foreach ($holidays as $holiday) {
            $current = Carbon::parse($holiday->start_date);
            $end = Carbon::parse($holiday->end_date ?? $holiday->start_date);

            while ($current->lte($end)) {
                $map[$current->format('Y-m-d')] = $holiday->name;
                $current->addDay();
            }
        }

        return $map;
    }

    private function getLeastTasksEmployees()
    {
        return User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('task_assigns', 'users.id', '=', 'task_assigns.assigned_to')
            ->leftJoin('tasks', 'task_assigns.task_id', '=', 'tasks.id')
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
            ->where('users.role', "!=", "admin")
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

    private function getWorkingDaysThisMonth($monthDate = null)
    {
        $monthDate = $monthDate ? $monthDate->copy() : Carbon::now();
        $startOfMonth = $monthDate->copy()->startOfMonth();
        $endOfMonth = $monthDate->copy()->endOfMonth();

        $holidays = Holiday::whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->where('status', 1)
            ->get();

        $workingDays = 0;
        $current = $startOfMonth->copy();

        while ($current <= $endOfMonth) {
            // Skip weekends (assuming Saturday and Sunday are weekends)
            if (!$current->isSaturday() && !$current->isSunday()) {
                $isHoliday = false;
                foreach ($holidays as $holiday) {
                    if ($current->between($holiday->start_date, $holiday->end_date)) {
                        $isHoliday = true;
                        break;
                    }
                }
                if (!$isHoliday) {
                    $workingDays++;
                }
            }
            $current->addDay();
        }

        return $workingDays;
    }
    /**
     * Get project statistics and distribution
     */
    private function getProjectStatistics()
    {
        // Basic project counts
        $totalProjects = Project::count();
        $activeProjects = Project::where('status', 'active')->count();
        $completedProjects = Project::where('status', 'completed')->count();
        $ongoingProjects = Project::where('status', 'ongoing')->count();
        $onHoldProjects = Project::where('status', 'on_hold')->count();
        $cancelledProjects = Project::where('status', 'cancelled')->count();

        // Project assignments
        $totalAssignments = ProjectAssign::count();
        $activeAssignments = ProjectAssign::where('status', 1)->count();

        // Project heads count
        $projectHeads = ProjectAssign::where('is_head', 1)
            ->where('status', 1)
            ->count();

        // Projects by month (last 6 months)
        $projectsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $count = Project::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $projectsByMonth['months'][] = $month->format('M Y');
            $projectsByMonth['counts'][] = $count;
        }

        // Projects by status for pie chart
        $projectsByStatus = [
            'active' => $activeProjects,
            'ongoing' => $ongoingProjects,
            'completed' => $completedProjects,
            'on_hold' => $onHoldProjects,
            'cancelled' => $cancelledProjects
        ];

        // Top projects by team size
        $topProjectsByTeam = Project::where('projects.status', "!=", "cancelled")
            ->leftJoin('project_assigns', 'projects.id', '=', 'project_assigns.project_id')
            ->select(
                'projects.id',
                'projects.name',
                'projects.project_code',
                'projects.status',
                DB::raw('COUNT(DISTINCT project_assigns.user_id) as team_size'),
                DB::raw('SUM(CASE WHEN project_assigns.is_head = 1 THEN 1 ELSE 0 END) as heads_count')
            )
            ->groupBy('projects.id', 'projects.name', 'projects.project_code', 'projects.status')
            ->orderBy('team_size', 'desc')
            ->limit(5)
            ->get();


        // Department wise project distribution
        $deptProjects = Department::leftJoin('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->leftJoin('project_assigns', 'user_job_details.user_id', '=', 'project_assigns.user_id')
            ->leftJoin('projects', 'project_assigns.project_id', '=', 'projects.id')
            ->select(
                'departments.id',
                'departments.name',
                DB::raw('COUNT(DISTINCT projects.id) as project_count'),
                DB::raw('COUNT(DISTINCT project_assigns.user_id) as assigned_users')
            )
            ->where('projects.status', '!=', 'cancelled')
            ->groupBy('departments.id', 'departments.name')
            ->having('project_count', '>', 0)
            ->orderBy('project_count', 'desc')
            ->get();

        // Project completion timeline (next deadlines)
        $upcomingDeadlines = Project::where('status', 'ongoing')
            ->where('deadline_date', '>=', Carbon::today())
            ->where('deadline_date', '<=', Carbon::today()->addDays(30))
            ->orderBy('deadline_date')
            ->limit(5)
            ->get(['id', 'name', 'project_code', 'deadline_date']);

        // Recent projects
        $recentProjects = Project::latest()
            ->limit(5)
            ->get(['id', 'name', 'project_code', 'status', 'start_date', 'deadline_date']);

        return [
            'total_projects' => $totalProjects,
            'active_projects' => $activeProjects,
            'completed_projects' => $completedProjects,
            'ongoing_projects' => $ongoingProjects,
            'on_hold_projects' => $onHoldProjects,
            'cancelled_projects' => $cancelledProjects,
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'project_heads' => $projectHeads,
            'projects_by_month' => $projectsByMonth,
            'projects_by_status' => $projectsByStatus,
            'top_projects_by_team' => $topProjectsByTeam,
            'dept_projects' => $deptProjects,
            'upcoming_deadlines' => $upcomingDeadlines,
            'recent_projects' => $recentProjects,
        ];
    }

    /**
     * Get project progress data for charts
     */
    private function getProjectChartData()
    {
        // Status distribution for pie chart
        $statusData = [
            'labels' => [],
            'data' => [],
            'colors' => []
        ];

        $statusColors = [
            'active' => '#4361ee',
            'ongoing' => '#f8961e',
            'completed' => '#06d6a0',
            'on_hold' => '#ffd166',
            'cancelled' => '#ef476f'
        ];

        foreach ($this->getProjectStatistics()['projects_by_status'] as $status => $count) {
            if ($count > 0) {
                $statusData['labels'][] = ucfirst($status) . ' (' . $count . ')';
                $statusData['data'][] = $count;
                $statusData['colors'][] = $statusColors[$status] ?? '#6c757d';
            }
        }

        // Project assignments by department
        $deptData = [
            'labels' => [],
            'data' => []
        ];

        $deptProjects = Department::leftJoin('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->leftJoin('project_assigns', 'user_job_details.user_id', '=', 'project_assigns.user_id')
            ->select(
                'departments.name',
                DB::raw('COUNT(DISTINCT project_assigns.project_id) as project_count')
            )
            ->whereNotNull('project_assigns.project_id')
            ->groupBy('departments.name')
            ->orderBy('project_count', 'desc')
            ->limit(5)
            ->get();

        foreach ($deptProjects as $dept) {
            $deptData['labels'][] = $dept->name;
            $deptData['data'][] = $dept->project_count;
        }

        return [
            'status_chart' => $statusData,
            'dept_chart' => $deptData
        ];
    }

    /**
     * Get employees with most attendance regularization requests
     */
    private function getMostRegularizationRequests()
    {
        $today = Carbon::today();

        return AttendanceRegularization::join('users', 'attendance_regularizations.user_id', '=', 'users.id')
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name as designation_name',
                'departments.name as department_name',
                DB::raw('COUNT(attendance_regularizations.id) as total_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "approved" THEN 1 ELSE 0 END) as approved_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "pending" THEN 1 ELSE 0 END) as pending_requests'),
                DB::raw('SUM(CASE WHEN attendance_regularizations.status = "rejected" THEN 1 ELSE 0 END) as rejected_requests')
            )
            ->where('users.status', 1)
            ->groupBy(
                'users.id',
                'users.name',
                'users.email',
                'users.employee_id',
                'user_basic_details.profile_image',
                'designations.name',
                'departments.name'
            )
            ->orderBy('total_requests', 'desc')
            ->limit(3)
            ->get();
    }

    /**
     * Get monthly regularization statistics
     */
    private function getMonthlyRegularizationStats()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return [
            'total_requests' => AttendanceRegularization::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count(),

            'approved_requests' => AttendanceRegularization::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->where('status', 'approved')
                ->count(),

            'pending_requests' => AttendanceRegularization::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->where('status', 'pending')
                ->count(),

            'rejected_requests' => AttendanceRegularization::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->where('status', 'rejected')
                ->count(),
        ];
    }

    /**
     * Get today's regularization requests
     */
    private function getTodaysRegularizationRequests()
    {
        $today = Carbon::today()->format('Y-m-d');

        return AttendanceRegularization::whereDate('created_at', $today)
            ->count();
    }
}
