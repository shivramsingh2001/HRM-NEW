{{--
    <x-ui.page-header title="Leave Requests" />
    <x-ui.page-header title="Apply Leave" :parent="['label' => 'Leave', 'route' => 'leave.view']" />
    <x-ui.page-header title="Leave Requests">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm">Apply Leave</button>
        </x-slot:actions>
    </x-ui.page-header>

    Standardizes the page-title/breadcrumb block every index page currently
    hand-rolls with slightly different markup. Uses the existing .page-header
    class (styled centrally in theme-custom.css — sticky, compact padding).
    `parent` adds one intermediate breadcrumb level (Home > parent > title)
    for sub-pages of a module (e.g. Leave > Apply Leave).
--}}
@props(['title', 'subtitle' => null, 'parent' => null])

<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">{{ $title }}</h5>
            {{-- @isset($subtitle)
                <span class="d-block fs-12 text-muted">{{ $subtitle }}</span>
            @endisset --}}
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            @isset($parent)
                <li class="breadcrumb-item"><a href="{{ route($parent['route']) }}">{{ $parent['label'] }}</a></li>
            @endisset
            <li class="breadcrumb-item active">{{ $title }}</li>
        </ul>
    </div>
    @isset($actions)
        <div class="page-header-right ms-auto d-flex align-items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
