@extends('client.layout.master')

@section('style')
    @include('client.report.attendance.partials.shift-activity-styles')
@endsection

@php
    $avg = $stats->avg_minutes !== null ? \App\Http\Controllers\Report\ShiftActivityReportController::turnaround((object) [
        'created_at' => '2000-01-01 00:00:00', 'decided_at' => \Carbon\Carbon::parse('2000-01-01')->addMinutes((int) round($stats->avg_minutes)), 'mode' => 'request',
    ]) : '—';
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Shift Requests Register" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]">
        <x-slot:actions>
            <a href="{{ route('report.attendance.shift-requests.export', request()->query()) }}" class="btn btn-sm btn-primary">
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
            <div class="col-6 col-md"><x-ui.stat-card icon="inbox" label="Requests" value="{{ (int) $stats->total }}" /></div>
            <div class="col-6 col-md"><x-ui.stat-card icon="clock" label="Pending" value="{{ (int) $stats->pending }}" /></div>
            <div class="col-6 col-md"><x-ui.stat-card icon="check-circle" label="Approved" value="{{ (int) $stats->approved }}" /></div>
            <div class="col-6 col-md"><x-ui.stat-card icon="x-circle" label="Rejected / declined" value="{{ (int) $stats->rejected }}" /></div>
            <div class="col-12 col-md"><x-ui.stat-card icon="activity" label="Avg. turnaround" value="{{ $avg }}" /></div>
        </div>

        <x-ui.filter-card title="Filter Report">
            <form action="{{ route('report.attendance.shift-requests.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="date_by" class="form-control-sm-custom auto-submit" aria-label="Dates are">
                            <option value="changed" @selected($filters['date_by'] === 'changed')>Raised on</option>
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
                        <select name="type" class="form-control-sm-custom auto-submit" aria-label="Type">
                            <option value="">-- Swaps & Changes --</option>
                            <option value="swap" @selected($filters['type'] === 'swap')>Shift swaps</option>
                            <option value="change" @selected($filters['type'] === 'change')>Shift changes</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="status" class="form-control-sm-custom auto-submit" aria-label="Status">
                            <option value="">-- All Statuses --</option>
                            <option value="pending" @selected($filters['status'] === 'pending')>Pending (any)</option>
                            @foreach ($statuses as $k => $label)
                                <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="actor_id" class="form-control-sm-custom auto-submit" aria-label="Decided by">
                            <option value="">-- Decided by anyone --</option>
                            @foreach ($actors as $a)
                                <option value="{{ $a->id }}" @selected($filters['actor_id'] === $a->id)>{{ $a->name }} ({{ ucfirst($a->role) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item fi-btn"><a href="{{ route('report.attendance.shift-requests.index') }}" class="btn-sm-custom-outline" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a></div>
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
                                <th>Request</th>
                                <th class="col-emp">Requested By</th>
                                <th>Department</th>
                                @feature('branches')<th>Branch</th>@endfeature
                                <th>Colleague</th>
                                <th>Shifts</th>
                                <th>Status</th>
                                <th>Decided By</th>
                                <th>Turnaround</th>
                                <th>Reason / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>{{ $rows->firstItem() + $loop->index }}</td>
                                    <td>
                                        <a href="{{ route('shift.requests.index', ['open' => $r->id]) }}" class="fw-semibold">{{ $r->request_no }}</a>
                                        <div class="sub">{{ $r->type === 'swap' ? 'Shift swap' : 'Shift change' }}{{ $r->mode === 'direct' ? ' · direct' : '' }} · {{ \Carbon\Carbon::parse($r->created_at)->format('d M Y, h:i A') }}</div>
                                    </td>
                                    <td class="col-emp">
                                        <div class="employee-info">
                                            <div class="employee-avatar">{{ strtoupper(substr($r->requester_name ?? 'NA', 0, 2)) }}</div>
                                            <div class="employee-details">
                                                <div class="employee-name-text">{{ $r->requester_name }} <small>( {{ $r->requester_code ?? 'N/A' }} )</small></div>
                                                <div class="employee-email-text">{{ $r->requester_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $r->department_name ?: '—' }}</td>
                                    @feature('branches')<td>{{ $r->branch_name ?: '—' }}</td>@endfeature
                                    <td>{{ $r->counterpart_name ?: '—' }}</td>
                                    <td class="wrap">
                                        @foreach ($shifts[$r->id]['lines'] ?? [] as $i)
                                            <div>{{ $i->name }} · {{ \Carbon\Carbon::parse($i->date)->format('d M') }}: <span class="muted">{{ $i->from_name ?? 'None' }}</span> → <strong>{{ $i->to_name ?? 'None' }}</strong></div>
                                        @endforeach
                                    </td>
                                    <td><span class="badge {{ \App\Models\ShiftRequest::STATUS_BADGES[$r->status] ?? 'bg-soft-secondary text-secondary' }}">{{ $statuses[$r->status] ?? $r->status }}</span></td>
                                    <td>{{ $r->decider_name ?: '—' }}
                                        @if ($r->decided_at)<div class="sub">{{ \Carbon\Carbon::parse($r->decided_at)->format('d M Y, h:i A') }}</div>@endif
                                    </td>
                                    <td>{{ \App\Http\Controllers\Report\ShiftActivityReportController::turnaround($r) ?: '—' }}</td>
                                    <td class="wrap">
                                        {{ $r->reason ?: '—' }}
                                        @if ($r->peer_remarks)<div class="sub">Colleague: {{ $r->peer_remarks }}</div>@endif
                                        @if ($r->approver_remarks)<div class="sub">Approver: {{ $r->approver_remarks }}</div>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center py-5 text-muted" style="font-size:11px;">No shift requests found for these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($rows->hasPages())
                <x-ui.pagination-footer :paginator="$rows" label="requests" />
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    @include('client.report.attendance.partials.shift-activity-script')
@endsection
