@extends('client.layout.master')

@section('title', 'Loan Approvals')

@section('style')
    <style>
        /* .stats-grid/.stats-card/.stats-icon-wrapper/.stats-content/.stats-amount-main/.stats-label
           are centralized in client.layout.head (single blue-only theme) — no local copy. */

        /* ==================== FILTER SECTION ==================== */
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
            color: var(--primary-mid);
        }

        .filter-title span {
            background: var(--primary-light);
            color: var(--primary-mid);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 160px;
        }

        .filter-select,
        .filter-input {
            width: 100%;
            height: 36px;
            padding: 6px 12px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .apply-btn {
            height: 36px;
            padding: 0 20px;
            background: var(--primary-mid);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        .reset-btn {
            height: 36px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
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
            color: #475569;
            padding: 12px 16px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
            padding: 12px 16px;
        }

        /* ==================== BADGES ==================== */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
            display: inline-block;
        }

        /* Repayment-type badges (Lump Sum / EMI) — status badges below use
           the ui.status-badge component instead of these Bootstrap bg-* overrides. */
        .repayment-type-badge.type-lumpsum {
            background: var(--primary-light) !important;
            color: var(--primary-mid);
        }

        .repayment-type-badge.type-emi {
            background: var(--success-light) !important;
            color: var(--success);
        }

        /* Loan-specific statuses beyond the shared .status-badge mapping in
           theme-custom.css (pending/approved/active already map there). */
        .status-badge[data-status="closed"] {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        .status-badge[data-status="default"] {
            background: var(--danger-light);
            color: var(--danger);
        }

        /* ==================== LOAN TYPE BADGES ==================== */
        .loan-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .type-salary {
            background: var(--primary-light);
            color: var(--primary-mid);
        }

        .type-festival {
            background: #fef3c7;
            color: #92400e;
        }

        .type-medical {
            background: #d1fae5;
            color: #065f46;
        }

        .type-personal {
            background: #fce7f3;
            color: #be185d;
        }

        .type-vehicle {
            background: #e0f2fe;
            color: #0369a1;
        }

        /* ==================== EMPLOYEE INFO ==================== */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
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
            color: var(--primary-mid);
            border-color: var(--primary-mid);
        }

        .action-btn.approve {
            background: var(--success-light);
            color: var(--success);
            border-color: #a7f3d0;
        }

        .action-btn.approve:hover {
            background: var(--success);
            color: white;
        }

        .action-btn.reject {
            background: var(--danger-light);
            color: var(--danger);
            border-color: #fecaca;
        }

        .action-btn.reject:hover {
            background: var(--danger);
            color: white;
        }

        .action-btn.disburse {
            background: var(--warning-light);
            color: var(--warning);
            border-color: #fde68a;
        }

        .action-btn.disburse:hover {
            background: var(--warning);
            color: white;
        }

        /* ==================== DETAIL CARD ==================== */
        .detail-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 5px;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-size: 10px;
            font-weight: 600;
            color: #1e293b;
        }

        .detail-value-small {
            font-size: 10px;
            font-weight: 500;
            color: #475569;
        }

        /* ==================== PROGRESS BAR ==================== */
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #10b981;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* ==================== SCHEDULE TABLE ==================== */
        .schedule-table th {
            font-size: 11px;
            background: #f8fafc;
            padding: 10px 12px;
        }

        .schedule-table td {
            font-size: 12px;
            padding: 10px 12px;
            vertical-align: middle;
        }

        .repayment-status {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: 500;
        }

        .repayment-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .repayment-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .repayment-overdue {
            background: #fee2e2;
            color: #991b1b;
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
            border-color: var(--primary-mid);
            box-shadow: var(--shadow-focus);
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                <h5 class="m-b-10">Loan Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Loan Approvals</li>
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

    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper"><i class="feather-file-text"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $totalLoans ?? 0 }}</div>
                    <div class="stats-label">Total (Pending + Approved)</div>
                </div>
            </div>
            <div class="stats-card pending-card">
                <div class="stats-icon-wrapper"><i class="feather-clock"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $pendingCount ?? 0 }}</div>
                    <div class="stats-label">Pending Approval</div>
                </div>
            </div>
            <div class="stats-card approved-card">
                <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $approvedCount ?? 0 }}</div>
                    <div class="stats-label">Approved (Ready for Disbursement)</div>
                </div>
            </div>
            <div class="stats-card rejected-card">
                <div class="stats-icon-wrapper"><i class="feather-x-circle"></i></div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $rejectedCount ?? 0 }}</div>
                    <div class="stats-label">Rejected This Month</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i> Filter Approvals
                    @php
                        $activeFilterCount = collect(request()->only(['search', 'category']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['search', 'category','payment_type','employee']))
                    <a href="{{ route('loan.approvals.pending') }}" class="reset-btn"><i class="feather-x"></i> Clear
                        All</a>
                @endif
            </div>
            <form action="{{ route('loan.approvals.pending') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    {{-- <div class="filter-item" style="flex: 1; min-width: 200px;">
                        <input type="text" class="filter-input" name="search" value="{{ request('search') }}"
                            placeholder="Search by loan #, employee, purpose...">
                    </div> --}}
                     <div class="filter-item" >

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
                    <div class="filter-item">
                        <select class="filter-select" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach ($categories ?? [] as $category)
                                <option value="{{ $category->id }}"
                                    {{ request('category') == $category->id ? 'selected' : '' }}>{{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select class="filter-select" name="payment_type" onchange="this.form.submit()">
                            <option value="">All Payment Type</option>

                            <option value="emi" {{ request('payment_type') == 'emi' ? 'selected' : '' }}>
                                EMI
                            </option>
                            <option value="lumpsum" {{ request('payment_type') == 'lumpsum' ? 'selected' : '' }}>
                                Lumpsum
                            </option>
                        </select>
                    </div>
                    {{-- <div class="filter-item" style="min-width: auto;">
                        <button type="submit" class="apply-btn"><i class="feather-search"></i> Search</button>
                    </div> --}}
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('loan.approvals.pending') }}" class="reset-btn"><i
                                class="feather-refresh-cw"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pending/Approved Loans Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Loan Approval & Disbursement</h5>
                        <span class="badge" style="background: var(--primary-light); color: var(--primary-mid);"><i class="feather-list me-1"></i>Total: {{ $loans->total() }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="approvalsTable">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Loan Number</th>
                                        <th>Employee</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Payment Type</th>
                                        <th>Tenure</th>
                                        <th>Purpose</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($loans as $index => $loan)
                                        <tr>
                                            <td>{{ $loans->firstItem() + $index }}</td>
                                            <td><span class="fw-bold">{{ $loan->loan_number }}</span></td>
                                            <td>
                                                <div class="employee-info">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($loan->user->name ?? 'NA', 0, 2)) }}</div>
                                                    <div>
                                                        <div class="employee-name">{{ $loan->user->name ?? 'N/A' }}
                                                            <small>( {{ $loan->user->employee_id ?? 'N/A' }} )</small>
                                                        </div>
                                                        <div class="employee-id">{{ $loan->user->email ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @php
                                                    $categoryClass = 'type-personal';
                                                    $categoryName = strtolower($loan->loanCategory->name ?? '');
                                                    if (strpos($categoryName, 'salary') !== false) {
                                                        $categoryClass = 'type-salary';
                                                    } elseif (strpos($categoryName, 'festival') !== false) {
                                                        $categoryClass = 'type-festival';
                                                    } elseif (strpos($categoryName, 'medical') !== false) {
                                                        $categoryClass = 'type-medical';
                                                    } elseif (strpos($categoryName, 'vehicle') !== false) {
                                                        $categoryClass = 'type-vehicle';
                                                    }
                                                @endphp
                                                <span class="loan-type-badge {{ $categoryClass }}">
                                                    {{-- <i
                                                        class="feather-tag"></i> --}}
                                                    {{ $loan->loanCategory->name ?? 'N/A' }}</span>
                                            </td>
                                            <td><span class="amount-text">₹ {{ number_format($loan->amount, 2) }}</span>
                                            </td>
                                            <td>
                                                @if ($loan->repayment_type == 'lumpsum')
                                                    <span class="badge repayment-type-badge type-lumpsum">Lump Sum</span>
                                                @else
                                                    <span class="badge repayment-type-badge type-emi">EMI (₹
                                                        {{ number_format($loan->emi_amount, 2) }})</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($loan->repayment_type == 'lumpsum')
                                                    {{ $loan->tenure_months }} months
                                                @else
                                                    {{ $loan->tenure_months }} months
                                                @endif
                                            </td>
                                            <td>
                                                <div title="{{ $loan->purpose }}">
                                                    {{ Str::limit($loan->purpose ?? 'No purpose', 30) }}</div>
                                            </td>
                                            <td>{{ $loan->created_at->format('d M Y') }}</td>
                                            <td>
                                                <x-ui.status-badge :status="$loan->status" :label="$loan->status === 'default' ? 'Rejected' : null" />
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <!-- View Button (Always visible) -->
                                                    <button class="action-btn view view-loan"
                                                        data-id="{{ $loan->id }}" title="View Details" data-bs-toggle="tooltip">
                                                        <i class="feather-eye"></i>
                                                    </button>

                                                    <!-- Approve/Reject Buttons (Only for PENDING loans) -->
                                                    @if ($loan->status == 'pending')
                                                        <button class="action-btn approve approve-loan"
                                                            data-id="{{ $loan->id }}"
                                                            data-number="{{ $loan->loan_number }}"
                                                            data-employee="{{ $loan->user->name ?? 'N/A' }}"
                                                            title="Approve Loan" data-bs-toggle="tooltip">
                                                            <i class="feather-check"></i>
                                                        </button>
                                                        <button class="action-btn reject reject-loan"
                                                            data-id="{{ $loan->id }}"
                                                            data-number="{{ $loan->loan_number }}"
                                                            data-employee="{{ $loan->user->name ?? 'N/A' }}"
                                                            title="Reject Loan" data-bs-toggle="tooltip">
                                                            <i class="feather-x"></i>
                                                        </button>
                                                    @endif

                                                    <!-- Disburse Button (Only for APPROVED loans) -->
                                                    @if ($loan->status == 'approved')
                                                        <button class="action-btn disburse disburse-loan"
                                                            data-id="{{ $loan->id }}"
                                                            data-number="{{ $loan->loan_number }}"
                                                            data-employee="{{ $loan->user->name ?? 'N/A' }}"
                                                            title="Disburse Loan" data-bs-toggle="tooltip">
                                                            <i class="feather-dollar-sign"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Records Found</h4>
                                                    <p class="text-muted">There are no pending or approved loan requests at
                                                        the moment.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($loans->hasPages())
                        <div class="card-footer">{{ $loans->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- ==================== VIEW LOAN DETAIL MODAL ==================== -->
    <div class="modal fade" id="viewLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="mb-0" style="font-size: 14px; font-weight: bold;">Loan Details</h2>
                        {{-- <span class="text-muted" id="view_loan_number" style="font-size: 11px;"></span> --}}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0" style="border: none;">
                        <div class="card-body" style="padding: 20px !important;">
                            <div id="viewLoanLoading" class="text-center py-4">
                                <i class="feather-loader fa-spin" style="font-size: 24px;"></i>
                                <p class="mt-2">Loading loan details...</p>
                            </div>
                            <div id="viewLoanContent" style="display: none;">
                                <!-- Employee & Category -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-card">
                                            <div class="detail-label">Employee</div>
                                            <div class="detail-value" id="view_employee"></div>
                                            <div class="detail-value-small" id="view_employee_id"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-card">
                                            <div class="detail-label">Category</div>
                                            <div class="detail-value" id="view_category"></div>
                                            <div class="detail-value-small" id="view_repayment_type"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loan Amount Details -->
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="detail-card text-center">
                                            <div class="detail-label">Loan Amount</div>
                                            <div class="detail-value" id="view_amount">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card text-center">
                                            <div class="detail-label">Interest Rate</div>
                                            <div class="detail-value" id="view_interest_rate">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card text-center">
                                            <div class="detail-label">Tenure (Months)</div>
                                            <div class="detail-value" id="view_tenure">0 months</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="detail-card text-center">
                                            <div class="detail-label">Current Status</div>
                                            <div id="view_status" class="detail-value"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- EMI Specific Info -->
                                <div class="row" id="viewEmiInfo">
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Monthly EMI</div>
                                            <div class="detail-value" id="view_emi">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Total Payable</div>
                                            <div class="detail-value" id="view_total_payable">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Total Interest</div>
                                            <div class="detail-value" id="view_total_interest">₹ 0</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lump Sum Specific Info -->
                                <div class="row" id="viewLumpsumInfo" style="display: none;">
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Lump Sum Amount</div>
                                            <div class="detail-value" id="view_lumpsum_amount">₹ 0</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Due Date</div>
                                            <div class="detail-value" id="view_lumpsum_due_date">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-card">
                                            <div class="detail-label">Interest Amount</div>
                                            <div class="detail-value" id="view_lumpsum_interest">₹ 0</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Purpose & Description -->
                                <div class="detail-card">
                                    <div class="detail-label">Purpose</div>
                                    <div class="detail-value-small" id="view_purpose">-</div>
                                </div>
                                <div class="detail-card" id="view_description_card" style="display: none;">
                                    <div class="detail-label">Description</div>
                                    <div class="detail-value-small" id="view_description">-</div>
                                </div>

                                <!-- Progress Bar (for active loans) -->
                                <div class="detail-card" id="viewScheduleCard" style="display: none;">
                                    <div class="detail-label">Repayment Progress</div>
                                    <div class="progress-bar-custom mb-2">
                                        <div class="progress-fill" id="view_progress_bar" style="width: 0%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="detail-value-small">Paid: <span id="view_paid_amount">₹
                                                0</span></span>
                                        <span class="detail-value-small">Remaining: <span id="view_remaining_amount">₹
                                                0</span></span>
                                    </div>
                                </div>

                                <!-- Repayment Schedule Table -->
                                <div class="mt-3" id="viewScheduleTable" style="display: none;">
                                    <h6 class="fw-bold mb-2"><i class="feather-calendar me-2"></i>Repayment Schedule</h6>
                                    <div class="table-responsive">
                                        <table class="table schedule-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Due Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Paid Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="view_schedule_body">
                                                <tr>
                                                    <td colspan="5" class="text-center">No schedule available</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== DISBURSE LOAN MODAL ==================== -->
    <div class="modal fade" id="disburseLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disburse Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="feather-check-circle text-success" style="font-size: 48px;"></i>
                    <p class="mt-3">Are you sure you want to disburse loan <strong id="disburseLoanNumber"></strong>?
                    </p>
                    <p class="text-muted small">This will mark the loan as active and generate the repayment schedule.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmDisburse">Yes, Disburse</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== APPROVE LOAN MODAL ==================== -->
    <div class="modal fade" id="approveLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Loan Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3"><i class="feather-check-circle text-success"
                            style="font-size: 48px;"></i></div>
                    <p class="text-center mb-3">Are you sure you want to approve the loan request from <strong
                            id="approveEmployeeName"></strong>?<br><small class="text-muted"
                            id="approveLoanNumber"></small></p>
                    <div class="form-group">
                        <label for="approveComments">Comments (Optional)</label>
                        <textarea class="modal-comments" id="approveComments" placeholder="Add any comments or remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApprove"><i
                            class="feather-check me-1"></i> Yes, Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== REJECT LOAN MODAL ==================== -->
    <div class="modal fade" id="rejectLoanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Loan Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3"><i class="feather-alert-triangle text-danger"
                            style="font-size: 48px;"></i></div>
                    <p class="text-center mb-3">Are you sure you want to reject the loan request from <strong
                            id="rejectEmployeeName"></strong>?<br><small class="text-muted"
                            id="rejectLoanNumber"></small></p>
                    <div class="form-group">
                        <label for="rejectComments">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="modal-comments" id="rejectComments" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReject"><i class="feather-x me-1"></i> Yes,
                        Reject</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        $(document).ready(function() {

            // ==================== SEARCH WITH DEBOUNCE ====================
            let searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => $('#filterForm').submit(), 500);
            });

            // ==================== SHARED STATE ====================
            let approveId = null,
                rejectId = null,
                disburseId = null;

            // ==================== VIEW LOAN DETAILS ====================
            // FIX: Use delegated event binding via $(document).on(...)
            $(document).on('click', '.view-loan', function() {
                let loanId = $(this).data('id');
                $('#viewLoanLoading').show();
                $('#viewLoanContent').hide();
                // Reset toggled sections
                $('#viewEmiInfo').show();
                $('#viewLumpsumInfo').hide();
                $('#viewScheduleCard').hide();
                $('#viewScheduleTable').hide();
                $('#view_description_card').hide();
                $('#viewLoanModal').modal('show');

                $.ajax({
                    url: '{{ url('loan/requests') }}/' + loanId,
                    type: 'GET',
                    success: function(response) {
                        $('#viewLoanLoading').hide();
                        if (response.success) {
                            let loan = response.data;

                            // Basic Info
                            $('#view_loan_number').text(loan.loan_number);
                            $('#view_employee').text(loan.user?.name || 'N/A');
                            $('#view_employee_id').text(loan.user?.employee_id || 'N/A');
                            $('#view_category').text(loan.loan_category?.name || 'N/A');
                            $('#view_repayment_type').text(loan.repayment_type === 'lumpsum' ?
                                'Lump Sum' : 'Monthly EMI');
                            $('#view_amount').text('₹ ' + parseFloat(loan.amount)
                                .toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                            $('#view_interest_rate').text(loan.interest_rate + '%');
                            $('#view_tenure').text(loan.tenure_months + ' months');
                            $('#view_purpose').text(loan.purpose || '-');

                            // Status Badge — same markup the ui.status-badge component renders
                            var statusLabel = loan.status === 'default' ? 'Rejected' :
                                (loan.status.charAt(0).toUpperCase() + loan.status.slice(1));
                            $('#view_status').html('<span class="status-badge" data-status="' + loan.status + '">' +
                                statusLabel + '</span>');

                            // Description
                            if (loan.description) {
                                $('#view_description').text(loan.description);
                                $('#view_description_card').show();
                            }

                            // Handle EMI vs Lump Sum
                            if (loan.repayment_type === 'lumpsum') {
                                $('#viewEmiInfo').hide();
                                $('#viewLumpsumInfo').show();
                                $('#view_lumpsum_amount').text('₹ ' + parseFloat(loan
                                    .lumpsum_amount || loan.amount).toLocaleString(
                                    'en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#view_lumpsum_due_date').text(loan.lumpsum_due_date ?
                                    new Date(loan.lumpsum_due_date).toLocaleDateString(
                                        'en-IN') : '-');
                                $('#view_lumpsum_interest').text('₹ ' + parseFloat((loan
                                        .lumpsum_amount || loan.amount) - loan.amount)
                                    .toLocaleString('en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                            } else {
                                $('#viewEmiInfo').show();
                                $('#viewLumpsumInfo').hide();
                                $('#view_emi').text('₹ ' + parseFloat(loan.emi_amount)
                                    .toLocaleString('en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#view_total_payable').text('₹ ' + parseFloat(loan
                                    .emi_amount * loan.tenure_months).toLocaleString(
                                    'en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#view_total_interest').text('₹ ' + parseFloat((loan
                                        .emi_amount * loan.tenure_months) - loan.amount)
                                    .toLocaleString('en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                            }

                            // Repayment Schedule
                            if (loan.repayments && loan.repayments.length > 0) {
                                $('#viewScheduleTable').show();
                                let scheduleHtml = '';
                                loan.repayments.forEach(function(repayment) {
                                    let statusClass = repayment.status === 'paid' ?
                                        'repayment-paid' :
                                        (repayment.status === 'overdue' ?
                                            'repayment-overdue' : 'repayment-pending');
                                    let statusText = repayment.status.charAt(0)
                                        .toUpperCase() + repayment.status.slice(1);
                                    let amount = repayment.total_amount || repayment
                                        .emi_amount;
                                    scheduleHtml += `<tr>
                                        <td>${repayment.installment_number}</td>
                                        <td>${new Date(repayment.due_date).toLocaleDateString('en-IN')}</td>
                                        <td>₹ ${parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                                        <td><span class="repayment-status ${statusClass}">${statusText}</span></td>
                                        <td>${repayment.paid_date ? new Date(repayment.paid_date).toLocaleDateString('en-IN') : '-'}</td>
                                    </tr>`;
                                });
                                $('#view_schedule_body').html(scheduleHtml);
                            } else {
                                $('#viewScheduleTable').hide();
                            }

                            // Progress for active loans
                            if (loan.status === 'active') {
                                $('#viewScheduleCard').show();
                                let totalPaid = loan.amount - loan.remaining_amount;
                                let percentage = (totalPaid / loan.amount) * 100;
                                $('#view_progress_bar').css('width', percentage + '%');
                                $('#view_paid_amount').text('₹ ' + totalPaid.toLocaleString(
                                    'en-IN', {
                                        minimumFractionDigits: 2
                                    }));
                                $('#view_remaining_amount').text('₹ ' + parseFloat(loan
                                    .remaining_amount).toLocaleString('en-IN', {
                                    minimumFractionDigits: 2
                                }));
                            } else {
                                $('#viewScheduleCard').hide();
                            }

                            $('#viewLoanContent').show();
                        }
                    },
                    error: function() {
                        $('#viewLoanLoading').hide();
                        $('#viewLoanContent').show();
                        toastr.error('Error loading loan details');
                    }
                });
            });

            // ==================== APPROVE LOAN ====================
            // FIX: Use delegated event binding via $(document).on(...)
            $(document).on('click', '.approve-loan', function() {
                approveId = $(this).data('id');
                $('#approveEmployeeName').text($(this).data('employee'));
                $('#approveLoanNumber').text('Loan #: ' + $(this).data('number'));
                $('#approveComments').val('');
                $('#approveLoanModal').modal('show');
            });

            $('#confirmApprove').click(function() {
                if (!approveId) return;
                $.ajax({
                    url: '{{ url('loan/approvals') }}/' + approveId + '/approve',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        comments: $('#approveComments').val()
                    },
                    beforeSend: function() {
                        $('#confirmApprove').prop('disabled', true).html(
                            '<i class="feather-loader me-1"></i> Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#approveLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    },
                    complete: function() {
                        $('#confirmApprove').prop('disabled', false).html(
                            '<i class="feather-check me-1"></i> Yes, Approve');
                    }
                });
            });

            // ==================== REJECT LOAN ====================
            // FIX: Use delegated event binding via $(document).on(...)
            $(document).on('click', '.reject-loan', function() {
                rejectId = $(this).data('id');
                $('#rejectEmployeeName').text($(this).data('employee'));
                $('#rejectLoanNumber').text('Loan #: ' + $(this).data('number'));
                $('#rejectComments').val('');
                $('#rejectLoanModal').modal('show');
            });

            $('#confirmReject').click(function() {
                if (!rejectId) return;
                let reason = $('#rejectComments').val().trim();
                if (!reason) {
                    toastr.error('Please provide a reason for rejection');
                    return;
                }
                $.ajax({
                    url: '{{ url('loan/approvals') }}/' + rejectId + '/reject',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        rejection_reason: reason
                    },
                    beforeSend: function() {
                        $('#confirmReject').prop('disabled', true).html(
                            '<i class="feather-loader me-1"></i> Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#rejectLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    },
                    complete: function() {
                        $('#confirmReject').prop('disabled', false).html(
                            '<i class="feather-x me-1"></i> Yes, Reject');
                    }
                });
            });

            // ==================== DISBURSE LOAN ====================
            // FIX: Use delegated event binding via $(document).on(...)
            $(document).on('click', '.disburse-loan', function() {
                disburseId = $(this).data('id');
                $('#disburseLoanNumber').text($(this).data('number'));
                $('#disburseLoanModal').modal('show');
            });

            $('#confirmDisburse').click(function() {
                if (!disburseId) return;
                $.ajax({
                    url: '{{ url('loan/approvals') }}/' + disburseId + '/disburse',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function() {
                        $('#confirmDisburse').prop('disabled', true).html(
                            '<i class="feather-loader me-1"></i> Processing...');
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#disburseLoanModal').modal('hide');
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                    },
                    complete: function() {
                        $('#confirmDisburse').prop('disabled', false).html(
                            '<i class="feather-check me-1"></i> Yes, Disburse');
                    }
                });
            });

            // ==================== CLEAR STATE ON MODAL CLOSE ====================
            $('#approveLoanModal, #rejectLoanModal, #viewLoanModal, #disburseLoanModal').on('hidden.bs.modal',
                function() {
                    approveId = null;
                    rejectId = null;
                    disburseId = null;
                    $('#approveComments, #rejectComments').val('');
                });

        }); // end document.ready

        // ==================== EXPORT TO CSV ====================
        function exportToCSV() {
            let table = document.getElementById('approvalsTable');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];
            let headers = [];

            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length - 1; i++) {
                let text = headerCells[i].innerText.trim();
                if (text) headers.push('"' + text.replace(/"/g, '""') + '"');
            }
            csv.push(headers.join(','));

            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');
                for (let j = 0; j < cols.length - 1; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""').trim();
                    rowData.push('"' + text + '"');
                }
                if (rowData.length) csv.push(rowData.join(','));
            }

            let blob = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let link = document.createElement('a');
            link.download = 'pending_approvals_' + new Date().getTime() + '.csv';
            link.href = URL.createObjectURL(blob);
            link.click();
            URL.revokeObjectURL(link.href);
            toastr.success('CSV exported successfully');
        }
    </script>
@endsection
