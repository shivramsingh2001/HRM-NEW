@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header :title="$user->name . ' · Site Visits'" current="{{ $user->name }}"
        :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')], ['label' => 'Monthly Site Visits', 'url' => route('report.site-visit.monthly.index', ['month' => $month])]]" />

    <div class="main-content" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'site_visit'])

        <x-ui.filter-card title="{{ $user->name }}{{ $user->employee_id ? ' (' . $user->employee_id . ')' : '' }}">
            <form method="GET" class="d-flex flex-wrap gap-2 filter-row">
                <input type="month" name="month" class="form-control form-control-sm" style="width:auto;" value="{{ $month }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()">
            </form>
        </x-ui.filter-card>

        <div class="row g-2 mb-3 text-center">
            <div class="col-4"><div class="card mb-0"><div class="card-body py-2"><div class="text-muted small">Total Visits</div><div class="fs-5 fw-semibold">{{ $totals['visits'] }}</div></div></div></div>
            <div class="col-4"><div class="card mb-0"><div class="card-body py-2"><div class="text-muted small">Different Sites</div><div class="fs-5 fw-semibold">{{ $totals['sites'] }}</div></div></div></div>
            <div class="col-4"><div class="card mb-0"><div class="card-body py-2"><div class="text-muted small">Days with a Visit</div><div class="fs-5 fw-semibold">{{ $totals['days'] }}</div></div></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Day by Day</h6></div>
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr><th style="width:160px;">Date</th><th class="text-center" style="width:90px;">Visits</th><th>Sites Visited</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($days as $d)
                            <tr class="{{ $d['visits'] ? '' : 'text-muted' }}">
                                <td>{{ \Carbon\Carbon::parse($d['date'])->format('D, d M') }}</td>
                                <td class="text-center">{{ $d['visits'] ?: '' }}</td>
                                <td>{{ implode(', ', $d['sites']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-ui.empty-state icon="calendar" title="No days to show" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">All Visits</h6></div>
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr><th>Date &amp; Time</th><th>Site</th><th>Contact</th><th>Remarks</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $v)
                            @php $fileUrl = file_url($v->file_path, \App\Services\Site\SiteVisitService::FILE_MODULE); @endphp
                            <tr>
                                <td class="text-nowrap">{{ $v->visited_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $v->site->site_name ?? '—' }} <div class="text-muted small">{{ $v->site->site_code ?? '' }}</div></td>
                                <td>{{ $v->contact_person_name ?: '—' }}@if ($v->contact_mobile)<div class="text-muted small">{{ $v->contact_mobile }}</div>@endif</td>
                                <td style="max-width:320px; white-space:pre-line;">{{ $v->remarks }}</td>
                                <td>@if ($fileUrl)<a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="map-pin" title="No visits this month" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
    </div>
@endsection
