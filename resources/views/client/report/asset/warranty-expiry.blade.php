@extends('client.layout.master')

@section('style')
    <style>
        .asset-table td, .asset-table th { font-size: 11.5px; padding: 8px 10px !important; vertical-align: middle; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Warranty Expiry" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']" />

    <div class="main-content" style="padding: 20px !important;">
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <label style="font-size:11.5px;" class="fw-semibold mb-0">Within</label>
                <select name="days" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="30" {{ request('days', 90) == 30 ? 'selected' : '' }}>30 days</option>
                    <option value="90" {{ request('days', 90) == 90 ? 'selected' : '' }}>90 days</option>
                    <option value="180" {{ request('days', 90) == 180 ? 'selected' : '' }}>180 days</option>
                </select>
                <select name="branch_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control form-control-sm" style="width:auto;" placeholder="Search code, name or assignee…" value="{{ request('search') }}">
                <a href="{{ route('report.asset.warranty-expiry.index') }}" class="btn btn-light btn-sm">Reset</a>
            </form>
        </div></div>

        <div class="card">
            <div class="card-body p-0">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="alert-triangle" title="No assets expiring in this window" />
                @else
                    <x-ui.data-table>
                        <thead class="asset-table"><tr><th>Code</th><th>Name</th><th>Category</th><th>Branch</th><th>Assigned To</th><th>Warranty End</th></tr></thead>
                        <tbody class="asset-table">
                            @foreach ($assets as $a)
                                <tr>
                                    <td>{{ $a->asset_code }}</td>
                                    <td><a href="{{ route('assets.show', encrypt($a->id)) }}">{{ $a->name }}</a></td>
                                    <td>{{ $a->category->name ?? '-' }}</td>
                                    <td>{{ $a->branch->name ?? '-' }}</td>
                                    <td>{{ $a->currentAssigneeUser->name ?? '-' }}</td>
                                    <td class="{{ $a->warranty_end_date?->isPast() ? 'text-danger fw-semibold' : '' }}">
                                        {{ $a->warranty_end_date?->format('d M Y') }}
                                        @if ($a->warranty_end_date?->isPast())
                                            (expired)
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>
                    <div class="p-3">{{ $assets->links('pagination::bootstrap-4') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
