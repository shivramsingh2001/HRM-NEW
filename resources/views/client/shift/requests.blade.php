@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .sr-page .content-area-body { padding: 10px 14px !important; }
        .sr-page .nav-tabs .nav-link { padding: 8px 14px; font-size: 13px; font-weight: 600; }
        .sr-page .nav-tabs .count { font-size: 10px; font-weight: 700; color: #fff; background: var(--primary, #0D6EFD); border-radius: 999px; padding: 1px 7px; margin-left: 4px; }
        .sr-policy { font-size: 11px; color: #64748b; display: flex; flex-wrap: wrap; gap: 4px 14px; margin-bottom: 8px; }
        .sr-policy i { color: var(--icon-color, #0D6EFD); margin-right: 3px; }
        .sr-chip { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 6px; border-left: 3px solid var(--c, #4f46e5); background: #f8fafc; }
        .sr-arrow { color: #94a3b8; margin: 0 4px; }
        .sr-no { font-weight: 700; color: var(--primary, #0D6EFD); cursor: pointer; }
        .sr-sub { font-size: 10px; color: #64748b; }
        .sr-timeline { list-style: none; padding: 0; margin: 0; }
        .sr-timeline li { border-left: 2px solid var(--primary-light, #EFF6FF); padding: 2px 0 10px 12px; position: relative; font-size: 12px; }
        .sr-timeline li::before { content: ''; position: absolute; left: -5px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--primary, #0D6EFD); }
        .sr-timeline .meta { font-size: 10.5px; color: #64748b; }
        .sr-kv { font-size: 12px; } .sr-kv td { padding: 3px 6px; } .sr-kv td:first-child { color: #64748b; width: 34%; }
        .msg-box { font-size: 12px; padding: 6px 10px; border-radius: 8px; margin-bottom: 6px; }
        .msg-box.err { background: #fef2f2; color: #b91c1c; }
        .msg-box.warn { background: #fffbeb; color: #92400e; }
        .sr-preview table { font-size: 12px; margin-bottom: 6px; }
        .sr-preview .chg { color: var(--primary, #0D6EFD); font-weight: 600; }
        .type-toggle .btn { font-size: 12px; }
        .type-toggle .btn.active { background: var(--primary, #0D6EFD); color: #fff; }
    </style>
@endsection

@section('content-area')
    <div class="sr-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Shift Requests" :crumbs="[['label' => 'Shift']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    @if ($isApprover)
                        <a href="{{ route('shift.roster') }}" class="btn-reset"><i class="feather-grid"></i>Roster</a>
                    @endif
                    @if ($settings->swap_enabled || $settings->change_enabled)
                        <a href="#" class="btn-grad" id="newRequestBtn"><i class="feather-plus"></i>New Request</a>
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <div class="sr-policy">
                <span><i class="feather-clock"></i>At least {{ $settings->min_notice_hours }} h notice</span>
                @if ($workingTime->minRestHours)<span><i class="feather-moon"></i>At least {{ rtrim(rtrim(number_format((float) $workingTime->minRestHours, 2), '0'), '.') }} h rest between shifts</span>@endif
                @if ($workingTime->maxDailyHours)<span><i class="feather-sun"></i>Up to {{ rtrim(rtrim(number_format((float) $workingTime->maxDailyHours, 2), '0'), '.') }} h a day</span>@endif
                @if ($settings->max_requests_per_month)<span><i class="feather-hash"></i>Up to {{ $settings->max_requests_per_month }} requests a month</span>@endif
                @if ($settings->swap_enabled)<span><i class="feather-user-check"></i>Colleague answers within {{ $settings->peer_response_hours }} h</span>@endif
                <span><i class="feather-check-circle"></i>{{ $settings->requires_approval ? 'Manager / HR approval needed' : 'No approval needed' }}</span>
                @if (! $settings->swap_enabled)<span class="text-danger">Swaps are switched off</span>@endif
                @if (! $settings->change_enabled)<span class="text-danger">Change requests are switched off</span>@endif
            </div>

            <div class="card stretch stretch-full">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#" data-tab="mine">My Requests</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#" data-tab="to_me">Swaps Asked of Me @if ($counts['to_me'])<span class="count">{{ $counts['to_me'] }}</span>@endif</a></li>
                        @if ($isApprover)
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#" data-tab="approvals">Approvals @if ($counts['approvals'])<span class="count">{{ $counts['approvals'] }}</span>@endif</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#" data-tab="all">All Requests</a></li>
                        @endif
                    </ul>
                </div>
                <div class="card-body">
                    <form class="filter-row mb-2 d-flex flex-wrap gap-2 align-items-end" id="srFilter" onsubmit="return false">
                        <div class="filter-item search">
                            <input type="text" class="form-control form-control-sm" id="f_search" placeholder="Request no / employee" style="width:190px">
                        </div>
                        <div class="filter-item">
                            <select class="form-control form-control-sm" id="f_status" aria-label="Status">
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                @foreach (\App\Models\ShiftRequest::STATUS_LABELS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <select class="form-control form-control-sm" id="f_type" aria-label="Type">
                                <option value="">All types</option><option value="swap">Shift swap</option><option value="change">Shift change</option>
                            </select>
                        </div>
                        <div class="filter-item"><input type="date" class="form-control form-control-sm" id="f_from" title="Shift dates from" aria-label="From"></div>
                        <div class="filter-item"><input type="date" class="form-control form-control-sm" id="f_to" title="Shift dates to" aria-label="To"></div>
                        <a href="#" class="btn-reset" id="f_reset">Reset</a>
                        @if ($isApprover)
                            <a href="#" class="btn btn-sm btn-light ms-auto" id="exportBtn"><i class="feather-download me-1"></i>CSV</a>
                        @endif
                    </form>

                    <div id="bulkBar" class="alert alert-secondary d-none align-items-center gap-2 py-1 px-2" style="font-size:12px">
                        <span><strong id="bulkCount">0</strong> selected</span>
                        <button class="btn btn-sm btn-success" id="bulkApprove"><i class="feather-check me-1"></i>Approve</button>
                        <button class="btn btn-sm btn-outline-danger" id="bulkReject"><i class="feather-x me-1"></i>Reject</button>
                    </div>

                    <x-ui.data-table>
                        <thead>
                            <tr>
                                <th style="width:28px" class="bulk-col d-none"><input type="checkbox" id="selectAll"></th>
                                <th>Request</th><th>Employee(s)</th><th>Shift dates</th><th>Change</th><th>Status</th><th>Raised</th><th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="srBody"><tr><td colspan="8" class="text-center py-3 text-muted">Loading…</td></tr></tbody>
                    </x-ui.data-table>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-muted" style="font-size:11px" id="srInfo"></span>
                        <ul class="pagination pagination-sm mb-0" id="srPager"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    {{-- New request (swap with a colleague / change my shift; HR & admin may raise for an employee) --}}
    <div class="modal fade modal-custom" id="newModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">New Shift Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="newForm" autocomplete="off">
                        <div class="btn-group type-toggle mb-2" role="group">
                            @if ($settings->swap_enabled)<button type="button" class="btn btn-outline-primary active" data-type="swap"><i class="feather-repeat me-1"></i>Swap with a colleague</button>@endif
                            @if ($settings->change_enabled)<button type="button" class="btn btn-outline-primary {{ $settings->swap_enabled ? '' : 'active' }}" data-type="change"><i class="feather-edit-2 me-1"></i>Change my shift</button>@endif
                        </div>
                        <input type="hidden" name="type" id="n_type" value="{{ $settings->swap_enabled ? 'swap' : 'change' }}">
                        <div class="row g-2">
                            @if (in_array(Auth::user()->role, ['admin', 'hr'], true))
                                <div class="col-12"><label class="form-label">For employee <span class="text-muted">(leave empty for yourself)</span></label>
                                    <select class="form-control" name="on_behalf_user_id" id="n_behalf">
                                        <option value="">Myself</option>
                                        @foreach ($directUsers as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id }})</option>@endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-md-3"><label class="form-label">From date</label><input type="date" class="form-control" name="start_date" id="n_from" min="{{ now()->toDateString() }}" required></div>
                            <div class="col-md-3"><label class="form-label">To date <span class="text-muted">(optional)</span></label><input type="date" class="form-control" name="end_date" id="n_to" min="{{ now()->toDateString() }}"></div>
                            <div class="col-md-6 swap-only"><label class="form-label">Swap with</label>
                                <select class="form-control" name="counterpart_id" id="n_colleague"><option value="">Pick a date first</option></select>
                            </div>
                            <div class="col-md-6 change-only d-none"><label class="form-label">Shift you want</label>
                                <select class="form-control" name="to_shift_id" id="n_shift">
                                    <option value="">Select shift</option>
                                    @foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ \Carbon\Carbon::parse($s->start_time)->format('h:i A') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('h:i A') }})</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label">Reason</label><input type="text" class="form-control" name="reason" id="n_reason" maxlength="500" placeholder="Optional — helps your manager decide"></div>
                        </div>
                        <div class="text-muted mt-1" style="font-size:11px" id="n_myshift"></div>
                        <div class="sr-preview mt-2" id="n_preview"></div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="newForm" class="btn btn-primary btn-sm" id="n_submit" disabled>Send Request</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Request details + timeline --}}
    <div class="modal fade modal-custom" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px" id="d_title">Shift Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="d_body"><div class="text-muted">Loading…</div></div>
                <div class="modal-footer py-2" id="d_actions"></div>
            </div>
        </div>
    </div>

    {{-- Remarks / reason prompt for accept, decline, approve, reject, cancel, revert --}}
    <div class="modal fade modal-custom" id="actModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px" id="a_title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="mb-2" style="font-size:12px" id="a_text"></p>
                    <label class="form-label" id="a_label">Remarks</label>
                    <textarea class="form-control" id="a_remarks" rows="2" maxlength="500"></textarea>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="a_go">Confirm</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const esc = s => $('<div>').text(s ?? '').html();
            const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
            const urls = {
                data: "{{ route('shift.requests.data') }}",
                show: id => "{{ url('shift/requests') }}/" + id,
                act: (id, a) => "{{ url('shift/requests') }}/" + id + '/' + a,
                preview: "{{ route('shift.requests.preview') }}",
                store: "{{ route('shift.requests.store') }}",
                candidates: "{{ route('shift.requests.candidates') }}",
                bulk: "{{ route('shift.requests.bulk-decide') }}",
                export: "{{ $isApprover ? route('shift.requests.export') : '' }}",
            };
            let tab = 'mine', page = 1;

            /* ---------------- list ---------------- */
            const chip = s => s ? '<span class="sr-chip" style="--c:' + esc(s.color_code || '#4f46e5') + '">' + esc(s.name) + '</span>' : '<span class="text-muted">No shift</span>';
            function people(r) {
                const p = r => r ? esc(r.name) + ' <span class="sr-sub">' + esc(r.employee_id || '') + '</span>' : '';
                return p(r.requester) + (r.counterpart ? '<div class="sr-sub"><i class="feather-repeat"></i> ' + p(r.counterpart) + '</div>' : '')
                    + (r.on_behalf ? '<div class="sr-sub">raised by ' + esc(r.created_by) + '</div>' : '');
            }
            function actions(r) {
                let h = '<div class="row-actions d-inline-flex gap-1">';
                h += '<a href="#" class="action-btn" data-open="' + r.id + '" title="Details & history"><i class="feather-eye"></i></a>';
                if (r.can.respond) h += '<a href="#" class="action-btn approve" data-act="accept" data-id="' + r.id + '" title="Accept swap"><i class="feather-check"></i></a><a href="#" class="action-btn reject" data-act="decline" data-id="' + r.id + '" title="Decline swap"><i class="feather-x"></i></a>';
                if (r.can.decide) h += '<a href="#" class="action-btn approve" data-act="approve" data-id="' + r.id + '" title="Approve"><i class="feather-check-circle"></i></a><a href="#" class="action-btn reject" data-act="reject" data-id="' + r.id + '" title="Reject"><i class="feather-x-circle"></i></a>';
                if (r.can.cancel) h += '<a href="#" class="action-btn warning" data-act="cancel" data-id="' + r.id + '" title="Cancel request"><i class="feather-slash"></i></a>';
                if (r.can.revert) h += '<a href="#" class="action-btn warning" data-act="revert" data-id="' + r.id + '" title="Revert"><i class="feather-rotate-ccw"></i></a>';
                return h + '</div>';
            }
            function load() {
                const bulk = tab === 'approvals';
                $('.bulk-col').toggleClass('d-none', !bulk);
                $('#bulkBar').addClass('d-none').removeClass('d-flex');
                $('#srBody').html('<tr><td colspan="8" class="text-center py-3 text-muted">Loading…</td></tr>');
                $.get(urls.data, { tab, page, status: $('#f_status').val(), type: $('#f_type').val(), from: $('#f_from').val(), to: $('#f_to').val(), search: $('#f_search').val() }, r => {
                    if (!r.data.length) {
                        $('#srBody').html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="feather-inbox d-block mb-1" style="font-size:20px"></i>' +
                            ({ mine: 'You have no shift requests yet.', to_me: 'No colleague has asked you for a swap.', approvals: 'Nothing is waiting for your approval.', all: 'No shift requests match these filters.' }[tab]) + '</td></tr>');
                    } else {
                        $('#srBody').html(r.data.map(x => '<tr>' +
                            '<td class="bulk-col ' + (bulk ? '' : 'd-none') + '">' + (x.can.decide ? '<input type="checkbox" class="row-sel" value="' + x.id + '">' : '') + '</td>' +
                            '<td><span class="sr-no" data-open="' + x.id + '">' + esc(x.request_no) + '</span><div class="sr-sub">' + esc(x.type_label) + (x.mode === 'direct' ? ' · direct' : '') + '</div></td>' +
                            '<td>' + people(x) + '</td>' +
                            '<td>' + esc(x.dates_label) + (x.expires_at ? '<div class="sr-sub">decide by ' + esc(x.expires_at) + '</div>' : '') + '</td>' +
                            '<td>' + chip(x.from_shift) + '<span class="sr-arrow">→</span>' + chip(x.to_shift) + '</td>' +
                            '<td><span class="badge ' + esc(x.status_badge) + '">' + esc(x.status_label) + '</span></td>' +
                            '<td><span class="sr-sub">' + esc(x.created_at) + '</span></td>' +
                            '<td class="text-end">' + actions(x) + '</td></tr>').join(''));
                    }
                    const p = r.pagination;
                    $('#srInfo').text(p.total ? 'Showing ' + p.from + '–' + p.to + ' of ' + p.total : '');
                    const $ul = $('#srPager').empty();
                    if (p.last_page > 1) {
                        for (let i = 1; i <= p.last_page; i++) {
                            if (i === 1 || i === p.last_page || Math.abs(i - p.current_page) <= 2) $ul.append('<li class="page-item ' + (i === p.current_page ? 'active' : '') + '"><a class="page-link" href="#" data-pg="' + i + '">' + i + '</a></li>');
                        }
                    }
                }).fail(x => $('#srBody').html('<tr><td colspan="8" class="text-center text-danger py-3">' + esc(x.responseJSON?.message || 'Failed to load requests.') + '</td></tr>'));
            }
            $('[data-tab]').on('click', function (e) { e.preventDefault(); tab = $(this).data('tab'); page = 1; $('[data-tab]').removeClass('active'); $(this).addClass('active'); load(); });
            $(document).on('click', '#srPager .page-link', function (e) { e.preventDefault(); page = +$(this).data('pg'); load(); });
            $('#f_status,#f_type,#f_from,#f_to').on('change', () => { page = 1; load(); });
            let sTimer; $('#f_search').on('input', () => { clearTimeout(sTimer); sTimer = setTimeout(() => { page = 1; load(); }, 400); });
            $('#f_reset').on('click', e => { e.preventDefault(); $('#srFilter')[0].reset(); page = 1; load(); });
            $('#exportBtn').on('click', e => { e.preventDefault(); window.location = urls.export + '?' + $.param({ status: $('#f_status').val(), type: $('#f_type').val(), from: $('#f_from').val(), to: $('#f_to').val() }); });

            /* ---------------- bulk approve ---------------- */
            const selected = () => $('.row-sel:checked').map((i, el) => +el.value).get();
            $(document).on('change', '.row-sel', () => { const n = selected().length; $('#bulkCount').text(n); $('#bulkBar').toggleClass('d-none', !n).toggleClass('d-flex', !!n); });
            $('#selectAll').on('change', function () { $('.row-sel').prop('checked', this.checked).first().trigger('change'); });
            function bulk(action) {
                const ids = selected(); if (!ids.length) return;
                $.ajax({ url: urls.bulk, type: 'POST', data: { ids, action }, headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message); load(); }, error: x => { toastr.error(x.responseJSON?.message || 'Failed'); load(); } });
            }
            $('#bulkApprove').on('click', () => bulk('approved'));
            $('#bulkReject').on('click', () => bulk('rejected'));

            /* ---------------- details ---------------- */
            function openDetail(id) {
                $('#d_body').html('<div class="text-muted">Loading…</div>'); $('#d_actions').empty();
                modal('detailModal').show();
                $.get(urls.show(id), r => {
                    const d = r.data;
                    $('#d_title').text(d.request_no + ' · ' + d.type_label);
                    let h = '<table class="table table-borderless sr-kv mb-2"><tbody>' +
                        '<tr><td>Status</td><td><span class="badge ' + esc(d.status_badge) + '">' + esc(d.status_label) + '</span>' + (d.expires_at ? ' <span class="sr-sub">decide by ' + esc(d.expires_at) + '</span>' : '') + '</td></tr>' +
                        '<tr><td>Employee</td><td>' + esc(d.requester?.name) + '</td></tr>' +
                        (d.counterpart ? '<tr><td>Swap with</td><td>' + esc(d.counterpart.name) + '</td></tr>' : '') +
                        '<tr><td>Raised</td><td>' + esc(d.created_at) + ' by ' + esc(d.created_by) + (d.mode === 'direct' ? ' (direct change, no request)' : '') + (d.channel === 'mobile' ? ' · mobile app' : '') + '</td></tr>' +
                        (d.reason ? '<tr><td>Reason</td><td>' + esc(d.reason) + '</td></tr>' : '') +
                        (d.peer_remarks ? '<tr><td>Colleague said</td><td>' + esc(d.peer_remarks) + '</td></tr>' : '') +
                        (d.decided_by ? '<tr><td>Decided</td><td>' + esc(d.decided_by) + ' · ' + esc(d.decided_at) + (d.approver_remarks ? ' — ' + esc(d.approver_remarks) : '') + '</td></tr>' : '') +
                        '</tbody></table>';
                    h += '<h6 style="font-size:12px">Shifts</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Employee</th><th>Date</th><th>Was</th><th>Becomes</th></tr></thead><tbody>' +
                        d.items.map(i => '<tr><td>' + esc(i.user_name) + '</td><td>' + esc(i.date_label) + '</td><td>' + esc(i.from ? i.from.name + ' · ' + i.from.time : 'No shift') + '</td><td class="text-primary fw-semibold">' + esc(i.to ? i.to.name + ' · ' + i.to.time : '—') + '</td></tr>').join('') +
                        '</tbody></table></div>';
                    h += '<h6 style="font-size:12px" class="mt-2">History</h6><ul class="sr-timeline">' + d.events.map(e => '<li><strong>' + esc(e.label) + '</strong>' +
                        '<div class="meta">' + esc(e.actor) + (e.actor_role ? ' (' + esc(e.actor_role) + ')' : '') + ' · ' + esc(e.at) + (e.channel !== 'web' ? ' · ' + esc(e.channel) : '') + (e.ip ? ' · IP ' + esc(e.ip) : '') + '</div>' +
                        (e.remarks ? '<div class="meta">“' + esc(e.remarks) + '”</div>' : '') + '</li>').join('') + '</ul>';
                    $('#d_body').html(h);
                    const b = (act, cls, label) => '<button class="btn btn-sm ' + cls + '" data-act="' + act + '" data-id="' + d.id + '">' + label + '</button>';
                    $('#d_actions').html((d.can.respond ? b('decline', 'btn-outline-danger', 'Decline') + b('accept', 'btn-success', 'Accept swap') : '') +
                        (d.can.decide ? b('reject', 'btn-outline-danger', 'Reject') + b('approve', 'btn-success', 'Approve') : '') +
                        (d.can.cancel ? b('cancel', 'btn-outline-warning', 'Cancel request') : '') +
                        (d.can.revert ? b('revert', 'btn-outline-warning', 'Revert') : '') +
                        '<button class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>');
                }).fail(x => $('#d_body').html('<div class="text-danger">' + esc(x.responseJSON?.message || 'Request not found.') + '</div>'));
            }
            $(document).on('click', '[data-open]', function (e) { e.preventDefault(); openDetail($(this).data('open')); });

            /* ---------------- actions ---------------- */
            const ACTS = {
                accept: { title: 'Accept swap', text: 'You will work the shift(s) shown once the swap is approved.', label: 'Message (optional)', url: 'respond', data: r => ({ accept: 1, remarks: r }), cls: 'btn-success' },
                decline: { title: 'Decline swap', text: 'Your colleague will be told you declined.', label: 'Message (optional)', url: 'respond', data: r => ({ accept: 0, remarks: r }), cls: 'btn-danger' },
                approve: { title: 'Approve request', text: 'The shifts are updated as soon as you approve.', label: 'Remarks (optional)', url: 'decide', data: r => ({ action: 'approved', remarks: r }), cls: 'btn-success' },
                reject: { title: 'Reject request', text: 'The employees keep their current shifts.', label: 'Reason', url: 'decide', data: r => ({ action: 'rejected', remarks: r }), cls: 'btn-danger', required: true },
                cancel: { title: 'Cancel request', text: 'The request is withdrawn; nothing changes.', label: 'Remarks (optional)', url: 'cancel', data: r => ({ remarks: r }), cls: 'btn-warning' },
                revert: { title: 'Revert change', text: 'Every day of this request goes back to the shift it had before. Both employees are notified.', label: 'Reason', url: 'revert', data: r => ({ reason: r }), cls: 'btn-warning', required: true },
            };
            let pending = null;
            $(document).on('click', '[data-act]', function (e) {
                e.preventDefault();
                const a = ACTS[$(this).data('act')]; pending = { a, id: $(this).data('id') };
                $('#a_title').text(a.title); $('#a_text').text(a.text); $('#a_label').html(esc(a.label) + (a.required ? ' <span class="text-danger">*</span>' : ''));
                $('#a_remarks').val(''); $('#a_go').attr('class', 'btn btn-sm ' + a.cls).text(a.title).prop('disabled', false);
                modal('actModal').show();
            });
            $('#a_go').on('click', function () {
                if (!pending) return;
                const remarks = $('#a_remarks').val().trim();
                if (pending.a.required && !remarks) { toastr.warning('Please enter the ' + pending.a.label.toLowerCase() + '.'); return; }
                $(this).prop('disabled', true);
                $.ajax({ url: urls.act(pending.id, pending.a.url), type: 'POST', data: pending.a.data(remarks), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { modal('actModal').hide(); bootstrap.Modal.getInstance(document.getElementById('detailModal'))?.hide(); toastr.success(r.message); load(); },
                    error: x => { $(this).prop('disabled', false); toastr.error(x.responseJSON?.message || 'Failed'); }
                });
            });

            /* ---------------- new request ---------------- */
            $('#n_colleague').select2({ width: '100%', dropdownParent: $('#newModal'), placeholder: 'Pick a colleague' });
            $('#n_behalf').select2({ width: '100%', dropdownParent: $('#newModal') });
            const nType = () => $('#n_type').val();
            $('.type-toggle .btn').on('click', function () {
                $('.type-toggle .btn').removeClass('active'); $(this).addClass('active');
                $('#n_type').val($(this).data('type'));
                $('.swap-only').toggleClass('d-none', nType() !== 'swap'); $('.change-only').toggleClass('d-none', nType() !== 'change');
                runPreview();
            });
            $('#newRequestBtn').on('click', e => {
                e.preventDefault();
                $('#newForm')[0].reset(); $('#n_preview,#n_myshift').empty(); $('#n_submit').prop('disabled', true);
                $('#n_colleague').html('<option value="">Pick a date first</option>').trigger('change.select2');
                $('#n_behalf').val('').trigger('change.select2');
                $('.type-toggle .btn.active').trigger('click');
                modal('newModal').show();
            });
            function loadColleagues() {
                if (nType() !== 'swap' || !$('#n_from').val()) return;
                $.get(urls.candidates, { date: $('#n_from').val(), on_behalf_user_id: $('#n_behalf').val() || '' }, r => {
                    const d = r.data;
                    $('#n_myshift').text(d.my_shift ? 'Shift on ' + $('#n_from').val() + ': ' + d.my_shift.name + ' (' + d.my_shift.time + ')' : 'No shift on ' + $('#n_from').val() + ' — only working days can be swapped.');
                    const keep = $('#n_colleague').val();
                    $('#n_colleague').html('<option value="">Pick a colleague</option>' + d.colleagues.map(c =>
                        '<option value="' + c.id + '"' + (c.can_swap ? '' : ' disabled') + '>' + esc(c.name) + ' (' + esc(c.employee_id || '') + ') — ' + esc(c.shift ? c.shift.name + ' ' + c.shift.time : 'no shift') + '</option>').join(''));
                    $('#n_colleague').val(keep).trigger('change.select2');
                });
            }
            let pTimer;
            function runPreview() {
                clearTimeout(pTimer);
                const ready = $('#n_from').val() && (nType() === 'swap' ? $('#n_colleague').val() : $('#n_shift').val());
                if (!ready) { $('#n_preview').empty(); $('#n_submit').prop('disabled', true); return; }
                pTimer = setTimeout(() => {
                    $('#n_submit').prop('disabled', true);
                    $('#n_preview').html('<div class="text-muted" style="font-size:12px">Checking…</div>');
                    $.ajax({ url: urls.preview, type: 'POST', data: $('#newForm').serialize() + '&mode=request', headers: { 'X-CSRF-TOKEN': csrf },
                        success: r => $('#n_submit').prop('disabled', !renderPreview(r.data)),
                        error: x => renderPreview({ errors: x.responseJSON?.errors ? (Array.isArray(x.responseJSON.errors) ? x.responseJSON.errors : Object.values(x.responseJSON.errors).flat()) : [x.responseJSON?.message || 'Could not check this request.'] })
                    });
                }, 250);
            }
            function renderPreview(r) {
                let h = '';
                (r.errors || []).forEach(e => h += '<div class="msg-box err"><i class="feather-x-circle me-1"></i>' + esc(e) + '</div>');
                (r.warnings || []).forEach(w => h += '<div class="msg-box warn"><i class="feather-alert-triangle me-1"></i>' + esc(w) + '</div>');
                if (r.items?.length) h += '<table class="table table-sm"><thead><tr><th>Employee</th><th>Date</th><th>Now</th><th>After</th></tr></thead><tbody>' +
                    r.items.map(i => '<tr><td>' + esc(i.user_name) + '</td><td>' + esc(i.date_label) + '</td><td>' + esc(i.from ? i.from.name : 'No shift') + '</td><td class="chg">' + esc(i.to ? i.to.name + ' · ' + i.to.time : '—') + '</td></tr>').join('') + '</tbody></table>';
                $('#n_preview').html(h);
                return !(r.errors || []).length && (r.items || []).length > 0;
            }
            $('#n_from').on('change', () => { if (!$('#n_to').val() || $('#n_to').val() < $('#n_from').val()) $('#n_to').val(''); loadColleagues(); runPreview(); });
            $('#n_behalf').on('change', () => { loadColleagues(); runPreview(); });
            $('#n_to,#n_colleague,#n_shift').on('change', runPreview);
            $('#newForm').on('submit', function (e) {
                e.preventDefault();
                const $b = $('#n_submit').prop('disabled', true).text('Sending…');
                $.ajax({ url: urls.store, type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { modal('newModal').hide(); toastr.success(r.message); tab = 'mine'; $('[data-tab="mine"]').trigger('click'); },
                    error: x => { $b.prop('disabled', false); toastr.error(x.responseJSON?.message || 'Request failed'); },
                    complete: () => $b.text('Send Request')
                });
            });

            // ?tab=approvals (dashboard links) opens that tab; otherwise My Requests.
            const startTab = new URLSearchParams(location.search).get('tab');
            if (startTab && $('[data-tab="' + startTab + '"]').length) $('[data-tab="' + startTab + '"]').trigger('click');
            else load();
            const openId = new URLSearchParams(location.search).get('open');
            if (openId) openDetail(openId);
        });
    </script>
@endsection
