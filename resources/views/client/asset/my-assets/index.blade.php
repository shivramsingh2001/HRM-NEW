@extends('client.layout.master')

@section('style')
    <style>



        .badge-info-custom {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 16px !important;
            border: 1px solid var(--border-focus) !important;
            font-size: 11px !important;
        }



        .asset-table td,
        .asset-table th {
            font-size: 11.5px;
            vertical-align: middle;
        }

        .asset-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 160px;
        }

        .asset-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 14px;
            flex-shrink: 0;
        }

        .asset-details {
            line-height: 1.3;
            min-width: 0;
        }

        .asset-name-text {
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }

        .asset-name-text a {
            color: #0f172a;
            text-decoration: none;
        }

        .asset-name-text a:hover {
            color: var(--primary);
        }

        .asset-code {
            font-family: monospace;
            font-size: 10.5px;
            color: var(--primary);
            font-weight: 600;
        }



        @media (max-width: 768px) {




        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="My Assets" subtitle="Assets currently or previously assigned to you." />

    <div class="main-content" style="padding: 20px !important;">
        @if ($pendingAcceptanceCount > 0)
            <div class="alert alert-warning d-flex align-items-center gap-2" style="font-size:11.5px;border-radius:10px;">
                <i class="feather-alert-circle"></i>
                You have {{ $pendingAcceptanceCount }} asset(s) awaiting your acceptance.
            </div>
        @endif

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter My Assets
                </div>
                @if (request()->hasAny(['status']))
                    <a href="{{ route('my-assets.index') }}" class="reset-btn" style="height: auto; padding: 4px 12px; font-size: 12px;">
                        <i class="feather-x"></i> Clear Filters
                    </a>
                @endif
            </div>

            <form action="{{ route('my-assets.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item select-filter">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            @foreach (\App\Models\Asset::STATUSES as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <a href="{{ route('my-assets.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- My Assets Table -->
        <div class="card">
            <div class="card-body p-0">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="hard-drive" title="No assets assigned to you yet" />
                @else
                    <x-ui.data-table>
                        <thead>
                            <tr class="asset-table">
                                <th>Asset</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Branch</th>
                                <th style="width:70px">Action</th>
                            </tr>
                        </thead>
                        <tbody class="asset-table">
                            @foreach ($assets as $asset)
                                <tr>
                                    <td>
                                        <div class="asset-info">
                                            <div class="asset-avatar"><i class="feather-hard-drive"></i></div>
                                            <div class="asset-details">
                                                <div class="asset-name-text">
                                                    <a href="{{ route('my-assets.show', encrypt($asset->id)) }}">{{ $asset->name }}</a>
                                                </div>
                                                <div class="asset-code">{{ $asset->asset_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $asset->category->name ?? '-' }}</td>
                                    <td><x-ui.status-badge :status="$asset->status" /></td>
                                    <td>{{ $asset->branch->name ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('my-assets.show', encrypt($asset->id)) }}" class="action-btn" title="View Asset">
                                            <i class="feather-eye"></i>
                                        </a>
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
