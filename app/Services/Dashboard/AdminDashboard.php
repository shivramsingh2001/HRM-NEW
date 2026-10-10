<?php

namespace App\Services\Dashboard;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\Project;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Models\UserWeekoffs;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin (and HR) dashboard data. Each module's cards — and their queries — only run when the company's plan includes that module; the cards live in App\Services\Dashboard\Cards.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2);
 * DashboardController::index() renders client.dashboard.admin with build().
 */
class AdminDashboard
{
    /**
     * Admin (and HR) dashboard. Each module's widgets — and their queries —
     * only run when the company's plan includes that module.
     *
     * Period filter (?range=today|last_7_days|this_month|previous_month|custom
     * &from=&to= — see dashboardRange()).
     * - No filter (default): every card shows its normal data — tasks /
     *   projects / expenses / regularizations / leave requests all-time, new
     *   joinings this month, attendance chart last 7 days, and the Present
     *   Today + Today at a glance cards today.
     * - Filter applied: all of those count only what happened in the period.
     * Pending approvals, today's celebrations, upcoming birthdays/holidays, the
     * department split and the 6-month company trend always show "now".
     */
    public function build(): array
    {
        $today = Carbon::today();
        $range = $this->dashboardRange();
        $applied = $range['applied'];
        [$from, $to] = [$range['start'], $range['end']]; // today..today when no filter
        $between = $applied ? [$from->copy()->startOfDay(), $to->copy()->endOfDay()] : null; // null = unfiltered

        $features = app(\App\Services\FeatureService::class);
        $on = fn (string ...$keys) => collect($keys)->contains(fn ($k) => $features->enabledForCurrentTenant($k));

        $show = [
            'attendance' => $on('attendance', 'attendance_face', 'attendance_biometric'),
            'leave' => $on('leave_management'),
            'tasks' => $on('task_single', 'task_group'),
            'projects' => $on('project_management'),
            'expenses' => $on('expense_management'),
            'regularization' => $on('regularization'),
            'holiday' => $on('holiday'),
            'announcements' => $on('announcements'),
        ];

        // Money / assets / meetings cards: in the plan AND the viewer may open the module
        // (same pairing as adminPendingApprovals()).
        $viewer = Auth::user();
        $rbac = app(RbacService::class);
        $may = fn (string $module) => $rbac->can($viewer, $module, 'view');
        $show += [
            'payroll' => $on('payroll') && $may('payroll'),
            'loans' => $on('loan_management') && $may('loans'),
            'overtime' => $on('overtime') && $may('overtime'),
            'assets' => $on('asset_management') && $may('assets'),
            'meetings' => $on('meetings') && $may('meetings'),
        ];

        // Employees (headcount is "now"; joinings follow the period)
        $activeEmployees = User::where('status', 1)->where('role', '!=', 'admin')->count();
        $inactiveEmployees = User::where('status', 0)->where('role', '!=', 'admin')->count();
        $periodJoinings = UserJobDetail::join('users', 'users.id', '=', 'user_job_details.user_id')
            ->where('users.role', '!=', 'admin')
            ->whereBetween('user_job_details.joining_date', $applied
                ? [$from->toDateString(), $to->toDateString()]
                : [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()]) // no filter: this month
            ->count();

        // Attendance over the period (today when no filter): present / leave / holiday / week-off / absent + daily chart series
        $attendance = $this->adminAttendanceBreakdown($from, $to);
        if (! $applied) {
            // No filter: the chart keeps its usual last-7-days view while the cards show today.
            $attendance['chart'] = $this->adminAttendanceBreakdown($today->copy()->subDays(6), $today)['chart'];
        }

        // Department distribution (now)
        $departmentDistribution = Department::leftJoin('user_job_details', 'departments.id', '=', 'user_job_details.department')
            ->leftJoin('users', 'user_job_details.user_id', '=', 'users.id')
            ->select('departments.id', 'departments.name', DB::raw('COUNT(DISTINCT users.id) as users_count'))
            ->where('users.status', 1)
            ->groupBy('departments.id', 'departments.name')
            ->having('users_count', '>', 0)
            ->orderBy('users_count', 'desc')
            ->get();

        $recentAnnouncements = $show['announcements']
            ? Announcement::leftJoin('users', 'announcements.user_id', '=', 'users.id')
                ->select('announcements.*', 'users.name as user_name')
                ->where('announcements.status', 1)->notExpired()
                ->orderBy('announcements.created_at', 'desc')->limit(5)->get()
            : collect();

        // People (now)
        $recentJoinings = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->select('users.id', 'users.name', 'users.employee_id', 'users.created_at', 'user_job_details.joining_date as joined_on',
                'user_basic_details.profile_image', 'designations.name as designation_name')
            ->where('users.status', 1)->where('users.role', '!=', 'admin')
            ->orderBy('users.created_at', 'desc')->limit(5)->get();

        $upcomingHolidays = $show['holiday']
            ? Holiday::where('end_date', '>=', $today)->where('status', 1)->orderBy('start_date')->limit(3)->get()
            : collect();

        // Leave requests raised in the period
        $recentLeaves = $show['leave']
            ? Leave::leftJoin('users', 'leaves.user_id', '=', 'users.id')
                ->leftJoin('user_basic_details', 'user_basic_details.user_id', '=', 'users.id')
                ->leftJoin('leave_types', 'leaves.leave_type', '=', 'leave_types.id')
                ->select('leaves.id', 'leaves.user_id', 'leaves.start_date', 'leaves.end_date', 'leaves.status', 'leaves.created_at',
                    'users.name as user_name', 'users.employee_id', 'user_basic_details.profile_image', 'leave_types.name as leave_type_name')
                ->when($between, fn ($q) => $q->whereBetween('leaves.created_at', $between))
                ->orderBy('leaves.created_at', 'desc')->limit(5)->get()
            : collect();

        $data = [
            'page_title' => 'Admin Dashboard',
            'show' => $show,
            'range' => $range,
            'total_employees' => $activeEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'monthly_joinings' => $periodJoinings,
            'today' => $attendance,
            'present_today' => $attendance['present'],
            'absent_today' => $attendance['absent'],
            'on_leave_today' => $attendance['on_leave'],
            'pending_approvals' => app(Cards\PendingApprovalsCard::class)->build($on),
            // Payroll / loans / overtime follow the period's month (this month when no filter); assets / meetings are "now".
            'payroll_snapshot' => $show['payroll'] ? app(Cards\PayrollCard::class)->forMonth(($applied ? $to : $today)->copy()->startOfMonth()) : null,
            'loan_snapshot' => $show['loans'] ? app(Cards\LoanCard::class)->forMonth(($applied ? $to : $today)->copy()->startOfMonth()) : null,
            'overtime_snapshot' => $show['overtime'] ? app(Cards\OvertimeCard::class)->forMonth(($applied ? $to : $today)->copy()->startOfMonth()) : null,
            'asset_snapshot' => $show['assets'] ? app(Cards\AssetCard::class)->build() : null,
            'meeting_snapshot' => $show['meetings'] ? app(Cards\MeetingCard::class)->build() : null,
            'department_distribution' => $departmentDistribution,
            'recent_announcements' => $recentAnnouncements,
            'recent_joinings' => $recentJoinings,
            'upcoming_holidays' => $upcomingHolidays,
            'today_celebrations' => app(\App\Services\CelebrationService::class)->today(), // card shows only when someone has one today
            'upcoming_birthdays' => app(Cards\CelebrationCards::class)->upcomingBirthdays(),
            'upcoming_anniversaries' => app(Cards\CelebrationCards::class)->upcomingAnniversaries(),
            'recent_leaves' => $recentLeaves,
            'attendance_chart_data' => $show['attendance'] ? $attendance['chart'] : null,
            'top_performers' => [],
            'performance_month_options' => [],
            'selected_performance_month' => null,
            'company_overview_trend' => app(Cards\CompanyTrendCard::class)->build(6),
            'calendar_holidays' => app(Cards\HolidayCalendarCard::class)->build(),
            'colorPalette' => ['#3454d1', '#0d519e', '#1976d2', '#1e88e5', '#2196f3', '#42a5f5', '#64b5f6', '#90caf9'],
        ];

        if ($show['attendance']) {
            $data['selected_performance_month'] = request('performance_month') ?: Carbon::now()->format('Y-m');
            $data['top_performers'] = app(Cards\PerformanceCards::class)->topPerformers($data['selected_performance_month']);
            $data['performance_month_options'] = app(Cards\PerformanceCards::class)->monthOptions();
        }

        if ($show['regularization']) {
            $data['most_regularization_requests'] = app(Cards\RegularizationCard::class)->mostRequests($between);
        }

        if ($show['tasks']) {
            $data = array_merge($data, app(Cards\TaskCards::class)->statistics($between));
            $data['least_tasks_employees'] = app(Cards\TaskCards::class)->leastTasksEmployees($between);
        }

        if ($show['expenses']) {
            $data = array_merge($data, app(Cards\ExpenseCard::class)->statistics($applied ? [$from->toDateString(), $to->toDateString()] : null));
        }

        if ($show['projects']) {
            // Projects created in the period, by status (one grouped query).
            $byStatus = Project::when($between, fn ($q) => $q->whereBetween('created_at', $between))
                ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
            $data = array_merge($data, [
                'total_projects' => (int) $byStatus->sum(),
                'pending_projects' => (int) ($byStatus['pending'] ?? 0),
                'ongoing_projects' => (int) ($byStatus['ongoing'] ?? 0),
                'completed_projects' => (int) ($byStatus['completed'] ?? 0),
                'on_hold_projects' => (int) ($byStatus['on_hold'] ?? 0),
                'cancelled_projects' => (int) ($byStatus['cancelled'] ?? 0),
                'projects_by_status' => $byStatus->map(fn ($c) => (int) $c)->all(),
            ]);
        }

        return $data;
    }

    /**
     * The dashboard period from the query string. Presets: today (default),
     * last_7_days, this_month (1st → today), previous_month (whole month),
     * custom (from / to, swapped if reversed, capped at 366 days).
     *
     * @return array{preset:string,start:Carbon,end:Carbon,label:string,from:string,to:string,days:int,note:?string,is_today:bool,applied:bool}
     */
    private function dashboardRange(): array
    {
        $today = Carbon::today();
        $preset = (string) request('range', 'none');
        $note = null;

        switch ($preset) {
            case 'last_7_days':
                [$start, $end, $label] = [$today->copy()->subDays(6), $today->copy(), 'Last 7 days'];
                break;
            case 'this_month':
                [$start, $end, $label] = [$today->copy()->startOfMonth(), $today->copy(), 'This month'];
                break;
            case 'previous_month':
                $prev = $today->copy()->subMonthNoOverflow();
                [$start, $end, $label] = [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth()->startOfDay(), $prev->format('F Y')];
                break;
            case 'custom':
                try {
                    $start = Carbon::parse((string) request('from'))->startOfDay();
                    $end = Carbon::parse((string) request('to', request('from')))->startOfDay();
                } catch (\Throwable $e) {
                    $start = $end = $today->copy();
                }
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }
                if ($start->diffInDays($end) > 365) {
                    $end = $start->copy()->addDays(365);
                    $note = 'Custom range limited to one year — showing '.$start->format('d M Y').' to '.$end->format('d M Y').'.';
                }
                $label = $start->equalTo($end) ? $start->format('d M Y') : $start->format('d M Y').' – '.$end->format('d M Y');
                break;
            case 'today':
                [$start, $end, $label] = [$today->copy(), $today->copy(), 'Today'];
                break;
            default:
                // No filter applied: each card shows its normal (unfiltered) data — see adminDashboard().
                $preset = 'none';
                [$start, $end, $label] = [$today->copy(), $today->copy(), 'All data'];
        }

        return [
            'preset' => $preset,
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'days' => (int) $start->diffInDays($end) + 1,
            'note' => $note,
            'is_today' => $start->isSameDay($today) && $end->isSameDay($today),
            'applied' => $preset !== 'none',
        ];
    }

    /**
     * Dashboard bucket for one clocked-in attendance row — the same reading as
     * TeamController::persistedDayStatus(): a hand-marked row keeps its marked
     * status, an automatic row its graded effective_status; a row still open
     * (clocked in, no clock-out yet) is present.
     */
    private function dashboardDayBucket($a): string
    {
        if ($a->clock_in && ! $a->clock_out) {
            return 'present';
        }
        $isManual = ($a->attendance_type === 'manual') || ! empty($a->marked_by);
        $status = $isManual ? $a->attendance_status : ($a->effective_status ?: $a->attendance_status);

        return match ($status) {
            'half_day', 'first_half_leave', 'second_half_leave' => 'half_day',
            'absent' => 'absent',
            'on_leave' => 'on_leave',
            'holiday' => 'holiday',
            'weekoff' => 'week_off',
            default => 'present',
        };
    }

    /**
     * Attendance over a period, counted in employee-days. For every day up to
     * today and every active non-admin employee (from their joining date on),
     * exactly one bucket, in this order: present (clocked in) → approved leave
     * → company holiday → their week-off → absent. So the buckets always add
     * up to the employee-days counted, and week-offs / holidays are never
     * shown as absent. Late / early come from the shift-aware minutes stored on
     * the attendance row. Also returns the per-day series for the chart.
     */
    private function adminAttendanceBreakdown(Carbon $start, Carbon $end): array
    {
        $today = Carbon::today();
        $lastDay = $end->copy()->min($today); // no future days
        $from = $start->toDateString();
        $to = $lastDay->toDateString();

        $employees = User::where('users.status', 1)->where('users.role', '!=', 'admin')
            ->leftJoin('user_job_details as j', 'j.user_id', '=', 'users.id')
            ->get(['users.id', 'j.joining_date as joined_on'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => $u->joined_on ? substr((string) $u->joined_on, 0, 10) : null]);
        $employeeIds = $employees->keys();

        $counts = ['present' => 0, 'half_day' => 0, 'on_leave' => 0, 'holiday' => 0, 'week_off' => 0, 'absent' => 0];
        $chart = ['present' => [], 'absent' => [], 'days' => [], 'average' => 0, 'totalPresent' => 0, 'totalAbsent' => 0];
        $holidayName = null;

        if ($start->lte($lastDay)) {
            // The day's graded status — the same one the Team page, reports and
            // payroll use (Day Classification, late / early allowance, hand
            // marking), not just "clocked in".
            $dayStatus = Attendance::whereIn('user_id', $employeeIds)->whereBetween('date', [$from, $to])->whereNotNull('clock_in')
                ->get(['user_id', 'date', 'clock_in', 'clock_out', 'attendance_status', 'effective_status', 'attendance_type', 'marked_by'])
                ->mapWithKeys(fn ($a) => [$a->user_id.'|'.substr((string) $a->date, 0, 10) => $this->dashboardDayBucket($a)]);

            $leaves = Leave::where('status', 'approved')->whereIn('user_id', $employeeIds)
                ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
                ->get(['user_id', 'start_date', 'end_date'])->groupBy('user_id');

            $holidays = Holiday::where('status', 1)->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
                ->get(['name', 'start_date', 'end_date']);

            $weekoffs = UserWeekoffs::where('status', 1)->whereIn('user_id', $employeeIds)->get()->groupBy('user_id');

            $labelFormat = $start->diffInDays($lastDay) < 7 ? 'D' : 'd M';
            foreach (\Carbon\CarbonPeriod::create($start, $lastDay) as $day) {
                $d = $day->toDateString();
                $holiday = $holidays->first(fn ($h) => substr((string) $h->start_date, 0, 10) <= $d && substr((string) $h->end_date, 0, 10) >= $d);
                $dayPresent = $dayAbsent = 0;

                foreach ($employees as $id => $joinedOn) {
                    if ($joinedOn && $joinedOn > $d) {
                        continue; // not employed yet that day
                    }
                    $bucket = $dayStatus[$id.'|'.$d] ?? null;
                    if ($bucket === 'present' || $bucket === 'half_day') {
                        $counts[$bucket]++;
                        $dayPresent++;
                    } elseif ($bucket === 'absent') {
                        $counts['absent']++;
                        $dayAbsent++;
                    } elseif ($bucket === 'on_leave') {
                        $counts['on_leave']++;
                    } elseif ($bucket === 'holiday' || $bucket === 'week_off') {
                        $counts[$bucket]++;
                    } elseif (($l = $leaves->get($id)) && $l->contains(fn ($x) => substr((string) $x->start_date, 0, 10) <= $d && substr((string) $x->end_date, 0, 10) >= $d)) {
                        $counts['on_leave']++;
                    } elseif ($holiday) {
                        $counts['holiday']++;
                    } elseif (($w = $weekoffs->get($id)) && \App\Support\WeekOffPredicate::isWeekOff($w, $day)) {
                        $counts['week_off']++;
                    } else {
                        $counts['absent']++;
                        $dayAbsent++;
                    }
                }

                $chart['days'][] = $day->format($labelFormat);
                $chart['present'][] = $dayPresent;
                $chart['absent'][] = $dayAbsent;
            }

            if ($start->equalTo($lastDay) && $holidays->isNotEmpty()) {
                $holidayName = $holidays->first()->name;
            }
        }

        // Long periods: one bar per month instead of hundreds of daily bars.
        if (count($chart['days']) > 62) {
            $monthly = [];
            foreach (\Carbon\CarbonPeriod::create($start, $lastDay) as $i => $day) {
                $key = $day->format('M Y');
                $monthly[$key]['present'] = ($monthly[$key]['present'] ?? 0) + $chart['present'][$i];
                $monthly[$key]['absent'] = ($monthly[$key]['absent'] ?? 0) + $chart['absent'][$i];
            }
            $chart['days'] = array_keys($monthly);
            $chart['present'] = array_column($monthly, 'present');
            $chart['absent'] = array_column($monthly, 'absent');
        }

        $chart['totalPresent'] = $counts['present'];
        $chart['totalAbsent'] = $counts['absent'];
        // A half day counts as half a present day in the rate.
        $workingDays = $counts['present'] + $counts['half_day'] + $counts['on_leave'] + $counts['absent'];
        $rate = $workingDays > 0 ? (int) round(($counts['present'] + 0.5 * $counts['half_day']) / $workingDays * 100) : 0;
        $chart['average'] = $rate;

        $inRange = fn () => Attendance::join('users', 'users.id', '=', 'attendances.user_id')
            ->where('users.status', 1)->where('users.role', '!=', 'admin')
            ->whereBetween('attendances.date', [$from, $to]);

        return $counts + [
            'total' => $employeeIds->count(),
            'employee_days' => array_sum($counts),
            'rate' => $rate,
            'holiday_name' => $holidayName,
            'chart' => $chart,
            'late_count' => $inRange()->where('attendances.late_minutes', '>', 0)->count(),
            // most late days in the period first (for one day: the latest arrivals)
            'late_list' => $inRange()->where('attendances.late_minutes', '>', 0)
                ->groupBy('users.id', 'users.name', 'users.employee_id')
                ->selectRaw('users.id, users.name, users.employee_id, COUNT(*) as late_days, SUM(attendances.late_minutes) as late_minutes')
                ->orderByDesc('late_days')->orderByDesc('late_minutes')->limit(5)->get(),
            'early_count' => $inRange()->whereNotNull('attendances.clock_out')->where('attendances.early_departure_minutes', '>', 0)->count(),
            // clocked in but never out, on days already over (today is still open)
            'missed_clock_out' => Attendance::join('users', 'users.id', '=', 'attendances.user_id')
                ->where('users.status', 1)->where('users.role', '!=', 'admin')
                ->whereBetween('attendances.date', [
                    $start->isSameDay($today) && $end->isSameDay($today) ? $today->copy()->subDay()->toDateString() : $from,
                    $start->isSameDay($today) && $end->isSameDay($today) ? $today->copy()->subDay()->toDateString() : min($to, $today->copy()->subDay()->toDateString()),
                ])
                ->whereNotNull('attendances.clock_in')->whereNull('attendances.clock_out')->count(),
        ];
    }
}
