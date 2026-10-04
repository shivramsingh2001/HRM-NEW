@extends('client.layout.master')

@section('style')
<style>
    /* ==================== ALL-BLUE THEME ==================== */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .6rem; margin-bottom: .8rem; }
    .stats-card {
        background: white; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px;
        display: flex; align-items: center; box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
    }
    .stats-icon { width: 30px; height: 30px; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex: none; }
    .stats-icon i { font-size: 13px; color: var(--icon-color, #0D6EFD); }
    .stats-info h3 { font-size: 15px; font-weight: 700; margin: 0 0 1px 0; color: #1a2236; line-height: 1.2; }
    .stats-info p { font-size: 9.5px; color: #6b7385; margin: 0; }

    .tp-filter-bar { background: #fff; border: 1px solid #eef2f6; border-radius: 10px; padding: 10px 12px; margin-bottom: .8rem; }
    .tp-filter-bar .form-control, .tp-filter-bar select {
        font-size: 10.5px; padding: 4px 8px; height: auto; border-radius: 7px; border: 1px solid #dfe5f0;
    }
    .tp-filter-bar .form-control:focus, .tp-filter-bar select:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253,.12); }
    .tp-filter-bar .btn-apply { background: #0D6EFD; border-color: #0D6EFD; color: #fff; font-size: 10.5px; padding: 4px 14px; border-radius: 7px; }
    .tp-filter-bar .btn-apply:hover { background: #0B5ED7; border-color: #0B5ED7; }

    .section-title { font-size: 12.5px; font-weight: 700; color: #1a2236; margin: 1rem 0 .5rem; }

    #workloadTable, #projectsTable { font-size: 10.5px; }
    #workloadTable th, #projectsTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #6b7385; background: #f7faff; padding: 6px 8px; border-bottom: 1px solid #EFF6FF; }
    #workloadTable td, #projectsTable td { padding: 6px 8px; vertical-align: middle; }
    #workloadTable tr:hover td, #projectsTable tr:hover td { background: #fafcff; }
    .count-pill { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    .pill-completed { background: #0D6EFD; color: #fff; }
    .pill-pending { background: #93c5fd; color: #0D6EFD; }
    .pill-progress { background: #bfd3f7; color: #0D6EFD; }
    .pill-overdue { background: #e2e8f0; color: #475569; }
    .completion-rate { font-weight: 700; color: #0D6EFD; }
    .progress-track { background: #EFF6FF; border-radius: 999px; height: 6px; width: 100px; overflow: hidden; }
    .progress-fill { background: #0D6EFD; height: 100%; border-radius: 999px; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Task & Project Report" current="Task & Project" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]" />

    <div class="content-area-body" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'task'])
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-list"></i></div>
                <div class="stats-info">
                    <h3>{{ $totalTasks }}</h3>
                    <p>Total Tasks</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-check-circle"></i></div>
                <div class="stats-info">
                    <h3>{{ $completionRate }}%</h3>
                    <p>Completion Rate</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-alert-triangle"></i></div>
                <div class="stats-info">
                    <h3>{{ $overdueTasks }}</h3>
                    <p>Overdue</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-clock"></i></div>
                <div class="stats-info">
                    <h3>{{ $pendingTasks }}</h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-loader"></i></div>
                <div class="stats-info">
                    <h3>{{ $inProgressTasks }}</h3>
                    <p>In Progress</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="feather-x-circle"></i></div>
                <div class="stats-info">
                    <h3>{{ $rejectedTasks }}</h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <x-ui.filter-card title="Filter Report">
<form method="GET" class="tp-filter-bar row g-2 align-items-end">
            <div class="col-md-3">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Month</label>
                <input type="month" name="month" class="form-control" onchange="this.form.submit()" value="{{ $month }}">
            </div>
            <div class="col-md-3">
                <label class="d-block text-muted mb-1" style="font-size:9.5px;">Department</label>
                <select name="department" class="form-control" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $departmentFilter === (string) $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
</x-ui.filter-card>

        <div class="section-title">Per-Employee Workload</div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="workloadTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Completed</th>
                                <th class="text-center">In Progress</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workload as $row)
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar"
                                                style="background:#0D6EFD;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                {{ strtoupper(substr($row->name, 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ $row->name }}
                                                    <small class="text-muted">({{ $row->employee_id ?? 'N/A' }})</small>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $row->department_name ?? '—' }}</td>
                                    <td class="text-center">{{ $row->total }}</td>
                                    <td class="text-center"><span class="count-pill pill-completed">{{ $row->completed }}</span></td>
                                    <td class="text-center"><span class="count-pill pill-progress">{{ $row->in_progress }}</span></td>
                                    <td class="text-center"><span class="count-pill pill-pending">{{ $row->pending }}</span></td>
                                    <td class="text-center"><span class="count-pill pill-overdue">{{ $row->overdue }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No task activity found for this month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="section-title">Active Projects</div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="projectsTable">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                <tr>
                                    <td>{{ $project->name }} <small class="text-muted">({{ $project->project_code }})</small></td>
                                    <td>{{ ucfirst($project->status) }}</td>
                                    <td>{{ $project->deadline_date ? \Carbon\Carbon::parse($project->deadline_date)->format('d M Y') : '—' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width:{{ $project->progress_percentage }}%;"></div>
                                            </div>
                                            <span class="completion-rate">{{ $project->progress_percentage }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No active projects.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
