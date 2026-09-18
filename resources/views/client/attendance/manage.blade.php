@extends('client.layout.master')

@section('style')
<style>

/* Simple consistent styling */
        .custom-employee-dropdown .btn {
            height: 36px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 13px;
            padding: 0 12px;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.12);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: #1e3a8a;
            /* Single consistent color */
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .employee-initials-sm {
            width: 24px;
            height: 24px;
            font-size: 11px;
        }

        /* Dropdown menu styling */
        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 8px;
            max-height: 300px;
            overflow-y: auto;

            min-width: 250px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #e3edfe;
            color: #1e3a8a;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #1e3a8a !important;
            opacity: 0.8;
        }

        /* Employee name styling */
        .employee-name {
            color: #1e293b;
            font-weight: 500;
        }

        .text-muted {
            color: #64748b !important;
        }
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

    .employee-search {
        flex: 1;
        min-width: 200px;
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

    .search-wrapper .form-control:focus {
        border-color: #1e3a8a;
        outline: none;
        background: white;
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

    .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .employee-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #e3edfe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1e3a8a;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
    }

    .employee-details {
        line-height: 1.3;
    }

    .employee-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 12px;
    }

    .employee-email {
        font-size: 11px;
        color: #64748b;
    }

    .reporting-head-badge {
        font-size: 10px;
        background: #e0e7ff;
        color: #1e3a8a;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: 5px;
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
        transform: translateY(-2px);
    }

    .action-btn.approve {
        color: #10b981;
    }

    .action-btn.approve:hover {
        color: #059669;
        border-color: #059669;
        background: #f0fdf4;
    }

    .action-btn.reject {
        color: #ef4444;
    }

    .action-btn.reject:hover {
        color: #dc2626;
        border-color: #dc2626;
        background: #fef2f2;
    }

    .action-btn.view:hover {
        color: #1e3a8a;
        border-color: #1e3a8a;
        background: #e3edfe;
    }

    .action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
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

    .file-thumbnail {
        width: 30px;
        height: 30px;
        border-radius: 4px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
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

    /* ==================== MODAL STYLES ==================== */
    .detail-row {
        display: flex;
        margin-bottom: 12px;
        padding: 8px 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        width: 120px;
        font-weight: 600;
        color: #475569;
    }

    .detail-value {
        flex: 1;
        color: #1e293b;
    }

    .status-select {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 11.5px;
        margin-bottom: 15px;
        transition: all 0.2s;
    }

    .status-select:focus {
        border-color: #1e3a8a;
        outline: none;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.12);
    }

    .status-select.approve-selected {
        border-color: #10b981;
        background-color: #f0fdf4;
    }

    .status-select.reject-selected {
        border-color: #ef4444;
        background-color: #fef2f2;
    }

    .remarks-input {
        margin-top: 15px;
    }

    .remarks-input textarea {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 11.5px;
        resize: vertical;
        transition: all 0.2s;
    }

    .remarks-input textarea:focus {
        border-color: #1e3a8a;
        outline: none;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.12);
    }

    #viewModal .modal-header {
        padding: 10px 16px;
        border-bottom: none;
        background: #1e3a8a;
    }

    #viewModal .modal-title {
        font-size: 13px !important;
        color: #fff;
        font-weight: 600;
    }

    #viewModal .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.85;
        font-size: 11px;
    }

    /* Process Regularization Request modal - matches the Regularization request modal exactly */
    #approvalModal .modal-header {
        background: #fff !important;
        padding: 10px 16px !important;
        border-bottom: 1px solid #edf2f7 !important;
    }

    #approvalModal .modal-header .fs-18 {
        font-size: 13px !important;
        color: #1e293b !important;
    }

    #approvalModal .modal-body {
        padding: 1.5rem !important;
    }

    #approvalModal .modal-footer {
        padding: 12px 16px;
        border-top: 1px solid #edf2f7;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #edf2f7;
    }

    #approvalModal .btn,
    #viewModal .btn {
        font-size: 11.5px !important;
        padding: 6px 14px !important;
    }

    #approvalModal .btn-primary {
        background: #1e3a8a !important;
        border-color: #1e3a8a !important;
    }

    #approvalModal .btn-primary:hover {
        background: #16295e !important;
        border-color: #16295e !important;
    }

    #approvalModal .btn-modal-cancel {
        background: #eef3fd !important;
        border-color: #bfd3f7 !important;
        color: #1e3a8a !important;
    }

    #approvalModal .btn-modal-cancel:hover {
        background: #dbeafe !important;
        color: #1e3a8a !important;
    }

    .employee-detail-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
    }

    .employee-detail-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .employee-detail-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #e3edfe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1e3a8a;
        font-weight: 600;
        font-size: 18px;
    }

    .employee-detail-info h6 {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .employee-detail-info p {
        color: #64748b;
        font-size: 12px;
        margin-bottom: 0;
    }

    /* ==================== PAGINATION ==================== */
    .pagination {
        margin-bottom: 0;
        gap: 5px;
    }

    .page-link {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
        font-size: 13px;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s;
    }

    .page-link:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .page-item.active .page-link {
        background: #1e3a8a;
        border-color: #1e3a8a;
        color: white;
    }

    .page-item.disabled .page-link {
        background: #f8fafc;
        color: #94a3b8;
        pointer-events: none;
    }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 992px) {
        .filter-row {
            gap: 8px;
        }
        .filter-item, .employee-search {
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
        }
        .filter-item, .employee-search {
            width: 100%;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .table th, .table td {
            white-space: nowrap;
        }
        .employee-info {
            min-width: 150px;
        }
    }
</style>
@endsection

@section('content-area')
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Team Regularization Management</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Manage Regularizations</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="dropdown">
                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10">
                    <i class="feather-download"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="#" class="dropdown-item" onclick="exportToCSV()">
                        <i class="bi bi-filetype-csv me-3"></i>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="main-content" style="padding: 20px !important;">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stats-card total-card">
            <div class="stats-icon-wrapper">
                <i class="feather-clock"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $totalRequests ?? 0 }}</div>
                <div class="stats-label">Total Requests</div>
            </div>
        </div>

        <div class="stats-card pending-card">
            <div class="stats-icon-wrapper">
                <i class="feather-hourglass"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $pendingRequests ?? 0 }}</div>
                <div class="stats-label">Pending</div>
                <!--<div class="stats-count">-->
                <!--    <span class="count-number">{{ $pendingRequests ?? 0 }}</span>-->
                <!--    <span class="count-text">awaiting action</span>-->
                <!--</div>-->
            </div>
        </div>

        <div class="stats-card approved-card">
            <div class="stats-icon-wrapper">
                <i class="feather-check-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $approvedRequests ?? 0 }}</div>
                <div class="stats-label">Approved</div>
                <!--<div class="stats-count">-->
                <!--    <span class="count-number">{{ $approvedRequests ?? 0 }}</span>-->
                <!--    <span class="count-text">requests</span>-->
                <!--</div>-->
            </div>
        </div>

        <div class="stats-card rejected-card">
            <div class="stats-icon-wrapper">
                <i class="feather-x-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $rejectedRequests ?? 0 }}</div>
                <div class="stats-label">Rejected</div>
                <!--<div class="stats-count">-->
                <!--    <span class="count-number">{{ $rejectedRequests ?? 0 }}</span>-->
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
                Filter Requests
                @php
                    $activeFilterCount = collect(
                        request()->only(['status', 'from_date', 'to_date', 'request_type', 'employee'])
                    )->filter()->count();
                @endphp
                @if ($activeFilterCount > 0)
                    <span>{{ $activeFilterCount }} active</span>
                @endif
            </div>
            @if (request()->hasAny(['status', 'from_date', 'to_date', 'request_type', 'employee']))
                <a href="{{ route('attendance-regularization.manage') }}" class="clear-all-link">
                    <i class="feather-x"></i>
                    Clear All
                </a>
            @endif
        </div>

        <form action="{{ route('attendance-regularization.manage') }}" method="GET" id="filterForm">
            <div class="filter-row">
                 <div class="filter-item" style="min-width: 220px;">

                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('user_id') && ($selectedEmployee = $employees->firstWhere('id', request('user_id'))))
                                        @php
                                            $selectedInitials = strtoupper(substr($selectedEmployee->name, 0, 2));
                                        @endphp
                                        <span class="employee-initials-sm">{{ $selectedInitials }}</span>
                                        <span class="employee-name">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>

                            <ul class="dropdown-menu w-80 p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('user_id') ? 'active' : '' }}"
                                        href="{{ route('attendance-regularization.manage', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('attendance-regularization.manage', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $employee->name }} (<small
                                                        class="text-muted">{{ $employee->employee_id }})</small></span>
                                                <small class="text-muted">{{ $employee->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                <div class="filter-item">
                    <select class="filter-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="filter-item">
                    <select class="filter-select" name="request_type">
                        <option value="">All Types</option>
                        <option value="in_time" {{ request('request_type') == 'in_time' ? 'selected' : '' }}>In Time</option>
                        <option value="out_time" {{ request('request_type') == 'out_time' ? 'selected' : '' }}>Out Time</option>
                        <option value="both" {{ request('request_type') == 'both' ? 'selected' : '' }}>Both</option>
                        <option value="full_day" {{ request('request_type') == 'full_day' ? 'selected' : '' }}>Full Day Missed Punch</option>
                        <option value="wfh_not_marked" {{ request('request_type') == 'wfh_not_marked' ? 'selected' : '' }}>WFH Not Marked</option>
                        <option value="technical_issue" {{ request('request_type') == 'technical_issue' ? 'selected' : '' }}>System/Technical Issue</option>
                    </select>
                </div>

               

                <div class="filter-item">
                    <input type="date" class="form-control" name="from_date" 
                           value="{{ request('from_date') }}" placeholder="From Date" style="padding: .375rem .75rem !important;">
                </div>

                <div class="filter-item">
                    <input type="date" class="form-control" name="to_date" 
                           value="{{ request('to_date') }}" placeholder="To Date" style="padding: .375rem .75rem !important;">
                </div>

                <div class="filter-item" style="min-width: auto;">
                    <!--<button type="submit" class="apply-btn">-->
                    <!--    <i class="feather-search"></i>-->
                    <!--    Apply-->
                    <!--</button>-->
                </div>

                <div class="filter-item" style="min-width: auto;">
                    <a href="{{ route('attendance-regularization.manage') }}" class="reset-btn">
                        <i class="feather-refresh-cw"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Active Filter Tags -->
        @if (request()->hasAny(['status', 'from_date', 'to_date', 'request_type', 'employee']))
            <div class="active-filters">
                <span class="active-filters-label">Active:</span>

                @if (request('status'))
                    <span class="filter-tag">
                        <i class="feather-activity"></i>
                        Status: {{ ucfirst(request('status')) }}
                        <a href="{{ route('attendance-regularization.manage', array_merge(request()->except(['status', 'page']))) }}"
                            class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('request_type'))
                    <span class="filter-tag">
                        <i class="feather-tag"></i>
                        Type: {{ ucfirst(str_replace('_', ' ', request('request_type'))) }}
                        <a href="{{ route('attendance-regularization.manage', array_merge(request()->except(['request_type', 'page']))) }}"
                            class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('employee'))
                    <span class="filter-tag">
                        <i class="feather-user"></i>
                        Employee: {{ request('employee') }}
                        <a href="{{ route('attendance-regularization.manage', array_merge(request()->except(['employee', 'page']))) }}"
                            class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('from_date'))
                    <span class="filter-tag">
                        <i class="feather-calendar"></i>
                        From: {{ request('from_date') }}
                        <a href="{{ route('attendance-regularization.manage', array_merge(request()->except(['from_date', 'page']))) }}"
                            class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('to_date'))
                    <span class="filter-tag">
                        <i class="feather-calendar"></i>
                        To: {{ request('to_date') }}
                        <a href="{{ route('attendance-regularization.manage', array_merge(request()->except(['to_date', 'page']))) }}"
                            class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                <a href="{{ route('attendance-regularization.manage') }}" class="filter-tag clear-all">
                    <i class="feather-refresh-cw"></i>
                    Clear All
                </a>
            </div>
        @endif
    </div>

    <!-- Regularizations Table -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">All Regularization Requests</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info">
                            <i class="feather-list me-1"></i>Total: {{ $regularizations->total() }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table" id="regularizationList">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Request Type</th>
                                    <th>In Time</th>
                                    <th>Out Time</th>
                                    {{-- <th>Reason</th> --}}
                                    <th>Attachment</th>
                                    <th>Status</th>
                                    {{-- <th>Approved By</th> --}}
                                    <th>Approval Time</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($regularizations as $regularization)
                                    <tr>
                                        <td>{{ $loop->iteration + ($regularizations->currentPage() - 1) * $regularizations->perPage() }}</td>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    {{ strtoupper(substr($regularization->user->name, 0, 2)) }}
                                                </div>
                                                
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ $regularization->user->name }} <small class="text-secondary fs-10">( {{ $regularization->user->employee_id }} )</small>
                                                       
                                                    </div>
                                                    <div class="employee-email">{{ $regularization->user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($regularization->date)->format('d M Y') }}</td>
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
                                                    'in_time' => 'In Time',
                                                    'out_time' => 'Out Time',
                                                    'both' => 'Both',
                                                    'full_day' => 'Full Day',
                                                    'wfh_not_marked' => 'WFH Not Marked',
                                                    'technical_issue' => 'Technical Issue',
                                                    default => ucfirst(str_replace('_', ' ', $regularization->request_type))
                                                };
                                            @endphp
                                            <span class="badge {{ $typeClass }}">
                                                {{ $typeText }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($regularization->in_time)
                                                {{ \Carbon\Carbon::parse($regularization->in_time)->format('h:i A') }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($regularization->out_time)
                                                {{ \Carbon\Carbon::parse($regularization->out_time)->format('h:i A') }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        {{-- <td style="max-width: 200px;">
                                            <div style="white-space: normal; word-wrap: break-word;">
                                                {{ Str::limit($regularization->reason ?? 'No reason provided', 50) }}
                                            </div>
                                        </td> --}}
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
                                                            class="file-thumbnail">
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
                                        {{-- <td>
                                            @if($regularization->approver)
                                                <span class="fw-medium">{{ $regularization->approver->name }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td> --}}
                                        <td>
                                            @if($regularization->approved_date)
                                                {{ \Carbon\Carbon::parse($regularization->approved_date)->format('d M Y, h:i A') }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($regularization->status == 'pending')
                                                <button class="action-btn approve" onclick="openApprovalModal({{ $regularization->id }})"
                                                        title="Process Request" data-bs-toggle="modal" data-bs-target="#approvalModal">
                                                    <i class="feather-check-circle"></i>
                                                </button>
                                            @endif
                                            <button class="action-btn view" onclick="viewDetails({{ $regularization->id }})"
                                                    title="View Details" data-bs-toggle="modal" data-bs-target="#viewModal">
                                                <i class="feather-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather-clock"></i>
                                                <h4>No Regularization Requests Found</h4>
                                                <p class="text-muted">There are no regularization requests matching your criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div> 
                    <!-- Pagination -->
                
                @if(method_exists($regularizations, 'links') && $regularizations->hasPages())
                 <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $regularizations->firstItem() }} to {{ $regularizations->lastItem() }} 
                            of {{ $regularizations->total() }} entries
                        </div>
                        <div>
                            {{ $regularizations->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
                @endif
               
            </div>
        </div>
    </div>
</div>
@endsection

@section('create-modal')
<!-- Single Approval Modal with Status Select -->
<x-ui.modal id="approvalModal" title="Process Regularization Request" bodyOnly>
    <x-slot:footer>
        <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="approvalSubmitBtn" form="approvalForm">
            <i class="feather-check-circle me-2"></i>Submit
        </button>
    </x-slot:footer>
    <form id="approvalForm">
        @csrf
        <input type="hidden" name="id" id="approval_id">

        <!-- Employee Details Card -->
        <div class="employee-detail-card" id="employeeDetailCard" style="display: none;">
            <div class="employee-detail-row">
                <div class="employee-detail-avatar" id="employeeAvatar"></div>
                <div class="employee-detail-info">
                    <h6 id="employeeName"></h6>
                    <p id="employeeEmail"></p>
                    <p id="requestDate"></p>
                </div>
            </div>
        </div>

        <!-- Status Selection -->
        <div class="form-group mb-3">
            <label class="fw-semibold mb-2">Action <span class="text-danger">*</span></label>
            <select class="status-select" name="status" id="approval_status" required>
                <option value="" disabled selected>-- Select Action --</option>
                <option value="approved" class="text-success">✓ Approve Request</option>
                <option value="rejected" class="text-danger">✗ Reject Request</option>
            </select>
        </div>

        <!-- Remarks -->
        <div class="remarks-input">
            <label class="fw-semibold mb-2">Remarks <small class="text-muted">(Optional)</small></label>
            <textarea name="remarks" id="approval_remarks" rows="3"
                      placeholder="Add any comments or remarks..."></textarea>
        </div>
    </form>
</x-ui.modal>

<!-- View Details Modal (keep this separate) -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details will be loaded here via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
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
        "timeOut": "3000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Open approval modal and fetch employee details
    function openApprovalModal(id) {
        $('#approval_id').val(id);
        $('#approval_status').val('');
        $('#approval_remarks').val('');
        
        // Show loading state in modal
        $('#employeeDetailCard').hide();
        $('#approvalSubmitBtn').prop('disabled', false).html('<i class="feather-check-circle me-2"></i>Submit');
        
        // Fetch employee details
        $.ajax({
            url: "{{ route('attendance-regularization.details', '') }}/" + id,
            type: "GET",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    let data = response.data;
                    
                    // Update employee details card
                    $('#employeeAvatar').text(data.user_name ? data.user_name.substring(0, 2).toUpperCase() : '--');
                    $('#employeeName').text(data.user_name || 'N/A');
                    $('#employeeEmail').text(data.user_email || '');
                    $('#requestDate').text('Request Date: ' + (data.formatted_date || data.date || 'N/A'));
                    
                    $('#employeeDetailCard').show();
                }
            },
            error: function(xhr) {
                console.error('Failed to fetch employee details');
            }
        });
    }

    // Handle status select change - update styling
    $('#approval_status').on('change', function() {
        let value = $(this).val();
        $(this).removeClass('approve-selected reject-selected');
        
        if (value === 'approved') {
            $(this).addClass('approve-selected');
        } else if (value === 'rejected') {
            $(this).addClass('reject-selected');
        }
    });

    // View details
    function viewDetails(id) {
        $('#viewModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        
        $.ajax({
            url: "{{ route('attendance-regularization.details', '') }}/" + id,
            type: "GET",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    let data = response.data;
                    let html = `
                        <div class="detail-row">
                            <span class="detail-label">Employee:</span>
                            <span class="detail-value">
                                <strong>${data.user_name || 'N/A'}</strong><br>
                                <small class="text-muted">${data.user_email || ''}</small>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">${data.formatted_date || data.date || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Request Type:</span>
                            <span class="detail-value">
                                <span class="badge ${data.request_type_badge || 'bg-secondary'}">${data.request_type_text || data.request_type}</span>
                            </span>
                        </div>
                    `;
                    
                    if (data.in_time) {
                        html += `
                        <div class="detail-row">
                            <span class="detail-label">In Time:</span>
                            <span class="detail-value">${data.in_time}</span>
                        </div>
                        `;
                    }
                    
                    if (data.out_time) {
                        html += `
                        <div class="detail-row">
                            <span class="detail-label">Out Time:</span>
                            <span class="detail-value">${data.out_time}</span>
                        </div>
                        `;
                    }
                    
                    html += `
                        <div class="detail-row">
                            <span class="detail-label">Reason:</span>
                            <span class="detail-value">${data.reason || 'No reason provided'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="badge ${data.status_badge || 'bg-secondary'}">${data.status || 'N/A'}</span>
                            </span>
                        </div>
                    `;
                    
                    if (data.file_url) {
                        html += `
                        <div class="detail-row">
                            <span class="detail-label">Attachment:</span>
                            <span class="detail-value">
                                <a href="${data.file_url}" target="_blank" class="btn btn-sm btn-light">
                                    <i class="fa fa-download me-1"></i> Download
                                </a>
                            </span>
                        </div>
                        `;
                    }
                    
                    if (data.approver_name) {
                        html += `
                        <div class="detail-row">
                            <span class="detail-label">Processed By:</span>
                            <span class="detail-value">
                                <strong>${data.approver_name}</strong><br>
                                <small class="text-muted">${data.approved_date || ''}</small>
                            </span>
                        </div>
                        `;
                    }
                    
                    $('#viewModalBody').html(html);
                } else {
                    $('#viewModalBody').html('<div class="alert alert-danger">Failed to load details</div>');
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON?.message || 'Failed to load details';
                $('#viewModalBody').html(`<div class="alert alert-danger">${message}</div>`);
            }
        });
    }

    // Handle approval form submission
    $('#approvalForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate status selection
        let status = $('#approval_status').val();
        if (!status) {
            toastr.error('Please select an action (Approve or Reject)');
            return;
        }
        
        // Disable submit button to prevent double submission
        $('#approvalSubmitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

        let formData = {
            id: $('#approval_id').val(),
            status: status,
            remarks: $('#approval_remarks').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        $.ajax({
            url: "{{ route('attendance-regularization.approval') }}",
            type: "POST",
            data: formData,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#approvalModal').modal('hide');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    toastr.error(response.message);
                    $('#approvalSubmitBtn').prop('disabled', false).html('<i class="feather-check-circle me-2"></i>Submit');
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON?.message || 'Something went wrong';
                toastr.error(message);
                $('#approvalSubmitBtn').prop('disabled', false).html('<i class="feather-check-circle me-2"></i>Submit');
            }
        });
    });

    // Auto-submit on select change
    $('.filter-select').on('change', function() {
        $('#filterForm').submit();
    });

    // Date validation
    $('input[name="from_date"], input[name="to_date"]').on('change', function() {
        let fromDate = $('input[name="from_date"]').val();
        let toDate = $('input[name="to_date"]').val();

        if (fromDate && toDate && fromDate > toDate) {
            toastr.error('From date cannot be greater than To date');
            $(this).val('');
        }
    });

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

        let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        let downloadLink = document.createElement('a');
        downloadLink.download = 'regularization_requests_' + new Date().getTime() + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.click();

        toastr.success('CSV exported successfully');
    }

    // Reset modal on close
    $('#approvalModal').on('hidden.bs.modal', function() {
        $('#approvalForm')[0].reset();
        $('#approval_status').removeClass('approve-selected reject-selected');
        $('#approvalSubmitBtn').prop('disabled', false).html('<i class="feather-check-circle me-2"></i>Submit');
        $('#employeeDetailCard').hide();
    });

    // Clear search on empty input
    $('input[name="employee"]').on('keyup', function(e) {
        if (e.key === 'Enter') {
            $('#filterForm').submit();
        }
    });
</script>
@endsection