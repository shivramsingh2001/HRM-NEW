@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        /* Stats Card Base Styles */
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 20px 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            border: 1px solid #edf2f7;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }

        /* Card-specific gradient borders */
        .total-card::before {
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .pending-card::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .approved-card::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .cancelled-card::before {
            background: linear-gradient(90deg, #6b7280, #9ca3af);
        }

        /* Icon Wrapper */
        .stats-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .stats-card:hover .stats-icon-wrapper {
            transform: scale(1.05);
        }

        /* Card-specific icon backgrounds */
        .total-card .stats-icon-wrapper {
            background: rgba(79, 70, 229, 0.1);
        }

        .total-card .stats-icon-wrapper i {
            color: #4f46e5;
            font-size: 24px;
        }

        .pending-card .stats-icon-wrapper {
            background: rgba(245, 158, 11, 0.1);
        }

        .pending-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 24px;
        }

        .approved-card .stats-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
        }

        .approved-card .stats-icon-wrapper i {
            color: #10b981;
            font-size: 24px;
        }

        .cancelled-card .stats-icon-wrapper {
            background: rgba(107, 114, 128, 0.1);
        }

        .cancelled-card .stats-icon-wrapper i {
            color: #6b7280;
            font-size: 24px;
        }

        /* Content Styles */
        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.3;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stats-label {
            font-size: 10px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }

        .stats-count {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .count-number {
            font-size: 11px;
            font-weight: 600;
        }

        .count-text {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==================== FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-title i {
            color: #4f46e5;
            font-size: 16px;
        }

        .filter-title span {
            background: #eef2ff;
            color: #4f46e5;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
        }

        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .clear-all-link:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 140px;
        }

        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .apply-btn {
            height: 36px;
            padding: 0 16px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .reset-btn {
            height: 36px;
            padding: 0 12px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .active-filters {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .active-filters-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .filter-tag {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 3px 10px 3px 8px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .filter-tag i {
            color: #4f46e5;
            font-size: 11px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
        }

        .filter-tag.clear-all {
            background: #eef2ff;
            border-color: #4f46e5;
            color: #4f46e5;
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        /* ==================== TABLE STYLES ==================== */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 12px 16px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* ==================== BADGES ==================== */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .badge.bg-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-purple {
            background: #e0e7ff !important;
            color: #4f46e5;
        }

        /* ==================== REQUEST TYPE BADGES ==================== */
        .request-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .type-wfh {
            background: #e0f2fe;
            color: #0369a1;
        }

        .type-travel {
            background: #fef3c7;
            color: #92400e;
        }

        /* ==================== DATE RANGE STYLING ==================== */
        .date-range {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .date-range .start-date {
            font-weight: 600;
            color: #1e293b;
        }

        .date-range .end-date {
            font-size: 11px;
            color: #64748b;
        }

        .date-range .end-date i {
            font-size: 10px;
            margin-right: 2px;
        }

        /* ==================== DURATION BADGE ==================== */
        .duration-badge {
            background: #f1f5f9;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 500;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .duration-badge i {
            font-size: 10px;
            color: #4f46e5;
        }

        /* ==================== REASON CELL ==================== */
        .reason-cell {
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
            font-size: 12px;
            color: #334155;
            line-height: 1.4;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            margin: 0 2px;
        }

        .action-btn:hover {
            background: white;
            transform: translateY(-2px);
        }

        .action-btn.view:hover {
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .action-btn.edit:hover {
            color: #10b981;
            border-color: #10b981;
        }

        .action-btn.delete:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

        .action-btn i {
            font-size: 14px;
        }

        .action-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            margin: 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: #334155;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .filter-row {
                gap: 8px;
            }

            .filter-item {
                flex: 1 1 calc(50% - 8px);
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .filter-wrapper {
                padding: 12px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .apply-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Travel & WFH Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Travel & WFH Requests</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="#" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <div class="dropdown">
                        <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10"
                            data-bs-auto-close="outside">
                            <i class="feather-download"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="#" class="dropdown-item" onclick="exportToCSV()">
                                <i class="bi bi-filetype-csv me-3"></i>
                                <span>Export CSV</span>
                            </a>
                        </div>
                    </div>
                    <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addRequestModal">
                        <i class="feather-plus me-2"></i>
                        <span>New Request</span>
                    </a>
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="#" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- Total Requests Card -->
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-file-text"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $totalRequests ?? 0 }}</div>
                    <div class="stats-label">Total Requests</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $totalDays ?? 0 }}</span>
                        <span class="count-text">Total Days</span>
                    </div>
                </div>
            </div>

            <!-- Pending Requests Card -->
            <div class="stats-card pending-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-clock"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $pendingRequests ?? 0 }}</div>
                    <div class="stats-label">Pending</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $pendingDays ?? 0 }}</span>
                        <span class="count-text">Days</span>
                    </div>
                </div>
            </div>

            <!-- Approved Requests Card -->
            <div class="stats-card approved-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $approvedRequests ?? 0 }}</div>
                    <div class="stats-label">Approved</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $approvedDays ?? 0 }}</span>
                        <span class="count-text">Days</span>
                    </div>
                </div>
            </div>

            <!-- Cancelled Requests Card -->
            <div class="stats-card cancelled-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $cancelledRequests ?? 0 }}</div>
                    <div class="stats-label">Rejected</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $cancelledDays ?? 0 }}</span>
                        <span class="count-text">Days</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter My Requests
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'request_type', 'from_date', 'to_date']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'request_type', 'from_date', 'to_date']))
                    <a href="{{ route('requests.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('requests.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                            <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="REJECTED" {{ request('status') == 'REJECTED' ? 'selected' : '' }}>Rejected
                            </option>
                            <option value="CANCELLED" {{ request('status') == 'CANCELLED' ? 'selected' : '' }}>Cancelled
                            </option>
                        </select>
                    </div>

                    <!-- Request Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="request_type">
                            <option value="">All Types</option>
                            @foreach ($requestTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ request('request_type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->type_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- From Date -->
                    <div class="filter-item">
                        <input type="date" class="form-control" name="from_date"
                            style="padding: .375rem .75rem !important;" value="{{ request('from_date') }}"
                            placeholder="From Date">
                    </div>

                    <!-- To Date -->
                    <div class="filter-item">
                        <input type="date" class="form-control" name="to_date"
                            style="padding: .375rem .75rem !important;" value="{{ request('to_date') }}"
                            placeholder="To Date">
                    </div>

                    <!-- Action Buttons -->
                    <div class="filter-item" style="min-width: auto;">
                        <button type="submit" class="apply-btn">
                            <i class="feather-search"></i>
                            Apply
                        </button>
                    </div>

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('requests.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'request_type', 'from_date', 'to_date']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(strtolower(request('status'))) }}
                            <a href="{{ route('requests.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('request_type'))
                        @php
                            $selectedType = $requestTypes->firstWhere('id', request('request_type'));
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Type: {{ $selectedType->type_name ?? request('request_type') }}
                            <a href="{{ route('requests.index', array_merge(request()->except(['request_type', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ \Carbon\Carbon::parse(request('from_date'))->format('d M Y') }}
                            <a href="{{ route('requests.index', array_merge(request()->except(['from_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ \Carbon\Carbon::parse(request('to_date'))->format('d M Y') }}
                            <a href="{{ route('requests.index', array_merge(request()->except(['to_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('requests.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Requests Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">My Requests</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $requests->total() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="requestsTable">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Request Type</th>
                                        <th>Date Range</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                        <th>Applied On</th>
                                        {{-- <th>Reporting Head</th> --}}
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($requests as $request)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <span
                                                    class="request-type-badge {{ $request->requestType->type_name == 'WFH' ? 'type-wfh' : 'type-travel' }}">
                                                    <i
                                                        class="feather-{{ $request->requestType->type_name == 'WFH' ? 'home' : 'map-pin' }}"></i>
                                                    {{ $request->requestType->type_name }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="date-range">
                                                    <span
                                                        class="start-date">{{ \Carbon\Carbon::parse($request->start_date)->format('d M Y') }}</span>
                                                    <span class="end-date">
                                                        <i class="feather-arrow-right"></i>
                                                        {{ \Carbon\Carbon::parse($request->end_date)->format('d M Y') }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="duration-badge">
                                                    <i class="feather-calendar"></i>
                                                    {{ $request->duration_in_days }}
                                                    {{ Str::plural('day', $request->duration_in_days) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="reason-cell">
                                                    {{ Str::limit($request->reason ?? 'No reason provided', 50) }}
                                                </div>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($request->applied_date)->format('d M Y') }}</td>
                                            {{-- <td>{{ $request->reportingHead->name ?? 'N/A' }}</td> --}}
                                            <td>
                                                @php
                                                    $statusClass =
                                                        [
                                                            'PENDING' => 'bg-warning',
                                                            'APPROVED' => 'bg-success',
                                                            'REJECTED' => 'bg-danger',
                                                            'CANCELLED' => 'bg-secondary',
                                                        ][$request->status] ?? 'bg-secondary';
                                                @endphp
                                                <span class="badge {{ $statusClass }}">
                                                    {{ ucfirst(strtolower($request->status)) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">

                                                    @if ($request->status == 'PENDING')
                                                        <a href="#" class="action-btn edit edit-request"
                                                            data-id="{{ $request->id }}"
                                                            data-request_type="{{ $request->request_type_id }}"
                                                            data-start_date="{{ \Carbon\Carbon::parse($request->start_date)->format('Y-m-d') }}"
                                                            data-end_date="{{ \Carbon\Carbon::parse($request->end_date)->format('Y-m-d') }}"
                                                            data-reason="{{ $request->reason }}" title="Edit">
                                                            <i class="feather-edit-3"></i>
                                                        </a>
                                                    @else
                                                        <span class="action-btn edit disabled">
                                                            <i class="feather-edit-3"></i>
                                                        </span>
                                                    @endif

                                                    <!-- Cancel Button (only for pending/approved requests) -->
                                                    @if (in_array($request->status, ['PENDING', 'APPROVED']))
                                                        <a href="#" class="action-btn delete cancel-request"
                                                            data-id="{{ $request->id }}" title="Delete Request">
                                                            <i class="feather-x-circle"></i>
                                                        </a>
                                                    @else
                                                        <span class="action-btn delete disabled">
                                                            <i class="feather-x-circle"></i>
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-file-text"></i>
                                                    <h4>No Requests Found</h4>
                                                    <p class="text-muted">You haven't submitted any requests yet</p>
                                                    {{-- <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                        data-bs-target="#addRequestModal">
                                                        <i class="feather-plus me-2"></i>Create Your First Request
                                                    </button> --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if ($requests->hasPages())
                        <div class="card-footer">
                            {{ $requests->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Request Modal -->
    <div class="modal fade-scale" id="addRequestModal" tabindex="-1" aria-labelledby="addRequestModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">New Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('requests.store') }}" method="POST" id="addRequestForm"
                                enctype="multipart/form-data">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="request_type_id">Request Type *</label>
                                            <select class="form-control" name="request_type_id" required
                                                id="request_type_id">
                                                <option value="" disabled selected>-- Select Request Type --</option>
                                                @foreach ($requestTypes as $type)
                                                    <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text request_type_id_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" required
                                                id="start_date" min="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text start_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="end_date">End Date *</label>
                                            <input type="date" class="form-control" name="end_date" required
                                                id="end_date" min="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text end_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="reason">Reason *</label>
                                            <textarea class="form-control" name="reason" id="reason" rows="3"
                                                placeholder="Enter reason for your request..." required></textarea>
                                            <small class="text-danger error-text reason_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="attachments">Attachments (Optional)</label>
                                            <input type="file" class="form-control" name="attachments"
                                                id="attachments" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            <small class="text-muted">You can upload files (Max: 5MB each)</small>
                                            <small class="text-danger error-text attachments_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Submit Request</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-danger text-warning float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Request Modal -->
    <div class="modal fade-scale" id="editRequestModal" tabindex="-1" aria-labelledby="editRequestModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editRequestForm" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>

                                <input type="hidden" name="id" id="edit_id">

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_request_type_id">Request Type *</label>
                                            <select class="form-control" name="request_type_id" required
                                                id="edit_request_type_id">
                                                <option value="" disabled selected>-- Select Request Type --</option>
                                                @foreach ($requestTypes as $type)
                                                    <option value="{{ $type->id }}">{{ $type->type_name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_request_type_id_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_start_date">Start Date *</label>
                                            <input type="date" class="form-control" name="start_date" required
                                                id="edit_start_date" min="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text edit_start_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_end_date">End Date *</label>
                                            <input type="date" class="form-control" name="end_date" required
                                                id="edit_end_date" min="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text edit_end_date_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_reason">Reason *</label>
                                            <textarea class="form-control" name="reason" id="edit_reason" rows="3"
                                                placeholder="Enter reason for your request..." required></textarea>
                                            <small class="text-danger error-text edit_reason_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_attachments">Add More Attachments
                                                (Optional)</label>
                                            <input type="file" class="form-control" name="attachments"
                                                id="edit_attachments" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            <small class="text-muted">You can upload files (Max: 5MB each)</small>
                                            <small class="text-danger error-text edit_attachments_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Update Request</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-danger text-warning float-end"
                                            data-bs-dismiss="modal">
                                            Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Request Modal -->
    <div class="modal fade" id="cancelRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="feather-alert-triangle text-warning" style="font-size: 48px;"></i>
                    <p class="mt-3">Are you sure you want to delete this request?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="confirmCancel">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Date validation
            $('#start_date, #edit_start_date').on('change', function() {
                let startDate = $(this).val();
                let endDateField = $(this).attr('id') === 'start_date' ? '#end_date' : '#edit_end_date';
                $(endDateField).attr('min', startDate);

                // If end date is before start date, reset it
                let endDate = $(endDateField).val();
                if (endDate && startDate > endDate) {
                    $(endDateField).val('');
                }
            });

            $('#end_date, #edit_end_date').on('change', function() {
                let endDate = $(this).val();
                let startDateField = $(this).attr('id') === 'end_date' ? '#start_date' : '#edit_start_date';
                let startDate = $(startDateField).val();

                if (startDate && endDate < startDate) {
                    toastr.error('End date cannot be before start date');
                    $(this).val('');
                }
            });

            // Add Request Form Submission
            $('#addRequestForm').on('submit', function(e) {
                e.preventDefault();
                $('.error-text').text('');
                $('#addFormError').addClass('d-none').text('');

                let formData = new FormData(this);

                $.ajax({
                    url: $(this).attr('action'),
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#addRequestModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        } else {
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message ||
                                    'Something went wrong. Please try again.');
                        }
                    }
                });
            });

            // Edit Request - Open Modal with Data
            // Edit Request - Open Modal with Data
            $(document).on('click', '.edit-request', function(e) {
                e.preventDefault();

                // Get the raw date values from data attributes (should already be Y-m-d)
                let startDate = $(this).data('start_date');
                let endDate = $(this).data('end_date');

                // Debug - remove after testing
                console.log('Start Date from data:', startDate);
                console.log('End Date from data:', endDate);

                $('#edit_id').val($(this).data('id'));
                $('#edit_request_type_id').val($(this).data('request_type'));
                $('#edit_start_date').val(startDate);
                $('#edit_end_date').val(endDate);
                $('#edit_reason').val($(this).data('reason'));

                // Set min date for end date
                $('#edit_end_date').attr('min', startDate);

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                $('#editRequestModal').modal('show');
            });
            // Edit Request Form Submission
            $('#editRequestForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                let formData = new FormData(this);
                formData.append('_method', 'POST');

                $.ajax({
                    url: "{{ route('requests.update', '') }}/" + id,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#editRequestModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                        } else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message ||
                                    'Something went wrong. Please try again.');
                        }
                    }
                });
            });

            // Cancel Request
            let cancelId = null;
            $(document).on('click', '.cancel-request', function(e) {
                e.preventDefault();
                cancelId = $(this).data('id');
                $('#cancelRequestModal').modal('show');
            });

            $('#confirmCancel').on('click', function() {
                if (!cancelId) return;
                let url = "{{ route('requests.destroy', ':id') }}".replace(':id', cancelId);
                $.ajax({
                    url: url,
                    type: "POST",
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#cancelRequestModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function() {
                        toastr.error('Something went wrong. Please try again.');
                    }
                });
            });

            // Clear form when modal is closed
            $('#addRequestModal, #editRequestModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('.error-text').text('');
                $('#addFormError, #editFormError').addClass('d-none').text('');
            });
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('requestsTable');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length - 1; i++) { // Exclude Actions column
                let headerText = headerCells[i].innerText.trim();
                if (headerText) {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length - 1; j++) { // Exclude Actions column
                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                    rowData.push('"' + cellText + '"');
                }

                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            }

            let csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'my_requests_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection
