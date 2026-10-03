@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STATS CARDS — same anatomy as the dashboard's
           Total Employees KPI card: icon + pill, bold value + label,
           divider, 2 footer stats ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }

        /* .kpi5-* is centralized in client.layout.head — no local copy. */

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
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .clear-all-link i {
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

        .apply-btn {
            height: 36px;
            padding: 0 16px;
            background: #0D6EFD;
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
            background: #0D6EFD;
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
            color: var(--icon-color, #0D6EFD);
            font-size: 11px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
        }

        .filter-tag .remove-tag:hover {
            color: #0D6EFD;
        }

        .filter-tag.clear-all {
            background: #EFF6FF;
            border-color: #0D6EFD;
            color: #0D6EFD;
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
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
            background: #EFF6FF !important;
            color: #0B5ED7;
        }

        .badge.bg-danger {
            background: #EFF6FF !important;
            color: #0D6EFD;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-warning {
            background: #EFF6FF !important;
            color: #0D6EFD;
        }

        .badge.bg-purple {
            background: #e0e7ff !important;
            color: #0D6EFD;
        }

        /* Credit Badge */
        .credit-badge {
            background: #EFF6FF;
            color: #0B5ED7;
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
            background: #0D6EFD;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
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
            font-size: 12px;
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
            color: #0B5ED7;
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

        /* ==================== PAGE HEADER ==================== */
      

        .page-header-left {
            gap: 16px;
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
            color: var(--icon-color, #0D6EFD);
            border-color: #0D6EFD;
        }

        .btn-light-brand {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .btn-primary {
            background: #0D6EFD;
            border: none;
            padding: 8px 16px;
            font-size: 11.5px;
            font-weight: 500;
            border-radius: 8px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #0D6EFD;
            transform: translateY(-1px);
        }

        .btn-info {
            background: #e0e7ff;
            border: none;
            padding: 8px 16px;
            font-size: 11.5px;
            font-weight: 500;
            border-radius: 8px;
            color: #0D6EFD;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-info:hover {
            background: #93c5fd;
            transform: translateY(-1px);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {



            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {





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

        /* Table font/spacing is centralized in client.layout.head — no local copy. */
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Leave Credit Reports" :parent="['label' => 'Leave Credit', 'route' => 'leave-credit.index']" />

    <div class="main-content" style="padding: 18px !important;">
        <!-- Filters -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    <span>Filters</span>
                </div>
                <a href="{{ route('leave-credit.reports') }}" class="clear-all-link">
                    <i class="feather-x-circle"></i> Clear all
                </a>
            </div>
            <form method="GET" action="{{ route('leave-credit.reports') }}" class="filter-row">
                <div class="filter-item">
                    <select name="month" class="filter-select" onchange="this.form.submit()">
                        <option value="all" {{ $month == 'all' ? 'selected' : '' }}>All Months</option>
                        @foreach ($months as $num => $name)
                            <option value="{{ $num }}" {{ (string) $month === (string) $num ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <select name="year" class="filter-select" onchange="this.form.submit()">
                        @for ($y = now()->year; $y >= now()->year - 4; $y--)
                            <option value="{{ $y }}" {{ (string) $year === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="filter-item" style="min-width: 200px;">
                    <select name="user_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Employees</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" {{ (string) $userId === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="apply-btn">
                    <i class="feather-check"></i> Apply
                </button>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- Total Credits Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-coins"></i></span>
                    <span class="kpi5-pill">{{ $totalTransactionsCount }} txns</span>
                </div>
                <div class="kpi5-value">{{ number_format($totalCreditedAmount, 2) }}</div>
                <div class="kpi5-label">Total Credits (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $summaryByType->count() }}</span><span class="l">Types</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($averageCreditAmount, 1) }}</span><span class="l">Avg/Txn</span></div>
                </div>
            </div>

            <!-- Leave Types Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-tags"></i></span>
                    <span class="kpi5-pill">Active</span>
                </div>
                <div class="kpi5-value">{{ $summaryByType->count() }}</div>
                <div class="kpi5-label">Leave Types</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $totalTransactionsCount }}</span><span class="l">Txns</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalCreditedAmount, 1) }}</span><span class="l">Total Days</span></div>
                </div>
            </div>

            <!-- Total Transactions Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-exchange-alt"></i></span>
                    <span class="kpi5-pill">Records</span>
                </div>
                <div class="kpi5-value">{{ $totalTransactionsCount }}</div>
                <div class="kpi5-label">Transactions</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $summaryByType->count() }}</span><span class="l">Types</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($averageCreditAmount, 1) }}</span><span class="l">Avg Days</span></div>
                </div>
            </div>

            <!-- Average Credit Card -->
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="fas fa-chart-line"></i></span>
                    <span class="kpi5-pill">Per Txn</span>
                </div>
                <div class="kpi5-value">{{ number_format($averageCreditAmount, 2) }}</div>
                <div class="kpi5-label">Average Credit (days)</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $totalTransactionsCount }}</span><span class="l">Txns</span></div>
                    <div class="kpi5-stat"><span class="n">{{ number_format($totalCreditedAmount, 1) }}</span><span class="l">Total Days</span></div>
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
