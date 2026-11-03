@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .site-stats { grid-template-columns: repeat(4, 1fr); }
        @media (max-width: 768px) { .site-stats { grid-template-columns: repeat(2, 1fr); } }

        /* All filters on one line on desktop; they wrap only on small screens. */
        .site-filter .filter-row { flex-wrap: nowrap; align-items: center; }
        .site-filter .filter-item { flex: 1 1 0; min-width: 0 !important; }
        .site-filter .filter-item.search { flex: 1.6 1 0; }
        .site-filter .filter-item.reset { flex: 0 0 auto; }
        .site-filter .filter-item.date-range { flex: 2 1 0; display: flex; align-items: center; gap: 6px; }
        .site-filter .date-range input { flex: 1 1 0; min-width: 0; }
        .site-filter .date-range span { font-size: 11px; color: #64748b; white-space: nowrap; }
        @media (max-width: 1100px) {
            .site-filter .filter-row { flex-wrap: wrap; }
            .site-filter .filter-item { flex: 1 1 180px; }
            .site-filter .filter-item.date-range { flex: 1 1 100%; }
        }

        .site-table th, .site-table td { font-size: 12px; padding: 8px 10px; vertical-align: middle; }
        .site-table .site-name { font-weight: 600; color: #0f172a; }
        .site-table .site-sub { font-size: 11px; color: #64748b; }
        .site-actions { display: inline-flex; flex-wrap: nowrap; gap: 4px; }
        .site-actions .action-btn { width: 28px; height: 28px; }
    </style>
@endsection

@php
    $isFiltered = request()->hasAny(['search', 'state_code', 'district', 'status', 'date_from', 'date_to']);
@endphp

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

        <div class="stats-grid site-stats">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper"><i class="feather-map-pin"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['total'] }}</div>
                    <div class="stats-label">Total Sites</div>
                </div>
            </div>
            <div class="stats-card present-card">
                <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['active'] }}</div>
                    <div class="stats-label">Active Sites</div>
                </div>
            </div>
            <div class="stats-card inactive-card">
                <div class="stats-icon-wrapper"><i class="feather-pause-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['inactive'] }}</div>
                    <div class="stats-label">Inactive Sites</div>
                </div>
            </div>
            <div class="stats-card visits-card">
                <div class="stats-icon-wrapper"><i class="feather-navigation"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['visits'] }}</div>
                    <div class="stats-label">Total Visits</div>
                </div>
            </div>
        </div>

        <x-ui.filter-card title="Filter Sites" class="site-filter" :clear-url="$isFiltered ? route('sites.index') : null">
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
                    <div class="filter-item date-range" title="Onboarding date">
                        <span>From</span>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" max="{{ request('date_to') }}"
                            aria-label="Onboarding date from" onchange="this.form.submit()">
                        <span>To</span>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" min="{{ request('date_from') }}"
                            aria-label="Onboarding date to" onchange="this.form.submit()">
                    </div>
                    <div class="filter-item reset"><a href="{{ route('sites.index') }}" class="reset-btn"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card stretch stretch-full">
            <div class="card-body p-0">
                <x-ui.data-table class="site-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:56px;">Sr. No.</th>
                            <th>Site Code</th>
                            <th>Site Name</th>
                            <th>State / District</th>
                            <th>Address</th>
                            <th>Onboarding Date</th>
                            <th class="text-center">Visits</th>
                            <th>Status</th>
                            <th class="text-center" style="width:1%;">Actions</th>
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
                                $isActive = $site->status === 'active';
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $sites->firstItem() + $loop->index }}</td>
                                <td class="text-nowrap"><a href="{{ route('sites.show', $site->id) }}" class="fw-semibold">{{ $site->site_code }}</a></td>
                                <td>
                                    <div class="site-name">{{ $site->site_name }}</div>
                                    @if ($site->location)
                                        <div class="site-sub"><i class="feather-map-pin"></i> {{ $site->location }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $states[$site->state_code] ?? '—' }}</div>
                                    <div class="site-sub">{{ $site->district ?: '—' }}</div>
                                </td>
                                <td class="text-truncate" style="max-width:220px;" title="{{ $site->address }}">{{ $site->address }}</td>
                                <td class="text-nowrap">{{ $site->onboarding_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center"><span class="badge bg-light text-dark">{{ $site->visits_count }}</span></td>
                                <td><x-ui.status-badge :status="$site->status" /></td>
                                <td class="text-center">
                                    <div class="site-actions">
                                        <a href="{{ route('sites.show', $site->id) }}" class="action-btn" title="View"><i class="feather-eye"></i></a>
                                        @permission('sites', 'edit')
                                            <button type="button" class="action-btn" title="Edit"
                                                data-site="{{ json_encode($siteJson) }}" onclick="openSiteForm(JSON.parse(this.dataset.site))"><i class="feather-edit-2"></i></button>
                                            <button type="button" class="action-btn {{ $isActive ? 'warning' : 'success' }}"
                                                title="{{ $isActive ? 'Mark inactive' : 'Mark active' }}"
                                                onclick="setSiteStatus({{ $site->id }}, '{{ $isActive ? 'inactive' : 'active' }}')">
                                                <i class="feather-{{ $isActive ? 'pause-circle' : 'play-circle' }}"></i></button>
                                        @endpermission
                                        @permission('sites', 'delete')
                                            @if ($site->visits_count === 0)
                                                <button type="button" class="action-btn delete" title="Delete"
                                                    onclick="deleteSite({{ $site->id }}, '{{ $site->site_code }}')"><i class="feather-trash-2"></i></button>
                                            @endif
                                        @endpermission
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <x-ui.empty-state icon="map-pin" title="No sites found"
                                        :subtitle="$isFiltered ? 'Try changing or clearing the filters.' : 'Add a site to get started.'" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
            <x-ui.pagination-footer :paginator="$sites" label="sites" />
        </div>
    </div>
@endsection

@section('create-modal')
    @include('client.site.partials.form-drawer')
    @permission('sites', 'create')
        @include('client.site.partials.import-modal')
    @endpermission
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @include('client.site.partials.actions-script')
    @stack('site-scripts')
@endsection
