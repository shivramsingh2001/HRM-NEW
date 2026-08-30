@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== SIMPLE CONSISTENT STYLING ==================== */
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
        .mini-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg-soft-primary {
            background-color: rgba(79, 70, 229, 0.1);
        }

        .bg-soft-success {
            background-color: rgba(16, 185, 129, 0.1);
        }

        .bg-soft-warning {
            background-color: rgba(245, 158, 11, 0.1);
        }

        .bg-soft-danger {
            background-color: rgba(239, 68, 68, 0.1);
        }

        .bg-soft-info {
            background-color: rgba(14, 165, 233, 0.1);
        }

        .border-warning {
            border-left: 3px solid #f59e0b !important;
        }

        .border-success {
            border-left: 3px solid #10b981 !important;
        }

        .border-info {
            border-left: 3px solid #0ea5e9 !important;
        }

        .border-danger {
            border-left: 3px solid #ef4444 !important;
        }

        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .text-decoration-none {
            text-decoration: none !important;
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

        .filter-item.search {
            flex: 1 1 200px;
            min-width: 180px;
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
            background: white;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
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
            background-color: white;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
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
            transform: translateY(-1px);
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
            border-color: #94a3b8;
            color: #1e293b;
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
            display: inline-flex;
            align-items: center;
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

        /* ==================== EMPLOYEE AVATAR ==================== */
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-details {
            line-height: 1.4;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        .employee-email {
            font-size: 11px;
            color: #64748b;
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

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .badge.bg-primary {
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
            margin: 0 2px;
            cursor: pointer;
        }

        .action-btn:hover {
            background: white;
            color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.1);
        }

        .action-btn.approve {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.approve:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .action-btn.reject {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        .action-btn.reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 14px;
        }

        /* ==================== FILE ATTACHMENT ==================== */
        .file-attachment {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: #475569;
            transition: all 0.2s;
        }

        .file-attachment:hover {
            color: #4f46e5;
            transform: translateY(-1px);
        }

        /* ==================== MODAL STYLES ==================== */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 20px;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 16px 20px;
        }

        .modal-body {
            padding: 24px;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 6px !important;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .page-item.active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        .page-item.disabled .page-link {
            color: #94a3b8;
            background: #f8fafc;
            cursor: not-allowed;
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
        @media (max-width: 1400px) {
            .col-xxl-4 {
                flex: 0 0 auto;
                width: 33.33333333%;
            }
        }

        @media (max-width: 992px) {
            .filter-row {
                gap: 8px;
            }

            .filter-item {
                flex: 1 1 calc(33.333% - 8px);
                min-width: 120px;
            }

            .filter-item.search {
                flex: 1 1 100%;
                min-width: 100%;
            }

            .col-xxl-4 {
                flex: 0 0 auto;
                width: 50%;
            }

            .col-xxl-3 {
                flex: 0 0 auto;
                width: 50%;
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

            .col-xxl-4,
            .col-xxl-3 {
                flex: 0 0 auto;
                width: 100%;
            }

            .employee-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }

        /* ==================== UTILITY CLASSES ==================== */
        .fs-11 {
            font-size: 10px;
        }

        .fs-12 {
            font-size: 11px;
        }

        .fs-20 {
            font-size: 16px;
        }

        .fw-semibold {
            font-weight: 600;
        }

        .gap-1 {
            gap: 0.25rem;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .gap-3 {
            gap: 1rem;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .pt-2 {
            padding-top: 0.5rem;
        }

        .border-top {
            border-top: 1px solid #e2e8f0;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #64748b !important;
        }

        .text-white {
            color: white !important;
        }

        .text-primary {
            color: #4f46e5 !important;
        }

        .text-success {
            color: #10b981 !important;
        }

        .text-warning {
            color: #f59e0b !important;
        }

        .text-danger {
            color: #ef4444 !important;
        }

        .text-info {
            color: #0ea5e9 !important;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Team Expense</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.view-all') }}">Expenses</a></li>
                <li class="breadcrumb-item active">Expense Applications</li>
            </ul>
        </div>
        <!--<div class="page-header-right ms-auto">-->
        <!--    <div class="page-header-right-items">-->
        <!--        <div class="d-flex d-md-none">-->
        <!--            <a href="#" class="page-header-right-close-toggle">-->
        <!--                <i class="feather-arrow-left me-2"></i>-->
        <!--                <span>Back</span>-->
        <!--            </a>-->
        <!--        </div>-->
        <!--        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">-->
        <!--            <div class="dropdown">-->
        <!--                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10"-->
        <!--                    data-bs-auto-close="outside">-->
        <!--                    <i class="feather-download"></i>-->
        <!--                </a>-->
        <!--                <div class="dropdown-menu dropdown-menu-end">-->
        <!--                    <a href="#" class="dropdown-item" onclick="exportToCSV()">-->
        <!--                        <i class="bi bi-filetype-csv me-3"></i>-->
        <!--                        <span>Export CSV</span>-->
        <!--                    </a>-->
        <!--                </div>-->
        <!--            </div>-->
        <!--            <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal"-->
        <!--                data-bs-target="#addexpenseModal">-->
        <!--                <i class="feather-plus me-2"></i>-->
        <!--                <span>Add Expense</span>-->
        <!--            </a>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--    <div class="d-md-none d-flex align-items-center">-->
        <!--        <a href="#" class="page-header-right-open-toggle">-->
        <!--            <i class="feather-align-right fs-20"></i>-->
        <!--        </a>-->
        <!--    </div>-->
        <!--</div>-->
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="row g-3 mb-2">
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-primary" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'advance'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-primary">
                                        <i class="fas fa-arrow-up text-primary fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Advance</span>
                                        <h6 class="fw-bold mb-0">₹{{ number_format($totalAdvanceAmount ?? 0, 2) }}</h6>
                                        <span class="fs-11 text-muted">{{ $totalAdvanceCount ?? 0 }} total requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Pending:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'pending'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-warning">{{ $pendingAdvanceCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($pendingAdvanceAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Approved:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'approved'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-success">{{ $approvedAdvanceCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($approvedAdvanceAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Completed:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'complete'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completedAdvanceCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($completedAdvanceAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Cancelled:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'cancelled'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-danger">{{ $cancelledAdvanceCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($cancelledAdvanceAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settlement Card with Status Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-success" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'settlement'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-success">
                                        <i class="fas fa-arrow-down text-success fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Settlement</span>
                                        <h6 class="fw-bold mb-0">₹{{ number_format($totalSettlementAmount ?? 0, 2) }}</h6>
                                        <span class="fs-11 text-muted">{{ $totalSettlementCount ?? 0 }} total
                                            requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Pending:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'pending'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-warning">{{ $pendingSettlementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($pendingSettlementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Approved:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'approved'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-success">{{ $approvedSettlementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($approvedSettlementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Completed:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'complete'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completedSettlementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($completedSettlementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Cancelled:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'cancelled'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-danger">{{ $cancelledSettlementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($cancelledSettlementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reimbursement Card with Status Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-info" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-info">
                                        <i class="fas fa-exchange-alt text-info fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Reimbursement</span>
                                        <h6 class="fw-bold mb-0">₹{{ number_format($totalReimbursementAmount ?? 0, 2) }}
                                        </h6>
                                        <span class="fs-11 text-muted">{{ $totalReimbursementCount ?? 0 }} total
                                            requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Pending:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'pending'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span
                                            class="fw-semibold text-warning">{{ $pendingReimbursementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($pendingReimbursementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Approved:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'approved'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span
                                            class="fw-semibold text-success">{{ $approvedReimbursementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($approvedReimbursementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Completed:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'complete'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completedReimbursementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($completedReimbursementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Cancelled:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'cancelled'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span
                                            class="fw-semibold text-danger">{{ $cancelledReimbursementCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($cancelledReimbursementAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Card with Status Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-dark" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']))) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-dark">
                                        <i class="fas fa-chart-pie text-dark fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Total Expenses</span>
                                        <h6 class="fw-bold mb-0">₹{{ number_format($totalAmount ?? 0, 2) }}</h6>
                                        <span class="fs-11 text-muted">{{ $totalExpenses ?? 0 }} total requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Pending:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'pending'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-warning">{{ $pendingCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($pendingAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Approved:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'approved'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-success">{{ $approvedCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($approvedAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Completed:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'complete'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completedCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($completedAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Cancelled:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'cancelled'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-danger">{{ $cancelledCount ?? 0 }}</span>
                                        <span
                                            class="fs-11 text-muted">(₹{{ number_format($cancelledAmount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- <!-- Second Row - Status Wise Breakdown -->
        <div class="row mt-2">
            <!--<div class="col-12">-->
            <!--    <h6 class="mb-3 text-muted">Status Breakdown</h6>-->
            <!--</div>-->

            <!-- Pending Card with Advance/Settlement Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-warning" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'pending'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-warning">
                                        <i class="feather-clock text-warning fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Pending</span>
                                        <h5 class="fw-bold mb-0">₹{{ number_format($pending_amount ?? 0, 2) }}</h5>
                                        <span class="fs-11 text-muted">{{ $pending_expenses ?? 0 }} total requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Advance:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'pending', 'requirement_type' => 'advance'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-primary">{{ $pending_advance_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($pending_advance_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Settlement:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'pending', 'requirement_type' => 'settlement'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-primary">{{ $pending_settlement_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($pending_settlement_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approved Card with Advance/Settlement Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-success" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'approved'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-success">
                                        <i class="feather-check-circle text-success fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Approved</span>
                                        <h5 class="fw-bold mb-0">₹{{ number_format($approved_amount ?? 0, 2) }}</h5>
                                        <span class="fs-11 text-muted">{{ $approved_expenses ?? 0 }} total requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Advance:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'approved', 'requirement_type' => 'advance'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-success">{{ $approved_advance_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($approved_advance_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Settlement:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'approved', 'requirement_type' => 'settlement'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span
                                            class="fw-semibold text-success">{{ $approved_settlement_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($approved_settlement_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed Card with Advance/Settlement Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-info" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'complete'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-info">
                                        <i class="feather-check-square text-info fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Completed</span>
                                        <h5 class="fw-bold mb-0">₹{{ number_format($complete_amount ?? 0, 2) }}</h5>
                                        <span class="fs-11 text-muted">{{ $complete_expenses ?? 0 }} total requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fs-11 text-muted">Advance:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'complete', 'requirement_type' => 'advance'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completed_advance_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($completed_advance_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Settlement:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'complete', 'requirement_type' => 'settlement'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-info">{{ $completed_settlement_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($completed_settlement_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejected/Cancelled Card with Advance/Settlement Breakdown -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-danger" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'cancelled'])) }}"
                                class="text-decoration-none flex-grow-1">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-danger">
                                        <i class="feather-x-circle text-danger fs-5"></i>
                                    </div>
                                    <div>
                                        <span class="fs-12 text-muted">Rejected/Cancelled</span>
                                        <h5 class="fw-bold mb-0">₹{{ number_format($cancelled_amount ?? 0, 2) }}</h5>
                                        <span class="fs-11 text-muted">{{ $cancelled_expenses ?? 0 }} total
                                            requests</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fs-11 text-muted">Advance:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'cancelled', 'requirement_type' => 'advance'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span class="fw-semibold text-danger">{{ $cancelled_advance_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($cancelled_advance_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fs-11 text-muted">Settlement:</span>
                                <a href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['status' => 'cancelled', 'requirement_type' => 'settlement'])) }}"
                                    class="text-decoration-none">
                                    <div>
                                        <span
                                            class="fw-semibold text-danger">{{ $cancelled_settlement_count ?? 0 }}</span>
                                        <span class="fs-11 text-muted">
                                            (₹{{ number_format($cancelled_settlement_amount ?? 0, 2) }})</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}
        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Expenses
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'from_date', 'to_date', 'expense_type', 'search']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'from_date', 'to_date', 'expense_type', 'search']))
                    <a href="{{ route('expense.view-all') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('expense.view-all') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Search -->
                    <div class="filter-item">

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
                                        href="{{ route('expense.view-all', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
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

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                                Cancelled/Rejected</option>
                            <option value="complete" {{ request('status') == 'complete' ? 'selected' : '' }}>Complete
                            </option>
                        </select>
                    </div>

                    <!-- Expense Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="expense_type">
                            <option value="">All Types</option>
                            @foreach ($expenseTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ request('expense_type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                     <!-- Project Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="project_id">
                            <option value="">All Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Expense Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="requirement_type">
                            <option value="">All Requirement</option>
                            <option value="advance" {{ request('requirement_type') == 'advance' ? 'selected' : '' }}>
                                Advance
                            </option>
                            <option value="settlement"
                                {{ request('requirement_type') == 'settlement' ? 'selected' : '' }}>
                                Settlement
                            </option>
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
                    {{-- <div class="filter-item" style="min-width: auto;">
                        <button type="submit" class="apply-btn">
                            <i class="feather-search"></i>
                            Apply
                        </button>
                    </div> --}}

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('expense.view-all') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'from_date', 'to_date', 'expense_type', 'search']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('expense_type'))
                        @php
                            $selectedType = $expenseTypes->firstWhere('id', request('expense_type'));
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Type: {{ $selectedType->name ?? request('expense_type') }}
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['expense_type', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ request('from_date') }}
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['from_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ request('to_date') }}
                            <a href="{{ route('expense.view-all', array_merge(request()->except(['to_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('expense.view-all') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Expenses Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Expense Applications</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $expenses->total() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="expenseList">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Expense Code</th>
                                        <th>Requirement Type</th>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Expense Type</th>
                                        <th>Project</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($expenses as $expense)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td class="text-bold">{{ $expense->expense_number ?? 'NA' }}</td>
                                            <td>
                                                @if ($expense->requirement_type == 'advance')
                                                    <span class="badge bg-primary">Advance</span>
                                                @elseif ($expense->requirement_type == 'reimbursement')
                                                    <span class="badge bg-info">Reimbursement</span>
                                                @else
                                                    <span class="badge bg-warning">Settlement</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="employee-info">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($expense->user_name ?? 'U', 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ $expense->user_name ?? 'N/A' }}
                                                            <small
                                                                class="text-muted fs-11">({{ $expense->employee_id ?? 'N/A' }})</small>
                                                        </div>
                                                        <div class="employee-email">{{ $expense->user_email ?? '' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>{{ date('d M Y', strtotime($expense->date)) ?? '' }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-primary">{{ $expense->expenseType->name ?? ucfirst($expense->expense_type) }}</span>
                                            </td>
                                            <td>{{ $expense->project->name ?? 'NA' }}</td>
                                            <td>
                                                <span class="fw-bold">₹{{ number_format($expense->amount, 2) }}</span>
                                            </td>
                                            <td style="max-width: 200px;">
                                                <div style="white-space: normal; word-wrap: break-word;">
                                                    {{ Str::limit($expense->description ?? 'No description', 50) }}
                                                </div>
                                            </td>
                                            <td>
                                                @if (!empty($expense->file))
                                                    @php
                                                        $filePath = $expense->file;
                                                        $extension = strtolower(
                                                            pathinfo($filePath, PATHINFO_EXTENSION),
                                                        );
                                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                    @endphp

                                                    @if (in_array($extension, $imageExtensions))
                                                        <a href="{{ asset($filePath) }}" target="_blank"
                                                            class="file-attachment"
                                                            style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                                            <img src="{{ asset($filePath) }}" alt="Expense File"
                                                                style="width:30px; height:30px; border-radius:50%; object-fit: cover;">
                                                        </a>
                                                    @else
                                                        <a href="{{ asset($filePath) }}" class="file-attachment"
                                                            download
                                                            style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: #475569;">
                                                            <i class="feather-paperclip"></i>
                                                            <span>View</span>
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass =
                                                        [
                                                            'complete' => 'bg-success',
                                                            'cancelled' => 'bg-danger',
                                                            'approved' => 'bg-info',
                                                            'pending' => 'bg-warning',
                                                        ][$expense->status] ?? 'bg-secondary';
                                                @endphp
                                                <span class="badge {{ $statusClass }}">
                                                    {{ ucfirst($expense->status) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if (in_array($expense->status, ['pending']))
                                                    <div class="d-flex gap-1 justify-content-center">
                                                        <button type="button" class="action-btn approve"
                                                            onclick="openStatusModal({{ $expense->id }}, 'approved')"
                                                            title="Approve">
                                                            <i class="feather-check"></i>
                                                        </button>
                                                        <button type="button" class="action-btn reject"
                                                            onclick="openStatusModal({{ $expense->id }}, 'cancelled')"
                                                            title="Reject">
                                                            <i class="feather-x"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="fas fa-rupee-sign"></i>
                                                    <h4>No Expense Applications Found</h4>
                                                    <p class="text-muted">There are no expense applications to display</p>
                                                    <!--<button class="btn btn-primary btn-sm" data-bs-toggle="modal"-->
                                                    <!--    data-bs-target="#addexpenseModal">-->
                                                    <!--    <i class="feather-plus me-2"></i>Add Expense-->
                                                    <!--</button>-->
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if (method_exists($expenses, 'links') && $expenses->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $expenses->firstItem() }} to {{ $expenses->lastItem() }} of
                                    {{ $expenses->total() }} entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $expenses->appends(request()->query())->links() }}
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
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Expense Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="statusUpdateForm" method="POST">
                    @csrf
                    <input type="hidden" name="expense_id" id="expense_id">
                    <input type="hidden" name="status" id="status_value">

                    <div class="modal-body">
                        <div class="text-center mb-4">
                            <div id="statusIcon" class="mb-3"></div>
                            <h4 id="statusTitle"></h4>
                            <p class="text-muted" id="statusMessage"></p>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks <small
                                    class="text-muted">(Optional)</small></label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                placeholder="Add remarks or reason for this action..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="confirmButton">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000"
        };

        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Debounce search input
            let searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
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

            // Status update form submission
            $('#statusUpdateForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const expenseId = $('#expense_id').val();
                const status = $('#status_value').val();
                const actionUrl = "{{ url('expense/update-status') }}/" + expenseId;
                const formData = form.serialize();
                const confirmBtn = $('#confirmButton');

                // Disable button and show loading
                confirmBtn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

                $.ajax({
                    url: actionUrl,
                    type: "POST",
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#statusModal').modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 1200);
                        } else {
                            toastr.error(response.message);
                            confirmBtn.prop('disabled', false).html('Confirm');
                        }
                    },
                    error: function(xhr) {
                        confirmBtn.prop('disabled', false).html('Confirm');
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                toastr.error(value[0]);
                            });
                        } else if (xhr.status === 400) {
                        // Bad request - our custom error for insufficient balance
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            toastr.error(xhr.responseJSON.message);
                        } else {
                            toastr.error("Bad request. Please check your data.");
                        }
                    } 
                    else {
                            toastr.error("Something went wrong. Please try again.");
                        }
                    }
                });
            });
        });

        // Open status modal
        function openStatusModal(id, status) {
            $('#expense_id').val(id);
            $('#status_value').val(status);

            const iconDiv = $('#statusIcon');
            const title = $('#statusTitle');
            const message = $('#statusMessage');
            const confirmBtn = $('#confirmButton');

            if (status === 'approved') {
                iconDiv.html('<i class="feather-check-circle" style="font-size: 64px; color: #10b981;"></i>');
                title.html('Approve Expense');
                message.html('Are you sure you want to approve this expense?');
                confirmBtn.removeClass('btn-danger').addClass('btn-success').text('Approve');
            } else {
                iconDiv.html('<i class="feather-x-circle" style="font-size: 64px; color: #ef4444;"></i>');
                title.html('Reject Expense');
                message.html('Are you sure you want to reject this expense?');
                confirmBtn.removeClass('btn-success').addClass('btn-danger').text('Reject');
            }

            $('#statusModal').modal('show');
        }

        // Export to CSV
        // Enhanced Export to CSV using data attributes
        function exportToCSV() {
            let table = document.getElementById('expenseList');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tbody tr');
            let csv = [];

            // Add headers
            let headers = ['S.No', 'Employee (ID)', 'Date', 'Expense Type', 'Amount', 'Description', 'File', 'Status'];
            csv.push(headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(','));

            // Add data rows
            rows.forEach((row, index) => {
                // Get data from attributes
                let employeeName = row.dataset.employeeName || 'N/A';
                let employeeId = row.dataset.employeeId || '';
                let employeeEmail = row.dataset.employeeEmail || '';
                let expenseType = row.dataset.expenseType || 'N/A';
                let amount = row.dataset.amount || '0';
                let date = row.dataset.date || '';
                let description = row.dataset.description || '';
                let status = row.dataset.status || 'N/A';
                let hasFile = row.dataset.hasFile || 'No';

                // Format employee with ID
                let employeeDisplay = employeeName;
                if (employeeId) {
                    employeeDisplay += ' (' + employeeId + ')';
                }

                // Create row data
                let rowData = [
                    index + 1,
                    employeeDisplay,
                    date,
                    expenseType,
                    '₹' + parseFloat(amount).toFixed(2),
                    description,
                    hasFile,
                    status.charAt(0).toUpperCase() + status.slice(1)
                ];

                // Clean and quote
                rowData = rowData.map(cell => '"' + String(cell).replace(/"/g, '""') + '"');
                csv.push(rowData.join(','));
            });

            let csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'expenses_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
        // Modal hidden event
        $('#statusModal').on('hidden.bs.modal', function() {
            $('#remarks').val('');
            $('#confirmButton').prop('disabled', false).html('Confirm');
        });
    </script>
@endsection
