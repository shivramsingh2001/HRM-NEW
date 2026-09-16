{{--
    <x-ui.data-table>
        <thead><tr><th>Name</th></tr></thead>
        <tbody>...</tbody>
    </x-ui.data-table>

    Thin wrapper enforcing table-responsive + the shared .table styling
    (theme-custom.css) automatically — currently only 60/141 pages wrap
    their tables in table-responsive at all.
--}}
@props(['hover' => true])

<div class="table-responsive">
    <table {{ $attributes->class(['table', 'table-hover' => $hover]) }}>
        {{ $slot }}
    </table>
</div>
