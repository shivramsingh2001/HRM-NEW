@extends('client.layout.master')

@section('style')
    <style>
        .asset-table td, .asset-table th { font-size: 11.5px; padding: 8px 10px !important; vertical-align: middle; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Asset Register" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']">
        <x-slot:actions>
            <a href="{{ route('report.asset.register.export') }}?{{ http_build_query(request()->query()) }}" class="btn btn-light btn-sm">
                <i class="feather-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="d-flex flex-wrap gap-2">
                <select name="category_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach (\App\Models\Asset::STATUSES as $s)
                        <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
                <select name="branch_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control form-control-sm" style="width:auto;" placeholder="Search code, name, serial or assignee…" value="{{ request('search') }}">
                <a href="{{ route('report.asset.register.index') }}" class="btn btn-light btn-sm">Reset</a>
            </form>
        </div></div>

        <div class="card">
            <div class="card-body p-0">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="hard-drive" title="No assets match these filters" />
                @else
                    <x-ui.data-table>
                        <thead class="asset-table">
                            <tr><th>Code</th><th>Name</th><th>Category</th><th>Status</th><th>Branch</th><th>Assigned To</th><th>Purchase Date</th><th>Warranty End</th></tr>
                        </thead>
                        <tbody class="asset-table">
                            @foreach ($assets as $a)
                                <tr>
                                    <td>{{ $a->asset_code }}</td>
                                    <td><a href="{{ route('assets.show', encrypt($a->id)) }}">{{ $a->name }}</a></td>
                                    <td>{{ $a->category->name ?? '-' }}</td>
                                    <td><x-ui.status-badge :status="$a->status" /></td>
                                    <td>{{ $a->branch->name ?? '-' }}</td>
                                    <td>{{ $a->currentAssigneeUser->name ?? '-' }}</td>
                                    <td>{{ $a->purchase_date?->format('d M Y') ?? '-' }}</td>
                                    <td>{{ $a->warranty_end_date?->format('d M Y') ?? '-' }}</td>
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
