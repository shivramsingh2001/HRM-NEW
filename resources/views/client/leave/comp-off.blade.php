@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .co-page .content-area-body { padding: 10px 14px !important; }
        .co-stat { border: 1px solid #edf2f7; border-radius: 10px; padding: 10px 14px; background: #fff; }
        .co-stat .v { font-size: 22px; font-weight: 700; color: var(--primary); }
        .co-stat .l { font-size: 11px; color: #64748b; }
        .co-page td, .co-page th { font-size: 12px; vertical-align: middle; }
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
        $typeLabel = fn ($t) => $t === 'holiday' ? 'Holiday' : 'Week-off';
    @endphp
    <div class="co-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Comp-off" :crumbs="[['label' => 'Leave']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    <a href="{{ route('leave.apply') }}" class="btn-reset"><i class="feather-calendar"></i>Apply Leave</a>
                    <a href="#" class="btn-grad" id="reqBtn"><i class="feather-plus"></i>Request Comp-off</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <p class="text-muted mb-2" style="font-size:12px">
                Worked one of your week-offs or a holiday? Claim it here as a day off to take later. Once approved it is added to your
                <strong>Comp Off</strong> leave balance — take it with Apply Leave like any other leave. A day paid as overtime cannot also be taken as comp-off.
            </p>

            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3"><div class="co-stat"><div class="v">{{ rtrim(rtrim(number_format($balance, 1), '0'), '.') }}</div><div class="l">Comp Off balance (days)</div></div></div>
                <div class="col-6 col-md-3"><div class="co-stat"><div class="v">{{ count($claimable) }}</div><div class="l">Worked days you can claim (last 60 days)</div></div></div>
                <div class="col-6 col-md-3"><div class="co-stat"><div class="v">{{ $mine->where('status', 'pending')->count() }}</div><div class="l">Waiting for approval</div></div></div>
            </div>

            @if ($canApprove)
                <x-ui.card title="Team requests" :bodyClass="'p-0'">
                    <x-slot:headerActions>
                        <select class="form-select form-select-sm" style="width:auto" onchange="location.href='?status='+this.value">
                            @foreach (['' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'] as $v => $l)
                                <option value="{{ $v }}" @selected(($status ?? '') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </x-slot:headerActions>
                    <x-ui.data-table>
                        <thead><tr><th>Employee</th><th>Worked</th><th>Day</th><th>Hours</th><th>Days</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($team as $c)
                                <tr>
                                    <td>{{ $c->user->name ?? '—' }} <span class="text-muted">{{ $c->user->employee_id ?? '' }}</span></td>
                                    <td class="text-nowrap">{{ $c->worked_date->format('D, d M Y') }}</td>
                                    <td>{{ $typeLabel($c->day_type) }}</td>
                                    <td>{{ $c->worked_hours }}</td>
                                    <td>{{ $c->days }}</td>
                                    <td>{{ $c->reason ?: '—' }}</td>
                                    <td><span class="badge {{ $badge($c->status) }}">{{ ucfirst($c->status) }}</span>@if ($c->source === 'auto') <span class="text-muted">auto</span>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if ($c->status === 'pending')
                                            <a href="#" class="action-btn decide" data-id="{{ $c->id }}" data-action="approved" title="Approve"><i class="feather-check"></i></a>
                                            <a href="#" class="action-btn delete decide" data-id="{{ $c->id }}" data-action="rejected" title="Reject"><i class="feather-x"></i></a>
                                        @else
                                            <span class="text-muted">{{ $c->decider->name ?? '' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-3">Nothing here.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.data-table>
                </x-ui.card>
            @endif

            <x-ui.card title="My comp-off" :bodyClass="'p-0'" class="mt-3">
                <x-ui.data-table>
                    <thead><tr><th>Worked</th><th>Day</th><th>Hours</th><th>Days</th><th>Status</th><th>Expires</th><th>Note</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($mine as $c)
                            <tr>
                                <td class="text-nowrap">{{ $c->worked_date->format('D, d M Y') }}</td>
                                <td>{{ $typeLabel($c->day_type) }}</td>
                                <td>{{ $c->worked_hours }}</td>
                                <td>{{ $c->days }}</td>
                                <td><span class="badge {{ $badge($c->status) }}">{{ ucfirst($c->status) }}</span></td>
                                <td class="text-nowrap">{{ $c->expires_on ? $c->expires_on->format('d M Y') : '—' }}{{ $c->status === 'expired' && $c->lapsed_days > 0 ? ' (' . $c->lapsed_days . ' lapsed)' : '' }}</td>
                                <td>{{ $c->decision_remarks ?: ($c->reason ?: '—') }}</td>
                                <td class="text-end">
                                    @if ($c->status === 'pending')
                                        <a href="#" class="action-btn delete cancel-req" data-id="{{ $c->id }}" title="Cancel request"><i class="feather-x-circle"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state icon="gift" title="No comp-off yet" subtitle="Worked a week-off or holiday? Use “Request Comp-off”." /></td></tr>
                        @endforelse
                    </tbody>
                </x-ui.data-table>
            </x-ui.card>
        </div>
    </div>
@endsection

@section('create-modal')
    <div class="modal fade modal-custom" id="reqModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2"><h5 class="modal-title" style="font-size:15px">Request Comp-off</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form id="reqForm" autocomplete="off">
                        <label class="form-label">Day you worked</label>
                        <select class="form-control" name="worked_date" required>
                            @forelse ($claimable as $d)
                                <option value="{{ $d['date'] }}">{{ \Carbon\Carbon::parse($d['date'])->format('D, d M Y') }} — {{ $typeLabel($d['day_type']) }}, {{ $d['hours'] }} h → {{ $d['days'] }} day</option>
                            @empty
                                <option value="">No worked week-off / holiday to claim</option>
                            @endforelse
                        </select>
                        <label class="form-label mt-2">Reason <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" name="reason" rows="2" maxlength="500"></textarea>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="reqForm" class="btn btn-primary btn-sm" @disabled(empty($claimable))>Request</button>
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

            $('#reqBtn').on('click', e => { e.preventDefault(); bootstrap.Modal.getOrCreateInstance(document.getElementById('reqModal')).show(); });
            $('#reqForm').on('submit', function (e) {
                e.preventDefault();
                $.ajax({ url: "{{ route('leave.comp-off.store') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf }, success: done, error: fail });
            });
            $(document).on('click', '.decide', function (e) {
                e.preventDefault();
                const action = $(this).data('action');
                const remarks = action === 'rejected' ? prompt('Reason for rejecting (optional)') : '';
                if (remarks === null) return;
                $.ajax({ url: "{{ url('leave/comp-off') }}/" + $(this).data('id') + '/decide', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
                    data: { action: action, remarks: remarks }, success: done, error: fail });
            });
            $(document).on('click', '.cancel-req', function (e) {
                e.preventDefault();
                if (!confirm('Cancel this comp-off request?')) return;
                $.ajax({ url: "{{ url('leave/comp-off') }}/" + $(this).data('id') + '/cancel', type: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, success: done, error: fail });
            });
        });
    </script>
@endsection
