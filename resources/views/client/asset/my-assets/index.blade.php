@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 10px 14px;
            margin-bottom: 14px;
            box-shadow: var(--shadow-sm);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-title i {
            color: var(--primary);
            font-size: 12px;
            background: var(--primary-light);
            padding: 5px;
            border-radius: 8px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-item.select-filter {
            min-width: 180px;
        }

        .filter-item.select-filter select {
            width: 100%;
            height: 34px;
            padding: 6px 32px 6px 12px;
            font-size: 11.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-item.select-filter select:hover {
            background-color: white;
            border-color: #cbd5e1;
        }

        .filter-item.select-filter select:focus {
            border-color: var(--primary-mid);
            outline: none;
            background-color: white;
            box-shadow: var(--shadow-focus);
        }

        .reset-btn {
            height: 34px;
            padding: 0 14px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .reset-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .reset-btn i {
            font-size: 12px;
        }

        /* ==================== TABLE / CARD ==================== */
        .card {
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .card-title {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }

        .badge-info-custom {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 16px !important;
            border: 1px solid var(--border-focus) !important;
            font-size: 11px !important;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            padding: 10px 12px;
            white-space: nowrap;
            border-bottom: 2px solid #e2e8f0;
        }

        .table tbody tr:hover td {
            background-color: #f8fafc;
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

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--border-focus);
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        @media (max-width: 768px) {
            .filter-wrapper {
                padding: 12px 14px;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-item {
                width: 100%;
            }

            .reset-btn {
                width: 100%;
                justify-content: center;
            }
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
            <div class="card-header">
                <h5 class="card-title">
                    <i class="feather-hard-drive me-2" style="color: var(--primary);"></i>
                    My Assets
                </h5>
                <span class="badge badge-info-custom">
                    <i class="feather-list me-1"></i>Total: {{ $assets->total() ?? count($assets) }}
                </span>
            </div>
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
