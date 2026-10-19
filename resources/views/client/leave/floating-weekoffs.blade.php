@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .fw-page .content-area-body { padding: 10px 14px !important; }
        .fw-stat { border: 1px solid #edf2f7; border-radius: 10px; padding: 10px 14px; background: #fff; }
        .fw-stat .v { font-size: 22px; font-weight: 700; color: var(--primary); }
        .fw-stat .l { font-size: 11px; color: #64748b; }
        .fw-page td, .fw-page th { font-size: 12px; vertical-align: middle; }
    </style>
@endsection

@section('content-area')
    @php
        $badge = fn ($s) => match ($s) {
            'approved' => 'bg-soft-success text-success',
            'pending' => 'bg-soft-warning text-warning',
            'rejected', 'cancelled' => 'bg-soft-danger text-danger',
            default => 'bg-soft-secondary text-secondary',
        };
        $enabled = $months->contains(fn ($m) => $m['enabled']);
    @endphp
    <div class="fw-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Floating Week-offs" :crumbs="[['label' => 'Leave']]">
            <x-slot:actions>
                @if ($enabled)
                    <a href="#" class="btn-grad" id="planBtn"><i class="feather-plus"></i>Plan a Day Off</a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <p class="text-muted mb-2" style="font-size:12px">
                You get a number of week-offs each month on any day you choose. Plan one ahead here (your manager / HR approves it), or simply don't
                come in — each night, missed days become week-offs until the month's count is used; after that a missed day is Absent.
            </p>

            <div class="row g-2 mb-3">
                @foreach ($months as $m)
                    <div class="col-6 col-md-3">
                        <div class="fw-stat">
                            @if ($m['enabled'])
                                <div class="v">{{ max(0, $m['quota'] - $m['used'] - $m['pending']) }} <span style="font-size:13px;color:#64748b">of {{ $m['quota'] }}</span></div>
                                <div class="l">left in {{ $m['label'] }} · {{ $m['used'] }} used{{ $m['pending'] ? ', ' . $m['pending'] . ' waiting' : '' }}{{ $m['per_week'] ? ' · at most ' . $m['per_week'] . ' a week' : '' }}</div>
                            @else
                                <div class="v">—</div>
                                <div class="l">{{ $m['label'] }}: not on floating week-offs</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($canApprove)
                <x-ui.card title="Team requests" :bodyClass="'p-0'">
                    <x-slot:headerActions>
                        <select class="form-select form-select-sm" style="width:auto" onchange="location.href='?status='+this.value">
                            @foreach (['' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $v => $l)
                                <option value="{{ $v }}" @selected(($status ?? '') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </x-slot:headerActions>
                    <x-ui.data-table>
                        <thead><tr><th>Employee</th><th>Day off</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($team as $r)
                                <tr>
                                    <td>{{ $r->user->name ?? '—' }} <span class="text-muted">{{ $r->user->employee_id ?? '' }}</span></td>
                                    <td class="text-nowrap">{{ $r->date->format('D, d M Y') }}</td>
                                    <td>{{ $r->reason ?: '—' }}</td>
                                    <td><span class="badge {{ $badge($r->status) }}">{{ ucfirst($r->status) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if ($r->status === 'pending')
                                            <a href="#" class="action-btn decide" data-id="{{ $r->id }}" data-action="approved" title="Approve"><i class="feather-check"></i></a>
                                            <a href="#" class="action-btn delete decide" data-id="{{ $r->id }}" data-action="rejected" title="Reject"><i class="feather-x"></i></a>
                                        @elseif ($r->status === 'approved' && $r->date->toDateString() >= now()->toDateString())
                                            <a href="#" class="action-btn warning cancel-day" data-id="{{ $r->id }}" title="Cancel this day off"><i class="feather-x-circle"></i></a>
                                        @else
                                            <span class="text-muted">{{ $r->decider->name ?? '' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">Nothing here.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.data-table>
                </x-ui.card>
            @endif

            <x-ui.card title="My floating week-offs" :bodyClass="'p-0'" class="mt-3">
                <x-ui.data-table>
                    <thead><tr><th>Day off</th><th>How</th><th>Status</th><th>Note</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($mine as $r)
                            <tr>
                                <td class="text-nowrap">{{ $r->date->format('D, d M Y') }}</td>
                                <td>{{ $r->source === 'planned' ? 'Planned' : 'Missed day' }}</td>
                                <td><span class="badge {{ $badge($r->status) }}">{{ ucfirst($r->status) }}</span></td>
                                <td>{{ $r->decision_remarks ?: ($r->reason ?: '—') }}</td>
                                <td class="text-end">
                                    @if ($r->source === 'planned' && ($r->status === 'pending' || ($r->status === 'approved' && $r->date->toDateString() >= now()->toDateString())))
                                        <a href="#" class="action-btn delete cancel-day" data-id="{{ $r->id }}" title="Cancel"><i class="feather-x-circle"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="shuffle" title="No floating week-offs yet" subtitle="{{ $enabled ? 'Plan a day off, or it is filled from days you miss.' : 'Your company has not put you on floating week-offs.' }}" /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </x-ui.card>
        </div>
    </div>
@endsection

@section('create-modal')
    <div class="modal fade modal-custom" id="planModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Plan a Day Off</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="planForm" autocomplete="off">
                        <label class="form-label">Day</label>
                        <input type="date" class="form-control" name="date" id="fw_date" min="{{ now()->toDateString() }}" required>
                        <div class="mt-1" style="font-size:12px" id="fw_hint"></div>
                        <label class="form-label mt-2">Reason <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" name="reason" rows="2" maxlength="500"></textarea>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="planForm" class="btn btn-primary btn-sm">Request</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const fail = x => toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed'));
            const done = r => { toastr.success(r.message); setTimeout(() => location.reload(), 900); };

            $('#planBtn').on('click', e => { e.preventDefault(); bootstrap.Modal.getOrCreateInstance(document.getElementById('planModal')).show(); });
            $('#fw_date').on('change', function () {
                if (!this.value) return;
                $.get("{{ route('leave.floating-weekoffs.check') }}", { date: this.value },
                    r => $('#fw_hint').attr('class', 'mt-1 ' + (r.status ? 'text-success' : 'text-danger')).text(r.message));
            });
            $('#planForm').on('submit', function (e) {
                e.preventDefault();
                $.ajax({ url: "{{ route('leave.floating-weekoffs.store') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf }, success: done, error: fail });
            });
            $(document).on('click', '.decide', function (e) {
                e.preventDefault();
                const action = $(this).data('action');
                const remarks = action === 'rejected' ? prompt('Reason for rejecting (optional)') : '';
                if (remarks === null) return;
                $.ajax({ url: "{{ url('leave/floating-weekoffs') }}/" + $(this).data('id') + '/decide', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: { action: action, remarks: remarks }, success: done, error: fail });
            });
            $(document).on('click', '.cancel-day', function (e) {
                e.preventDefault();
                if (!confirm('Cancel this floating week-off?')) return;
                $.ajax({ url: "{{ url('leave/floating-weekoffs') }}/" + $(this).data('id') + '/cancel', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, success: done, error: fail });
            });
        });
    </script>
@endsection
