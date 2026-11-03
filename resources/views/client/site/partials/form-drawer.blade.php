{{-- Add / Edit site drawer + its script; shared by the Sites list and the site detail page.
     Open for a new site: openSiteForm(); for an existing one: openSiteForm(siteJson). --}}
<x-ui.drawer id="siteDrawer" title="Add Site" subtitle="The site code is generated automatically when you save." width="480px">
    <form id="siteForm" autocomplete="off">
        <input type="hidden" name="id" id="siteId">
        <div id="siteFormError" class="alert alert-danger d-none" style="font-size:11.5px;"></div>

        <div class="row">
            <div class="col-12 mb-3 d-none" id="siteCodeRow">
                <label class="fw-semibold" style="font-size:11.5px;">Site Code</label>
                <input type="text" class="form-control" id="siteCode" disabled>
            </div>
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Site Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="site_name" maxlength="191" required placeholder="e.g. Noida Sector 62 Warehouse">
                <small class="text-danger error-text site_name_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Onboarding Date</label>
                <input type="date" class="form-control" name="onboarding_date" placeholder="Select onboarding date">
                <small class="text-danger error-text onboarding_date_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Status</label>
                <select class="form-control" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <small class="text-danger error-text status_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">State <span class="text-danger">*</span></label>
                <select class="form-control" name="state_code" id="siteState" required>
                    <option value="">-- Select state --</option>
                    @foreach ($states as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
                <small class="text-danger error-text state_code_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">District</label>
                <select class="form-control" name="district" id="siteDistrict" disabled>
                    <option value="">-- Select state first --</option>
                </select>
                <small class="text-danger error-text district_error"></small>
            </div>
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" name="address" rows="3" maxlength="500" required placeholder="Building, street, area, city, PIN code"></textarea>
                <small class="text-danger error-text address_error"></small>
            </div>
            <div class="col-12 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Landmark / Area</label>
                <input type="text" class="form-control" name="location" maxlength="191" placeholder="e.g. Near City Centre Metro Station">
                <small class="text-danger error-text location_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Latitude</label>
                <input type="number" step="any" min="-90" max="90" class="form-control" name="latitude" placeholder="e.g. 28.5355">
                <small class="text-danger error-text latitude_error"></small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="fw-semibold" style="font-size:11.5px;">Longitude</label>
                <input type="number" step="any" min="-180" max="180" class="form-control" name="longitude" placeholder="e.g. 77.3910">
                <small class="text-danger error-text longitude_error"></small>
            </div>
            <div class="col-12 mb-2">
                <small class="text-muted" style="font-size:11px;">Optional. Latitude and longitude pin the site on the map.</small>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm" id="siteSaveBtn">
                <i class="feather-check me-1"></i> <span id="siteSaveLabel">Save Site</span>
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>

@push('site-scripts')
    <script>
        (function () {
            const form = document.getElementById('siteForm');
            const errorBox = document.getElementById('siteFormError');
            const drawer = () => bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('siteDrawer'));
            const fields = ['site_name', 'state_code', 'address', 'location', 'latitude', 'longitude', 'onboarding_date', 'status'];
            const stateSelect = document.getElementById('siteState');
            const districtSelect = document.getElementById('siteDistrict');
            const titleCase = s => String(s).toLowerCase().replace(/\b\w/g, c => c.toUpperCase());

            // District is stored as text; the list comes from the state's cities (districts) master.
            function loadDistricts(stateCode, selected) {
                districtSelect.innerHTML = '';
                districtSelect.add(new Option(stateCode ? 'Loading…' : '-- Select state first --', ''));
                districtSelect.disabled = true;
                if (!stateCode) return;
                $.get("{{ route('get.cities') }}", { state_code: stateCode }, function (rows) {
                    districtSelect.innerHTML = '';
                    districtSelect.add(new Option('-- Select district --', ''));
                    const names = rows.map(r => titleCase(r.name));
                    names.forEach(n => districtSelect.add(new Option(n, n)));
                    // An older or imported site may hold a district that isn't in the list.
                    if (selected && !names.some(n => n.toLowerCase() === String(selected).toLowerCase())) {
                        districtSelect.add(new Option(selected, selected));
                    }
                    if (selected) {
                        const match = [...districtSelect.options].find(o => o.value.toLowerCase() === String(selected).toLowerCase());
                        if (match) districtSelect.value = match.value;
                    }
                    districtSelect.disabled = false;
                }).fail(() => {
                    districtSelect.innerHTML = '';
                    districtSelect.add(new Option('Could not load districts', ''));
                });
            }

            stateSelect.addEventListener('change', () => loadDistricts(stateSelect.value, ''));

            function clearErrors() {
                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                form.querySelectorAll('.error-text').forEach(el => { el.textContent = ''; });
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            }

            window.openSiteForm = function (site) {
                form.reset();
                clearErrors();
                document.getElementById('siteId').value = site ? site.id : '';
                document.getElementById('siteCodeRow').classList.toggle('d-none', !site);
                document.getElementById('siteCode').value = site ? site.site_code : '';
                document.getElementById('siteDrawerLabel').textContent = site ? 'Edit Site' : 'Add Site';
                document.getElementById('siteSaveLabel').textContent = site ? 'Update Site' : 'Save Site';
                if (site) {
                    fields.forEach(f => { if (form.elements[f]) form.elements[f].value = site[f] ?? ''; });
                } else {
                    form.elements.status.value = 'active';
                }
                loadDistricts(site ? site.state_code : '', site ? site.district : '');
                drawer().show();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearErrors();
                const id = document.getElementById('siteId').value;
                const btn = document.getElementById('siteSaveBtn');
                btn.disabled = true;
                $.ajax({
                    url: id ? "{{ url('sites') }}/" + id : "{{ route('sites.store') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                    data: $(form).serialize(),
                    success: r => { toastr.success(r.message); drawer().hide(); setTimeout(() => location.reload(), 800); },
                    error: x => {
                        const errors = x.responseJSON?.errors;
                        if (errors) {
                            Object.entries(errors).forEach(([field, messages]) => {
                                const slot = form.querySelector('.' + field + '_error');
                                if (slot) slot.textContent = messages[0];
                                if (form.elements[field]) form.elements[field].classList.add('is-invalid');
                            });
                            toastr.error(Object.values(errors)[0][0]);
                        } else {
                            errorBox.textContent = x.responseJSON?.message || 'Could not save the site.';
                            errorBox.classList.remove('d-none');
                        }
                    },
                    complete: () => { btn.disabled = false; },
                });
            });
        })();
    </script>
@endpush
