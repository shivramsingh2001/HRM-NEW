{{-- resources/views/client/performance/my-dashboard.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .perf-hero {
            background: #0D6EFD;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 18px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .perf-hero h5 { font-size: 15px; margin: 0 0 3px; }
        .perf-hero p { font-size: 12px; margin: 0; opacity: .9; }
        .perf-hero-score { display: flex; align-items: center; gap: 14px; }
        #overallGauge { width: 96px; height: 96px; }
        .perf-hero-score .score-text { text-align: right; }
        .perf-hero-score .score-text .val { font-size: 26px; font-weight: 700; line-height: 1; }
        .perf-hero-score .score-text .diff { font-size: 11px; opacity: .9; margin-top: 3px; }

        .perf-stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        @media (max-width: 1200px) { .perf-stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 640px) { .perf-stats-grid { grid-template-columns: repeat(2, 1fr); } }

        .perf-section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .perf-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .perf-info-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid var(--border, #f1f5f9);
            font-size: 12px;
        }
        .perf-info-row:last-child { border-bottom: none; }
        .perf-info-row span { color: #64748b; }
        .perf-info-row strong { color: var(--text-primary); font-weight: 600; }

        .perf-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        @media (max-width: 700px) { .perf-mini-grid { grid-template-columns: 1fr; } }
        .perf-mini-item {
            background: #f8fafc;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        .perf-mini-item .num { font-size: 19px; font-weight: 700; color: var(--text-primary); }
        .perf-mini-item .lbl { font-size: 10.5px; color: #64748b; margin-top: 3px; text-transform: uppercase; letter-spacing: .3px; }

        .feedback-box {
            background: var(--primary-light);
            border-left: 3px solid var(--primary);
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 12.5px;
        }

        .perf-day-cell { cursor: pointer; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Performance Dashboard">
        <x-slot:actions>
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
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">

        <div class="perf-hero">
            <div>
                <h5>Welcome back, {{ Auth::user()->name }}</h5>
                <p>Performance summary for {{ date('F Y', strtotime($month . '-01')) }}</p>
            </div>
            <div class="perf-hero-score">
                <div id="overallGauge"></div>
                <div class="score-text">
                    <div class="val">{{ $kpiScore->overall_score ?? '—' }}@if($kpiScore?->overall_score)%@endif</div>
                    @if(($prevOverallScore ?? null) !== null && $kpiScore?->overall_score !== null)
                        @php $diff = round($kpiScore->overall_score - $prevOverallScore, 1); @endphp
                        <div class="diff"><i class="feather-arrow-{{ $diff >= 0 ? 'up' : 'down' }}"></i> {{ abs($diff) }}% vs last month</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="perf-stats-grid">
            <x-ui.stat-card icon="calendar" label="Attendance" :value="($kpiScore->attendance_score ?? null) !== null ? $kpiScore->attendance_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="check-square" label="Task Completion" :value="($kpiScore->task_completion_score ?? null) !== null ? $kpiScore->task_completion_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="clock" label="On-Time Completion" :value="($kpiScore->deadline_met_score ?? null) !== null ? $kpiScore->deadline_met_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="briefcase" label="Project Participation" :value="($kpiScore->project_participation_score ?? null) !== null ? $kpiScore->project_participation_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="file-text" label="Regularization" :value="($kpiScore->regularization_score ?? null) !== null ? $kpiScore->regularization_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="star" label="Manager Rating" :value="$kpiScore->manager_rating_score ?? 'N/A'" />
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <x-ui.card>
                    <div class="perf-card-head">
                        <h6 class="perf-section-title">Performance Trend</h6>
                        <div class="period-toggle" id="trendToggle">
                            <button type="button" class="active" data-period="daily">Daily</button>
                            <button type="button" data-period="weekly">Weekly</button>
                            <button type="button" data-period="monthly">Monthly</button>
                        </div>
                    </div>
                    <div id="trendChart" style="height: 270px;"></div>
                </x-ui.card>
            </div>
            <div class="col-lg-4">
                <x-ui.card>
                    <h6 class="perf-section-title mb-2">Score Composition</h6>
                    <div id="compositionChart" style="height: 230px;"></div>
                </x-ui.card>
            </div>
        </div>

        <div class="perf-mini-grid">
            <div class="perf-mini-item">
                <div class="num">{{ $kpiScore->regularization_count ?? 0 }}</div>
                <div class="lbl">Regularization Requests</div>
                @if(($kpiScore->regularization_count ?? 0) > 0)
                    <div class="mt-1">
                        <span class="status-badge" data-status="approved">A {{ $kpiScore->approved_regularization_count ?? 0 }}</span>
                        <span class="status-badge" data-status="pending">P {{ $kpiScore->pending_regularization_count ?? 0 }}</span>
                        <span class="status-badge" data-status="rejected">R {{ $kpiScore->rejected_regularization_count ?? 0 }}</span>
                    </div>
                @endif
            </div>
            <div class="perf-mini-item">
                <div class="num">{{ $kpiScore->overtime_hours ?? 0 }}<span style="font-size:12px;">h</span></div>
                <div class="lbl">Overtime Hours</div>
            </div>
            <div class="perf-mini-item">
                <div class="num">{{ $kpiScore->manager_rating_raw ?? 'N/A' }}<span style="font-size:12px;">/5</span></div>
                <div class="lbl">Manager Rating</div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <x-ui.card title="Attendance Breakdown">
                    <div class="perf-info-row"><span>Present Days</span><strong>{{ $kpiScore->present_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Absent Days</span><strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Half Days</span><strong>{{ $kpiScore->half_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Late Days</span><strong class="text-warning">{{ $kpiScore->late_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Early Departure Days</span><strong>{{ $kpiScore->early_departure_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Paid Leaves</span><strong>{{ $kpiScore->paid_leaves ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Unpaid Leaves</span><strong class="text-danger">{{ $kpiScore->unpaid_leaves ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Holidays / Week-offs</span><strong>{{ $kpiScore->holidays ?? 0 }} / {{ $kpiScore->weekoffs ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Days calculated / expected</span><strong>{{ $kpiScore->days_calculated ?? 0 }} / {{ $kpiScore->days_expected ?? 0 }}</strong></div>
                </x-ui.card>
            </div>
            <div class="col-md-6">
                <x-ui.card title="Task &amp; Project Performance">
                    <div class="perf-info-row"><span>Assigned Tasks</span><strong>{{ $kpiScore->assigned_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Completed Tasks</span><strong class="text-success">{{ $kpiScore->completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>On-Time Completed</span><strong>{{ $kpiScore->on_time_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Late Completed</span><strong class="text-warning">{{ $kpiScore->late_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Overdue Tasks</span><strong class="text-danger">{{ $kpiScore->overdue_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Project-linked Tasks</span><strong>{{ $kpiScore->project_assigned_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Project Tasks Completed</span><strong>{{ $kpiScore->project_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Project Tasks On-Time</span><strong>{{ $kpiScore->project_on_time_tasks ?? 0 }}</strong></div>
                </x-ui.card>
            </div>
        </div>

        @if($kpiScore?->manager_feedback)
            <x-ui.card title="Manager Feedback">
                <div class="feedback-box">
                    {{ $kpiScore->manager_feedback }}
                    <div class="text-muted mt-2" style="font-size:11px;">
                        Rating: {{ $kpiScore->manager_rating_raw ?? 'N/A' }}/5
                        @if($kpiScore->manager_rated_at) &middot; {{ \Carbon\Carbon::parse($kpiScore->manager_rated_at)->format('d M, Y') }} @endif
                    </div>
                </div>
            </x-ui.card>
        @endif
    </div>

    <x-ui.drawer id="dayDetailDrawer" title="Day Detail">
        <div id="dayDetailBody">
            <x-ui.empty-state icon="calendar" title="Pick a day on the Daily trend chart" />
        </div>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script>
        $(function () {
            const overall = {{ (float) ($kpiScore->overall_score ?? 0) }};
            const grade = @json($kpiScore->grade ?? 'N/A');
            const gaugeColor = overall >= 80 ? '#059669' : (overall >= 60 ? '#0D6EFD' : (overall >= 40 ? '#d97706' : '#dc2626'));

            try {
                new ApexCharts(document.querySelector('#overallGauge'), {
                    chart: { type: 'radialBar', height: 96, width: 96, sparkline: { enabled: true } },
                    series: [overall],
                    colors: [gaugeColor],
                    plotOptions: {
                        radialBar: {
                            hollow: { size: '55%' },
                            track: { background: 'rgba(255,255,255,0.25)' },
                            dataLabels: {
                                show: true,
                                value: { show: true, fontSize: '15px', fontWeight: 700, color: '#fff', offsetY: 4, formatter: () => grade },
                            },
                        },
                    },
                    stroke: { lineCap: 'round' },
                }).render();
            } catch (e) { console.error(e); }

            try {
                new ApexCharts(document.querySelector('#compositionChart'), {
                    chart: { type: 'donut', height: 230, toolbar: { show: false } },
                    series: [
                        {{ (float) ($kpiScore->attendance_score ?? 0) }},
                        {{ (float) ($kpiScore->task_completion_score ?? 0) }},
                        {{ (float) ($kpiScore->deadline_met_score ?? 0) }},
                        {{ (float) ($kpiScore->project_participation_score ?? 0) }},
                        {{ (float) ($kpiScore->regularization_score ?? 0) }},
                    ],
                    labels: ['Attendance', 'Task Completion', 'On-Time', 'Project Participation', 'Regularization'],
                    colors: ['#0D6EFD', '#0D6EFD', '#059669', '#d97706', '#7c3aed'],
                    legend: { position: 'bottom', fontSize: '10.5px' },
                    dataLabels: { style: { fontSize: '10px' } },
                    plotOptions: { pie: { donut: { size: '62%' } } },
                }).render();
            } catch (e) { console.error(e); }

            // ---- Trend chart: 3 pre-loaded datasets, switched client-side ----
            const daily = @json($dailyPerformance ?? []);
            const weekly = @json($weeklyPerformance ?? []);
            const monthly = @json(($history ?? collect())->reverse()->values());

            const datasets = {
                daily: {
                    categories: daily.map(d => new Date(d.performance_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })),
                    raw: daily.map(d => d.performance_date),
                    series: [{ name: 'Daily Score', data: daily.map(d => d.overall_daily_score ?? null) }],
                },
                weekly: {
                    categories: weekly.map(w => w.label),
                    raw: [],
                    series: [{ name: 'Weekly Score', data: weekly.map(w => w.overall_score ?? null) }],
                },
                monthly: {
                    categories: monthly.map(m => m.reporting_month ? new Date(m.reporting_month).toLocaleDateString('en-GB', { month: 'short', year: '2-digit' }) : 'N/A'),
                    raw: [],
                    series: [{ name: 'Monthly Score', data: monthly.map(m => m.overall_score ?? null) }],
                },
            };

            let trendChart = null;
            function renderTrend(period) {
                const d = datasets[period];
                const opts = {
                    chart: { type: 'line', height: 270, toolbar: { show: false }, zoom: { enabled: false },
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                if (period === 'daily') {
                                    const date = d.raw[config.dataPointIndex];
                                    if (date) openDayDetail(date);
                                }
                            },
                        },
                    },
                    series: d.series,
                    xaxis: { categories: d.categories, labels: { style: { fontSize: '10px' } } },
                    yaxis: { min: 0, max: 100, labels: { formatter: v => v + '%' } },
                    colors: ['#0D6EFD'],
                    stroke: { curve: 'smooth', width: 2 },
                    markers: { size: period === 'daily' ? 3 : 4 },
                    grid: { borderColor: '#eef1f7', strokeDashArray: 4 },
                    tooltip: { y: { formatter: v => (v === null ? 'No data' : v + '%') } },
                };
                if (trendChart) { trendChart.updateOptions(opts, true, true); return; }
                trendChart = new ApexCharts(document.querySelector('#trendChart'), opts);
                trendChart.render();
            }
            try { renderTrend('daily'); } catch (e) { console.error(e); }

            $('#trendToggle button').on('click', function () {
                $('#trendToggle button').removeClass('active');
                $(this).addClass('active');
                renderTrend($(this).data('period'));
            });

            function openDayDetail(date) {
                $('#dayDetailBody').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>');
                const off = new bootstrap.Offcanvas(document.getElementById('dayDetailDrawer'));
                off.show();
                $.get('{{ route("performance.daily-detail", Auth::id()) }}', { date: date }, function (res) {
                    if (!res.success) { $('#dayDetailBody').html('<p class="text-muted">No data for this day.</p>'); return; }
                    const r = res.data;
                    const row = (label, val) => `<div class="perf-info-row"><span>${label}</span><strong>${val ?? 'N/A'}</strong></div>`;
                    $('#dayDetailBody').html(
                        `<h6 class="mb-3">${new Date(r.performance_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' })}</h6>` +
                        row('Day Type', r.day_type) +
                        row('Attendance Status', r.attendance_status) +
                        row('Attendance Score', r.attendance_score !== null ? r.attendance_score + '%' : null) +
                        row('Task Completion Score', r.task_completion_score !== null ? r.task_completion_score + '%' : null) +
                        row('On-Time Score', r.task_ontime_score !== null ? r.task_ontime_score + '%' : null) +
                        row('Project Participation Score', r.project_participation_score !== null ? r.project_participation_score + '%' : null) +
                        row('Regularization Score', r.regularization_score !== null ? r.regularization_score + '%' : null) +
                        row('Overall Daily Score', r.overall_daily_score !== null ? r.overall_daily_score + '%' : null)
                    );
                }).fail(function () {
                    $('#dayDetailBody').html('<p class="text-danger">Could not load this day.</p>');
                });
            }
        });
    </script>
@endsection
