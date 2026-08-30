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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        }

        .stats-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .total-card .stats-icon-wrapper {
            background: rgba(79, 70, 229, 0.1);
        }
        .total-card .stats-icon-wrapper i {
            color: #4f46e5;
            font-size: 24px;
        }

        .stats-content {
            flex: 1;
        }

        .stats-amount-main {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stats-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 6px;
        }

        /* Filter Section */
        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 16px 20px;
            margin-bottom: 24px;
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
        }

        .filter-title span {
            background: #eef2ff;
            color: #4f46e5;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
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

        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
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
        }

        .reset-btn:hover {
            background: #f8fafc;
        }

        /* Active Filters */
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
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            cursor: pointer;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
        }

        /* Table Styles */
        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            padding: 12px 16px;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
            padding: 12px 16px;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        /* Action Buttons */
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
        }

        .action-btn:hover {
            background: white;
            color: #4f46e5;
            border-color: #4f46e5;
        }

        /* Empty State */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-row {
                flex-direction: column;
            }
            .filter-item {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
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
                <h5 class="m-b-10">My Salary Slips</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Salary Slips</li>
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
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">₹{{ number_format($summary['total_earned'] ?? 0, 2) }}</div>
                    <div class="stats-label">Total Earnings</div>
                </div>
            </div>

            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-file-text"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $summary['total_slips'] ?? 0 }}</div>
                    <div class="stats-label">Total Salary Slips</div>
                </div>
            </div>

            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-trending-up"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">₹{{ number_format($summary['average_salary'] ?? 0, 2) }}</div>
                    <div class="stats-label">Average Salary</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Salary Slips
                    @php
                        $activeFilterCount = collect(request()->only(['year', 'month']))->filter()->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['year', 'month']))
                    <a href="{{ route('my-payroll.my-salary-slips') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="year" class="filter-select">
                            <option value="">All Years</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-item">
                        <select name="month" class="filter-select">
                            <option value="">All Months</option>
                            @foreach($months as $key => $month)
                                <option value="{{ $key }}" {{ request('month') == $key ? 'selected' : '' }}>
                                    {{ $month }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!--<div class="filter-item" style="min-width: auto;">-->
                    <!--    <button type="submit" class="apply-btn">-->
                    <!--        <i class="feather-search"></i>-->
                    <!--        Apply-->
                    <!--    </button>-->
                    <!--</div>-->

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('my-payroll.my-salary-slips') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['year', 'month']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>
                    
                    @if (request('year'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            Year: {{ request('year') }}
                            <a href="{{ route('my-payroll.my-salary-slips', array_merge(request()->except(['year', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('month'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            Month: {{ $months[request('month')] ?? request('month') }}
                            <a href="{{ route('my-payroll.my-salary-slips', array_merge(request()->except(['month', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif
                </div>
            @endif
        </div>

        <!-- Salary Slips Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Salary Slips</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $salarySlips->total() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="salarySlipsTable">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Month/Year</th>
                                        <th>Gross Salary</th>
                                        <th>Total Deductions</th>
                                        <th>Net Payable</th>
                                        <th>Payment Status</th>
                                        <th>Payment Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($salarySlips as $index => $slip)
                                        <tr data-slip-id="{{ $slip->id }}"
                                            data-month="{{ $slip->payroll_month }}"
                                            data-gross="{{ $slip->gross_earnings }}"
                                            data-net="{{ $slip->net_payable }}"
                                            data-status="{{ $slip->payment_status }}">
                                            <td>{{ $salarySlips->firstItem() + $index }}</td>
                                            <td>
                                                <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $slip->payroll_month)->format('F Y') }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $slip->payroll_month }}</small>
                                             </div>
                                            </td>
                                            <td>₹ {{ number_format($slip->gross_earnings, 2) }}</div>
                                            </td>
                                            <td>₹ {{ number_format($slip->total_deductions, 2) }}</div>
                                            </td>
                                            <td class="fw-bold text-success">₹ {{ number_format($slip->net_payable, 2) }}</div>
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = [
                                                        'paid' => 'success',
                                                        'processed' => 'info',
                                                        'pending' => 'warning',
                                                        'cancelled' => 'danger'
                                                    ][$slip->payment_status] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-{{ $statusClass }}">
                                                    {{ ucfirst($slip->payment_status) }}
                                                </span>
                                             </div>
                                            </td>
                                            <td>
                                                {{ $slip->payment_date ? \Carbon\Carbon::parse($slip->payment_date)->format('d M Y') : 'N/A' }}
                                             </div>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <a href="{{ route('my-payroll.view-salary-slip', $slip->id) }}" 
                                                       class="action-btn" 
                                                       target="_blank"
                                                       title="View Slip">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                    <a href="{{ route('my-payroll.download-salary-slip', $slip->id) }}" 
                                                       class="action-btn" 
                                                       title="Download PDF">
                                                        <i class="feather-download"></i>
                                                    </a>
                                                </div>
                                             </div>
                                         </div>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-file-text"></i>
                                                    <h4>No Salary Slips Found</h4>
                                                    <p class="text-muted">No salary slips available for the selected filters.</p>
                                                    <p class="text-muted small">Salary slips are available only for processed or paid payrolls.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if (method_exists($salarySlips, 'links') && $salarySlips->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $salarySlips->firstItem() }} to {{ $salarySlips->lastItem() }} of
                                    {{ $salarySlips->total() }} entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $salarySlips->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });
        });

        // Export to CSV
        function exportToCSV() {
            let rows = document.querySelectorAll('#salarySlipsTable tbody tr');
            if (!rows.length || (rows.length === 1 && rows[0].querySelector('.empty-state'))) {
                toastr.error('No data to export');
                return;
            }

            let csv = [];
            // Headers
            csv.push(['S.No', 'Month/Year', 'Gross Salary', 'Total Deductions', 'Net Payable', 'Payment Status', 'Payment Date'].map(h => '"' + h + '"').join(','));

            // Data rows
            rows.forEach((row, index) => {
                let cols = row.querySelectorAll('td');
                if (cols.length >= 7) {
                    let rowData = [
                        index + 1,
                        cols[1]?.innerText.trim() || '',
                        cols[2]?.innerText.trim() || '',
                        cols[3]?.innerText.trim() || '',
                        cols[4]?.innerText.trim() || '',
                        cols[5]?.innerText.trim() || '',
                        cols[6]?.innerText.trim() || ''
                    ];
                    csv.push(rowData.map(cell => '"' + String(cell).replace(/"/g, '""') + '"').join(','));
                }
            });

            let csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'salary_slips_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }
    </script>
@endsection