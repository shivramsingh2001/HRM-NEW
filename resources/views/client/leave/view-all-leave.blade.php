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
                border-color: #0D6EFD;
                box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
            }

            /* Consistent initials style - same color for all */
            .employee-initials,
            .employee-initials-sm {
                width: 28px;
                height: 28px;
                background: #0D6EFD;
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
                background: #EFF6FF;
                color: #0D6EFD;
            }

            .custom-employee-dropdown .dropdown-item.active .text-muted {
                color: #0D6EFD !important;
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
                color: #0D6EFD;
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
                background: #0D6EFD;
                transform: translateY(-1px);
            }

            .apply-btn i {
                font-size: 12px;
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
                background: #EFF6FF;
                border-radius: 9px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex: none;
            }

            .stats-icon i {
                font-size: 12px;
                color: var(--icon-color, #0D6EFD);
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
                background: #EFF6FF !important;
                color: #0B5ED7;
            }

            .badge.bg-danger {
                background: #EFF6FF !important;
                color: #0D6EFD;
            }

            .badge.bg-primary {
                background: #e0f2fe !important;
                color: #0369a1;
            }

            .badge.bg-warning {
                background: #EFF6FF !important;
                color: #0D6EFD;
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
                color: var(--icon-color, #0D6EFD);
            }

            /* ==================== REASON TEXT (NO SCROLLBAR) ==================== */
            .reason-text {
                max-width: 250px;
                white-space: normal;
                word-wrap: break-word;
                line-height: 1.4;
                font-size: 11.5px;
            }

            /* Leave ID (leaves.leave_id, e.g. LV-000001) under the type badge */
            .leave-code { margin-top: 3px; font-size: 10.5px; font-weight: 700; color: #0D6EFD; white-space: nowrap; }

            /* View Details modal — compact, small font */
            #viewDetailsModal .modal-title { font-size: 14px; }
            .ld-view { font-size: 11.5px; padding: 14px 16px; }
            .ld-view-head { display: flex; align-items: center; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
            .ld-view-avatar { width: 36px; height: 36px; border-radius: 50%; background: #0D6EFD; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex: none; }
            .ld-view-name { font-size: 13px; font-weight: 700; color: #1e293b; }
            .ld-view-name small { font-size: 10.5px; font-weight: 600; color: #64748b; }
            .ld-view-sub { font-size: 10.5px; color: #64748b; }
            .ld-view-section { margin: 12px 0 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #0D6EFD; }
            .ld-view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 14px; }
            .ld-view-grid > div { display: flex; flex-direction: column; min-width: 0; }
            .ld-view-grid span { font-size: 10px; color: #94a3b8; }
            .ld-view-grid strong { font-size: 11.5px; font-weight: 600; color: #1e293b; }
            .ld-view .reason-box { font-size: 11.5px; padding: 8px 10px; }
            .ld-view .badge { font-size: 10px; }

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
                background: #EFF6FF;
                border-color: #0D6EFD;
                color: #0D6EFD;
            }

            .file-preview i {
                font-size: 14px;
            }

            .reason-box {
                background: #f8fafc;
                padding: 12px 16px;
                border-radius: 8px;
                border-left: 3px solid #0D6EFD;
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
        <x-ui.page-header title="Team Leave Applications" :parent="['label' => 'Leave', 'route' => 'leave.view-all']">
            <x-slot:actions>
                <x-on-behalf.button module="leave" />
            </x-slot:actions>
        </x-ui.page-header>
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
                        <i class="feather-check-circle" style="color: var(--icon-color, #0D6EFD);"></i>
                    </div>
                    <div class="stats-info">
                        <h3>{{ $approvedLeaves ?? 0 }}</h3>
                        <p>Approved Leaves</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon" style="background: rgba(13, 110, 253, 0.1);">
                        <i class="feather-x-circle" style="color: var(--icon-color, #0D6EFD);"></i>
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
                            $activeFilterCount = collect(request()->only(['search', 'status', 'from_date', 'to_date']))
                                ->filter()
                                ->count();
                        @endphp
                        @if ($activeFilterCount > 0)
                            <span>{{ $activeFilterCount }} active</span>
                        @endif
                    </div>
                    @if (request()->hasAny(['search', 'status', 'from_date', 'to_date']))
                        <a href="{{ route('leave.view-all') }}" class="clear-all-link">
                            <i class="feather-x"></i>
                            Clear All
                        </a>
                    @endif
                </div>

                <form action="{{ route('leave.view-all') }}" method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-item search">
                            <div class="search-wrapper">
                                <i class="feather-search"></i>
                                <input type="text" name="search" class="form-control" placeholder="Search employee, leave ID, type, reason..." value="{{ request('search') }}" autocomplete="off">
                            </div>
                        </div>
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
                @if (request()->hasAny(['search', 'status', 'from_date', 'to_date']))
                    <div class="active-filters">
                        <span class="active-filters-label">Active:</span>

                        @if (request('search'))
                            <span class="filter-tag">
                                <i class="feather-search"></i>
                                Search: {{ request('search') }}
                                <a href="{{ route('leave.view-all', request()->except(['search', 'page'])) }}"
                                    class="remove-tag">
                                    <i class="feather-x"></i>
                                </a>
                            </span>
                        @endif

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
                                                            style="background: #0D6EFD; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                            {{ strtoupper(substr($leave->user_name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">{{ $leave->user_name ?? 'N/A' }}
                                                                <small
                                                                    class="text-muted employee-email">({{ $leave->employee_id ?? 'N/A' }})</small>
                                                                @if (($leave->source ?? null) === 'on_behalf')
                                                                    <x-on-behalf.badge :by="$leave->applied_by" :owner="$leave->user_id" />
                                                                @endif
                                                            </div>
                                                            <div class="employee-email">{{ $leave->user_email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-primary">{{ $leave->leaveType->name ?? '' }}</span>
                                                    <div class="leave-code">{{ $leave->leave_id }}</div>
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
                                                                @php
                                                                    $jd = $leave->user?->jobDetails;
                                                                    $sessionText = fn ($v) => ['fullday' => 'Full Day', 'session1' => 'Session 1 (First half)', 'session2' => 'Session 2 (Second half)'][$v] ?? ucfirst((string) $v);
                                                                    $detail = [
                                                                        'leave_id' => $leave->leave_id ?: '—',
                                                                        'name' => $leave->user_name,
                                                                        'employee_id' => $leave->employee_id,
                                                                        'email' => $leave->user_email,
                                                                        'department' => $jd?->departmentRel?->name,
                                                                        'designation' => $jd?->designationRel?->name,
                                                                        'leave_type' => $leave->leaveType->name ?? null,
                                                                        'from' => date('d M Y', strtotime($leave->start_date)) . ' · ' . $sessionText($leave->start_session),
                                                                        'to' => date('d M Y', strtotime($leave->end_date ?: $leave->start_date)) . ' · ' . $sessionText($leave->end_session ?: $leave->start_session),
                                                                        'days' => rtrim(rtrim(number_format((float) ($leave->total_days ?? $leave->leave_count), 2), '0'), '.'),
                                                                        'paid' => $leave->deduct_balance === null ? null : ((int) $leave->deduct_balance === 1 ? 'Deducted from balance' : 'Not deducted'),
                                                                        'applied_on' => $leave->created_at ? date('d M Y, h:i A', strtotime($leave->created_at)) : null,
                                                                        'source' => ($leave->source ?? null) === 'on_behalf' ? 'Applied by Admin / HR on behalf' : 'Applied by employee',
                                                                        'status' => $leave->status,
                                                                        'updated_by' => $leave->statusUpdater?->name,
                                                                        'updated_on' => $leave->status !== 'pending' && $leave->updated_at ? date('d M Y, h:i A', strtotime($leave->updated_at)) : null,
                                                                        'remarks' => $leave->status_update_remarks,
                                                                        'reason' => $leave->reason,
                                                                        'file' => $leave->file ? file_url($leave->file, 'leave') : null,
                                                                    ];
                                                                @endphp
                                                                <a class="dropdown-item js-view-leave" href="javascript:void(0)" data-detail="{{ json_encode($detail) }}">
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
                            <x-ui.pagination-footer :paginator="$leaves" label="leaves" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('create-modal')
        <x-on-behalf.modal module="leave" />

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
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-md">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Leave Application Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body ld-view">
                        <div class="ld-view-head">
                            <div class="ld-view-avatar" id="detail_avatar"></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="ld-view-name"><span id="detail_name"></span> <small id="detail_emp_id"></small></div>
                                <div class="ld-view-sub" id="detail_email"></div>
                                <div class="ld-view-sub" id="detail_job"></div>
                            </div>
                            <div id="detail_status"></div>
                        </div>

                        <div class="ld-view-section">Leave</div>
                        <div class="ld-view-grid">
                            <div><span>Leave ID</span><strong id="detail_leave_id"></strong></div>
                            <div><span>Leave Type</span><strong id="detail_leave_type"></strong></div>
                            <div><span>From</span><strong id="detail_from"></strong></div>
                            <div><span>To</span><strong id="detail_to"></strong></div>
                            <div><span>Days</span><strong id="detail_days"></strong></div>
                            <div><span>Balance</span><strong id="detail_paid"></strong></div>
                            <div><span>Applied On</span><strong id="detail_applied_on"></strong></div>
                            <div><span>Applied By</span><strong id="detail_source"></strong></div>
                        </div>

                        <div class="ld-view-section">Reason</div>
                        <div class="reason-box" id="detail_reason"></div>

                        <div class="ld-view-section">Attachment</div>
                        <div id="detail_file"></div>

                        <div id="detail_decision_wrap">
                            <div class="ld-view-section">Decision</div>
                            <div class="ld-view-grid">
                                <div><span>Updated By</span><strong id="detail_updated_by"></strong></div>
                                <div><span>Updated On</span><strong id="detail_updated_on"></strong></div>
                            </div>
                            <div class="reason-box mt-2" id="detail_remarks"></div>
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

                // Search: submit after typing stops
                let searchTimeout;
                $('input[name="search"]').on('input', function() {
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

            // View details modal — everything comes from the link's data-detail JSON (no AJAX).
            $(document).on('click', '.js-view-leave', function (e) {
                e.preventDefault();
                let d = $(this).data('detail') || {};
                let txt = (v) => (v === null || v === undefined || v === '') ? '—' : v;

                $('#detail_avatar').text(String(d.name || 'U').substring(0, 2).toUpperCase());
                $('#detail_name').text(txt(d.name));
                $('#detail_emp_id').text(d.employee_id ? '(' + d.employee_id + ')' : '');
                $('#detail_email').text(d.email || '');
                $('#detail_job').text([d.designation, d.department].filter(Boolean).join(' · '));
                $('#detail_leave_id').text(txt(d.leave_id));
                $('#detail_leave_type').text(txt(d.leave_type));
                $('#detail_from').text(txt(d.from));
                $('#detail_to').text(txt(d.to));
                $('#detail_days').text(d.days ? d.days + ' day(s)' : '—');
                $('#detail_paid').text(txt(d.paid));
                $('#detail_applied_on').text(txt(d.applied_on));
                $('#detail_source').text(txt(d.source));

                let badge = {approved: 'bg-success', pending: 'bg-warning', cancelled: 'bg-danger'}[d.status] || 'bg-secondary';
                $('#detail_status').html($('<span>').addClass('badge ' + badge).text(d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : 'N/A'));

                $('#detail_reason').text(d.reason || 'No reason provided');

                if (d.file) {
                    let isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(d.file.split('?')[0].split('.').pop().toLowerCase());
                    $('#detail_file').html($('<a target="_blank" rel="noopener" class="file-preview">').attr('href', d.file)
                        .html(isImage ? '<i class="feather-image"></i> View Image' : '<i class="feather-download"></i> Download File'));
                } else {
                    $('#detail_file').html('<span class="text-muted">No attachment</span>');
                }

                let decided = d.status && d.status !== 'pending';
                $('#detail_decision_wrap').toggle(decided);
                $('#detail_updated_by').text(txt(d.updated_by));
                $('#detail_updated_on').text(txt(d.updated_on));
                $('#detail_remarks').text(d.remarks || 'No remarks').toggleClass('text-muted', !d.remarks);

                $('#viewDetailsModal').modal('show');
            });
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
