{{-- resources/views/client/performance/my-dashboard.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .welcome-card {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            color: white;
        }

        .grade-circle {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            border: 2px solid rgba(255,255,255,0.3);
        }

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
            padding: 14px 12px;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-box:hover {
            border-color: #4f46e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 11px;
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
            padding: 18px 20px;
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .kpi-badge {
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .kpi-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .progress {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 12px 0;
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
            padding: 14px 20px;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 14px;
        }

        .section-body {
            padding: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .feedback-box {
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
        }

        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        
        .badge-sm { font-size: 10px; padding: 2px 8px; border-radius: 12px; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #ede9fe; color: #6d28d9; }

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
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Performance</li>
            </ul>
        </div>
        <div class="page-header-right">
            <div class="btn-group btn-group-sm">
                <a href="{{ route('performance.my-dashboard', ['month' => $prevMonth]) }}" class="btn btn-outline-secondary">
                    <i class="feather-chevron-left"></i>
                </a>
                <button class="btn btn-light disabled">{{ date('F Y', strtotime($month . '-01')) }}</button>
                @if($nextMonth)
                    <a href="{{ route('performance.my-dashboard', ['month' => $nextMonth]) }}" class="btn btn-outline-secondary">
                        <i class="feather-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary disabled" disabled><i class="feather-chevron-right"></i></button>
                @endif
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 24px !important;">

        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 text-light" style="font-size: 18px;">Welcome back, {{ Auth::user()->name }}!</h5>
                    <p class="mb-0" style="font-size: 13px; opacity: 0.9;">Performance summary for {{ date('F Y', strtotime($month . '-01')) }}</p>
                </div>
                <div class="grade-circle">
                    {{ $kpiScore->grade ?? 'C' }}
                </div>
            </div>
        </div>

        <!-- Stats Grid - 5 Metrics -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->attendance_score ?? 0 }}%</div>
                <div class="stat-label">Attendance</div>
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
            </div>
            <div class="stat-box">
                <div class="stat-value">{{ $kpiScore->manager_rating_score ?? 'N/A' }}</div>
                <div class="stat-label">Manager Rating</div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <!-- Overall Score -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Overall Score</span>
                    <span class="kpi-badge">Grade {{ $kpiScore->grade ?? 'C' }}</span>
                </div>
                <div class="kpi-number">{{ $kpiScore->overall_score ?? 0 }}%</div>
                <div class="progress">
                    <div class="progress-bar bg-primary" style="width: {{ $kpiScore->overall_score ?? 0 }}%"></div>
                </div>
                @if(($prevOverallScore ?? 0) > 0)
                    @php $diff = ($kpiScore->overall_score ?? 0) - $prevOverallScore; @endphp
                    <small class="text-{{ $diff >= 0 ? 'success' : 'danger' }}">
                        <i class="feather-arrow-{{ $diff >= 0 ? 'up' : 'down' }}"></i> {{ abs($diff) }}% from last month
                    </small>
                @endif
            </div>

            <!-- Attendance Breakdown -->
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Attendance Details</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 12px;">Present Days</span>
                    <strong class="text-success">{{ $kpiScore->present_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 12px;">Absent Days</span>
                    <strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 12px;">Late Days</span>
                    <strong class="text-warning">{{ $kpiScore->late_days ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="font-size: 12px;">Half Days</span>
                    <strong>{{ $kpiScore->half_days ?? 0 }}</strong>
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
                    <span style="font-size: 12px;">Completed Tasks</span>
                    <strong class="text-success">{{ $kpiScore->completed_tasks ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size: 12px;">Assigned Tasks</span>
                    <strong>{{ $kpiScore->assigned_tasks ?? 0 }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span style="font-size: 12px;">On-Time Completion</span>
                    <strong class="text-info">{{ $kpiScore->on_time_completed_tasks ?? 0 }}</strong>
                </div>
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" style="width: {{ $kpiScore->task_completion_score ?? 0 }}%"></div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3">
            <div class="col-md-7">
                <div class="section-card">
                    <div class="section-header">Performance Trend (Last 6 Months)</div>
                    <div class="section-body">
                        <div class="chart-box" style="height: 260px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="section-card">
                    <div class="section-header">Score Distribution</div>
                    <div class="section-body">
                        <div style="height: 200px;">
                            <canvas id="pieChart"></canvas>
                        </div>
                        <div class="row mt-3">
                            <div class="col-6">
                                <small><span class="badge-sm badge-success">●</span> Attendance: {{ $kpiScore->attendance_score ?? 0 }}%</small>
                            </div>
                            <div class="col-6">
                                <small><span class="badge-sm badge-primary">●</span> Task: {{ $kpiScore->task_completion_score ?? 0 }}%</small>
                            </div>
                            <div class="col-6 mt-2">
                                <small><span class="badge-sm badge-warning">●</span> Deadline: {{ $kpiScore->deadline_met_score ?? 0 }}%</small>
                            </div>
                            <div class="col-6 mt-2">
                                <small><span class="badge-sm badge-purple">●</span> Regularization: {{ $kpiScore->regularization_score ?? 100 }}%</small>
                            </div>
                        </div>
                    </div>
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
                <div class="stat-number">{{ $kpiScore->overtime_hours ?? 0 }}<span style="font-size: 12px;">h</span></div>
                <div class="stat-label">Overtime Hours</div>
                <small class="text-muted">Bonus metric (+0.5%/hr)</small>
            </div>
            <div class="stat-item">
                <div class="stat-number">{{ $kpiScore->manager_rating_raw ?? 'N/A' }}<span style="font-size: 12px;">/5</span></div>
                <div class="stat-label">Manager Rating</div>
                @if($kpiScore->manager_rating_score)
                    <small class="text-muted">{{ $kpiScore->manager_rating_score }}%</small>
                @endif
            </div>
        </div>

        <!-- Detailed Info Row -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="section-card">
                    <div class="section-header">Complete Attendance Breakdown</div>
                    <div class="section-body">
                        <div class="info-row"><span>Present Days</span><strong>{{ $kpiScore->present_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Absent Days</span><strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Half Days</span><strong>{{ $kpiScore->half_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Late Days</span><strong class="text-warning">{{ $kpiScore->late_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Early Departure Days</span><strong>{{ $kpiScore->early_departure_days ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Paid Leaves</span><strong>{{ $kpiScore->paid_leaves ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Unpaid Leaves</span><strong class="text-danger">{{ $kpiScore->unpaid_leaves ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Holidays</span><strong>{{ $kpiScore->holidays ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Week-offs</span><strong>{{ $kpiScore->weekoffs ?? 0 }} days</strong></div>
                        <div class="info-row"><span>Late Minutes</span><strong>{{ $kpiScore->total_late_minutes ?? 0 }} min</strong></div>
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
                                    $penalty = ($kpiScore->rejected_regularization_count ?? 0) * 5;
                                @endphp
                                -{{ $penalty }}% penalty from rejections
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

        <!-- Manager Feedback -->
        @if($kpiScore->manager_feedback)
        <div class="section-card">
            <div class="section-header">Manager Feedback</div>
            <div class="section-body">
                <div class="feedback-box">
                    <p class="mb-2" style="font-size: 13px;">{{ $kpiScore->manager_feedback }}</p>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            const historyData = @json($history ?? []);
            
            // Line Chart
            if (historyData && historyData.length > 0) {
                const ctx = document.getElementById('trendChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: historyData.map(item => {
                            if (item.reporting_month) {
                                const date = new Date(item.reporting_month);
                                return date.toLocaleString('default', { month: 'short', year: 'numeric' });
                            }
                            return 'N/A';
                        }).reverse(),
                        datasets: [
                            {
                                label: 'Overall Score',
                                data: historyData.map(item => item.overall_score || 0).reverse(),
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79, 70, 229, 0.05)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3
                            },
                            {
                                label: 'Attendance',
                                data: historyData.map(item => item.attendance_score || 0).reverse(),
                                borderColor: '#10b981',
                                borderWidth: 1.5,
                                tension: 0.3,
                                pointRadius: 2
                            },
                            {
                                label: 'Task Completion',
                                data: historyData.map(item => item.task_completion_score || 0).reverse(),
                                borderColor: '#f59e0b',
                                borderWidth: 1.5,
                                tension: 0.3,
                                pointRadius: 2
                            },
                            {
                                label: 'Deadline Met',
                                data: historyData.map(item => item.deadline_met_score || 0).reverse(),
                                borderColor: '#ef4444',
                                borderWidth: 1.5,
                                tension: 0.3,
                                pointRadius: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 10 } } }
                        },
                        scales: {
                            y: { beginAtZero: true, max: 100, ticks: { stepSize: 25, callback: v => v + '%' } }
                        }
                    }
                });
            }

            // Pie Chart
            const pieCtx = document.getElementById('pieChart').getContext('2d');
            new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Attendance', 'Task Completion', 'Deadline Met', 'Regularization'],
                    datasets: [{
                        data: [
                            {{ $kpiScore->attendance_score ?? 0 }},
                            {{ $kpiScore->task_completion_score ?? 0 }},
                            {{ $kpiScore->deadline_met_score ?? 0 }},
                            {{ $kpiScore->regularization_score ?? 100 }}
                        ],
                        backgroundColor: ['#10b981', '#4f46e5', '#f59e0b', '#8b5cf6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom', labels: { font: { size: 9 } } } }
                }
            });
        });
    </script>
@endsection