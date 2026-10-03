{{--
    The filter section used on every list / report page (design reference:
    Team → "Filter Team Members"). Styles: "FILTER CARD" in theme-custom.css.

    <x-ui.filter-card title="Filter Team Members"
        :clear-url="request()->hasAny(['date', 'status']) ? route('team.index') : null">
        <form action="{{ route('team.index') }}" method="GET" id="filterForm">
            <div class="filter-row">
                <div class="filter-item search">
                    <div class="search-wrapper"><i class="feather-search"></i>
                        <input type="text" name="search" placeholder="Search…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="filter-item">
                    <select name="status" onchange="this.form.submit()">…</select>
                </div>
                <div class="filter-item"><a href="{{ route('team.index') }}" class="reset-btn"><i class="feather-refresh-cw"></i> Reset</a></div>
            </div>
        </form>
    </x-ui.filter-card>

    Props
      title     heading text (default "Filters")
      icon      feather icon name (default "filter")
      clearUrl  when set, a "Clear Filters" link is shown in the header
    Put the page's own form in the slot unchanged — field names, routes,
    onchange auto-submit and query parameters stay exactly as they were.
--}}
@props(['title' => 'Filters', 'icon' => 'filter', 'clearUrl' => null])

<div {{ $attributes->merge(['class' => 'filter-wrapper']) }}>
    <div class="filter-header">
        <div class="filter-title">
            <i class="feather-{{ $icon }}"></i>
            {{ $title }}
        </div>
        @if ($clearUrl)
            <a href="{{ $clearUrl }}" class="reset-btn"><i class="feather-x"></i> Clear Filters</a>
        @endif
    </div>
    {{ $slot }}
</div>
