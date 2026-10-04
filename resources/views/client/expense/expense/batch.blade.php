@extends('client.layout.master')

@section('style')
    @include('client.expense._ui')
    <style>
        /* ---- Pay Batch: compact everything (small font / padding / margin) ---- */
        .ex-page.batch-page {
            padding: 14px 16px !important;
        }

        .batch-page .bp-card {
            margin-bottom: 10px;
        }

        .batch-page .bp-card .card-header {
            padding: 7px 12px;
        }

        .batch-page .bp-card .card-header .card-title,
        .batch-page .bp-card .card-header .bp-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--x-text);
            margin: 0;
        }

        .batch-page .bp-card .card-body {
            padding: 10px 12px;
        }

        /* filter strip: one row, no captions */
        .batch-page .filter-wrapper {
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .batch-page .filter-row {
            align-items: center;
        }

        .batch-page .bp-alloc {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #e2e8f0;
        }

        .batch-page .bp-alloc .bp-alloc-text {
            font-size: 11.5px;
            color: #64748b;
            flex: 1 1 auto;
            min-width: 0;
        }

        .batch-page .bp-alloc .bp-alloc-text b {
            color: #1e293b;
            font-weight: 700;
        }

        .batch-page .bp-alloc .filter-select {
            flex: 0 0 210px;
        }

        .batch-page .bp-alloc .bp-amount {
            flex: 0 0 130px;
        }

        .batch-page .btn-suggest {
            height: 34px;
            font-size: 12px;
            white-space: nowrap;
            padding: 0 12px;
            border-radius: 8px;
        }

        /* payable table */
        .batch-page #payableTable th {
            white-space: nowrap;
            padding: 7px 10px;
        }

        .batch-page #payableTable td {
            padding: 6px 10px;
            font-size: 12px;
            vertical-align: middle;
        }

        .batch-page #payableTable .pay-input {
            width: 104px;
            height: 28px;
            padding: 2px 8px;
            font-size: 12px;
            text-align: right;
            border-radius: 7px;
            border: 1px solid #dfe5f0;
        }

        .batch-page #payableTable .pay-input:disabled {
            background: #f8fafc;
            color: #94a3b8;
        }

        .batch-page #payableTable .pay-input:focus {
            border-color: #0D6EFD;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
            outline: none;
        }

        .batch-page #payableTable tr.row-selected {
            background: #eef4ff;
        }

        .batch-page #payableTable tr.row-error {
            background: #fef2f2;
        }

        .batch-page #payableTable input[type="checkbox"] {
            accent-color: #0D6EFD;
        }

        .batch-page .emp-select-link {
            font-size: 10.5px;
            cursor: pointer;
            color: #0D6EFD;
            text-decoration: underline;
        }

        .batch-page .sub {
            font-size: 10.5px;
            color: #64748b;
            line-height: 1.25;
        }

        .batch-page .type-badge-advance {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .batch-page .type-badge-reimbursement {
            background: rgba(14, 165, 233, .14);
            color: #0c87c4;
        }

        /* right-hand voucher panel */
        .batch-page .batch-panel {
            position: sticky;
            top: var(--header-h, 56px); /* just below the fixed top header */
        }

        .batch-page .bp-label {
            display: block;
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2px;
        }

        .batch-page .bp-input {
            width: 100%;
            height: 32px;
            padding: 4px 8px;
            font-size: 12px;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .batch-page textarea.bp-input {
            height: auto;
            resize: vertical;
        }

        .batch-page .bp-input:focus {
            background: #fff;
            border-color: #0D6EFD;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, .10);
            outline: none;
        }

        .batch-page .total-box {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            color: #fff;
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 10px;
        }

        .batch-page .total-box .lbl {
            font-size: 10.5px;
            opacity: .8;
        }

        .batch-page .total-box .amt {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -.3px;
        }

        .batch-page .bp-btn {
            height: 34px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 9px;
            padding: 0 12px;
        }

        .batch-page .bp-btn.btn-primary {
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
            border-color: transparent;
        }

        .batch-page .bp-btn.btn-outline-primary {
            color: #0D6EFD;
            border-color: #bcd0f5;
            background: #fff;
        }

        .batch-page .bp-btn.btn-outline-primary:hover:not(:disabled) {
            background: #EFF6FF;
            border-color: #0D6EFD;
        }

        .batch-page .bp-note {
            font-size: 10.5px;
            color: #64748b;
            text-align: center;
            line-height: 1.35;
        }

        .batch-page #previewBox .err {
            color: #b91c1c;
            font-size: 11.5px;
        }

        .batch-page #previewBody {
            font-size: 12px;
        }

        .batch-page #previewBody .table td {
            padding: 4px 6px;
            font-size: 11.5px;
        }

        @media (max-width: 767.98px) {
            .batch-page .bp-alloc {
                flex-wrap: wrap;
            }

            .batch-page .bp-alloc .filter-select,
            .batch-page .bp-alloc .bp-amount {
                flex: 1 1 100%;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Pay Batch" :crumbs="[['label' => 'Expenses', 'url' => route('expense.view-all')], ['label' => 'Payments', 'url' => route('expense.payments.index')]]" :back="route('expense.payments.index')">
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('expense.vouchers.index') }}" class="btn btn-light-brand btn-sm"><i
                        class="feather-file-text me-1"></i>Vouchers</a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content ex-page batch-page">
        {{-- ============ Filters (one row) + lump-sum shortcut ============ --}}
        <x-ui.filter-card title="Filter Expenses">
            <div class="filter-row">
                <div class="filter-item">
                    <select id="f_user" class="filter-select" aria-label="Employee">
                        <option value="">All employees</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_id }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <select id="f_req" class="filter-select" aria-label="Type">
                        <option value="">Advance + Reimb.</option>
                        <option value="advance">Advance</option>
                        <option value="reimbursement">Reimbursement</option>
                    </select>
                </div>
                <div class="filter-item">
                    <select id="f_type" class="filter-select" aria-label="Category">
                        <option value="">All categories</option>
                        @foreach ($expenseTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <select id="f_project" class="filter-select" aria-label="Project">
                        <option value="">All projects</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <input type="date" id="f_from" class="filter-select filter-date" title="From date" aria-label="From date">
                </div>
                <div class="filter-item">
                    <input type="date" id="f_to" class="filter-select filter-date" title="To date" aria-label="To date">
                </div>
                <div class="filter-item search">
                    <div class="search-wrapper">
                        <i class="feather-search"></i>
                        <input type="text" id="f_search" class="form-control" placeholder="Expense no. / name…"
                            aria-label="Search" autocomplete="off">
                    </div>
                </div>
                <div class="filter-item reset">
                    <button type="button" id="btnReset" class="reset-btn" title="Reset all filters">
                        <i class="feather-refresh-cw"></i>
                    </button>
                </div>
            </div>

            <div class="bp-alloc">
                <div class="bp-alloc-text"><i class="feather-zap me-1" style="color:var(--icon-color, #0D6EFD)"></i><b>Pay a lump sum to one employee</b>
                    — the amount is spread over their outstanding expenses, oldest first.</div>
                <select id="alloc_user" class="filter-select" aria-label="Employee for lump sum">
                    <option value="">Choose employee…</option>
                    @foreach ($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_id }})</option>
                    @endforeach
                </select>
                <input type="number" id="alloc_amount" class="filter-select bp-amount" min="0.01" step="0.01"
                    placeholder="Amount ₹" style="background-image:none">
                <button type="button" id="btnAllocate" class="btn btn-outline-primary btn-suggest">Suggest lines</button>
            </div>
        </x-ui.filter-card>

        <div class="row g-2">
            {{-- ============ LEFT: payable expenses ============ --}}
            <div class="col-xl-8">
                <div class="card bp-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="bp-title">Approved &amp; unpaid</span>
                        <span class="sub" id="tableInfo">Loading…</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 66vh; overflow:auto;">
                            <table class="table table-hover mb-0" id="payableTable">
                                <thead style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width:34px"><input type="checkbox" id="selectAll"></th>
                                        <th>Employee</th>
                                        <th>Expense #</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Outstanding</th>
                                        <th class="text-end">Pay now (₹)</th>
                                    </tr>
                                </thead>
                                <tbody id="payableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ RIGHT: voucher details + totals ============ --}}
            <div class="col-xl-4">
                <div class="batch-panel">
                    <div class="card bp-card">
                        <div class="card-header"><span class="bp-title">Voucher details</span></div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="bp-label">Payment date *</label>
                                    <input type="date" id="m_date" class="bp-input" value="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-6">
                                    <label class="bp-label">Mode *</label>
                                    <select id="m_mode" class="bp-input">
                                        @foreach ($paymentModes as $m)
                                            <option value="{{ $m }}">{{ ucwords(str_replace('_', ' ', $m)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="bp-label">Reference / UTR</label>
                                    <input type="text" id="m_ref" maxlength="100" class="bp-input">
                                </div>
                                <div class="col-6">
                                    <label class="bp-label">Bank</label>
                                    <input type="text" id="m_bank" maxlength="255" class="bp-input">
                                </div>
                                <div class="col-12">
                                    <label class="bp-label">Remarks</label>
                                    <textarea id="m_remarks" rows="2" maxlength="500" class="bp-input"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="total-box">
                        <div class="lbl"><span id="selCount">0</span> expense(s) selected · max {{ $maxLines }}</div>
                        <div class="amt">₹<span id="selTotal">0.00</span></div>
                    </div>

                    <div class="d-grid gap-2 mb-2">
                        <button type="button" id="btnPreview" class="btn btn-outline-primary bp-btn" disabled>
                            <i class="feather-eye me-1"></i>Preview
                        </button>
                        <button type="button" id="btnConfirm" class="btn btn-primary bp-btn" disabled>
                            <i class="feather-check-circle me-1"></i>Confirm &amp; Pay
                        </button>
                        <div class="bp-note">Nothing is recorded until you press Confirm. The whole voucher is posted
                            together or not at all.</div>
                    </div>

                    <div id="previewBox" class="card bp-card d-none">
                        <div class="card-body py-2" id="previewBody"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            timeOut: 4000
        };

        const URLS = {
            payable: @json(route('expense.payments.batch.payable')),
            preview: @json(route('expense.payments.batch.preview')),
            allocate: @json(route('expense.payments.batch.allocate')),
            store: @json(route('expense.payments.batch.store')),
        };
        const MAX_LINES = {{ (int) $maxLines }};
        const csrf = $('meta[name="csrf-token"]').attr('content');

        // One key per "attempt at this voucher": a double-click / retry re-sends the SAME key, so the server
        // posts it once. A failed attempt (nothing posted) can be retried with it; it is renewed on success.
        let idemKey = newKey();
        let rows = []; // rows currently listed
        let selected = {}; // expense_id -> amount string (survives re-filtering)
        let previewOk = false;
        let pendingSelection = null;

        function newKey() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() :
                (Date.now().toString(36) + Math.random().toString(36).slice(2));
        }

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));
        }

        const money = n => (Math.round((parseFloat(n) || 0) * 100) / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        function filters() {
            return {
                user_id: $('#f_user').val(),
                requirement_type: $('#f_req').val(),
                expense_type: $('#f_type').val(),
                project_id: $('#f_project').val(),
                from_date: $('#f_from').val(),
                to_date: $('#f_to').val(),
                search: $('#f_search').val(),
            };
        }

        function loadRows() {
            $('#payableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted small">Loading…</td></tr>');
            $.get(URLS.payable, filters()).done(function(res) {
                rows = res.data || [];
                renderRows();
                $('#tableInfo').text(rows.length + ' shown' + (res.truncated ? ' of ' + res.total +
                    ' (narrow the filters to see the rest)' : ''));
                if (pendingSelection) {
                    selected = {};
                    pendingSelection.forEach(l => selected[l.expense_id] = String(l.amount));
                    pendingSelection = null;
                    renderRows();
                }
            }).fail(() => toastr.error('Could not load the payable expenses.'));
        }

        function renderRows() {
            if (!rows.length) {
                $('#payableBody').html(
                    '<tr><td colspan="9" class="text-center py-5 text-muted">No approved, unpaid advances or reimbursements match.</td></tr>'
                    );
                syncTotals();
                return;
            }

            let html = '';
            rows.forEach(r => {
                const isSel = selected.hasOwnProperty(r.id);
                const val = isSel ? selected[r.id] : r.outstanding;
                html += `<tr data-id="${r.id}" data-user="${r.user_id}" class="${isSel ? 'row-selected' : ''}">
                    <td><input type="checkbox" class="row-sel" ${isSel ? 'checked' : ''}></td>
                    <td><div class="fw-semibold">${esc(r.employee_name)}</div>
                        <div class="sub">${esc(r.employee_code)} · <span class="emp-select-link" data-user="${r.user_id}">select all</span></div></td>
                    <td><span class="code-link">${esc(r.expense_number)}</span><div class="sub text-truncate" style="max-width:170px" title="${esc(r.description)}">${esc(r.type_name || '')}</div></td>
                    <td><span class="badge type-badge-${esc(r.requirement_type)}">${esc(r.requirement_type)}</span></td>
                    <td>${esc(r.date || '')}</td>
                    <td class="text-end">${money(r.amount)}</td>
                    <td class="text-end">${money(r.paid)}</td>
                    <td class="text-end fw-semibold">${money(r.outstanding)}</td>
                    <td class="text-end"><input type="number" class="pay-input d-inline-block"
                        min="0.01" max="${r.outstanding}" step="0.01" value="${val}" ${isSel ? '' : 'disabled'}></td>
                </tr>`;
            });
            $('#payableBody').html(html);
            syncTotals();
        }

        function syncTotals() {
            const ids = Object.keys(selected);
            let total = 0;
            ids.forEach(id => total += Math.round((parseFloat(selected[id]) || 0) * 100));
            $('#selCount').text(ids.length);
            $('#selTotal').text(money(total / 100));
            $('#btnPreview').prop('disabled', ids.length === 0);
            $('#selectAll').prop('checked', rows.length > 0 && rows.every(r => selected.hasOwnProperty(r.id)));
            invalidatePreview();
        }

        function invalidatePreview() {
            previewOk = false;
            $('#btnConfirm').prop('disabled', true);
        }

        function linesPayload() {
            return Object.keys(selected).map(id => ({
                expense_id: parseInt(id),
                amount: selected[id]
            }));
        }

        // ---- selection ----
        $(document).on('change', '.row-sel', function() {
            const tr = $(this).closest('tr');
            const id = tr.data('id');
            const input = tr.find('.pay-input');
            if (this.checked) {
                selected[id] = input.val();
                input.prop('disabled', false);
                tr.addClass('row-selected');
            } else {
                delete selected[id];
                input.prop('disabled', true);
                tr.removeClass('row-selected');
            }
            syncTotals();
        });

        $(document).on('input', '.pay-input', function() {
            const id = $(this).closest('tr').data('id');
            if (selected.hasOwnProperty(id)) {
                selected[id] = this.value;
                syncTotals();
            }
        });

        $('#selectAll').on('change', function() {
            const on = this.checked;
            rows.forEach(r => {
                if (on) {
                    if (!selected.hasOwnProperty(r.id)) selected[r.id] = String(r.outstanding);
                } else delete selected[r.id];
            });
            renderRows();
        });

        // "select all" for one employee's rows: pays each in full.
        $(document).on('click', '.emp-select-link', function() {
            const uid = $(this).data('user');
            rows.filter(r => r.user_id === uid).forEach(r => {
                if (!selected.hasOwnProperty(r.id)) selected[r.id] = String(r.outstanding);
            });
            renderRows();
        });

        // Filters apply as you choose (no Apply button); search waits for a short pause in typing.
        $('#f_user,#f_req,#f_type,#f_project').on('change', loadRows);
        $('#f_from,#f_to').on('change', function() {
            const from = $('#f_from').val(), to = $('#f_to').val();
            if (from && to && from > to) {
                toastr.error('From date cannot be greater than To date');
                $(this).val('');
                return;
            }
            loadRows();
        });
        let searchTimer;
        $('#f_search').on('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadRows, 400);
        }).on('keydown', e => {
            if (e.key === 'Enter') {
                clearTimeout(searchTimer);
                loadRows();
            }
        });
        $('#btnReset').on('click', function() {
            $('#f_user,#f_req,#f_type,#f_project,#f_from,#f_to,#f_search').val('');
            loadRows();
        });

        // ---- lump-sum allocation ----
        $('#btnAllocate').on('click', function() {
            const user = $('#alloc_user').val(),
                amount = $('#alloc_amount').val();
            if (!user || !amount) {
                toastr.warning('Choose an employee and an amount.');
                return;
            }

            $.post(URLS.allocate, {
                    _token: csrf,
                    user_id: user,
                    amount: amount
                })
                .done(function(res) {
                    if (!res.lines.length) {
                        toastr.warning('This employee has nothing outstanding to pay.');
                        return;
                    }
                    pendingSelection = res.lines;
                    $('#f_user').val(user);
                    $('#f_req,#f_type,#f_project,#f_from,#f_to,#f_search').val('');
                    loadRows();
                    toastr.success('₹' + money(res.allocated) + ' spread over ' + res.lines.length + ' expense(s)' +
                        (res.unallocated > 0 ? ' — ₹' + money(res.unallocated) +
                            ' could not be allocated (more than they are owed).' : '.'));
                })
                .fail(xhr => toastr.error(xhr.responseJSON?.message || 'Could not allocate.'));
        });

        // ---- preview ----
        function meta() {
            return {
                payment_date: $('#m_date').val(),
                payment_mode: $('#m_mode').val(),
                reference_number: $('#m_ref').val(),
                bank_name: $('#m_bank').val(),
                remarks: $('#m_remarks').val(),
            };
        }

        $('#m_date,#m_mode,#m_ref,#m_bank,#m_remarks').on('input change', invalidatePreview);

        $('#btnPreview').on('click', function() {
            if (Object.keys(selected).length > MAX_LINES) {
                toastr.error('A voucher can hold at most ' + MAX_LINES + ' lines.');
                return;
            }
            const btn = $(this).prop('disabled', true);

            $.ajax({
                url: URLS.preview,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: csrf,
                    lines: linesPayload()
                }),
                headers: {
                    'X-CSRF-TOKEN': csrf
                },
            }).done(function(res) {
                showPreview(res);
                previewOk = res.valid;
                $('#btnConfirm').prop('disabled', !res.valid);
            }).fail(xhr => toastr.error(xhr.responseJSON?.message || 'Preview failed.'))
                .always(() => btn.prop('disabled', Object.keys(selected).length === 0));
        });

        function showPreview(res) {
            $('#payableBody tr').removeClass('row-error');
            let html = '';

            if (res.errors && res.errors.length) {
                html += '<div class="fw-semibold text-danger mb-1">Cannot pay yet:</div>';
                res.errors.forEach(e => {
                    html +=
                        `<div class="err">• ${e.expense_number ? '<b>' + esc(e.expense_number) + '</b> — ' : ''}${esc(e.message)}</div>`;
                    if (e.expense_id) $(`#payableBody tr[data-id="${e.expense_id}"]`).addClass('row-error');
                });
            }
            if (res.employees && res.employees.length) {
                html += '<div class="fw-semibold mt-2 mb-1">' + (res.valid ? 'Ready to post:' : 'Valid lines:') +
                    '</div><table class="table table-sm mb-1"><tbody>';
                res.employees.forEach(e => {
                    html +=
                        `<tr><td>${esc(e.name)} <span class="text-muted small">${esc(e.employee_id)}</span></td><td class="text-end">${e.count}</td><td class="text-end">₹${money(e.amount)}</td></tr>`;
                });
                html += `<tr class="fw-bold"><td>Total</td><td class="text-end">${res.count}</td><td class="text-end">₹${money(res.total)}</td></tr>`;
                html += '</tbody></table>';
            }
            $('#previewBody').html(html);
            $('#previewBox').removeClass('d-none');
        }

        // ---- confirm & post ----
        $('#btnConfirm').on('click', function() {
            if (!previewOk) return;

            const total = $('#selTotal').text(),
                n = $('#selCount').text();
            if (!confirm(`Post this voucher?\n\n${n} payment(s), total ₹${total}\n\nThis will credit/pay the employees now.`))
                return;

            const btn = $(this).prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span>Posting…');

            $.ajax({
                url: URLS.store,
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': csrf
                },
                data: JSON.stringify(Object.assign({
                    _token: csrf,
                    idempotency_key: idemKey,
                    lines: linesPayload()
                }, meta())),
            }).done(function(res) {
                toastr.success(res.message);
                idemKey = newKey();
                setTimeout(() => window.location = res.batch.url, 900);
            }).fail(function(xhr) {
                const r = xhr.responseJSON || {};
                toastr.error(r.message || 'Could not post the voucher.');
                if (r.line_errors) showPreview({
                    errors: r.line_errors,
                    employees: [],
                    valid: false
                });
                btn.html('<i class="feather-check-circle me-1"></i>Confirm & Pay');
                invalidatePreview(); // nothing was posted; re-preview, then retry (same key is safe)
            });
        });

        loadRows();
    </script>
@endsection
