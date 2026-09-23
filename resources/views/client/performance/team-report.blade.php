{{-- resources/views/client/performance/team-report.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STAT CARDS — same canonical stat-card component (.kpi5-*)
           family as Leave Credit Management; .kpi5-* itself is centralized in
           theme-custom.css, only the grid wrapper is local. ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 16px 20px;
            margin-bottom: 18px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .filter-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .filter-title span {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-item {
            flex: 1;
            min-width: 180px;
        }

        .filter-select {
            width: 100%;
            padding: 8px 32px 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12.5px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;
            appearance: none;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .clear-all-link {
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .table-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            overflow: hidden;
        }

        .rank-cell {
            font-weight: 700;
            font-size: 13px;
        }

        .score-high {
            color: var(--success);
            font-weight: 700;
        }

        .score-medium {
            color: var(--primary);
            font-weight: 700;
        }

        .score-low {
            color: var(--danger);
            font-weight: 700;
        }

        .legend-card {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            padding: 12px 16px;
            font-size: 11.5px;
            color: #64748b;
        }

        .legend-card span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Team Performance Report">
        <x-slot:actions>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('performance.team', array_merge(request()->except('month'), ['month' => $prevMonth])) }}"
                    class="btn btn-outline-secondary">
                    <i class="feather-chevron-left"></i>
                </a>
                <button class="btn btn-light disabled">{{ date('F Y', strtotime($month . '-01')) }}</button>
                @if ($nextMonth)
                    <a href="{{ route('performance.team', array_merge(request()->except('month'), ['month' => $nextMonth])) }}"
                        class="btn btn-outline-secondary">
                        <i class="feather-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary disabled" disabled><i
                            class="feather-chevron-right"></i></button>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 18px !important;">

        <div class="stats-grid">
            <x-ui.stat-card icon="users" label="Team Size" :value="$totals['team_size'] ?? 0" />
            <x-ui.stat-card icon="award" label="Avg Overall" :value="($totals['avg_overall'] ?? null) !== null ? $totals['avg_overall'] . '%' : 'N/A'" />
            <x-ui.stat-card icon="calendar" label="Avg Attendance" :value="($totals['avg_attendance'] ?? null) !== null ? $totals['avg_attendance'] . '%' : 'N/A'" />
            <x-ui.stat-card icon="check-square" label="Avg Task Completion" :value="($totals['avg_task'] ?? null) !== null ? $totals['avg_task'] . '%' : 'N/A'" />
            <x-ui.stat-card icon="briefcase" label="Avg Project Participation" :value="($totals['avg_project_participation'] ?? null) !== null
                ? $totals['avg_project_participation'] . '%'
                : 'N/A'" />
            <x-ui.stat-card icon="file-text" label="Avg Regularization" :value="($totals['avg_regularization'] ?? null) !== null ? $totals['avg_regularization'] . '%' : 'N/A'" />
        </div>

        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Team
                    @php
                        $activeFilterCount = collect(request()->only(['department_id', 'employee_id']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('performance.team', ['month' => $month]) }}" class="clear-all-link">
                        <i class="feather-x"></i> Clear All
                    </a>
                @endif
            </div>
            <form action="{{ route('performance.team') }}" method="GET" id="filterForm">
                <input type="hidden" name="month" value="{{ $month }}">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="department_id" class="filter-select">
                            <option value="">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}"
                                    {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="employee_id" class="filter-select">
                            <option value="">All Employees</option>
                            @foreach ($allEmployees as $emp)
                                <option value="{{ $emp->id }}"
                                    {{ request('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Team Performance</h5>

            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Employee</th>
                                <th>Attendance</th>
                                <th>Task</th>
                                <th>On-Time</th>
                                <th>Project</th>
                                <th>Regularization</th>
                                <th>Manager</th>
                                <th>Overall</th>
                                <th>Grade</th>
                                <th>Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamData as $data)
                                @php
                                    $scoreClass =
                                        $data->overall_score >= 75
                                            ? 'score-high'
                                            : ($data->overall_score >= 50
                                                ? 'score-medium'
                                                : 'score-low');
                                    $medal =
                                        $data->rank === 1
                                            ? '🥇'
                                            : ($data->rank === 2
                                                ? '🥈'
                                                : ($data->rank === 3
                                                    ? '🥉'
                                                    : null));
                                @endphp
                                <tr class="clickable-row"
                                    data-href="{{ route('performance.individual', $data->user->id) }}"
                                    style="cursor:pointer;">
                                    <td class="rank-cell">{{ $medal ?? $data->rank }}</td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($data->user->name ?? 'N/A', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name-text">
                                                    {{ $data->user->name ?? 'N/A' }}
                                                    <small class="employee-id-text">(
                                                        {{ $data->user->employee_id ?? 'N/A' }} )</small>
                                                </div>
                                                <div class="employee-email-text">
                                                    {{ $data->user->email ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $data->attendance_score }}%</td>
                                    <td>{{ $data->task_score }}%</td>
                                    <td>{{ $data->deadline_score }}%</td>
                                    <td>{{ $data->project_participation_score }}%</td>
                                    <td>{{ $data->regularization_score }}%</td>
                                    <td>{{ $data->manager_rating_score ?: 'N/A' }}</td>
                                    <td class="{{ $scoreClass }}">{{ $data->overall_score }}%</td>
                                    <td><span class="grade-badge"
                                            data-grade="{{ $data->grade }}">{{ $data->grade }}</span>
                                    </td>
                                    <td class="text-muted" style="font-size:11px;">
                                        {{ $data->days_calculated ?? 0 }}/{{ $data->days_expected ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11"><x-ui.empty-state icon="bar-chart-2"
                                            title="No performance data for this period" /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (method_exists($teamData, 'links') && $teamData->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $teamData->firstItem() }} to {{ $teamData->lastItem() }}
                            of {{ $teamData->total() }} entries
                        </div>
                        <div class="remove-internal-para">
                            {{ $teamData->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(function() {
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });
            $('.clickable-row').on('click', function() {
                window.location.href = $(this).data('href');
            });
        });
    </script>
@endsection
