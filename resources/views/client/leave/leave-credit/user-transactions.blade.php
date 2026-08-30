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
        .total-credit-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .total-used-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .transactions-card::before {
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


        /* Card-specific icon backgrounds */
        .total-credit-card .stats-icon-wrapper {
            background: rgba(79, 70, 229, 0.1);
        }

        .total-credit-card .stats-icon-wrapper i {
            color: #4f46e5;
            font-size: 24px;
        }

        .total-used-card .stats-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
        }

        .total-used-card .stats-icon-wrapper i {
            color: #10b981;
            font-size: 24px;
        }

        .balance-card .stats-icon-wrapper {
            background: rgba(245, 158, 11, 0.1);
        }

        .balance-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 24px;
        }

        .transactions-card .stats-icon-wrapper {
            background: rgba(99, 102, 241, 0.1);
        }

        .transactions-card .stats-icon-wrapper i {
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

        .count-number {
            font-size: 11px;
            font-weight: 600;
        }

        .count-text {
            font-size: 11px;
            color: #64748b;
            font-weight: 400;
        }

        /* ==================== PROFILE HEADER ==================== */
        .profile-header {
            background: white;
            border-radius: 20px;
            border: 1px solid #edf2f7;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            color: #64748b;
            font-size: 14px;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .profile-meta-item i {
            color: #4f46e5;
            font-size: 14px;
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

        /* ==================== BALANCE SUMMARY TABLE ==================== */
        .balance-summary-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .balance-summary-header {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .balance-summary-header i {
            width: 40px;
            height: 40px;
            background: #4f46e5;
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .balance-summary-header h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .balance-summary-header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            padding: 20px 24px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h5 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h5 i {
            color: #4f46e5;
            font-size: 20px;
        }

        .table-badge {
            background: #e0e7ff;
            color: #4f46e5;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-responsive {
            padding: 0 24px 24px 24px;
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

        .badge-credit {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-debit {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-purple {
            background: #e0e7ff;
            color: #4f46e5;
        }

        /* ==================== AMOUNT STYLES ==================== */
        .amount-credit {
            color: #059669;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .amount-debit {
            color: #dc2626;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .amount-before {
            color: #64748b;
            font-weight: 500;
        }

        .amount-after {
            color: #1e293b;
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

        /* ==================== ACTION BUTTONS ==================== */
        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-back {
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateX(-3px);
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-2px);
        }

        /* ==================== REMARKS CELL ==================== */
        .remarks-cell {
            max-width: 250px;
            color: #64748b;
            font-size: 12px;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                padding: 0 16px 16px 16px;
                overflow-x: auto;
            }

            .table td,
            .table th {
                white-space: nowrap;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Employee Transactions</h5>
            </div>
            <ul class="breadcrumb">
                {{-- <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('leave-credit.index') }}">Leave Credit</a></li> --}}
                <li class="breadcrumb-item"><a href="{{ route('leave-credit.reports') }}">Reports</a></li>
                <li class="breadcrumb-item active">Transactions</li>
            </ul>
        </div>
        {{-- <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="{{ route('leave-credit.reports') }}" class="btn-action btn-back">
                    <i class="feather-arrow-left"></i>
                    Back to Reports
                </a>
                <button onclick="window.print()" class="btn-action btn-primary">
                    <i class="feather-printer"></i>
                    Print Statement
                </button>
            </div>
        </div>
    </div> --}}
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <!-- Stats Cards -->
        @php
            $totalCredited = $balanceByType->sum('total_credited');
            $totalUsed = $balanceByType->sum('total_used');
            $currentBalance = $balanceByType->sum('balance');
        @endphp

        <div class="stats-grid">
            <!-- Total Credited Card -->
            <div class="stats-card total-credit-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($totalCredited, 2) }} days</div>
                    <div class="stats-label">Total Credited</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $transactions->where('transaction_type', 'add')->count() }}</span>
                        <span class="count-text">Transactions</span>
                    </div>
                </div>
            </div>

            <!-- Total Used Card -->
            <div class="stats-card total-used-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($totalUsed, 2) }} days</div>
                    <div class="stats-label">Total Used</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $transactions->where('transaction_type', 'sub')->count() }}</span>
                        <span class="count-text">Transactions</span>
                    </div>
                </div>
            </div>

            <!-- Current Balance Card -->
            <div class="stats-card balance-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ number_format($currentBalance, 2) }} days</div>
                    <div class="stats-label">Current Balance</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $balanceByType->count() }}</span>
                        <span class="count-text">Leave Types</span>
                    </div>
                </div>
            </div>

            <!-- Total Transactions Card -->
            <div class="stats-card transactions-card">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $transactions->count() }}</div>
                    <div class="stats-label">Total Transactions</div>
                    <div class="stats-count">
                        <span class="count-number">{{ $transactions->count() }}</span>
                        <span class="count-text">Records</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div class="profile-info">
                <div class="profile-name">{{ $user->name }}</div>
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <i class="fas fa-envelope"></i>
                        {{ $user->email }}
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        Joined: {{ $user->joining_date ? date('d M Y', strtotime($user->joining_date)) : 'N/A' }}
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-id-card"></i>
                        ID: {{ $user->employee_id ?? 'N/A' }}
                    </div>
                </div>
            </div>
        </div>


        <!-- Balance Summary by Leave Type -->
        <div class="balance-summary-card">
            <div class="balance-summary-header">
                <i class="fas fa-chart-pie"></i>
                <div>
                    <h5>Balance Summary by Leave Type</h5>
                    <p>Detailed breakdown of leave balances</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Total Credited</th>
                            <th>Total Used</th>
                            <th>Available Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($balanceByType as $item)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        {{-- <span class="badge-purple badge me-2">
                                        {{ substr($item['type'], 0, 2) }}
                                    </span> --}}
                                        <strong>{{ $item['type'] }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount-credit">
                                        <i class="fas fa-arrow-down"></i>
                                        {{ number_format($item['total_credited'], 2) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="amount-debit">
                                        <i class="fas fa-arrow-up"></i>
                                        {{ number_format($item['total_used'], 2) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold {{ $item['balance'] > 0 ? 'text-success' : 'text-muted' }}">
                                        {{ number_format($item['balance'], 2) }} days
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-chart-bar"></i>
                                        <h4>No Balance Data</h4>
                                        <p class="text-muted">No leave balance records found for this employee</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction History -->
        <!-- Transaction History -->
        <div class="table-card">
            <div class="table-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Transaction History
                </h5>
                <span class="table-badge">{{ $transactions->total() }} Records</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Leave Type</th>
                            <th>Amount</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $transaction->created_at->format('d M Y') }}</span>
                                    <br>
                                    <small class="text-muted">{{ $transaction->created_at->format('h:i A') }}</small>
                                </td>
                                <td>
                                    @if ($transaction->transaction_type == 'add')
                                        <span class="badge badge-credit">
                                            <i class="fas fa-plus-circle"></i>
                                            Credit
                                        </span>
                                    @else
                                        <span class="badge badge-debit">
                                            <i class="fas fa-minus-circle"></i>
                                            Debit
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-purple badge">
                                        {{ $transaction->leaveType->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($transaction->transaction_type == 'add')
                                        <span class="amount-credit">
                                            <i class="fas fa-plus"></i>
                                            {{ number_format($transaction->total_leaves, 2) }}
                                        </span>
                                    @else
                                        <span class="amount-debit">
                                            <i class="fas fa-minus"></i>
                                            {{ number_format($transaction->total_leaves, 2) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="amount-before">{{ number_format($transaction->before_leaves, 2) }}</td>
                                <td class="amount-after">{{ number_format($transaction->after_leaves, 2) }}</td>
                                <td class="remarks-cell">
                                    @if ($transaction->remarks)
                                        <i class="fas fa-quote-left text-muted me-1"></i>
                                        {{ Str::limit($transaction->remarks, 40) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h4>No Transactions Found</h4>
                                        <p class="text-muted">No transaction history available for this employee</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Laravel Pagination -->
            @if ($transactions->hasPages())
                <div class="card-footer border-top-0">
                    <div class="d-flex justify-content-between align-items-center py-3 px-3">
                        <div class="text-muted small">
                            Showing <strong>{{ $transactions->firstItem() }}</strong> to
                            <strong>{{ $transactions->lastItem() }}</strong>
                            of <strong>{{ $transactions->total() }}</strong> entries
                        </div>
                        <div class="remove-internal-para">
                            {{ $transactions->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            @endif
            
        </div>
    </div>
@endsection

@section('scripts')
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {

            // Export to CSV function
            window.exportToCSV = function() {
                var csv = [];
                var rows = $('#transactionsTable').find('tr');

                rows.each(function() {
                    var row = [];
                    $(this).find('td, th').each(function() {
                        var text = $(this).text().trim();
                        row.push('"' + text.replace(/"/g, '""') + '"');
                    });
                    csv.push(row.join(','));
                });

                var csvFile = new Blob([csv.join('\n')], {
                    type: 'text/csv'
                });
                var downloadLink = document.createElement('a');
                downloadLink.download = 'employee_transactions_{{ $user->id }}_{{ date('Y-m-d') }}.csv';
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.click();

                toastr.success('CSV exported successfully');
            };
        });
    </script>
@endsection
