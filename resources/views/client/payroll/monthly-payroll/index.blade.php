{{-- resources/views/client/payroll/monthly-payroll/index.blade.php --}}
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
            border-color: #0D6EFD;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: #0D6EFD;
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
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #EFF6FF;
            color: #0D6EFD;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #0D6EFD !important;
            opacity: 0.8;
        }

        .employee-name {
            color: #1e293b;
            font-weight: 500;
        }

        .text-muted {
            color: #64748b !important;
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
            font-size: 14px;
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
            border-color: #0D6EFD;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
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
            background: #0B5ED7;
            transform: translateY(-1px);
        }

        .apply-btn i {
            font-size: 14px;
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
            color: var(--icon-color, #0D6EFD);
            font-size: 11px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
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

        .filter-tag.clear-all:hover {
            background: #0D6EFD;
            color: white;
        }

        .filter-tag.clear-all i {
            color: currentColor;
        }

        /* .stats-grid/.stats-card/.stats-icon/.stats-info are centralized
           in client.layout.head (single blue-only theme) — no local copy. */

        /* ==================== STATUS BADGES ==================== */
        .status-badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .status-badge i {
            font-size: 11px;
        }

        .status-pending {
            background: #EFF6FF;
            color: #0D6EFD;
            border: 1px solid #93c5fd;
        }

        .status-processed {
            background: #bfd3f7;
            color: #0D6EFD;
            border: 1px solid #60a5fa;
        }

        .status-paid {
            background: #0D6EFD;
            color: #ffffff;
            border: 1px solid #0D6EFD;
        }

        .status-cancelled {
            background: #e2e8f0;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-container {
            background: white;
            border-radius: 14px;
            border: 1px solid #edf2f7;
            overflow: hidden;
            margin-bottom: 25px;
        }





        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD);
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

        .attendance-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .attendance-days {
            font-weight: 600;
            color: #1e293b;
            font-size: 12px;
        }

        .attendance-breakdown {
            font-size: 10px;
            color: #64748b;
            display: flex;
            gap: 8px;
        }

        .amount-positive {
            font-weight: 600;
            color: #1e293b;
            font-size: 12px;
        }

        .amount-negative {
            font-weight: 600;
            color: #0D6EFD;
            font-size: 12px;
        }

        .net-amount {
            font-weight: 700;
            color: #1e293b;
            font-size: 13px;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
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
        }

        .btn-action:hover {
            background: #0D6EFD;
            color: white;
            border-color: #0D6EFD;
            transform: translateY(-2px);
        }

        .btn-action i {
            font-size: 14px;
        }

        /* ==================== BULK ACTIONS ==================== */
        .bulk-actions-bar {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .selected-count {
            background: #EFF6FF;
            color: #0D6EFD;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .bulk-actions-dropdown {
            position: relative;
        }

        .bulk-actions-btn {
            background: #0D6EFD;
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .bulk-actions-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            min-width: 160px;
            display: none;
            z-index: 100;
            margin-top: 4px;
        }

        .bulk-actions-menu.show {
            display: block;
        }

        .bulk-actions-menu a {
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e293b;
            font-size: 11px;
            text-decoration: none;
            transition: background 0.2s;
        }

        .bulk-actions-menu a:hover {
            background: #f8fafc;
        }

        .bulk-actions-menu a i {
            color: var(--icon-color, #0D6EFD);
            font-size: 13px;
        }





        /* ==================== MODAL STYLES ==================== */
        .modal-content {
            border: none;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0;
        }

        .modal-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header h5 i {
            color: var(--icon-color, #0D6EFD);
            font-size: 16px;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {


        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }





            .apply-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .bulk-actions-dropdown {
                width: 100%;
            }

            .bulk-actions-btn {
                width: 100%;
                justify-content: center;
            }

            .table-container {
                overflow-x: auto;
            }

        }

        /* Toastr Customization */
        .toast-success {
            background-color: #10b981 !important;
        }

        .toast-error {
            background-color: #ef4444 !important;
        }

        .toast-warning {
            background-color: #f59e0b !important;
        }

        .toast-info {
            background-color: #3b82f6 !important;
        }
    </style>
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content-area')
    <x-ui.page-header title="Monthly Payroll Management" current="Monthly Payroll"
        :crumbs="[['label' => 'Payroll', 'url' => route('monthly-payrolls.index')]]">
        <x-slot:actions>
            <a href="{{ route('monthly-payrolls.create') }}" class="btn btn-sm btn-primary">
                <i class="feather-plus me-2"></i>Process New Month
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        @php
            // $totalNet / $paidAmount are passed in from the controller, aggregated against the
            // full filtered set -- NOT derived from $monthlyPayrolls (only the current page of 15).
            $totalEmployees = $monthlyPayrolls->total();
            $paymentProgress = $totalNet > 0 ? round(($paidAmount / $totalNet) * 100, 1) : 0;
        @endphp

        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="feather-users"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $totalEmployees }}</h3>
                    <p>Total Employees</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fa fa-inr"></i>
                </div>
                <div class="stats-info">
                    <h3>₹{{ number_format($totalNet / 100000, 1) }}L</h3>
                    <p>Total Payroll</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="feather-credit-card"></i>
                </div>
                <div class="stats-info">
                    <h3>₹{{ number_format($paidAmount / 100000, 1) }}L</h3>
                    <p>Paid Amount</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="feather-bar-chart-2"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $paymentProgress }}%</h3>
                    <p>Progress</p>
                </div>
            </div>
        </div>

        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Payroll Records
                    @php
                        $activeFilterCount = collect(request()->only(['month', 'user_id', 'status']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['month', 'user_id', 'status']))
                    <a href="{{ route('monthly-payrolls.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('monthly-payrolls.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Month Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="month">
                            <option value="">Select Month</option>
                            @for ($i = 0; $i < 12; $i++)
                                @php
                                    $date = now()->subMonths($i);
                                    $monthValue = $date->format('Y-m');
                                    $monthName = $date->format('F Y');
                                @endphp
                                <option value="{{ $monthValue }}"
                                    {{ request('month', date('Y-m')) == $monthValue ? 'selected' : '' }}>
                                    {{ $monthName }}
                                </option>
                            @endfor
                        </select>
                    </div>

                    <!-- Custom Employee Dropdown with Initials -->
                    <div class="filter-item">
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
                                        href="{{ route('monthly-payrolls.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($users as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('monthly-payrolls.index', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
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

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="processed" {{ request('status') == 'processed' ? 'selected' : '' }}>Processed
                            </option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
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
                        <a href="{{ route('monthly-payrolls.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['month', 'user_id', 'status']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('month'))
                        @php
                            $monthDate = \Carbon\Carbon::createFromFormat('Y-m', request('month'));
                            $monthDisplay = $monthDate ? $monthDate->format('F Y') : request('month');
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            Month: {{ $monthDisplay }}
                            <a href="{{ route('monthly-payrolls.index', array_merge(request()->except(['month', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('user_id') && ($selectedEmployee = $users->firstWhere('id', request('user_id'))))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Employee: {{ $selectedEmployee->name }}
                            <a href="{{ route('monthly-payrolls.index', array_merge(request()->except(['user_id', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('monthly-payrolls.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('monthly-payrolls.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    toastr.success("{{ session('success') }}");
                });
            </script>
        @endif

        @if (session('error'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    toastr.error("{{ session('error') }}");
                });
            </script>
        @endif

        @if (session('warning'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    toastr.warning("{{ session('warning') }}");
                });
            </script>
        @endif

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar">
            <div class="selected-count" id="selectedCountDisplay">
                <i class="feather-check-square"></i>
                <span id="selectedCount">0</span> items selected
            </div>
            <div class="bulk-actions-dropdown">
                <button class="bulk-actions-btn" onclick="toggleBulkMenu()">
                    <i class="feather-sliders"></i>
                    Bulk Actions
                    <i class="feather-chevron-down"></i>
                </button>
                <div class="bulk-actions-menu" id="bulkMenu">
                    <a href="#" onclick="bulkUpdate('processed', event)">
                        <i class="feather-check-circle"></i> Mark as Processed
                    </a>
                    <a href="#" onclick="bulkUpdate('paid', event)">
                        <i class="feather-credit-card"></i> Mark as Paid
                    </a>
                    <a href="#" onclick="bulkUpdate('cancelled', event)">
                        <i class="feather-x-circle"></i> Mark as Cancelled
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" onclick="exportSelected(event)">
                        <i class="feather-download"></i> Export Selected
                    </a>
                </div>
            </div>
        </div>

        <!-- Monthly Payroll Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Employee</th>
                            <th>Month</th>
                            <th>Attendance</th>
                            <th>Earnings</th>
                            <th>Deductions</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($monthlyPayrolls as $payroll)
                            <tr>
                                <td>
                                    <input type="checkbox" class="select-row" value="{{ $payroll->id }}">
                                </td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($payroll->user->name ?? 'NA', 0, 2)) }}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">{{ $payroll->user->name ?? 'N/A' }} <small
                                                    class="text-muted">({{ $payroll->user->employee_id ?? '' }})</small>
                                            </div>
                                            <div class="employee-id">{{ $payroll->user->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        class="attendance-days">{{ \Carbon\Carbon::createFromFormat('Y-m', $payroll->payroll_month)->format('M Y') }}</span>
                                    <div class="employee-id">{{ $payroll->payroll_month }}</div>
                                </td>
                                <td>
                                    <div class="attendance-info">
                                        <span
                                            class="attendance-days">{{ $payroll->payable_days }}/{{ $payroll->total_working_days }}</span>
                                        <div class="attendance-breakdown">
                                            <span><i class="feather-calendar"></i> L:{{ $payroll->paid_leaves }}</span>
                                            {{-- <span><i class="feather-clock"></i> OT:{{ $payroll->overtime_hours }}h</span> --}}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount-positive">₹{{ number_format($payroll->gross_earnings, 0) }}</span>
                                    <div class="employee-id">Basic: ₹{{ number_format($payroll->basic_salary, 0) }}</div>
                                </td>
                                <td>
                                    <span
                                        class="amount-negative">-₹{{ number_format($payroll->total_deductions, 0) }}</span>
                                    <div class="employee-id">PF: ₹{{ number_format($payroll->provident_fund, 0) }}</div>
                                </td>
                                <td>
                                    <span class="net-amount">₹{{ number_format($payroll->net_payable, 0) }}</span>
                                </td>
                                <td>
                                    @php
                                        $statusClass = '';
                                        switch ($payroll->payment_status) {
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                break;
                                            case 'processed':
                                                $statusClass = 'status-processed';
                                                break;
                                            case 'paid':
                                                $statusClass = 'status-paid';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'status-cancelled';
                                                break;
                                        }
                                    @endphp
                                    <span class="status-badge {{ $statusClass }}">
                                        <i
                                            class="feather-{{ $payroll->payment_status == 'paid' ? 'check' : ($payroll->payment_status == 'pending' ? 'clock' : 'circle') }}"></i>
                                        {{ ucfirst($payroll->payment_status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($payroll->payment_date)
                                        <span>{{ \Carbon\Carbon::parse($payroll->payment_date)->format('d M y') }}</span>
                                        <div class="employee-id">{{ $payroll->payment_mode ?? 'N/A' }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="{{ route('monthly-payrolls.show', $payroll->id) }}" class="btn-action"
                                            title="View Details">
                                            <i class="feather-eye"></i>
                                        </a>
                                        @if (in_array($payroll->payment_status, ['processed', 'paid']))
                                            <a href="{{ route('monthly-payrolls.payslip', $payroll->id) }}"
                                                class="btn-action" title="Download Payslip" target="_blank">
                                                <i class="feather-download"></i>
                                            </a>
                                        @endif
                                        @if ($payroll->payment_status == 'pending')
                                            <a href="{{ route('monthly-payrolls.edit', $payroll->id) }}"
                                                class="btn-action edit-pending" title="Edit Payroll">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                        @endif
                                        @if (in_array($payroll->payment_status, ['pending', 'processed']))
                                            <button type="button" class="btn-action"
                                                onclick="showStatusModal({{ $payroll->id }}, '{{ $payroll->payment_status }}')"
                                                title="Update Status">
                                                <i class="feather-toggle-right"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="feather-inbox"></i>
                                        <h6>No Payroll Records Found</h6>
                                        <p class="text-muted">No payroll records match your selected filters.</p>
                                        <a href="{{ route('monthly-payrolls.create') }}" class="btn btn-sm btn-primary">
                                            <i class="feather-plus me-2"></i>Process New Payroll
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($monthlyPayrolls, 'links') && $monthlyPayrolls->hasPages())
                <x-ui.pagination-footer :paginator="$monthlyPayrolls" label="employees" />
            @endif
        </div>

    </div>

    <!-- Bulk Update Form -->
    <form id="bulkForm" method="POST" style="display: none;">
        @csrf
        <div id="bulkIdsContainer"></div>
        <input type="hidden" name="payment_status" id="bulkStatus">
    </form>
@endsection

@section('create-modal')
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">
                        <i class="feather-edit-2 me-2"></i>
                        Update Payment Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="statusForm" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" id="payment_status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="processed">Processed</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group mb-3" id="paymentDetails" style="display: none;">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}">
                            <label class="form-label mt-3">Payment Mode</label>
                            <select name="payment_mode" class="form-select">
                                <option value="">Select Mode</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="Online">Online</option>
                            </select>
                            <label class="form-label mt-3">Transaction Reference</label>
                            <input type="text" name="transaction_reference" class="form-control"
                                placeholder="Cheque/Transaction No.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000",
            "extendedTimeOut": "2000"
        };

        let currentPayrollId = null;
        let currentStatus = null;

        $(document).ready(function() {
            // Initialize Bootstrap dropdowns manually
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
            dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });

            // Alternative: Direct dropdown toggle using click event
            $('#employeeDropdown').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var dropdown = bootstrap.Dropdown.getInstance(this);
                if (dropdown) {
                    dropdown.toggle();
                } else {
                    new bootstrap.Dropdown(this).toggle();
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.custom-employee-dropdown').length) {
                    $('.custom-employee-dropdown .dropdown-menu').removeClass('show');
                }
            });

            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Select All functionality
            $('#selectAll').change(function() {
                $('.select-row').prop('checked', $(this).prop('checked'));
                updateSelectedCount();
            });

            $('.select-row').change(function() {
                updateSelectedCount();
                updateSelectAll();
            });

            updateSelectedCount();

            $('#payment_status').change(function() {
                if ($(this).val() == 'paid') {
                    $('#paymentDetails').slideDown(200);
                } else {
                    $('#paymentDetails').slideUp(200);
                }
            }).trigger('change');

            $(document).click(function(event) {
                if (!$(event.target).closest('.bulk-actions-dropdown').length) {
                    $('#bulkMenu').removeClass('show');
                }
            });
        });

        function updateSelectedCount() {
            let count = $('.select-row:checked').length;
            $('#selectedCount').text(count);
        }

        function updateSelectAll() {
            let total = $('.select-row').length;
            let checked = $('.select-row:checked').length;
            if (checked === 0) {
                $('#selectAll').prop('checked', false).prop('indeterminate', false);
            } else if (checked === total) {
                $('#selectAll').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#selectAll').prop('indeterminate', true);
            }
        }

        function toggleBulkMenu() {
            $('#bulkMenu').toggleClass('show');
        }

        function showStatusModal(id, currentStatusValue) {
            currentPayrollId = id;
            currentStatus = currentStatusValue;
            $('#payment_status').val(currentStatusValue);
            $('#payment_status').trigger('change');
            let url = "{{ route('monthly-payrolls.status', ':id') }}".replace(':id', id);
            $('#statusForm').attr('action', url);
            $('#statusModal').modal('show');
        }

        function bulkUpdate(status, event) {
            if (event) event.preventDefault();
            let selectedIds = [];
            $('.select-row:checked').each(function() {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) {
                toastr.error('Please select at least one record to update');
                return;
            }
            let statusText = status.charAt(0).toUpperCase() + status.slice(1);
            if (confirm(`Are you sure you want to mark ${selectedIds.length} record(s) as ${statusText}?`)) {
                // Real ids[] hidden inputs (not a JSON-encoded string) so
                // Laravel parses `ids` as an actual array server-side,
                // matching the controller's 'ids' => 'required|array' rule.
                const container = $('#bulkIdsContainer').empty();
                selectedIds.forEach(function(id) {
                    container.append($('<input>', {type: 'hidden', name: 'ids[]', value: id}));
                });
                $('#bulkStatus').val(status);
                $('#bulkForm').attr('action', '{{ route('monthly-payrolls.bulk-update') }}').submit();
            }
        }

        function exportSelected(event) {
            if (event) event.preventDefault();
            let selectedIds = [];
            $('.select-row:checked').each(function() {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) {
                toastr.error('Please select at least one record to export');
                return;
            }
            toastr.info('Preparing export...');
            let form = $('<form>', {
                action: '{{ route('monthly-payrolls.export') }}',
                method: 'POST'
            });
            form.append($('<input>', {
                name: '_token',
                value: '{{ csrf_token() }}',
                type: 'hidden'
            }));
            form.append($('<input>', {
                name: 'ids',
                value: JSON.stringify(selectedIds),
                type: 'hidden'
            }));
            $('body').append(form);
            form.submit();
            form.remove();
            toastr.success('Export started successfully');
        }
    </script>
@endsection
