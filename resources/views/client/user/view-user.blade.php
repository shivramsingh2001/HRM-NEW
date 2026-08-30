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
            /* Single consistent color */
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
            background: #eef2ff;
            color: #4f46e5;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #4f46e5 !important;
            opacity: 0.8;
        }

        /* Employee name styling */
        .employee-name {
            color: #1e293b;
            font-weight: 500;
        }

        .text-muted {
            color: #64748b !important;
        }

        /* ==================== STATUS TOGGLE ==================== */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 22px;
            background: #e2e8f0;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-switch.active {
            background: #10b981;
        }

        .toggle-switch .toggle-circle {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .toggle-switch.active .toggle-circle {
            left: 24px;
        }

        .status-label {
            font-size: 13px;
            font-weight: 500;
        }

        .status-label.active {
            color: #10b981;
        }

        .status-label.inactive {
            color: #ef4444;
        }

        /* Add to your style section */
        .status-label.registered {
            color: #10b981;
        }

        .status-label.not-registered {
            color: #64748b;
        }

        .toggle-switch.face-toggle.active {
            background: #4f46e5;
            /* Different color for face register toggle */
        }

        /* ==================== ATTENDANCE TYPE SELECT ==================== */
        .attendance-type-select {
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 140px;
        }

        .attendance-type-select:hover {
            border-color: #4f46e5;
            background-color: white !important;
        }

        .attendance-type-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .attendance-type-select:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .attendance-type-select option {
            padding: 4px 8px;
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

        .filter-item.search {
            flex: 1 1 200px;
            min-width: 180px;
        }

        .filter-item .form-label {
            display: none;
        }

        /* Search Box */
        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }

        .search-wrapper .form-control {
            width: 100%;
            height: 36px;
            padding: 6px 12px 6px 32px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .search-wrapper .form-control:focus {
            background: white;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .search-wrapper .form-control::placeholder {
            color: #94a3b8;
            font-size: 12px;
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

        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .stats-card:hover {
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }

        .stats-icon i {
            font-size: 24px;
            color: #4f46e5;
        }

        .stats-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: #1e293b;
            line-height: 1.2;
        }

        .stats-info p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        /* ==================== EMPLOYEE AVATAR ==================== */
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .employee-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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

        .table tbody tr {
            transition: all 0.2s;
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

        .badge i {
            font-size: 11px;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .badge.bg-primary {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        /* ==================== ROLE TAGS ==================== */
        .role-tag {
            padding: 4px 10px;
            background: #f1f5f9;
            color: #334155;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }

        .role-tag.admin {
            background: #818cf8;
            color: white;
        }

        .role-tag.manager {
            background: #60a5fa;
            color: white;
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
        }

        .action-btn:hover {
            background: white;
            color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.1);
        }

        .action-btn i {
            font-size: 14px;
        }

        .dropdown-item {
            font-size: 12px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-item i {
            font-size: 14px;
            color: #64748b;
        }

        .dropdown-item:hover i {
            color: #4f46e5;
        }

        /* ==================== EMPLOYEE ID BADGE ==================== */
        .employee-id {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            font-family: monospace;
            display: inline-block;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 6px !important;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .page-item.active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
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

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .filter-row {
                gap: 8px;
            }

            .filter-item {
                flex: 1 1 calc(33.333% - 8px);
                min-width: 120px;
            }

            .filter-item.search {
                flex: 1 1 100%;
                min-width: 100%;
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

            .table th,
            .table td {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <!--<h5 class="m-b-10">Employees</h5>-->
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">View Employees</li>
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
                        <div class="dropdown-menu dropdown-menu-end" >
                            <a href="#" class="dropdown-item" onclick="exportToCSV()">
                                <i class="bi bi-filetype-csv me-3"></i>
                                <span>Export CSV</span>
                            </a>
                        </div>
                    </div>
                    <a href="{{ route('employee.create') }}" class="btn btn-sm btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Employee</span>
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

    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 30px !important;">
        <!-- Stats Cards -->


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
                <div class="stats-icon" style="background: rgba(16, 185, 129, 0.1);">
                    <i class="feather-check-circle" style="color: #10b981;"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $activeEmployees }}</h3>
                    <p>Active Employees</p>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon" style="background: rgba(239, 68, 68, 0.1);">
                    <i class="feather-x-circle" style="color: #ef4444;"></i>
                </div>
                <div class="stats-info">
                    <h3>{{ $inactiveEmployees }}</h3>
                    <p>Inactive Employees</p>
                </div>
            </div>
        </div>

        <!-- Compact Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Employees
                    @php
                        $activeFilterCount = collect(
                            request()->only(['search', 'department', 'role', 'status', 'designation']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['search', 'department', 'role', 'status', 'designation']))
                    <a href="{{ route('employee.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('employee.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item" style="min-width: 220px;">

                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('user_id') && ($selectedEmployee = $allEmployees->firstWhere('id', request('user_id'))))
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
                                        href="{{ route('employee.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($allEmployees  as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('employee.index', array_merge(request()->except(['page']), ['user_id' => $user->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $user->name }} (<small
                                                        class="text-muted">{{ $user->employee_id }})</small></span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <!-- Search Field -->
                    <!--<div class="filter-item search">-->
                    <!--    <div class="search-wrapper">-->
                    <!--        <i class="feather-search"></i>-->
                    <!--        <input type="text" class="form-control" name="search"-->
                    <!--            placeholder="Search by name, email, ID..." value="{{ request('search') }}">-->
                    <!--    </div>-->
                    <!--</div>-->

                    <!-- Department Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="department">
                            <option value="">All Departments</option>
                            @foreach ($departments ?? [] as $department)
                                <option value="{{ $department->id }}"
                                    {{ request('department') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Designation Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="designation">
                            <option value="">All Designation</option>
                            @foreach ($designations as $designation)
                                <option value="{{ $designation->id }}"
                                    {{ request('designation') == $designation->id ? 'selected' : '' }}>
                                    {{ $designation->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="role">
                            <option value="">All Roles</option>
                            <option value="employee" {{ request('role') == 'employee' ? 'selected' : '' }}>Employee
                            </option>
                            <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="hr" {{ request('role') == 'hr' ? 'selected' : '' }}>HR</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    {{-- <div class="filter-item" style="min-width: auto;">
                        <button type="submit" class="apply-btn">
                            <i class="feather-search"></i>
                            Apply
                        </button>
                    </div> --}}

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('employee.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['search', 'department', 'role', 'status']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('employee.index', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('department'))
                        <span class="filter-tag">
                            <i class="feather-briefcase"></i>
                            {{ request('department') }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['department', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('role'))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            {{ ucfirst(request('role')) }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['role', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('status') !== null && request('status') !== '')
                        <span class="filter-tag">
                            <i class="feather-toggle-right"></i>
                            {{ request('status') == '1' ? 'Active' : 'Inactive' }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('employee.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Employees Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Employees List</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $totalEmployees }}
                            </span>
                            <span class="badge bg-success">
                                <i class="feather-check me-1"></i>Active: {{ $activeEmployees }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="employeeList1">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Employee</th>
                                        {{-- <th>Employee ID</th> --}}
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Face Register</th>
                                        <th>Attendance Type</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <a href="{{ route('employee.show', ['id' => encrypt($user->id)]) }}">
                                                <div class="employee-info">
                                                    @if ($user->profile_image)
                                                        <img src="{{ asset($user->profile_image) }}"
                                                            class="employee-avatar" alt="{{ $user->name }}">
                                                    @else
                                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&size=36&background=4f46e5&color=fff"
                                                            class="employee-avatar" alt="{{ $user->name }}">
                                                    @endif
                                                    <div class="employee-details">
                                                        <div class="employee-name">{{ ucfirst($user->name) }} <span
                                                                class="text-muted"
                                                                style="font-size: 11px;">({{ $user->employee_id ?? 'N/A' }})</span>
                                                        </div>
                                                        <div class="employee-email">{{ $user->email }}</div>
                                                        
                                                    </div>
                                                </div>
                                                </a>
                                            </td>
                                            {{-- <td>
                                                <span class="employee-id">{{ $user->employee_id ?? 'N/A' }}</span>
                                            </td> --}}
                                            <td>{{ $user->designation ?? 'N/A' }}</td>
                                            <td>{{ $user->department ?? 'N/A' }}</td>
                                            <td>
                                                <span class="role-tag {{ $user->role }}">
                                                    {{ ucfirst($user->role) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div><i class="feather-phone me-1"
                                                        style="font-size: 11px;"></i>{{ $user->contact }}</div>
                                                @if ($user->alternate_phone)
                                                    <small class="text-muted"><i
                                                            class="feather-smartphone me-1"></i>{{ $user->alternate_phone }}</small>
                                                @endif
                                            </td>
                                            <!-- Replace the status column (around line 280) with this: -->
                                            <td>
                                                <div class="status-toggle">
                                                    <div class="toggle-switch {{ $user->status == 1 ? 'active' : '' }}"
                                                        onclick="toggleEmployeeStatus({{ $user->id }}, {{ $user->status }})">
                                                        <div class="toggle-circle"></div>
                                                    </div>
                                                    <span
                                                        class="status-label {{ $user->status == 1 ? 'active' : 'inactive' }}"
                                                        id="status-label-{{ $user->id }}">
                                                        {{ $user->status == 1 ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="status-toggle">
                                                    <div class="toggle-switch face-toggle {{ $user->face_register == 1 ? 'active' : '' }}"
                                                        onclick="toggleFaceRegister({{ $user->id }}, {{ $user->face_register }})">
                                                        <div class="toggle-circle"></div>
                                                    </div>
                                                    <span
                                                        class="status-label {{ $user->face_register == 1 ? 'registered' : 'not-registered' }}"
                                                        id="face-status-label-{{ $user->id }}">
                                                        {{ $user->face_register == 1 ? 'Registered' : 'Not Registered' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <!-- Attendance Type Column -->
                                            <td>
                                               
                                                <select class="form-select form-select-sm attendance-type-select" 
                                                        style="width: 100%; min-width: 140px; font-size: 12px; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; background-color: #f8fafc;"
                                                        data-user-id="{{ $user->id }}"
                                                        onchange="updateAttendanceType(this)">
                                                    <option value="manual_attendance" {{ $user->attendance_type == 'manual_attendance' ? 'selected' : '' }}>
                                                        📝 Manual
                                                    </option>
                                                    <option value="face_verification" {{ $user->attendance_type == 'face_verification' ? 'selected' : '' }}>
                                                        👤 Face Verification
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                        data-bs-offset="0,5">
                                                        <i class="feather-more-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('employee.show', ['id' => encrypt($user->id)]) }}">
                                                                <i class="feather-eye"></i>
                                                                <span>Details</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('employee.edit', ['id' => encrypt($user->id)]) }}">
                                                                <i class="feather-edit-3"></i>
                                                                <span>Edit</span>
                                                            </a>
                                                        </li>

                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Employees Found</h4>
                                                    <p class="text-muted">Get started by adding your first employee</p>
                                                    {{-- <a href="{{ route('employee.create') }}" class="btn btn-primary">
                                                        <i class="feather-plus me-2"></i>
                                                        Add Employee
                                                    </a> --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if (method_exists($users, 'links') && $users->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of
                                    {{ $users->total() }} entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $users->appends(request()->query())->links() }}
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
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000"
        };

        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Debounce search input
            let searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Direct Status Update Function for Employee Status
        function toggleEmployeeStatus(userId, currentStatus) {
            // Get the event to find the clicked element
            const event = window.event;
            const row = event.target.closest('tr');
            const toggle = row ? row.querySelector('.toggle-switch:not(.face-toggle)') : null;
            const statusLabel = document.getElementById(`status-label-${userId}`);

            // Disable the toggle during update
            if (toggle) {
                toggle.style.pointerEvents = 'none';
                toggle.style.opacity = '0.6';
            }

            // Calculate new status
            const newStatus = currentStatus == 1 ? 0 : 1;
            const newStatusText = newStatus == 1 ? 'Active' : 'Inactive';

            $.ajax({
                url: "{{ route('employee.toggle-status') }}",
                type: "POST",
                data: {
                    id: userId,
                    status: currentStatus,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(`Status updated successfully`);

                        // Update the toggle UI
                        if (toggle && statusLabel) {
                            // Update toggle state
                            if (newStatus == 1) {
                                toggle.classList.add('active');
                            } else {
                                toggle.classList.remove('active');
                            }

                            // Update status label
                            statusLabel.textContent = newStatusText;
                            statusLabel.className = `status-label ${newStatus == 1 ? 'active' : 'inactive'}`;

                            // Update the onclick attribute with new status
                            toggle.setAttribute('onclick', `toggleEmployeeStatus(${userId}, ${newStatus})`);
                        }

                        // Re-enable the toggle
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    } else {
                        toastr.error(response.message || 'Error updating status');
                        // Re-enable the toggle on error
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating status. Please try again.');
                    console.error(xhr);

                    // Re-enable the toggle on error
                    if (toggle) {
                        toggle.style.pointerEvents = '';
                        toggle.style.opacity = '';
                    }
                }
            });
        }

        // Direct Status Update Function for Face Register Status
        function toggleFaceRegister(userId, currentFaceStatus) {
            // Get the event to find the clicked element
            const event = window.event;
            const row = event.target.closest('tr');
            const toggle = row ? row.querySelector('.toggle-switch.face-toggle') : null;
            const statusLabel = document.getElementById(`face-status-label-${userId}`);

            // Disable the toggle during update
            if (toggle) {
                toggle.style.pointerEvents = 'none';
                toggle.style.opacity = '0.6';
            }

            // Calculate new status
            const newStatus = currentFaceStatus == 1 ? 0 : 1;
            const newStatusText = newStatus == 1 ? 'Registered' : 'Not Registered';

            $.ajax({
                url: "{{ route('employee.toggle-face-register') }}",
                type: "POST",
                data: {
                    id: userId,
                    face_status: currentFaceStatus,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(`Face register status updated successfully`);

                        // Update the toggle UI
                        if (toggle && statusLabel) {
                            // Update toggle state
                            if (newStatus == 1) {
                                toggle.classList.add('active');
                            } else {
                                toggle.classList.remove('active');
                            }

                            // Update status label
                            statusLabel.textContent = newStatusText;
                            statusLabel.className =
                                `status-label ${newStatus == 1 ? 'registered' : 'not-registered'}`;

                            // Update the onclick attribute with new status
                            toggle.setAttribute('onclick', `toggleFaceRegister(${userId}, ${newStatus})`);
                        }

                        // Re-enable the toggle
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    } else {
                        toastr.error(response.message || 'Error updating face register status');
                        // Re-enable the toggle on error
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating face register status. Please try again.');
                    console.error(xhr);

                    // Re-enable the toggle on error
                    if (toggle) {
                        toggle.style.pointerEvents = '';
                        toggle.style.opacity = '';
                    }
                }
            });
        }

        // Update Attendance Type
        function updateAttendanceType(selectElement) {
            const userId = selectElement.getAttribute('data-user-id');
            const attendanceType = selectElement.value;
            
            const originalBg = selectElement.style.backgroundColor;
            selectElement.style.backgroundColor = '#fef3c7';
            selectElement.disabled = true;
            
            $.ajax({
                url: "{{ route('employee.update-attendance-type') }}",
                type: "POST",
                data: {
                    id: userId,
                    attendance_type: attendanceType,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Attendance type updated successfully');
                        selectElement.style.backgroundColor = '#d1fae5';
                        
                        setTimeout(() => {
                            selectElement.style.backgroundColor = originalBg || '#f8fafc';
                            selectElement.disabled = false;
                        }, 1000);
                    } else {
                        toastr.error(response.message || 'Error updating attendance type');
                        const previousValue = selectElement.getAttribute('data-previous-value');
                        if (previousValue) {
                            selectElement.value = previousValue;
                        }
                        selectElement.style.backgroundColor = '#fee2e2';
                        selectElement.disabled = false;
                        
                        setTimeout(() => {
                            selectElement.style.backgroundColor = originalBg || '#f8fafc';
                        }, 2000);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating attendance type. Please try again.');
                    console.error(xhr);
                    
                    const previousValue = selectElement.getAttribute('data-previous-value');
                    if (previousValue) {
                        selectElement.value = previousValue;
                    }
                    selectElement.style.backgroundColor = '#fee2e2';
                    selectElement.disabled = false;
                    
                    setTimeout(() => {
                        selectElement.style.backgroundColor = originalBg || '#f8fafc';
                    }, 2000);
                }
            });
        }

        // Store previous value when dropdown opens
        $(document).on('focus', '.attendance-type-select', function() {
            $(this).attr('data-previous-value', $(this).val());
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('employeeList1');
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
                // Skip empty headers or actions if needed
                let headerText = headerCells[i].innerText.trim();
                if (headerText && headerText !== 'Actions') {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length; j++) {
                    // Skip actions column if needed (last column)
                    if (j === cols.length - 1) continue;

                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
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
            downloadLink.download = 'employees_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }

        // Apply filters
        function applyFilters() {
            $('#filterForm').submit();
        }

        // Reset filters
        function resetFilters() {
            window.location.href = "{{ route('employee.index') }}";
        }
    </script>
@endsection