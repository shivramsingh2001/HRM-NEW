@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== SIMPLE CONSISTENT STYLING ==================== */
        .custom-employee-dropdown .btn {
            height: 34px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 12px;
            padding: 0 9px;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: #1e3a8a;
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
            background-color: rgba(30, 58, 138, 0.1);
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

        .filter-desc {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* One single row on desktop; wraps only below 1200px */
        .filter-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-end;
            gap: 8px;
        }

        .filter-item {
            flex: 1 1 0;
            min-width: 0;
        }

        .filter-item.search {
            flex: 1.7 1 0;
        }

        .filter-item.reset {
            flex: 0 0 auto;
        }

        .filter-label {
            display: block;
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .filter-date {
            padding-right: 6px !important;
            background-image: none !important;
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
            height: 34px;
            padding: 5px 10px 5px 30px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .search-wrapper .form-control:focus {
            background: white;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }

        .filter-select {
            width: 100%;
            height: 34px;
            padding: 5px 24px 5px 9px;
            font-size: 12px;
            color: #1e293b;
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
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
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
            background: #172c6b;
            transform: translateY(-1px);
        }

        .reset-btn {
            height: 34px;
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
            color: #1e3a8a;
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
            background-color: #f7f8fb;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #64748b;
            border-bottom: 1px solid #eaeef5;
            padding: 9px 12px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid #eef1f7;
        }

        .table tbody tr:hover {
            background-color: #f6f8fd;
        }

        /* ==================== BADGES — one blue theme ==================== */
        .badge {
            padding: 3px 10px;
            font-weight: 600;
            font-size: 10.5px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge.bg-success {
            background: rgba(29, 78, 216, .14) !important;
            color: #1d4ed8;
        }

        .badge.bg-danger {
            background: #eef1f7 !important;
            color: #64748b;
        }

        .badge.bg-info {
            background: rgba(14, 165, 233, .14) !important;
            color: #0c87c4;
        }

        .badge.bg-warning {
            background: rgba(96, 165, 250, .20) !important;
            color: #2563eb;
        }

        .badge.bg-primary {
            background: #e3edfe !important;
            color: #1e3a8a;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-btn {
            width: 30px;
            height: 30px;
            padding: 0;
            border-radius: 10%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #475569;
            border: 1px solid #dfe5f0;
            transition: all 0.18s ease;
            cursor: pointer;
        }

        .action-btn i {
            font-size: 14px;
            line-height: 1;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(30, 58, 138, 0.18);
        }

        /* Approve = filled theme blue, Reject = outlined — same colour family, clearly different weight */
        .action-btn.approve {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border-color: transparent;
        }

        .action-btn.approve:hover {
            background: linear-gradient(135deg, #172c6b, #1d4ed8);
        }

        .action-btn.reject {
            background: #fff;
            color: #1e3a8a;
            border-color: #bcd0f5;
        }

        .action-btn.reject:hover {
            background: #e3edfe;
            border-color: #1e3a8a;
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
            color: #1e3a8a;
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
            background: #1e3a8a;
            border-color: #1e3a8a;
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

        @media (max-width: 1199.98px) {
            .filter-row {
                flex-wrap: wrap;
            }

            .filter-item {
                flex: 1 1 calc(25% - 8px);
                min-width: 130px;
            }

            .filter-item.search {
                flex: 1 1 calc(50% - 8px);
            }

            .filter-item.reset {
                flex: 0 0 auto;
                min-width: 0;
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
            color: #1e3a8a !important;
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

        /* ==================== EXPENSE OVERVIEW — mirrors the admin dashboard tiles ==================== */
        .exp-team {
            --d-card: #ffffff;
            --d-border: #eaeef5;
            --d-border-strong: #dfe5f0;
            --d-text: #1a2236;
            --d-text-soft: #6b7385;
            --d-text-muted: #9aa1b1;
            --d-radius: 14px;
            --d-shadow: 0 1px 2px rgba(20, 30, 60, .04), 0 2px 8px rgba(20, 30, 60, .04);
            --d-shadow-hover: 0 6px 22px rgba(30, 50, 110, .10);
        }

        .exp-team .section-hdr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: #f7f8fb;
            border: 1px solid var(--d-border);
            border-radius: 10px;
            padding: 7px 12px;
            margin-bottom: 8px;
        }

        .exp-team .section-hdr-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .exp-team .section-hdr-icon {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            flex: none;
            background: #e3edfe;
            color: #1e3a8a;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .exp-team .section-hdr-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--d-text);
            margin: 0;
        }

        .exp-team .section-hdr-hint {
            font-size: 10.5px;
            color: var(--d-text-soft);
        }

        .exp-team .row.g-compact {
            --bs-gutter-x: 8px;
            --bs-gutter-y: 8px;
        }

        .exp-team .exp-card {
            background: var(--d-card);
            border: 1px solid var(--d-border);
            border-radius: var(--d-radius);
            box-shadow: var(--d-shadow);
            margin-bottom: 0;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .exp-team .exp-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--d-shadow-hover);
            border-color: var(--d-border-strong);
        }

        .exp-team .exp-card .card-body {
            padding: 10px;
        }

        .exp-team .exp-card .mini-stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            font-size: 13px;
            flex-shrink: 0;
            background: #e3edfe !important;
            color: #1e3a8a !important;
        }

        .exp-team .exp-card a {
            text-decoration: none;
            transition: color .15s ease;
        }

        .exp-team .stat-label {
            font-size: 10px;
            font-weight: 600;
            color: var(--d-text-soft);
        }

        .exp-team .stat-value-sm {
            font-size: 14px;
            font-weight: 800;
            color: var(--d-text);
            line-height: 1.1;
            margin: 0;
        }

        .exp-team .stat-sub {
            font-size: 9.5px;
            color: var(--d-text-muted);
        }

        .exp-team .breakdown-row + .breakdown-row {
            border-top: 1px dashed var(--d-border);
        }

        .exp-team .bd-num {
            font-size: 12px;
            font-weight: 700;
        }

        .exp-team .bd-pending {
            color: #3b82f6;
        }

        .exp-team .bd-approved {
            color: #1d4ed8;
        }

        .exp-team .bd-complete {
            color: #0ea5e9;
        }

        .exp-team .bd-cancelled {
            color: #64748b;
        }

        .exp-team .exp-card a:hover .stat-sub {
            color: #1e3a8a;
        }

        .exp-team .employee-avatar {
            width: 32px;
            height: 32px;
            font-size: 12px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        .exp-team .employee-name {
            font-size: 12.5px;
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

    <div class="main-content exp-team" style="padding: 20px !important;">
        <!-- Expense Overview (same tiles as the admin dashboard) -->
        {{-- <div class="section-hdr">
            <div class="section-hdr-left">
                <span class="section-hdr-icon"><i class="feather-credit-card"></i></span>
                <h6 class="section-hdr-title">Expense Overview</h6>
            </div>
            <span class="section-hdr-hint">Click any figure to filter the list below</span>
        </div> --}}
        <div class="row g-compact mb-3">
            @php
                $expenseCards = [
                    ['title'=>'Advance','amount'=>$totalAdvanceAmount ?? 0,'count'=>$totalAdvanceCount ?? 0,'icon'=>'fa-arrow-up','type'=>'advance',
                        'pending'=>['c'=>$pendingAdvanceCount ?? 0,'a'=>$pendingAdvanceAmount ?? 0],'approved'=>['c'=>$approvedAdvanceCount ?? 0,'a'=>$approvedAdvanceAmount ?? 0],
                        'completed'=>['c'=>$completedAdvanceCount ?? 0,'a'=>$completedAdvanceAmount ?? 0],'cancelled'=>['c'=>$cancelledAdvanceCount ?? 0,'a'=>$cancelledAdvanceAmount ?? 0]],
                    ['title'=>'Settlement','amount'=>$totalSettlementAmount ?? 0,'count'=>$totalSettlementCount ?? 0,'icon'=>'fa-arrow-down','type'=>'settlement',
                        'pending'=>['c'=>$pendingSettlementCount ?? 0,'a'=>$pendingSettlementAmount ?? 0],'approved'=>['c'=>$approvedSettlementCount ?? 0,'a'=>$approvedSettlementAmount ?? 0],
                        'completed'=>['c'=>$completedSettlementCount ?? 0,'a'=>$completedSettlementAmount ?? 0],'cancelled'=>['c'=>$cancelledSettlementCount ?? 0,'a'=>$cancelledSettlementAmount ?? 0]],
                    ['title'=>'Reimbursement','amount'=>$totalReimbursementAmount ?? 0,'count'=>$totalReimbursementCount ?? 0,'icon'=>'fa-exchange-alt','type'=>'reimbursement',
                        'pending'=>['c'=>$pendingReimbursementCount ?? 0,'a'=>$pendingReimbursementAmount ?? 0],'approved'=>['c'=>$approvedReimbursementCount ?? 0,'a'=>$approvedReimbursementAmount ?? 0],
                        'completed'=>['c'=>$completedReimbursementCount ?? 0,'a'=>$completedReimbursementAmount ?? 0],'cancelled'=>['c'=>$cancelledReimbursementCount ?? 0,'a'=>$cancelledReimbursementAmount ?? 0]],
                    ['title'=>'Total Expenses','amount'=>$totalAmount ?? 0,'count'=>$totalExpenses ?? 0,'icon'=>'fa-chart-pie','type'=>null,
                        'pending'=>['c'=>$pendingCount ?? 0,'a'=>$pendingAmount ?? 0],'approved'=>['c'=>$approvedCount ?? 0,'a'=>$approvedAmount ?? 0],
                        'completed'=>['c'=>$completedCount ?? 0,'a'=>$completedAmount ?? 0],'cancelled'=>['c'=>$cancelledCount ?? 0,'a'=>$cancelledAmount ?? 0]],
                ];
            @endphp
            @foreach ($expenseCards as $ec)
                @php
                    $baseParams = request()->except(['page']);
                    $allParams = $ec['type'] ? array_merge($baseParams, ['requirement_type' => $ec['type']]) : $baseParams;
                    $mk = function ($status) use ($baseParams, $ec) {
                        $p = $ec['type'] ? array_merge($baseParams, ['requirement_type' => $ec['type'], 'status' => $status]) : array_merge($baseParams, ['status' => $status]);
                        return route('expense.view-all', $p);
                    };
                @endphp
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card exp-card stretch-full">
                        <div class="card-body">
                            <a href="{{ route('expense.view-all', $allParams) }}" class="d-block mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-primary"><i class="fas {{ $ec['icon'] }}"></i></div>
                                    <div>
                                        <span class="stat-label d-block">{{ $ec['title'] }}</span>
                                        <h6 class="stat-value-sm">₹{{ number_format($ec['amount'], 2) }}</h6>
                                        <span class="stat-sub">{{ $ec['count'] }} total requests</span>
                                    </div>
                                </div>
                            </a>
                            <div class="border-top pt-2">
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Pending</span>
                                    <a href="{{ $mk('pending') }}"><span class="bd-num bd-pending">{{ $ec['pending']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['pending']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Approved</span>
                                    <a href="{{ $mk('approved') }}"><span class="bd-num bd-approved">{{ $ec['approved']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['approved']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Completed</span>
                                    <a href="{{ $mk('complete') }}"><span class="bd-num bd-complete">{{ $ec['completed']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['completed']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Cancelled</span>
                                    <a href="{{ $mk('cancelled') }}"><span class="bd-num bd-cancelled">{{ $ec['cancelled']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['cancelled']['a'], 2) }})</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Filter Section -->
        @php
            $filterKeys = ['status', 'from_date', 'to_date', 'expense_type', 'project_id', 'requirement_type', 'user_id', 'search'];
            $activeFilterCount = collect(request()->only($filterKeys))->filter()->count();
            $tagUrl = fn(array $drop) => route('expense.view-all', request()->except(array_merge($drop, ['page'])));
        @endphp
        <div class="filter-wrapper">
            <div class="filter-header">
                <div>
                    <div class="filter-title">
                        <i class="feather-filter"></i>
                        Filter Expenses
                        @if ($activeFilterCount > 0)
                            <span>{{ $activeFilterCount }} active</span>
                        @endif
                    </div>
                    {{-- <div class="filter-desc">Search or narrow the list by employee, status, category, project, requirement and date — it updates as you choose.</div> --}}
                </div>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('expense.view-all') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('expense.view-all') }}" method="GET" id="filterForm">
                <input type="hidden" name="user_id" value="{{ request('user_id') }}">
                <div class="filter-row">
                    <!-- Search -->
                    <div class="filter-item search">
                        {{-- <label class="filter-label">Search</label> --}}
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                placeholder="Name, code, category, project…" autocomplete="off">
                        </div>
                    </div>

                    <!-- Employee -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">Employee</label> --}}
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2 text-truncate" id="selectedEmployeeDisplay">
                                    @if (request('user_id') && ($selectedEmployee = $employees->firstWhere('id', request('user_id'))))
                                        <span class="employee-initials-sm">{{ strtoupper(substr($selectedEmployee->name, 0, 2)) }}</span>
                                        <span class="employee-name text-truncate">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>

                            <ul class="dropdown-menu p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('user_id') ? 'active' : '' }}"
                                        href="{{ $tagUrl(['user_id']) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $employee)
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('expense.view-all', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
                                            <span class="employee-initials">{{ strtoupper(substr($employee->name, 0, 2)) }}</span>
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

                    <!-- Status -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">Status</label> --}}
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="complete" {{ request('status') == 'complete' ? 'selected' : '' }}>Complete</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled / Rejected</option>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">Category</label> --}}
                        <select class="filter-select" name="expense_type">
                            <option value="">All Categories</option>
                            @foreach ($expenseTypes as $type)
                                <option value="{{ $type->id }}" {{ request('expense_type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Project -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">Project</label> --}}
                        <select class="filter-select" name="project_id">
                            <option value="">All Projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Requirement -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">Requirement</label> --}}
                        <select class="filter-select" name="requirement_type">
                            <option value="">All Requirements</option>
                            <option value="advance" {{ request('requirement_type') == 'advance' ? 'selected' : '' }}>Advance</option>
                            <option value="settlement" {{ request('requirement_type') == 'settlement' ? 'selected' : '' }}>Settlement</option>
                            <option value="reimbursement" {{ request('requirement_type') == 'reimbursement' ? 'selected' : '' }}>Reimbursement</option>
                        </select>
                    </div>

                    <!-- From date -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">From date</label> --}}
                        <input type="date" class="filter-select filter-date" name="from_date" value="{{ request('from_date') }}">
                    </div>

                    <!-- To date -->
                    <div class="filter-item">
                        {{-- <label class="filter-label">To date</label> --}}
                        <input type="date" class="filter-select filter-date" name="to_date" value="{{ request('to_date') }}">
                    </div>

                    <!-- Reset -->
                    <div class="filter-item reset">
                        {{-- <label class="filter-label">&nbsp;</label> --}}
                        <a href="{{ route('expense.view-all') }}" class="reset-btn" title="Reset all filters">
                            <i class="feather-refresh-cw"></i>
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if ($activeFilterCount > 0)
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ $tagUrl(['search']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('user_id') && ($tagEmployee = $employees->firstWhere('id', request('user_id'))))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Employee: {{ $tagEmployee->name }}
                            <a href="{{ $tagUrl(['user_id']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ $tagUrl(['status']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('expense_type'))
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Category: {{ $expenseTypes->firstWhere('id', request('expense_type'))->name ?? request('expense_type') }}
                            <a href="{{ $tagUrl(['expense_type']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('project_id'))
                        <span class="filter-tag">
                            <i class="feather-briefcase"></i>
                            Project: {{ $projects->firstWhere('id', request('project_id'))->name ?? request('project_id') }}
                            <a href="{{ $tagUrl(['project_id']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('requirement_type'))
                        <span class="filter-tag">
                            <i class="feather-layers"></i>
                            Requirement: {{ ucfirst(request('requirement_type')) }}
                            <a href="{{ $tagUrl(['requirement_type']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ request('from_date') }}
                            <a href="{{ $tagUrl(['from_date']) }}" class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ request('to_date') }}
                            <a href="{{ $tagUrl(['to_date']) }}" class="remove-tag"><i class="feather-x"></i></a>
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
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="card-title mb-0">Expense Applications</h5>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            @if ($canBulk ?? false)
                                {{-- Bulk approve / reject (Super Admin can switch this off per company) --}}
                                <div id="bulkBar" class="d-none align-items-center gap-2">
                                    <span class="small fw-semibold"><span id="bulkCount">0</span> selected</span>
                                    <button type="button" class="btn btn-primary btn-sm"
                                        onclick="openBulkModal('approved')"><i class="feather-check me-1"></i>Approve</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="openBulkModal('cancelled')"><i class="feather-x me-1"></i>Reject</button>
                                    <button type="button" class="btn btn-light btn-sm" id="bulkClear">Clear</button>
                                </div>
                            @endif
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
                                        @if ($canBulk ?? false)
                                            <th width="34"><input type="checkbox" id="bulkSelectAll"
                                                    title="Select all pending on this page"></th>
                                        @endif
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
                                            @if ($canBulk ?? false)
                                                <td>
                                                    @if ($expense->status === 'pending')
                                                        <input type="checkbox" class="bulk-row" value="{{ $expense->id }}">
                                                    @endif
                                                </td>
                                            @endif
                                            <td>{{ $loop->iteration }}</td>
                                            <td class="text-bold">
                                                {{ $expense->expense_number ?? 'NA' }}
                                                @if ($expense->possible_duplicate_of)
                                                    {{-- Flag for the approver, never a block: same person, category, amount and date. --}}
                                                    <div><span class="badge bg-warning text-dark" style="font-size:10px"
                                                            title="Same employee, category, amount and date as {{ $expense->possibleDuplicateOf?->expense_number ?? 'an earlier claim' }}">
                                                            <i class="feather-copy"></i> Possible duplicate</span></div>
                                                @endif
                                                @if ($expense->parent_expense_id)
                                                    <div><span class="badge bg-light text-dark" style="font-size:10px"
                                                            title="Created automatically from the shortfall of a settlement">Split from a settlement</span>
                                                    </div>
                                                @endif
                                                @if ($expense->payout_channel === 'payroll')
                                                    <div><span class="badge bg-info" style="font-size:10px"
                                                            title="Paid with the salary instead of a voucher">Via payroll {{ $expense->payroll_target_month }}</span>
                                                    </div>
                                                @endif
                                            </td>
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
                                            <td style="max-width: 100px;">
                                                <div style="white-space: normal;">
                                                    {{ Str::limit($expense->description ?? 'No description', 10) }}
                                                </div>
                                            </td>
                                            <td>
                                                {{-- Every receipt: the primary one plus any extras (signed URLs). --}}
                                                @forelse ($expense->receipt_list ?? [] as $receipt)
                                                    @if ($receipt['is_image'])
                                                        <a href="{{ $receipt['url'] }}" target="_blank" class="file-attachment"
                                                            title="{{ $receipt['name'] }}"
                                                            style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                                            <img src="{{ $receipt['url'] }}" alt="{{ $receipt['name'] }}"
                                                                style="width:30px; height:30px; border-radius:50%; object-fit: cover;">
                                                        </a>
                                                    @else
                                                        <a href="{{ $receipt['url'] }}" class="file-attachment" download
                                                            title="{{ $receipt['name'] }}"
                                                            style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: #475569;">
                                                            <i class="feather-paperclip"></i>
                                                            <span>PDF</span>
                                                        </a>
                                                    @endif
                                                @empty
                                                    <span class="text-muted">—</span>
                                                @endforelse
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
                                                    {{ $expense->withdrawn_at ? 'Withdrawn' : ucfirst($expense->status) }}
                                                </span>
                                                @if ($expense->withdrawn_at && $expense->withdrawn_reason)
                                                    <div class="small text-muted" style="font-size:10px;max-width:140px"
                                                        title="{{ $expense->withdrawn_reason }}">
                                                        {{ \Illuminate\Support\Str::limit($expense->withdrawn_reason, 40) }}</div>
                                                @endif
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
                                            <td colspan="13" class="text-center py-5">
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

    @if ($canBulk ?? false)
        {{-- Bulk approve / reject --}}
        <div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkTitle">Bulk update</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-2" id="bulkMessage"></p>
                        <label class="form-label small">Remarks <small class="text-muted">(optional, applied to
                                all)</small></label>
                        <textarea class="form-control" id="bulkRemarks" rows="2" maxlength="500"></textarea>
                        <div class="form-check mt-2" id="bulkCoverWrap">
                            <input class="form-check-input" type="checkbox" id="bulkCover">
                            <label class="form-check-label small" for="bulkCover">If a settlement is larger than the
                                employee's advance balance, deduct what the advance covers and turn the rest into a
                                reimbursement (instead of skipping it).</label>
                        </div>
                        <div id="bulkResult" class="mt-3 d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="bulkCancel">Cancel</button>
                        <button type="button" class="btn btn-primary" id="bulkConfirm">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('script-area')
    @if ($canBulk ?? false)
        <script>
            (function() {
                const BULK_URL = @json(route('expense.bulk-status'));
                let bulkStatus = 'approved';

                const ids = () => $('.bulk-row:checked').map((i, el) => parseInt(el.value)).get();
                const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));

                function sync() {
                    const n = ids().length;
                    $('#bulkCount').text(n);
                    $('#bulkBar').toggleClass('d-none', n === 0).toggleClass('d-flex', n > 0);
                    const all = $('.bulk-row').length;
                    $('#bulkSelectAll').prop('checked', all > 0 && n === all);
                }

                $(document).on('change', '.bulk-row', sync);
                $('#bulkSelectAll').on('change', function() {
                    $('.bulk-row').prop('checked', this.checked);
                    sync();
                });
                $('#bulkClear').on('click', function() {
                    $('.bulk-row, #bulkSelectAll').prop('checked', false);
                    sync();
                });

                window.openBulkModal = function(status) {
                    bulkStatus = status;
                    const n = ids().length;
                    const approve = status === 'approved';
                    $('#bulkTitle').text((approve ? 'Approve ' : 'Reject ') + n + ' expense(s)');
                    $('#bulkMessage').text(approve ?
                        'Each expense is approved on its own — any that cannot be (already processed, outside your team, or a settlement larger than the employee\'s balance) is skipped and listed afterwards.' :
                        'Each selected expense is rejected. Any that were already processed are skipped and listed afterwards.'
                        );
                    $('#bulkRemarks').val('');
                    $('#bulkCover').prop('checked', false);
                    $('#bulkCoverWrap').toggle(approve);   // only meaningful when approving
                    $('#bulkResult').addClass('d-none').empty();
                    $('#bulkConfirm').prop('disabled', false).removeClass('btn-danger btn-success btn-primary')
                        .addClass(approve ? 'btn-success' : 'btn-danger').text(approve ? 'Approve all' : 'Reject all');
                    $('#bulkModal').modal('show');
                };

                $('#bulkConfirm').on('click', function() {
                    const selected = ids();
                    if (!selected.length) return;
                    const btn = $(this).prop('disabled', true).html(
                        '<span class="spinner-border spinner-border-sm me-1"></span>Working…');

                    $.ajax({
                        url: BULK_URL,
                        type: 'POST',
                        data: {
                            ids: selected,
                            status: bulkStatus,
                            remarks: $('#bulkRemarks').val(),
                            cover_shortfall: (bulkStatus === 'approved' && $('#bulkCover').is(':checked')) ? 1 : 0
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                    }).done(function(res) {
                        const failed = (res.results || []).filter(r => !r.ok);
                        let html = `<div class="alert alert-${failed.length ? 'warning' : 'success'} mb-2">${esc(res.message)}</div>`;
                        if (failed.length) {
                            html += '<div class="small">Skipped:</div><ul class="small mb-0">' +
                                failed.map(f =>
                                    `<li><b>${esc(f.expense_number || ('#' + f.id))}</b> — ${esc(f.message)}</li>`
                                    ).join('') + '</ul>';
                        }
                        $('#bulkResult').html(html).removeClass('d-none');
                        btn.addClass('d-none');
                        $('#bulkCancel').text('Close').off('click').on('click', () => location.reload());
                        $('#bulkModal').off('hidden.bs.modal').on('hidden.bs.modal', () => location.reload());
                    }).fail(function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                        btn.prop('disabled', false).text(bulkStatus === 'approved' ? 'Approve all' :
                            'Reject all');
                    });
                });
            })();
        </script>
    @endif
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
            $('select.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Live search: submit shortly after typing stops, then put the cursor back
            // (the page reloads) so the user can keep typing.
            let searchTimeout;
            const searchBox = $('input[name="search"]');
            let lastSearch = searchBox.val();
            searchBox.on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (searchBox.val() === lastSearch) return;
                    try { sessionStorage.setItem('expTeamSearchFocus', '1'); } catch (e) {}
                    $('#filterForm').submit();
                }, 600);
            });
            try {
                if (sessionStorage.getItem('expTeamSearchFocus')) {
                    sessionStorage.removeItem('expTeamSearchFocus');
                    const el = searchBox.get(0);
                    if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
                }
            } catch (e) {}


            // Date inputs validation
            $('input[name="from_date"], input[name="to_date"]').on('change', function() {
                let fromDate = $('input[name="from_date"]').val();
                let toDate = $('input[name="to_date"]').val();

                if (fromDate && toDate && fromDate > toDate) {
                    toastr.error('From date cannot be greater than To date');
                    $(this).val('');
                    return;
                }
                $('#filterForm').submit();
            });

            // Status update form submission
            $('#statusUpdateForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const expenseId = $('#expense_id').val();
                const status = $('#status_value').val();
                const actionUrl = "{{ url('expense/update-status') }}/" + expenseId;
                let formData = form.serialize();
                // Set when the approver just agreed to "cover the shortfall" (see the 400 handler below).
                if (window.coverShortfallNext) {
                    formData += '&cover_shortfall=1';
                    window.coverShortfallNext = false;
                }
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
                            // Validation errors come as `errors`; a blocked budget comes as a plain `message`.
                            let errors = xhr.responseJSON?.errors;
                            if (errors) {
                                $.each(errors, function(key, value) {
                                    toastr.error(value[0]);
                                });
                            } else {
                                toastr.error(xhr.responseJSON?.message || 'This could not be approved.');
                            }
                        } else if (xhr.status === 400) {
                        // Bad request. An insufficient-advance-balance error carries the numbers, so offer the remedy
                        // instead of a dead end: deduct what the advance covers and turn the rest into a reimbursement.
                        const r = xhr.responseJSON || {};
                        if (r.code === 'insufficient_balance' && status === 'approved') {
                            const m = n => Number(n).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            if (confirm(`The employee's advance balance is ₹${m(r.available)}, but this settlement is ₹${m(r.requested)}.\n\nApprove anyway? ₹${m(r.available)} will be deducted from the advance and the remaining ₹${m(r.shortfall)} will become a reimbursement, ready to be paid.`)) {
                                window.coverShortfallNext = true;
                                $('#statusUpdateForm').trigger('submit');
                            } else {
                                toastr.warning('Not approved.');
                            }
                        } else if (r.message) {
                            toastr.error(r.message);
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
