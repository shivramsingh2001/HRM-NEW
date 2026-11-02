@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Sites">
        <x-slot:actions>
            <a href="{{ route('sites.export', request()->query()) }}" class="btn btn-light btn-sm"><i class="feather-download me-1"></i> Export</a>
            @permission('sites', 'create')
                <button type="button" class="btn btn-light btn-sm" onclick="openSiteImport()"><i class="feather-upload me-1"></i> Import</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openSiteForm()"><i class="feather-plus me-1"></i> Add Site</button>
            @endpermission
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <x-ui.filter-card title="Filter Sites"
            :clear-url="request()->hasAny(['search', 'state_code', 'district', 'status']) ? route('sites.index') : null">
            <form action="{{ route('sites.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item search">
                        <div class="search-wrapper"><i class="feather-search"></i>
                            <input type="text" name="search" placeholder="Search code, name or address…" value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="filter-item">
                        <select name="state_code" onchange="this.form.submit()">
                            <option value="">All States</option>
                            @foreach ($states as $code => $name)
                                <option value="{{ $code }}" @selected((string) request('state_code') === (string) $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="district" onchange="this.form.submit()">
                            <option value="">All Districts</option>
                            @foreach ($districts as $district)
                                <option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-item"><a href="{{ route('sites.index') }}" class="reset-btn"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th>Site Code</th>
                            <th>Site Name</th>
                            <th>State</th>
                            <th>District</th>
                            <th>Address</th>
                            <th>Onboarding</th>
                            <th class="text-center">Visits</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sites as $site)
                            @php
                                $siteJson = [
                                    'id' => $site->id, 'site_code' => $site->site_code, 'site_name' => $site->site_name,
                                    'state_code' => $site->state_code, 'district' => $site->district, 'address' => $site->address,
                                    'location' => $site->location, 'latitude' => $site->latitude, 'longitude' => $site->longitude,
                                    'onboarding_date' => $site->onboarding_date?->format('Y-m-d'), 'status' => $site->status,
                                ];
                            @endphp
                            <tr>
                                <td><a href="{{ route('sites.show', $site->id) }}" class="fw-semibold">{{ $site->site_code }}</a></td>
                                <td>{{ $site->site_name }}</td>
                                <td>{{ $states[$site->state_code] ?? '—' }}</td>
                                <td>{{ $site->district ?: '—' }}</td>
                                <td class="text-truncate" style="max-width:240px;" title="{{ $site->address }}">{{ $site->address }}</td>
                                <td>{{ $site->onboarding_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">{{ $site->visits_count }}</td>
                                <td><x-ui.status-badge :status="$site->status" /></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('sites.show', $site->id) }}" class="btn btn-sm btn-light" title="View"><i class="feather-eye"></i></a>
                                    @permission('sites', 'edit')
                                        <button type="button" class="btn btn-sm btn-light" title="Edit"
                                            data-site="{{ json_encode($siteJson) }}" onclick="openSiteForm(JSON.parse(this.dataset.site))"><i class="feather-edit-2"></i></button>
                                        <button type="button" class="btn btn-sm btn-light" title="{{ $site->status === 'active' ? 'Mark inactive' : 'Mark active' }}"
                                            onclick="setSiteStatus({{ $site->id }}, '{{ $site->status === 'active' ? 'inactive' : 'active' }}')">
                                            <i class="feather-{{ $site->status === 'active' ? 'pause-circle' : 'play-circle' }}"></i></button>
                                    @endpermission
                                    @permission('sites', 'delete')
                                        @if ($site->visits_count === 0)
                                            <button type="button" class="btn btn-sm btn-light-danger" title="Delete"
                                                onclick="deleteSite({{ $site->id }}, '{{ $site->site_code }}')"><i class="feather-trash-2"></i></button>
                                        @endif
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <x-ui.empty-state icon="map-pin" title="No sites yet" subtitle="Add a site to get started." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>

        <x-ui.pagination-footer :paginator="$sites" label="sites" />
    </div>
@endsection

@section('create-modal')
    @include('client.site.partials.form-drawer')
    @permission('sites', 'create')
        @include('client.site.partials.import-drawer')
    @endpermission
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @include('client.site.partials.actions-script')
    @stack('site-scripts')
@endsection
