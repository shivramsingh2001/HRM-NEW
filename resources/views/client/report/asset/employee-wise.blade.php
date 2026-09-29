@extends('client.layout.master')

@section('style')
    <style>
        .fs-card { border: 1px solid #eaeef5; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .fs-meta { font-size: 9px; color: #6b7385; }
        .fs-body { font-size: 11px; color: #1a2236; margin-top: 4px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Employee-wise Assets" :parent="['label' => 'Reports', 'route' => 'report.attendance.index']" />

    <div class="main-content" style="padding: 20px !important;">
        <div class="card mb-3"><div class="card-body">
            <form method="GET" class="d-flex flex-wrap gap-2">
                <select name="branch_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Branches</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" onchange="this.form.submit()" onkeydown="if (event.key === 'Enter') { event.preventDefault(); this.form.submit(); }" class="form-control form-control-sm" style="width:auto;" placeholder="Search employee or ID…" value="{{ request('search') }}">
                <a href="{{ route('report.asset.employee-wise.index') }}" class="btn btn-light btn-sm" title="Reset filters" aria-label="Reset filters"><i class="feather-refresh-cw"></i></a>
            </form>
        </div></div>

        @forelse ($users as $user)
            <div class="card mb-2">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2" style="font-size:12px;">
                        {{ $user->name }} <small class="text-muted">({{ $user->employee_id }})</small>
                        <small class="text-muted">&middot; {{ $user->jobDetails->branch->name ?? 'No branch' }}</small>
                    </h6>
                    @foreach ($user->assetAssignments as $a)
                        <div class="fs-card">
                            <div class="fs-meta">{{ $a->assigned_at?->format('d M Y') }}</div>
                            <div class="fs-body">
                                <strong>{{ $a->asset->name ?? '-' }}</strong> ({{ $a->asset->asset_code ?? '-' }})
                                &middot; {{ $a->asset->category->name ?? '-' }}
                                &middot; <x-ui.status-badge :status="$a->status" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="users" title="No employees currently hold any assets" />
        @endforelse

        <div class="p-3">{{ $users->links('pagination::bootstrap-4') }}</div>
    </div>
@endsection
