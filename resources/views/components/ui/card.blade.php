{{--
    <x-ui.card>content</x-ui.card>
    <x-ui.card title="Recent Activity">content</x-ui.card>
    <x-ui.card title="Recent Activity" :bodyClass="'p-0'">content</x-ui.card>
--}}
@props(['title' => null, 'bodyClass' => ''])

<div {{ $attributes->class(['card']) }}>
    @isset($title)
        <div class="card-header">
            <h5 class="card-title">{{ $title }}</h5>
            @isset($headerActions)
                <div class="ms-auto">{{ $headerActions }}</div>
            @endisset
        </div>
    @endisset
    <div class="card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>
</div>
