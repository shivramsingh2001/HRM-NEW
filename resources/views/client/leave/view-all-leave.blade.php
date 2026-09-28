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
                font-size: 11.5px;
                padding: 0 12px;
            }

            .custom-employee-dropdown .btn:hover {
                background: #ffffff;
                border-color: #cbd5e1;
            }

            .custom-employee-dropdown .btn:focus {
                border-color: #1e3a8a;
                box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            }

            /* Consistent initials style - same color for all */
            .employee-initials,
            .employee-initials-sm {
                width: 28px;
                height: 28px;
                background: #1e3a8a;
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
                font-size: 11.5px;
                color: #1e293b;
                margin-bottom: 2px;
            }

            .custom-employee-dropdown .dropdown-item:hover {
                background: #f1f5f9;
            }

            .custom-employee-dropdown .dropdown-item.active {
                background: #e3edfe;
                color: #1e3a8a;
            }

            .custom-employee-dropdown .dropdown-item.active .text-muted {
                color: #1e3a8a !important;
                opacity: 0.8;
            }

            /* .employee-name/.employee-email are centralized in client.layout.head
               (12px/600-weight name, 8px email) — no local override here. */

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
                background: #3b82f6;
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
                font-size: 11.5px;
                font-weight: 500;
            }

            .status-label.active {
                color: #3b82f6;
            }

            .status-label.inactive {
                color: #1e3a8a;
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
                font-size: 12px;
                font-weight: 600;
                color: #1e293b;
            }

            .filter-title i {
                color: #1e3a8a;
                font-size: 13px;
            }

            .filter-title span {
                background: #e3edfe;
                color: #1e3a8a;
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
                background: #e3edfe;
                color: #1e3a8a;
            }

            .clear-all-link i {
                font-size: 12px;
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
                font-size: 12px;
                pointer-events: none;
            }

            .search-wrapper .form-control {
                width: 100%;
                height: 36px;
                padding: 6px 12px 6px 32px;
                font-size: 11.5px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background: #f8fafc;
                transition: all 0.2s;
            }

            .search-wrapper .form-control:focus {
                background: white;
                border-color: #1e3a8a;
                box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
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
                border-color: #1e3a8a;
                box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
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
                background: #1e3a8a;
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
                background: #1e3a8a;
                transform: translateY(-1px);
            }

            .apply-btn i {
                font-size: 12px;
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
                color: #1e3a8a;
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
                color: #1e3a8a;
            }

            .filter-tag.clear-all {
                background: #e3edfe;
                border-color: #1e3a8a;
                color: #1e3a8a;
                font-weight: 600;
                text-decoration: none;
                padding: 3px 10px;
            }

            .filter-tag.clear-all:hover {
                background: #1e3a8a;
                color: white;
            }

            .filter-tag.clear-all i {
                color: currentColor;
            }

            /* ==================== STATS CARDS (compact, like the dashboard's
               Expense Overview tiles) ==================== */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: .75rem;
                margin-bottom: 1rem;
            }

            .stats-card {
                background: white;
                border: 1px solid #eaeef5;
                border-radius: 10px;
                padding: 12px;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: all 0.2s;
                box-shadow: 0 1px 2px rgba(20, 30, 60, .04);
            }

            .stats-card:hover {
                box-shadow: 0 4px 12px -4px rgba(30, 50, 110, .12);
                border-color: #dfe5f0;
                transform: translateY(-1px);
            }

            .stats-icon {
                width: 32px;
                height: 32px;
                background: #e3edfe;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex: none;
            }

            .stats-icon i {
                font-size: 12px;
                color: #1e3a8a;
            }

            .stats-info h3 {
                font-size: 15px;
                font-weight: 800;
                margin: 0 0 1px 0;
                color: #1a2236;
                line-height: 1.2;
            }

            .stats-info p {
                font-size: 9.5px;
                font-weight: 600;
                color: #6b7385;
                margin: 0;
            }

            /* .employee-avatar/.employee-info/.employee-details/.employee-name/
               .employee-email are centralized in client.layout.head — no local copy. */

            /* ==================== TABLE STYLES ====================
               font-size and td padding are centralized in client.layout.head
               (11.5px / 3px 15px) — no local copy, only this page's own
               margin-bottom addition stays. */
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
                font-size: 11.5px;
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
                background: #e3edfe !important;
                color: #1d4ed8;
            }

            .badge.bg-danger {
                background: #e3edfe !important;
                color: #1e3a8a;
            }

            .badge.bg-primary {
                background: #e0f2fe !important;
                color: #0369a1;
            }

            .badge.bg-warning {
                background: #e3edfe !important;
                color: #2563eb;
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
                background: #93c5fd;
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
                color: #1e3a8a;
                border-color: #1e3a8a;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(30, 58, 138, 0.1);
            }

            .action-btn i {
                font-size: 12px;
            }

            .dropdown-item {
                font-size: 12px;
                padding: 8px 16px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .dropdown-item i {
                font-size: 12px;
                color: #64748b;
            }

            .dropdown-item:hover i {
                color: #1e3a8a;
            }

            /* ==================== REASON TEXT (NO SCROLLBAR) ==================== */
            .reason-text {
                max-width: 250px;
                white-space: normal;
                word-wrap: break-word;
                line-height: 1.4;
                font-size: 11.5px;
            }

            /* ==================== MODAL STYLES ==================== */
            .modal-content {
                border-radius: 16px;
                border: none;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }

            .detail-row {
                display: flex;
                margin-bottom: 16px;
                padding-bottom: 12px;
                border-bottom: 1px solid #f1f5f9;
            }

            .detail-label {
                width: 140px;
                font-weight: 600;
                color: #475569;
                font-size: 11.5px;
            }

            .detail-value {
                flex: 1;
                color: #1e293b;
                font-size: 11.5px;
            }

            .file-preview {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 8px 16px;
                background: #f8fafc;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                text-decoration: none;
                color: #475569;
                transition: all 0.2s;
            }

            .file-preview:hover {
                background: #e3edfe;
                border-color: #1e3a8a;
                color: #1e3a8a;
            }

            .file-preview i {
                font-size: 14px;
            }

            .reason-box {
                background: #f8fafc;
                padding: 12px 16px;
                border-radius: 8px;
                border-left: 3px solid #1e3a8a;
                font-size: 11.5px;
                line-height: 1.6;
                color: #334155;
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
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .empty-state p {
                color: #64748b;
                font-size: 12px;
                margin-bottom: 20px;
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
                background: #1e3a8a;
                border-color: #1e3a8a;
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

                .detail-row {
                    flex-direction: column;
                }

                .detail-label {
                    width: 100%;
                    margin-bottom: 4px;
                }
            }
        </style>
    @endsection

    @section('content-area')
        <!-- [ page-header ] start -->
        <x-ui.page-header title="Team Leave Applications" :parent="['label' => 'Leave', 'route' => 'leave.view-all']" />
        <!-- [ page-header ] end -->

        <!-- [ Main Content ] start -->
        <div class="main-content" style="padding: 18px !important;">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="feather-calendar"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $totalLeaves ?? 0 }}</h3>
                        <p>Total Leaves</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon" style="background: rgba(96, 165, 250, 0.1);">
                        <i class="feather-clock" style="color: #60a5fa;"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $pendingLeaves ?? 0 }}</h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon" style="background: rgba(59, 130, 246, 0.1);">
                        <i class="feather-check-circle" style="color: #3b82f6;"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $approvedLeaves ?? 0 }}</h3>
                        <p>Approved Leaves</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon" style="background: rgba(30, 58, 138, 0.1);">
                        <i class="feather-x-circle" style="color: #1e3a8a;"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $cancelledLeaves ?? 0 }}</h3>
                        <p>Cancelled/Rejected</p>
                    </div>
                </div>
            </div>

            <!-- Compact Filter Section -->
            <div class="filter-wrapper">
                <div class="filter-header">
                    <div class="filter-title">
                        <i class="feather-filter"></i>
                        Filter Leave Applications
                        @php
                            $activeFilterCount = collect(request()->only(['status', 'from_date', 'to_date']))
                                ->filter()
                                ->count();
                        @endphp
                        @if ($activeFilterCount > 0)
                            <span>{{ $activeFilterCount }} active</span>
                        @endif
                    </div>
                    @if (request()->hasAny(['status', 'from_date', 'to_date']))
                        <a href="{{ route('leave.view-all') }}" class="clear-all-link">
                            <i class="feather-x"></i>
                            Clear All
                        </a>
                    @endif
                </div>

                <form action="{{ route('leave.view-all') }}" method="GET" id="filterForm">
                    <div class="filter-row">
                        <!-- Custom Employee Dropdown with Initials -->
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
                                            href="{{ route('leave.view-all', array_merge(request()->except(['user_id', 'page']))) }}">
                                            <span>All Employees</span>
                                        </a>
                                    </li>
                                    @foreach ($employees as $employee)
                                        @php
                                            $initials = strtoupper(substr($employee->name, 0, 2));
                                        @endphp
                                        <li>
                                            <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                                href="{{ route('leave.view-all', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
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
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>
                                    Approved
                                </option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                                    Cancelled/Rejected</option>
                            </select>
                        </div>

                        <!-- From Date Filter -->
                        <div class="filter-item">
                            <input type="date" class="form-control" name="from_date"
                                value="{{ request('from_date') }}" placeholder="From Date"
                                style="padding: .375rem .75rem !important;">
                        </div>

                        <!-- To Date Filter -->
                        <div class="filter-item">
                            <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}"
                                placeholder="To Date" style="padding: .375rem .75rem !important;">
                        </div>

                        <!-- Action Buttons -->
                        {{-- <div class="filter-item" style="min-width: auto;">
                            <button type="submit" class="apply-btn">
                                <i class="feather-search"></i>
                                Apply
                            </button>
                        </div> --}}

                        <div class="filter-item" style="min-width: auto;">
                            <a href="{{ route('leave.view-all') }}" class="reset-btn">
                                <i class="feather-refresh-cw"></i>
                                Reset
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Active Filter Tags -->
                @if (request()->hasAny(['status', 'from_date', 'to_date']))
                    <div class="active-filters">
                        <span class="active-filters-label">Active:</span>

                        @if (request('status'))
                            <span class="filter-tag">
                                <i class="feather-activity"></i>
                                Status: {{ ucfirst(request('status')) }}
                                <a href="{{ route('leave.view-all', array_merge(request()->except(['status', 'page']))) }}"
                                    class="remove-tag">
                                    <i class="feather-x"></i>
                                </a>
                            </span>
                        @endif

                        @if (request('from_date'))
                            <span class="filter-tag">
                                <i class="feather-calendar"></i>
                                From: {{ request('from_date') }}
                                <a href="{{ route('leave.view-all', array_merge(request()->except(['from_date', 'page']))) }}"
                                    class="remove-tag">
                                    <i class="feather-x"></i>
                                </a>
                            </span>
                        @endif

                        @if (request('to_date'))
                            <span class="filter-tag">
                                <i class="feather-calendar"></i>
                                To: {{ request('to_date') }}
                                <a href="{{ route('leave.view-all', array_merge(request()->except(['to_date', 'page']))) }}"
                                    class="remove-tag">
                                    <i class="feather-x"></i>
                                </a>
                            </span>
                        @endif

                        <a href="{{ route('leave.view-all') }}" class="filter-tag clear-all">
                            <i class="feather-refresh-cw"></i>
                            Clear All
                        </a>
                    </div>
                @endif
            </div>

            <!-- Leaves Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card stretch stretch-full">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Leave Applications</h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-info">
                                    <i class="feather-list me-1"></i>Total: {{ $leaves->total() }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table" id="leaveList">
                                    <thead>
                                        <tr>
                                            <th width="50">#</th>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Date & Session</th>
                                            <th>Reason</th>
                                            <th>Attachment</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($leaves as $leave)
                                            <tr data-employee-name="{{ $leave->user_name }}"
                                                data-employee-id="{{ $leave->employee_id ?? 'N/A' }}"
                                                data-employee-email="{{ $leave->user_email }}"
                                                data-leave-type="{{ $leave->leaveType->name ?? '' }}"
                                                data-start-date="{{ date('d M Y', strtotime($leave->start_date)) }}"
                                                data-session="{{ ucfirst($leave->start_session) }}"
                                                data-reason="{{ $leave->reason ?? 'No reason provided' }}"
                                                data-status="{{ $leave->status }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td>
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background: #1e3a8a; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                            {{ strtoupper(substr($leave->user_name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">{{ $leave->user_name ?? 'N/A' }}
                                                                <small
                                                                    class="text-muted employee-email">({{ $leave->employee_id ?? 'N/A' }})</small>
                                                            </div>
                                                            <div class="employee-email">{{ $leave->user_email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-primary">{{ $leave->leaveType->name ?? '' }}</span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>
                                                            {{ date('d M Y', strtotime($leave->start_date)) }}
                                                            @if ($leave->end_date && $leave->end_date != $leave->start_date)
                                                                &rarr; {{ date('d M Y', strtotime($leave->end_date)) }}
                                                            @endif
                                                        </strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Session: {{ ucfirst($leave->start_session) }}
                                                            &middot; {{ $leave->total_days ?? $leave->leave_count }} day(s)
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="reason-text" title="{{ $leave->reason ?? '' }}">
                                                        {{ Str::limit($leave->reason ?? 'No reason provided', 50) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($leave->file)
                                                        @php
                                                            $fileUrl = $leave->file;
                                                            $extension = strtolower(
                                                                pathinfo((string) parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION),
                                                            );
                                                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                        @endphp
                                                        @if (in_array($extension, $imageExtensions))
                                                            <a href="{{ file_url($fileUrl, 'leave') }}" target="_blank"
                                                                class="file-attachment"
                                                                style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none;">
                                                                <img src="{{ file_url($fileUrl, 'leave') }}" alt="Attachment"
                                                                    height="30" width="30"
                                                                    style="border-radius: 50%; object-fit: cover;">
                                                            </a>
                                                        @else
                                                            <a href="{{ file_url($fileUrl, 'leave') }}" download
                                                                class="file-attachment"
                                                                style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: #475569;">
                                                                <i class="feather-paperclip"></i>
                                                                <span>View</span>
                                                            </a>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">No file</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <x-ui.status-badge :status="$leave->status" />
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                            data-bs-offset="0,5">
                                                            <i class="feather-more-vertical"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            @if ($leave->status == 'pending')
                                                                <li>
                                                                    <a class="dropdown-item" href="javascript:void(0)"
                                                                        onclick="openStatusModal({{ $leave->id }})">
                                                                        <i class="feather-edit-3"></i>
                                                                        <span>Update Status</span>
                                                                    </a>
                                                                </li>
                                                            @endif
                                                            <li>
                                                                <a class="dropdown-item" href="javascript:void(0)"
                                                                    onclick="viewLeaveDetails({{ $leave->id }}, '{{ addslashes($leave->user_name) }}', '{{ $leave->user_email }}', '{{ $leave->leaveType->name }}', '{{ $leave->start_date }}', '{{ $leave->start_session }}', '{{ addslashes($leave->reason) }}', '{{ $leave->status }}', '{{ $leave->file }}')">
                                                                    <i class="feather-eye"></i>
                                                                    <span>View Details</span>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8">
                                                    <x-ui.empty-state icon="calendar" title="No Leave Applications Found" subtitle="There are no leave applications to display" />
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if (method_exists($leaves, 'links') && $leaves->hasPages())
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted small">
                                        Showing {{ $leaves->firstItem() }} to {{ $leaves->lastItem() }} of
                                        {{ $leaves->total() }} entries
                                    </div>
                                    <div class="remove-internal-para">
                                        {{ $leaves->appends(request()->query())->links() }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('create-modal')
        <!-- Status Update Modal -->
        <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Leave Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="statusUpdateForm" method="POST">
                        @csrf
                        <input type="hidden" name="leave_id" id="leave_id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="" disabled selected>Select Status</option>
                                    <option value="approved">Approve</option>
                                    <option value="cancelled">Reject</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Add remarks (optional)"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Details Modal (No AJAX, uses same data) -->
        <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Leave Application Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-row">
                            <div class="detail-label">Employee Name:</div>
                            <div class="detail-value" id="detail_name"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email:</div>
                            <div class="detail-value" id="detail_email"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Leave Type:</div>
                            <div class="detail-value" id="detail_leave_type"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Start Date:</div>
                            <div class="detail-value" id="detail_start_date"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Session:</div>
                            <div class="detail-value" id="detail_session"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value" id="detail_status"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Reason:</div>
                            <div class="detail-value">
                                <div class="reason-box" id="detail_reason"></div>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Attachment:</div>
                            <div class="detail-value" id="detail_file"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
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

                // Debounce search input (if added later)
                let searchTimeout;
                $('input[name="search"]').on('keyup', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        $('#filterForm').submit();
                    }, 500);
                });

                // Date inputs change
                $('input[name="from_date"], input[name="to_date"]').on('change', function() {
                    let fromDate = $('input[name="from_date"]').val();
                    let toDate = $('input[name="to_date"]').val();

                    if (fromDate && toDate && fromDate > toDate) {
                        toastr.error('From date cannot be greater than To date');
                        $(this).val('');
                    }
                });

                // Status update form submission
                $('#statusUpdateForm').on('submit', function(e) {
                    e.preventDefault();

                    let form = $(this);
                    let actionUrl = "{{ url('leave/update-status') }}/" + $('#leave_id').val();

                    $.ajax({
                        url: actionUrl,
                        type: "POST",
                        data: form.serialize(),
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.message);
                                $('#statusModal').modal('hide');
                                setTimeout(function() {
                                    location.reload();
                                }, 1200);
                            } else {
                                toastr.error(response.message);
                            }
                        },
                        error: function(xhr) {
                            if (xhr.status === 422) {
                                let errors = xhr.responseJSON.errors;
                                $.each(errors, function(key, value) {
                                    toastr.error(value[0]);
                                });
                            } else {
                                toastr.error("Something went wrong");
                            }
                        }
                    });
                });
            });

            // Open status modal
            function openStatusModal(id) {
                $('#leave_id').val(id);
                $('#statusModal').modal('show');
            }

            // View details modal - using data passed from the row (no AJAX)
            // View details modal - using data passed from the row (no AJAX)
            function viewLeaveDetails(id, name, email, leaveType, startDate, session, reason, status, file) {

                // Set the values in the modal
                $('#detail_name').text(name || 'N/A');
                $('#detail_email').text(email || 'N/A');
                $('#detail_leave_type').text(leaveType || 'N/A');

                // Format date
                if (startDate) {
                    let date = new Date(startDate);
                    let formattedDate = date.getDate() + ' ' +
                        date.toLocaleString('default', {
                            month: 'short'
                        }) + ' ' +
                        date.getFullYear();
                    $('#detail_start_date').text(formattedDate);
                } else {
                    $('#detail_start_date').text('N/A');
                }

                $('#detail_session').text(session ? session.charAt(0).toUpperCase() + session.slice(1) : 'N/A');

                // Status with badge
                let statusClass = '';
                if (status === 'approved') statusClass = 'badge bg-success';
                else if (status === 'pending') statusClass = 'badge bg-warning';
                else if (status === 'cancelled') statusClass = 'badge bg-danger';
                else statusClass = 'badge bg-secondary';

                $('#detail_status').html('<span class="' + statusClass + '">' + (status ? status.charAt(0).toUpperCase() +
                    status.slice(1) : 'N/A') + '</span>');

                $('#detail_reason').text(reason || 'No reason provided');

                // Handle file attachment - FIXED URL ISSUE
                if (file && file !== 'null' && file !== '') {
                    let fileUrl = file;

                    // Check if the URL already has 'uploads' in it to avoid duplication
                    // If the file path starts with 'uploads/', use asset() to generate correct URL
                    if (fileUrl.startsWith('uploads/')) {
                        let baseUrl = $('meta[name="base-url"]').attr('content') || '';
                        fileUrl = baseUrl + '/' + fileUrl;
                    }

                    // Signed URLs carry a query string: read the extension from the path only.
                    let extension = file.split('?')[0].split('.').pop().toLowerCase();
                    let imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (imageExtensions.includes(extension)) {
                        $('#detail_file').html('<a href="' + fileUrl +
                            '" target="_blank" class="file-preview"><i class="feather-image"></i> View Image</a>');
                    } else {
                        $('#detail_file').html('<a href="' + fileUrl +
                            '" download class="file-preview"><i class="feather-download"></i> Download File</a>');
                    }
                } else {
                    $('#detail_file').html('<span class="text-muted">No attachment</span>');
                }

                $('#viewDetailsModal').modal('show');
            }

            // Export to CSV
            // Export to CSV using data attributes
            function exportToCSV() {
                let table = document.getElementById('leaveList');
                if (!table) {
                    toastr.error('Table not found');
                    return;
                }

                let rows = table.querySelectorAll('tbody tr');
                let csv = [];

                // Add headers
                let headers = ['#', 'Employee', 'Leave Type', 'Date & Session', 'Reason', 'Attachment', 'Status'];
                csv.push(headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(','));

                // Add data rows
                rows.forEach((row, index) => {
                    // Get data from attributes
                    let employeeName = row.dataset.employeeName || 'N/A';
                    let employeeId = row.dataset.employeeId || 'N/A';
                    let employeeEmail = row.dataset.employeeEmail || '';
                    let leaveType = row.dataset.leaveType || 'N/A';
                    let startDate = row.dataset.startDate || 'N/A';
                    let session = row.dataset.session || 'N/A';
                    let reason = row.dataset.reason || 'No reason provided';
                    let status = row.dataset.status || 'N/A';

                    // Check attachment
                    let attachmentCol = row.querySelector('td:nth-child(6)');
                    let hasAttachment = attachmentCol && attachmentCol.innerText.trim() !== 'No file';
                    let attachment = hasAttachment ? 'Yes' : 'No';

                    // Format employee info
                    let employeeInfo = `${employeeName} (${employeeId}) - ${employeeEmail}`;
                    let dateSession = `${startDate} (${session})`;

                    // Create row data
                    let rowData = [
                        index + 1,
                        employeeInfo,
                        leaveType,
                        dateSession,
                        reason,
                        attachment,
                        status
                    ];

                    // Clean and quote
                    rowData = rowData.map(cell => '"' + String(cell).replace(/"/g, '""') + '"');
                    csv.push(rowData.join(','));
                });

                let csvFile = new Blob([csv.join('\n')], {
                    type: 'text/csv'
                });
                let downloadLink = document.createElement('a');
                downloadLink.download = 'leave_applications_' + new Date().getTime() + '.csv';
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.click();

                toastr.success('CSV exported successfully');
            }
        </script>
    @endsection
