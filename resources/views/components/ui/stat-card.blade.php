{{--
    <x-ui.stat-card icon="users" label="Total Employees" value="128" />
    <x-ui.stat-card icon="clock" label="Pending" value="4" pill="This month" />

    Replaces the .kpi5-card/.stats-card/dashboard-stat-card family — three
    markup variants of the same thing found across the app. This is the one
    canonical version, styled centrally in theme-custom.css (.kpi5-*).
--}}
@props(['icon' => 'bar-chart-2', 'label', 'value', 'pill' => null, 'valueId' => null, 'color' => null])

<div {{ $attributes->merge(['class' => 'kpi5-card' . ($color ? ' kpi5-card--' . $color : '')]) }}>
    <div class="kpi5-top">
        <span class="kpi5-icon"><i class="feather-{{ $icon }}"></i></span>
        @isset($pill)
            <span class="kpi5-pill">{{ $pill }}</span>
        @endisset
    </div>
    <div class="kpi5-value" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</div>
    <div class="kpi5-label">{{ $label }}</div>
</div>
