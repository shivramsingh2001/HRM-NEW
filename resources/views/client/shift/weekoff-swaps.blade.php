@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* "How it works" note — same look as the Shift Request Rules card */
        .ws-info { display: flex; gap: 12px; align-items: flex-start; background: #fff; border: 1px solid var(--border, #e2e8f0); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; box-shadow: var(--shadow-sm); }
        .ws-info-icon { width: 34px; height: 34px; flex-shrink: 0; border-radius: 50%; background: var(--primary-light, #EFF6FF); color: var(--icon-color, #0D6EFD); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
        .ws-info h6 { font-size: 13px; font-weight: 700; margin: 0 0 3px; color: #0f172a; }
        .ws-info p { font-size: 12.5px; color: #475569; margin: 0; line-height: 1.5; }
        .ws-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px; }
        @media (max-width: 767px) { .ws-stats { grid-template-columns: 1fr; } }
        /* Dates: weekday on top, date below */
        .ws-date strong { display: block; color: #0f172a; }
        .ws-date small { color: #64748b; }
        .ws-arrow { color: #94a3b8; }
        /* Employee chips */
        .ws-emp { display: flex; flex-wrap: wrap; gap: 4px; }
        .ws-emp .chip { display: inline-flex; align-items: center; gap: 4px; border: 1px solid #dbeafe; border-radius: 20px; padding: 2px 4px 2px 10px; background: var(--primary-light, #EFF6FF); color: #1e293b; }
        .ws-emp .chip.cancelled { text-decoration: line-through; color: #94a3b8; background: #f8fafc; border-color: #e2e8f0; padding-right: 10px; }
        .ws-emp .chip .x { border: 0; background: #fff; color: #dc2626; width: 18px; height: 18px; border-radius: 50%; line-height: 1; display: inline-flex; align-items: center; justify-content: center; }
        .ws-emp .chip .x:hover { background: #fee2e2; }
        /* New Swap modal */
        #ws_list { max-height: 340px; overflow: auto; }
        #ws_list td, #ws_list th { vertical-align: middle; }
        #ws_list input[type=date] { width: 150px; }
        .ws-hint { color: #64748b; }
    </style>
@endsection

@section('content-area')
    <div class="ws-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Week-off Swaps" :crumbs="[['label' => 'Shift']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    <a href="{{ route('shift.roster') }}" class="btn btn-light-brand btn-sm"><i class="feather-grid me-1"></i>Roster</a>
                    <a href="#" class="btn btn-primary btn-sm" id="newBtn"><i class="feather-plus me-1"></i>New Swap</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <div class="ws-info">
                <span class="ws-info-icon"><i class="feather-repeat"></i></span>
                <div>
                    <h6>How week-off swaps work</h6>
                    <p>When employees work one of their week-offs, give them another day off instead — e.g. worked Saturday, off on Monday.
                        The day off shows as a week-off everywhere (roster, attendance, reports, app, payroll) and is always paid. Cancelling gives them their shift back.</p>
                </div>
            </div>

            @php
                $activeSwaps = $swaps->where('status', 'active');
                $employeesOff = $activeSwaps->sum(fn ($s) => $s->employees->where('status', 'active')->count());
            @endphp
            <div class="ws-stats">
                <x-ui.stat-card icon="repeat" label="Total Swaps" :value="$swaps->count()" />
                <x-ui.stat-card icon="check-circle" label="Active Swaps" :value="$activeSwaps->count()" />
                <x-ui.stat-card icon="users" label="Employees with a Day Off" :value="$employeesOff" />
            </div>

            <x-ui.card title="Swaps" :bodyClass="'p-0'">
                <x-ui.data-table>
                    <thead><tr><th>Worked Day</th><th></th><th>Day Off</th><th>Employees</th><th>Reason</th><th>Created By</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($swaps as $s)
                            @php $active = $s->employees->where('status', 'active'); @endphp
                            <tr>
                                <td class="text-nowrap ws-date"><strong>{{ $s->worked_date->format('l') }}</strong><small>{{ $s->worked_date->format('d M Y') }}</small></td>
                                <td class="ws-arrow"><i class="feather-arrow-right"></i></td>
                                <td class="text-nowrap ws-date"><strong>{{ $s->replacement_date->format('l') }}</strong><small>{{ $s->replacement_date->format('d M Y') }}</small></td>
                                <td><div class="ws-emp">
                                    @foreach ($s->employees as $e)
                                        <span class="chip {{ $e->status === 'cancelled' ? 'cancelled' : '' }}" title="{{ $e->status === 'cancelled' ? 'Cancelled' : 'Day off ' . $e->replacement_date->format('d M') }}">
                                            {{ $e->user->name ?? '#' . $e->user_id }}
                                            @if (! $e->replacement_date->eq($s->replacement_date))<span class="text-muted">· {{ $e->replacement_date->format('d M') }}</span>@endif
                                            @if ($e->status === 'active')
                                                <button type="button" class="x cancel-one" data-id="{{ $s->id }}" data-user="{{ $e->user_id }}" data-name="{{ $e->user->name ?? '' }}" title="Cancel for this employee">&times;</button>
                                            @endif
                                        </span>
                                    @endforeach
                                </div></td>
                                <td>{{ $s->reason ?: '—' }}</td>
                                <td>{{ $s->creator->name ?? '—' }}</td>
                                <td>
                                    @if ($s->status === 'active')
                                        <span class="badge bg-soft-success text-success">Active · {{ $active->count() }}</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary">Cancelled</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($s->status === 'active')
                                        <a href="#" class="action-btn warning cancel-all" data-id="{{ $s->id }}" title="Cancel the whole swap"><i class="feather-x-circle"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state icon="repeat" title="No week-off swaps yet" subtitle="Use “New Swap” when employees work a week-off." /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </x-ui.card>
        </div>
    </div>
@endsection

@section('create-modal')
    <div class="modal fade modal-custom" id="swapModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="feather-repeat me-2"></i>New Week-off Swap</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="swapForm" autocomplete="off">
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label">Week-off worked</label><input type="date" class="form-control" id="ws_worked" required></div>
                            <div class="col-md-3"><label class="form-label">Day off instead</label><input type="date" class="form-control" id="ws_day" required></div>
                            <div class="col-md-6"><label class="form-label">Reason <span class="text-muted">(optional)</span></label><input type="text" class="form-control" id="ws_reason" maxlength="500" placeholder="e.g. Saturday stock audit"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                            <div class="ws-hint" id="ws_hint"><i class="feather-info me-1"></i>Pick the week-off worked to list the employees who had it off.</div>
                            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="ws_all"><label class="form-check-label" for="ws_all">Select all</label></div>
                        </div>
                        <div id="ws_list" class="border rounded">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th style="width:28px"></th><th>Employee</th><th>Worked that day?</th><th>Day off</th><th>Status</th></tr></thead>
                                <tbody id="ws_rows"><tr><td colspan="5" class="text-center text-muted py-3">—</td></tr></tbody>
                            </table>
                        </div>
                        <p class="ws-hint mt-2 mb-0">Employees who clocked in on the worked day are ticked. The day off can be changed per employee.</p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="swapForm" class="btn btn-primary btn-sm"><i class="feather-check me-1"></i>Save Swap</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 6000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const modal = id => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
            const esc = s => $('<div>').text(s ?? '').html();
            const fail = x => toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed'));

            function load() {
                const worked = $('#ws_worked').val(), day = $('#ws_day').val();
                if (!worked) return;
                $('#ws_rows').html('<tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr>');
                $.get("{{ route('shift.weekoff-swaps.candidates') }}", { worked_date: worked, replacement_date: day || null }, r => {
                    const rows = r.data || [];
                    $('#ws_hint').text(rows.length + ' employee(s) had ' + worked + ' as a week-off.');
                    $('#ws_rows').html(rows.length ? rows.map(e => '<tr data-user="' + e.user_id + '">' +
                        '<td><input type="checkbox" class="form-check-input pick"' + (e.worked && e.eligible ? ' checked' : '') + '></td>' +
                        '<td>' + esc(e.name) + ' <span class="text-muted">' + esc(e.employee_id) + '</span></td>' +
                        '<td>' + (e.worked ? '<span class="badge bg-soft-success text-success">Clocked in</span>' : '<span class="text-muted">No</span>') + '</td>' +
                        '<td><input type="date" class="form-control day" value="' + esc(day) + '"></td>' +
                        '<td>' + (e.eligible ? '<span class="text-success">OK</span>' : '<span class="text-danger">' + esc(e.reason || (day ? '' : 'Pick the day off')) + '</span>') + '</td></tr>').join('')
                        : '<tr><td colspan="5" class="text-center text-muted py-3">Nobody had this day as a week-off.</td></tr>');
                }).fail(fail);
            }
            $('#ws_worked,#ws_day').on('change', load);
            $('#ws_all').on('change', function () { $('#ws_rows .pick').prop('checked', this.checked); });

            $('#newBtn').on('click', e => {
                e.preventDefault();
                $('#swapForm')[0].reset(); $('#ws_rows').html('<tr><td colspan="5" class="text-center text-muted py-3">—</td></tr>');
                modal('swapModal').show();
            });

            $('#swapForm').on('submit', function (e) {
                e.preventDefault();
                const day = $('#ws_day').val();
                const employees = $('#ws_rows tr[data-user]').filter((i, tr) => $(tr).find('.pick').is(':checked'))
                    .map((i, tr) => ({ user_id: $(tr).data('user'), replacement_date: $(tr).find('.day').val() || day })).get();
                if (!employees.length) { toastr.warning('Pick at least one employee.'); return; }
                $.ajax({ url: "{{ route('shift.weekoff-swaps.store') }}", type: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: { worked_date: $('#ws_worked').val(), replacement_date: day, reason: $('#ws_reason').val(), employees: employees },
                    success: r => { modal('swapModal').hide(); (r.skipped?.length ? toastr.warning : toastr.success)(r.message); setTimeout(() => location.reload(), 1500); },
                    error: fail });
            });

            function cancel(id, userId, text) {
                if (!confirm(text)) return;
                $.ajax({ url: "{{ url('shift/weekoff-swaps') }}/" + id + '/cancel', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: userId ? { user_id: userId } : {},
                    success: r => { toastr.success(r.message); setTimeout(() => location.reload(), 900); }, error: fail });
            }
            $(document).on('click', '.cancel-all', function (e) { e.preventDefault(); cancel($(this).data('id'), null, 'Cancel this swap for everyone? Their shift comes back on the day off.'); });
            $(document).on('click', '.cancel-one', function () { cancel($(this).data('id'), $(this).data('user'), 'Cancel the swap for ' + $(this).data('name') + '?'); });
        });
    </script>
@endsection
