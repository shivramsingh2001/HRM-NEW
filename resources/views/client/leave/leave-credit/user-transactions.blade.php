@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STATS CARDS — same anatomy as the dashboard's
           Total Employees KPI card ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }

        /* .kpi5-* is centralized in client.layout.head — no local copy. */

        /* ==================== PROFILE HEADER ==================== */
        .profile-header {
            background: white;
            border-radius: 12px;
            border: 1px solid #eaeef5;
            padding: 12px 14px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            color: white;
            flex: none;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 12px;
            font-weight: 700;
            color: #1a2236;
            margin-bottom: 2px;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: #6b7385;
            font-size: 10.5px;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .profile-meta-item i {
            color: #1e3a8a;
            font-size: 10.5px;
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
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-title i {
            color: #1e3a8a;
            font-size: 12px;
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
            border-radius: 12px;
            border: 1px solid #eaeef5;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .balance-summary-header {
            background: #f7f8fb;
            padding: 10px 14px;
            border-bottom: 1px solid #eaeef5;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .balance-summary-header i {
            width: 28px;
            height: 28px;
            background: #1e3a8a;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            flex: none;
        }

        .balance-summary-header h5 {
            margin: 0;
            font-size: 11.5px;
            font-weight: 700;
            color: #1a2236;
        }

        .balance-summary-header p {
            margin: 2px 0 0;
            font-size: 10.5px;
            color: #6b7385;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #eaeef5;
            overflow: hidden;
        }

        .table-header {
            background: #f7f8fb;
            padding: 10px 14px;
            border-bottom: 1px solid #eaeef5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-header h5 {
            margin: 0;
            font-size: 11.5px;
            font-weight: 700;
            color: #1a2236;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .table-header h5 i {
            color: #1e3a8a;
            font-size: 11.5px;
        }

        .table-badge {
            background: #e3edfe;
            color: #1e3a8a;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }

        .table-responsive {
            padding: 0 14px 14px 14px;
        }

        /* Table font/spacing is centralized in client.layout.head — no local copy. */


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
            background: #e3edfe;
            color: #1d4ed8;
        }

        .badge-debit {
            background: #e3edfe;
            color: #1e3a8a;
        }

        .badge-purple {
            background: #e0e7ff;
            color: #1e3a8a;
        }

        /* ==================== AMOUNT STYLES ==================== */
        .amount-credit {
            color: #1d4ed8;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .amount-debit {
            color: #1d4ed8;
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
            font-size: 11.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 20px;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .btn-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 11.5px;
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
            background: #1e3a8a;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
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
    <x-ui.page-header title="Employee Transactions" :parent="['label' => 'Reports', 'route' => 'leave-credit.reports']" />
    </div>

    <div class="main-content" style="padding: 18px !important;">
        <!-- Stats Cards -->
        @php
            $totalCredited = $balanceByType->sum('total_credited');
            $totalUsed = $balanceByType->sum('total_used');
            $currentBalance = $balanceByType->sum('balance');
        @endphp

        <div class="stats-grid">
            <!-- Total Credited Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-arrow-down"></i></span>
                    <span class="kpi5-pill">{{ $transactions->where('transaction_type', 'add')->count() }} txns</span>
                </div>
                <div class="kpi5-value">{{ number_format($totalCredited, 1) }}</div>
                <div class="kpi5-label">Total Credited (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalUsed, 1) }}</span><span class="l">Used</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($currentBalance, 1) }}</span><span class="l">Balance</span></div>
                </div>
            </div>

            <!-- Total Used Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-arrow-up"></i></span>
                    <span class="kpi5-pill">{{ $transactions->where('transaction_type', 'sub')->count() }} txns</span>
                </div>
                <div class="kpi5-value">{{ number_format($totalUsed, 1) }}</div>
                <div class="kpi5-label">Total Used (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalCredited, 1) }}</span><span class="l">Credited</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($currentBalance, 1) }}</span><span class="l">Balance</span></div>
                </div>
            </div>

            <!-- Current Balance Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-wallet"></i></span>
                    <span class="kpi5-pill">{{ $balanceByType->count() }} types</span>
                </div>
                <div class="kpi5-value">{{ number_format($currentBalance, 1) }}</div>
                <div class="kpi5-label">Current Balance (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalCredited, 1) }}</span><span class="l">Credited</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalUsed, 1) }}</span><span class="l">Used</span></div>
                </div>
            </div>

            <!-- Total Transactions Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-exchange-alt"></i></span>
                    <span class="kpi5-pill">Records</span>
                </div>
                <div class="kpi5-value">{{ $transactions->count() }}</div>
                <div class="kpi5-label">Total Transactions</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $transactions->where('transaction_type', 'add')->count() }}</span><span class="l">Credits</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $transactions->where('transaction_type', 'sub')->count() }}</span><span class="l">Debits</span></div>
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
