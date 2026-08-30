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
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: #4f46e5;
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
            background: #eef2ff;
            color: #4f46e5;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #4f46e5 !important;
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
    /* ==================== STATS CARDS ==================== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

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

    .cancelled-card::before {
        background: linear-gradient(90deg, #6b7280, #9ca3af);
    }

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

    .cancelled-card .stats-icon-wrapper {
        background: rgba(107, 114, 128, 0.1);
    }

    .cancelled-card .stats-icon-wrapper i {
        color: #6b7280;
        font-size: 24px;
    }

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

    @media (max-width: 1400px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (max-width: 992px) {
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

    .filter-select, .filter-input {
        width: 100%;
        height: 36px;
        padding: 6px 28px 6px 10px;
        font-size: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        transition: all 0.2s;
    }

    .filter-select {
        background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
        background-size: 14px;
        appearance: none;
        cursor: pointer;
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

    .badge.bg-secondary {
        background: #f1f5f9 !important;
        color: #475569;
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

    /* ==================== EMPLOYEE INFO ==================== */
    .employee-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .employee-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e0e7ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 12px;
    }

    .employee-details {
        line-height: 1.3;
    }

    .employee-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 13px;
    }

    .employee-id {
        font-size: 10px;
        color: #64748b;
    }

    /* ==================== DATE RANGE ==================== */
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

    .action-btn.approve {
        background: #d1fae5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .action-btn.approve:hover {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .action-btn.reject {
        background: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .action-btn.reject:hover {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
    }

    .action-btn i {
        font-size: 14px;
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
    .modal-comments {
        width: 100%;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        resize: vertical;
        min-height: 100px;
    }

    .modal-comments:focus {
        outline: none;
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
            <h5 class="m-b-10">
                @if(auth()->user()->role == 'admin')
                    All Travel & WFH Requests
                @elseif(auth()->user()->role == 'hr')
                    HR - ravel & WFH Requests
                @else
                    Team ravel & WFH Requests
                @endif
            </h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">
                @if(auth()->user()->role == 'admin' || auth()->user()->role == 'hr')
                    All Requests
                @else
                    Team Requests
                @endif
            </li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown">
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
</div>

<div class="main-content" style="padding: 30px !important;">
    <!-- Stats Cards -->
    <div class="stats-grid">
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

        <div class="stats-card rejected-card">
            <div class="stats-icon-wrapper">
                <i class="feather-x-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $rejectedRequests ?? 0 }}</div>
                <div class="stats-label">Rejected</div>
                <div class="stats-count">
                    <span class="count-number">{{ $rejectedDays ?? 0 }}</span>
                    <span class="count-text">Days</span>
                </div>
            </div>
        </div>

        <!--<div class="stats-card cancelled-card">-->
        <!--    <div class="stats-icon-wrapper">-->
        <!--        <i class="feather-slash"></i>-->
        <!--    </div>-->
        <!--    <div class="stats-content">-->
        <!--        <div class="stats-amount-main">{{ $cancelledRequests ?? 0 }}</div>-->
        <!--        <div class="stats-label">Cancelled</div>-->
        <!--        <div class="stats-count">-->
        <!--            <span class="count-number">{{ $cancelledDays ?? 0 }}</span>-->
        <!--            <span class="count-text">Days</span>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--</div>-->
    </div>

    <!-- Filter Section -->
    <div class="filter-wrapper">
        <div class="filter-header">
            <div class="filter-title">
                <i class="feather-filter"></i>
                Filter Requests
                @php
                    $activeFilterCount = collect(
                        request()->only(['status', 'request_type', 'employee', 'from_date', 'to_date'])
                    )->filter()->count();
                @endphp
                @if ($activeFilterCount > 0)
                    <span>{{ $activeFilterCount }} active</span>
                @endif
            </div>
            @if (request()->hasAny(['status', 'request_type', 'employee', 'from_date', 'to_date']))
                <a href="{{ route('manager.requests') }}" class="clear-all-link">
                    <i class="feather-x"></i>
                    Clear All
                </a>
            @endif
        </div>

        <form action="{{ route('manager.requests') }}" method="GET" id="filterForm">
            <div class="filter-row">
                <div class="filter-item">
                    <select class="filter-select" name="status">
                        <option value="">All Status</option>
                        <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>Pending</option>
                        <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>Approved</option>
                        <option value="REJECTED" {{ request('status') == 'REJECTED' ? 'selected' : '' }}>Rejected</option>
                        <option value="CANCELLED" {{ request('status') == 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

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

                @if(auth()->user()->role == 'admin' || auth()->user()->role == 'hr')
                
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
                                    <a class="dropdown-item rounded {{ !request('employee') ? 'active' : '' }}"
                                        href="{{ route('manager.requests', array_merge(request()->except(['employee', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('manager.requests', array_merge(request()->except(['page']), ['employee' => $employee->id])) }}">
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
                @endif

                <div class="filter-item">
                    <input type="date" class="filter-input" name="from_date" 
                           value="{{ request('from_date') }}" placeholder="From Date">
                </div>

                <div class="filter-item">
                    <input type="date" class="filter-input" name="to_date" 
                           value="{{ request('to_date') }}" placeholder="To Date">
                </div>

                <!--<div class="filter-item" style="min-width: auto;">-->
                <!--    <button type="submit" class="apply-btn">-->
                <!--        <i class="feather-search"></i>-->
                <!--        Apply-->
                <!--    </button>-->
                <!--</div>-->

                <div class="filter-item" style="min-width: auto;">
                    <a href="{{ route('manager.requests') }}" class="reset-btn">
                        <i class="feather-refresh-cw"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>

        @if (request()->hasAny(['status', 'request_type', 'employee', 'from_date', 'to_date']))
            <div class="active-filters">
                <span class="active-filters-label">Active:</span>

                @if (request('status'))
                    <span class="filter-tag">
                        <i class="feather-activity"></i>
                        Status: {{ ucfirst(strtolower(request('status'))) }}
                        <a href="{{ route('manager.requests', array_merge(request()->except(['status', 'page']))) }}"
                           class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('request_type') && $selectedType = $requestTypes->firstWhere('id', request('request_type')))
                    <span class="filter-tag">
                        <i class="feather-tag"></i>
                        Type: {{ $selectedType->type_name }}
                        <a href="{{ route('manager.requests', array_merge(request()->except(['request_type', 'page']))) }}"
                           class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('employee') && $selectedEmployee = $employees->firstWhere('id', request('employee')))
                    <span class="filter-tag">
                        <i class="feather-user"></i>
                        Employee: {{ $selectedEmployee->name }}
                        <a href="{{ route('manager.requests', array_merge(request()->except(['employee', 'page']))) }}"
                           class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif
            
                @if (request('from_date'))
                    <span class="filter-tag">
                        <i class="feather-calendar"></i>
                        From: {{ \Carbon\Carbon::parse(request('from_date'))->format('d M Y') }}
                        <a href="{{ route('manager.requests', array_merge(request()->except(['from_date', 'page']))) }}"
                           class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                @if (request('to_date'))
                    <span class="filter-tag">
                        <i class="feather-calendar"></i>
                        To: {{ \Carbon\Carbon::parse(request('to_date'))->format('d M Y') }}
                        <a href="{{ route('manager.requests', array_merge(request()->except(['to_date', 'page']))) }}"
                           class="remove-tag">
                            <i class="feather-x"></i>
                        </a>
                    </span>
                @endif

                <a href="{{ route('manager.requests') }}" class="filter-tag clear-all">
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
                    <h5 class="card-title mb-0">
                        @if(auth()->user()->role == 'admin')
                            All Employee Requests
                        @elseif(auth()->user()->role == 'hr')
                            HR - All Requests
                        @else
                            Team Member Requests
                        @endif
                    </h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info">
                            <i class="feather-list me-1"></i>Total: {{ $requests->total() }}
                        </span>
                        <span class="badge bg-warning">
                            <i class="feather-clock me-1"></i>Pending: {{ $pendingRequests }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table" id="requestsTable">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>Employee</th>
                                    <th>Request Type</th>
                                    <th>Date Range</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requests as $request)
                                    <tr class="{{ $request->status == 'PENDING' ? 'pending-row' : '' }}">
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    {{ strtoupper(substr($request->user->name, 0, 2)) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name">{{ $request->user->name }}</div>
                                                    <div class="employee-id">{{ $request->user->employee_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="request-type-badge {{ $request->requestType->type_name == 'WFH' ? 'type-wfh' : 'type-travel' }}">
                                                <i class="feather-{{ $request->requestType->type_name == 'WFH' ? 'home' : 'map-pin' }}"></i>
                                                {{ $request->requestType->type_name }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="date-range">
                                                <span class="start-date">{{ \Carbon\Carbon::parse($request->start_date)->format('d M Y') }}</span>
                                                <span class="end-date">
                                                    <i class="feather-arrow-right"></i>
                                                    {{ \Carbon\Carbon::parse($request->end_date)->format('d M Y') }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="duration-badge">
                                                <i class="feather-calendar"></i>
                                                {{ $request->duration_in_days }} {{ Str::plural('day', $request->duration_in_days) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="reason-cell" title="{{ $request->reason }}">
                                                {{ Str::limit($request->reason ?? 'No reason provided', 30) }}
                                            </div>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($request->applied_date)->format('d M Y') }}</td>
                                        <td>
                                            @php
                                                $statusClass = [
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
                                               

                                                <!-- Approve Button (only for pending requests) -->
                                                @if($request->status == 'PENDING')
                                                    <button class="action-btn approve approve-request"
                                                            data-id="{{ $request->id }}"
                                                            data-employee="{{ $request->user->name }}"
                                                            title="Approve Request">
                                                        <i class="feather-check"></i>
                                                    </button>

                                                    <button class="action-btn reject reject-request"
                                                            data-id="{{ $request->id }}"
                                                            data-employee="{{ $request->user->name }}"
                                                            title="Reject Request">
                                                        <i class="feather-x"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather-inbox"></i>
                                                <h4>No Requests Found</h4>
                                                <p class="text-muted">There are no requests to display at the moment.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                
                @if($requests->hasPages())
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
<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="feather-check-circle text-success" style="font-size: 48px;"></i>
                </div>
                <p class="text-center mb-3">
                    Are you sure you want to approve the request from <strong id="approveEmployeeName"></strong>?
                </p>
                <div class="form-group">
                    <label for="approveComments">Comments (Optional)</label>
                    <textarea class="modal-comments" id="approveComments" 
                              placeholder="Add any comments or remarks..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApprove">
                    <i class="feather-check me-1"></i> Yes, Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="feather-alert-triangle text-danger" style="font-size: 48px;"></i>
                </div>
                <p class="text-center mb-3">
                    Are you sure you want to reject the request from <strong id="rejectEmployeeName"></strong>?
                </p>
                <div class="form-group">
                    <label for="rejectComments">Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea class="modal-comments" id="rejectComments" 
                              placeholder="Please provide a reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmReject">
                    <i class="feather-x me-1"></i> Yes, Reject
                </button>
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
        $('.filter-select, .filter-input').on('change', function() {
            $('#filterForm').submit();
        });

        // Approve Request
        let approveId = null;
        $(document).on('click', '.approve-request', function() {
            approveId = $(this).data('id');
            let employeeName = $(this).data('employee');
            $('#approveEmployeeName').text(employeeName);
            $('#approveComments').val('');
            $('#approveModal').modal('show');
        });

        $('#confirmApprove').on('click', function() {
            if (!approveId) return;

            let comments = $('#approveComments').val();

            $.ajax({
                url: '/manager/requests/' + approveId + '/approve',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    comments: comments
                },
                beforeSend: function() {
                    $('#confirmApprove').prop('disabled', true).html('<i class="feather-loader me-1"></i> Processing...');
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#approveModal').modal('hide');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    let message = 'Something went wrong.';
                    if (xhr.responseJSON?.message) {
                        message = xhr.responseJSON.message;
                    }
                    toastr.error(message);
                },
                complete: function() {
                    $('#confirmApprove').prop('disabled', false).html('<i class="feather-check me-1"></i> Yes, Approve');
                }
            });
        });

        // Reject Request
        let rejectId = null;
        $(document).on('click', '.reject-request', function() {
            rejectId = $(this).data('id');
            let employeeName = $(this).data('employee');
            $('#rejectEmployeeName').text(employeeName);
            $('#rejectComments').val('');
            $('#rejectModal').modal('show');
        });

        $('#confirmReject').on('click', function() {
            if (!rejectId) return;

            let comments = $('#rejectComments').val().trim();
            
            if (!comments) {
                toastr.error('Please provide a reason for rejection');
                return;
            }

            $.ajax({
                url: '/manager/requests/' + rejectId + '/reject',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    comments: comments
                },
                beforeSend: function() {
                    $('#confirmReject').prop('disabled', true).html('<i class="feather-loader me-1"></i> Processing...');
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#rejectModal').modal('hide');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    let message = 'Something went wrong.';
                    if (xhr.responseJSON?.message) {
                        message = xhr.responseJSON.message;
                    }
                    toastr.error(message);
                },
                complete: function() {
                    $('#confirmReject').prop('disabled', false).html('<i class="feather-x me-1"></i> Yes, Reject');
                }
            });
        });

        // Reset modals on close
        $('#approveModal, #rejectModal').on('hidden.bs.modal', function() {
            approveId = null;
            rejectId = null;
            $('#approveComments, #rejectComments').val('');
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
        for (let i = 0; i < headerCells.length - 1; i++) {
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

            for (let j = 0; j < cols.length - 1; j++) {
                let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                rowData.push('"' + cellText + '"');
            }

            if (rowData.length > 0) {
                csv.push(rowData.join(','));
            }
        }

        let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        let downloadLink = document.createElement('a');
        downloadLink.download = 'requests_' + new Date().getTime() + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.click();

        toastr.success('CSV exported successfully');
    }
</script>
@endsection