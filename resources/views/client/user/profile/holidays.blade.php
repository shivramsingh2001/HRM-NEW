{{-- Employee 360 — company holidays (EmployeeProfileController::tabHolidays) --}}
@php $url = fn ($y) => route('employee.profile.tab', ['id' => $encId, 'tab' => 'holidays', 'year' => $y]); @endphp

<div class="p360-toolbar">
    <h5 class="section-title mb-0 border-0 pb-0"><i class="feather-gift"></i> Holidays {{ $year }}</h5>
    <div class="btn-group btn-group-sm">
        <a href="{{ $url($year - 1) }}" class="btn btn-outline-primary" data-p360-reload>‹ {{ $year - 1 }}</a>
        <a href="{{ $url($year + 1) }}" class="btn btn-outline-primary" data-p360-reload>{{ $year + 1 }} ›</a>
    </div>
</div>

@if ($holidays->isEmpty())
    <x-ui.empty-state icon="gift" title="No holidays" subtitle="No company holidays are set for {{ $year }}." />
@else
    <table class="p360-table">
        <thead><tr><th>Holiday</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead>
        <tbody>
            @foreach ($holidays as $h)
                @php
                    $from = \Carbon\Carbon::parse($h->start_date);
                    $to = \Carbon\Carbon::parse($h->end_date ?: $h->start_date);
                @endphp
                <tr>
                    <td>{{ $h->name }}</td>
                    <td>{{ $from->format('d M Y, D') }}</td>
                    <td>{{ $to->format('d M Y, D') }}</td>
                    <td>{{ $from->diffInDays($to) + 1 }}</td>
                    <td>
                        @if ($to->lt(today()))<span class="p360-chip muted">Past</span>
                        @elseif ($from->lte(today()))<span class="p360-chip">Today</span>
                        @else<span class="p360-chip">Upcoming</span>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
