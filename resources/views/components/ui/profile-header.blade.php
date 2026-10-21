{{--
    <x-ui.profile-header :profile="$profile_header" />
    <x-ui.profile-header :profile="$profile_header" greeting="Welcome back" />
    <x-ui.profile-header :profile="$profile_header" :date="$date" :stats="[['label' => 'Distance', 'value' => '2.4 km']]" />

    Blue header card: photo, name + role, designation · department, contact /
    reporting / joining chips, and a white "Today" panel (status, in, out, hours).
    With :date the panel shows that day instead (ProfileHeaderData::forUser($user, $date))
    and In / Out carry their date; :stats adds extra label / value tiles after Hours.
    $profile['shifts'] (multi-shift day) adds one row per shift beside the
    tiles (the panel widens to two columns).
    $profile = App\Services\Dashboard\ProfileHeaderData::forUser() shape. Used on the
    Team member profile and the Employee / Manager dashboards. Styled centrally in
    theme-custom.css ("PROFILE HEADER").
--}}
@props(['profile', 'greeting' => null, 'date' => null, 'stats' => []])

@php
    $p = $profile;
    $today = $p['today'] ?? null;
    $joinedOn = ! empty($p['joining_date']) ? \Carbon\Carbon::parse($p['joining_date']) : null;
    $tenure = $joinedOn && $joinedOn->isPast() ? $joinedOn->diff(now()) : null;
    $tenureText = $tenure ? trim(($tenure->y ? $tenure->y . 'y ' : '') . ($tenure->m ? $tenure->m . 'm' : ($tenure->y ? '' : $tenure->d . 'd'))) : null;
    // Same day-status colours as the attendance calendars.
    $statusColors = [
        'Present' => '#16a34a', 'Checked In Only' => '#d97706', 'Half Day' => '#65a30d', 'Absent' => '#dc2626',
        'Full Day Leave' => '#7c3aed', 'First Half Leave' => '#a855f7', 'Second Half Leave' => '#a855f7',
        'Holiday' => '#0D6EFD', 'Week Off' => '#64748b', 'Upcoming' => '#94a3b8',
    ];
    $status = $today['day_status'] ?? null;
    $time = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('h:i A') : '--:--';
    $day = $date ? \Carbon\Carbon::parse($date) : now();
    // In / Out date line (date mode only): the clock-out can fall on the next day.
    $stamp = fn ($v) => $date && $v ? \Carbon\Carbon::parse($v)->format('d M Y') : null;
@endphp

<div {{ $attributes->merge(['class' => 'profile-header ui-profile-header']) }}>
    <div class="profile-main">
        <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" class="profile-avatar">
        <div class="profile-identity">
            @if ($greeting)
                <div class="profile-greeting">{{ $greeting }}</div>
            @endif
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h4 class="mb-0">{{ ucwords(strtolower($p['name'])) }}</h4>
                @if (! empty($p['role']))
                    <span class="profile-role">{{ ucfirst($p['role']) }}</span>
                @endif
            </div>
            <div class="profile-subtitle">
                {{ $p['designation'] ?: 'Designation not assigned' }}
                @if (! empty($p['department']) && $p['department'] !== 'N/A')
                    <span class="profile-sep">•</span>{{ $p['department'] }}
                @endif
            </div>
            <div class="profile-meta">
                <span class="profile-meta-item"><i class="feather-hash"></i>{{ $p['employee_id'] ?: 'N/A' }}</span>
                @if (! empty($p['email']))
                    <a class="profile-meta-item" href="mailto:{{ $p['email'] }}"><i class="feather-mail"></i>{{ $p['email'] }}</a>
                @endif
                @if (! empty($p['contact']))
                    <a class="profile-meta-item" href="tel:{{ $p['contact'] }}"><i class="feather-phone"></i>{{ $p['contact'] }}</a>
                @endif
                @if (! empty($p['reporting_head']))
                    <span class="profile-meta-item"><i class="feather-user-check"></i>Reports to {{ $p['reporting_head'] }}</span>
                @endif
                @if ($joinedOn)
                    <span class="profile-meta-item"><i class="feather-calendar"></i>Joined {{ $joinedOn->format('d M Y') }}{{ $tenureText ? ' · ' . $tenureText : '' }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="profile-today {{ ! empty($p['shifts']) ? 'is-wide' : '' }}">
        <div class="profile-today-head">
            <span>{{ $day->isToday() ? 'Today · ' . $day->format('D, d M') : $day->format('l, d M Y') }}</span>
            @if ($status)
                <span class="profile-today-status" style="background: {{ $statusColors[$status] ?? '#94a3b8' }};">{{ $status }}</span>
            @endif
        </div>
        @if ($today && (! empty($today['holiday_name']) || ! empty($today['leave_type'])))
            <div class="profile-today-note">
                {{ $today['holiday_name'] ?: ('Leave' . (! empty($today['leave_reason']) ? ': ' . \Illuminate\Support\Str::limit($today['leave_reason'], 40) : '')) }}
            </div>
        @endif
        {{-- Totals + extra tiles on the left, per-shift rows on the right (wide panel) --}}
        <div class="profile-today-body">
        <div class="profile-today-times">
            @foreach (['In' => $today['clock_in'] ?? null, 'Out' => $today['clock_out'] ?? null] as $label => $at)
                <div>
                    <small>{{ $label }}</small><strong>{{ $time($at) }}</strong>
                    @if ($stamp($at))
                        <span class="profile-today-date">{{ $stamp($at) }}</span>
                    @endif
                </div>
            @endforeach
            <div><small>Hours</small><strong>{{ ! empty($today['total_hours']) ? $today['total_hours'] : '--' }}</strong></div>
            @foreach ($stats as $stat)
                <div><small>{{ $stat['label'] }}</small><strong>{{ $stat['value'] }}</strong></div>
            @endforeach
        </div>
        @if (! empty($p['shifts']))
            <div class="profile-shifts">
                @foreach ($p['shifts'] as $shift)
                    <div class="profile-shift">
                        <div class="profile-shift-head">
                            <span class="profile-shift-name">{{ $shift['name'] }}</span>
                            <span class="profile-shift-tag {{ $shift['is_extra'] ? 'is-extra' : '' }}">{{ $shift['is_extra'] ? 'Extra' : 'Normal' }}</span>
                            @if ($shift['scheduled'])
                                <span class="profile-shift-sched">{{ $shift['scheduled'] }}</span>
                            @endif
                        </div>
                        <div class="profile-shift-body">
                            <span>{{ $shift['clock_in'] ? \Carbon\Carbon::parse($shift['clock_in'])->format('d M, h:i A') : '--:--' }}</span>
                            <i class="feather-arrow-right"></i>
                            @if ($shift['clock_out'])
                                <span>{{ \Carbon\Carbon::parse($shift['clock_out'])->format('d M, h:i A') }}</span>
                            @elseif ($shift['is_open'])
                                <span class="profile-shift-open">Open</span>
                            @else
                                <span>--:--</span>
                            @endif
                            <strong>{{ intdiv($shift['worked_minutes'], 60) }}h {{ $shift['worked_minutes'] % 60 }}m</strong>
                        </div>
                        @if ($shift['late_minutes'] || $shift['early_minutes'])
                            <div class="profile-shift-note">
                                {{ $shift['late_minutes'] ? 'Late ' . $shift['late_minutes'] . 'm' : '' }}{{ $shift['late_minutes'] && $shift['early_minutes'] ? ' · ' : '' }}{{ $shift['early_minutes'] ? 'Left early ' . $shift['early_minutes'] . 'm' : '' }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </div>
</div>
