@extends('client.layout.master')

@section('style')
    <style>
        /* Mini Stat Icon Styles */
        .mini-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg-soft-primary {
            background: rgba(79, 70, 229, 0.1);
        }

        .bg-soft-success {
            background: rgba(16, 185, 129, 0.1);
        }

        .bg-soft-info {
            background: rgba(99, 102, 241, 0.1);
        }

        .bg-soft-warning {
            background: rgba(245, 158, 11, 0.1);
        }

        .bg-soft-danger {
            background: rgba(239, 68, 68, 0.1);
        }

        .bg-soft-dark {
            background: rgba(30, 41, 59, 0.1);
        }

        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        /* Add Balance Card Styles */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white;
            border: none;
        }

        .balance-card .stats-amount-main {
            color: white;
        }

        .balance-card .stats-label {
            color: rgba(255, 255, 255, 0.8);
        }

        .balance-card .count-number {
            color: white;
        }

        .balance-card .count-text {
            color: rgba(255, 255, 255, 0.8);
        }

        .balance-card .stats-icon-wrapper {
            background: rgba(255, 255, 255, 0.2);
        }

        .balance-card .stats-icon-wrapper i {
            color: white;
        }


        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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

        .completed-card::before {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
        }

        .cancelled-card::before {
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

        .completed-card .stats-icon-wrapper {
            background: rgba(99, 102, 241, 0.1);
        }

        .completed-card .stats-icon-wrapper i {
            color: #6366f1;
            font-size: 24px;
        }

        .cancelled-card .stats-icon-wrapper {
            background: rgba(239, 68, 68, 0.1);
        }

        .cancelled-card .stats-icon-wrapper i {
            color: #ef4444;
            font-size: 24px;
        }

        /* Content Styles */
        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 12px;
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

        .stats-card:hover .stats-count {
            background: white;
            border-color: currentColor;
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
                grid-template-columns: repeat(3, 1fr);
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
            background: #f8fafc url("datAdvance:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
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

        .badge.bg-purple {
            background: #e0e7ff !important;
            color: #4f46e5;
        }

        /* ==================== PAYMENT DETAILS BUTTON ==================== */
        .payment-info-btn {
            background: #e0e7ff;
            color: #4f46e5;
            border: none;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .payment-info-btn:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-1px);
        }

        .payment-info-btn i {
            font-size: 12px;
        }

        /* ==================== PAYMENT DETAILS CARD ==================== */
        .payment-details-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-top: 10px;
            border-left: 3px solid #4f46e5;
        }

        .payment-detail-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .payment-detail-label {
            width: 100px;
            font-weight: 600;
            color: #475569;
        }

        .payment-detail-value {
            flex: 1;
            color: #1e293b;
        }

        .payment-mode-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }

        .mode-cash {
            background: #d1fae5;
            color: #065f46;
        }

        .mode-bank {
            background: #e0f2fe;
            color: #0369a1;
        }

        .mode-cheque {
            background: #fef3c7;
            color: #92400e;
        }

        .mode-upi {
            background: #e0e7ff;
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

        /* ==================== AMOUNT STYLING ==================== */
        .amount-positive {
            font-weight: 600;
            color: #059669;
        }

        .amount-pending {
            font-weight: 600;
            color: #d97706;
        }

        .amount-completed {
            font-weight: 600;
            color: #4f46e5;
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
                flex: 1 1 calc(33.333% - 8px);
                min-width: 120px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">My Expenses</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.index') }}">Expenses</a></li>
                <li class="breadcrumb-item active">My Expenses</li>
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
                        data-bs-target="#addexpenseModal">
                        <i class="feather-plus me-2"></i>
                        <span>Add Expense</span>
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

        <div class="row g-3 mb-3">
            <!-- Current Balance Card -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-card balance-card">
                    <div class="stats-icon-wrapper">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-amount-main">₹{{ number_format($currentBalance ?? 0, 2) }}</div>
                        <div class="stats-label">Current Balance</div>
                    </div>
                </div>
            </div>

            <!-- Total Advance Card -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-card total-card">
                    <div class="stats-icon-wrapper">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-amount-main">₹{{ number_format($totalAdvanceTaken ?? 0, 2) }}</div>
                        <div class="stats-label">Total Advance</div>
                    </div>
                </div>
            </div>

            <!-- Total Settlement Card -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-card approved-card">
                    <div class="stats-icon-wrapper">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-amount-main">₹{{ number_format($totalSettlementDone ?? 0, 2) }}</div>
                        <div class="stats-label">Total Settlement</div>
                    </div>
                </div>
            </div>

            <!-- Total Reimbursement Card -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-card approved-card">
                    <div class="stats-icon-wrapper">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-amount-main">₹{{ number_format($totalReimbursementDone ?? 0, 2) }}</div>
                        <div class="stats-label">Total Reimbursement</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advance Card with Status Breakdown -->
        <div class="row g-3 mb-2">
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card border-primary" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'advance'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'pending'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'approved'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'complete'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'advance', 'status' => 'cancelled'])) }}"
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
                            <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'settlement'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'pending'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'approved'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'complete'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'settlement', 'status' => 'cancelled'])) }}"
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
                            <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'pending'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'approved'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'complete'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['requirement_type' => 'reimbursement', 'status' => 'cancelled'])) }}"
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
                            <a href="{{ route('expense.index', array_merge(request()->except(['page']))) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['status' => 'pending'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['status' => 'approved'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['status' => 'complete'])) }}"
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
                                <a href="{{ route('expense.index', array_merge(request()->except(['page']), ['status' => 'cancelled'])) }}"
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


        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter My Expenses
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'from_date', 'to_date', 'expense_type']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'from_date', 'to_date', 'expense_type']))
                    <a href="{{ route('expense.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('expense.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                            </option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="complete" {{ request('status') == 'complete' ? 'selected' : '' }}>Completed
                                (Paid)
                            </option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                            </option>
                        </select>
                    </div>

                    <!-- Expense Type Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="expense_type">
                            <option value="">All Expense Types</option>
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
                        <a href="{{ route('expense.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'from_date', 'to_date', 'expense_type']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('expense.index', array_merge(request()->except(['status', 'page']))) }}"
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
                            <a href="{{ route('expense.index', array_merge(request()->except(['expense_type', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ request('from_date') }}
                            <a href="{{ route('expense.index', array_merge(request()->except(['from_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ request('to_date') }}
                            <a href="{{ route('expense.index', array_merge(request()->except(['to_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('expense.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>


        <!-- Expenses Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">My Expense Applications</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ count($expenses) }}
                            </span>
                            <span class="badge bg-primary">
                                Total Amount: ₹{{ number_format($totalAmount ?? 0, 2) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="expenseList">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Date</th>
                                        <th>Expense Code</th>
                                        <th>Requirement Type</th>
                                        <th>Expense Type</th>
                                        <th>Project</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Payment Info</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($expenses as $expense)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ date('d M Y', strtotime($expense->date)) ?? '' }}</td>
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
                                                <span
                                                    class="badge bg-info">{{ $expense->expenseType->name ?? ucfirst($expense->expense_type) }}</span>
                                            </td>
                                            <td>{{ $expense->project_name ?? 'NA' }}</td>
                                            <td>
                                                <span
                                                    class="fw-bold 
                                                @if ($expense->status == 'approved') text-success
                                                @elseif($expense->status == 'pending') text-warning
                                                @elseif($expense->status == 'cancelled') text-danger
                                                @elseif($expense->status == 'complete') text-primary @endif">
                                                    ₹{{ number_format($expense->amount, 2) }}
                                                </span>
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
                                                        <a href="{{ asset($filePath) }}" target="_blank">
                                                            <img src="{{ asset($filePath) }}" alt="Expense File"
                                                                style="width:30px; height:30px; border-radius:50%; object-fit: cover;">
                                                        </a>
                                                    @else
                                                        <a href="{{ asset($filePath) }}" class="btn btn-sm btn-light"
                                                            download>
                                                            <i class="fa fa-download"></i>
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

                                                    $statusText =
                                                        [
                                                            'complete' => 'Completed',
                                                            'cancelled' => 'Cancelled',
                                                            'approved' => 'Approved',
                                                            'pending' => 'Pending',
                                                        ][$expense->status] ?? ucfirst($expense->status);
                                                @endphp
                                                <span class="badge {{ $statusClass }}">
                                                    {{ $statusText }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($expense->status == 'complete' && $expense->payments && $expense->payments->count() > 0)
                                                    @php
                                                        $payment = $expense->payments->first();
                                                        $totalPaid = $expense->payments->sum('amount');
                                                        $paymentCount = $expense->payments->count();
                                                    @endphp
                                                    <button class="payment-info-btn"
                                                        onclick="showPaymentModal({{ $expense->id }})">
                                                        <i class="feather-eye"></i>
                                                        @if ($paymentCount > 1)
                                                            View {{ $paymentCount }} Payments
                                                        @else
                                                            View Payment
                                                        @endif
                                                    </button>
                                                @elseif($expense->status == 'approved' && $expense->requirement_type == 'advance')
                                                    <span class="badge bg-info" style="font-size: 10px;">Awaiting
                                                        Payment</span>
                                                @elseif($expense->status == 'approved' && $expense->requirement_type == 'settlement')
                                                    <span class="badge bg-success" style="font-size: 10px;">Settled</span>
                                                @else
                                                    <span class="text-muted" style="font-size: 10px;">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($expense->status == 'pending')
                                                    <div class="dropdown">
                                                        <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                            data-bs-offset="0,5">
                                                            <i class="feather-more-vertical"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item edit-expense" href="#"
                                                                    data-id="{{ $expense->id }}"
                                                                    data-expense_type="{{ $expense->expense_type }}"
                                                                    data-requirement_type="{{ $expense->requirement_type }}"
                                                                    data-project_id="{{ $expense->project_id }}"
                                                                    data-date="{{ $expense->date }}"
                                                                    data-amount="{{ $expense->amount }}"
                                                                    data-description="{{ $expense->description }}">
                                                                    <i class="feather-edit-3"></i>
                                                                    <span>Edit</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item delete-expense" href="#"
                                                                    data-id="{{ $expense->id }}">
                                                                    <i class="feather-trash-2"></i>
                                                                    <span>Delete</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="fas fa-rupee-sign"></i>
                                                    <h4>No Expense Applications Found</h4>
                                                    <p class="text-muted">You haven't submitted any expense yet</p>
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
    <!-- Add Expense Modal -->
    <div class="modal fade-scale" id="addexpenseModal" tabindex="-1" aria-labelledby="addexpenseModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Add Expense</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form action="{{ route('expense.create') }}" id="addexpenseForm"
                                enctype="multipart/form-data">
                                @csrf
                                <div id="addFormError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="expense_type">Expense Type *</label>
                                            <select class="form-control" name="expense_type" required id="expense_type">
                                                <option value="" disabled selected>-- Select Expense Type --</option>
                                                @foreach ($expenseTypes as $expenseType)
                                                    <option value="{{ $expenseType->id }}">
                                                        {{ $expenseType->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text expense_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="requirement_type">Requirement Type *</label>
                                            <select class="form-control" name="requirement_type" required
                                                id="requirement_type">
                                                <option value="" disabled selected>-- Select Requirement Type --
                                                </option>
                                                <option value="advance">Advance</option>
                                                <option value="settlement">Settlement</option>
                                                <option value="reimbursement">Reimbursement</option>
                                            </select>
                                            <small class="text-danger error-text requirement_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="project_id">Project </label>
                                            <select class="form-control" name="project_id" id="project_id">
                                                <option value="" disabled selected>-- Select Project --</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text project_id_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="date">Date *</label>
                                            <input type="date" class="form-control" name="date" required
                                                id="date">
                                            <small class="text-danger error-text date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="amount">Amount *</label>
                                            <input type="number" step="0.01" class="form-control" name="amount"
                                                id="amount" required placeholder="Enter Amount">
                                            <small class="text-danger error-text amount_error"></small>
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
                                            <label class="fw-semibold" for="description">Description</label>
                                            <textarea class="form-control" name="description" id="description" rows="3"
                                                placeholder="Enter Expense Description..."></textarea>
                                            <small class="text-danger error-text description_error"></small>
                                        </div>
                                    </div>

                                    <!-- Show current balance info for settlement -->
                                    <div class="col-12 mb-3" id="balanceInfo" style="display: none;">
                                        <div class="alert alert-info">
                                            <i class="feather-info"></i>
                                            <strong>Current Advance Balance:</strong>
                                            ₹{{ number_format($currentBalance ?? 0, 2) }}
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Save</button>
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

    <!-- Edit Expense Modal (keep your existing) -->
    <div class="modal fade-scale" id="editexpenseModal" tabindex="-1" aria-labelledby="editexpenseModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Expense</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editexpenseForm" enctype="multipart/form-data">
                                @csrf
                                <div id="editFormError" class="alert alert-danger d-none"></div>
                                <input type="hidden" name="id" id="edit_id">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_expense_type">Expense Type *</label>
                                            <select class="form-control" name="expense_type" required
                                                id="edit_expense_type">
                                                <option value="" disabled selected>-- Select Expense Type --</option>
                                                @foreach ($expenseTypes as $expenseType)
                                                    <option value="{{ $expenseType->id }}">
                                                        {{ $expenseType->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_expense_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_requirement_type">Requirement Type
                                                *</label>
                                            <select class="form-control" name="requirement_type" required
                                                id="edit_requirement_type">
                                                <option value="" disabled selected>-- Select Requirement Type --
                                                </option>
                                                <option value="advance">Advance</option>
                                                <option value="settlement">Settlement</option>
                                                <option value="reimbursement">Reimbursement</option>
                                            </select>
                                            <small class="text-danger error-text edit_requirement_type_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_project_id">Project </label>
                                            <select class="form-control" name="project_id" id="edit_project_id">
                                                <option value="" disabled selected>-- Select Project --</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name ?? '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_project_id_error"></small>
                                        </div>
                                    </div>

                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_date">Date *</label>
                                            <input type="date" class="form-control" name="date" required
                                                id="edit_date">
                                            <small class="text-danger error-text edit_date_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_amount">Amount *</label>
                                            <input type="number" step="0.01" class="form-control" name="amount"
                                                id="edit_amount" required placeholder="Enter Amount">
                                            <small class="text-danger error-text edit_amount_error"></small>
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
                                            <label class="fw-semibold" for="edit_description">Description</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                                placeholder="Enter Expense Description..."></textarea>
                                            <small class="text-danger error-text edit_description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit">Update</button>
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
                    <p class="mt-3">Are you sure you want to delete this expense?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>

    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Payment Details</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>

                <div class="modal-body p-0">
                    <div class="card m-0 border-0">
                        <div class="card-body p-4">
                            <div id="paymentModalContent">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading payment details...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i> Close
                    </button>
                </div> --}}
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

        // Function to show payment modal with details
        function showPaymentModal(expenseId) {
            $('#paymentModal').modal('show');
            $('#paymentModalContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading payment details...</p>
                </div>
            `);

            // Fetch payment details via AJAX
            $.ajax({
                url: "{{ route('expense.payments', '') }}/" + expenseId,
                type: "GET",
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let payments = response.data;
                        let totalPaid = payments.reduce((sum, payment) => sum + parseFloat(payment.amount), 0);
                        let expenseAmount = payments[0]?.expense?.amount || 0;
                        let remaining = expenseAmount - totalPaid;

                        let html = `
                            <div class="mb-4">
                                <div class="alert alert-info">
                                    <div class="row">
                                      
                                        <div class="col-12">
                                            <strong>Total Paid:</strong><br>
                                            <span class="fs-5 fw-bold text-success">₹${formatNumber(totalPaid)}</span>
                                        </div>
                                    </div>
                                 
                                </div>
                            </div>
                            <h6 class="mb-3"><i class="feather-list me-2"></i>Payment Transactions (${payments.length})</h6>
                        `;

                        payments.forEach((payment, index) => {
                            let modeClass = '';
                            switch (payment.payment_mode) {
                                case 'cash':
                                    modeClass = 'mode-cash';
                                    break;
                                case 'bank_transfer':
                                    modeClass = 'mode-bank';
                                    break;
                                case 'cheque':
                                    modeClass = 'mode-cheque';
                                    break;
                                case 'upi':
                                    modeClass = 'mode-upi';
                                    break;
                                default:
                                    modeClass = 'mode-bank';
                            }

                            html += `
                                <div class="payment-modal-detail-row">
                                    <div class="payment-modal-label">Payment #${index + 1}</div>
                                    <div class="payment-modal-value">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>₹${formatNumber(payment.amount)}</strong>
                                            <span class="payment-mode-badge ${modeClass}">
                                                ${payment.payment_mode ? payment.payment_mode.toUpperCase().replace('_', ' ') : 'N/A'}
                                            </span>
                                        </div>
                                        <div class="small text-muted">
                                            <div><i class="feather-calendar me-1"></i> Date: ${formatDate(payment.payment_date)}</div>
                                            ${payment.reference_number ? `<div><i class="feather-hash me-1"></i> Ref: ${payment.reference_number}</div>` : ''}
                                            ${payment.bank_name ? `<div><i class="feather-building me-1"></i> Bank: ${payment.bank_name}</div>` : ''}
                                            ${payment.paid_to ? `<div><i class="feather-user me-1"></i> Paid To: ${payment.paid_to}</div>` : ''}
                                            ${payment.remarks ? `<div><i class="feather-message-square me-1"></i> Remarks: ${payment.remarks}</div>` : ''}
                                            <div><i class="feather-user-check me-1"></i> Processed By: ${payment.payer?.name || 'Finance Team'}</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        $('#paymentModalContent').html(html);
                    } else {
                        $('#paymentModalContent').html(`
                            <div class="text-center py-4">
                                <i class="feather-alert-circle text-muted" style="font-size: 48px;"></i>
                                <p class="mt-3 text-muted">No payment records found for this expense.</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#paymentModalContent').html(`
                        <div class="text-center py-4">
                            <i class="feather-alert-triangle text-danger" style="font-size: 48px;"></i>
                            <p class="mt-3 text-danger">Failed to load payment details. Please try again.</p>
                        </div>
                    `);
                }
            });
        }

        // Helper function to format numbers
        function formatNumber(amount) {
            return parseFloat(amount).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Helper function to format dates
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            let date = new Date(dateString);
            return date.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        $(document).ready(function() {
            // Show balance info when settlement is selected
            $('#requirement_type').on('change', function() {
                if ($(this).val() === 'settlement') {
                    $('#balanceInfo').show();

                    $('#amount').on('input', function() {
                        let amount = parseFloat($(this).val());
                        let balance = {{ $currentBalance ?? 0 }};

                        if (amount > balance) {
                            $(this).addClass('is-invalid');
                            $('.amount_error').text('Amount exceeds available balance of ₹' +
                                balance.toFixed(2));
                        } else {
                            $(this).removeClass('is-invalid');
                            $('.amount_error').text('');
                        }
                    });
                } else {
                    $('#balanceInfo').hide();
                }
            });

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

            // ==================== EDIT EXPENSE FUNCTIONALITY ====================
            $(document).on('click', '.edit-expense', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');
                $('#currentFile').html('');

                $('#edit_id').val($(this).data('id'));
                $('#edit_expense_type').val($(this).data('expense_type'));
                $('#edit_requirement_type').val($(this).data('requirement_type'));
                $('#edit_project_id').val($(this).data('project_id'));
                $('#edit_date').val($(this).data('date'));
                $('#edit_amount').val($(this).data('amount'));
                $('#edit_description').val($(this).data('description'));

                $('#editexpenseModal').modal('show');
            });

            // Edit Expense Form Submission
            $('#editexpenseForm').on('submit', function(e) {
                e.preventDefault();

                $('.error-text').text('');
                $('#editFormError').addClass('d-none').text('');

                const id = $('#edit_id').val();
                let formData = new FormData(this);
                formData.append('_method', 'POST');

                $.ajax({
                    url: "{{ route('expense.update', '') }}/" + id,
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
                            $('#editexpenseModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.edit_' + key + '_error').text(value[0]);
                            });
                        } else if (xhr.status === 400) {
                            toastr.error(xhr.responseJSON.message);
                        } else {
                            $('#editFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // ==================== DELETE EXPENSE FUNCTIONALITY ====================
            let deleteId = null;

            $(document).on('click', '.delete-expense', function(e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                $('#deleteModal').modal('show');
            });

            $('#confirmDelete').on('click', function() {
                if (!deleteId) return;

                $.ajax({
                    url: "{{ route('expense.delete', '') }}/" + deleteId,
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
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        $('#deleteModal').modal('hide');
                    }
                });
            });

            // ==================== ADD EXPENSE FORM SUBMISSION ====================
            $('#addexpenseForm').on('submit', function(e) {
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
                            $('#addexpenseModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, function(key, value) {
                                $('.' + key + '_error').text(value[0]);
                            });
                        } else if (xhr.status === 400) {
                            toastr.error(xhr.responseJSON.message);
                        } else {
                            $('#addFormError')
                                .removeClass('d-none')
                                .text(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    }
                });
            });

            // ==================== MODAL CLEANUP ====================
            $('#addexpenseModal, #editexpenseModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('.error-text').text('');
                $('#addFormError, #editFormError').addClass('d-none').text('');
                $('#currentFile').html('');
                $('#balanceInfo').hide();
                $('.is-invalid').removeClass('is-invalid');
            });
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('expenseList');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length - 1; i++) {
                let headerText = headerCells[i].innerText.trim();
                if (headerText) {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

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

            let csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'my_expenses_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection
