{{-- resources/views/client/performance/individual-report.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .employee-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            color: white;
        }

        .score-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .score-circle .score {
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
        }

        .score-circle .grade {
            font-size: 12px;
            font-weight: 600;
        }

        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-stable { color: #f59e0b; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .stat-box:hover {
            border-color: #4f46e5;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 4px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .kpi-number {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .progress {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 3px;
        }

        .stats-row {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .stats-row .stat-item {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }

        .stats-row .stat-number {
            font-size: 20px;
            font-weight: 700;
        }

        .stats-row .stat-label {
            font-size: 10px;
            margin-top: 4px;
        }

        .section-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .section-header {
            padding: 12px 20px;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 13px;
        }

        .section-body {
            padding: 18px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .badge-sm {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-purple { background: #ede9fe; color: #6d28d9; }

        .feedback-box {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 14px;
            border-radius: 8px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        @media (max-width: 1000px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-row { flex-wrap: wrap; }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left">
            <h5 class="m-b-10">Performance Dashboard</h5>
            <ul class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ url('/dashboard') }}">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('performance.team') }}">Performance</a>
                </li>
                <li class="breadcrumb-item active">
                    {{ $employee->name }}
                </li>
            </ul>
        </div>

        <div class="page-header-right">
            <div class="d-flex align-items-center gap-2">
                @if ($prevMonth)
                    <a href="{{ route('performance.individual', ['userId' => $employee->id, 'month' => $prevMonth]) }}"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="feather-chevron-left"></i>
                    </a>
                @else
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="feather-chevron-left"></i>
                    </button>
                @endif

                <div class="px-3 py-1 border rounded bg-light fw-semibold">
                    {{ date('F Y', strtotime($month . '-01')) }}
                </div>

                @if ($nextMonth && ($nextMonthExists ?? false))
                    <a href="{{ route('performance.individual', ['userId' => $employee->id, 'month' => $nextMonth]) }}"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="feather-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="feather-chevron-right"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 24px !important;">

        <!-- Employee Header -->
        <div class="employee-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center gap-3">
                    <div class="score-circle">
                        <div class="score">{{ $overallScore ?? 0 }}%</div>
                        <div class="grade">Grade {{ $grade ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <h5 class="mb-1 text-light" style="font-size: 18px;">{{ $employee->name }}</h5>
                        <p class="mb-0" style="font-size: 12px; opacity: 0.9;">
                            {{ $employee->jobDetails?->Designation?->name ?? 'No Designation' }} |
                            {{ $employee->jobDetails?->Department?->name ?? 'No Department' }}
                        </p>
                        <small style="opacity: 0.85;">ID: {{ $employee->employee_id ?? 'N/A' }}</small>
                    </div>
                </div>
                <div class="text-end">
                    @if (isset($scoreDifference))
                        <div class="mb-1">
                            @if ($scoreDifference > 0)
                                <span class="trend-up"><i class="feather-trending-up"></i> +{{ $scoreDifference }}%</span>
                            @elseif($scoreDifference < 0)
                                <span class="trend-down"><i class="feather-trending-down"></i> {{ $scoreDifference }}%</span>
                            @else
                                <span class="trend-stable"><i class="feather-minus"></i> No change</span>
                            @endif
                        </div>
                    @endif
                    @if (isset($departmentAvg))
                        <div>
                            <small>Dept Avg</small>
                            <div class="fw-bold">{{ $departmentAvg }}%</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Stats Grid - 5 Metrics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->attendance_score ?? 0 }}%</div>
                <div class="stat-label">Attendance</div>
                @if(($kpiScore->late_count ?? 0) > 0)
                    <small class="text-warning" style="font-size: 9px;">({{ $kpiScore->late_count }} late)</small>
                @endif
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->task_completion_score ?? 0 }}%</div>
                <div class="stat-label">Task Completion</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->deadline_met_score ?? 0 }}%</div>
                <div class="stat-label">Deadline Met</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->regularization_score ?? 100 }}%</div>
                <div class="stat-label">Regularization</div>
                @if(($kpiScore->regularization_count ?? 0) > 0)
                    <small class="text-warning" style="font-size: 9px;">({{ $kpiScore->regularization_count }} req)</small>
                @endif
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->manager_rating_score ?? 'N/A' }}%</div>
                <div class="stat-label">Manager Rating</div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <!-- Overall Score -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Overall Score</span>
                    <span class="badge-sm badge-info">Grade {{ $grade ?? 'C' }}</span>
                </div>
                <div class="kpi-number">{{ $overallScore ?? 0 }}%</div>
                <div class="progress">
                    <div class="progress-bar bg-primary" style="width: {{ $overallScore ?? 0 }}%"></div>
                </div>
                @if (isset($scoreDifference) && $scoreDifference != 0)
                    <small class="text-muted d-block mt-2">
                        {{ $scoreDifference > 0 ? '+' : '' }}{{ $scoreDifference }}% vs last month
                    </small>
                @endif
            </div>

            <!-- Attendance Breakdown -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Attendance Details</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">Present Days</span>
                    <strong class="text-success">{{ $kpiScore->present_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">Absent Days</span>
                    <strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">Late Days</span>
                    <strong class="text-warning">{{ $kpiScore->late_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="font-size: 11px;">Paid Leaves</span>
                    <strong>{{ $kpiScore->paid_leaves ?? 0 }}</strong>
                </div>
                @if(($kpiScore->late_penalty ?? 0) > 0)
                    <small class="text-warning d-block mt-2">Penalty: -{{ $kpiScore->late_penalty }}%</small>
                @endif
            </div>

            <!-- Task Performance -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Task Performance</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">Completed</span>
                    <strong class="text-success">{{ $kpiScore->completed_tasks ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">Assigned</span>
                    <strong>{{ $kpiScore->assigned_tasks ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 11px;">On-Time</span>
                    <strong class="text-info">{{ $kpiScore->on_time_completed_tasks ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="font-size: 11px;">Late Tasks</span>
                    <strong class="text-warning">
                        {{ ($kpiScore->completed_tasks ?? 0) - ($kpiScore->on_time_completed_tasks ?? 0) }}
                    </strong>
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: {{ $kpiScore->task_completion_score ?? 0 }}%"></div>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number">{{ $kpiScore->regularization_count ?? 0 }}</div>
                <div class="stat-label">Regularization Requests</div>
                @if(($kpiScore->regularization_count ?? 0) > 0)
                    <div class="mt-1">
                        <span class="badge-sm badge-success">A: {{ $kpiScore->approved_regularization_count ?? 0 }}</span>
                        <span class="badge-sm badge-warning ms-1">P: {{ $kpiScore->pending_regularization_count ?? 0 }}</span>
                        <span class="badge-sm badge-danger ms-1">R: {{ $kpiScore->rejected_regularization_count ?? 0 }}</span>
                    </div>
                @endif
            </div>
            <div class="stat-item">
                <div class="stat-number">{{ $kpiScore->overtime_hours ?? 0 }}<span style="font-size: 11px;">h</span></div>
                <div class="stat-label">Overtime</div>
                <small class="text-muted">Bonus metric (+0.5%/hr)</small>
            </div>
            <div class="stat-item">
                <div class="stat-number">{{ $kpiScore->manager_rating_raw ?? 'N/A' }}<span style="font-size: 11px;">/5</span></div>
                <div class="stat-label">Manager Rating</div>
                @if($kpiScore->manager_rating_score)
                    <small class="text-muted">{{ $kpiScore->manager_rating_score }}%</small>
                @endif
            </div>
        </div>

        <!-- Attendance & Leave Details -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="section-card">
                    <div class="section-header">Attendance Breakdown</div>
                    <div class="section-body">
                        <div class="info-row"><span>Present Days</span><strong class="text-success">{{ $kpiScore->present_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Absent Days</span><strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Half Days</span><strong>{{ $kpiScore->half_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Late Days</span><strong class="text-warning">{{ $kpiScore->late_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Early Departure Days</span><strong>{{ $kpiScore->early_departure_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Paid Leaves</span><strong>{{ $kpiScore->paid_leaves ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Unpaid Leaves</span><strong class="text-danger">{{ $kpiScore->unpaid_leaves ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Holidays</span><strong>{{ $kpiScore->holidays ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Week-offs</span><strong>{{ $kpiScore->weekoffs ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Total Late Minutes</span><strong>{{ $kpiScore->total_late_minutes ?? 0 }} min</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="section-card">
                    <div class="section-header">Quick Insights</div>
                    <div class="section-body">
                        <div class="info-row">
                            <span>Best Area</span>
                            <strong class="text-success">
                                @php
                                    $scores = [
                                        'Attendance' => $kpiScore->attendance_score ?? 0,
                                        'Task Completion' => $kpiScore->task_completion_score ?? 0,
                                        'Deadline Met' => $kpiScore->deadline_met_score ?? 0,
                                        'Regularization' => $kpiScore->regularization_score ?? 100
                                    ];
                                    arsort($scores);
                                    echo key($scores) . ' (' . round(current($scores), 1) . '%)';
                                @endphp
                            </strong>
                        </div>
                        <div class="info-row">
                            <span>Needs Improvement</span>
                            <strong class="text-warning">
                                @php
                                    asort($scores);
                                    echo key($scores) . ' (' . round(current($scores), 1) . '%)';
                                @endphp
                            </strong>
                        </div>
                        <div class="info-row">
                            <span>Performance Level</span>
                            <strong>
                                @php
                                    $overall = $kpiScore->overall_score ?? 0;
                                    if ($overall >= 90) echo 'Outstanding';
                                    elseif ($overall >= 80) echo 'Excellent';
                                    elseif ($overall >= 70) echo 'Good';
                                    elseif ($overall >= 60) echo 'Satisfactory';
                                    elseif ($overall >= 50) echo 'Needs Improvement';
                                    else echo 'Critical';
                                @endphp
                            </strong>
                        </div>
                        @if(($kpiScore->regularization_count ?? 0) > 0)
                            <div class="info-row">
                                <span>Regularization Impact</span>
                                <strong class="text-warning">
                                    @php
                                        $rejectionPenalty = ($kpiScore->rejected_regularization_count ?? 0) * 5;
                                    @endphp
                                    -{{ $rejectionPenalty }}% from rejections
                                </strong>
                            </div>
                        @endif
                        @if(($kpiScore->late_penalty ?? 0) > 0)
                            <div class="info-row">
                                <span>Late Penalty</span>
                                <strong class="text-warning">-{{ $kpiScore->late_penalty }}%</strong>
                            </div>
                        @endif
                        @if(($kpiScore->overtime_hours ?? 0) > 0)
                            <div class="info-row">
                                <span>Overtime Bonus</span>
                                <strong class="text-success">+{{ min(10, ($kpiScore->overtime_hours * 0.5)) }}%</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Regularization Details -->
        @if(($kpiScore->regularization_count ?? 0) > 0 && isset($regularizationDetails) && count($regularizationDetails) > 0)
            <div class="section-card">
                <div class="section-header">Regularization Requests</div>
                <div class="section-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($regularizationDetails as $reg)
                                <tr>
                                    <td>{{ $reg['date'] ?? 'N/A' }}</td>
                                    <td>{{ $reg['request_type'] ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge-sm 
                                            @if($reg['status'] == 'approved') badge-success
                                            @elseif($reg['status'] == 'pending') badge-warning
                                            @else badge-danger @endif">
                                            {{ ucfirst($reg['status'] ?? 'N/A') }}
                                        </span>
                                    </td>
                                    <td>{{ Str::limit($reg['reason'] ?? '', 40) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <small class="text-muted">
                        Score calculation: {{ $kpiScore->approved_regularization_count ?? 0 }}/{{ $kpiScore->regularization_count ?? 0 }} approved = 
                        {{ round((($kpiScore->approved_regularization_count ?? 0) / max(1, $kpiScore->regularization_count ?? 1)) * 100, 1) }}% - 
                        {{ ($kpiScore->rejected_regularization_count ?? 0) * 5 }}% penalty = 
                        {{ $kpiScore->regularization_score ?? 100 }}%
                    </small>
                </div>
            </div>
        @endif

        <!-- Manager Feedback -->
        @if($kpiScore->manager_feedback)
            <div class="section-card">
                <div class="section-header">Manager Feedback</div>
                <div class="section-body">
                    <div class="feedback-box">
                        <p class="mb-2" style="font-size: 12px;">{{ $kpiScore->manager_feedback }}</p>
                        <small class="text-muted">
                            Rating: {{ $kpiScore->manager_rating_raw ?? 'N/A' }}/5 |
                            @if($kpiScore->manager_rated_at)
                                {{ \Carbon\Carbon::parse($kpiScore->manager_rated_at)->format('d M, Y') }}
                            @else
                                Not rated yet
                            @endif
                        </small>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Toggle for leave details
            $('[data-bs-toggle="collapse"]').on('click', function(e) {
                e.preventDefault();
                const target = $(this).data('target');
                $(target).toggleClass('show');
                $(this).find('i').toggleClass('feather-chevron-down feather-chevron-up');
            });
        });
    </script>
@endsection
