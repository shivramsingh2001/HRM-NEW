{{-- Employee 360 — attendance month (EmployeeProfileController::tabAttendance) --}}
@php
    $prev = $monthStart->copy()->subMonth()->format('Y-m');
    $next = $monthStart->copy()->addMonth()->format('Y-m');
    $url = fn ($m) => route('employee.profile.tab', ['id' => $encId, 'tab' => 'attendance', 'month' => $m]);
    $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q);
@endphp

<div class="p360-toolbar">
    <h5 class="section-title mb-0 border-0 pb-0"><i class="feather-calendar"></i> {{ $monthStart->format('F Y') }}</h5>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group btn-group-sm">
            <a href="{{ $url($prev) }}" class="btn btn-outline-primary" data-p360-reload>‹ Prev</a>
            <a href="{{ $url(now()->format('Y-m')) }}" class="btn btn-outline-primary" data-p360-reload>This month</a>
            <a href="{{ $url($next) }}" class="btn btn-outline-primary" data-p360-reload>Next ›</a>
        </div>
        <a href="#" class="btn btn-sm btn-primary" data-p360-open="{{ $form('attendance-mark') }}" data-title="Mark attendance — {{ $user->name }}" data-size="md">
            <i class="feather-check-square me-1"></i>Mark attendance</a>
    </div>
</div>

@if ($summary)
    <div class="p360-kpis">
        <div class="p360-kpi"><div class="v">{{ $summary->present_days }}</div><div class="l">Present</div></div>
        <div class="p360-kpi"><div class="v">{{ $summary->absent_days }}</div><div class="l">Absent</div></div>
        <div class="p360-kpi"><div class="v">{{ $summary->half_days }}</div><div class="l">Half days</div></div>
        <div class="p360-kpi"><div class="v">{{ $summary->late_days }}</div><div class="l">Late</div></div>
        <div class="p360-kpi"><div class="v">{{ $summary->total_leaves }}</div><div class="l">Leaves</div></div>
        <div class="p360-kpi"><div class="v">{{ round((float) $summary->total_worked_hours, 1) }}</div><div class="l">Hours worked</div></div>
    </div>
@endif

<div class="table-responsive">
    <table class="p360-table">
        <thead><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Hours</th><th>Late</th><th>Early</th><th>Note</th><th></th></tr></thead>
        <tbody>
            @for ($d = $monthStart->copy(); $d->lte($monthStart->copy()->endOfMonth()) && $d->lte(now()); $d->addDay())
                @php $r = $rows->get($d->toDateString()); @endphp
                <tr>
                    <td>{{ $d->format('d D') }}</td>
                    <td>
                        @if ($r)
                            <x-ui.status-badge :status="$r->effective_status ?: ($r->attendance_status ?: 'present')" />
                        @else
                            <span class="p360-note">—</span>
                        @endif
                    </td>
                    <td>{{ $r && $r->clock_in ? \Carbon\Carbon::parse($r->clock_in)->format('h:i A') : '—' }}</td>
                    <td>{{ $r && $r->clock_out ? \Carbon\Carbon::parse($r->clock_out)->format('h:i A') : '—' }}</td>
                    <td>{{ $r && $r->worked_hours ? round((float) $r->worked_hours, 2) : '—' }}</td>
                    <td>{{ $r && $r->late_minutes ? $r->late_minutes . 'm' : '' }}</td>
                    <td>{{ $r && $r->early_departure_minutes ? $r->early_departure_minutes . 'm' : '' }}</td>
                    <td>
                        @if ($r && (int) $r->shift_count > 1)<span class="p360-chip extra">{{ $r->shift_count }} shifts · +{{ round($r->extra_shift_minutes / 60, 1) }}h</span>@endif
                        @if ($r && $r->is_regularized)<span class="p360-chip">Regularized</span>@endif
                        @if ($r && $r->attendance_type === 'manual')<span class="p360-chip muted">Manual</span>@endif
                    </td>
                    <td class="text-end">
                        <a href="#" class="p360-icon-btn" title="Mark this day" data-size="md"
                            data-p360-open="{{ $form('attendance-mark', ['date' => $d->toDateString()]) }}" data-title="Mark attendance — {{ $d->format('d M Y') }}">
                            <i class="feather-edit-2"></i></a>
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>
@if ($monthStart->gt(now()))
    <p class="p360-note mt-2">This month has not started yet.</p>
@endif

<div class="p360-sub">Recent regularization requests</div>
@if ($regularizations->isEmpty())
    <p class="p360-note">None.</p>
@else
    <table class="p360-table">
        <thead><tr><th>Date</th><th>Type</th><th>In</th><th>Out</th><th>Reason</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach ($regularizations as $g)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($g->date)->format('d M Y') }}</td>
                    <td>{{ str_replace('_', ' ', $g->request_type) }}</td>
                    <td>{{ $g->in_time ?: '—' }}</td>
                    <td>{{ $g->out_time ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($g->reason, 50) }}</td>
                    <td><x-ui.status-badge :status="$g->status" /></td>
                    <td class="text-end text-nowrap">
                        @if ($g->status === 'pending' && $can['attendance_approve'])
                            <a href="#" class="btn btn-sm btn-primary" data-title="Approve regularization"
                                data-p360-open="{{ $form('regularization', ['reg' => $g->id, 'decision' => 'approved']) }}">Approve</a>
                            <a href="#" class="btn btn-sm btn-light-brand" data-title="Reject regularization"
                                data-p360-open="{{ $form('regularization', ['reg' => $g->id, 'decision' => 'rejected']) }}">Reject</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
<p class="p360-note mt-3"><a href="{{ route('team.member-detail', ['id' => $encId]) }}">Open the full attendance view →</a></p>
