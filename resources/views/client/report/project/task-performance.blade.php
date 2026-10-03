@extends('client.layout.master')

@section('style')
<style>







    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }

    #reportTable { font-size: 11px; }
    #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; }
    #reportTable td { padding: 6px 10px; vertical-align: middle; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Project Task & Employee Performance Report" current="Task & Employee Performance" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.project.task-performance.export', request()->query()) }}" class="btn btn-sm btn-primary" target="_blank">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body pb-3">
        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.project.task-performance.index') }}" method="GET">
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
                        <select name="employee_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Employees --</option>
                            @foreach ($allEmployees as $e)
                                <option value="{{ $e->id }}" {{ request('employee_id') == $e->id ? 'selected' : '' }}>{{ $e->employee_id }} - {{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="department_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Departments --</option>
                            @foreach ($allDepartments as $d)
                                <option value="{{ $d->id }}" {{ request('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="priority" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Priority --</option>
                            @foreach (['low','medium','high','critical'] as $p)
                                <option value="{{ $p }}" {{ request('priority') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="status" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Status --</option>
                            @foreach (['pending','in_progress','completed','approved','rejected','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @feature('branches')
                    <div class="filter-item">
                        <select name="branch_id" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Branches --</option>
                            @foreach ($allBranches as $b)
                                <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endfeature
                    <div class="filter-item"><input type="date" name="date_from" class="form-control-sm-custom" onchange="this.form.submit()" value="{{ request('date_from') }}" title="Deadline from"></div>
                    <div class="filter-item"><input type="date" name="date_to" class="form-control-sm-custom" onchange="this.form.submit()" value="{{ request('date_to') }}" title="Deadline to"></div>
                    <div class="filter-item fi-search">
                        <input type="text" name="search" class="form-control-sm-custom" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" placeholder="Search employee or ID…" value="{{ request('search') }}">
                    </div>
                    <div class="filter-item"><a href="{{ route('report.project.task-performance.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card mb-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead>
                            <tr>
                                <th>Sr. No.</th><th>Employee</th><th>ID</th>@feature('branches')<th>Branch</th>@endfeature<th>Project</th><th>Total</th><th>Completed</th>
                                <th>In Progress</th><th>Pending</th><th>Overdue</th><th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                @php $rate = $r->total > 0 ? round(($r->completed / $r->total) * 100, 1) : 0; @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $r->employee_name }}</td>
                                    <td>{{ $r->employee_code }}</td>
                                    @feature('branches')<td>{{ $r->branch_name ?? '—' }}</td>@endfeature
                                    <td>{{ $r->project_name ?? '—' }}</td>
                                    <td>{{ $r->total }}</td>
                                    <td>{{ $r->completed }}</td>
                                    <td>{{ $r->in_progress }}</td>
                                    <td>{{ $r->pending }}</td>
                                    <td>{{ $r->overdue }}</td>
                                    <td>{{ $rate }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted py-4">No task assignments match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
