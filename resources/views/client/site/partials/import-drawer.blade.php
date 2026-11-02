{{-- Sites → Import drawer: upload, preview (valid rows + failed rows with reasons), then import the valid rows. --}}
<x-ui.drawer id="siteImportDrawer" title="Import Sites" width="860px"
    subtitle="Upload an Excel (.xlsx) or CSV file. Site codes are generated automatically.">
    <div class="alert alert-info small mb-3">
        <i class="feather-info me-1"></i>
        Columns: <strong>Site Name*</strong>, <strong>State*</strong>, District, <strong>Address*</strong>, Location,
        Latitude, Longitude, Onboarding Date (DD-MM-YYYY). The template's <em>States</em> sheet lists the accepted state names.
        Up to {{ \App\Services\Site\SiteImportService::MAX_ROWS }} rows per file.
        <a href="{{ route('sites.import.template') }}" class="fw-semibold ms-1"><i class="feather-download"></i> Download template</a>
    </div>

    <form id="siteImportForm" class="d-flex gap-2 align-items-end mb-3">
        <div class="flex-grow-1">
            <label class="form-label">File <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="file" accept=".xlsx,.csv" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" id="siteImportPreviewBtn"><i class="feather-eye me-1"></i> Preview</button>
    </form>

    <div id="siteImportResult" class="d-none">
        <div class="row g-2 mb-3 text-center">
            <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Rows in file</div><div class="fs-5 fw-semibold" id="impTotal">0</div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Ready to import</div><div class="fs-5 fw-semibold text-success" id="impValid">0</div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">With errors</div><div class="fs-5 fw-semibold text-danger" id="impInvalid">0</div></div></div>
        </div>

        <div id="impErrorsBox" class="d-none mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 text-danger">Rows with errors (not imported)</h6>
                <a href="#" id="impErrorsLink" class="btn btn-light btn-sm"><i class="feather-download me-1"></i> Download error rows</a>
            </div>
            <div class="table-responsive" style="max-height:240px;">
                <table class="table table-sm mb-0">
                    <thead><tr><th style="width:60px;">Row</th><th>Site Name</th><th>Problem</th></tr></thead>
                    <tbody id="impErrors"></tbody>
                </table>
            </div>
            <div class="small text-muted mt-1" id="impErrorsMore"></div>
        </div>

        <div id="impSampleBox" class="d-none mb-3">
            <h6 class="mb-2">Ready to import <span class="text-muted small fw-normal">(first 10 shown)</span></h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th style="width:60px;">Row</th><th>Site Name</th><th>State</th><th>District</th><th>Address</th><th>Onboarding</th></tr></thead>
                    <tbody id="impSample"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="siteImportConfirmBtn"></button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Close</button>
        </div>
    </div>
</x-ui.drawer>

@push('site-scripts')
    <script>
        (function () {
            const csrf = () => $('meta[name="csrf-token"]').attr('content');
            const esc = v => $('<div>').text(v ?? '').html();
            const form = document.getElementById('siteImportForm');
            const confirmBtn = document.getElementById('siteImportConfirmBtn');
            let token = null;
            let imported = false;
            document.getElementById('siteImportDrawer').addEventListener('hidden.bs.offcanvas', () => { if (imported) location.reload(); });

            window.openSiteImport = function () {
                form.reset();
                token = null;
                document.getElementById('siteImportResult').classList.add('d-none');
                bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('siteImportDrawer')).show();
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = document.getElementById('siteImportPreviewBtn');
                btn.disabled = true;
                $.ajax({
                    url: "{{ route('sites.import.preview') }}", type: 'POST', data: new FormData(form),
                    processData: false, contentType: false,
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    success: showPreview,
                    error: x => toastr.error(x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : (x.responseJSON?.message || 'Could not read the file.')),
                    complete: () => { btn.disabled = false; },
                });
            });

            function showPreview(r) {
                token = r.token;
                document.getElementById('siteImportResult').classList.remove('d-none');
                document.getElementById('impTotal').textContent = r.total;
                document.getElementById('impValid').textContent = r.valid;
                document.getElementById('impInvalid').textContent = r.invalid;

                document.getElementById('impErrorsBox').classList.toggle('d-none', r.invalid === 0);
                document.getElementById('impErrors').innerHTML = r.errors.map(e =>
                    '<tr><td>' + e.row + '</td><td>' + esc(e.site_name || '—') + '</td><td class="text-danger">' + e.messages.map(esc).join('<br>') + '</td></tr>').join('');
                document.getElementById('impErrorsMore').textContent = r.invalid > r.errors.length ? 'Showing the first ' + r.errors.length + ' — download the file for all of them.' : '';
                document.getElementById('impErrorsLink').href = "{{ url('sites/import/errors') }}/" + r.token;

                document.getElementById('impSampleBox').classList.toggle('d-none', r.valid === 0);
                document.getElementById('impSample').innerHTML = r.sample.map(s =>
                    '<tr><td>' + s.row + '</td><td>' + esc(s.site_name) + '</td><td>' + esc(s.state) + '</td><td>' + esc(s.district || '—') +
                    '</td><td>' + esc(s.address) + '</td><td>' + esc(s.onboarding_date || '—') + '</td></tr>').join('');

                confirmBtn.classList.toggle('d-none', r.valid === 0);
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Import ' + r.valid + ' valid site' + (r.valid === 1 ? '' : 's');
            }

            confirmBtn.addEventListener('click', function () {
                if (!token) return;
                confirmBtn.disabled = true;
                $.ajax({
                    url: "{{ route('sites.import.confirm') }}", type: 'POST', data: { token: token },
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    success: r => {
                        toastr.success(r.message);
                        confirmBtn.classList.add('d-none');
                        imported = true;
                        // Keep the drawer open while there are error rows to download; the list refreshes on close.
                        if (document.getElementById('impInvalid').textContent === '0') setTimeout(() => location.reload(), 1500);
                    },
                    error: x => { confirmBtn.disabled = false; toastr.error(x.responseJSON?.message || 'Import failed. Please try again.'); },
                });
            });
        })();
    </script>
@endpush
