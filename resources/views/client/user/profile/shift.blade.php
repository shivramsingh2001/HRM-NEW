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
