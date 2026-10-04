@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Asset Status &amp; Category Summary" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']" />

    <div class="main-content" style="padding: 20px !important;">
        @include('client.report.partials.report-subnav', ['group' => 'asset'])
        <x-ui.filter-card title="Filter Report">
<form method="GET" class="d-flex flex-wrap gap-2 filter-row">
                <select name="branch_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <a href="{{ route('report.asset.summary.index') }}" class="btn btn-light btn-sm" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a>
            </form>
</x-ui.filter-card>
            <p class="text-muted mb-0 mt-1" style="font-size:10.5px;">Status and Category counts respect the Branch filter above; the By Branch breakdown always shows every branch.</p>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="fw-semibold mb-3" style="font-size:12px;">By Status</h6>
                    @forelse (\App\Models\Asset::STATUSES as $status)
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:11.5px;">
                            <x-ui.status-badge :status="$status" />
                            <strong>{{ $byStatus[$status] ?? 0 }}</strong>
                        </div>
                    @empty
                    @endforelse
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="fw-semibold mb-3" style="font-size:12px;">By Category</h6>
                    @forelse ($byCategory as $row)
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:11.5px;">
                            <span>{{ $row['name'] }}</span>
                            <strong>{{ $row['total'] }}</strong>
                        </div>
                    @empty
                        <x-ui.empty-state icon="pie-chart" title="No assets registered yet" />
                    @endforelse
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <h6 class="fw-semibold mb-3" style="font-size:12px;">By Branch</h6>
                    @forelse ($byBranch as $row)
                        <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:11.5px;">
                            <span>{{ $row['name'] }}</span>
                            <strong>{{ $row['total'] }}</strong>
                        </div>
                    @empty
                        <x-ui.empty-state icon="map-pin" title="No assets registered yet" />
                    @endforelse
                </div></div>
            </div>
        </div>
    </div>
@endsection
