@extends('client.layout.master')

@section('style')
<style>
    .filter-section { background: #fff; border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
    .filter-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; }
    .btn-sm-custom { background: #1e3a8a; color: #fff; border: none; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; }
    .btn-sm-custom:hover { background: #2563eb; color: #fff; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #e3edfe; color: #1e3a8a; }

    #reportTable { font-size: 11px; }
    #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; }
    #reportTable td { padding: 6px 10px; vertical-align: middle; }
    .progress-track { height: 6px; border-radius: 4px; background: #e3edfe; overflow: hidden; width: 90px; }
    .progress-fill { height: 100%; background: #1e3a8a; }
</style>
@endsection

@section('content-area')
    <div class="content-area-header sticky-top">
        <div class="page-header-left d-flex align-items-center gap-2">
            <div class="page-header-title"><h5 class="m-b-10">Project Progress Report</h5></div>
            <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Project Progress</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('report.project.progress.export', request()->query()) }}" class="btn btn-sm btn-primary" target="_blank">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="content-area-body pb-0 h-100">
        <div class="filter-section">
            <form action="{{ route('report.project.progress.index') }}" method="GET">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="project_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Projects --</option>
                            @foreach ($allProjects as $p)
                                <option value="{{ $p->id }}" {{ request('project_id') == $p->id ? 'selected' : '' }}>{{ $p->project_code }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="project_head" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Managers --</option>
                            @foreach ($allManagers as $m)
                                <option value="{{ $m->id }}" {{ request('project_head') == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="status" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Status --</option>
                            @foreach (['ongoing','pending','hold','completed','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="branch_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Branches --</option>
                            @foreach ($allBranches as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item"><input type="date" name="date_from" class="form-control-sm-custom" value="{{ request('date_from') }}" title="Deadline from"></div>
                    <div class="filter-item"><input type="date" name="date_to" class="form-control-sm-custom" value="{{ request('date_to') }}" title="Deadline to"></div>
                    <div class="filter-item">
                        <input type="text" name="search" class="form-control-sm-custom" placeholder="Search project or code…" value="{{ request('search') }}">
                    </div>
                    <div class="filter-item"><button type="submit" class="btn-sm-custom"><i class="feather-eye"></i> View</button></div>
                    <div class="filter-item"><a href="{{ route('report.project.progress.index') }}" class="btn-sm-custom-outline"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead>
                            <tr>
                                <th>Code</th><th>Name</th><th>Branch</th><th>Progress</th><th>Completed</th><th>In Progress</th>
                                <th>Pending</th><th>Overdue Tasks</th><th>Days</th><th>Latest Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $p)
                                <tr>
                                    <td>{{ $p->project_code }}</td>
                                    <td>{{ $p->name }}</td>
                                    <td>{{ $p->branch_name ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <div class="progress-track"><div class="progress-fill" style="width: {{ $p->progress_percentage }}%"></div></div>
                                            <small>{{ $p->progress_percentage }}%</small>
                                        </div>
                                    </td>
                                    <td>{{ $p->tasks_completed }}</td>
                                    <td>{{ $p->tasks_in_progress }}</td>
                                    <td>{{ $p->tasks_pending }}</td>
                                    <td>{{ $p->tasks_overdue }}</td>
                                    <td>{{ $p->days_label }}</td>
                                    <td>{{ $p->latest_update_summary }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted py-4">No projects match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
