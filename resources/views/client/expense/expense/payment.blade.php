@extends('client.layout.master')

@section('style')
    <style>
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

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.15);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(79, 70, 229, 0.1);
        }

        .stats-icon i {
            font-size: 24px;
            color: #4f46e5;
        }

        .stats-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #1e293b;
        }

        .stats-info p {
            font-size: 13px;
            color: #64748b;
            margin: 4px 0 0;
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

        .filter-select,
        .filter-input {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
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
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8fafc;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            padding: 12px 16px;
            white-space: nowrap;
        }

        .table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            margin: 0 2px;
            cursor: pointer;
        }

        .action-btn:hover {
            background: white;
            color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-2px);
        }

        .action-btn.delete:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

        /* Payment Type Card */
        .payment-type-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .payment-type-card.active {
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
        }

        .payment-type-card:hover {
            border-color: #4f46e5;
            transform: translateY(-2px);
        }

        .payment-type-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(79, 70, 229, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .payment-type-icon i {
            font-size: 24px;
            color: #4f46e5;
        }

        .expense-option {
            background: white;
            transition: all 0.2s;
        }

        .expense-option:hover {
            background: #f8fafc;
            transform: translateX(4px);
            border-color: #4f46e5 !important;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        /* Reimbursement Card Specific */
        .payment-type-card.reimbursement-card.active {
            border-color: #10b981;
        }

        .payment-type-card.reimbursement-card .payment-type-icon i {
            color: #10b981;
        }

        .payment-type-card.reimbursement-card.active .payment-type-icon {
            background: rgba(16, 185, 129, 0.1);
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-item {
                width: 100%;
            }
        }

        .payment-type-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .payment-type-card.active {
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
        }

        .payment-type-card:hover {
            border-color: #4f46e5;
            transform: translateY(-2px);
        }

        .payment-type-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(79, 70, 229, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .payment-type-icon i {
            font-size: 24px;
            color: #4f46e5;
        }

        /* Reimbursement Card Specific */
        .payment-type-card.reimbursement-card.active {
            border-color: #10b981;
        }

        .payment-type-card.reimbursement-card .payment-type-icon i {
            color: #10b981;
        }

        .payment-type-card.reimbursement-card.active .payment-type-icon {
            background: rgba(16, 185, 129, 0.1);
        }
    </style>
@endsection

@section('content-area')
    
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Payment Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.view-all') }}">Expenses</a></li>
                <li class="breadcrumb-item active">Payments</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-flex gap-2">
                @if (in_array($userRole, ['admin', 'hr']))
                    <button type="button" class="btn btn-primary btn-sm" onclick="openAddPaymentModal()">
                        <i class="feather-plus me-2"></i>Add Payment
                    </button>
                @endif
                <a href="{{ route('expense.view-all') }}" class="btn btn-light-brand btn-sm">
                    <i class="feather-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        {{-- Success/Error Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="feather-alert-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $totalPayments }}</h3>
                    <p>Total Payments</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="feather-credit-card"></i>
                </div>
                <div class="stats-info">
                    <h3>₹{{ number_format($totalAmount, 2) }}</h3>
                    <p>Total Amount</p>
                </div>
            </div>
            @foreach ($modeStats as $mode)
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas {{ $mode->payment_mode == 'cash' ? 'fa-rupee-sign' : 'fa-credit-card' }}"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $mode->count }}</h3>
                        <p>{{ ucfirst($mode->payment_mode) }} (₹{{ number_format($mode->total, 2) }})</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Filter Section --}}
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Payments
                </div>
            </div>

            <form action="{{ route('expense.payments.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item" style="min-width: 220px;">
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
                                        href="{{ route('expense.payments.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('expense.payments.index', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
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
                        <select class="filter-select" name="payment_mode">
                            <option value="">All Modes</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode }}"
                                    {{ request('payment_mode') == $mode ? 'selected' : '' }}>
                                    {{ ucfirst($mode) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <input type="date" class="filter-input" name="from_date" value="{{ request('from_date') }}">
                    </div>
                    <div class="filter-item">
                        <input type="date" class="filter-input" name="to_date" value="{{ request('to_date') }}">
                    </div>
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('expense.payments.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Payments Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Payment History</h5>
                <span class="badge bg-info">Total: {{ $payments->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table" id="paymentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Expense #</th>
                                <th>Employee</th>
                                <th>Payment Date</th>
                                <th>Amount</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th>Processed By</th>
                                <th>Status</th>
                                @if (in_array($userRole, ['admin', 'hr']))
                                    <th>Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <span
                                            class="text-primary">{{ $payment->expense_number ?? 'Direct Payment' }}</span>
                                    </td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($payment->employee_name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">{{ $payment->employee_name ?? 'N/A' }}</div>
                                                <div class="employee-email">{{ $payment->employee_email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ date('d M Y', strtotime($payment->payment_date)) }}</td>
                                    <td class="fw-bold">₹{{ number_format($payment->amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ ucfirst($payment->payment_mode) }}</span>
                                    </td>
                                    <td>{{ $payment->reference_number ?? '-' }}</td>
                                    <td>{{ $payment->payer->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-success">Completed</span>
                                    </td>

                                    @if (in_array($userRole, ['admin', 'hr']))
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="action-btn"
                                                    onclick="editPayment({{ $payment->id }})" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ in_array($userRole, ['admin', 'hr']) ? 10 : 9 }}"
                                        class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="feather-credit-card" style="font-size: 48px; color: #ccc;"></i>
                                            <h6 class="mt-3">No Payments Found</h6>
                                            <p class="text-muted">No payment records available</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($payments->hasPages())
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of
                            {{ $payments->total() }} entries
                        </div>
                        <div>
                            {{ $payments->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('create-modal')
    {{-- Add Payment Modal --}}
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="paymentForm">
                    @csrf
                    <input type="hidden" name="payment_id" id="payment_id">

                    <div class="modal-body">
                        {{-- Payment Type Selection --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold">Payment Type</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="payment-type-card text-center" id="directPaymentCard"
                                        onclick="selectPaymentType('direct')">
                                        <div class="payment-type-icon mx-auto">
                                            <i class="feather-user"></i>
                                        </div>
                                        <h6 class="mb-1">Direct Payment</h6>
                                        <small class="text-muted">Manual payment to employee</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="payment-type-card text-center" id="advancePaymentCard"
                                        onclick="selectPaymentType('advance')">
                                        <div class="payment-type-icon mx-auto">
                                            <i class="feather-dollar-sign"></i>
                                        </div>
                                        <h6 class="mb-1">Advance Payment</h6>
                                        <small class="text-muted">Against advance request</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="payment-type-card text-center reimbursement-card"
                                        id="reimbursementPaymentCard" onclick="selectPaymentType('reimbursement')">
                                        <div class="payment-type-icon mx-auto">
                                            <i class="feather-rotate-ccw"></i>
                                        </div>
                                        <h6 class="mb-1">Reimbursement Payment</h6>
                                        <small class="text-muted">Against reimbursement</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Direct Payment Section --}}
                        <div id="directPaymentSection" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Employee <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="direct_user_id" id="direct_user_id">
                                    <option value="">-- Select Employee --</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" data-name="{{ $employee->name }}">
                                            {{ $employee->name }} ({{ $employee->employee_id }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="feather-info me-2"></i> Direct payment will be added to employee's balance as
                                advance.
                            </div>
                        </div>

                        {{-- Advance Payment Section --}}
                        <div id="advancePaymentSection" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Advance Request <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="advance_expense_id" id="advance_expense_id">
                                    <option value="">-- Select Advance Request --</option>
                                    @foreach ($approvedAdvances ?? [] as $advance)
                                        <option value="{{ $advance->id }}" data-number="{{ $advance->expense_number }}"
                                            data-employee="{{ $advance->user->name }}"
                                            data-employee-id="{{ $advance->user_id }}"
                                            data-amount="{{ $advance->amount }}"
                                            data-remaining="{{ $advance->remaining_amount ?? $advance->amount }}">
                                            {{ $advance->expense_number }} - {{ $advance->user->name }}
                                            (₹{{ number_format($advance->remaining_amount ?? $advance->amount, 2) }}
                                            remaining)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="expense-info-card" id="advanceInfoCard" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1" id="selectedAdvanceNumber"></h6>
                                        <p class="mb-0 text-muted small" id="selectedAdvanceDetails"></p>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold" id="selectedAdvanceAmount"></div>
                                        <div class="text-muted small" id="selectedAdvanceRemaining"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning">
                                <i class="feather-alert-circle me-2"></i> Payment will be added to employee's advance
                                balance.
                            </div>
                        </div>

                        {{-- Reimbursement Payment Section --}}
                        <div id="reimbursementPaymentSection" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Reimbursement Request <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="reimbursement_expense_id"
                                    id="reimbursement_expense_id">
                                    <option value="">-- Select Reimbursement Request --</option>
                                    @foreach ($approvedReimbursements ?? [] as $reimbursement)
                                        <option value="{{ $reimbursement->id }}"
                                            data-number="{{ $reimbursement->expense_number }}"
                                            data-employee="{{ $reimbursement->user->name }}"
                                            data-employee-id="{{ $reimbursement->user_id }}"
                                            data-amount="{{ $reimbursement->amount }}">
                                            {{ $reimbursement->expense_number }} - {{ $reimbursement->user->name }}
                                            (₹{{ number_format($reimbursement->amount, 2) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="expense-info-card" id="reimbursementInfoCard" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1" id="selectedReimbursementNumber"></h6>
                                        <p class="mb-0 text-muted small" id="selectedReimbursementDetails"></p>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold" id="selectedReimbursementAmount"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-success">
                                <i class="feather-check-circle me-2"></i> Reimbursement payment will add money to
                                employee's reimbursement balance.
                            </div>
                        </div>

                        {{-- Common Payment Fields --}}
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Payment Date *</label>
                                <input type="date" class="form-control" name="payment_date" id="payment_date"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Amount *</label>
                                <input type="number" step="0.01" class="form-control" name="amount"
                                    id="payment_amount" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Payment Mode *</label>
                                <select class="form-control" name="payment_mode" id="payment_mode" required>
                                    <option value="">Select Mode</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode }}">{{ ucfirst($mode) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Paid To</label>
                                <input type="text" class="form-control" name="paid_to" id="paid_to">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number"
                                    id="reference_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" id="bank_name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="payment_remarks" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="paymentSubmitBtn">
                            <i class="feather-save me-2"></i>Save Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Payment Modal --}}
    <div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editPaymentForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="payment_id" id="edit_payment_id">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Payment Type</label>
                            <div class="alert alert-info mb-0">
                                <i class="feather-info me-2"></i>
                                <span id="edit_payment_type_display"></span>
                            </div>
                        </div>

                        <div id="editExpenseInfoCard" class="expense-info-card mb-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1" id="editExpenseNumber"></h6>
                                    <p class="mb-0 text-muted small" id="editExpenseDetails"></p>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold" id="editExpenseAmount"></div>
                                    <div class="text-muted small" id="editExpenseRemaining"></div>
                                </div>
                            </div>
                        </div>

                        <div id="editDirectPaymentInfo" class="alert alert-info mb-3" style="display: none;">
                            <i class="feather-user me-2"></i>
                            <strong>Employee:</strong> <span id="editEmployeeName"></span>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Payment Date *</label>
                                <input type="date" class="form-control" name="payment_date" id="edit_payment_date"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Amount *</label>
                                <input type="number" step="0.01" class="form-control" name="amount"
                                    id="edit_payment_amount" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Payment Mode *</label>
                                <select class="form-control" name="payment_mode" id="edit_payment_mode" required>
                                    <option value="">Select Mode</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode }}">{{ ucfirst($mode) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Paid To</label>
                                <input type="text" class="form-control" name="paid_to" id="edit_paid_to">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number"
                                    id="edit_reference_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" id="edit_bank_name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_payment_remarks" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editPaymentSubmitBtn">
                            <i class="feather-save me-2"></i>Update Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="feather-alert-triangle text-danger" style="font-size: 48px;"></i>
                    <h5 class="mt-3">Confirm Delete</h5>
                    <p class="text-muted">Are you sure you want to delete this payment? This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
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

        let currentPaymentId = null;
        let currentPaymentType = 'direct';

        function handlePaymentModeChange() {
            const paymentMode = $('#payment_mode').val();
            const referenceField = $('#reference_number').closest('.col-md-6');
            const bankField = $('#bank_name').closest('.col-md-6');

            if (paymentMode === 'cash') {
                referenceField.hide();
                bankField.hide();
                $('#reference_number').val('');
                $('#bank_name').val('');
            } else {
                referenceField.show();
                bankField.show();
            }
        }

        function handleEditPaymentModeChange() {
            const paymentMode = $('#edit_payment_mode').val();
            const referenceField = $('#edit_reference_number').closest('.col-md-6');
            const bankField = $('#edit_bank_name').closest('.col-md-6');

            if (paymentMode === 'cash') {
                referenceField.hide();
                bankField.hide();
                $('#edit_reference_number').val('');
                $('#edit_bank_name').val('');
            } else {
                referenceField.show();
                bankField.show();
            }
        }

        function selectPaymentType(type) {
            currentPaymentType = type;

            // Reset active state on all cards
            $('#directPaymentCard, #advancePaymentCard, #reimbursementPaymentCard').removeClass('active');

            // Hide all sections
            $('#directPaymentSection, #advancePaymentSection, #reimbursementPaymentSection').hide();

            // Remove required from all selects
            $('#advance_expense_id, #reimbursement_expense_id, #direct_user_id').removeAttr('required');

            // Show selected section and set required attributes
            if (type === 'direct') {
                $('#directPaymentCard').addClass('active');
                $('#directPaymentSection').show();
                $('#direct_user_id').prop('required', true);
                $('#payment_amount').removeAttr('max');
                $('#payment_amount').val('');
                $('#advanceInfoCard, #reimbursementInfoCard').hide();
            } else if (type === 'advance') {
                $('#advancePaymentCard').addClass('active');
                $('#advancePaymentSection').show();
                $('#advance_expense_id').prop('required', true);
                $('#payment_amount').removeAttr('max');
                $('#reimbursementInfoCard').hide();
            } else if (type === 'reimbursement') {
                $('#reimbursementPaymentCard').addClass('active');
                $('#reimbursementPaymentSection').show();
                $('#reimbursement_expense_id').prop('required', true);
                $('#advanceInfoCard').hide();
            }

            // Reset amount field and paid_to
            $('#payment_amount').val('');
            $('#paid_to').val('');
        }
        $(document).ready(function() {
            // Auto-submit filters
            $('.filter-select, .filter-input').on('change', function() {
                $('#filterForm').submit();
            });

            // Set default date to today
            let today = new Date();
            let todayFormatted = today.toISOString().split('T')[0];
            $('#payment_date').val(todayFormatted);

            // Handle payment mode change
            $('#payment_mode').on('change', function() {
                handlePaymentModeChange();
            });

            $('#edit_payment_mode').on('change', function() {
                handleEditPaymentModeChange();
            });

            // Advance expense selection
            $('#advance_expense_id').on('change', function() {
                let selected = $(this).find(':selected');
                if (selected.val()) {
                    let expenseNumber = selected.data('number');
                    let employeeName = selected.data('employee');
                    let amount = parseFloat(selected.data('amount'));
                    let remaining = parseFloat(selected.data('remaining'));

                    $('#selectedAdvanceNumber').text('Advance: ' + expenseNumber);
                    $('#selectedAdvanceDetails').text('Employee: ' + employeeName);
                    $('#selectedAdvanceAmount').text('Total: ₹' + amount.toFixed(2));
                    $('#selectedAdvanceRemaining').text('Remaining: ₹' + remaining.toFixed(2));
                    $('#advanceInfoCard').show();

                    $('#payment_amount').attr('max', remaining);
                    $('#payment_amount').attr('placeholder', 'Max: ₹' + remaining.toFixed(2));
                    $('#paid_to').val(employeeName);
                } else {
                    $('#advanceInfoCard').hide();
                    $('#payment_amount').removeAttr('max');
                    $('#payment_amount').attr('placeholder', 'Enter amount');
                }
            });

            // Reimbursement expense selection
            $('#reimbursement_expense_id').on('change', function() {
                let selected = $(this).find(':selected');
                if (selected.val()) {
                    let expenseNumber = selected.data('number');
                    let employeeName = selected.data('employee');
                    let amount = parseFloat(selected.data('amount'));

                    $('#selectedReimbursementNumber').text('Reimbursement: ' + expenseNumber);
                    $('#selectedReimbursementDetails').text('Employee: ' + employeeName);
                    $('#selectedReimbursementAmount').text('Amount: ₹' + amount.toFixed(2));
                    $('#reimbursementInfoCard').show();

                    $('#payment_amount').attr('max', amount);
                    $('#payment_amount').val(amount);
                    $('#paid_to').val(employeeName);
                } else {
                    $('#reimbursementInfoCard').hide();
                    $('#payment_amount').removeAttr('max');
                    $('#payment_amount').val('');
                }
            });

            // Direct user selection
            $('#direct_user_id').on('change', function() {
                let selected = $(this).find(':selected');
                if (selected.val()) {
                    let employeeName = selected.data('name');
                    $('#paid_to').val(employeeName);
                }
            });

            // Payment form submission
            // Payment form submission
            $('#paymentForm').on('submit', function(e) {
                e.preventDefault();

                let paymentId = $('#payment_id').val();
                let url = paymentId ?
                    "{{ route('expense.payments.update', '') }}/" + paymentId :
                    "{{ route('expense.payments.store') }}";
                let method = paymentId ? 'PUT' : 'POST';

                // Build form data manually to ensure correct expense_id is sent
                let formData = new FormData(this);

                // Add payment type
                formData.append('payment_type', currentPaymentType);

                // Add the correct expense_id based on payment type
                if (currentPaymentType === 'advance') {
                    let advanceExpenseId = $('#advance_expense_id').val();
                    if (advanceExpenseId) {
                        formData.append('expense_id', advanceExpenseId);
                    }
                } else if (currentPaymentType === 'reimbursement') {
                    let reimbursementExpenseId = $('#reimbursement_expense_id').val();
                    if (reimbursementExpenseId) {
                        formData.append('expense_id', reimbursementExpenseId);
                    }
                }

                let btn = $('#paymentSubmitBtn');
                let originalText = btn.html();
                btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

                $.ajax({
                    url: url,
                    type: "POST",
                    data: formData,
                    processData: false, // Important for FormData
                    contentType: false, // Important for FormData
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#paymentModal').modal('hide');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            toastr.error(response.message || 'Something went wrong');
                        }
                        btn.prop('disabled', false).html(originalText);
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html(originalText);
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, (key, value) => toastr.error(value[0]));
                        } else if (xhr.status === 400) {
                            toastr.error(xhr.responseJSON?.message || 'Insufficient balance');
                        } else {
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });
            // Edit payment form submission
            $('#editPaymentForm').on('submit', function(e) {
                e.preventDefault();

                let paymentId = $('#edit_payment_id').val();
                let url = "{{ route('expense.payments.update', '') }}/" + paymentId;

                let btn = $('#editPaymentSubmitBtn');
                let originalText = btn.html();
                btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Updating...');

                $.ajax({
                    url: url,
                    type: "POST",
                    data: $(this).serialize() + '&_method=PUT',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#editPaymentModal').modal('hide');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            toastr.error(response.message || 'Something went wrong');
                        }
                        btn.prop('disabled', false).html(originalText);
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html(originalText);
                        if (xhr.status === 422) {
                            let errors = xhr.responseJSON.errors;
                            $.each(errors, (key, value) => toastr.error(value[0]));
                        } else {
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong');
                        }
                    }
                });
            });

            // Confirm delete
            $('#confirmDelete').on('click', function() {
                if (!currentPaymentId) return;

                $.ajax({
                    url: "{{ route('expense.payments.destroy', '') }}/" + currentPaymentId,
                    type: "DELETE",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#deleteModal').modal('hide');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            toastr.error(response.message);
                            $('#deleteModal').modal('hide');
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to delete payment');
                        $('#deleteModal').modal('hide');
                    }
                });
            });
        });

        function openAddPaymentModal() {
            $('#paymentModalTitle').text('Add Payment');
            $('#paymentForm')[0].reset();
            $('#payment_id').val('');
            $('#advanceInfoCard, #reimbursementInfoCard').hide();
            $('#payment_amount').removeAttr('max');

            let today = new Date();
            let todayFormatted = today.toISOString().split('T')[0];
            $('#payment_date').val(todayFormatted);

            selectPaymentType('direct');
            $('#paymentModal').modal('show');
        }

        function editPayment(id) {
            toastr.info('Fetching payment details...');

            $.ajax({
                url: "{{ route('expense.payments.show', '') }}/" + id,
                type: "GET",
                success: function(response) {
                    if (response.success) {
                        let p = response.data;
                        $('#edit_payment_id').val(p.id);

                        if (p.payment_date) {
                            let date = new Date(p.payment_date);
                            if (!isNaN(date.getTime())) {
                                let year = date.getFullYear();
                                let month = String(date.getMonth() + 1).padStart(2, '0');
                                let day = String(date.getDate()).padStart(2, '0');
                                $('#edit_payment_date').val(year + '-' + month + '-' + day);
                            }
                        }

                        $('#edit_payment_amount').val(p.amount);
                        $('#edit_payment_mode').val(p.payment_mode);
                        $('#edit_reference_number').val(p.reference_number || '');
                        $('#edit_bank_name').val(p.bank_name || '');
                        $('#edit_paid_to').val(p.paid_to || '');
                        $('#edit_payment_remarks').val(p.remarks || '');

                        if (p.expense_id && p.expense) {
                            if (p.expense.requirement_type === 'advance') {
                                $('#edit_payment_type_display').text('Advance Payment - ' + (p.expense
                                    .expense_number || 'N/A'));
                            } else if (p.expense.requirement_type === 'reimbursement') {
                                $('#edit_payment_type_display').text('Reimbursement Payment - ' + (p.expense
                                    .expense_number || 'N/A'));
                            } else {
                                $('#edit_payment_type_display').text('Expense Payment - ' + (p.expense
                                    .expense_number || 'N/A'));
                            }

                            $('#editExpenseNumber').text('Expense: ' + (p.expense.expense_number || 'N/A'));
                            $('#editExpenseDetails').text('Employee: ' + (p.expense.user?.name || 'N/A'));
                            $('#editExpenseAmount').text('Amount: ₹' + parseFloat(p.expense.amount || 0)
                                .toFixed(2));

                            let paid = parseFloat(p.amount);
                            let total = parseFloat(p.expense.amount || 0);
                            let remaining = total - paid;
                            $('#editExpenseRemaining').text('Remaining: ₹' + remaining.toFixed(2));

                            $('#editExpenseInfoCard').show();
                            $('#editDirectPaymentInfo').hide();
                            $('#edit_payment_amount').attr('max', total);
                        } else {
                            $('#edit_payment_type_display').text('Direct Payment');
                            $('#editEmployeeName').text(p.employee_name || 'N/A');
                            $('#editDirectPaymentInfo').show();
                            $('#editExpenseInfoCard').hide();
                            $('#edit_payment_amount').removeAttr('max');
                        }

                        handleEditPaymentModeChange();
                        $('#editPaymentModal').modal('show');
                        toastr.clear();
                    } else {
                        toastr.error(response.message || 'Failed to load payment details');
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 404) {
                        toastr.error('Payment not found');
                    } else if (xhr.status === 403) {
                        toastr.error('You are not authorized to view this payment');
                    } else {
                        toastr.error('Failed to fetch payment details. Please try again.');
                    }
                }
            });
        }

        function deletePayment(id) {
            currentPaymentId = id;
            $('#deleteModal').modal('show');
        }

        $('#paymentModal').on('hidden.bs.modal', function() {
            $('#paymentForm')[0].reset();
            $('#payment_id').val('');
            $('#advanceInfoCard, #reimbursementInfoCard').hide();
            $('.is-invalid').removeClass('is-invalid');
            selectPaymentType('direct');
        });

        $('#editPaymentModal').on('hidden.bs.modal', function() {
            $('#editPaymentForm')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
        });

        $('#deleteModal').on('hidden.bs.modal', function() {
            currentPaymentId = null;
        });
    </script>
@endsection
