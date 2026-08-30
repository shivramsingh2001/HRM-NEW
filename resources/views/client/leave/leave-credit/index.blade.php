@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .used-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .types-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
        }

        .pending-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
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

        .balance-card .stats-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
        }

        .balance-card .stats-icon-wrapper i {
            color: #10b981;
            font-size: 24px;
        }

        .used-card .stats-icon-wrapper {
            background: rgba(245, 158, 11, 0.1);
        }

        .used-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 24px;
        }

        .types-card .stats-icon-wrapper {
            background: rgba(99, 102, 241, 0.1);
        }

        .types-card .stats-icon-wrapper i {
            color: #6366f1;
            font-size: 24px;
        }

        .pending-card .stats-icon-wrapper {
            background: rgba(239, 68, 68, 0.1);
        }

        .pending-card .stats-icon-wrapper i {
            color: #ef4444;
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

        .filter-input {
            width: 100%;
            height: 36px;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
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
            background: #f1f5f9;
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

        .badge.bg-secondary {
            background: #f1f5f9 !important;
            color: #475569;
        }

        /* ==================== FREQUENCY BADGES ==================== */
        .frequency-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }

        .frequency-weekly {
            background: #e0f2fe;
            color: #0369a1;
        }

        .frequency-monthly {
            background: #d1fae5;
            color: #065f46;
        }

        .frequency-yearly {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .frequency-no {
            background: #f1f5f9;
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

        .action-btn.initialize:hover {
            color: #10b981;
            border-color: #10b981;
        }

        /* ==================== PROCESS CARD ==================== */
        .process-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 1px solid #edf2f7;
            padding: 20px;
            margin-bottom: 24px;
        }

        .process-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .process-title i {
            color: #4f46e5;
            font-size: 18px;
        }

        /* ==================== PAGINATION STYLES ==================== */
        .pagination-wrapper {
            padding: 20px 24px;
            border-top: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination-info strong {
            color: #1e293b;
            font-weight: 600;
        }

        .pagination {
            display: flex;
            gap: 5px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .page-item {
            margin: 0;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }

        .page-item.active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        .page-item.disabled .page-link {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #94a3b8;
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

        /* ==================== EMPLOYEE AVATAR ==================== */
        .avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .avatar-md {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .avatar-title {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg-soft-primary {
            background: rgba(79, 70, 229, 0.1);
            color: #4f46e5;
        }

        .bg-soft-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .bg-soft-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
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
            
            .pagination-wrapper {
                flex-direction: column;
                text-align: center;
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
@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Leave Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Leave Credit Management</li>
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
                    <a href="{{ route('leave-credit.manual.create') }}" class="btn btn-sm btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Manual Credit</span>
                    </a>
                    <a href="{{ route('leave-credit.reports') }}" class="btn btn-sm btn-info">
                        <i class="feather-bar-chart-2 me-2"></i>
                        <span>Reports</span>
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
            <!-- Total Employees Card -->
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $totalUsers }}</div>
                    <div class="stats-label">Total Employees</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $usersWithBalance }}</span>
                        <span class="count-text">With Balance</span>
                    </div>
                </div>
            </div>

            <!-- Total Balance Card -->
            <div class="stats-card balance-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($totalBalance ?? 0, 2) }}</div>
                    <div class="stats-label">Total Leave Balance</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $usersWithBalance }}</span>
                        <span class="count-text">Employees</span>
                    </div>
                </div>
            </div>

            <!-- Total Used Card -->
            <div class="stats-card used-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($totalUsed ?? 0, 2) }}</div>
                    <div class="stats-label">Total Used</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $totalTransactions ?? 0 }}</span>
                        <span class="count-text">Transactions</span>
                    </div>
                </div>
            </div>

            <!-- Leave Types Card -->
            <div class="stats-card types-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $leaveTypes->count() }}</div>
                    <div class="stats-label">Leave Types</div>
                    <div class="stats-count">
                        @php
                            $weeklyCount = $leaveTypes->where('credit_type', 'weekly')->count();
                            $monthlyCount = $leaveTypes->where('credit_type', 'monthly')->count();
                            $yearlyCount = $leaveTypes->where('credit_type', 'yearly')->count();
                        @endphp
                        <span class="count-text">W:{{ $weeklyCount }} M:{{ $monthlyCount }} Y:{{ $yearlyCount }}</span>
                    </div>
                </div>
            </div>

            <!-- Pending Initialization Card -->
            <div class="stats-card pending-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $usersWithoutBalance->count() }}</div>
                    <div class="stats-label">Need Initialization</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $usersWithoutBalance->count() }}</span>
                        <span class="count-text">Employees</span>
                    </div>
                </div>
            </div>
        </div>
        @if($role == "admin" && $role == "manager")
        <!-- Process Credit Form -->
        <div class="process-card">
            <div class="process-title">
                <i class="feather-play-circle"></i>
                Process Auto Credit
            </div>
            <form action="{{ route('leave-credit.process') }}" method="POST" id="processCreditForm">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">Frequency <span class="text-danger">*</span></label> --}}
                            <select name="frequency" class="filter-select" required>
                                <option value="">Select Frequency</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">Credit Date</label> --}}
                            <input type="date" name="credit_date" class="filter-input" value="{{ date('Y-m-d') }}">
                            <small class="text-muted d-block mt-1">Leave empty for current date</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            {{-- <label class="fw-semibold mb-2">&nbsp;</label> --}}
                            <button type="submit" class="apply-btn w-100"
                                onclick="return confirm('Process auto credit for all employees?')">
                                <i class="feather-play"></i> Process Credits
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif

        <!-- All Employees Leave Balance Table with Pagination -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-users me-2"></i>
                            Employee Leave Balances
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-primary">{{ $allUsers->total() }} Total</span>
                            <span class="badge bg-success">{{ $usersWithBalanceCount }} With Balance</span>
                            <span class="badge bg-warning">{{ $usersWithoutBalance->count() }} No Balance</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Joining Date</th>
                                        <th>Current Balance</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allUsers as $user)
                                        @php
                                            $balance = optional($user->leaveBalance)->sum('balance') ?? 0;
                                            $balanceClass =
                                            $balance > 0 ? 'bg-success' : ($balance < 0 ? 'bg-danger'
                                                        : 'bg-secondary');
                                            $hasBalance = $user->leaveBalance !== null;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-md me-2">
                                                        <span
                                                            class="avatar-title {{ $hasBalance ? 'bg-soft-success' : 'bg-soft-warning' }} rounded-circle">
                                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong>{{ $user->name ?? 'N/A' }}
                                                            (<small>{{ $user->employee_id ?? 'N/A' }}</small>)</strong>
                                                        <br>
                                                        <small
                                                            class="text-muted">{{ $user->email ?? '' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($user->joining_date)
                                                    <span class="badge bg-info">
                                                        {{ date('d M Y', strtotime($user->joining_date)) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $balanceClass }}"
                                                    style="font-size: 12px; padding: 6px 12px;">
                                                    <i
                                                        class="fas {{ $balance > 0 ? 'fa-arrow-up' : 'fa-minus' }} me-1"></i>
                                                    {{ number_format($balance, 2) }} days
                                                </span>
                                            </td>
                                            <td>
                                                @if ($hasBalance)
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i> Active
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i> No Balance
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('leave-credit.transactions', $user->id) }}"
                                                    class="action-btn" title="View Transactions">
                                                    <i class="feather-eye"></i>
                                                </a>
                                                @if (!$hasBalance)
                                                    <form action="{{ route('leave-credit.new-joiner', $user->id) }}"
                                                        method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="action-btn initialize"
                                                            onclick="return confirm('Initialize leave balance for this user?')"
                                                            title="Initialize Balance">
                                                            <i class="feather-edit text-primary"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Employees Found</h4>
                                                    <p class="text-muted">No active employees in the system</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination Links -->
                        @if ($allUsers->hasPages())
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    Showing <strong>{{ $allUsers->firstItem() }}</strong> to 
                                    <strong>{{ $allUsers->lastItem() }}</strong> of 
                                    <strong>{{ $allUsers->total() }}</strong> entries
                                </div>
                                
                                <nav aria-label="Employee pagination">
                                    <ul class="pagination">
                                        {{-- Previous Page Link --}}
                                        @if ($allUsers->onFirstPage())
                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $allUsers->previousPageUrl() }}" rel="prev">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        @endif

                                        {{-- Pagination Elements --}}
                                        @foreach ($allUsers->getUrlRange(1, $allUsers->lastPage()) as $page => $url)
                                            @if ($page == $allUsers->currentPage())
                                                <li class="page-item active">
                                                    <span class="page-link">{{ $page }}</span>
                                                </li>
                                            @else
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                                </li>
                                            @endif
                                        @endforeach

                                        {{-- Next Page Link --}}
                                        @if ($allUsers->hasMorePages())
                                            <li class="page-item">
                                                <a class="page-link" href="{{ $allUsers->nextPageUrl() }}" rel="next">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        @else
                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Without Balance Section (if you want to keep it separate) -->
        @if ($usersWithoutBalance->count() > 0)
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="feather-alert-circle me-2"></i>
                                Users Needing Balance Initialization
                            </h5>
                            <span class="badge bg-warning">{{ $usersWithoutBalance->count() }} Pending</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Email</th>
                                            <th>Joining Date</th>
                                            <th>Available Leave Types</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($usersWithoutBalance as $user)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <span class="avatar-title bg-soft-warning rounded-circle">
                                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <strong>{{ $user->name }}</strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $user->email }}</td>
                                                <td>
                                                    @if ($user->joining_date)
                                                        <span class="badge bg-info">
                                                            {{ date('d M Y', strtotime($user->joining_date)) }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @foreach ($leaveTypes as $type)
                                                        <span
                                                            class="frequency-badge frequency-{{ $type->credit_type }} me-1">
                                                            {{ $type->name }}
                                                        </span>
                                                    @endforeach
                                                </td>
                                                <td class="text-center">
                                                    <form action="{{ route('leave-credit.new-joiner', $user->id) }}"
                                                        method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="action-btn initialize"
                                                            onclick="return confirm('Initialize leave balance for this user?')"
                                                            title="Initialize">
                                                            <i class="feather-edit"></i>
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('leave-credit.transactions', $user->id) }}"
                                                        class="action-btn" title="View Details">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Recent Credit Transactions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-clock me-2"></i>
                            Recent Credit Transactions
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $recentTransactions->count() }}
                            </span>
                            <span class="badge bg-primary">
                                <i class="feather-calendar me-1"></i>Last 10
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Frequency</th>
                                        <th>Credited</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Remarks</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentTransactions as $transaction)
                                        <tr>
                                            <td>
                                                <span
                                                    class="fw-semibold">{{ $transaction->created_at->format('d M Y') }}</span>
                                                <br>
                                                <small
                                                    class="text-muted">{{ $transaction->created_at->format('H:i') }}</small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-md me-2">
                                                        <span class="avatar-title bg-soft-primary rounded-circle">
                                                            {{ strtoupper(substr($transaction->user->name ?? 'NA', 0, 2)) }}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <strong>{{ $transaction->user->name ?? 'N/A' }}
                                                            (<small>{{ $transaction->user->employee_id ?? 'N/A' }}</small>)</strong>
                                                        <br>
                                                        <small
                                                            class="text-muted">{{ $transaction->user->email ?? '' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-purple">{{ $transaction->leaveType->name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                @if ($transaction->leaveType)
                                                    <span
                                                        class="frequency-badge frequency-{{ $transaction->leaveType->credit_type }}">
                                                        {{ ucfirst($transaction->leaveType->credit_type) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-success">+{{ number_format($transaction->total_leaves, 2) }}</span>
                                            </td>
                                            <td>{{ number_format($transaction->before_leaves, 2) }}</td>
                                            <td><strong>{{ number_format($transaction->after_leaves, 2) }}</strong></td>
                                            <td>
                                                <span class="text-muted"
                                                    style="font-size: 11px;">{{ $transaction->remarks }}</span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('leave-credit.transactions', $transaction->user_id) }}"
                                                    class="action-btn" title="View Details">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Transactions Found</h4>
                                                    <p class="text-muted">No leave credit transactions have been recorded
                                                        yet</p>
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
            // Handle form submission with AJAX
            $('#processCreditForm').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var url = form.attr('action');
                var data = form.serialize();

                // Show loading state
                var submitBtn = form.find('button[type="submit"]');
                var originalText = submitBtn.html();
                submitBtn.html('<i class="feather-loader me-2"></i> Processing...').prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            toastr.error(response.message);
                            submitBtn.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'An error occurred while processing';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        toastr.error(errorMessage);
                        submitBtn.html(originalText).prop('disabled', false);
                    }
                });
            });
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.querySelector('.table:last-of-type');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length; i++) {
                let headerText = headerCells[i].innerText.trim();
                if (headerText && headerText !== 'Action') {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length - 1; j++) { // Exclude last column (Action)
                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                    // Clean up the text (remove extra spaces, newlines)
                    cellText = cellText.replace(/\s+/g, ' ').trim();
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
            downloadLink.download = 'leave_credits_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection