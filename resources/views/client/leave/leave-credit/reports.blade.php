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
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .types-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .transactions-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .average-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
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

        .types-card .stats-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
        }

        .types-card .stats-icon-wrapper i {
            color: #10b981;
            font-size: 24px;
        }

        .transactions-card .stats-icon-wrapper {
            background: rgba(245, 158, 11, 0.1);
        }

        .transactions-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 24px;
        }

        .average-card .stats-icon-wrapper {
            background: rgba(99, 102, 241, 0.1);
        }

        .average-card .stats-icon-wrapper i {
            color: #6366f1;
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
                grid-template-columns: repeat(2, 1fr);
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

        /* Credit Badge */
        .credit-badge {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .credit-badge i {
            font-size: 11px;
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

        /* ==================== AMOUNT CELLS ==================== */
        .amount-cell {
            font-weight: 600;
        }

        .amount-before {
            color: #64748b;
        }

        .amount-after {
            color: #059669;
            font-weight: 700;
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

        /* ==================== PAGE HEADER ==================== */
      

        .page-header-left {
            gap: 16px;
        }

        .page-header-title h5 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
        }

        .breadcrumb-item {
            font-size: 13px;
        }

        .breadcrumb-item a {
            color: #64748b;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #4f46e5;
            font-weight: 500;
        }

        .page-header-right-items-wrapper {
            gap: 12px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-icon:hover {
            background: #f8fafc;
            color: #4f46e5;
            border-color: #4f46e5;
        }

        .btn-light-brand {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .btn-primary {
            background: #4f46e5;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        .btn-info {
            background: #e0e7ff;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            color: #4f46e5;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-info:hover {
            background: #c7d2fe;
            transform: translateY(-1px);
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

            .page-header-right-items-wrapper {
                flex-wrap: wrap;
            }
        }

        /* ==================== REMARKS CELL ==================== */
        .remarks-cell {
            max-width: 200px;
            color: #64748b;
            font-size: 12px;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Leave Credit Reports</h5>
            </div>
            <ul class="breadcrumb">

                <li class="breadcrumb-item"><a href="{{ route('leave-credit.index') }}">Leave Credit</a></li>
                <li class="breadcrumb-item active">Reports</li>
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
            <!-- Total Credits Card -->
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($totalCreditedAmount, 2) }} days</div>
                    <div class="stats-label">Total Credits</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $totalTransactionsCount }}</span>
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
                    <div class="stats-amount-main">{{ $summaryByType->count() }}</div>
                    <div class="stats-label">Leave Types</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $summaryByType->count() }}</span>
                        <span class="count-text">Active</span>
                    </div>
                </div>
            </div>

            <!-- Total Transactions Card -->
            <div class="stats-card transactions-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $totalTransactionsCount }}</div>
                    <div class="stats-label">Transactions</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $totalTransactionsCount }}</span>
                        <span class="count-text">Records</span>
                    </div>
                </div>
            </div>

            <!-- Average Credit Card -->
            <div class="stats-card average-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">
                        {{ number_format($averageCreditAmount, 2) }} days
                    </div>
                    <div class="stats-label">Average Credit</div>
                    <div class="stats-count">
                        <span class="count-number">Per Transaction</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary by Leave Type Table (uses $summaryByType) -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-pie-chart me-2"></i>
                            Summary by Leave Type
                        </h5>
                        <span class="badge bg-purple">{{ $summaryByType->count() }} Types</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Total Credited</th>
                                        <th>Transactions</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($summaryByType as $summary)
                                        <tr>
                                            <td>
                                                <strong>{{ $summary['type'] }}</strong>
                                            </td>
                                            <td>
                                                <span class="credit-badge">
                                                    <i class="feather-calendar"></i>
                                                    {{ number_format($summary['total_credited'], 2) }} days
                                                </span>
                                            </td>
                                            <td>{{ $summary['count'] }}</td>
                                            <td class="amount-cell">
                                                {{ $summary['count'] > 0 ? number_format($summary['total_credited'] / $summary['count'], 2) : 0 }}
                                                days
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-pie-chart"></i>
                                                    <h4>No Summary Data</h4>
                                                    <p class="text-muted">No leave credit transactions found</p>
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

        <!-- Detailed Transactions Table (uses $paginatedTransactions) -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-list me-2"></i>
                            Detailed Transactions
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">{{ $paginatedTransactions->total() }} Records</span>
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
                                        <th>Credited</th>
                                        <th>Before</th>
                                        <th>After</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($paginatedTransactions as $transaction)
                                        <tr>
                                            <td>
                                                <span
                                                    class="fw-semibold">{{ $transaction->created_at->format('d M Y') }}</span>
                                                <br>
                                                <small
                                                    class="text-muted">{{ $transaction->created_at->format('h:i A') }}</small>
                                            </td>
                                            <td>
                                                <a class="employee-info"
                                                    href="{{ route('leave-credit.transactions', $transaction->user_id) }}">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($transaction->user->name ?? 'U', 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ $transaction->user->name ?? 'N/A' }}
                                                            <small
                                                                class="text-muted fs-11">({{ $transaction->user->employee_id ?? 'N/A' }})</small>
                                                        </div>
                                                        <div class="employee-email">{{ $transaction->user->email ?? '' }}
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-purple">{{ $transaction->leaveType->name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                <span class="credit-badge">
                                                    <i class="feather-plus-circle"></i>
                                                    +{{ number_format($transaction->total_leaves, 2) }}
                                                </span>
                                            </td>
                                            <td class="amount-cell amount-before">
                                                {{ number_format($transaction->before_leaves, 2) }}</td>
                                            <td class="amount-cell amount-after">
                                                {{ number_format($transaction->after_leaves, 2) }}</td>
                                            <td class="remarks-cell">
                                                @if ($transaction->remarks)
                                                    <i class="feather-message-square text-muted me-1"
                                                        style="font-size: 11px;"></i>
                                                    {{ Str::limit($transaction->remarks, 30) }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Transactions Found</h4>
                                                    <p class="text-muted">No leave credit transactions match your criteria
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Links -->
                        @if ($paginatedTransactions->hasPages())
                            <div class="card-footer border-top-0 p-0 m-0">
                                <div class="d-flex justify-content-between align-items-center py-3 px-3">
                                    <div class="text-muted small">
                                        Showing <strong>{{ $paginatedTransactions->firstItem() }}</strong> to
                                        <strong>{{ $paginatedTransactions->lastItem() }}</strong>
                                        of <strong>{{ $paginatedTransactions->total() }}</strong> entries
                                    </div>
                                    <div class="remove-internal-para">
                                        {{ $paginatedTransactions->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit on filter change (optional)
        $('.filter-select').on('change', function() {
            if ($(this).closest('form').find('button[type="submit"]').length) {
                // Uncomment below if you want auto-submit on change
                // $(this).closest('form').submit();
            }
        });
    </script>
@endsection
