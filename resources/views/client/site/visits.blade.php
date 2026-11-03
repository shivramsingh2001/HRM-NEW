@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .visit-stats { grid-template-columns: repeat(4, 1fr); }
        @media (max-width: 768px) { .visit-stats { grid-template-columns: repeat(2, 1fr); } }

        /* All filters on one line on desktop; they wrap only on small screens. */
        .visit-filter .filter-row { flex-wrap: nowrap; align-items: center; }
        .visit-filter .filter-item { flex: 1 1 0; min-width: 0 !important; }
        .visit-filter .filter-item.search { flex: 1.6 1 0; }
        .visit-filter .filter-item.reset { flex: 0 0 auto; }
        .visit-filter .filter-item.date-range { flex: 2 1 0; display: flex; align-items: center; gap: 6px; }
        .visit-filter .date-range input { flex: 1 1 0; min-width: 0; }
        .visit-filter .date-range span { font-size: 11px; color: #64748b; white-space: nowrap; }
        @media (max-width: 1100px) {
            .visit-filter .filter-row { flex-wrap: wrap; }
            .visit-filter .filter-item { flex: 1 1 180px; }
            .visit-filter .filter-item.date-range { flex: 1 1 100%; }
        }

        .visit-table th, .visit-table td { font-size: 12px; padding: 8px 10px; vertical-align: middle; }
        .visit-table .v-main { font-weight: 600; color: #0f172a; }
        .visit-table .v-sub { font-size: 11px; color: #64748b; }
        .visit-table .v-remarks { max-width: 320px; white-space: pre-line; }
        .visit-actions { display: inline-flex; flex-wrap: nowrap; gap: 4px; }
        .visit-actions .action-btn { width: 28px; height: 28px; }
        .visit-tabs .nav-link { font-size: 12px; padding: 10px 14px; }
        .visit-tabs .nav-link .count { font-size: 10px; background: var(--primary-light); color: var(--primary); border-radius: 10px; padding: 1px 7px; margin-left: 4px; }
        .edited-tag { font-size: 10px; color: #64748b; background: #f1f5f9; border-radius: 4px; padding: 0 5px; margin-left: 4px; }
    </style>
@endsection

@php
    $isTeam = $tab === 'team';
    $isFiltered = request()->hasAny(['search', 'site_id', 'user_id', 'date_from', 'date_to', 'date', 'month']);
    $resetUrl = route('site-visits.index', $isTeam ? ['tab' => 'team'] : []);
@endphp

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" :title="$canTeam ? 'Site Visits' : 'My Site Visits'">
        <x-slot:actions>
            @permission('site_visits', 'create')
                <button type="button" class="btn btn-primary btn-sm" onclick="openVisitForm()"><i class="feather-plus me-1"></i> Record Visit</button>
            @endpermission
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body">
        <div class="stats-grid visit-stats">
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-navigation"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['visits'] }}</div>
                    <div class="stats-label">{{ $isTeam ? 'Team Visits' : 'Total Visits' }}</div>
                </div>
            </div>
            @if ($isTeam)
                <div class="stats-card">
                    <div class="stats-icon-wrapper"><i class="feather-users"></i></div>
                    <div class="stats-content">
                        <div class="stats-amount-main">{{ $summary['employees'] }}</div>
                        <div class="stats-label">Employees Visiting</div>
                    </div>
                </div>
            @endif
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-map-pin"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['sites'] }}</div>
                    <div class="stats-label">Sites Covered</div>
                </div>
            </div>
            @unless ($isTeam)
                <div class="stats-card">
                    <div class="stats-icon-wrapper"><i class="feather-calendar"></i></div>
                    <div class="stats-content">
                        <div class="stats-amount-main">{{ $summary['days'] }}</div>
                        <div class="stats-label">Days with a Visit</div>
                    </div>
                </div>
            @endunless
            <div class="stats-card">
                <div class="stats-icon-wrapper"><i class="feather-clock"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['today'] }}</div>
                    <div class="stats-label">Visits Today</div>
                </div>
            </div>
        </div>

        <x-ui.filter-card :title="$isTeam ? 'Filter Team Visits' : 'Filter Visits'" class="visit-filter" :clear-url="$isFiltered ? $resetUrl : null">
            <form action="{{ route('site-visits.index') }}" method="GET" id="filterForm">
                @if ($isTeam)
                    <input type="hidden" name="tab" value="team">
                @endif
                <div class="filter-row">
                    <div class="filter-item search">
                        <div class="search-wrapper"><i class="feather-search"></i>
                            <input type="text" name="search" placeholder="Search site, contact or remarks…" value="{{ $filters['search'] ?? '' }}">
                        </div>
                    </div>
                    @if ($isTeam)
                        <div class="filter-item">
                            <select name="user_id" onchange="this.form.submit()">
                                <option value="">All Employees</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $emp->id)>{{ $emp->name }}{{ $emp->employee_id ? ' (' . $emp->employee_id . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="filter-item">
                        <select name="site_id" onchange="this.form.submit()">
                            <option value="">All Sites</option>
                            @foreach ($visitedSites as $id => $label)
                                <option value="{{ $id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $id)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item date-range" title="Visit date">
                        <span>From</span>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" max="{{ $filters['date_to'] ?? now()->toDateString() }}"
                            aria-label="Visit date from" onchange="this.form.submit()">
                        <span>To</span>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" min="{{ $filters['date_from'] ?? '' }}" max="{{ now()->toDateString() }}"
                            aria-label="Visit date to" onchange="this.form.submit()">
                    </div>
                    <div class="filter-item reset"><a href="{{ $resetUrl }}" class="reset-btn"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card stretch stretch-full">
            @if ($canTeam)
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2 visit-tabs">
                        <li class="nav-item">
                            <a class="nav-link {{ $isTeam ? '' : 'active' }}" href="{{ route('site-visits.index') }}">
                                <i class="feather-user me-1"></i>My Visits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $isTeam ? 'active' : '' }}" href="{{ route('site-visits.index', ['tab' => 'team']) }}">
                                <i class="feather-users me-1"></i>Team Visits @if ($isTeam)<span class="count">{{ $visits->total() }}</span>@endif
                            </a>
                        </li>
                    </ul>
                </div>
            @endif
            <div class="card-body p-0">
                <x-ui.data-table class="visit-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:56px;">Sr. No.</th>
                            <th>Date &amp; Time</th>
                            @if ($isTeam)
                                <th>Employee</th>
                            @endif
                            <th>Site</th>
                            <th>Contact</th>
                            <th>Remarks</th>
                            <th>File</th>
                            @unless ($isTeam)
                                <th class="text-center" style="width:1%;">Actions</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($visits as $visit)
                            @php
                                $fileUrl = file_url($visit->file_path, \App\Services\Site\SiteVisitService::FILE_MODULE);
                                $canEdit = $visit->visit_date?->isToday();
                                $visitJson = [
                                    'id' => $visit->id,
                                    'site' => ($visit->site->site_code ?? '') . ' · ' . ($visit->site->site_name ?? ''),
                                    'contact_person_name' => $visit->contact_person_name, 'contact_mobile' => $visit->contact_mobile,
                                    'remarks' => $visit->remarks, 'file_url' => $fileUrl,
                                ];
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $visits->firstItem() + $loop->index }}</td>
                                <td class="text-nowrap">
                                    <div class="v-main">{{ $visit->visit_date?->format('d M Y') }}</div>
                                    <div class="v-sub">{{ $visit->visited_at?->format('h:i A') }}@if ($visit->edited_at)<span class="edited-tag">edited</span>@endif</div>
                                </td>
                                @if ($isTeam)
                                    <td>
                                        <div class="v-main">{{ $visit->user->name ?? '—' }}</div>
                                        <div class="v-sub">{{ $visit->user->employee_id ?? '' }}</div>
                                    </td>
                                @endif
                                <td>
                                    <div class="v-main">{{ $visit->site->site_name ?? '—' }}</div>
                                    <div class="v-sub">{{ $visit->site->site_code ?? '' }}{{ $visit->site?->district ? ' · ' . $visit->site->district : '' }}</div>
                                </td>
                                <td>
                                    <div>{{ $visit->contact_person_name ?: '—' }}</div>
                                    @if ($visit->contact_mobile)<div class="v-sub">{{ $visit->contact_mobile }}</div>@endif
                                </td>
                                <td class="v-remarks">{{ $visit->remarks }}</td>
                                <td class="text-nowrap">
                                    @if ($fileUrl)
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @unless ($isTeam)
                                    <td class="text-center">
                                        <div class="visit-actions">
                                            @if ($canEdit)
                                                @permission('site_visits', 'edit')
                                                    <button type="button" class="action-btn" title="Edit (today only)"
                                                        data-visit="{{ json_encode($visitJson) }}" onclick="openVisitForm(JSON.parse(this.dataset.visit))"><i class="feather-edit-2"></i></button>
                                                @endpermission
                                            @else
                                                <span class="action-btn disabled" title="Visits can be edited on the same day only"><i class="feather-lock"></i></span>
                                            @endif
                                        </div>
                                    </td>
                                @endunless
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-ui.empty-state icon="map-pin" title="No visits found"
                                        :subtitle="$isFiltered ? 'Try changing or clearing the filters.' : ($isTeam ? 'Visits your team records this month will show here.' : 'Visits you record this month will show here.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
            <x-ui.pagination-footer :paginator="$visits" label="visits" />
        </div>
    </div>
@endsection

@section('create-modal')
    <x-ui.drawer id="visitDrawer" title="Record Visit" subtitle="The visit date is always today." width="480px">
        <form id="visitForm" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" id="visitId">
            <input type="hidden" name="latitude">
            <input type="hidden" name="longitude">
            <div id="visitFormError" class="alert alert-danger d-none" style="font-size:11.5px;"></div>

            <div class="row">
                <div class="col-12 mb-3" id="visitSiteRow">
                    <label class="fw-semibold" style="font-size:11.5px;">Site <span class="text-danger">*</span></label>
                    <select class="form-control" name="site_id" id="visitSite" style="width:100%"></select>
                    <small class="text-danger error-text site_id_error"></small>
                </div>
                <div class="col-12 mb-3 d-none" id="visitSiteFixedRow">
                    <label class="fw-semibold" style="font-size:11.5px;">Site</label>
                    <input type="text" class="form-control" id="visitSiteFixed" disabled>
                </div>
                <div class="col-12 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">Visit Date</label>
                    <input type="text" class="form-control" value="{{ now()->format('d M Y') }}" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">Contact Person Name</label>
                    <input type="text" class="form-control" name="contact_person_name" maxlength="191" placeholder="e.g. Rajesh Kumar">
                    <small class="text-danger error-text contact_person_name_error"></small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">Contact Mobile No.</label>
                    <input type="text" class="form-control" name="contact_mobile" maxlength="10" inputmode="numeric" pattern="\d{10}" placeholder="10-digit mobile number">
                    <small class="text-danger error-text contact_mobile_error"></small>
                </div>
                <div class="col-12 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">Remarks <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="remarks" rows="4" maxlength="2000" required placeholder="What was done at the site, who you met, next steps…"></textarea>
                    <small class="text-danger error-text remarks_error"></small>
                </div>
                <div class="col-12 mb-3">
                    <label class="fw-semibold" style="font-size:11.5px;">File</label>
                    <input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                    <small class="text-muted" style="font-size:10.5px;">Optional. Photo or document, up to 10 MB.</small>
                    <small class="text-danger error-text file_error d-block"></small>
                    <div class="mt-2 d-none" id="visitCurrentFile" style="font-size:11.5px;">
                        <a href="#" target="_blank" rel="noopener" id="visitCurrentFileLink"><i class="feather-paperclip"></i> Current file</a>
                        <label class="ms-3"><input type="checkbox" name="remove_file" value="1"> Remove it</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm" id="visitSaveBtn">
                    <i class="feather-check me-1"></i> <span id="visitSaveLabel">Save Visit</span>
                </button>
                <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        (function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
            const form = document.getElementById('visitForm');
            const drawerEl = document.getElementById('visitDrawer');
            const errorBox = document.getElementById('visitFormError');
            const drawer = () => bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

            $('#visitSite').select2({
                dropdownParent: $(drawerEl),
                placeholder: 'Search site by code, name or district',
                ajax: {
                    url: "{{ route('site-visits.sites') }}", dataType: 'json', delay: 250,
                    data: p => ({ search: p.term || '', page: p.page || 1 }),
                },
            });

            function clearErrors() {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                form.querySelectorAll('.error-text').forEach(el => { el.textContent = ''; });
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }

            window.openVisitForm = function (visit) {
                form.reset();
                clearErrors();
                $('#visitSite').val(null).trigger('change');
                document.getElementById('visitId').value = visit ? visit.id : '';
                document.getElementById('visitDrawerLabel').textContent = visit ? 'Edit Visit' : 'Record Visit';
                document.getElementById('visitSaveLabel').textContent = visit ? 'Update Visit' : 'Save Visit';
                document.getElementById('visitSiteRow').classList.toggle('d-none', !!visit);
                document.getElementById('visitSiteFixedRow').classList.toggle('d-none', !visit);
                document.getElementById('visitCurrentFile').classList.toggle('d-none', !(visit && visit.file_url));
                if (visit) {
                    document.getElementById('visitSiteFixed').value = visit.site;
                    ['contact_person_name', 'contact_mobile', 'remarks'].forEach(f => { form.elements[f].value = visit[f] ?? ''; });
                    if (visit.file_url) document.getElementById('visitCurrentFileLink').href = visit.file_url;
                } else if (navigator.geolocation) {
                    // Optional: where the visit was recorded from. Ignored if the browser refuses.
                    navigator.geolocation.getCurrentPosition(p => {
                        form.elements.latitude.value = p.coords.latitude.toFixed(7);
                        form.elements.longitude.value = p.coords.longitude.toFixed(7);
                    }, () => {}, { timeout: 8000 });
                }
                drawer().show();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearErrors();
                const id = document.getElementById('visitId').value;
                const data = new FormData(form);
                if (id) { data.delete('site_id'); data.delete('latitude'); data.delete('longitude'); }
                if (!data.get('latitude')) { data.delete('latitude'); data.delete('longitude'); }
                const btn = document.getElementById('visitSaveBtn');
                btn.disabled = true;
                $.ajax({
                    url: id ? "{{ url('site-visits') }}/" + id : "{{ route('site-visits.store') }}",
                    type: 'POST', data: data, processData: false, contentType: false,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                    success: r => { toastr.success(r.message); drawer().hide(); setTimeout(() => location.reload(), 800); },
                    error: x => {
                        const errors = x.responseJSON?.errors;
                        if (errors && !Array.isArray(errors)) {
                            Object.entries(errors).forEach(([field, messages]) => {
                                const slot = form.querySelector('.' + field + '_error');
                                if (slot) slot.textContent = messages[0];
                                if (form.elements[field]) form.elements[field].classList.add('is-invalid');
                            });
                            toastr.error(Object.values(errors)[0][0]);
                        } else {
                            errorBox.textContent = x.responseJSON?.message || 'Could not save the visit.';
                            errorBox.classList.remove('d-none');
                        }
                    },
                    complete: () => { btn.disabled = false; },
                });
            });
        })();
    </script>
@endsection
