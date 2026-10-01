@extends('client.layout.master')

@section('style')
    <style>
        /* Design tokens — same anatomy as the Employee/Admin dashboards, so
           all three dashboards share one typographic/spacing/color scale. */
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

        .card .card-body { padding: 10px; }

        .card-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--d-text);
            letter-spacing: -.2px;
        }

        .row.g-compact { --bs-gutter-x: 10px; --bs-gutter-y: 10px; }

        /* Single blue theme lock */
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
        .badge.bg-success, .badge.bg-danger, .badge.bg-warning, .badge.bg-info { background: #e3edfe !important; color: #1e3a8a !important; }

        .status-badge[data-status] { background: var(--primary-light) !important; color: var(--primary) !important; }

        .pill { padding: 1px 8px; border-radius: 999px; font-size: 9.5px; font-weight: 700; letter-spacing: .2px; white-space: nowrap; }
        .pill-success { background: rgba(59, 130, 246, .14); color: #1d4ed8; }
        .pill-danger  { background: rgba(30, 58, 138, .14); color: #1e3a8a; }
        .pill-warning { background: rgba(96, 165, 250, .18); color: #2563eb; }
        .pill-info    { background: rgba(14, 165, 233, .12); color: #0c87c4; }

        /* Employee avatar chip used across the team/leave/task tables */
        .employee-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 12px; color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-mid));
            flex-shrink: 0;
        }
        .employee-info { display: flex; align-items: center; gap: 10px; }
        .employee-details { line-height: 1.4; }
        .employee-name { font-weight: 600; color: var(--d-text); font-size: 12.5px; }
        .employee-email { font-size: 10.5px; color: var(--d-text-soft); }

        /* Welcome banner — same anatomy as the Employee/Admin dashboards */
        .welcome-banner {
            background: linear-gradient(120deg, #1e3a8a 0%, #1d4ed8 55%, #2563eb 100%);
            border-radius: var(--d-radius);
            position: relative;
            overflow: hidden;
            border: none;
            margin-bottom: 10px !important;
        }
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

        .today-status-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 6px 0; border-bottom: 1px solid #f1f2f4; font-size: 12px;
        }
        .today-status-row:last-child { border-bottom: none; }

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

        /* Task Overview — funnel-style progress bars */
        .view-toggle-btn { font-size: 10px; font-weight: 600; color: var(--primary-mid); cursor: pointer; white-space: nowrap; user-select: none; }
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

        /* Pending Approvals breakdown */
        .approval-row {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 8px 0; border-bottom: 1px solid #f1f2f4; text-decoration: none; color: var(--d-text);
        }
        .approval-row:last-child { border-bottom: none; }
        .approval-row:hover .approval-count { background: var(--primary); color: #fff; }
        .approval-row-left { display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; }
        .approval-count {
            min-width: 24px; height: 22px; padding: 0 7px; border-radius: 20px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; background: var(--primary-light); color: var(--primary);
            transition: all .15s ease;
        }

        .table-responsive .table { margin-bottom: 0; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Manager Dashboard" />

    <div class="main-content" style="padding: 16px !important;">

        <!-- Welcome Banner -->
        <div class="row g-compact mb-2">
            <div class="col-12">
                <div class="card welcome-banner text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h5 class="text-white mb-1 fw-bold">Welcome back, {{ $user->name }}!</h5>
                                <p class="text-white-50 mb-0 fs-12">You're managing {{ $team_size }} team member{{ $team_size == 1 ? '' : 's' }} today.</p>
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
                <a href="{{ route('team.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-users"></i></span>
                            <span class="kpi5-pill">Team</span>
                        </div>
                        <div class="kpi5-value">{{ $team_size }}</div>
                        <div class="kpi5-label">Team Size</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $team_present }}</span><span class="l">Present</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $team_on_leave }}</span><span class="l">On Leave</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('team.index', ['status' => 'present']) }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-check-circle"></i></span>
                            <span class="pill pill-success">Today</span>
                        </div>
                        <div class="kpi5-value">{{ $team_present }}</div>
                        <div class="kpi5-label">Present Today</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $team_on_leave }}</span><span class="l">On Leave</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $team_absent }}</span><span class="l">Absent</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                @php
                    $teamTaskPct = $total_team_tasks > 0 ? round((($team_task_status_counts['completed'] ?? 0) / $total_team_tasks) * 100) : 0;
                @endphp
                <a href="{{ route('task.assigned-by-me') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-info"><i class="feather-list"></i></span>
                            <span class="kpi5-pill">{{ $teamTaskPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_team_tasks }}</div>
                        <div class="kpi5-label">Team Tasks</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $team_task_status_counts['pending'] ?? 0 }}</span><span class="l">Pending</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $team_task_status_counts['completed'] ?? 0 }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('leave.view-all', ['status' => 'pending']) }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-alert-circle"></i></span>
                            <span class="pill pill-warning">Requires Action</span>
                        </div>
                        <div class="kpi5-value">{{ $total_pending_approvals }}</div>
                        <div class="kpi5-label">Pending Approvals</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $pending_leave_requests }}</span><span class="l">Leave</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $total_pending_approvals - $pending_leave_requests }}</span><span class="l">Other</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                @php
                    $teamProjPct = $total_projects > 0 ? round(($completed_projects / $total_projects) * 100) : 0;
                @endphp
                <a href="{{ route('project.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-purple"><i class="feather-briefcase"></i></span>
                            <span class="kpi5-pill">{{ $teamProjPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_projects }}</div>
                        <div class="kpi5-label">Total Projects</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $ongoing_projects }}</span><span class="l">Ongoing</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $completed_projects }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Team Attendance Overview / Pending Approvals / Recent Team Leaves -->
        <div class="row g-compact mb-2">
            <div class="col-xxl-4 col-md-6">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Team Attendance Overview</h6>
                        <span class="fs-11 text-muted">This Month</span>
                    </div>
                    <div id="dash-attendance-trend-chart" style="height: 210px;"></div>
                </x-ui.card>
            </div>

            <div class="col-xxl-4 col-md-6">
                <x-ui.card title="Pending Approvals" class="stretch stretch-full h-100">
                    <a href="{{ route('leave.view-all', ['status' => 'pending']) }}" class="approval-row">
                        <span class="approval-row-left"><i class="feather-calendar text-muted"></i> Leave Requests</span>
                        <span class="approval-count">{{ $pending_leave_requests }}</span>
                    </a>
                    @if ($show_regularization)
                        <a href="{{ route('attendance-regularization.manage', ['status' => 'pending']) }}" class="approval-row">
                            <span class="approval-row-left"><i class="feather-edit-3 text-muted"></i> Regularizations</span>
                            <span class="approval-count">{{ $pending_regularizations }}</span>
                        </a>
                    @endif
                    @if ($show_overtime)
                        <a href="{{ route('overtime.view-all', ['status' => 'pending']) }}" class="approval-row">
                            <span class="approval-row-left"><i class="feather-watch text-muted"></i> Overtime</span>
                            <span class="approval-count">{{ $pending_overtime_requests }}</span>
                        </a>
                    @endif
                    @if ($show_expenses)
                        <a href="{{ route('expense.view-all', ['status' => 'pending']) }}" class="approval-row">
                            <span class="approval-row-left"><i class="feather-credit-card text-muted"></i> Expenses</span>
                            <span class="approval-count">{{ $pending_expense_approvals }}</span>
                        </a>
                    @endif
                    @if ($show_wfh_travel)
                        <a href="{{ route('manager.requests', ['status' => 'PENDING']) }}" class="approval-row">
                            <span class="approval-row-left"><i class="feather-send text-muted"></i> WFH / Travel</span>
                            <span class="approval-count">{{ $pending_wfh_travel_requests }}</span>
                        </a>
                    @endif
                    @feature('offboarding')
                        <a href="{{ route('offboarding.manager') }}" class="approval-row">
                            <span class="approval-row-left"><i class="feather-user-minus text-muted"></i> Offboarding</span>
                            <span class="approval-count">{{ $pending_offboarding_requests }}</span>
                        </a>
                    @endfeature
                </x-ui.card>
            </div>

            <div class="col-xxl-4 col-md-6">
                <x-ui.card :bodyClass="'p-0'" class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Team Leaves</h6>
                        <a href="{{ route('leave.view-all') }}" class="fs-11">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Employee</th><th>Type</th><th>Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse($recent_team_leaves as $leave)
                                    <tr>
                                        <td>{{ Str::limit($leave->user_name ?? 'N/A', 16) }}</td>
                                        <td>{{ $leave->leave_type_name ?? 'N/A' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d M') }}</td>
                                        <td><x-ui.status-badge :status="$leave->status" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-ui.empty-state icon="calendar" title="No leave requests" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
        </div>

        <!-- Quick Links / Team Task Overview / Team Performance / Upcoming Holidays -->
        <div class="row g-compact mb-2">
            <div class="col-md-3">
                <x-ui.card title="Quick Links" class="stretch stretch-full h-100">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('team.index') }}" class="qa-block">
                                <span class="qa-label">My Team</span>
                                <span class="qa-icon"><i class="feather-users"></i></span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="{{ route('leave.view-all') }}" class="qa-block">
                                <span class="qa-label">Leave Approvals</span>
                                <span class="qa-icon"><i class="feather-calendar"></i></span>
                            </a>
                        </div>
                        @if ($show_regularization)
                            <div class="col-6">
                                <a href="{{ route('attendance-regularization.manage') }}" class="qa-block">
                                    <span class="qa-label">Regularizations</span>
                                    <span class="qa-icon"><i class="feather-edit-3"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_overtime)
                            <div class="col-6">
                                <a href="{{ route('overtime.view-all') }}" class="qa-block">
                                    <span class="qa-label">Overtime</span>
                                    <span class="qa-icon"><i class="feather-watch"></i></span>
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
                        @if ($show_wfh_travel)
                            <div class="col-6">
                                <a href="{{ route('manager.requests') }}" class="qa-block">
                                    <span class="qa-label">WFH / Travel</span>
                                    <span class="qa-icon"><i class="feather-send"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_tasks)
                            <div class="col-6">
                                <a href="{{ route('task.assigned-by-me') }}" class="qa-block">
                                    <span class="qa-label">Team Tasks</span>
                                    <span class="qa-icon"><i class="feather-check-square"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_projects)
                            <div class="col-6">
                                <a href="{{ route('project.index') }}" class="qa-block">
                                    <span class="qa-label">Projects</span>
                                    <span class="qa-icon"><i class="feather-folder"></i></span>
                                </a>
                            </div>
                        @endif
                        @if ($show_meetings)
                            <div class="col-6">
                                <a href="{{ route('meetings.index') }}" class="qa-block">
                                    <span class="qa-label">Meetings</span>
                                    <span class="qa-icon"><i class="feather-video"></i></span>
                                </a>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            <div class="col-md-3">
                <div class="card stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Team Task Overview</h6>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'team-task-bars', 'team-task-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        @php
                            $teamTaskFunnel = [
                                ['label' => 'Total', 'val' => $total_team_tasks],
                                ['label' => 'Pending', 'val' => $team_task_status_counts['pending'] ?? 0],
                                ['label' => 'In Progress', 'val' => $team_task_status_counts['in_progress'] ?? 0],
                                ['label' => 'Completed', 'val' => $team_task_status_counts['completed'] ?? 0],
                            ];
                        @endphp
                        <div id="team-task-bars" class="funnel-list">
                            @foreach ($teamTaskFunnel as $t)
                                <div class="funnel-row">
                                    <div class="funnel-label">{{ $t['label'] }}</div>
                                    <div class="funnel-track">
                                        <div class="funnel-fill" style="width: {{ $total_team_tasks > 0 ? round(($t['val'] / $total_team_tasks) * 100) : 0 }}%"></div>
                                    </div>
                                    <div class="funnel-count">{{ $t['val'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <table class="mini-table" id="team-task-table-view" style="display:none;">
                            <tbody>
                                @foreach ($teamTaskFunnel as $t)
                                    <tr><td>{{ $t['label'] }}</td><td>{{ $t['val'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Team Performance</h6>
                        @if ($show_performance)
                            <a href="{{ route('performance.team') }}" class="fs-11">View Details</a>
                        @endif
                    </div>
                    @if ($show_performance && $team_kpi_avg !== null)
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <x-ui.stat-card icon="trending-up" label="Team Avg Score" value="{{ $team_kpi_avg }}%" />
                            </div>
                            <div class="col-6">
                                <x-ui.stat-card icon="award" label="Top Performer" value="{{ Str::limit($team_top_performer ?? 'N/A', 12) }}" />
                            </div>
                        </div>
                    @else
                        <x-ui.empty-state icon="trending-up" title="No performance data yet" />
                    @endif
                </x-ui.card>
            </div>

            <div class="col-md-3">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Upcoming Holidays</h6>
                        @if ($show_holidays)
                            <a href="{{ route('holiday.index') }}" class="fs-11">View All</a>
                        @endif
                    </div>
                    @forelse($upcoming_holidays as $holiday)
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

        <!-- Recent Team Overtime / WFH-Travel / Regularization / Announcements -->
        <div class="row g-compact mb-2">
            @if ($show_overtime)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Team Overtime</h6>
                            <a href="{{ route('overtime.view-all') }}" class="fs-11">View All</a>
                        </div>
                        @forelse($recent_team_overtime as $ot)
                            <div class="today-status-row">
                                <span class="text-muted">{{ Str::limit($ot->user_name ?? 'N/A', 12) }}</span>
                                <span>{{ $ot->overtime_hours }} hrs</span>
                                <x-ui.status-badge :status="$ot->status" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="watch" title="No overtime requests" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            @if ($show_wfh_travel)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Team WFH / Travel</h6>
                            <a href="{{ route('manager.requests') }}" class="fs-11">View All</a>
                        </div>
                        @forelse($recent_team_requests as $reqRow)
                            <div class="today-status-row">
                                <span class="text-muted">{{ Str::limit($reqRow->user->name ?? 'N/A', 12) }}</span>
                                <span>{{ $reqRow->requestType->type_name ?? 'Request' }}</span>
                                <x-ui.status-badge :status="strtolower($reqRow->status)" />
                            </div>
                        @empty
                            <x-ui.empty-state icon="send" title="No WFH / Travel requests" />
                        @endforelse
                    </x-ui.card>
                </div>
            @endif

            @if ($show_regularization)
                <div class="col-md-3">
                    <x-ui.card class="stretch stretch-full h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Team Regularizations</h6>
                            <a href="{{ route('attendance-regularization.manage') }}" class="fs-11">View All</a>
                        </div>
                        @forelse($recent_team_regularizations as $reg)
                            <div class="dash-list-item">
                                <div>
                                    <span class="d-block dash-list-title">{{ Str::limit($reg->user_name ?? 'N/A', 14) }}</span>
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

            <div class="col-md-3">
                <x-ui.card class="stretch stretch-full h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Announcements</h6>
                        @if ($show_announcements)
                            <a href="{{ route('announcement.all') }}" class="fs-11">View All</a>
                        @endif
                    </div>
                    @forelse($recent_announcements as $announcement)
                        <div class="dash-list-item" style="align-items:flex-start;">
                            <div>
                                <span class="d-block dash-list-title">{{ $announcement->title }}</span>
                                <span class="dash-list-sub d-block mb-1">{{ Str::limit($announcement->description, 60) }}</span>
                                <span class="dash-list-sub">{{ $announcement->user_name ?? 'System' }} • {{ \Carbon\Carbon::parse($announcement->created_at)->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state icon="megaphone" title="No announcements" />
                    @endforelse
                </x-ui.card>
            </div>
        </div>

    </div>
@endsection

@section('script-area')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            var trendEl = document.getElementById('dash-attendance-trend-chart');
            if (!trendEl) return;
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
        })();

        function toggleChartTable(btn, chartId, tableId) {
            var chartEl = document.getElementById(chartId);
            var tableEl = document.getElementById(tableId);
            if (!chartEl || !tableEl) return;
            var showingTable = tableEl.style.display !== 'none';
            chartEl.style.display = showingTable ? '' : 'none';
            tableEl.style.display = showingTable ? 'none' : 'table';
            btn.textContent = showingTable ? 'View as table' : 'View as chart';
        }
    </script>
@endsection
