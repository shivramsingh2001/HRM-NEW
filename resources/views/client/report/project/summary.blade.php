@extends('client.layout.master')

@section('style')
<style>







    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }

    #reportTable { font-size: 11px; }
    #reportTable th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #6b7385; padding: 6px 10px; }
    #reportTable td { padding: 6px 10px; vertical-align: middle; }
    .rpt-badge { padding: 2px 8px; border-radius: 30px; font-size: 8.5px; font-weight: 700; }
    .badge-active { background: #3b82f6; color: #fff; }
    .badge-inactive { background: #0D6EFD; color: #fff; }
    .progress-track { height: 6px; border-radius: 4px; background: #EFF6FF; overflow: hidden; width: 90px; }
    .progress-fill { height: 100%; background: #0D6EFD; }
</style>
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Project Summary Report" current="Project Summary" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.project.summary.export', request()->query()) }}" class="btn btn-sm btn-primary" target="_blank">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body pb-3">
        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.project.summary.index') }}" method="GET">
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
                        <select name="priority" class="form-control-sm-custom" onchange="this.form.submit()">
                            <option value="">-- All Priority --</option>
                            @foreach (['low','medium','high','critical'] as $p)
                                <option value="{{ $p }}" {{ request('priority') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
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
                    <div class="filter-item">
                        <input type="date" name="date_from" class="form-control-sm-custom" onchange="this.form.submit()" value="{{ request('date_from') }}" title="Deadline from">
                    </div>
                    <div class="filter-item">
                        <input type="date" name="date_to" class="form-control-sm-custom" onchange="this.form.submit()" value="{{ request('date_to') }}" title="Deadline to">
                    </div>
                    <div class="filter-item fi-search">
                        <input type="text" name="search" class="form-control-sm-custom" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" placeholder="Search project, code or manager…" value="{{ request('search') }}">
                    </div>
                    <div class="filter-item"><a href="{{ route('report.project.summary.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card mb-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover" id="reportTable">
                        <thead>
                            <tr>
                                <th>Sr. No.</th><th>Code</th><th>Name</th><th>Manager</th>@feature('branches')<th>Branch</th>@endfeature<th>Status</th><th>Priority</th>
                                <th>Start</th><th>Deadline</th><th>Team</th><th>Tasks</th><th>Progress</th><th>Budget</th><th>Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $p)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $p->project_code }}</td>
                                    <td>{{ $p->name }}</td>
                                    <td>{{ $p->manager_name ?? 'N/A' }}</td>
                                    @feature('branches')<td>{{ $p->branch_name ?? '—' }}</td>@endfeature
                                    <td><span class="rpt-badge {{ in_array($p->status, ['ongoing','pending','hold']) ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($p->status) }}</span></td>
                                    <td>{{ ucfirst($p->priority) }}</td>
                                    <td>{{ optional($p->start_date)->format('d M Y') ?? '—' }}</td>
                                    <td>{{ optional($p->deadline_date)->format('d M Y') ?? '—' }}</td>
                                    <td>{{ $p->team_size }}</td>
                                    <td>{{ $p->tasks_completed }}/{{ $p->tasks_total }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <div class="progress-track"><div class="progress-fill" style="width: {{ $p->progress_percentage }}%"></div></div>
                                            <small>{{ $p->progress_percentage }}%</small>
                                        </div>
                                    </td>
                                    <td>{{ $p->budget !== null ? number_format($p->budget, 2) : '—' }}</td>
                                    <td>{{ number_format($p->spent, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="14" class="text-center text-muted py-4">No projects match the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
