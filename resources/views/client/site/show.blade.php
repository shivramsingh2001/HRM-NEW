@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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

    <div class="content-area-body">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Site Details</h6>
                        <x-ui.status-badge :status="$site->status" />
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="text-muted small">Site Code</div><div class="fw-semibold">{{ $site->site_code }}</div></div>
                            <div class="col-md-6"><div class="text-muted small">Site Name</div><div class="fw-semibold">{{ $site->site_name }}</div></div>
                            <div class="col-md-6"><div class="text-muted small">State</div><div>{{ $states[$site->state_code] ?? '—' }}</div></div>
                            <div class="col-md-6"><div class="text-muted small">District</div><div>{{ $site->district ?: '—' }}</div></div>
                            <div class="col-12"><div class="text-muted small">Address</div><div>{{ $site->address }}</div></div>
                            <div class="col-md-6"><div class="text-muted small">Location</div><div>{{ $site->location ?: '—' }}</div></div>
                            <div class="col-md-6">
                                <div class="text-muted small">Map Pin</div>
                                <div>
                                    @if ($hasPin)
                                        <a href="https://www.google.com/maps?q={{ $site->latitude }},{{ $site->longitude }}" target="_blank" rel="noopener">
                                            {{ $site->latitude }}, {{ $site->longitude }} <i class="feather-external-link"></i></a>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6"><div class="text-muted small">Onboarding Date</div><div>{{ $site->onboarding_date?->format('d M Y') ?? '—' }}</div></div>
                            <div class="col-md-6"><div class="text-muted small">Total Visits</div><div>{{ $site->visits_count }}</div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Record</h6></div>
                    <div class="card-body small">
                        <div class="mb-2"><span class="text-muted">Added by</span><br>{{ $site->createdBy->name ?? '—' }} · {{ $site->created_at?->format('d M Y, h:i A') }}</div>
                        <div><span class="text-muted">Last updated by</span><br>{{ $site->updatedBy->name ?? '—' }} · {{ $site->updated_at?->format('d M Y, h:i A') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id="visit-history">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Visit History</h6>
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
                        <tr><th>Date &amp; Time</th><th>Employee</th><th>Contact</th><th>Remarks</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $v)
                            @php $fileUrl = file_url($v->file_path, \App\Services\Site\SiteVisitService::FILE_MODULE); @endphp
                            <tr>
                                <td class="text-nowrap">{{ $v->visited_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $v->user->name ?? '—' }}<div class="text-muted small">{{ $v->user->employee_id ?? '' }}</div></td>
                                <td>{{ $v->contact_person_name ?: '—' }}@if ($v->contact_mobile)<div class="text-muted small">{{ $v->contact_mobile }}</div>@endif</td>
                                <td style="max-width:360px; white-space:pre-line;">{{ $v->remarks }}</td>
                                <td>@if ($fileUrl)<a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>@else — @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="clock" title="No visits {{ $visitMonth ? 'in ' . \Carbon\Carbon::parse($visitMonth . '-01')->format('F Y') : 'yet' }}" /></td></tr>
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
