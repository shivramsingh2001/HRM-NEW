{{--
    <x-ui.profile-header :profile="$profile_header" />
    <x-ui.profile-header :profile="$profile_header" greeting="Welcome back" />

    Blue header card: photo, name + role, designation · department, contact /
    reporting / joining chips, and a white "Today" panel (status, in, out, hours).
    $profile = App\Services\Dashboard\ProfileHeaderData::forUser() shape. Used on the
    Team member profile and the Employee / Manager dashboards. Styled centrally in
    theme-custom.css ("PROFILE HEADER").
--}}
@props(['profile', 'greeting' => null])

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

    <div class="profile-today">
        <div class="profile-today-head">
            <span>Today · {{ now()->format('D, d M') }}</span>
            @if ($status)
                <span class="profile-today-status" style="background: {{ $statusColors[$status] ?? '#94a3b8' }};">{{ $status }}</span>
            @endif
        </div>
        @if ($today && (! empty($today['holiday_name']) || ! empty($today['leave_type'])))
            <div class="profile-today-note">
                {{ $today['holiday_name'] ?: ('Leave' . (! empty($today['leave_reason']) ? ': ' . \Illuminate\Support\Str::limit($today['leave_reason'], 40) : '')) }}
            </div>
        @endif
        <div class="profile-today-times">
            <div><small>In</small><strong>{{ $time($today['clock_in'] ?? null) }}</strong></div>
            <div><small>Out</small><strong>{{ $time($today['clock_out'] ?? null) }}</strong></div>
            <div><small>Hours</small><strong>{{ ! empty($today['total_hours']) ? $today['total_hours'] : '--' }}</strong></div>
        </div>
    </div>
</div>
