{{-- resources/views/client/performance/team-report.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #eef2f6;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card.attendance::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.task::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-card.deadline::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.regularization::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
        .stat-card.overtime::before { background: linear-gradient(90deg, #ef4444, #f87171); }
        .stat-card.overall::before { background: linear-gradient(90deg, #4f46e5, #818cf8); }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-section {
            background: white;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            padding: 20px;
            margin-bottom: 24px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eef2f6;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .filter-select {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            background: #fafcff;
        }

        .btn-reset {
            height: 42px;
            padding: 0 20px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-reset:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .table-wrapper {
            background: white;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            overflow: hidden;
        }

        .table-header {
            padding: 16px 20px;
            background: #fafcff;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-info {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .performance-table {
            width: 100%;
            margin-bottom: 0;
        }

        .performance-table th {
            background: #f8fafc;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 14px 12px;
            border-bottom: 1px solid #eef2f6;
        }

        .performance-table td {
            padding: 14px 12px;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .performance-table tbody tr:hover {
            background: #fafcff;
        }

        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }

        .rank-1 { background: #fef3c7; color: #d97706; }
        .rank-2 { background: #e0e7ff; color: #4f46e5; }
        .rank-3 { background: #d1fae5; color: #059669; }

        .employee-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar-sm {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 13px;
        }

        .score-high { color: #10b981; font-weight: 600; }
        .score-medium { color: #f59e0b; font-weight: 600; }
        .score-low { color: #ef4444; font-weight: 600; }

        .grade-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .grade-Aplus, .grade-A { background: #d1fae5; color: #065f46; }
        .grade-Bplus, .grade-B { background: #dbeafe; color: #1e40af; }
        .grade-C { background: #fef3c7; color: #92400e; }
        .grade-D { background: #ffe4e2; color: #991b1b; }
        .grade-F { background: #fee2e2; color: #991b1b; }

        .btn-view {
            padding: 6px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            color: #4f46e5;
            text-decoration: none;
        }

        .btn-view:hover {
            background: #eef2ff;
            border-color: #4f46e5;
        }

        .employee-initials-sm {
            width: 32px;
            height: 32px;
            background: #e0e7ff;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            color: #4f46e5;
        }

        .dropdown-item.active {
            background-color: #eef2ff;
            color: #4f46e5;
        }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-row { flex-direction: column; }
            .table-wrapper { overflow-x: auto; }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Team Performance Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Team Performance</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="btn-group">
                @if ($prevMonth)
                    <a href="{{ route('performance.team', array_merge(request()->query(), ['month' => $prevMonth])) }}"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="feather-chevron-left"></i> Prev
                    </a>
                @endif
                <button class="btn btn-sm btn-light disabled">{{ date('F Y', strtotime($month . '-01')) }}</button>
                @if ($nextMonth)
                    <a href="{{ route('performance.team', array_merge(request()->query(), ['month' => $nextMonth])) }}"
                        class="btn btn-sm btn-outline-secondary">
                        Next <i class="feather-chevron-right"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card attendance">
                <div class="stat-value">{{ $totals['avg_attendance'] ?? 0 }}%</div>
                <div class="stat-label">Avg Attendance</div>
            </div>
            <div class="stat-card task">
                <div class="stat-value">{{ $totals['avg_task'] ?? 0 }}%</div>
                <div class="stat-label">Task Completion</div>
            </div>
            <div class="stat-card deadline">
                <div class="stat-value">{{ $totals['avg_deadline'] ?? 0 }}%</div>
                <div class="stat-label">Deadline Met</div>
            </div>
            <div class="stat-card regularization">
                <div class="stat-value">{{ $totals['avg_regularization'] ?? 100 }}%</div>
                <div class="stat-label">Regularization</div>
            </div>
            <div class="stat-card overtime">
                <div class="stat-value">{{ $totals['total_overtime'] ?? 0 }}<span style="font-size: 14px;">h</span></div>
                <div class="stat-label">Total Overtime</div>
            </div>
            <div class="stat-card overall">
                <div class="stat-value">{{ $totals['avg_overall'] ?? 0 }}%</div>
                <div class="stat-label">Team Overall</div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="feather-filter"></i> Filter Team Members
            </div>
            <form method="GET" action="{{ route('performance.team') }}" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label><i class="feather-calendar"></i> Month</label>
                        <select name="month" class="filter-select" id="monthSelect">
                            @foreach ($availableMonths ?? [] as $availableMonth)
                                <option value="{{ $availableMonth }}"
                                    {{ ($month ?? '') == $availableMonth ? 'selected' : '' }}>
                                    {{ date('F Y', strtotime($availableMonth . '-01')) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="feather-grid"></i> Department</label>
                        <select name="department_id" class="filter-select" id="deptSelect">
                            <option value="">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}"
                                    {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="feather-users"></i> Employee</label>
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('employee_id') && ($selectedEmployee = $allEmployees->firstWhere('id', request('employee_id'))))
                                        <span class="employee-initials-sm">{{ strtoupper(substr($selectedEmployee->name, 0, 2)) }}</span>
                                        <span class="employee-name">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>
                            <ul class="dropdown-menu w-100 p-2" aria-labelledby="employeeDropdownBtn"
                                style="max-height: 300px; overflow-y: auto;">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('employee_id') ? 'active' : '' }}"
                                        href="{{ route('performance.team', array_merge(request()->except(['employee_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($allEmployees as $user)
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('performance.team', array_merge(request()->except(['page']), ['employee_id' => $user->id])) }}">
                                            <span class="employee-initials-sm">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $user->name }} <small class="text-muted">({{ $user->employee_id ?? 'N/A' }})</small></span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="{{ route('performance.team') }}" class="btn-reset">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Team Members Table -->
        <div class="table-wrapper">
            <div class="table-header">
                <div class="table-title">
                    <i class="feather-users"></i> Team Members Performance
                </div>
                <div class="badge-info">
                    <i class="feather-calendar"></i> {{ date('F Y', strtotime($month . '-01')) }} |
                    <i class="feather-users"></i> {{ count($teamData) }} members
                </div>
            </div>
            <div class="table-responsive">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Attendance</th>
                            <th>Task Completion</th>
                            <th>Deadline Met</th>
                            <th>Regularization</th>
                            <th>Manager Rating</th>
                            <th>Overall</th>
                            <th>Grade</th>
                            <th>Overtime</th>
                            <!--<th>Late Days</th>-->
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teamData as $member)
                            <tr>
                                <td>
                                    @if($member->rank == 1)
                                        <span class="rank-badge rank-1">🥇 1</span>
                                    @elseif($member->rank == 2)
                                        <span class="rank-badge rank-2">🥈 2</span>
                                    @elseif($member->rank == 3)
                                        <span class="rank-badge rank-3">🥉 3</span>
                                    @else
                                        <span class="rank-badge">{{ $member->rank }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="employee-cell">
                                        <div class="employee-avatar-sm">
                                            {{ strtoupper(substr($member->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold ">{{ $member->user->name }} <small class="text-muted">( {{ $member->user->employee_id ?? 'N/A' }} )</small></div>
                                            <small class="text-muted">{{ $member->user->email ?? 'N/A' }}</small>
                                            <!--<br><small class="text-muted">{{ $member->user->jobDetails?->Designation?->name ?? '' }}</small>-->
                                        </div>
                                    </div>
                                </td>
                                <td class="{{ ($member->attendance_score ?? 0) >= 85 ? 'score-high' : (($member->attendance_score ?? 0) >= 60 ? 'score-medium' : 'score-low') }}">
                                    {{ $member->attendance_score ?? 0 }}%
                                    @if(($member->late_days ?? 0) > 0)
                                        <br><small class="text-warning">({{ $member->late_days }} late)</small>
                                    @endif
                                </td>
                                <td class="{{ ($member->task_score ?? 0) >= 85 ? 'score-high' : (($member->task_score ?? 0) >= 60 ? 'score-medium' : 'score-low') }}">
                                    {{ $member->task_score ?? 0 }}%
                                    <br><small class="text-muted">{{ $member->completed_tasks ?? 0 }}/{{ $member->assigned_tasks ?? 0 }} tasks</small>
                                </td>
                                <td class="{{ ($member->deadline_score ?? 0) >= 85 ? 'score-high' : (($member->deadline_score ?? 0) >= 60 ? 'score-medium' : 'score-low') }}">
                                    {{ $member->deadline_score ?? 0 }}%
                                </td>
                                <td class="{{ ($member->regularization_score ?? 100) >= 85 ? 'score-high' : (($member->regularization_score ?? 100) >= 60 ? 'score-medium' : 'score-low') }}">
                                    {{ $member->regularization_score ?? 100 }}%
                                    @if(($member->regularization_count ?? 0) > 0)
                                        <br><small class="text-muted">({{ $member->regularization_count }} req)</small>
                                    @endif
                                </td>
                              
                                <td>
                                    @if(($member->manager_rating_score ?? 0) > 0)
                                        <span class="fw-semibold">{{ $member->manager_rating_score }}%</span>
                                        <br><small class="text-muted">{{ $member->manager_rating_raw ?? 'N/A' }}/5</small>
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </td>
                                <td class="fw-bold">{{ $member->overall_score ?? 0 }}%</td>
                                <td>
                                    <span class="grade-badge grade-{{ str_replace('+', 'plus', $member->grade ?? 'C') }}">
                                        {{ $member->grade ?? 'C' }}
                                    </span>
                                </td>
                                <td>{{ $member->overtime_hours ?? 0 }} hrs</td>
                                <!--<td>-->
                                <!--    @if(($member->late_days ?? 0) > 0)-->
                                <!--        <span class="text-warning">{{ $member->late_days }} days</span>-->
                                <!--    @else-->
                                <!--        <span class="text-success">0</span>-->
                                <!--    @endif-->
                                <!--</td>-->
                                <td>
                                    <a href="{{ route('performance.individual', $member->user->id) }}" class="btn-view">
                                        <i class="feather-eye"></i> 
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5">
                                    <i class="feather-users fs-48 text-muted d-block mb-3"></i>
                                    <h6 class="text-muted">No team members found</h6>
                                    <p class="text-muted small">Try adjusting your filters</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrapper p-3 border-top">
                <div class="text-muted small">
                    Showing {{ count($teamData) }} of {{ $totals['team_size'] ?? 0 }} members
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-4 flex-wrap align-items-center">
                            <span class="score-high">●</span> Excellent (85%+)
                            <span class="score-medium">●</span> Good (60-84%)
                            <span class="score-low">●</span> Needs Improvement (&lt;60%)
                            <span class="mx-2">|</span>
                            <span class="grade-badge grade-Aplus">A+</span>
                            <span class="grade-badge grade-A">A</span>
                            <span class="grade-badge grade-Bplus">B+</span>
                            <span class="grade-badge grade-B">B</span>
                            <span class="grade-badge grade-C">C</span>
                            <span class="grade-badge grade-D">D</span>
                            <span class="grade-badge grade-F">F</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            $('#monthSelect, #deptSelect').on('change', function() {
                $('#filterForm').submit();
            });
        });
    </script>
@endsection