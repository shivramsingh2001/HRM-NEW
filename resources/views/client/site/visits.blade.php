@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="My Site Visits">
        <x-slot:actions>
            @permission('site_visits', 'create')
                <button type="button" class="btn btn-primary btn-sm" onclick="openVisitForm()"><i class="feather-plus me-1"></i> Record Visit</button>
            @endpermission
        </x-slot:actions>
    </x-ui.page-header>

    <div class="content-area-body">
        <x-ui.filter-card title="Filter Visits" :clear-url="request()->hasAny(['date', 'month']) ? route('site-visits.index') : null">
            <form action="{{ route('site-visits.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" max="{{ now()->format('Y-m') }}" onchange="this.form.date.value=''; this.form.submit()">
                    </div>
                    <div class="filter-item">
                        <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" max="{{ now()->toDateString() }}" onchange="this.form.month.value=''; this.form.submit()">
                    </div>
                    <div class="filter-item"><a href="{{ route('site-visits.index') }}" class="reset-btn"><i class="feather-refresh-cw"></i> Reset</a></div>
                </div>
            </form>
        </x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Site</th>
                            <th>Contact</th>
                            <th>Remarks</th>
                            <th>File</th>
                            <th class="text-end">Actions</th>
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
                                <td class="text-nowrap">
                                    {{ $visit->visit_date?->format('d M Y') }}
                                    <div class="text-muted small">{{ $visit->visited_at?->format('h:i A') }}@if ($visit->edited_at) · edited @endif</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $visit->site->site_name ?? '—' }}</div>
                                    <div class="text-muted small">{{ $visit->site->site_code ?? '' }}{{ $visit->site?->district ? ' · ' . $visit->site->district : '' }}</div>
                                </td>
                                <td>
                                    {{ $visit->contact_person_name ?: '—' }}
                                    @if ($visit->contact_mobile)<div class="text-muted small">{{ $visit->contact_mobile }}</div>@endif
                                </td>
                                <td style="max-width:320px; white-space:pre-line;">{{ $visit->remarks }}</td>
                                <td>
                                    @if ($fileUrl)
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="feather-paperclip"></i> View</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($canEdit)
                                        @permission('site_visits', 'edit')
                                            <button type="button" class="btn btn-sm btn-light" title="Edit"
                                                data-visit="{{ json_encode($visitJson) }}" onclick="openVisitForm(JSON.parse(this.dataset.visit))"><i class="feather-edit-2"></i></button>
                                        @endpermission
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-ui.empty-state icon="map-pin" title="No visits" subtitle="Visits you record will show here." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
        </div>

        <x-ui.pagination-footer :paginator="$visits" label="visits" />
    </div>
@endsection

@section('create-modal')
    <x-ui.drawer id="visitDrawer" title="Record Visit" subtitle="The visit date is always today.">
        <form id="visitForm" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" id="visitId">
            <input type="hidden" name="latitude">
            <input type="hidden" name="longitude">
            <div class="mb-3" id="visitSiteRow">
                <label class="form-label">Site <span class="text-danger">*</span></label>
                <select class="form-select" name="site_id" id="visitSite" style="width:100%"></select>
            </div>
            <div class="mb-3 d-none" id="visitSiteFixedRow">
                <label class="form-label">Site</label>
                <input type="text" class="form-control" id="visitSiteFixed" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="text" class="form-control" value="{{ now()->format('d M Y') }}" disabled>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person Name</label>
                    <input type="text" class="form-control" name="contact_person_name" maxlength="191">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Mobile No.</label>
                    <input type="text" class="form-control" name="contact_mobile" maxlength="10" inputmode="numeric" pattern="\d{10}" placeholder="10-digit number">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Remarks <span class="text-danger">*</span></label>
                <textarea class="form-control" name="remarks" rows="4" maxlength="2000" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">File</label>
                <input type="file" class="form-control" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                <div class="form-text">Photo or document, up to 10 MB.</div>
                <div class="mt-2 d-none" id="visitCurrentFile">
                    <a href="#" target="_blank" rel="noopener" id="visitCurrentFileLink"><i class="feather-paperclip"></i> Current file</a>
                    <label class="ms-3 small"><input type="checkbox" name="remove_file" value="1"> Remove it</label>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm" id="visitSaveBtn">Save Visit</button>
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
            const drawer = () => bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

            $('#visitSite').select2({
                dropdownParent: $(drawerEl),
                placeholder: 'Search site by code, name or district',
                ajax: {
                    url: "{{ route('site-visits.sites') }}", dataType: 'json', delay: 250,
                    data: p => ({ search: p.term || '', page: p.page || 1 }),
                },
            });

            window.openVisitForm = function (visit) {
                form.reset();
                $('#visitSite').val(null).trigger('change');
                document.getElementById('visitId').value = visit ? visit.id : '';
                document.getElementById('visitDrawerLabel').textContent = visit ? 'Edit Visit' : 'Record Visit';
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
                    error: x => toastr.error(x.responseJSON?.errors && !Array.isArray(x.responseJSON.errors)
                        ? Object.values(x.responseJSON.errors)[0][0] : (x.responseJSON?.message || 'Could not save the visit.')),
                    complete: () => { btn.disabled = false; },
                });
            });
        })();
    </script>
@endsection
