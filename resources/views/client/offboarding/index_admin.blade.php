@extends('client.layout.master')

@section('style')
    <style>
        .employee-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        .custom-employee-dropdown .btn {
            height: 36px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 12px;
            padding: 0 12px;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: var(--primary-mid, #1e3a8a);
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
            padding: 8px;
            max-height: 300px;
            overflow-y: auto;
            min-width: 260px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12.5px;
            margin-bottom: 2px;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: var(--primary-light, #e3edfe);
            color: var(--primary-mid, #1e3a8a);
        }

        /* ==================== MODERN FILTER SECTION ====================
                   Same markup/classes as Tasks "Assigned By Me" / Overtime Management. */
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
            margin-bottom: 16px;
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
            color: var(--primary-mid, #1e3a8a);
            font-size: 16px;
        }

        .filter-title span {
            background: var(--primary-light, #e3edfe);
            color: var(--primary-mid, #1e3a8a);
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

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .filter-item {
            flex: 1;
            min-width: 150px;
        }

        .filter-item .form-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .filter-select,
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

        .filter-select {
            padding-right: 28px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
        }

        .filter-select:focus,
        .filter-input:focus {
            background-color: white;
            border-color: var(--primary-mid, #1e3a8a);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #94a3b8;
        }

        .reset-btn {
            height: 36px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
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

        .filter-submit-btn {
            height: 36px;
            padding: 0 16px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            cursor: pointer;
        }

        .filter-submit-btn:hover {
            filter: brightness(0.9);
        }

        .active-filters {
            margin-top: 16px;
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
            color: var(--primary-mid, #1e3a8a);
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
            background: var(--primary-light, #e3edfe);
            border-color: var(--primary-mid, #1e3a8a);
            color: var(--primary-mid, #1e3a8a);
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        .filter-tag.clear-all:hover {
            background: var(--primary-mid, #1e3a8a);
            color: white;
        }

        /* ==================== TABLE ==================== */
        .ob-table-card {
            background: #fff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 4px 14px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 12px 12px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 12px;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 7px;
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
            color: var(--primary-mid, #1e3a8a);
            border-color: var(--primary-mid, #1e3a8a);
        }

        /* ==================== NEW REQUEST BUTTON ==================== */
        .ob-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border: none;
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 16px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
        }

        .ob-btn:hover {
            filter: brightness(0.9);
            color: #fff;
        }

        /* ==================== DRAWER FORM ==================== */
        .ui-drawer .form-group {
            margin-bottom: 14px;
        }

        .ui-drawer label {
            font-size: 11px;
            font-weight: 600;
            color: #1a2236;
            margin-bottom: 3px;
            display: block;
        }

        .ui-drawer .form-control,
        .ui-drawer select.form-control {
            font-size: 12px;
            padding: 7px 10px;
            border-radius: 7px;
            border: 1px solid #dfe5f0;
        }

        .ui-drawer .form-control:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 .15rem rgba(30, 58, 138, .12);
        }

        .ui-drawer .form-hint {
            font-size: 10.5px;
            color: #6b7385;
            margin-top: 3px;
            display: block;
        }

        .ui-drawer .error-text {
            font-size: 10.5px;
            color: #dc3545;
            display: block;
            margin-top: 2px;
        }

        #newOffboardingDrawer .hint-bar {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border-left: 3px solid var(--primary-mid, #1e3a8a);
            margin-bottom: 14px;
            font-size: 11px;
            color: #475569;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .filter-row {
                gap: 10px;
            }

            .filter-item {
                flex: 1 1 calc(33.333% - 10px);
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

            .table th,
            .table td {
                padding: 8px 10px;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Offboarding Management">
        <x-slot:actions>
            <button type="button" class="ob-btn" data-bs-toggle="offcanvas" data-bs-target="#newOffboardingDrawer">
                <i class="feather-plus"></i> New Offboarding Request
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $stats['total'] }}</h3>
                    <p>Total</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-users"></i>
                </div>
            </div>
            <div class="stats-card" data-status="pending_approval">
                <div class="stats-info">
                    <h3>{{ $stats['pending_approval'] }}</h3>
                    <p>Pending Approval</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-clock"></i>
                </div>
            </div>
            <div class="stats-card" data-status="approved">
                <div class="stats-info">
                    <h3>{{ $stats['in_progress'] }}</h3>
                    <p>In Progress</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-loader"></i>
                </div>
            </div>
            <div class="stats-card" data-status="completed">
                <div class="stats-info">
                    <h3>{{ $stats['completed'] }}</h3>
                    <p>Completed</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="rejected">
                <div class="stats-info">
                    <h3>{{ $stats['rejected'] }}</h3>
                    <p>Rejected</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-x-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="cancelled">
                <div class="stats-info">
                    <h3>{{ $stats['cancelled'] }}</h3>
                    <p>Cancelled</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-slash"></i>
                </div>
            </div>
        </div>

        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Requests
                    @php
                        $activeFilterCount = collect(request()->only(['status', 'stage', 'employee_id', 'search']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'stage', 'employee_id', 'search']))
                    <a href="{{ route('offboarding.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('offboarding.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="filter-select" id="statusFilter">
                            <option value="">All statuses</option>
                            @foreach (\App\Models\OffboardingRequest::$statuses as $value => $label)
                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item">
                        <select name="stage" class="filter-select">
                            <option value="">All stages</option>
                            @foreach (\App\Models\OffboardingRequest::$stages as $value => $label)
                                <option value="{{ $value }}" {{ request('stage') == $value ? 'selected' : '' }}>
                                    {{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-item" style="min-width: 220px;">
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('employee_id') && ($selectedEmployee = $employees->firstWhere('id', request('employee_id'))))
                                        <span
                                            class="employee-initials-sm">{{ strtoupper(substr($selectedEmployee->name, 0, 2)) }}</span>
                                        <span class="employee-name">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>
                            <ul class="dropdown-menu w-80 p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('employee_id') ? 'active' : '' }}"
                                        href="{{ route('offboarding.index', array_merge(request()->except(['employee_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $emp)
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee_id') == $emp->id ? 'active' : '' }}"
                                            href="{{ route('offboarding.index', array_merge(request()->except(['page']), ['employee_id' => $emp->id])) }}">
                                            <span
                                                class="employee-initials">{{ strtoupper(substr($emp->name, 0, 2)) }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $emp->name }} <small
                                                        class="text-muted">({{ $emp->employee_id }})</small></span>
                                                <small class="text-muted">{{ $emp->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="filter-item">
                        <input type="text" name="search" class="filter-input" placeholder="Search name / ID / email"
                            value="{{ request('search') }}">
                    </div>
                    <div class="filter-item" style="min-width: auto; flex: 0 0 auto;">
                        <button type="submit" class="filter-submit-btn"><i class="feather-search"></i> Search</button>
                    </div>
                    <div class="filter-item" style="min-width: auto; flex: 0 0 auto;">
                        <a href="{{ route('offboarding.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            @if (request()->hasAny(['status', 'stage', 'employee_id', 'search']))
                <div class="active-filters">
                    <span class="active-filters-label">Active Filters:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ \App\Models\OffboardingRequest::$statuses[request('status')] ?? request('status') }}
                            <a href="{{ route('offboarding.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('stage'))
                        <span class="filter-tag">
                            <i class="feather-flag"></i>
                            Stage: {{ \App\Models\OffboardingRequest::$stages[request('stage')] ?? request('stage') }}
                            <a href="{{ route('offboarding.index', array_merge(request()->except(['stage', 'page']))) }}"
                                class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('employee_id') && ($selectedEmployee = $employees->firstWhere('id', request('employee_id'))))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Employee: {{ $selectedEmployee->name }}
                            <a href="{{ route('offboarding.index', array_merge(request()->except(['employee_id', 'page']))) }}"
                                class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('offboarding.index', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag"><i class="feather-x"></i></a>
                        </span>
                    @endif

                    <a href="{{ route('offboarding.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i> Clear All
                    </a>
                </div>
            @endif
        </div>

        <div class="ob-table-card">
            @if ($offboardings->isEmpty())
                <x-ui.empty-state icon="user-minus" title="No offboarding requests"
                    subtitle="Requests submitted by employees or HR will show up here." />
            @else
                <x-ui.data-table>
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Employee</th>
                            <th>Reason</th>
                            <th>Last Working Date</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>Submitted</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offboardings as $ob)
                            <tr>
                                <td>{{ $loop->iteration + ($offboardings->currentPage() - 1) * $offboardings->perPage() }}
                                </td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            {{ strtoupper(substr($ob->employee?->name, 0, 2)) }}
                                        </div>

                                        <div class="employee-details">
                                            <div class="employee-name">
                                                {{ $ob->employee?->name }} <small class="text-secondary fs-10">(
                                                    {{ $ob->employee?->employee_id }} )</small>

                                            </div>
                                            <div class="employee-email">{{ $ob->employee?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $ob->reason_label }}</td>
                                <td>{{ optional($ob->last_working_date)->format('d M Y') }}</td>
                                <td><x-ui.status-badge :status="$ob->badge_status" :label="$ob->status_label" /></td>
                                <td><x-ui.status-badge :status="$ob->badge_status" :label="$ob->stage_label" /></td>
                                <td>{{ $ob->created_at->format('d M Y') }}</td>
                                <td class="text-center">
                                    <a href="{{ route('offboarding.show', $ob->id) }}" class="action-btn" title="View"
                                        data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.data-table>
            @endif
        </div>
        @if (method_exists($ob, 'links') && $ob->hasPages())
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Showing {{ $ob->firstItem() }} to {{ $ob->lastItem() }}
                        of {{ $ob->total() }} entries
                    </div>
                    <div>
                        {{ $ob->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('create-modal')
    <!-- New Offboarding Request Drawer -->
    <x-ui.drawer id="newOffboardingDrawer" title="New Offboarding Request" width="480px">
        <form id="newOffboardingForm">
            @csrf
            <div id="newOffboardingError" class="alert alert-danger d-none"></div>

            @if (count($employees) > 1)
                <div class="form-group">
                    <label for="ob_employee_id">Employee *</label>
                    <select class="form-control" name="employee_id" id="ob_employee_id" required>
                        <option value="" disabled selected>Select employee</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->employee_id }})</option>
                        @endforeach
                    </select>
                    <small class="error-text employee_id_error"></small>
                </div>
            @endif

            <div class="form-group">
                <label for="ob_reason">Reason *</label>
                <select class="form-control" name="reason" id="ob_reason" required>
                    <option value="" disabled selected>Select reason</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <small class="error-text reason_error"></small>
            </div>

            <div class="hint-bar">
                <i class="feather-info"></i>
                <span id="obNoticeHint">Minimum notice period: {{ $noticeDays }} day(s). Last working date must be on or
                    after that.</span>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ob_resignation_date">Request Date</label>
                        <input type="date" class="form-control" id="ob_resignation_date" name="resignation_date"
                            value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ob_last_working_date">Last Working Date *</label>
                        <input type="date" class="form-control" id="ob_last_working_date" name="last_working_date"
                            required>
                        <small class="form-hint" id="obMinDateHint"></small>
                        <small class="error-text last_working_date_error"></small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="ob_reason_detail">Details</label>
                <textarea class="form-control" id="ob_reason_detail" name="reason_detail" rows="3" maxlength="2000"></textarea>
                <small class="error-text reason_detail_error"></small>
            </div>

            <div class="form-group">
                <label for="ob_feedback">Additional Feedback (optional)</label>
                <textarea class="form-control" id="ob_feedback" name="feedback" rows="2" maxlength="2000"></textarea>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="ob_eligible_for_rehire" name="eligible_for_rehire"
                    value="1" checked>
                <label class="form-check-label" for="ob_eligible_for_rehire" style="display:inline;">Eligible for
                    rehire</label>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit" id="newOffboardingSubmitBtn">Submit Request</button>
                <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </x-ui.drawer>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on filter change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Stats card click filter
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('offboarding.index') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('offboarding.index') }}';
                }
            });

            const currentStatus = '{{ request('status') }}';
            if (currentStatus) {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else {
                $('.stats-card[data-status="all"]').addClass('active');
            }

            // Reset drawer on open
            $('#newOffboardingDrawer').on('show.bs.offcanvas', function() {
                $('#newOffboardingForm')[0].reset();
                $('#newOffboardingForm .error-text').text('');
                $('#newOffboardingError').addClass('d-none').text('');
                syncNoticeHint();
            });

            const reasonRules = @json($reasonRules);
            const noticeDays = {{ (int) $noticeDays }};

            function todayPlus(days) {
                var d = new Date();
                d.setDate(d.getDate() + days);
                return d.toISOString().split('T')[0];
            }

            function syncNoticeHint() {
                const reason = $('#ob_reason').val();
                const rules = reasonRules[reason];
                const requiresNotice = !rules || rules.requires_notice;
                const lastWorkingDate = $('#ob_last_working_date');

                if (requiresNotice) {
                    const minDate = todayPlus(noticeDays);
                    lastWorkingDate.attr('min', minDate);
                    $('#obMinDateHint').text('Earliest allowed: ' + minDate);
                    $('#obNoticeHint').text('Minimum notice period: ' + noticeDays +
                        ' day(s). Last working date must be on or after that.');
                } else {
                    lastWorkingDate.removeAttr('min');
                    $('#obMinDateHint').text('');
                    $('#obNoticeHint').text('This reason does not require a minimum notice period.');
                }
            }

            $('#ob_reason').on('change', syncNoticeHint);

            $('#newOffboardingForm').on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $('#newOffboardingSubmitBtn');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                $('#newOffboardingForm .error-text').text('');
                $('#newOffboardingError').addClass('d-none').text('');

                $.ajax({
                    url: '{{ route('offboarding.store') }}',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        submitBtn.prop('disabled', false).html(originalText);
                        if (response.success) {
                            bootstrap.Offcanvas.getInstance(document.getElementById(
                                'newOffboardingDrawer'))?.hide();
                            if (response.redirect) {
                                window.location.href = response.redirect;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            $('#newOffboardingError').removeClass('d-none').text(response
                                .message || 'Failed to submit request');
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html(originalText);
                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            const errors = xhr.responseJSON.errors;
                            Object.keys(errors).forEach(function(field) {
                                $('.' + field + '_error').text(errors[field][0]);
                            });
                        } else {
                            $('#newOffboardingError').removeClass('d-none').text(xhr
                                .responseJSON?.message || 'Failed to submit request');
                        }
                    }
                });
            });
        });
    </script>
@endsection
