@extends('client.layout.master')

@section('style')
    <style>
        /* Design tokens — mirrors the Admin Dashboard's all-blue palette exactly,
           so both dashboards share one typographic/spacing scale. */
        :root {
            --d-bg: #f4f6fb;
            --d-card: #ffffff;
            --d-border: #eaeef5;
            --d-border-strong: #dfe5f0;
            --d-text: #1a2236;
            --d-text-soft: #6b7385;
            --d-text-muted: #9aa1b1;
            --d-radius: 14px;
            --d-shadow: 0 1px 2px rgba(20, 30, 60, .04), 0 2px 8px rgba(20, 30, 60, .04);
            --d-shadow-hover: 0 6px 22px rgba(30, 50, 110, .10);
        }

        th { font-size: 10.5px !important; font-weight: 600 !important; letter-spacing: .2px; text-transform: uppercase; color: var(--d-text-muted) !important; }
        td { font-size: 12px !important; color: var(--d-text); }

        .card {
            border: 1px solid var(--d-border);
            border-radius: var(--d-radius);
            box-shadow: var(--d-shadow);
        }

        .card .card-header {
            padding: 8px 12px;
            border-bottom: 1px solid var(--d-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card .card-body {
            padding: 10px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--d-text);
            letter-spacing: -.2px;
        }

        .row.g-compact {
            --bs-gutter-x: 10px;
            --bs-gutter-y: 10px;
        }

        /* Single blue theme lock — every semantic Bootstrap/status color on this
           page resolves to a blue tint instead of green/red/amber, same trick the
           Admin Dashboard uses on its own bg-soft-*/text-*/pill-* classes. */
        .bg-soft-primary, .bg-soft-success, .bg-soft-danger, .bg-soft-warning,
        .bg-soft-info, .bg-soft-purple, .bg-soft-dark {
            background: #e3edfe !important;
            color: #1e3a8a !important;
        }
        .text-primary { color: var(--primary) !important; }
        .text-success { color: #1d4ed8 !important; }
        .text-danger  { color: #1e3a8a !important; }
        .text-warning { color: #2563eb !important; }
        .text-info    { color: #0ea5e9 !important; }

        .status-badge[data-status] {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
        }

        .pill { padding: 1px 8px; border-radius: 999px; font-size: 9.5px; font-weight: 700; letter-spacing: .2px; white-space: nowrap; }
        .pill-success { background: rgba(59, 130, 246, .14); color: #1d4ed8; }
        .pill-danger  { background: rgba(30, 58, 138, .14); color: #1e3a8a; }
        .pill-warning { background: rgba(96, 165, 250, .18); color: #2563eb; }
        .pill-info    { background: rgba(14, 165, 233, .12); color: #0c87c4; }

        /* Welcome banner — same anatomy as the Admin Dashboard's .welcome-banner */
        .welcome-banner {
            background: linear-gradient(120deg, #1e3a8a 0%, #1d4ed8 55%, #2563eb 100%);
            border-radius: var(--d-radius);
            position: relative;
            overflow: hidden;
            border: none;
            /* box-shadow: 0 8px 24px rgba(30, 58, 138, .22); */
            margin-bottom: 10px !important;
        }
        /* .welcome-banner::after {
            content: '';
            position: absolute; right: -40px; top: -60px;
            width: 240px; height: 240px; border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }
        .welcome-banner::before {
            content: '';
            position: absolute; right: 90px; bottom: -90px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255, 255, 255, .06);
        } */
        .welcome-banner .card-body { padding: 14px 18px; position: relative; z-index: 2; border: none; }

        .dash-icon-circle {
            width: 38px; height: 38px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            background: var(--primary-light); color: var(--primary); flex-shrink: 0;
        }

        .dash-list-item {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 7px 0; border-bottom: 1px solid #f1f2f4; font-size: 12px;
        }
        .dash-list-item:last-child { border-bottom: none; }

        .dash-list-title { font-weight: 600; font-size: 12.5px; color: var(--d-text); margin-bottom: 1px; }
        .dash-list-sub { font-size: 11px; color: var(--d-text-soft); }

        .legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }

        .task-check-item {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
            padding: 7px 0; border-bottom: 1px solid #f1f2f4; font-size: 12px;
        }
        .task-check-item:last-child { border-bottom: none; }

        .qa-block {
            display: flex; align-items: center; justify-content: space-between; text-decoration: none; color: var(--d-text);
            border: 1px solid var(--d-border); border-radius: 10px; padding: 8px; height: 100%;
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .qa-block:hover { color: var(--primary); transform: translateY(-2px); box-shadow: var(--d-shadow-hover); border-color: var(--d-border-strong); }
        .qa-block .qa-icon {
            width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 13px;
            background: var(--primary-light); color: var(--primary);
        }
        .qa-block .qa-label { font-size: 11.5px; font-weight: 600; }

        .today-status-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid #f1f2f4; font-size: 12px;
        }
        .today-status-row:last-child { border-bottom: none; }

        #dashNotificationList .dash-list-item,
        #dashActivityList .dash-list-item {
            cursor: default;
        }

        /* Task Overview — funnel-style progress bars, same anatomy as the
           Admin Dashboard's Task/Project Overview cards. */
        .view-toggle-btn {
            font-size: 10px; font-weight: 600; color: var(--primary-mid);
            cursor: pointer; white-space: nowrap; user-select: none;
        }
        .view-toggle-btn:hover { text-decoration: underline; }

        .funnel-list { display: flex; flex-direction: column; gap: 8px; }
        .funnel-row { display: flex; align-items: center; gap: 8px; }
        .funnel-label { flex: 0 0 5.6rem; font-size: 11px; font-weight: 600; color: var(--d-text-soft); }
        .funnel-track { flex: 1; height: 8px; border-radius: 999px; background: #e3edfe; overflow: hidden; }
        .funnel-fill { background: #60a5fa; height: 100%; border-radius: 999px; transition: width .3s ease; }
        .funnel-count { flex: 0 0 1.8rem; text-align: right; font-size: 11px; font-weight: 700; color: var(--primary); }

        .mini-table { width: 100%; font-size: 11px; border-collapse: collapse; }
        .mini-table td { padding: 4px 2px; border-bottom: 1px dashed var(--d-border); color: var(--d-text); }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-table td:last-child { text-align: right; font-weight: 700; color: var(--primary); }

        /* Attendance Calendar — same anatomy as the Admin Dashboard's calendar,
           extended with a per-day P/A/H/L/W status badge. */
        .cal-nav { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 600; color: var(--d-text-soft); }
        .cal-nav-btn {
            cursor: pointer; width: 18px; height: 18px; border-radius: 5px; flex: none;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .cal-nav-btn:hover { background: #e3edfe; color: var(--primary); }
        .cal-nav-label { min-width: 62px; text-align: center; white-space: nowrap; }

        .cal-card-body { display: flex; flex-direction: column; padding: 10px; }
        .cal-weekdays {
            display: grid; grid-template-columns: repeat(7, 1fr); text-align: center;
            font-size: 9.5px; font-weight: 700; color: var(--d-text-muted); margin-bottom: 6px; flex: none;
        }
        .cal-grid {
            display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: 1fr;
            gap: 4px; flex: 1; min-height: 170px;
        }
        .cal-day {
            position: relative; display: flex; align-items: center; justify-content: center;
            font-size: 11.5px; font-weight: 600; border-radius: 8px;
            color: var(--d-text); background: #f2f6fd; padding: 4px;
        }
        .cal-day-empty { background: transparent; }
        .cal-day-today { background: var(--primary); color: #fff; font-weight: 800; }

        .cal-day-present { background: #bfdbfe; color: #1e3a8a; }
        .cal-day-absent { background: #eef1f7; color: var(--d-text-muted); }
        .cal-day-holiday { background: #dbeafe; color: var(--primary); border: 1px dashed var(--primary-mid); }
        .cal-day-leave { background: #dbeafe; color: var(--primary-mid); border: 1px dashed #93c5fd; }
        .cal-day-weekoff { background: #f2f6fd; color: var(--d-text-muted); opacity: .75; }
        .cal-day-today.cal-day-present, .cal-day-today.cal-day-holiday,
        .cal-day-today.cal-day-leave, .cal-day-today.cal-day-weekoff {
            background: var(--primary); color: #fff; border-color: #fff;
        }

        .cal-day-badge {
            position: absolute; top: 1px; right: 2px; font-size: 7.5px; font-weight: 800;
            line-height: 1; color: inherit;
        }

        .cal-legend {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; padding-top: 8px;
            border-top: 1px solid var(--d-border); font-size: 9.5px; color: var(--d-text-soft);
        }
        .cal-legend span { display: inline-flex; align-items: center; gap: 3px; }
        .cal-legend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 3px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Dashboard" />

    <div class="main-content" style="padding: 16px !important;">

        <!-- Welcome Banner -->
        <div class="row g-compact mb-2">
            <div class="col-12">
                <div class="card welcome-banner text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <img src="{{ $profile_image ?? asset('assets/images/avatar/default.png') }}" alt=""
                                    class="rounded-circle" width="52" height="52"
                                    style="object-fit:cover;border:2px solid rgba(255,255,255,.4);">
                                <div>
                                    <h5 class="text-white mb-1 fw-bold">Welcome back, {{ $user->name }}!</h5>
                                    <p class="text-white-50 mb-1 fs-12">Keep going, your hard work makes a difference.</p>
                                    <p class="text-white-50 mb-0 fs-11">
                                        {{ $designation }}
                                        @if ($department && $department !== 'N/A')
                                            &nbsp;|&nbsp; {{ $department }}
                                        @endif
                                        @if ($reporting_head_name)
                                            &nbsp;|&nbsp; Reporting To: {{ $reporting_head_name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="text-end">
                                <p class="text-white-50 mb-1 fs-12"><i class="feather-calendar me-1"></i>{{ now()->format('l, d F Y') }}</p>
                                <p class="text-white-50 mb-0 fs-12"><i class="feather-clock me-1"></i>{{ now()->format('h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row row-cols-1 row-cols-md-3 row-cols-xxl-5 g-compact mb-2">
            <div class="col">
                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="feather-calendar"></i></span>
                        <span class="pill pill-success">{{ $today_status }}</span>
                    </div>
                    <div class="kpi5-value" style="font-size:15px;">
                        {{ $today_attendance && $today_attendance->clock_in ? \Carbon\Carbon::parse($today_attendance->clock_in)->format('h:i A') : '--:--' }}
                    </div>
                    <div class="kpi5-label">Attendance Today</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $working_hours_today ?? ($today_attendance->total_hours ?? '0:00') }}</span><span class="l">Hours</span></div>
                        <div class="kpi5-stat"><span class="n">{{ $today_shift->name ?? 'N/A' }}</span><span class="l">Shift</span></div>
                    </div>
                </div>
            </div>

            <div class="col">
                <a href="{{ route('leave.view') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-briefcase"></i></span>
                            <span class="kpi5-pill">Available</span>
                        </div>
                        <div class="kpi5-value">{{ $leave_balance ?? 0 }}</div>
                        <div class="kpi5-label">Leave Balance</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $monthly_attendance['leave_count'] ?? 0 }}</span><span class="l">Taken</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $pending_leave_count ?? 0 }}</span><span class="l">Pending</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                @if ($show_tasks)
                    <a href="{{ route('task.assigned-to-me') }}" class="text-decoration-none">
                @endif
                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="feather-check-square"></i></span>
                        <span class="kpi5-pill">Pending</span>
                    </div>
                    <div class="kpi5-value">{{ $pending_tasks ?? 0 }}</div>
                    <div class="kpi5-label">Pending Tasks</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $completed_tasks_count ?? 0 }}</span><span class="l">Completed</span></div>
                        <div class="kpi5-stat"><span class="n">{{ $total_tasks_assigned ?? 0 }}</span><span class="l">Total</span></div>
                    </div>
                </div>
                @if ($show_tasks)
                    </a>
                @endif
            </div>

            <div class="col">
                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="feather-alert-circle"></i></span>
                        <span class="pill pill-warning">Requires Action</span>
                    </div>
                    <div class="kpi5-value">{{ $pending_approvals_count ?? 0 }}</div>
                    <div class="kpi5-label">Pending Approvals</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $pending_leave_count ?? 0 }}</span><span class="l">Leave</span></div>
                        <div class="kpi5-stat"><span class="n">{{ ($request_counts['pending'] ?? 0) + ($overtime_this_month['pending_count'] ?? 0) }}</span><span class="l">Other</span></div>
                    </div>
                </div>
            </div>

            <div class="col">
                <a href="{{ route('broadcast.notifications.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-bell"></i></span>
                            <span class="kpi5-pill">Live</span>
                        </div>
                        <div class="kpi5-value" id="dashNotifUnreadValue">0</div>
                        <div class="kpi5-label">Unread Notifications</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n" id="dashNotifUnreadFoot">0</span><span class="l">Unread</span></div>
                            <div class="kpi5-stat"><span class="n" id="dashNotifRecentFoot">0</span><span class="l">Recent</span></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Attendance Overview / Today's Status / Recent Leave Applications -->
        <div class="row g-compact mb-2">
            <div class="col-xxl-4 col-md-6">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Attendance Overview</h6>
                        <span class="fs-11 text-muted">This Month</span>
                    </div>
                    <div id="dash-attendance-trend-chart" style="height: 210px;"></div>
                </x-ui.card>
            </div>

            <div class="col-xxl-4 col-md-6">
                <x-ui.card title="Today's Status" class="stretch stretch-full h-100">
                    <div class="mb-2"><span class="pill pill-success">{{ $today_status }}</span></div>
                    <div class="today-status-row">
                        <span class="text-muted"><i class="feather-log-in me-1"></i>Check-in</span>
                        <strong>{{ $today_attendance && $today_attendance->clock_in ? \Carbon\Carbon::parse($today_attendance->clock_in)->format('h:i A') : '--' }}</strong>
                    </div>
                    <div class="today-status-row">
                        <span class="text-muted"><i class="feather-clock me-1"></i>Working Hours</span>
                        <strong>{{ $working_hours_today ?? ($today_attendance->total_hours ?? '0:00') }}</strong>
                    </div>
                    <div class="today-status-row">
                        <span class="text-muted"><i class="feather-alert-circle me-1"></i>Late Check-in</span>
                        <strong>{{ $today_attendance && $today_attendance->clock_in && \Carbon\Carbon::parse($today_attendance->clock_in)->format('H:i') > '10:00' ? 'Yes' : 'No' }}</strong>
                    </div>
                    <div class="today-status-row">
                        <span class="text-muted"><i class="feather-log-out me-1"></i>Early Logout</span>
                        <strong>{{ $today_attendance && $today_attendance->clock_out && \Carbon\Carbon::parse($today_attendance->clock_out)->format('H:i') < '18:00' ? 'Yes' : 'No' }}</strong>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xxl-4 col-md-6">
                <x-ui.card :bodyClass="'p-0'" class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Leave Applications</h6>
                        <a href="{{ route('leave.view') }}" class="fs-11">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Type</th><th>Day</th><th>Status</th><th>Applied</th></tr>
                            </thead>
                            <tbody>
                                @forelse($recent_leaves ?? [] as $leave)
                                    @php
                                        $lvStart = \Carbon\Carbon::parse($leave->start_date);
                                        $lvEnd = \Carbon\Carbon::parse($leave->end_date ?? $leave->start_date);
                                        $lvDays = $lvStart->diffInDays($lvEnd) + 1;
                                    @endphp
                                    <tr>
                                        <td>{{ $leave->leave_type_name ?? 'Leave' }}</td>
                                        <td>{{ $lvDays }} D{{ $lvDays > 1 ? 's' : '' }}</td>
                                        <td><x-ui.status-badge :status="$leave->status" /></td>
                                        <td>{{ \Carbon\Carbon::parse($leave->created_at)->format('d M') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-ui.empty-state icon="calendar" title="No leave applications" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>

        <!-- Quick Links / Task Overview / Monthly Attendance / Upcoming Holidays -->
        <div class="row g-compact mb-2">
            <div class="col-md-3">
                <x-ui.card title="Quick Links" class="stretch stretch-full h-100">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('leave.apply') }}" class="qa-block">
                                <span class="qa-label">Apply Leave</span>
                                <span class="qa-icon"><i class="feather-plus-circle"></i></span>
                            </a>
                        </div>
                        @if ($show_regularization)
                            <div class="col-6">
                                <a href="{{ route('attendance-regularization.index') }}" class="qa-block">
                                    <span class="qa-label">Regularization</span>
                                    <span class="qa-icon"><i class="feather-edit-3"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_attendance)
                            <div class="col-6">
                                <a href="{{ route('attendance.index') }}" class="qa-block">
                                    <span class="qa-label">Attendance</span>
                                    <span class="qa-icon"><i class="feather-calendar"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_tasks)
                            <div class="col-6">
                                <a href="{{ route('task.assigned-to-me') }}" class="qa-block">
                                    <span class="qa-label">My Tasks</span>
                                    <span class="qa-icon"><i class="feather-check-square"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_payroll && $latest_payslip)
                            <div class="col-6">
                                <a href="{{ route('monthly-payrolls.payslip', $latest_payslip->id) }}" class="qa-block">
                                    <span class="qa-label">Payslip</span>
                                    <span class="qa-icon"><i class="feather-dollar-sign"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_wfh_travel)
                            <div class="col-6">
                                <a href="{{ route('requests.index') }}" class="qa-block">
                                    <span class="qa-label">WFH / Travel</span>
                                    <span class="qa-icon"><i class="feather-send"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_expenses)
                            <div class="col-6">
                                <a href="{{ route('expense.view-all') }}" class="qa-block">
                                    <span class="qa-label">Expenses</span>
                                    <span class="qa-icon"><i class="feather-credit-card"></i></span>
                                </a>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            <div class="col-md-3">
                <div class="card stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Task Overview</h6>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'task-overview-bars', 'task-overview-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        @php
                            $taskFunnel = [
                                ['label' => 'Total', 'val' => $total_tasks_assigned ?? 0],
                                ['label' => 'Pending', 'val' => $task_status_counts['pending'] ?? 0],
                                ['label' => 'In Progress', 'val' => $task_status_counts['in_progress'] ?? 0],
                                ['label' => 'Completed', 'val' => $task_status_counts['completed'] ?? 0],
                            ];
                        @endphp
                        <div id="task-overview-bars" class="funnel-list">
                            @foreach ($taskFunnel as $t)
                                <div class="funnel-row">
                                    <div class="funnel-label">{{ $t['label'] }}</div>
                                    <div class="funnel-track">
                                        <div class="funnel-fill" style="width: {{ ($total_tasks_assigned ?? 0) > 0 ? round(($t['val'] / $total_tasks_assigned) * 100) : 0 }}%"></div>
                                    </div>
                                    <div class="funnel-count">{{ $t['val'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <table class="mini-table" id="task-overview-table-view" style="display:none;">
                            <tbody>
                                @foreach ($taskFunnel as $t)
                                    <tr><td>{{ $t['label'] }}</td><td>{{ $t['val'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Monthly Attendance</h6>
                        <div class="cal-nav">
                            <span class="cal-nav-btn" onclick="dashCalChangeMonth(-1)">&#8249;</span>
                            <span class="cal-nav-label" id="dash-cal-month-label"></span>
                            <span class="cal-nav-btn" onclick="dashCalChangeMonth(1)">&#8250;</span>
                        </div>
                    </div>
                    <div class="card-body cal-card-body">
                        <div class="cal-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                        <div class="cal-grid" id="dash-cal-grid"></div>
                        <div class="cal-legend">
                            <span><i class="cal-legend-dot cal-day-present"></i>P Present</span>
                            <span><i class="cal-legend-dot cal-day-absent"></i>A Absent</span>
                            <span><i class="cal-legend-dot cal-day-holiday"></i>H Holiday</span>
                            <span><i class="cal-legend-dot cal-day-leave"></i>L Leave</span>
                            <span><i class="cal-legend-dot cal-day-weekoff"></i>W Week Off</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Upcoming Holidays</h6>
                        @if ($show_holidays)
                            <a href="{{ route('holiday.index') }}" class="fs-11">View All</a>
                        @endif
                    </div>
                    @forelse($upcoming_holidays ?? [] as $holiday)
                        <div class="dash-list-item">
                            <div class="d-flex align-items-center gap-2">
                                <div class="dash-icon-circle"><i class="feather-gift"></i></div>
                                <div>
                                    <span class="d-block dash-list-title">{{ $holiday->name }}</span>
                                    <span class="dash-list-sub">{{ \Carbon\Carbon::parse($holiday->start_date)->format('l, d M Y') }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state icon="gift" title="No upcoming holidays" />
                    @endforelse
                </x-ui.card>
            </div>
        </div>

        <!-- WFH / Travel / Overtime / Upcoming Meetings / Recent Regularization -->
        <div class="row g-compact mb-2">
            @if ($show_wfh_travel)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">WFH / Travel</h6>
                            <a href="{{ route('requests.index') }}" class="fs-11">View All</a>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <x-ui.stat-card icon="home" label="WFH" value="{{ $wfh_days_this_month }} days" />
                            </div>
                            <div class="col-6">
                                <x-ui.stat-card icon="map-pin" label="Travel" value="{{ $travel_days_this_month }} days" />
                            </div>
                        </div>
                        @forelse($my_requests ?? [] as $reqRow)
                            <div class="today-status-row">
                                <span class="text-muted">{{ \Carbon\Carbon::parse($reqRow->start_date)->format('d M') }}</span>
                                <span>{{ $reqRow->requestType->type_name ?? 'Request' }}</span>
                                <x-ui.status-badge :status="$reqRow->status" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="send" title="No WFH / Travel requests" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            @if ($show_overtime)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Overtime</h6>
                            <a href="{{ route('overtime.index') }}" class="fs-11">View All</a>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <x-ui.stat-card icon="clock" label="Approved" value="{{ $overtime_this_month['approved_hours'] ?? 0 }} hrs" />
                            </div>
                            <div class="col-6">
                                <x-ui.stat-card icon="alert-circle" label="Pending" value="{{ $overtime_this_month['pending_hours'] ?? 0 }} hrs" />
                            </div>
                        </div>
                        @forelse($recent_overtime ?? [] as $ot)
                            <div class="today-status-row">
                                <span class="text-muted">{{ \Carbon\Carbon::parse($ot->date)->format('d M') }}</span>
                                <span>{{ $ot->overtime_hours }} hrs</span>
                                <x-ui.status-badge :status="$ot->status" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="clock" title="No overtime requests" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            @if ($show_meetings)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Upcoming Meetings</h6>
                            <a href="{{ route('meetings.index') }}" class="fs-11">View All</a>
                        </div>
                        @forelse($upcoming_meetings ?? [] as $participant)
                            @php $meeting = $participant->meeting; @endphp
                            @if ($meeting)
                                <div class="dash-list-item">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="dash-icon-circle"><i class="feather-video"></i></div>
                                        <div>
                                            <span class="d-block dash-list-title">{{ Str::limit($meeting->title, 26) }}</span>
                                            <span class="dash-list-sub">
                                                {{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M') }}
                                                @if ($meeting->start_time)
                                                    , {{ \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <x-ui.empty-state icon="video" title="No upcoming meetings" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            @if ($show_regularization)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Recent Regularization</h6>
                            <a href="{{ route('attendance-regularization.index') }}" class="fs-11">View All</a>
                        </div>
                        @forelse($recent_regularizations ?? [] as $reg)
                            <div class="dash-list-item">
                                <div>
                                    <span class="d-block dash-list-title">{{ ucwords(str_replace('_', ' ', $reg->request_type)) }}</span>
                                    <span class="dash-list-sub">{{ \Carbon\Carbon::parse($reg->date)->format('d M Y') }}</span>
                                </div>
                                <x-ui.status-badge :status="$reg->status" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="edit-3" title="No regularization requests" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

        </div>

        <!-- Expenses / Recent Announcements / Performance -->
        <div class="row g-compact mb-2">
            @if ($show_expenses)
                <div class="col-xxl-4 col-md-6">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Expenses</h6>
                            <a href="{{ route('expense.view-all') }}" class="fs-11">View All</a>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <x-ui.stat-card icon="wallet" label="Balance" value="₹{{ number_format($expense_balance->current_balance ?? 0, 0) }}" />
                            </div>
                            <div class="col-6">
                                <x-ui.stat-card icon="alert-circle" label="Pending" value="{{ $pending_expense_count ?? 0 }}" />
                            </div>
                        </div>
                        @forelse($recent_expenses ?? [] as $exp)
                            <div class="today-status-row">
                                <span class="text-muted">{{ \Carbon\Carbon::parse($exp->date)->format('d M') }}</span>
                                <span>₹{{ number_format($exp->amount, 0) }}</span>
                                <x-ui.status-badge :status="$exp->status" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="credit-card" title="No expense claims" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            <div class="col-xxl-4 col-md-6">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Announcements</h6>
                        @if ($show_announcements)
                            <a href="{{ route('announcement.all') }}" class="fs-11">View All</a>
                        @endif
                    </div>
                    @forelse($recent_announcements ?? [] as $announcement)
                        <div class="dash-list-item" style="align-items:flex-start;">
                            <div>
                                <span class="d-block dash-list-title">{{ $announcement->title }}</span>
                                <span class="dash-list-sub d-block mb-1">{{ Str::limit($announcement->description, 80) }}</span>
                                <span class="dash-list-sub">{{ $announcement->user_name ?? 'System' }} • {{ \Carbon\Carbon::parse($announcement->created_at)->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state icon="megaphone" title="No announcements" />
                    @endforelse
                </x-ui.card>
            </div>

            @if ($show_performance)
                <div class="col-xxl-4 col-md-6">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Performance</h6>
                            <a href="{{ route('performance.my-dashboard') }}" class="fs-11">View Details</a>
                        </div>
                        @if ($kpi_score)
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <x-ui.stat-card icon="trending-up" label="Overall Score" value="{{ number_format($kpi_score->overall_score, 0) }}%" />
                                </div>
                                <div class="col-6">
                                    <x-ui.stat-card icon="award" label="Grade" value="{{ $kpi_score->grade ?? 'N/A' }}" />
                                </div>
                            </div>
                            <div class="today-status-row">
                                <span class="text-muted">Attendance</span>
                                <strong>{{ $kpi_score->attendance_score !== null ? number_format($kpi_score->attendance_score, 0) . '%' : 'N/A' }}</strong>
                            </div>
                            <div class="today-status-row">
                                <span class="text-muted">Task Completion</span>
                                <strong>{{ $kpi_score->task_completion_score !== null ? number_format($kpi_score->task_completion_score, 0) . '%' : 'N/A' }}</strong>
                            </div>
                        @else
                            <x-ui.empty-state icon="trending-up" title="No performance data yet" />
                        @endif
                    </x-ui.card>
                </div>
            @endif
        </div>

    </div>
@endsection

@section('script-area')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            const list = document.getElementById('dashNotificationList');
            const recentFoot = document.getElementById('dashNotifRecentFoot');

            fetch("{{ route('broadcast.notifications.index') }}?per_page=5", { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(res => {
                    const items = (res.data && res.data.data) ? res.data.data : [];
                    if (recentFoot) recentFoot.textContent = items.length;
                })
                .catch(() => {});
        })();

        (function () {
            const value = document.getElementById('dashNotifUnreadValue');
            const foot = document.getElementById('dashNotifUnreadFoot');
            if (!value) return;

            fetch("{{ route('broadcast.notifications.unread-count') }}", { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const count = data.unread_count ?? 0;
                    value.textContent = count;
                    if (foot) foot.textContent = count;
                })
                .catch(() => {});
        })();

        (function () {
            const colorPalette = ['#1e3a8a', '#1d4ed8', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#0ea5e9'];

            // Attendance Overview — cumulative Present/Absent/Leave line chart (this month)
            var trendEl = document.getElementById('dash-attendance-trend-chart');
            if (trendEl) {
                var trendLabels = @json($attendance_trend_labels ?? []);
                var trendPresent = @json($attendance_trend_present ?? []);
                var trendAbsent = @json($attendance_trend_absent ?? []);
                var trendLeave = @json($attendance_trend_leave ?? []);
                try {
                    new ApexCharts(trendEl, {
                        series: [
                            { name: 'Present', data: trendPresent },
                            { name: 'Absent', data: trendAbsent },
                            { name: 'Leave', data: trendLeave }
                        ],
                        chart: { type: 'line', height: 210, toolbar: { show: false }, fontFamily: 'inherit' },
                        colors: ['#1e3a8a', '#93c5fd', '#0ea5e9'],
                        stroke: { curve: 'smooth', width: 2 },
                        markers: { size: 2 },
                        dataLabels: { enabled: false },
                        xaxis: {
                            categories: trendLabels,
                            tickAmount: 6,
                            labels: { style: { fontSize: '10.5px' } },
                            axisBorder: { show: false },
                            axisTicks: { show: false }
                        },
                        yaxis: { labels: { style: { fontSize: '10.5px' } } },
                        legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', markers: { radius: 4 } },
                        grid: { borderColor: '#eef1f7', strokeDashArray: 4 },
                        tooltip: { shared: true }
                    }).render();
                } catch (e) { console.error(e); }
            }

        })();

        // Task Overview — "View as table" toggle (same helper as the Admin Dashboard)
        function toggleChartTable(btn, chartId, tableId) {
            var chartEl = document.getElementById(chartId);
            var tableEl = document.getElementById(tableId);
            if (!chartEl || !tableEl) return;
            var showingTable = tableEl.style.display !== 'none';
            chartEl.style.display = showingTable ? '' : 'none';
            tableEl.style.display = showingTable ? 'none' : 'table';
            btn.textContent = showingTable ? 'View as table' : 'View as chart';
        }

        // Attendance Calendar — per-day P/A/H/L/W status badge, same nav anatomy as
        // the Admin Dashboard's Attendance Calendar (prev/current/next month shipped
        // from the server; navigating further shows plain, un-coded day cells).
        (function () {
            var calDate = new Date();
            var calStatusMap = @json($calendar_status_map ?? []);
            var calMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            function dashCalRenderInner() {
                var year = calDate.getFullYear();
                var month = calDate.getMonth();
                var monthLabel = document.getElementById('dash-cal-month-label');
                if (monthLabel) monthLabel.textContent = calMonthNames[month].slice(0, 3) + ' ' + year;

                var grid = document.getElementById('dash-cal-grid');
                if (!grid) return;
                grid.innerHTML = '';

                var firstDay = new Date(year, month, 1).getDay();
                var daysInMonth = new Date(year, month + 1, 0).getDate();
                var todayStr = new Date().toDateString();

                for (var i = 0; i < firstDay; i++) {
                    var blank = document.createElement('span');
                    blank.className = 'cal-day cal-day-empty';
                    grid.appendChild(blank);
                }
                for (var d = 1; d <= daysInMonth; d++) {
                    var mm = String(month + 1).padStart(2, '0');
                    var dd = String(d).padStart(2, '0');
                    var dateKey = year + '-' + mm + '-' + dd;
                    var status = calStatusMap[dateKey];

                    var cell = document.createElement('span');
                    cell.className = 'cal-day';
                    cell.textContent = d;

                    if (new Date(year, month, d).toDateString() === todayStr) {
                        cell.classList.add('cal-day-today');
                    }
                    if (status) {
                        var codeClass = { P: 'cal-day-present', A: 'cal-day-absent', H: 'cal-day-holiday', L: 'cal-day-leave', W: 'cal-day-weekoff' }[status.code];
                        if (codeClass) cell.classList.add(codeClass);
                        cell.title = status.title || '';
                        var badge = document.createElement('sup');
                        badge.className = 'cal-day-badge';
                        badge.textContent = status.code;
                        cell.appendChild(badge);
                    }

                    grid.appendChild(cell);
                }
            }

            window.dashCalChangeMonth = function (offset) {
                calDate.setMonth(calDate.getMonth() + offset);
                dashCalRenderInner();
            };

            dashCalRenderInner();
        })();
    </script>
@endsection
