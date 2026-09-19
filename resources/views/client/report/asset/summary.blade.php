@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header title="Asset Status &amp; Category Summary" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']" />

    <div class="main-content" style="padding: 20px !important;">
        <div class="row g-3">
            <div class="col-md-6">
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
            <div class="col-md-6">
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
        </div>
    </div>
@endsection
