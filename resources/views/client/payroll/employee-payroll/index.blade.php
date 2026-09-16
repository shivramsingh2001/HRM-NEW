{{-- resources/views/client/payroll/employee-payroll/index.blade.php --}}
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

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Consistent initials style - same color for all */
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
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #eef2ff;
            color: #4f46e5;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #4f46e5 !important;
            opacity: 0.8;
        }

        .employee-name {
            color: #1e293b;
            font-weight: 500;
        }

        .text-muted {
            color: #64748b !important;
        }

        /* ==================== MODERN FILTER SECTION ==================== */
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

        /* ==================== COMPACT FILTER ROW ==================== */
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

        .filter-item .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Select Dropdowns */
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

        .filter-select:focus {
            background-color: white;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #94a3b8;
        }

        /* Apply Button */
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
            transform: translateY(-1px);
        }

        .apply-btn i {
            font-size: 14px;
        }

        /* Reset Button */
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
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        /* ==================== ACTIVE FILTER TAGS ==================== */
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
            display: inline-flex;
            align-items: center;
            text-decoration: none;
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

        .filter-tag.clear-all:hover {
            background: #4f46e5;
            color: white;
        }

        .filter-tag.clear-all i {
            color: currentColor;
        }

        /* ==================== CARD STYLES ==================== */
        .card {
            border: 1px solid #edf2f7;
            border-radius: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 20px;
        }

        .card-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .card-body {
            padding: 0;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            text-align: left;
        }

        .table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        /* Employee Info with Avatar */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #4f46e5, #818cf8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }

        .employee-details {
            line-height: 1.3;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .employee-id {
            font-size: 10px;
            color: #64748b;
        }

        /* Payroll Code */
        .payroll-code {
            font-weight: 600;
            color: #4f46e5;
            background: #eef2ff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        /* Amount Styling */
        .amount {
            font-weight: 600;
            color: #1e293b;
            font-size: 12px;
        }

        /* Status Badges */
        .badge-status {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .badge-current {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-history {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-status i {
            font-size: 10px;
        }

        /* Date Styling */
        .date-text {
            font-size: 12px;
            color: #64748b;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 4px;
        }

        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-action:hover {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
            transform: translateY(-2px);
        }

        .btn-action.edit:hover {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #b45309;
        }

        .btn-action.delete:hover {
            background: #fee2e2;
            border-color: #ef4444;
            color: #b91c1c;
        }

        .btn-action i {
            font-size: 14px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h6 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 0;
        }

        /* ==================== PAGINATION ==================== */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            padding: 16px;
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

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 12px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
            }

            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 12px;
                border-bottom: 1px solid #f1f5f9;
            }

            .table tbody td:last-child {
                border-bottom: none;
            }

            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #64748b;
                font-size: 11px;
                text-transform: uppercase;
            }

            .action-buttons {
                justify-content: flex-end;
            }
        }
    </style>
@endsection

@section('content-area')

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Employee Payroll Management</h5>
            </div>
           
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('employee-payrolls.index') }}">Payroll</a></li>
                <li class="breadcrumb-item active">Employee Payroll</li>
            </ul>
        </div>

    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Payroll Records
                    @php
                        $activeFilterCount = collect(request()->only(['user_id', 'is_current']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['user_id', 'is_current']))
                    <a href="{{ route('employee-payrolls.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('employee-payrolls.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Custom Employee Dropdown with Initials -->
                    <div class="filter-item" style="min-width: 220px;">
                        <!--<label class="form-label">Employee</label>-->
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('user_id') && ($selectedEmployee = $users->firstWhere('id', request('user_id'))))
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
                                        href="{{ route('employee-payrolls.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($users as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('employee-payrolls.index', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $employee->name }} <small
                                                        class="text-muted">({{ $employee->employee_id }})</small></span>
                                                <small class="text-muted">{{ $employee->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <!--<label class="form-label">Status</label>-->
                        <select name="is_current" class="filter-select">
                            <option value="">All Payrolls</option>
                            <option value="1" {{ request('is_current') === '1' ? 'selected' : '' }}>Current Only
                            </option>
                            <option value="0" {{ request('is_current') === '0' ? 'selected' : '' }}>History Only
                            </option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <!--<div class="filter-item" style="min-width: auto;">-->
                    <!--    <button type="submit" class="apply-btn">-->
                    <!--        <i class="feather-search"></i>-->
                    <!--        Apply-->
                    <!--    </button>-->
                    <!--</div>-->

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('employee-payrolls.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['user_id', 'is_current']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('user_id') && ($selectedUser = $users->firstWhere('id', request('user_id'))))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Employee: {{ $selectedUser->name }}
                            <a href="{{ route('employee-payrolls.index', array_merge(request()->except(['user_id', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('is_current') !== null && request('is_current') !== '')
                        <span class="filter-tag">
                            <i class="feather-toggle-right"></i>
                            Status: {{ request('is_current') == '1' ? 'Current' : 'History' }}
                            <a href="{{ route('employee-payrolls.index', array_merge(request()->except(['is_current', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('employee-payrolls.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="alert-custom alert-success">
                <i class="feather-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">
                    <i class="feather-x"></i>
                </button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert-custom alert-danger">
                <i class="feather-alert-circle"></i>
                <span>{{ session('error') }}</span>
                <button type="button" class="close" onclick="this.parentElement.style.display='none'">
                    <i class="feather-x"></i>
                </button>
            </div>
        @endif

        <!-- Employee Payroll Table -->
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="feather-credit-card me-2"></i>
                    Employee Payroll Records
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payroll Code</th>
                                <th>Employee</th>
                                <th>Basic Salary</th>
                                <th>Gross Salary</th>
                                <th>Net Salary</th>
                                <th>CTC</th>
                                <th>Effective From</th>
                                <th>Effective To</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employeePayrolls as $payroll)
                                <tr>
                                    <td data-label="Payroll Code">
                                        <span class="payroll-code">{{ $payroll->payroll_code }}</span>
                                    </td>
                                    <td data-label="Employee">
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($payroll->user->name ?? 'NA', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">{{ $payroll->user->name ?? 'N/A' }}
                                                    <small>({{ $payroll->user->employee_id ?? 'N/A' }})</small>
                                                </div>
                                                <div class="employee-id">{{ $payroll->user->email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Basic Salary">
                                        <span class="amount">₹{{ number_format($payroll->basic_salary, 2) }}</span>
                                    </td>
                                    <td data-label="Gross Salary">
                                        <span class="amount">₹{{ number_format($payroll->gross_salary, 2) }}</span>
                                    </td>
                                    <td data-label="Net Salary">
                                        <span class="amount">₹{{ number_format($payroll->net_salary, 2) }}</span>
                                    </td>
                                    <td data-label="CTC">
                                        <span class="amount">₹{{ number_format($payroll->ctc, 2) }}</span>
                                    </td>
                                    <td data-label="Effective From">
                                        <span
                                            class="date-text">{{ \Carbon\Carbon::parse($payroll->effective_from)->format('d M, Y') }}</span>
                                    </td>
                                    <td data-label="Effective To">
                                        @if ($payroll->effective_to)
                                            <span
                                                class="date-text">{{ \Carbon\Carbon::parse($payroll->effective_to)->format('d M, Y') }}</span>
                                        @else
                                            <span class="badge-status badge-current"
                                                style="font-size: 11px;">Current</span>
                                        @endif
                                    </td>
                                    <td data-label="Status">
                                        @if ($payroll->is_current)
                                            <span class="badge-status badge-current">
                                                <i class="feather-check-circle"></i>
                                                Current
                                            </span>
                                        @else
                                            <span class="badge-status badge-history">
                                                <i class="feather-clock"></i>
                                                History
                                            </span>
                                        @endif
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">
                                            <a href="{{ route('employee-payrolls.edit', $payroll->id) }}"
                                                class="btn-action edit" title="Edit Payroll">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                            <form action="{{ route('employee-payrolls.destroy', $payroll->id) }}"
                                                method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action delete"
                                                    onclick="return confirm('Are you sure you want to delete this payroll?')"
                                                    title="Delete Payroll">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center">
                                        <div class="empty-state">
                                            <i class="fas fa-rupee-sign"></i>
                                            <h6>No Payroll Records Found</h6>
                                            <p>Get started by assigning payroll to an employee</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if (method_exists($employeePayrolls, 'links') && $employeePayrolls->hasPages())
                <div class="card-footer py-2 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $employeePayrolls->firstItem() }} to {{ $employeePayrolls->lastItem() }} of
                            {{ $employeePayrolls->total() }} entries
                        </div>
                        <div class="remove-internal-para">
                            {{ $employeePayrolls->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Bootstrap dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
            dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });

            // Auto-submit on filter select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });
        });
    </script>
@endsection
