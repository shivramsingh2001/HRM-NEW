@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @include('client.leave.partials.request-page-styles')
@endsection

@section('content-area')
    @php
        $typeLabel = fn ($t) => $t === 'holiday' ? 'Holiday' : 'Week-off';
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') ?: '0';
        $initials = fn ($name) => strtoupper(mb_substr((string) ($name ?: 'U'), 0, 2));
        $myPending = $mine->where('status', 'pending');
        $myApproved = $mine->where('status', 'approved');
    @endphp
    <div class="lr-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Comp-off" :crumbs="[['label' => 'Leave']]">
            <x-slot:actions>
                <div class="hstack gap-2">
                    <a href="{{ route('leave.view', ['apply' => 1]) }}" class="btn btn-light-brand btn-sm"><i class="feather-calendar me-1"></i>Apply Leave</a>
                    <a href="#" class="btn btn-primary btn-sm" id="reqBtn"><i class="feather-plus me-1"></i>Request Comp-off</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <div class="lr-info">
                <span class="lr-info-icon"><i class="feather-gift"></i></span>
                <div>
                    <h6>How comp-off works</h6>
                    <p>Worked one of your week-offs or a holiday? Claim it here as a day off to take later. Once approved it is added to your
                        <strong>Comp Off</strong> leave balance — take it with Apply Leave like any other leave. A day paid as overtime cannot also be taken as comp-off.</p>
                </div>
            </div>

            <div class="lr-stats">
                <x-ui.stat-card icon="gift" label="Comp Off Balance (days)" :value="$num($balance)" pill="Available" />
                <x-ui.stat-card icon="briefcase" label="Worked Days You Can Claim" :value="count($claimable)" pill="Last 60 days" />
                <x-ui.stat-card icon="clock" label="Waiting for Approval" :value="$myPending->count()" :pill="$num($myPending->sum('days')) . ' day(s)'" />
                <x-ui.stat-card icon="check-circle" label="Approved Comp-off" :value="$myApproved->count()" :pill="$num($myApproved->sum('days')) . ' day(s)'" />
            </div>

            {{-- One card, two tabs: My Comp-off | Team Requests (approvers). ?tab=team (or a ?status filter) opens Team Requests. --}}
            @php
                $activeTab = $canApprove && (request('tab') === 'team' || request()->filled('status')) ? 'team' : 'mine';
                $teamPending = ($status ?? '') === '' ? $team->count() : null;
            @endphp
            <div class="card stretch stretch-full lr-tabcard">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab === 'mine' ? 'active' : '' }}" data-bs-toggle="tab" href="#coMine" data-tab="mine" role="tab">
                                <i class="feather-user me-1"></i>My Comp-off <span class="count is-grey">{{ $mine->count() }}</span>
                            </a>
                        </li>
                        @if ($canApprove)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $activeTab === 'team' ? 'active' : '' }}" data-bs-toggle="tab" href="#coTeam" data-tab="team" role="tab">
                                    <i class="feather-users me-1"></i>Team Requests @if ($teamPending)<span class="count">{{ $teamPending }}</span>@endif
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade {{ $activeTab === 'mine' ? 'show active' : '' }}" id="coMine" role="tabpanel">
                            <x-ui.data-table>
                                <thead><tr><th>Worked</th><th>Day</th><th>Hours</th><th>Days</th><th>Status</th><th>Expires</th><th>Note</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    @forelse ($mine as $c)
                                        <tr>
                                            <td class="text-nowrap lr-date"><strong>{{ $c->worked_date->format('l') }}</strong><small>{{ $c->worked_date->format('d M Y') }}</small></td>
                                            <td><span class="lr-tag {{ $c->day_type === 'holiday' ? '' : 'is-grey' }}">{{ $typeLabel($c->day_type) }}</span></td>
                                            <td>{{ $c->worked_hours }} h</td>
                                            <td><strong>{{ $num($c->days) }}</strong></td>
                                            <td><x-ui.status-badge :status="$c->status" /></td>
                                            <td class="text-nowrap">
                                                {{ $c->expires_on ? $c->expires_on->format('d M Y') : '—' }}
                                                @if ($c->status === 'expired' && $c->lapsed_days > 0)<div class="lr-muted">{{ $num($c->lapsed_days) }} day(s) lapsed</div>@endif
                                            </td>
                                            <td class="lr-reason">
                                                {{ $c->decision_remarks ?: ($c->reason ?: '—') }}
                                                @if ($c->decider)<div class="lr-muted">by {{ $c->decider->name }}</div>@endif
                                            </td>
                                            <td class="text-end">
                                                @if ($c->status === 'pending')
                                                    <a href="#" class="action-btn delete js-decide" data-kind="cancel" data-url="{{ url('leave/comp-off/' . $c->id . '/cancel') }}"
                                                        data-question="Cancel your comp-off request for {{ $c->worked_date->format('D, d M Y') }}?" title="Cancel request"><i class="feather-x-circle"></i></a>
                                                @else
                                                    <span class="lr-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8"><x-ui.empty-state icon="gift" title="No comp-off yet" subtitle="Worked a week-off or holiday? Use “Request Comp-off”." /></td></tr>
                                    @endforelse
                                </tbody>
                            </x-ui.data-table>
                        </div>

                        @if ($canApprove)
                            <div class="tab-pane fade {{ $activeTab === 'team' ? 'show active' : '' }}" id="coTeam" role="tabpanel">
                                <div class="lr-subbar">
                                    <span class="lr-muted">Show</span>
                                    <div class="lr-tabs">
                                        @foreach (['' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'] as $v => $l)
                                            <a href="{{ route('leave.comp-off.index', array_filter(['tab' => 'team', 'status' => $v])) }}" class="{{ ($status ?? '') === $v ? 'active' : '' }}">{{ $l }}</a>
                                        @endforeach
                                    </div>
                                </div>
                                <x-ui.data-table>
                                    <thead><tr><th>Employee</th><th>Worked</th><th>Day</th><th>Hours</th><th>Days</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        @forelse ($team as $c)
                                            @php $what = ($c->user->name ?? 'Employee') . ' · ' . $c->worked_date->format('D, d M Y'); @endphp
                                            <tr>
                                                <td>
                                                    <div class="lr-emp">
                                                        <span class="lr-avatar">{{ $initials($c->user->name ?? null) }}</span>
                                                        <div><strong>{{ $c->user->name ?? '—' }}</strong><small>{{ $c->user->employee_id ?? '' }}</small></div>
                                                    </div>
                                                </td>
                                                <td class="text-nowrap lr-date"><strong>{{ $c->worked_date->format('l') }}</strong><small>{{ $c->worked_date->format('d M Y') }}</small></td>
                                                <td><span class="lr-tag {{ $c->day_type === 'holiday' ? '' : 'is-grey' }}">{{ $typeLabel($c->day_type) }}</span></td>
                                                <td>{{ $c->worked_hours }} h</td>
                                                <td><strong>{{ $num($c->days) }}</strong></td>
                                                <td class="lr-reason">{{ $c->reason ?: '—' }}</td>
                                                <td>
                                                    <x-ui.status-badge :status="$c->status" />
                                                    @if ($c->source === 'auto')<div class="lr-muted mt-1">Auto</div>@endif
                                                </td>
                                                <td class="text-end text-nowrap">
                                                    @if ($c->status === 'pending')
                                                        <div class="lr-actions">
                                                            <a href="#" class="action-btn approve js-decide" data-kind="approved" data-url="{{ url('leave/comp-off/' . $c->id . '/decide') }}" data-title="{{ $what }}" title="Approve"><i class="feather-check"></i></a>
                                                            <a href="#" class="action-btn reject js-decide" data-kind="rejected" data-url="{{ url('leave/comp-off/' . $c->id . '/decide') }}" data-title="{{ $what }}" title="Reject"><i class="feather-x"></i></a>
                                                        </div>
                                                    @else
                                                        <span class="lr-muted">{{ $c->decider->name ?? '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="8"><x-ui.empty-state icon="inbox" title="No {{ $status ?: 'pending' }} requests" subtitle="Comp-off requests from your team show here." /></td></tr>
                                        @endforelse
                                    </tbody>
                                </x-ui.data-table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <x-ui.drawer id="reqDrawer" title="Request Comp-off" subtitle="Claim a week-off or holiday you worked — your manager approves it." width="460px">
        <form id="reqForm" class="lr-drawer" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="co_date">Day you worked <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="worked_date" id="co_date" required @disabled(empty($claimable))>
                    @forelse ($claimable as $d)
                        <option value="{{ $d['date'] }}" data-hint="{{ $typeLabel($d['day_type']) }} · worked {{ $d['hours'] }} h → {{ $num($d['days']) }} day(s) of comp-off">
                            {{ \Carbon\Carbon::parse($d['date'])->format('D, d M Y') }} — {{ $typeLabel($d['day_type']) }}
                        </option>
                    @empty
                        <option value="">No worked week-off / holiday to claim</option>
                    @endforelse
                </select>
            </div>
            <div class="lr-hint mb-3 {{ empty($claimable) ? 'is-bad' : 'is-ok' }}" id="co_hint">
                <i class="feather-info mt-1"></i>
                <span>{{ empty($claimable) ? 'You have no worked week-off or holiday in the last 60 days that can be claimed.' : '' }}</span>
            </div>
            <div class="mb-3">
                <label class="form-label" for="co_reason">Reason <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control form-control-sm" name="reason" id="co_reason" rows="3" maxlength="500" placeholder="e.g. Saturday stock audit"></textarea>
            </div>
            <div class="d-flex gap-2 pt-2 border-top">
                <button type="submit" class="btn btn-primary btn-sm" @disabled(empty($claimable))><i class="feather-send me-1"></i>Request Comp-off</button>
                <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </x-ui.drawer>

    @include('client.leave.partials.request-decision-modal')
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');

            const showHint = () => {
                const opt = $('#co_date option:selected');
                if (opt.val()) $('#co_hint span').text(opt.data('hint'));
            };
            $('.lr-tabcard [data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                const url = new URL(location.href);
                if ($(this).data('tab') === 'team') url.searchParams.set('tab', 'team'); else { url.searchParams.delete('tab'); url.searchParams.delete('status'); }
                history.replaceState(null, '', url);
            });

            $('#co_date').on('change', showHint);
            showHint();

            $('#reqBtn').on('click', e => { e.preventDefault(); bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('reqDrawer')).show(); });
            $('#reqForm').on('submit', function (e) {
                e.preventDefault();
                const btn = $(this).find('[type=submit]').prop('disabled', true);
                $.ajax({ url: "{{ route('leave.comp-off.store') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message); setTimeout(() => location.reload(), 900); },
                    error: x => { btn.prop('disabled', false); toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed')); } });
            });
        });
    </script>
@endsection
