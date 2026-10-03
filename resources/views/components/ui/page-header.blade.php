{{--
    The one page header for every page (design reference: Biometric Terminals).
    Layout: title │ breadcrumb (Home › … › current) ........ [Back] [actions]

    <x-ui.page-header title="Leave Requests" />

    <x-ui.page-header title="Apply Leave" :parent="['label' => 'Leave', 'route' => 'leave.view']" />

    <x-ui.page-header title="Pay Batch" back
        :crumbs="[['label' => 'Expenses', 'url' => route('expense.index')], ['label' => 'Payments', 'url' => route('expense.payments.index')]]">
        <x-slot:actions>
            <button class="btn btn-primary btn-sm">Save</button>
        </x-slot:actions>
    </x-ui.page-header>

    Props
      title    the page title (also the last breadcrumb unless `current` is given)
      current  breadcrumb label for this page when it differs from the title
      parent   one intermediate crumb: ['label' => …, 'route' => 'route.name'] (kept for older call sites)
      crumbs   any number of intermediate crumbs: [['label' => …, 'url' => …], …] — url optional
      back     shown on every page by default (previous page; dashboard when opened directly,
               after login or from another site). A URL = go there instead. :back="false" hides it
               (dashboards — they are the landing page).
      subtitle accepted for compatibility, not shown (the header stays one line)

    Styles: .page-header in public/assets/css/theme-custom.css ("PAGE HEADER — shared
    design"); the Back button is .ph-back; action buttons get a uniform size there.
--}}
@props(['title', 'subtitle' => null, 'parent' => null, 'crumbs' => [], 'current' => null, 'back' => true])

@php
    $trail = [];
    if ($parent) {
        $trail[] = ['label' => $parent['label'], 'url' => isset($parent['route']) ? route($parent['route']) : ($parent['url'] ?? null)];
    }
    foreach ((array) $crumbs as $crumb) {
        $trail[] = is_array($crumb) ? $crumb + ['url' => null] : ['label' => (string) $crumb, 'url' => null];
    }

    $backUrl = null;
    if ($back === true || $back === '' || $back === '1' || $back === 1) {
        // Previous page — unless it is this page, the login screen, or another site.
        $previous = url()->previous();
        $prevPath = '/' . trim((string) parse_url($previous, PHP_URL_PATH), '/');
        $sameSite = parse_url($previous, PHP_URL_HOST) === request()->getHost();
        $backUrl = $previous && $sameSite && $previous !== url()->current() && ! in_array($prevPath, ['/', '/login', '/logout'], true)
            ? $previous
            : route('dashboard');
    } elseif (is_string($back) && $back !== '' && $back !== '0') {
        $backUrl = $back;
    }
@endphp

<div {{ $attributes->merge(['class' => 'page-header']) }}>
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">{{ $title }}</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            @foreach ($trail as $crumb)
                <li class="breadcrumb-item">
                    @if (!empty($crumb['url']))
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @else
                        {{ $crumb['label'] }}
                    @endif
                </li>
            @endforeach
            <li class="breadcrumb-item active">{{ $current ?? $title }}</li>
        </ul>
    </div>
    @if ($backUrl || isset($actions))
        <div class="page-header-right ph-actions ms-auto">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="ph-back"><i class="feather-arrow-left"></i><span>Back</span></a>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
