{{-- Employee 360 — shift & week-off (EmployeeProfileController::tabShift) --}}
@php
    use App\Http\Controllers\User\EmployeeProfileController as P;
    $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q);
@endphp

<div class="p360-toolbar">
    <h5 class="section-title mb-0 border-0 pb-0"><i class="feather-clock"></i> Next 14 days</h5>
    @if ($customShifts)
        <a href="#" class="btn btn-sm btn-primary" data-p360-open="{{ $form('shift-assign') }}" data-title="Assign shift to {{ $user->name }}" data-size="md">
            <i class="feather-plus me-1"></i>Assign shift</a>
    @endif
</div>
@if (!$customShifts)
    <p class="p360-note mb-3">This company runs on one fixed shift for everyone:
        <strong>{{ $defaultShift->name ?? 'not set' }}</strong> {{ $defaultShift ? P::shiftLabel($defaultShift) : '' }}.
        It is changed for the whole company in <a href="{{ route('shift-settings.index') }}">Shift Settings</a>.</p>
@endif
<div class="table-responsive">
    <table class="p360-table">
        <thead><tr><th>Date</th><th>Day</th><th>Shift(s)</th></tr></thead>
        <tbody>
            @foreach ($days as $day)
                <tr>
                    <td>{{ $day['date']->format('d M Y') }}</td>
                    <td>{{ $day['date']->format('D') }}</td>
                    <td>
                        @forelse ($day['shifts'] as $i)
                            <span class="p360-chip {{ $i['is_additional'] ? 'extra' : '' }}" title="{{ $i['is_additional'] ? 'Additional shift' : 'Main shift' }}">
                                {{ $i['is_additional'] ? '+ ' : '' }}{{ $i['shift']->name }} · {{ P::shiftLabel($i['shift']) }}
                            </span>
                        @empty
                            <span class="p360-chip muted">No shift</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($customShifts)
    <div class="p360-sub">Shift assignments</div>
    @if ($assignments->isEmpty())
        <x-ui.empty-state icon="clock" title="No shift assignments" subtitle="Use Assign shift above to give this employee a shift." />
    @else
        <div class="table-responsive">
            <table class="p360-table">
                <thead><tr><th>Shift</th><th>Timing</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($assignments as $a)
                        <tr>
                            <td>{{ $a->shift_name ?? '—' }} @if ($a->is_additional)<span class="p360-chip extra">Additional</span>@endif</td>
                            <td>{{ P::shiftLabel($a) }}</td>
                            <td>{{ ucfirst($a->type) }}</td>
                            <td>{{ \Carbon\Carbon::parse($a->start_date)->format('d M Y') }}</td>
                            <td>{{ $a->end_date ? \Carbon\Carbon::parse($a->end_date)->format('d M Y') : 'Ongoing' }}</td>
                            <td><x-ui.status-badge :status="$a->status" /></td>
                            <td class="text-end">
                                @if ($a->type === 'permanent' && $a->status === 'active')
                                    <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('shift-end', ['assignment' => $a->id]) }}"
                                        data-title="End permanent shift">End</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

<div class="p360-toolbar mt-4 mb-2">
    <div class="p360-sub m-0">Week-offs</div>
    @if ($customShifts)
        <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('weekoffs') }}" data-title="Weekly offs of {{ $user->name }}">
            <i class="feather-edit-2 me-1"></i>Set week-offs</a>
    @endif
</div>
@if ($weekoffs->isEmpty())
    <p class="p360-note">No week-offs set for this employee.</p>
@else
    @foreach ($weekoffs as $w)
        <span class="p360-chip">
            @if ($w->off_type === 'day_based')
                Every {{ $w->day_name }}
            @else
                {{ \Carbon\Carbon::parse($w->start_date)->format('d M Y') }}{{ $w->end_date && $w->end_date != $w->start_date ? ' – ' . \Carbon\Carbon::parse($w->end_date)->format('d M Y') : '' }}
            @endif
        </span>
    @endforeach
@endif
@if (!$customShifts)
    <p class="p360-note mt-2">Week-offs are the same for everyone and are set in Shift Settings.</p>
@endif

@if ($customShifts)
    <div class="p360-toolbar mt-4 mb-2">
        <div class="p360-sub m-0">Shift swap &amp; change requests</div>
        @feature('custom_shift')
        @feature('daily_reports')
            <a href="{{ route('report.attendance.shift-requests.index', ['user_id' => $user->id, 'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->toDateString()]) }}" class="btn btn-sm btn-light-brand">
                <i class="feather-list me-1"></i>Full register</a>
        @endfeature
        @endfeature
    </div>
    @if ($shiftRequests->isEmpty())
        <p class="p360-note">No shift swap or change requests for this employee.</p>
    @else
        <div class="table-responsive">
            <table class="p360-table">
                <thead><tr><th>Request</th><th>Shifts</th><th>Status</th><th>Decided by</th></tr></thead>
                <tbody>
                    @foreach ($shiftRequests as $sr)
                        <tr>
                            <td>
                                <a href="{{ route('shift.requests.index', ['open' => $sr->id]) }}">{{ $sr->request_no }}</a>
                                <div class="p360-note m-0">{{ $sr->typeLabel() }}{{ $sr->mode === 'direct' ? ' · direct' : '' }}
                                    · {{ (int) $sr->requester_id === (int) $user->id ? 'raised' : 'colleague of ' . ($sr->requester->name ?? '—') }}
                                    · {{ $sr->created_at->format('d M Y') }}</div>
                            </td>
                            <td>
                                @foreach ($sr->items as $i)
                                    <div>{{ $i->user->name ?? '—' }} · {{ \Carbon\Carbon::parse($i->date)->format('d M') }}: {{ $i->fromShift->name ?? 'None' }} → <strong>{{ $i->toShift->name ?? 'None' }}</strong></div>
                                @endforeach
                            </td>
                            <td><span class="badge {{ $sr->statusBadge() }}">{{ $sr->statusLabel() }}</span></td>
                            <td>{{ $sr->decider->name ?? '—' }}@if ($sr->decided_at)<div class="p360-note m-0">{{ $sr->decided_at->format('d M Y, h:i A') }}</div>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="p360-toolbar mt-4 mb-2">
        <div class="p360-sub m-0">Shift change history</div>
        @feature('custom_shift')
        @feature('daily_reports')
            <a href="{{ route('report.attendance.shift-changes.index', ['user_id' => $user->id, 'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->toDateString()]) }}" class="btn btn-sm btn-light-brand">
                <i class="feather-list me-1"></i>Full log</a>
        @endfeature
        @endfeature
    </div>
    @if ($changeLog->isEmpty())
        <p class="p360-note">No shift changes recorded yet.</p>
    @else
        <div class="table-responsive">
            <table class="p360-table">
                <thead><tr><th>Changed on</th><th>Shift date</th><th>From → To</th><th>How</th><th>By</th><th>Reason</th></tr></thead>
                <tbody>
                    @foreach ($changeLog as $l)
                        <tr>
                            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('d M Y, h:i A') }}</td>
                            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($l->date)->format('d M Y') }}@if ($l->is_additional) <span class="p360-chip extra">Additional</span>@endif</td>
                            <td>{{ $l->from_name ?? 'None' }} → <strong>{{ $l->to_name ?? 'None' }}</strong></td>
                            <td>{{ \App\Models\ShiftChangeLog::SOURCE_LABELS[$l->source] ?? ucfirst(str_replace('_', ' ', $l->source)) }}@if ($l->request_no)<div class="p360-note m-0">{{ $l->request_no }}</div>@endif</td>
                            <td>{{ $l->actor_name ?? 'System' }}</td>
                            <td>{{ $l->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
