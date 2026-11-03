{{-- Sites → Import modal: upload, preview (counts + failed rows with reasons), then import the valid rows. --}}
<x-ui.modal id="siteImportModal" title="Import Sites" size="md" bodyOnly>
    <div style="font-size:11.5px;">
        <p class="text-muted mb-2">
            Upload an Excel (.xlsx) or CSV file — up to {{ \App\Services\Site\SiteImportService::MAX_ROWS }} rows. Site codes are generated automatically.
        </p>
        <div class="d-flex justify-content-between align-items-center rounded px-2 py-2 mb-3" style="background:var(--primary-light);">
            <span>Required: <strong>Site Name</strong>, <strong>State</strong>, <strong>Address</strong></span>
            <a href="{{ route('sites.import.template') }}" class="fw-semibold text-nowrap"><i class="feather-download me-1"></i>Template</a>
        </div>

        <form id="siteImportForm">
            <label class="fw-semibold" style="font-size:11.5px;">File <span class="text-danger">*</span></label>
            <input type="file" class="form-control form-control-sm" name="file" id="siteImportFile" accept=".xlsx,.csv" required>
            <small class="text-muted" style="font-size:10.5px;">Rows are checked first; you then confirm to save the valid ones.</small>
        </form>

        <div id="siteImportResult" class="d-none mt-3">
            <div class="row g-2 mb-2 text-center">
                <div class="col-4"><div class="border rounded py-1"><div class="text-muted" style="font-size:10.5px;">Rows in file</div><div class="fw-bold" id="impTotal">0</div></div></div>
                <div class="col-4"><div class="border rounded py-1"><div class="text-muted" style="font-size:10.5px;">Ready</div><div class="fw-bold text-success" id="impValid">0</div></div></div>
                <div class="col-4"><div class="border rounded py-1"><div class="text-muted" style="font-size:10.5px;">With errors</div><div class="fw-bold text-danger" id="impInvalid">0</div></div></div>
            </div>

            <div id="impErrorsBox" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold text-danger">Rows with errors (not imported)</span>
                    <a href="#" id="impErrorsLink" class="fw-semibold text-nowrap"><i class="feather-download me-1"></i>Error rows</a>
                </div>
                <div class="border rounded" style="max-height:170px; overflow-y:auto;">
                    <table class="table table-sm mb-0" style="font-size:11px;">
                        <thead><tr><th style="width:44px;">Row</th><th>Site Name</th><th>Problem</th></tr></thead>
                        <tbody id="impErrors"></tbody>
                    </table>
                </div>
                <div class="text-muted mt-1" style="font-size:10.5px;" id="impErrorsMore"></div>
            </div>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary btn-sm" id="siteImportConfirmBtn" disabled></button>
    </x-slot:footer>
</x-ui.modal>

@push('site-scripts')
    <script>
        (function () {
            const csrf = () => $('meta[name="csrf-token"]').attr('content');
            const esc = v => $('<div>').text(v ?? '').html();
            const modalEl = document.getElementById('siteImportModal');
            const form = document.getElementById('siteImportForm');
            const confirmBtn = document.getElementById('siteImportConfirmBtn');
            const fileInput = document.getElementById('siteImportFile');
            let token = null;
            let validCount = 0;
            let imported = false;
            modalEl.addEventListener('hidden.bs.modal', () => { if (imported) location.reload(); });

            // One footer button for both steps: "Check File" before a preview, "Save N Sites" after it.
            function setButton(label, icon, enabled) {
                confirmBtn.innerHTML = '<i class="feather-' + icon + ' me-1"></i> ' + label;
                confirmBtn.disabled = !enabled;
            }

            function resetPreview() {
                token = null;
                validCount = 0;
                document.getElementById('siteImportResult').classList.add('d-none');
                setButton('Check File', 'eye', fileInput.files.length > 0);
            }

            window.openSiteImport = function () {
                form.reset();
                resetPreview();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            fileInput.addEventListener('change', resetPreview);
            form.addEventListener('submit', e => { e.preventDefault(); confirmBtn.click(); });

            function preview() {
                if (!fileInput.files.length) { toastr.error('Choose a file first.'); return; }
                setButton('Checking…', 'loader', false);
                $.ajax({
                    url: "{{ route('sites.import.preview') }}", type: 'POST', data: new FormData(form),
                    processData: false, contentType: false,
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    success: showPreview,
                    error: x => {
                        setButton('Check File', 'eye', true);
                        toastr.error(x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : (x.responseJSON?.message || 'Could not read the file.'));
                    },
                });
            }

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

                validCount = r.valid;
                if (r.valid === 0) {
                    setButton('Nothing to Save', 'slash', false);
                } else {
                    setButton('Save ' + r.valid + ' Site' + (r.valid === 1 ? '' : 's'), 'check', true);
                }
            }

            confirmBtn.addEventListener('click', function () {
                if (!token) { preview(); return; }
                if (validCount === 0) return;
                setButton('Saving…', 'loader', false);
                $.ajax({
                    url: "{{ route('sites.import.confirm') }}", type: 'POST', data: { token: token },
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    success: r => {
                        toastr.success(r.message);
                        imported = true;
                        validCount = 0;
                        setButton('Saved', 'check-circle', false);
                        // Keep the modal open while there are error rows to download; the list refreshes on close.
                        if (document.getElementById('impInvalid').textContent === '0') setTimeout(() => location.reload(), 1500);
                    },
                    error: x => {
                        setButton('Save ' + validCount + ' Site' + (validCount === 1 ? '' : 's'), 'check', true);
                        toastr.error(x.responseJSON?.message || 'Import failed. Please try again.');
                    },
                });
            });
        })();
    </script>
@endpush
