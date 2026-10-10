@extends('client.layout.master')

@section('style')
    @include('client.report.attendance.partials.shift-activity-styles')
@endsection

@php
    $pill = fn ($name, $color) => $name
        ? '<span class="shift-pill"><i style="background:' . e(preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#0D6EFD') . '"></i>' . e($name) . '</span>'
        : '<span class="muted">None</span>';
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Shift Change Log" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.attendance.shift-changes.export', request()->query()) }}" class="btn btn-sm btn-primary">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'attendance'])
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-2 mb-2">
            <div class="col-6 col-md-3"><x-ui.stat-card icon="repeat" label="Changes" value="{{ (int) $stats->total }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="users" label="Employees" value="{{ (int) $stats->employees }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="shuffle" label="Swaps" value="{{ (int) $stats->swaps }}" /></div>
            <div class="col-6 col-md-3"><x-ui.stat-card icon="inbox" label="From requests" value="{{ (int) $stats->from_requests }}" /></div>
        </div>

        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.shift-changes.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="date_by" class="form-control-sm-custom auto-submit" aria-label="Dates are">
                            <option value="changed" @selected($filters['date_by'] === 'changed')>Changed on</option>
                            <option value="shift" @selected($filters['date_by'] === 'shift')>Shift date</option>
                        </select>
                    </div>
                    <div class="filter-item fi-date">
                        <input type="date" name="start_date" value="{{ $filters['start'] }}" class="form-control-sm-custom auto-submit" aria-label="Start date">
                    </div>
                    <span class="date-sep">to</span>
                    <div class="filter-item fi-date">
                        <input type="date" name="end_date" value="{{ $filters['end'] }}" class="form-control-sm-custom auto-submit" aria-label="End date">
                    </div>
                    <div class="filter-item fi-search">
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control-sm-custom" placeholder="Search name / ID" aria-label="Search employee" autocomplete="off">
                    </div>
                    <div class="filter-item">
                        <select name="user_id" class="form-control-sm-custom auto-submit" aria-label="Employee">
                            <option value="">-- All Employees --</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}" @selected($filters['user_id'] === $e->id)>{{ $e->name }}{{ $e->employee_id ? ' (' . $e->employee_id . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('client.report.partials.employee-filters', ['selectClass' => 'form-control-sm-custom', 'deptParam' => 'department_id', 'desigParam' => 'designation_id', 'except' => ['location']])
                    <div class="filter-item">
                        <select name="change_type" class="form-control-sm-custom auto-submit" aria-label="Change">
                            <option value="">-- All Changes --</option>
                            @foreach ($changeTypes as $k => $label)
                                <option value="{{ $k }}" @selected($filters['change_type'] === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="source" class="form-control-sm-custom auto-submit" aria-label="How">
                            <option value="">-- All Sources --</option>
                            @foreach ($sources as $k => $label)
                                <option value="{{ $k }}" @selected($filters['source'] === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="actor_id" class="form-control-sm-custom auto-submit" aria-label="Changed by">
                            <option value="">-- Changed by anyone --</option>
                            @foreach ($actors as $a)
                                <option value="{{ $a->id }}" @selected($filters['actor_id'] === $a->id)>{{ $a->name }} ({{ ucfirst($a->role) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item fi-btn"><a href="{{ route('report.attendance.shift-changes.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Sr. No.</th>
                                <th>Changed On</th>
                                <th class="col-emp">Employee</th>
                                <th>Department</th>
                                @feature('branches')<th>Branch</th>@endfeature
                                <th>Shift Date</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Change</th>
                                <th>How</th>
                                <th>Changed By</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ $rows->firstItem() + $loop->index }}</td>
                                    <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d M Y') }}<div class="sub">{{ \Carbon\Carbon::parse($r->created_at)->format('h:i A') }}</div></td>
                                    <td class="col-emp">
                                        <div class="employee-info">
                                            <div class="employee-avatar">{{ strtoupper(substr($r->employee_name ?? 'NA', 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name-text">{{ $r->employee_name }} <small>( {{ $r->employee_code ?? 'N/A' }} )</small></div>
                                                <div class="employee-email-text">{{ $r->employee_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $r->department_name ?: '—' }}</td>
                                    @feature('branches')<td>{{ $r->branch_name ?: '—' }}</td>@endfeature
                                    <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}<div class="sub">{{ \Carbon\Carbon::parse($r->date)->format('l') }}@if ($r->is_additional) · additional shift @endif</div></td>
                                    <td>{!! $pill($r->from_shift_name, $r->from_color) !!}</td>
                                    <td>{!! $pill($r->to_shift_name, $r->to_color) !!}</td>
                                    <td><span class="chip">{{ $changeTypes[$r->change_type] ?? $r->change_type }}</span></td>
                                    <td>{{ $sources[$r->source] ?? ucfirst(str_replace('_', ' ', $r->source)) }}
                                        @if ($r->request_no)<div class="sub">{{ $r->request_no }}</div>@endif
                                    </td>
                                    <td>{{ $r->actor_name ?? 'System' }}<div class="sub">{{ $r->actor_role ? ucfirst($r->actor_role) . ' · ' : '' }}{{ ucfirst($r->channel) }}</div></td>
                                    <td class="wrap">{{ $r->reason ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="text-center py-5 text-muted" style="font-size:11px;">No shift changes found for these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($rows->hasPages())
                <x-ui.pagination-footer :paginator="$rows" label="changes" />
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    @include('client.report.attendance.partials.shift-activity-script')
@endsection
