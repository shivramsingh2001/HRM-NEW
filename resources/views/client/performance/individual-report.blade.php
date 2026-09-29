{{-- resources/views/client/performance/individual-report.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* Compact report: small padding / font / margins, scoped to this page. */
        .perf-page { padding: 12px !important; font-size: 12px; }
        .perf-page .card { margin-bottom: 0; border-radius: 10px; }
        .perf-page .card-header { padding: 8px 12px; min-height: 0; }
        .perf-page .card-header .card-title { font-size: 12.5px; font-weight: 700; margin: 0; }
        .perf-page .card-body { padding: 10px 12px; }
        .perf-page .row { --bs-gutter-x: 10px; --bs-gutter-y: 10px; }
        .perf-page .mb-block { margin-bottom: 10px; }

        .perf-hero {
            background: #1e3a8a;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            border-radius: 12px; padding: 12px 16px; color: #fff;
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        }
        .perf-hero .employee-info { color: #fff; display: flex; align-items: center; gap: 10px; }
        .perf-hero .employee-initials { background: rgba(255,255,255,0.22); width: 38px; height: 38px; font-size: 14px; }
        .perf-hero h5 { font-size: 14px; margin: 0 0 2px; color: #fff; font-weight: 700; }
        .perf-hero h5 small { font-size: 11px; font-weight: 500; opacity: .8; }
        .perf-hero p { font-size: 11px; margin: 0; opacity: .9; line-height: 1.5; }
        .perf-hero-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 10.5px; opacity: .85; margin-top: 2px; }
        .perf-hero-meta i { font-size: 10px; margin-right: 3px; }
        .perf-hero-score { display: flex; align-items: center; gap: 12px; }
        #overallGauge { width: 78px; height: 78px; }
        .perf-hero-score .score-text { text-align: right; }
        .perf-hero-score .score-text .val { font-size: 22px; font-weight: 800; line-height: 1; }
        .perf-hero-score .score-text .diff { font-size: 10.5px; opacity: .9; margin-top: 3px; }
        .hero-chips { display: flex; gap: 4px; justify-content: flex-end; margin-top: 5px; flex-wrap: wrap; }
        .hero-chip { background: rgba(255,255,255,.18); border-radius: 999px; padding: 2px 8px; font-size: 10px; font-weight: 600; white-space: nowrap; }

        .perf-stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
        @media (max-width: 1200px) { .perf-stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 640px) { .perf-stats-grid { grid-template-columns: repeat(2, 1fr); } }

        .compare-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        @media (max-width: 768px) { .compare-strip { grid-template-columns: repeat(2, 1fr); } }
        .compare-item { padding: 7px 10px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--border, #e2e8f0); }
        .compare-item .val { font-size: 15px; font-weight: 700; line-height: 1.2; }
        .compare-item .lbl { font-size: 9.5px; color: #64748b; text-transform: uppercase; letter-spacing: .03em; font-weight: 600; }
        .compare-item .delta { font-size: 10px; font-weight: 600; margin-left: 4px; }
        .delta.up { color: #059669; } .delta.down { color: #dc2626; } .delta.flat { color: #64748b; }

        .perf-section-title { font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .perf-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; gap: 8px; }
        .perf-page .period-toggle button { font-size: 10.5px; padding: 3px 9px; }

        /* Trend + Composition share one row height. */
        .equal-card { height: 100%; display: flex; flex-direction: column; }
        .equal-card > .card-body { flex: 1; display: flex; flex-direction: column; }
        .equal-card .chart-fill { flex: 1; min-height: 200px; }

        .trend-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 6px; }
        .trend-summary div { background: #f8fafc; border-radius: 6px; padding: 5px 8px; }
        .trend-summary b { display: block; font-size: 12.5px; }
        .trend-summary span { font-size: 9.5px; color: #64748b; text-transform: uppercase; font-weight: 600; }

        .comp-table { width: 100%; font-size: 11px; margin-top: 4px; }
        .comp-table td { padding: 3px 0; border-bottom: 1px solid #f1f5f9; }
        .comp-table tr:last-child td { border-bottom: none; }
        .comp-table .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 5px; }
        .comp-table .w { color: #94a3b8; font-size: 10px; }

        .perf-info-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid var(--border, #f1f5f9); font-size: 11.5px; }
        .perf-info-row:last-child { border-bottom: none; }
        .perf-info-row span { color: #64748b; }
        .perf-info-row strong { color: var(--text-primary); font-weight: 600; }

        .perf-page table.compact { font-size: 11.5px; margin: 0; }
        .perf-page table.compact th { font-size: 9.5px; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 6px 10px; font-weight: 700; }
        .perf-page table.compact td { padding: 5px 10px; vertical-align: middle; }


        .review-block { margin-bottom: 6px; }
        .review-block h6 { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin: 0 0 1px; }
        .review-block p { font-size: 11.5px; margin: 0; white-space: pre-line; }
        .feedback-box { background: var(--primary-light); border-left: 3px solid var(--primary); padding: 8px 10px; border-radius: 6px; font-size: 11.5px; }
    </style>
@endsection

@section('content-area')
    @php
        $delta = function ($a, $b) {
            if ($a === null || $b === null) return null;
            return round((float) $a - (float) $b, 2);
        };
        $deltaHtml = function ($d) {
            if ($d === null) return '';
            $cls = $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat');
            return '<span class="delta ' . $cls . '">' . ($d > 0 ? '+' : '') . $d . '</span>';
        };
        $scored = collect($dailyPerformance ?? [])->filter(fn ($d) => $d['overall_daily_score'] !== null);
        $best = $scored->sortByDesc('overall_daily_score')->first();
        $worst = $scored->sortBy('overall_daily_score')->first();
        $fmtDay = fn ($d) => $d ? \Carbon\Carbon::parse($d['performance_date'])->format('d M') : '';
        $components = [
            ['Attendance', $kpiScore->attendance_score, $weights['attendance'] ?? null, '#1e3a8a'],
            ['Task Completion', $kpiScore->task_completion_score, $weights['task_completion'] ?? null, '#2563eb'],
            ['On-Time', $kpiScore->deadline_met_score, $weights['task_ontime'] ?? null, '#059669'],
            ['Project Participation', $kpiScore->project_participation_score, $weights['project_participation'] ?? null, '#d97706'],
            ['Regularization', $kpiScore->regularization_score, $weights['regularization'] ?? null, '#7c3aed'],
            ['Manager Rating', $kpiScore->manager_rating_score, $weights['manager_rating'] ?? null, '#db2777'],
        ];
    @endphp

    <x-ui.page-header title="Employee Performance Report" :parent="['label' => 'Team Report', 'route' => 'performance.team']">
        <x-slot:actions>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('performance.individual', ['userId' => $employee->id, 'month' => $prevMonth]) }}" class="btn btn-outline-secondary">
                    <i class="feather-chevron-left"></i>
                </a>
                <button class="btn btn-light disabled">{{ date('F Y', strtotime($month . '-01')) }}</button>
                @if($nextMonth)
                    <a href="{{ route('performance.individual', ['userId' => $employee->id, 'month' => $nextMonth]) }}" class="btn btn-outline-secondary">
                        <i class="feather-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary disabled" disabled><i class="feather-chevron-right"></i></button>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content perf-page">

        {{-- Hero: who, where they sit, and the headline score --}}
        <div class="perf-hero mb-block">
            <div class="employee-info">
                <span class="employee-initials">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                <div>
                    <h5>{{ $employee->name }} @if($profile['code'])<small>&middot; {{ $profile['code'] }}</small>@endif</h5>
                    <p>{{ collect([$profile['designation'], $profile['department'] ?? ($employee->role ? ucfirst($employee->role) : null)])->filter()->implode(' · ') }} &middot; {{ date('F Y', strtotime($month . '-01')) }}</p>
                    <div class="perf-hero-meta">
                        @if($profile['reporting_heads'])<span><i class="feather-user"></i>Reports to {{ $profile['reporting_heads'] }}</span>@endif
                        @if($profile['joining_date'])<span><i class="feather-calendar"></i>Joined {{ $profile['joining_date'] }}</span>@endif
                        @if($profile['email'])<span><i class="feather-mail"></i>{{ $profile['email'] }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="perf-hero-score">
                <div id="overallGauge"></div>
                <div class="score-text">
                    <div class="val">{{ $overallScore ?? '—' }}@if($overallScore !== null)%@endif</div>
                    @if($prevOverallScore !== null && $overallScore !== null)
                        <div class="diff"><i class="feather-arrow-{{ $scoreTrend === 'down' ? 'down' : 'up' }}"></i> {{ abs($scoreDifference) }}% vs last month ({{ $prevOverallScore }}%)</div>
                    @else
                        <div class="diff">No score last month</div>
                    @endif
                    <div class="hero-chips">
                        @if($departmentRank)<span class="hero-chip">Dept rank {{ $departmentRank['rank'] }}/{{ $departmentRank['of'] }}</span>@endif
                        @if($companyRank)<span class="hero-chip">Company rank {{ $companyRank['rank'] }}/{{ $companyRank['of'] }}</span>@endif
                    </div>
                </div>
            </div>
        </div>

        <div class="perf-stats-grid mb-block">
            <x-ui.stat-card icon="calendar" label="Attendance" :value="($kpiScore->attendance_score ?? null) !== null ? $kpiScore->attendance_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="check-square" label="Task Completion" :value="($kpiScore->task_completion_score ?? null) !== null ? $kpiScore->task_completion_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="clock" label="On-Time Completion" :value="($kpiScore->deadline_met_score ?? null) !== null ? $kpiScore->deadline_met_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="briefcase" label="Project Participation" :value="($kpiScore->project_participation_score ?? null) !== null ? $kpiScore->project_participation_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="file-text" label="Regularization" :value="($kpiScore->regularization_score ?? null) !== null ? $kpiScore->regularization_score.'%' : 'N/A'" />
            <x-ui.stat-card icon="star" label="Manager Rating" :value="$kpiScore->manager_rating_score ?? 'N/A'" />
        </div>

        <div class="compare-strip mb-block">
            <div class="compare-item">
                <div class="val">{{ $overallScore ?? 'N/A' }}@if($overallScore !== null)%@endif <span class="grade-badge" data-grade="{{ $grade }}">{{ $grade ?? '—' }}</span></div>
                <div class="lbl">{{ $employee->name }}</div>
            </div>
            <div class="compare-item">
                <div class="val">{{ $departmentAvg ?? 'N/A' }}@if($departmentAvg !== null)%@endif {!! $deltaHtml($delta($overallScore, $departmentAvg)) !!}</div>
                <div class="lbl">Department Average</div>
            </div>
            <div class="compare-item">
                <div class="val">{{ $companyAvg ?? 'N/A' }}@if($companyAvg !== null)%@endif {!! $deltaHtml($delta($overallScore, $companyAvg)) !!}</div>
                <div class="lbl">Company Average</div>
            </div>
            <div class="compare-item">
                <div class="val">{{ $kpiScore->days_calculated ?? 0 }} / {{ $kpiScore->days_expected ?? 0 }}</div>
                <div class="lbl">Days scored / expected</div>
            </div>
        </div>

        {{-- Trend + composition: same height --}}
        <div class="row mb-block align-items-stretch">
            <div class="col-lg-8">
                <x-ui.card class="equal-card">
                    <div class="perf-card-head">
                        <h6 class="perf-section-title">Performance Trend</h6>
                        <div class="period-toggle" id="trendToggle">
                            <button type="button" class="active" data-period="daily">Daily</button>
                            <button type="button" data-period="weekly">Weekly</button>
                            <button type="button" data-period="monthly">Monthly</button>
                        </div>
                    </div>
                    <div id="trendChart" class="chart-fill"></div>
                    <div class="trend-summary">
                        <div><b>{{ $scored->isNotEmpty() ? round($scored->avg('overall_daily_score'), 1) . '%' : '—' }}</b><span>Avg daily</span></div>
                        <div><b>{{ $best ? $best['overall_daily_score'] . '%' : '—' }}</b><span>Best {{ $fmtDay($best) }}</span></div>
                        <div><b>{{ $worst ? $worst['overall_daily_score'] . '%' : '—' }}</b><span>Lowest {{ $fmtDay($worst) }}</span></div>
                        <div><b>{{ $scored->count() }}</b><span>Days with score</span></div>
                    </div>
                </x-ui.card>
            </div>
            <div class="col-lg-4">
                <x-ui.card class="equal-card">
                    <div class="perf-card-head">
                        <h6 class="perf-section-title">Score Composition</h6>
                        <span class="text-muted" style="font-size:10px">score &middot; weight</span>
                    </div>
                    <div id="compositionChart" style="height: 150px;"></div>
                    <table class="comp-table">
                        @foreach($components as [$label, $score, $weight, $color])
                            <tr>
                                <td><span class="dot" style="background: {{ $color }}"></span>{{ $label }}</td>
                                <td class="text-end"><strong>{{ $score !== null ? $score . ($label === 'Manager Rating' ? '' : '%') : 'N/A' }}</strong></td>
                                <td class="text-end w">{{ $weight !== null ? rtrim(rtrim(number_format((float) $weight, 2), '0'), '.') . '%' : '' }}</td>
                            </tr>
                        @endforeach
                    </table>
                </x-ui.card>
            </div>
        </div>

        <div class="row mb-block">
            <div class="col-lg-4 col-md-6">
                <x-ui.card title="Attendance Breakdown" class="h-100">
                    <div class="perf-info-row"><span>Present Days</span><strong class="text-success">{{ $kpiScore->present_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Absent Days</span><strong class="text-danger">{{ $kpiScore->absent_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Half Days</span><strong>{{ $kpiScore->half_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Paid / Unpaid Leaves</span><strong>{{ $kpiScore->paid_leaves ?? 0 }} / {{ $kpiScore->unpaid_leaves ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Holidays / Week-offs</span><strong>{{ $kpiScore->holidays ?? 0 }} / {{ $kpiScore->weekoffs ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Working days in period</span><strong>{{ $kpiScore->working_days_in_period ?? 0 }}</strong></div>
                </x-ui.card>
            </div>
            <div class="col-lg-4 col-md-6">
                <x-ui.card title="Punctuality &amp; Regularization" class="h-100">
                    <div class="perf-info-row"><span>Late Days</span><strong class="text-warning">{{ $kpiScore->late_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Total Late Time</span><strong>{{ intdiv((int) ($kpiScore->total_late_minutes ?? 0), 60) }}h {{ (int) ($kpiScore->total_late_minutes ?? 0) % 60 }}m</strong></div>
                    <div class="perf-info-row"><span>Early Departure Days</span><strong>{{ $kpiScore->early_departure_days ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Overtime Hours</span><strong>{{ (float) ($kpiScore->overtime_hours ?? 0) }} h</strong></div>
                    <div class="perf-info-row"><span>Regularizations</span><strong>{{ $kpiScore->regularization_count ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Approved / Rejected / Pending</span><strong><span class="text-success">{{ $kpiScore->approved_regularization_count ?? 0 }}</span> / <span class="text-danger">{{ $kpiScore->rejected_regularization_count ?? 0 }}</span> / {{ $kpiScore->pending_regularization_count ?? 0 }}</strong></div>
                </x-ui.card>
            </div>
            <div class="col-lg-4 col-md-12">
                <x-ui.card title="Task &amp; Project Performance" class="h-100">
                    <div class="perf-info-row"><span>Assigned / Completed</span><strong>{{ $kpiScore->assigned_tasks ?? 0 }} / <span class="text-success">{{ $kpiScore->completed_tasks ?? 0 }}</span></strong></div>
                    <div class="perf-info-row"><span>On-Time Completed</span><strong>{{ $kpiScore->on_time_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Late Completed</span><strong class="text-warning">{{ $kpiScore->late_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Overdue Tasks</span><strong class="text-danger">{{ $kpiScore->overdue_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Project Tasks Assigned / Done</span><strong>{{ $kpiScore->project_assigned_tasks ?? 0 }} / {{ $kpiScore->project_completed_tasks ?? 0 }}</strong></div>
                    <div class="perf-info-row"><span>Project Tasks On Time</span><strong>{{ $kpiScore->project_on_time_tasks ?? 0 }}</strong></div>
                </x-ui.card>
            </div>
        </div>

        <div class="row mb-block">
            <div class="col-lg-7">
                <x-ui.card title="Last 6 Months" :bodyClass="'p-0'" class="h-100">
                    <div class="table-responsive">
                        <table class="table table-hover compact">
                            <thead><tr><th>Month</th><th>Score</th><th>Grade</th><th>Attendance</th><th>Tasks</th><th>On-Time</th><th>Present / Absent</th></tr></thead>
                            <tbody>
                                @php $rows = ($history ?? collect())->values(); @endphp
                                @forelse($rows as $i => $h)
                                    @php $older = $rows->get($i + 1); @endphp
                                    <tr @if(substr((string) $h->reporting_month, 0, 7) === $month) style="background:#eff6ff" @endif>
                                        <td>{{ \Carbon\Carbon::parse($h->reporting_month)->format('M Y') }}</td>
                                        <td><strong>{{ $h->overall_score ?? '—' }}@if($h->overall_score !== null)%@endif</strong> {!! $deltaHtml($older ? $delta($h->overall_score, $older->overall_score) : null) !!}</td>
                                        <td><span class="grade-badge" data-grade="{{ $h->grade }}">{{ $h->grade ?? '—' }}</span></td>
                                        <td>{{ $h->attendance_score !== null ? $h->attendance_score . '%' : '—' }}</td>
                                        <td>{{ $h->task_completion_score !== null ? $h->task_completion_score . '%' : '—' }}</td>
                                        <td>{{ $h->deadline_met_score !== null ? $h->deadline_met_score . '%' : '—' }}</td>
                                        <td>{{ $h->present_days ?? 0 }} / {{ $h->absent_days ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-3">No monthly scores yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </div>
            <div class="col-lg-5">
                <x-ui.card title="Manager Review" class="h-100">
                    @if($managerReview)
                        <div class="d-flex justify-content-between mb-2" style="font-size:11px">
                            <span><strong>{{ $managerReview->overall_rating ?? 'N/A' }}/5</strong> rating</span>
                            <span class="text-muted">
                                {{ optional(\App\Models\User::withoutGlobalScopes()->find($managerReview->reviewer_id))->name }}
                                @if($managerReview->submitted_at) &middot; {{ \Carbon\Carbon::parse($managerReview->submitted_at)->format('d M, Y') }} @endif
                            </span>
                        </div>
                        @foreach(['strengths' => 'Strengths', 'areas_for_improvement' => 'Areas for improvement', 'achievements' => 'Achievements', 'goals_next_month' => 'Goals for next month'] as $field => $label)
                            @if($managerReview->$field)
                                <div class="review-block"><h6>{{ $label }}</h6><p>{{ $managerReview->$field }}</p></div>
                            @endif
                        @endforeach
                        <div class="feedback-box">{{ $managerReview->additional_feedback ?: 'No written feedback provided.' }}</div>
                    @else
                        <x-ui.empty-state icon="message-square" title="No manager review for this month" />
                    @endif
                </x-ui.card>
            </div>
        </div>

        @if($regularizationDetails && $regularizationDetails->isNotEmpty())
            <x-ui.card title="Regularization Requests This Month" :bodyClass="'p-0'">
                <div class="table-responsive">
                    <table class="table table-hover compact">
                        <thead><tr><th>Date</th><th>Type</th><th>Status</th><th>Reason</th></tr></thead>
                        <tbody>
                            @foreach($regularizationDetails as $reg)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($reg->date)->format('d M Y') }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $reg->request_type)) }}</td>
                                    <td><x-ui.status-badge :status="$reg->status" /></td>
                                    <td>{{ $reg->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
            const overall = {{ (float) ($overallScore ?? 0) }};
            const grade = @json($grade ?? 'N/A');
            const gaugeColor = overall >= 80 ? '#059669' : (overall >= 60 ? '#1e3a8a' : (overall >= 40 ? '#d97706' : '#dc2626'));

            try {
                new ApexCharts(document.querySelector('#overallGauge'), {
                    chart: { type: 'radialBar', height: 78, width: 78, sparkline: { enabled: true } },
                    series: [overall],
                    colors: [gaugeColor],
                    plotOptions: {
                        radialBar: {
                            hollow: { size: '55%' },
                            track: { background: 'rgba(255,255,255,0.25)' },
                            dataLabels: { show: true, value: { show: true, fontSize: '13px', fontWeight: 700, color: '#fff', offsetY: 4, formatter: () => grade } },
                        },
                    },
                    stroke: { lineCap: 'round' },
                }).render();
            } catch (e) { console.error(e); }

            try {
                new ApexCharts(document.querySelector('#compositionChart'), {
                    chart: { type: 'donut', height: 150, toolbar: { show: false } },
                    series: [
                        {{ (float) ($kpiScore->attendance_score ?? 0) }},
                        {{ (float) ($kpiScore->task_completion_score ?? 0) }},
                        {{ (float) ($kpiScore->deadline_met_score ?? 0) }},
                        {{ (float) ($kpiScore->project_participation_score ?? 0) }},
                        {{ (float) ($kpiScore->regularization_score ?? 0) }},
                    ],
                    labels: ['Attendance', 'Task Completion', 'On-Time', 'Project Participation', 'Regularization'],
                    colors: ['#1e3a8a', '#2563eb', '#059669', '#d97706', '#7c3aed'],
                    legend: { show: false },
                    dataLabels: { enabled: false },
                    plotOptions: { pie: { donut: { size: '62%' } } },
                }).render();
            } catch (e) { console.error(e); }

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

            // Dashed reference lines: department and company average for this month.
            const refLine = (y, label, color) => (y === null ? null : {
                y: y, borderColor: color, strokeDashArray: 4,
                label: { text: label + ' ' + y + '%', borderColor: color, style: { fontSize: '9px', color: '#fff', background: color } },
            });
            const refLines = [
                refLine(@json($departmentAvg), 'Dept avg', '#d97706'),
                refLine(@json($companyAvg), 'Company avg', '#64748b'),
            ].filter(Boolean);

            let trendChart = null;
            function renderTrend(period) {
                const d = datasets[period];
                const opts = {
                    chart: { type: 'line', height: Math.max(200, document.querySelector('#trendChart').clientHeight), toolbar: { show: false }, zoom: { enabled: false },
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
                    xaxis: { categories: d.categories, labels: { style: { fontSize: '9.5px' } } },
                    annotations: { yaxis: refLines },
                    yaxis: { min: 0, max: 100, tickAmount: 5, labels: { formatter: v => v + '%', style: { fontSize: '9.5px' } } },
                    colors: ['#1e3a8a'],
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
                $.get('{{ route("performance.daily-detail", $employee->id) }}', { date: date }, function (res) {
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
