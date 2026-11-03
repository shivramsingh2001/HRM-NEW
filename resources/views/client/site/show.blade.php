@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .site-detail .card { margin-bottom: 12px; }
        .site-detail .card-header { padding: 8px 12px; min-height: 0; }
        .site-detail .card-header h6 { font-size: 12.5px; font-weight: 600; margin: 0; }
        .site-detail .card-body { padding: 10px 12px; }
        .site-detail .info-label { font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 1px; }
        .site-detail .info-value { font-size: 12px; color: #0f172a; line-height: 1.4; word-break: break-word; }
        .site-detail .info-grid > div { margin-bottom: 8px; }
        .site-detail .record-line { font-size: 11.5px; line-height: 1.5; }
        .site-detail .record-line + .record-line { margin-top: 6px; }
        .site-detail .table th, .site-detail .table td { font-size: 11.5px; padding: 6px 10px; vertical-align: top; }
        .site-detail .table .sub { font-size: 10.5px; color: #64748b; }
        .site-detail .form-control-sm { font-size: 11.5px; padding: 3px 8px; height: 28px; }
    </style>
@endsection

@php
    $siteJson = [
        'id' => $site->id, 'site_code' => $site->site_code, 'site_name' => $site->site_name,
        'state_code' => $site->state_code, 'district' => $site->district, 'address' => $site->address,
        'location' => $site->location, 'latitude' => $site->latitude, 'longitude' => $site->longitude,
        'onboarding_date' => $site->onboarding_date?->format('Y-m-d'), 'status' => $site->status,
    ];
    $hasPin = $site->latitude !== null && $site->longitude !== null;
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" :title="$site->site_code . ' · ' . $site->site_name"
        current="{{ $site->site_code }}" :back="route('sites.index')"
        :crumbs="[['label' => 'Sites', 'url' => route('sites.index')]]">
        <x-slot:actions>
            @permission('sites', 'edit')
                <button type="button" class="btn btn-light btn-sm"
                    onclick="setSiteStatus({{ $site->id }}, '{{ $site->status === 'active' ? 'inactive' : 'active' }}')">
                    {{ $site->status === 'active' ? 'Mark Inactive' : 'Mark Active' }}</button>
                <button type="button" class="btn btn-primary btn-sm" data-site="{{ json_encode($siteJson) }}"
                    onclick="openSiteForm(JSON.parse(this.dataset.site))"><i class="feather-edit-2 me-1"></i> Edit</button>
            @endpermission
            @permission('sites', 'delete')
                @if ($site->visits_count === 0)
                    <button type="button" class="btn btn-light-danger btn-sm" onclick="deleteSite({{ $site->id }}, '{{ $site->site_code }}')">
                        <i class="feather-trash-2 me-1"></i> Delete</button>
                @endif
            @endpermission
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body site-detail">
        <div class="row g-2">
            <div class="col-lg-8">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6>Site Details</h6>
                        <x-ui.status-badge :status="$site->status" />
                    </div>
                    <div class="card-body">
                        <div class="row g-0 info-grid">
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">Site Code</div><div class="info-value fw-semibold">{{ $site->site_code }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">Site Name</div><div class="info-value fw-semibold">{{ $site->site_name }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">Onboarding Date</div><div class="info-value">{{ $site->onboarding_date?->format('d M Y') ?? '—' }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">State</div><div class="info-value">{{ $states[$site->state_code] ?? '—' }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">District</div><div class="info-value">{{ $site->district ?: '—' }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">Total Visits</div><div class="info-value">{{ $site->visits_count }}</div></div>
                            <div class="col-12 col-md-8 pe-2"><div class="info-label">Address</div><div class="info-value">{{ $site->address }}</div></div>
                            <div class="col-6 col-md-4 pe-2"><div class="info-label">Landmark / Area</div><div class="info-value">{{ $site->location ?: '—' }}</div></div>
                            <div class="col-12 pe-2 mb-0">
                                <div class="info-label">Map Pin</div>
                                <div class="info-value">
                                    @if ($hasPin)
                                        <a href="https://www.google.com/maps?q={{ $site->latitude }},{{ $site->longitude }}" target="_blank" rel="noopener">
                                            {{ $site->latitude }}, {{ $site->longitude }} <i class="feather-external-link"></i></a>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-header"><h6>Record</h6></div>
                    <div class="card-body">
                        <div class="record-line"><div class="info-label">Added by</div>{{ $site->createdBy->name ?? '—' }} · {{ $site->created_at?->format('d M Y, h:i A') }}</div>
                        <div class="record-line"><div class="info-label">Last updated by</div>{{ $site->updatedBy->name ?? '—' }} · {{ $site->updated_at?->format('d M Y, h:i A') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-2" id="visit-history">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6>Visit History</h6>
                <form method="GET" action="{{ route('sites.show', $site->id) }}#visit-history" class="d-flex gap-2 align-items-center">
                    <input type="month" name="month" class="form-control form-control-sm" style="width:auto;" value="{{ $visitMonth }}" max="{{ now()->format('Y-m') }}" onchange="this.form.submit()">
                    @if ($visitMonth)
                        <a href="{{ route('sites.show', $site->id) }}#visit-history" class="btn btn-light btn-sm">All months</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr><th class="text-center" style="width:48px;">Sr. No.</th><th>Date &amp; Time</th><th>Employee</th><th>Contact</th><th>Remarks</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $v)
                            @php $fileUrl = file_url($v->file_path, \App\Services\Site\SiteVisitService::FILE_MODULE); @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $visits->firstItem() + $loop->index }}</td>
                                <td class="text-nowrap">{{ $v->visited_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $v->user->name ?? '—' }}<div class="sub">{{ $v->user->employee_id ?? '' }}</div></td>
                                <td>{{ $v->contact_person_name ?: '—' }}@if ($v->contact_mobile)<div class="sub">{{ $v->contact_mobile }}</div>@endif</td>
                                <td style="max-width:360px; white-space:pre-line;">{{ $v->remarks }}</td>
                                <td>@if ($fileUrl)<a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="clock" title="No visits {{ $visitMonth ? 'in ' . \Carbon\Carbon::parse($visitMonth . '-01')->format('F Y') : 'yet' }}" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>
        <x-ui.pagination-footer :paginator="$visits" label="visits" />
    </div>
@endsection

@section('create-modal')
    @include('client.site.partials.form-drawer')
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @include('client.site.partials.actions-script', ['afterDelete' => route('sites.index')])
    @stack('site-scripts')
@endsection
