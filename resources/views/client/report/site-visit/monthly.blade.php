@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Monthly Site Visits" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']">
        <x-slot:actions>
            <a href="{{ route('report.site-visit.monthly.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="feather-download me-1"></i> Export</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'site_visit'])

        <x-ui.filter-card title="Filter Report" :clear-url="request()->hasAny(['month', 'user_id']) ? route('report.site-visit.monthly.index') : null">
            <form method="GET" class="d-flex flex-wrap gap-2 filter-row">
                <input type="month" name="month" class="form-control form-control-sm" style="width:auto;" value="{{ $month }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()">
                <select name="user_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Employees</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}" @selected($userId == $e->id)>{{ $e->name }}{{ $e->employee_id ? " ({$e->employee_id})" : '' }}</option>
                    @endforeach
                </select>
                <a href="{{ route('report.site-visit.monthly.index') }}" class="btn btn-light btn-sm" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a>
            </form>
        </x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="text-center">Total Visits</th>
                            <th class="text-center">Different Sites</th>
                            <th class="text-center">Days with a Visit</th>
                            <th>Last Visit</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $r['name'] }}</div>
                                    <div class="text-muted small">{{ $r['employee_id'] }}</div>
                                </td>
                                <td class="text-center fw-semibold">{{ $r['visits'] }}</td>
                                <td class="text-center">{{ $r['sites'] }}</td>
                                <td class="text-center">{{ $r['days'] }}</td>
                                <td>{{ $r['last_visit'] ? \Carbon\Carbon::parse($r['last_visit'])->format('d M Y, h:i A') : '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('report.site-visit.monthly.employee', ['userId' => $r['user_id'], 'month' => $month]) }}" class="btn btn-sm btn-light">Day by day <i class="feather-arrow-right"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="map-pin" title="No visits in {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>
@endsection
