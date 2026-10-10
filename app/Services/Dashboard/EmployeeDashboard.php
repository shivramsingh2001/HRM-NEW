<?php

namespace App\Services\Dashboard;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\EmployeeKpiScore;
use App\Models\Expense;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\MonthlyPayroll;
use App\Models\Task;
use App\Models\UserBankDetail;
use App\Models\UserBasicDetail;
use App\Models\UserExpenseBalance;
use App\Models\UserJobDetail;
use App\Models\UserLocation;
use App\Models\UserWeekoffs;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Employee dashboard data — the signed-in employee's own day, month, requests and widgets.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2);
 * DashboardController::index() renders client.dashboard.employee with build().
 */
class EmployeeDashboard
{
    /**
     * Employee Dashboard - Using JOIN queries
     */
    public function build(): array
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
                } elseif ($weekoff->off_type == 'day_based') {
                    // Day-based weekoff
                    if ($weekoff->day_name == $dayName) {
                        $isWeekoff = true;
                        break;
                    }
                }
            }

            if ($isHoliday) {
                $holidayDays++;
            } elseif ($isWeekoff) {
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
                    } elseif ($leave->start_session == 2 && $dateString == $leave->start_date) {
                        $leaveDays += 0.5; // Second half leave
                    } elseif ($leave->end_session == 1 && $dateString == $leave->end_date) {
                        $leaveDays += 0.5; // First half leave on end date
                    } elseif ($leave->end_session == 2 && $dateString == $leave->end_date) {
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

        // ============ ATTENDANCE ATTENTION (this month's days that need an action) ============
        // Past working days of this month (today excluded — the day is still running):
        //  - no clock-in and no approved leave  -> Absent      (Regularize / Apply Leave)
        //  - clocked in, never clocked out      -> Missed clock-out (Regularize)
        //  - marked half day / late             -> Half Day / Late  (Regularize, + Apply Leave for half day)
        // A day already covered by a pending leave or pending regularization shows that
        // instead of the buttons. Newest first; the card lists up to 5.
        $monthAttendanceRows = Attendance::where('user_id', $user->id)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('clock_in')
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $pendingLeaves = Leave::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('start_date', '<=', $today->format('Y-m-d'))
            ->whereRaw('COALESCE(end_date, start_date) >= ?', [$startOfMonth->format('Y-m-d')])
            ->get(['start_date', 'end_date']);

        $pendingRegularizationDates = AttendanceRegularization::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $today->format('Y-m-d')])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        $attentionItems = [];
        $attentionDate = $today->copy()->subDay();
        while ($attentionDate >= $startOfMonth) {
            $dateString = $attentionDate->format('Y-m-d');
            $dayName = $attentionDate->format('l');

            $isOffDay = false;
            foreach ($holidays as $holiday) {
                if ($attentionDate->between($holiday->start_date, $holiday->end_date)) {
                    $isOffDay = true;
                    break;
                }
            }
            foreach ($isOffDay ? [] : $weekoffs as $weekoff) {
                if (($weekoff->off_type == 'date_based' && $attentionDate->between($weekoff->start_date, $weekoff->end_date))
                    || ($weekoff->off_type == 'day_based' && $weekoff->day_name == $dayName)) {
                    $isOffDay = true;
                    break;
                }
            }

            $isLeaveDay = false;
            foreach ($approvedLeaves as $leave) {
                if ($attentionDate->between($leave->start_date, $leave->end_date ?? $leave->start_date)) {
                    $isLeaveDay = true;
                    break;
                }
            }

            $row = $monthAttendanceRows->get($dateString);
            $issue = null;
            if ($row) {
                if (! $row->clock_out) {
                    $issue = ['status' => 'Missed clock-out', 'regularize' => true, 'leave' => false];
                } elseif ($row->attendance_status === 'half_day') {
                    $issue = ['status' => 'Half day', 'regularize' => true, 'leave' => true];
                } elseif ($row->attendance_status === 'late') {
                    $issue = ['status' => 'Late check-in', 'regularize' => true, 'leave' => false];
                }
            } elseif (! $isOffDay && ! $isLeaveDay) {
                $issue = ['status' => 'Absent', 'regularize' => true, 'leave' => true];
            }

            if ($issue) {
                $issue['date'] = $dateString;
                $issue['pending'] = null;
                if (isset($pendingRegularizationDates[$dateString])) {
                    $issue['pending'] = 'Regularization pending';
                } elseif ($issue['leave'] && $pendingLeaves->contains(
                    fn ($l) => $attentionDate->between($l->start_date, $l->end_date ?? $l->start_date)
                )) {
                    $issue['pending'] = 'Leave pending';
                }
                $attentionItems[] = $issue;
            }

            $attentionDate->subDay();
        }
        $attentionTotal = count($attentionItems);
        $attentionItems = array_slice($attentionItems, 0, 5);

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
                'approved_hours' => $overtimeRows->where('status', 'approved')->sum(fn ($o) => $o->approved_hours ?? $o->overtime_hours),
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
                ->sortBy(fn ($p) => $p->meeting?->meeting_date.' '.$p->meeting?->start_time)
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
            ->keyBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'));

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
                'title' => ($leave->leave_type_name ?? 'Leave').' — '.\Carbon\Carbon::parse($leave->start_date)->format('d M'),
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
                'title' => ($reqRow->requestType->type_name ?? 'Request').' — '.\Carbon\Carbon::parse($reqRow->start_date)->format('d M'),
                'status' => strtolower($reqRow->status),
                'date' => $reqRow->applied_date ?? $reqRow->created_at,
            ]);
        }

        foreach ($recentOvertime as $ot) {
            $recentActivity->push([
                'type' => 'Overtime',
                'icon' => 'clock',
                'title' => ($ot->overtime_hours ?? 0).' hrs — '.\Carbon\Carbon::parse($ot->date)->format('d M'),
                'status' => $ot->status,
                'date' => $ot->created_at,
            ]);
        }

        $recentActivity = $recentActivity
            ->sortByDesc(fn ($item) => (string) $item['date'])
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
            'attendance_attention' => $attentionItems,
            'attendance_attention_total' => $attentionTotal,
            'calendar_status_map' => $calendarStatusMap,
            'wfh_days_this_month' => $wfhDaysThisMonth,
            'travel_days_this_month' => $travelDaysThisMonth,
        ];

        return $data;
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
                } elseif ($weekoff->off_type == 'day_based') {
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
                    'formatted' => $date->format('d M, Y'),
                ];

                // Limit to 5 upcoming week-offs
                if (count($upcomingWeekoffs) >= 5) {
                    break;
                }
            }
        }

        return $upcomingWeekoffs;
    }

    private function calculateProfileCompletion($userId)
    {
        $score = 0;
        $total = 10;

        // Check basic details
        $basic = UserBasicDetail::where('user_id', $userId)->first();
        if ($basic) {
            if ($basic->father_name) {
                $score++;
            }
            if ($basic->mother_name) {
                $score++;
            }
            if ($basic->dob) {
                $score++;
            }
            if ($basic->gender) {
                $score++;
            }
            if ($basic->profile_image) {
                $score++;
            }
            if ($basic->aadhaar_no) {
                $score++;
            }
            if ($basic->pan_no) {
                $score++;
            }
        }

        // Check bank details
        $bank = UserBankDetail::where('user_id', $userId)->first();
        if ($bank && $bank->account_number) {
            $score++;
        }

        // Check job details
        $job = UserJobDetail::where('user_id', $userId)->first();
        if ($job) {
            $score++;
        }

        // Check location
        $location = UserLocation::where('user_id', $userId)->first();
        if ($location && $location->address) {
            $score++;
        }

        return [
            'percentage' => min(round(($score / $total) * 100, 2), 100),
            'completed' => $score,
            'total' => $total,
        ];
    }
}
