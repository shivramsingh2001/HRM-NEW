@extends('client.layout.master')

@section('style')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @include('client.leave.partials.request-page-styles')
@endsection

@section('content-area')
    @php
        $enabled = $months->contains(fn ($m) => $m['enabled']);
        $initials = fn ($name) => strtoupper(mb_substr((string) ($name ?: 'U'), 0, 2));
        $today = now()->toDateString();
    @endphp
    <div class="lr-page">
        <x-ui.page-header class="content-area-header sticky-top" title="Floating Week-offs" :crumbs="[['label' => 'Leave']]">
            <x-slot:actions>
                @if ($enabled)
                    <a href="#" class="btn btn-primary btn-sm" id="planBtn"><i class="feather-plus me-1"></i>Plan a Day Off</a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <div class="content-area-body">
            <div class="lr-info">
                <span class="lr-info-icon"><i class="feather-shuffle"></i></span>
                <div>
                    <h6>How floating week-offs work</h6>
                    <p>You get a number of week-offs each month on any day you choose. Plan one ahead here (your manager / HR approves it), or simply don't
                        come in — each night, missed days become week-offs until the month's count is used; after that a missed day is Absent.</p>
                </div>
            </div>

            <div class="lr-stats cols-2">
                @foreach ($months as $m)
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon"><i class="feather-calendar"></i></span>
                            <span class="kpi5-pill">{{ $m['label'] }}</span>
                        </div>
                        @if ($m['enabled'])
                            <div class="kpi5-value">{{ max(0, $m['quota'] - $m['used'] - $m['pending']) }} <small>of {{ $m['quota'] }}</small></div>
                            <div class="kpi5-label">Week-offs left</div>
                            <div class="kpi5-divider"></div>
                            <div class="kpi5-foot">
                                <div class="kpi5-stat"><span class="n">{{ $m['used'] }}</span><span class="l">Used</span></div>
                                <div class="kpi5-stat"><span class="n">{{ $m['pending'] }}</span><span class="l">Waiting</span></div>
                                <div class="kpi5-stat"><span class="n">{{ $m['per_week'] ?: '—' }}</span><span class="l">Max a week</span></div>
                            </div>
                        @else
                            <div class="kpi5-value">—</div>
                            <div class="kpi5-label">Not on floating week-offs</div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- One card, two tabs: My Floating Week-offs | Team Requests (approvers). ?tab=team (or a ?status filter) opens Team Requests. --}}
            @php
                $activeTab = $canApprove && (request('tab') === 'team' || request()->filled('status')) ? 'team' : 'mine';
                $teamPending = ($status ?? '') === '' ? $team->count() : null;
            @endphp
            <div class="card stretch stretch-full lr-tabcard">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab === 'mine' ? 'active' : '' }}" data-bs-toggle="tab" href="#fwMine" data-tab="mine" role="tab">
                                <i class="feather-user me-1"></i>My Floating Week-offs <span class="count is-grey">{{ $mine->count() }}</span>
                            </a>
                        </li>
                        @if ($canApprove)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $activeTab === 'team' ? 'active' : '' }}" data-bs-toggle="tab" href="#fwTeam" data-tab="team" role="tab">
                                    <i class="feather-users me-1"></i>Team Requests @if ($teamPending)<span class="count">{{ $teamPending }}</span>@endif
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade {{ $activeTab === 'mine' ? 'show active' : '' }}" id="fwMine" role="tabpanel">
                            <x-ui.data-table>
                                <thead><tr><th>Day Off</th><th>How</th><th>Status</th><th>Note</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                    @forelse ($mine as $r)
                                        <tr>
                                            <td class="text-nowrap lr-date"><strong>{{ $r->date->format('l') }}</strong><small>{{ $r->date->format('d M Y') }}</small></td>
                                            <td><span class="lr-tag {{ $r->source === 'planned' ? '' : 'is-grey' }}">{{ $r->source === 'planned' ? 'Planned' : 'Missed day' }}</span></td>
                                            <td><x-ui.status-badge :status="$r->status" /></td>
                                            <td class="lr-reason">
                                                {{ $r->decision_remarks ?: ($r->reason ?: '—') }}
                                                @if ($r->decider)<div class="lr-muted">by {{ $r->decider->name }}</div>@endif
                                            </td>
                                            <td class="text-end">
                                                @if ($r->source === 'planned' && ($r->status === 'pending' || ($r->status === 'approved' && $r->date->toDateString() >= $today)))
                                                    <a href="#" class="action-btn delete js-decide" data-kind="cancel" data-url="{{ url('leave/floating-weekoffs/' . $r->id . '/cancel') }}"
                                                        data-question="Cancel your floating week-off on {{ $r->date->format('D, d M Y') }}?" title="Cancel"><i class="feather-x-circle"></i></a>
                                                @else
                                                    <span class="lr-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5"><x-ui.empty-state icon="shuffle" title="No floating week-offs yet" subtitle="{{ $enabled ? 'Plan a day off, or it is filled from days you miss.' : 'Your company has not put you on floating week-offs.' }}" /></td></tr>
                                    @endforelse
                                </tbody>
                            </x-ui.data-table>
                        </div>

                        @if ($canApprove)
                            <div class="tab-pane fade {{ $activeTab === 'team' ? 'show active' : '' }}" id="fwTeam" role="tabpanel">
                                <div class="lr-subbar">
                                    <span class="lr-muted">Show</span>
                                    <div class="lr-tabs">
                                        @foreach (['' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $v => $l)
                                            <a href="{{ route('leave.floating-weekoffs.index', array_filter(['tab' => 'team', 'status' => $v])) }}" class="{{ ($status ?? '') === $v ? 'active' : '' }}">{{ $l }}</a>
                                        @endforeach
                                    </div>
                                </div>
                                <x-ui.data-table>
                                    <thead><tr><th>Employee</th><th>Day Off</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                                    <tbody>
                                        @forelse ($team as $r)
                                            @php $what = ($r->user->name ?? 'Employee') . ' · ' . $r->date->format('D, d M Y'); @endphp
                                            <tr>
                                                <td>
                                                    <div class="lr-emp">
                                                        <span class="lr-avatar">{{ $initials($r->user->name ?? null) }}</span>
                                                        <div><strong>{{ $r->user->name ?? '—' }}</strong><small>{{ $r->user->employee_id ?? '' }}</small></div>
                                                    </div>
                                                </td>
                                                <td class="text-nowrap lr-date"><strong>{{ $r->date->format('l') }}</strong><small>{{ $r->date->format('d M Y') }}</small></td>
                                                <td class="lr-reason">{{ $r->reason ?: '—' }}</td>
                                                <td><x-ui.status-badge :status="$r->status" /></td>
                                                <td class="text-end text-nowrap">
                                                    @if ($r->status === 'pending')
                                                        <div class="lr-actions">
                                                            <a href="#" class="action-btn approve js-decide" data-kind="approved" data-url="{{ url('leave/floating-weekoffs/' . $r->id . '/decide') }}" data-title="{{ $what }}" title="Approve"><i class="feather-check"></i></a>
                                                            <a href="#" class="action-btn reject js-decide" data-kind="rejected" data-url="{{ url('leave/floating-weekoffs/' . $r->id . '/decide') }}" data-title="{{ $what }}" title="Reject"><i class="feather-x"></i></a>
                                                        </div>
                                                    @elseif ($r->status === 'approved' && $r->date->toDateString() >= $today)
                                                        <a href="#" class="action-btn warning js-decide" data-kind="cancel" data-url="{{ url('leave/floating-weekoffs/' . $r->id . '/cancel') }}"
                                                            data-question="Cancel {{ $what }} as a floating week-off?" title="Cancel this day off"><i class="feather-x-circle"></i></a>
                                                    @else
                                                        <span class="lr-muted">{{ $r->decider->name ?? '—' }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5"><x-ui.empty-state icon="inbox" title="No {{ $status ?: 'pending' }} requests" subtitle="Floating week-off requests from your team show here." /></td></tr>
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
    @if ($enabled)
        <x-ui.drawer id="planDrawer" title="Plan a Day Off" subtitle="Pick a day — your manager / HR approves it as a floating week-off." width="460px">
            <form id="planForm" class="lr-drawer" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label" for="fw_date">Day <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="date" id="fw_date" min="{{ $today }}" required>
                </div>
                <div class="lr-hint mb-3" id="fw_hint">
                    <i class="feather-info mt-1"></i>
                    <span>Pick a day to check it against this month's count.</span>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="fw_reason">Reason <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control form-control-sm" name="reason" id="fw_reason" rows="3" maxlength="500" placeholder="e.g. Family function"></textarea>
                </div>
                <div class="d-flex gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="feather-send me-1"></i>Request Day Off</button>
                    <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
                </div>
            </form>
        </x-ui.drawer>
    @endif

    @include('client.leave.partials.request-decision-modal')
@endsection

@section('script-area')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(function () {
            toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
            const csrf = $('meta[name="csrf-token"]').attr('content');

            // Keep the open tab in the URL so a reload / the status links land on it.
            $('.lr-tabcard [data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                const url = new URL(location.href);
                if ($(this).data('tab') === 'team') url.searchParams.set('tab', 'team'); else { url.searchParams.delete('tab'); url.searchParams.delete('status'); }
                history.replaceState(null, '', url);
            });

            $('#planBtn').on('click', e => { e.preventDefault(); bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('planDrawer')).show(); });
            $('#fw_date').on('change', function () {
                if (!this.value) return;
                $.get("{{ route('leave.floating-weekoffs.check') }}", { date: this.value }, r => {
                    $('#fw_hint').attr('class', 'lr-hint mb-3 ' + (r.status ? 'is-ok' : 'is-bad')).find('span').text(r.message);
                });
            });
            $('#planForm').on('submit', function (e) {
                e.preventDefault();
                const btn = $(this).find('[type=submit]').prop('disabled', true);
                $.ajax({ url: "{{ route('leave.floating-weekoffs.store') }}", type: 'POST', data: $(this).serialize(), headers: { 'X-CSRF-TOKEN': csrf },
                    success: r => { toastr.success(r.message); setTimeout(() => location.reload(), 900); },
                    error: x => { btn.prop('disabled', false); toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed')); } });
            });
        });
    </script>
@endsection
