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

    .rejected-card::before {
        background: linear-gradient(90deg, #ef4444, #f87171);
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

    .rejected-card .stats-icon-wrapper {
        background: rgba(239, 68, 68, 0.1);
    }

    .rejected-card .stats-icon-wrapper i {
        color: #ef4444;
        font-size: 24px;
    }

    /* Content Styles */
    .stats-content {
        flex: 1;
        min-width: 0;
    }

    .stats-amount-main {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.3;
        margin-bottom: 4px;
    }

    .stats-label {
        font-size: 11px;
        font-weight: 600;
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
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
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

    .clear-all-link i {
        font-size: 14px;
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

    .search-wrapper {
        position: relative;
        width: 100%;
    }

    .search-wrapper i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
        pointer-events: none;
    }

    .search-wrapper .form-control {
        width: 100%;
        height: 36px;
        padding: 6px 12px 6px 32px;
        font-size: 13px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        transition: all 0.2s;
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

    .filter-select:focus {
        border-color: #4f46e5;
        outline: none;
        background-color: white;
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

    .apply-btn:hover {
        background: #4338ca;
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

    .reset-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
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

    .badge.bg-danger {
        background: #fee2e2 !important;
        color: #991b1b;
    }

    .badge.bg-warning {
        background: #fef3c7 !important;
        color: #92400e;
    }

    .badge.bg-info {
        background: #dbeafe !important;
        color: #1e40af;
    }

    .badge.bg-purple {
        background: #ede9fe !important;
        color: #5b21b6;
    }

    /* Request Type Badges */
    .badge-type-in {
        background: #dbeafe !important;
        color: #1e40af;
    }

    .badge-type-out {
        background: #fef3c7 !important;
        color: #92400e;
    }

    .badge-type-both {
        background: #e0e7ff !important;
        color: #4f46e5;
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
        color: #4f46e5;
        border-color: #4f46e5;
        transform: translateY(-2px);
    }

    .action-btn i {
        font-size: 14px;
    }

    .action-btn.delete:hover {
        color: #ef4444;
        border-color: #ef4444;
    }

    .action-btn.info:hover {
        color: #4f46e5;
        border-color: #4f46e5;
    }

    .action-btn.approve {
        color: #10b981;
    }

    .action-btn.approve:hover {
        color: #059669;
        border-color: #059669;
    }

    .action-btn.reject {
        color: #ef4444;
    }

    .action-btn.reject:hover {
        color: #dc2626;
        border-color: #dc2626;
    }

    /* ==================== FILE PREVIEW ==================== */
    .file-preview {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .file-preview a {
        color: #4f46e5;
        text-decoration: none;
        font-size: 11px;
        display: flex;
        align-items: center;
        gap: 2px;
    }

    .file-preview a:hover {
        text-decoration: underline;
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

    /* ==================== APPROVAL INFO ==================== */
    .approval-info {
        background: #f8fafc;
        border-radius: 8px;
        padding: 8px 12px;
        margin-top: 8px;
        border-left: 2px solid #4f46e5;
        font-size: 12px;
    }

    .approval-info p {
        margin-bottom: 4px;
    }

    .approval-info .label {
        font-weight: 600;
        color: #475569;
        width: 70px;
        display: inline-block;
    }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 992px) {
        .filter-row {
            gap: 8px;
        }

        .filter-item {
            flex: 1 1 calc(33.333% - 8px);
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
                <h5 class="m-b-10">Attendance Regularizations</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Attendance Regularizations</li>
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
                        data-bs-target="#addRegularizationModal">
                        <i class="feather-plus me-2"></i>
                        <span>Add Regularization</span>
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
            <!-- Total Regularizations Card -->
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-clock"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $totalRegularizations ?? 0 }}</div>
                    <div class="stats-label">Total Requests</div>
                    <!--<div class="stats-count">-->
                    <!--    <span class="count-text">All time</span>-->
                    <!--</div>-->
                </div>
            </div>

            <!-- Pending Regularizations Card -->
            <div class="stats-card pending-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-hourglass"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $pendingRegularizations ?? 0 }}</div>
                    <div class="stats-label">Pending</div>
                    <!--<div class="stats-count">-->
                    <!--    <span class="count-number">{{ $pendingRegularizations ?? 0 }}</span>-->
                    <!--    <span class="count-text">awaiting approval</span>-->
                    <!--</div>-->
                </div>
            </div>

            <!-- Approved Regularizations Card -->
            <div class="stats-card approved-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $approvedRegularizations ?? 0 }}</div>
                    <div class="stats-label">Approved</div>
                    <!--<div class="stats-count">-->
                    <!--    <span class="count-number">{{ $approvedRegularizations ?? 0 }}</span>-->
                    <!--    <span class="count-text">requests</span>-->
                    <!--</div>-->
                </div>
            </div>

            <!-- Rejected Regularizations Card -->
            <div class="stats-card rejected-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $rejectedRegularizations ?? 0 }}</div>
                    <div class="stats-label">Rejected</div>
                    <!--<div class="stats-count">-->
                    <!--    <span class="count-number">{{ $rejectedRegularizations ?? 0 }}</span>-->
                    <!--    <span class="count-text">requests</span>-->
                    <!--</div>-->
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Regularization Requests
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'from_date', 'to_date', 'request_type'])
                        )->filter()->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'from_date', 'to_date', 'request_type']))
                    <a href="{{ route('attendance-regularization.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('attendance-regularization.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <!-- Request Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="request_type">
                            <option value="">All Types</option>
                            <option value="in_time" {{ request('request_type') == 'in_time' ? 'selected' : '' }}>In Time</option>
                            <option value="out_time" {{ request('request_type') == 'out_time' ? 'selected' : '' }}>Out Time</option>
                            <option value="both" {{ request('request_type') == 'both' ? 'selected' : '' }}>Both</option>
                        </select>
                    </div>

                    <!-- From Date -->
                    <div class="filter-item">
                        <input type="date" class="form-control" name="from_date" style="padding: .375rem .75rem !important;" 
                               value="{{ request('from_date') }}" placeholder="From Date">
                    </div>

                    <!-- To Date -->
                    <div class="filter-item">
                        <input type="date" class="form-control" name="to_date" style="padding: .375rem .75rem !important;" 
                               value="{{ request('to_date') }}" placeholder="To Date">
                    </div>

                    <!-- Action Buttons -->
                    <div class="filter-item" style="min-width: auto;">
                        <!--<button type="submit" class="apply-btn">-->
                        <!--    <i class="feather-search"></i>-->
                        <!--    Apply-->
                        <!--</button>-->
                    </div>

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('attendance-regularization.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'from_date', 'to_date', 'request_type']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('attendance-regularization.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('request_type'))
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Type: {{ ucfirst(str_replace('_', ' ', request('request_type'))) }}
                            <a href="{{ route('attendance-regularization.index', array_merge(request()->except(['request_type', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ request('from_date') }}
                            <a href="{{ route('attendance-regularization.index', array_merge(request()->except(['from_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ request('to_date') }}
                            <a href="{{ route('attendance-regularization.index', array_merge(request()->except(['to_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('attendance-regularization.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Regularizations Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">My Regularization Requests</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $regularizations->count() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="regularizationList">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Date</th>
                                        <th>Request Type</th>
                                        <th>In Time</th>
                                        <th>Out Time</th>
                                        <th>Reason</th>
                                        <th>Attachment</th>
                                        <th>Status</th>
                                        <th>Approval Info</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($regularizations as $regularization)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ date('d M Y', strtotime($regularization->date)) }}</td>
                                            <td>
                                                @php
                                                    $typeClass = match($regularization->request_type) {
                                                        'in_time' => 'badge-type-in',
                                                        'out_time' => 'badge-type-out',
                                                        'both' => 'badge-type-both',
                                                        default => 'bg-secondary'
                                                    };
                                                    $typeText = match($regularization->request_type) {
                                                        'in_time' => 'In Time Only',
                                                        'out_time' => 'Out Time Only',
                                                        'both' => 'Both In & Out',
                                                        default => ucfirst($regularization->request_type)
                                                    };
                                                @endphp
                                                <span class="badge {{ $typeClass }}">
                                                    {{ $typeText }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($regularization->in_time)
                                                    <span class="fw-medium">{{ date('h:i A', strtotime($regularization->in_time)) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($regularization->out_time)
                                                    <span class="fw-medium">{{ date('h:i A', strtotime($regularization->out_time)) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td style="max-width: 200px;">
                                                <div style="white-space: normal; word-wrap: break-word;">
                                                    {{ Str::limit($regularization->reason ?? 'No reason provided', 50) }}
                                                </div>
                                            </td>
                                            <td>
                                                @if (!empty($regularization->file))
                                                    @php
                                                        $filePath = $regularization->file;
                                                        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                    @endphp

                                                    @if (in_array($extension, $imageExtensions))
                                                        <a href="{{ asset($filePath) }}" target="_blank" class="file-preview">
                                                            <img src="{{ asset($filePath) }}" alt="Attachment"
                                                                style="width:30px; height:30px; border-radius:4px; object-fit: cover;">
                                                        </a>
                                                    @else
                                                        <a href="{{ asset($filePath) }}" class="btn btn-sm btn-light" download>
                                                            <i class="fa fa-download"></i>
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = [
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                        'pending' => 'bg-warning',
                                                    ][$regularization->status] ?? 'bg-secondary';
                                                @endphp
                                                <span class="badge {{ $statusClass }}">
                                                    {{ ucfirst($regularization->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($regularization->status == 'approved' && $regularization->approved_by)
                                                    <div class="approval-info">
                                                        <p><span class="label">By:</span> {{ $regularization->approver->name ?? 'N/A' }}</p>
                                                        <p><span class="label">On:</span> {{ date('d M Y', strtotime($regularization->approved_date)) }}</p>
                                                    </div>
                                                @elseif($regularization->status == 'rejected' && $regularization->approved_by)
                                                    <div class="approval-info" style="border-left-color: #ef4444;">
                                                        <p><span class="label">By:</span> {{ $regularization->approver->name ?? 'N/A' }}</p>
                                                        <p><span class="label">On:</span> {{ date('d M Y', strtotime($regularization->approved_date)) }}</p>
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($regularization->status == 'pending')
                                                    <div class="dropdown">
                                                        <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                            data-bs-offset="0,5">
                                                            <i class="feather-more-vertical"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item edit-regularization" href="#"
                                                                    data-id="{{ $regularization->id }}"
                                                                    data-request_type="{{ $regularization->request_type }}"
                                                                    data-date="{{ $regularization->date }}"
                                                                    data-in_time="{{ $regularization->in_time }}"
                                                                    data-out_time="{{ $regularization->out_time }}"
                                                                    data-reason="{{ $regularization->reason }}">
                                                                    <i class="feather-edit-3"></i>
                                                                    <span>Edit</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item delete-regularization" href="#"
                                                                    data-id="{{ $regularization->id }}">
                                                                    <i class="feather-trash-2"></i>
                                                                    <span>Delete</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                @else
                                                    <button class="action-btn info" onclick="toggleDetails({{ $regularization->id }})" 
                                                            title="Toggle Details">
                                                        <i class="feather-chevron-down" id="toggle-icon-{{ $regularization->id }}"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-clock"></i>
                                                    <h4>No Regularization Requests Found</h4>
                                                    <p class="text-muted">You haven't submitted any attendance regularization requests yet</p>
                                                  
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Add Regularization Modal -->
    <div class="modal fade-scale" id="addRegularizationModal" tabindex="-1" aria-labelledby="addRegularizationModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Attendance Regularization Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('attendance-regularization.store') }}" id="addRegularizationForm"
                                enctype="multipart/form-data" method="POST">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="request_type">Request Type *</label>
                                            <select class="form-control" name="request_type" required id="request_type">
                                                <option value="" disabled selected>-- Select Request Type --</option>
                                                <option value="in_time">In Time Only</option>
                                                <option value="out_time">Out Time Only</option>
                                                <option value="both">Both In & Out Time</option>
                                            </select>
                                            <small class="text-danger error-text request_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="date">Date *</label>
                                            <input type="date" class="form-control" name="date" required
                                                id="date" max="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3" id="in_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="in_time">In Time</label>
                                            <input type="time" class="form-control" name="in_time" id="in_time">
                                            <small class="text-danger error-text in_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3" id="out_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="out_time">Out Time</label>
                                            <input type="time" class="form-control" name="out_time" id="out_time">
                                            <small class="text-danger error-text out_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="file">Attachment</label>
                                            <input type="file" class="form-control" name="file" id="file"
                                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            <small class="text-danger error-text file_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="reason">Reason for Regularization *</label>
                                            <textarea class="form-control" name="reason" id="reason" rows="4" required
                                                placeholder="Please provide detailed reason for attendance regularization..."></textarea>
                                            <small class="text-danger error-text reason_error"></small>
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

    <!-- Edit Regularization Modal -->
    <div class="modal fade-scale" id="editRegularizationModal" tabindex="-1" aria-labelledby="editRegularizationModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Regularization Request</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editRegularizationForm" enctype="multipart/form-data">
                                @csrf
                              
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_request_type">Request Type *</label>
                                            <select class="form-control" name="request_type" required id="edit_request_type">
                                                <option value="" disabled selected>-- Select Request Type --</option>
                                                <option value="in_time">In Time Only</option>
                                                <option value="out_time">Out Time Only</option>
                                                <option value="both">Both In & Out Time</option>
                                            </select>
                                            <small class="text-danger error-text edit_request_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_date">Date *</label>
                                            <input type="date" class="form-control" name="date" required
                                                id="edit_date" max="{{ date('Y-m-d') }}">
                                            <small class="text-danger error-text edit_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3" id="edit_in_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_in_time">In Time</label>
                                            <input type="time" class="form-control" name="in_time" id="edit_in_time">
                                            <small class="text-danger error-text edit_in_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3" id="edit_out_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_out_time">Out Time</label>
                                            <input type="time" class="form-control" name="out_time" id="edit_out_time">
                                            <small class="text-danger error-text edit_out_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_file">Attachment</label>
                                            <input type="file" class="form-control" name="file" id="edit_file"
                                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            <small class="text-danger error-text edit_file_error"></small>
                                            <div id="currentFile" class="mt-2"></div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_reason">Reason for Regularization *</label>
                                            <textarea class="form-control" name="reason" id="edit_reason" rows="4" required
                                                placeholder="Please provide detailed reason for attendance regularization..."></textarea>
                                            <small class="text-danger error-text edit_reason_error"></small>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="feather-alert-triangle text-danger" style="font-size: 48px;"></i>
                    <p class="mt-3">Are you sure you want to delete this regularization request?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
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

            // Date inputs validation
            $('input[name="from_date"], input[name="to_date"]').on('change', function() {
                let fromDate = $('input[name="from_date"]').val();
                let toDate = $('input[name="to_date"]').val();

                if (fromDate && toDate && fromDate > toDate) {
                    toastr.error('From date cannot be greater than To date');
                    $(this).val('');
                }
            });

            // Toggle time fields based on request type
            function toggleTimeFields(requestType, prefix = '') {
                let inContainer = prefix ? $(`#${prefix}_in_time_container`) : $('#in_time_container');
                let outContainer = prefix ? $(`#${prefix}_out_time_container`) : $('#out_time_container');
                let inField = prefix ? $(`#${prefix}_in_time`) : $('#in_time');
                let outField = prefix ? $(`#${prefix}_out_time`) : $('#out_time');

                switch(requestType) {
                    case 'in_time':
                        inContainer.show();
                        outContainer.hide();
                        inField.prop('required', true);
                        outField.prop('required', false).val('');
                        break;
                    case 'out_time':
                        inContainer.hide();
                        outContainer.show();
                        inField.prop('required', false).val('');
                        outField.prop('required', true);
                        break;
                    case 'both':
                        inContainer.show();
                        outContainer.show();
                        inField.prop('required', true);
                        outField.prop('required', true);
                        break;
                    default:
                        inContainer.hide();
                        outContainer.hide();
                        inField.prop('required', false).val('');
                        outField.prop('required', false).val('');
                }
            }

            // Add modal - request type change
            $('#request_type').on('change', function() {
                toggleTimeFields($(this).val());
            });

            // Edit modal - request type change
            $('#edit_request_type').on('change', function() {
                toggleTimeFields($(this).val(), 'edit');
            });

            // Add Regularization Form Submission
            $('#addRegularizationForm').on('submit', function(e) {
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
                            $('#addRegularizationModal').modal('hide');
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
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Edit Regularization - Open Modal with Data
            $(document).on('click', '.edit-regularization', function(e) {
                e.preventDefault();

                let id = $(this).data('id');
                let requestType = $(this).data('request_type');
                
                $('#edit_id').val(id);
                $('#edit_request_type').val(requestType);
                $('#edit_date').val($(this).data('date'));
                $('#edit_in_time').val($(this).data('in_time'));
                $('#edit_out_time').val($(this).data('out_time'));
                $('#edit_reason').val($(this).data('reason'));

                toggleTimeFields(requestType, 'edit');

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#currentFile').html('');

                $('#editRegularizationModal').modal('show');
            });

            // Edit Regularization Form Submission
            $('#editRegularizationForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                let formData = new FormData(this);
                formData.append('_method', 'POST');

                $.ajax({
                    url: "{{ route('attendance-regularization.update', '') }}/" + id,
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
                            $('#editRegularizationModal').modal('hide');
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
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // Delete Regularization
            let deleteId = null;
            $(document).on('click', '.delete-regularization', function(e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                $('#deleteModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                if (!deleteId) return;

                $.ajax({
                    url: "{{ route('attendance-regularization.destroy', '') }}/" + deleteId,
                    type: "DELETE",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#deleteModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function() {
                        toastr.error('Something went wrong');
                    }
                });
            });

            // Clear form when modal is closed
            $('#addRegularizationModal, #editRegularizationModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('.error-text').text('');
                $('#addFormError, #editFormError').addClass('d-none').text('');
                $('#currentFile').html('');
                
                // Hide time containers
                $('#in_time_container, #out_time_container, #edit_in_time_container, #edit_out_time_container').hide();
            });
        });

        // Function to toggle details with chevron icon
        function toggleDetails(id) {
            // You can implement this to show/hide additional details if needed
            let icon = document.getElementById('toggle-icon-' + id);
            
            if (icon.className.includes('chevron-down')) {
                icon.className = 'feather-chevron-up';
                // Show additional details logic here
            } else {
                icon.className = 'feather-chevron-down';
                // Hide additional details logic here
            }
        }

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('regularizationList');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length - 1; i++) { // Skip Actions column
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

                for (let j = 0; j < cols.length - 1; j++) { // Skip Actions column
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
            downloadLink.download = 'attendance_regularizations_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection