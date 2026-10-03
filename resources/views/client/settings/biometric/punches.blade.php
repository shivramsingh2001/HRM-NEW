@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Recent Punches"
        :parent="['label' => 'Biometric Terminals', 'route' => 'settings.biometric.index']">
        <x-slot:actions>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.biometric.index') }}">
                <i class="feather-arrow-left"></i> Devices
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    <x-ui.filter-card title="Filter Punches">
<form method="GET" class="d-flex gap-2 align-items-center flex-wrap filter-row">
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" style="max-width:280px"
                   placeholder="Search enroll no., employee or device…">
            <select name="status" class="form-control form-control-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">all statuses</option>
                @foreach (['pending','processed','skipped','error'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-primary"><i class="feather-search"></i></button>
            @if ($q !== '' || request('status'))
                <a href="{{ route('settings.biometric.punches') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters">×</a>
            @endif
        </form>
</x-ui.filter-card>

    <div class="s-card"><div class="s-body">
        @if ($punches->isEmpty())
            @if ($q !== '')
                <x-ui.empty-state icon="activity" title="No matches" subtitle="No punches match your search." />
            @else
                <x-ui.empty-state icon="activity" title="No punches yet" />
            @endif
        @else
            <x-ui.data-table>
                <thead><tr>
                    <th width="40">#</th>
                    <th>When</th><th>Device</th><th>Enroll</th><th>Employee</th><th>Dir</th>
                    <th>Method</th><th>Status</th><th>Error</th><th class="text-center">Actions</th>
                </tr></thead>
                <tbody id="punchesTbody">
                    @include('client.settings.biometric.partials._punch-rows')
                </tbody>
            </x-ui.data-table>
        @endif
        <div class="mt-2" id="punchesPagination">{{ $punches->links() }}</div>
    </div></div>

    </div>
@endsection

@section('script-area')
    <script>
        let punchesInFlight = false;

        function refreshPunches() {
            if (punchesInFlight || document.activeElement?.name === 'q') return;
            punchesInFlight = true;

            $.ajax({
                url: window.location.href,
                type: 'GET',
                headers: { Accept: 'application/json' },
                success: function(res) {
                    if (res.html !== undefined) { $('#punchesTbody').html(res.html); }
                    if (res.pagination !== undefined) { $('#punchesPagination').html(res.pagination); }

                    [].slice.call(document.querySelectorAll('#punchesTbody [data-bs-toggle="tooltip"]')).map(function(el) {
                        return new bootstrap.Tooltip(el);
                    });
                },
                complete: function() { punchesInFlight = false; }
            });
        }

        $(function() {
            [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(function(el) {
                return new bootstrap.Tooltip(el);
            });

            setInterval(refreshPunches, 7000);
        });
    </script>
@endsection
