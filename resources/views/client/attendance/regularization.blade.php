@extends('client.layout.master')

@section('style')
<style>
    /* .stats-grid/.stats-card/.stats-icon-wrapper/.stats-content/.stats-amount-main/.stats-label
       are centralized in client.layout.head (single blue-only theme) — no local copy. */

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
        color: #1e3a8a;
        font-size: 16px;
    }

    .filter-title span {
        background: #e3edfe;
        color: #1e3a8a;
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
        border-color: #1e3a8a;
        outline: none;
        background-color: white;
    }

    .apply-btn {
        height: 36px;
        padding: 0 16px;
        background: #1e3a8a;
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
        background: #16295e;
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
        color: #1e3a8a;
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
        background: #e3edfe;
        border-color: #1e3a8a;
        color: #1e3a8a;
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
        background: #e3edfe !important;
        color: #1e3a8a;
    }

    /* Request Type Badges - single blue theme */
    .badge-type-in {
        background: #e3edfe !important;
        color: #1e3a8a;
    }

    .badge-type-out {
        background: #dbeafe !important;
        color: #2563eb;
    }

    .badge-type-both {
        background: #bfd3f7 !important;
        color: #1e3a8a;
    }

    .badge-type-fullday {
        background: #93c5fd !important;
        color: #1e3a8a;
    }

    .badge-type-wfh {
        background: #eef3fd !important;
        color: #2563eb;
        border: 1px solid #bfd3f7;
    }

    .badge-type-tech {
        background: #e2e8f0 !important;
        color: #475569;
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
        color: #1e3a8a;
        border-color: #1e3a8a;
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
        color: #1e3a8a;
        border-color: #1e3a8a;
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
        color: #1e3a8a;
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
        border-left: 2px solid #1e3a8a;
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

    /* ==================== MODAL HEADER / BUTTONS - small font, blue theme ==================== */
    #addRegularizationModal .modal-header,
    #editRegularizationModal .modal-header,
    #viewRegularizationModal .modal-header {
        background: #fff !important;
        border-bottom: 1px solid #edf2f7 !important;
        padding: 10px 16px !important;
    }

    #addRegularizationModal .modal-header .fs-18,
    #editRegularizationModal .modal-header .fs-18,
    #viewRegularizationModal .modal-header .fs-18 {
        font-size: 13px !important;
        color: #1e293b !important;
    }

    #addRegularizationModal .modal-header .close-icon,
    #editRegularizationModal .modal-header .close-icon,
    #viewRegularizationModal .modal-header .close-icon {
        width: 26px;
        height: 26px;
    }

    #addRegularizationModal .form-group label,
    #editRegularizationModal .form-group label {
        font-size: 11px !important;
    }

    #addRegularizationModal .form-control,
    #editRegularizationModal .form-control {
        font-size: 11.5px !important;
        padding: 6px 10px !important;
    }

    #addRegularizationModal .btn,
    #editRegularizationModal .btn {
        font-size: 11.5px !important;
        padding: 6px 14px !important;
    }

    #addRegularizationModal .btn-primary,
    #editRegularizationModal .btn-primary {
        background: #1e3a8a !important;
        border-color: #1e3a8a !important;
    }

    #addRegularizationModal .btn-primary:hover,
    #editRegularizationModal .btn-primary:hover {
        background: #16295e !important;
        border-color: #16295e !important;
    }

    #addRegularizationModal .btn-modal-cancel,
    #editRegularizationModal .btn-modal-cancel {
        background: #eef3fd !important;
        border-color: #bfd3f7 !important;
        color: #1e3a8a !important;
    }

    #addRegularizationModal .btn-modal-cancel:hover,
    #editRegularizationModal .btn-modal-cancel:hover {
        background: #dbeafe !important;
        color: #1e3a8a !important;
    }
</style>
@endsection

@section('content-area')
    <x-ui.page-header title="Attendance Regularizations">
        <x-slot:actions>
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
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
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
                            <option value="in_time" {{ request('request_type') == 'in_time' ? 'selected' : '' }}>In Time Only</option>
                            <option value="out_time" {{ request('request_type') == 'out_time' ? 'selected' : '' }}>Out Time Only</option>
                            <option value="both" {{ request('request_type') == 'both' ? 'selected' : '' }}>Both In & Out</option>
                            <option value="full_day" {{ request('request_type') == 'full_day' ? 'selected' : '' }}>Full Day Missed Punch</option>
                            <option value="wfh_not_marked" {{ request('request_type') == 'wfh_not_marked' ? 'selected' : '' }}>WFH Not Marked</option>
                            <option value="technical_issue" {{ request('request_type') == 'technical_issue' ? 'selected' : '' }}>System/Technical Issue</option>
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
                                                        'full_day' => 'badge-type-fullday',
                                                        'wfh_not_marked' => 'badge-type-wfh',
                                                        'technical_issue' => 'badge-type-tech',
                                                        default => 'badge-type-in'
                                                    };
                                                    $typeText = match($regularization->request_type) {
                                                        'in_time' => 'In Time Only',
                                                        'out_time' => 'Out Time Only',
                                                        'both' => 'Both In & Out',
                                                        'full_day' => 'Full Day Missed Punch',
                                                        'wfh_not_marked' => 'WFH Not Marked',
                                                        'technical_issue' => 'System/Technical Issue',
                                                        default => ucfirst(str_replace('_', ' ', $regularization->request_type))
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
                                                <x-ui.status-badge :status="$regularization->status" />
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
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
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
                                                <option value="full_day">Full Day Missed Punch</option>
                                                <option value="wfh_not_marked">WFH Not Marked</option>
                                                <option value="technical_issue">System/Technical Issue</option>
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
                                    <div class="col-12 mb-3" id="in_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="in_time">In Time</label>
                                            <input type="time" class="form-control" name="in_time" id="in_time">
                                            <small class="text-danger error-text in_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3" id="out_time_container">
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
                                        <a href="#" class="btn btn-modal-cancel float-end"
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
        <div class="modal-dialog modal-dialog-centered modal-sm compact-modal" role="document">
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
                                                <option value="full_day">Full Day Missed Punch</option>
                                                <option value="wfh_not_marked">WFH Not Marked</option>
                                                <option value="technical_issue">System/Technical Issue</option>
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
                                    <div class="col-12 mb-3" id="edit_in_time_container">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_in_time">In Time</label>
                                            <input type="time" class="form-control" name="in_time" id="edit_in_time">
                                            <small class="text-danger error-text edit_in_time_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3" id="edit_out_time_container">
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
                                        <a href="#" class="btn btn-modal-cancel float-end"
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