{{--
    <x-ui.empty-state />
    <x-ui.empty-state icon="inbox" title="No leave requests" subtitle="Applications you submit will show up here." />

    Every list page needs a "no records" state; today it's ad hoc or missing
    entirely on most pages.
--}}
@props(['icon' => 'inbox', 'title' => 'No records found', 'subtitle' => null])

<div class="text-center py-5">
    <i class="feather-{{ $icon }}" style="font-size:32px;color:var(--gray-300);"></i>
    <p class="mt-2 mb-0 fw-semibold" style="font-size:13px;color:var(--text-secondary);">{{ $title }}</p>
    @isset($subtitle)
        <p class="mb-0" style="font-size:11.5px;color:var(--text-muted);">{{ $subtitle }}</p>
    @endisset
</div>
