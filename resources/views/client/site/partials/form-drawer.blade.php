{{-- Add / Edit site drawer + its script; shared by the Sites list and the site detail page.
     Open for a new site: openSiteForm(); for an existing one: openSiteForm(siteJson). --}}
<x-ui.drawer id="siteDrawer" title="Add Site" subtitle="The site code is generated automatically when you save.">
    <form id="siteForm" autocomplete="off">
        <input type="hidden" name="id" id="siteId">
        <div class="mb-3 d-none" id="siteCodeRow">
            <label class="form-label">Site Code</label>
            <input type="text" class="form-control" id="siteCode" disabled>
        </div>
        <div class="mb-3">
            <label class="form-label">Site Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="site_name" maxlength="191" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">State <span class="text-danger">*</span></label>
                <select class="form-select" name="state_code" required>
                    <option value="">Choose state</option>
                    @foreach ($states as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">District</label>
                <input type="text" class="form-control" name="district" maxlength="120">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Address <span class="text-danger">*</span></label>
            <textarea class="form-control" name="address" rows="3" maxlength="500" required></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" class="form-control" name="location" maxlength="191" placeholder="Landmark or area">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Latitude</label>
                <input type="number" step="any" class="form-control" name="latitude" placeholder="e.g. 28.5355">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Longitude</label>
                <input type="number" step="any" class="form-control" name="longitude" placeholder="e.g. 77.3910">
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Onboarding Date</label>
                <input type="date" class="form-control" name="onboarding_date">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm" id="siteSaveBtn">Save Site</button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</x-ui.drawer>

@push('site-scripts')
    <script>
        (function () {
            const form = document.getElementById('siteForm');
            const drawer = () => bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('siteDrawer'));
            const fields = ['site_name', 'state_code', 'district', 'address', 'location', 'latitude', 'longitude', 'onboarding_date', 'status'];

            window.openSiteForm = function (site) {
                form.reset();
                document.getElementById('siteId').value = site ? site.id : '';
                document.getElementById('siteCodeRow').classList.toggle('d-none', !site);
                document.getElementById('siteCode').value = site ? site.site_code : '';
                document.getElementById('siteDrawerLabel').textContent = site ? 'Edit Site' : 'Add Site';
                if (site) {
                    fields.forEach(f => { if (form.elements[f]) form.elements[f].value = site[f] ?? ''; });
                } else {
                    form.elements.status.value = 'active';
                }
                drawer().show();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id = document.getElementById('siteId').value;
                const btn = document.getElementById('siteSaveBtn');
                btn.disabled = true;
                $.ajax({
                    url: id ? "{{ url('sites') }}/" + id : "{{ route('sites.store') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                    data: $(form).serialize(),
                    success: r => { toastr.success(r.message); drawer().hide(); setTimeout(() => location.reload(), 800); },
                    error: x => toastr.error(x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : (x.responseJSON?.message || 'Could not save the site.')),
                    complete: () => { btn.disabled = false; },
                });
            });
        })();
    </script>
@endpush
