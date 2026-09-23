@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== ADD TASK DRAWER ====================
           Chrome (width, header, body padding, buttons) now comes from
           the shared .ui-drawer class (theme-custom.css) via the ui.drawer
           Blade component — only this form's own field styling stays
           page-local. */
        .ui-drawer .form-group { margin-bottom: 10px; }
        .ui-drawer label { font-size: 11px; font-weight: 600; color: #1a2236; margin-bottom: 3px; display: block; }
        .ui-drawer .form-control, .ui-drawer select.form-control {
            font-size: 11.5px; padding: 6px 10px; border-radius: 7px; border: 1px solid #dfe5f0;
        }
        .ui-drawer .form-control:focus { border-color: #1e3a8a; box-shadow: 0 0 0 .15rem rgba(30,58,138,.12); }
        .ui-drawer .form-hint { font-size: 10px; color: #6b7385; margin-top: 3px; display: block; }
        .ui-drawer .error-text { font-size: 10px; color: #dc3545; display: block; margin-top: 2px; }
        .ui-drawer #recordButton { font-size: 11px; padding: 5px 12px; }
        /* At 480px, col-md-6 pairs (Bootstrap's md breakpoint is viewport-,
           not container-width, so they don't auto-stack in a narrow drawer)
           need to stack to one column to stay usable. */
        #addTaskDrawer .row > [class*="col-"] { flex: 0 0 100%; max-width: 100%; }

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
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
            background: #e3edfe;
            color: #1e3a8a;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #1e3a8a !important;
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

        /* ==================== TASK PRIORITY & STATUS STYLES ==================== */
        .task-priority {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .priority-low {
            background-color: #d1fae5;
            color: #065f46;
        }

        .priority-medium {
            background-color: #fef3c7;
            color: #92400e;
        }

        .priority-high {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .priority-critical {
            background-color: #e3edfe;
            color: #1e3a8a;
        }

        .task-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending {
            background-color: #f3f4f6;
            color: #374151;
        }

        .status-in_progress {
            background-color: #dbeafe;
            color: #1e3a8a;
        }

        .status-hold {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background-color: #e5e7eb;
            color: #4b5563;
        }

        /* .stats-grid/.stats-card/.stats-card.active/.stats-info/.stats-icon
           are centralized in client.layout.head (single blue-only theme,
           click-to-filter JS below still targets .stats-card/data-status
           unchanged) — no local copy. */

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
            color: #1e3a8a;
            font-size: 16px;
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
            background: #fee2e2;
            color: #ef4444;
        }

        .clear-all-link i {
            font-size: 14px;
        }

        /* Filter Row */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 120px;
        }

        .filter-item.date-range {
            min-width: 100px;
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
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #94a3b8;
        }

        /* Date Inputs */
        .filter-date {
            width: 100%;
            height: 36px;
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-date:focus {
            background-color: white;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        /* Reset Button */
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

        /* Active Filter Tags */
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
            text-decoration: none;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
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

        /* Table Styles */
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

        /* Action Button */
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
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
        }

        .dropdown-item {
            font-size: 12px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-item i {
            font-size: 12px;
        }

        /* Attachment Badge */
        .attachment-badge {
            background-color: #e3edfe;
            color: #1e3a8a;
            padding: 4px 8px;
            border-radius: 16px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .attachment-badge:hover {
            background-color: #bfd3f7;
        }

        /* Audio Player */
        .audio-player-compact {
            display: flex;
            align-items: center;
            gap: 6px;
            background: white;
            padding: 4px 8px;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            max-width: 200px;
            margin: 0 auto;
        }

        .audio-player-compact button {
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .audio-player-compact button:hover {
            background: #16295e;
            transform: scale(1.05);
        }

        .audio-player-compact button.playing {
            background: #10b981;
        }

        .audio-player-compact .progress-time {
            font-size: 10px;
            color: #6b7280;
            min-width: 40px;
            text-align: center;
        }

        .audio-player-compact .progress-container {
            flex-grow: 1;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
            cursor: pointer;
        }

        .audio-player-compact .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #1e3a8a, #60a5fa);
            width: 0%;
            transition: width 0.1s linear;
        }

        .audio-player-compact .download-btn {
            color: #1e3a8a;
            font-size: 12px;
            padding: 2px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        /* Pagination */
        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 6px !important;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .page-item.active .page-link {
            background: #1e3a8a;
            border-color: #1e3a8a;
        }

        /* Empty State */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            font-size: 16px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 12px;
            color: #64748b;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table th,
            .table td {
                padding: 8px 10px;
            }
        }
    </style>
@endsection

@section('content-area')
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Tasks Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('task.assigned-by-me') }}">Tasks</a></li>
                <li class="breadcrumb-item active">Assigned By Me</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-flex align-items-center gap-2">
                <!-- Export Dropdown -->
                <div class="dropdown">
                    <a class="btn btn-icon btn-light-brand btn-sm" data-bs-toggle="dropdown" data-bs-offset="0, 10"
                        data-bs-auto-close="outside">
                        <i class="feather-paperclip fs-14"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        {{-- <a href="#;" class="dropdown-item export-btn" data-type="csv">
                            <i class="bi bi-filetype-csv me-3"></i>
                            <span>CSV</span>
                        </a> --}}
                        <a href="#;" class="dropdown-item export-btn" data-type="excel">
                            <i class="bi bi-filetype-xlsx me-3"></i>
                            <span>Excel</span>
                        </a>
                        {{-- <a href="#;" class="dropdown-item export-btn" data-type="pdf">
                            <i class="bi bi-filetype-pdf me-3"></i>
                            <span>PDF</span>
                        </a> --}}
                    </div>
                </div>

                <!-- Create Task Button -->
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#addTaskDrawer">
                    <i class="feather-plus me-1"></i>
                    <span>New Task</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Task Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $totalTasks ?? 0 }}</h3>
                    <p>Total </p>
                </div>
                <div class="stats-icon">
                    <i class="feather-list"></i>
                </div>
            </div>
            <div class="stats-card" data-status="pending">
                <div class="stats-info">
                    <h3>{{ $pendingTasks ?? 0 }}</h3>
                    <p>Pending</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-clock"></i>
                </div>
            </div>
            <div class="stats-card" data-status="in_progress">
                <div class="stats-info">
                    <h3>{{ $inProgressTasks ?? 0 }}</h3>
                    <p>In Progress</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-activity"></i>
                </div>
            </div>
            <div class="stats-card" data-status="completed">
                <div class="stats-info">
                    <h3>{{ $completedTasks ?? 0 }}</h3>
                    <p>Completed</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="approved">
                <div class="stats-info">
                    <h3>{{ $approvedTasks ?? 0 }}</h3>
                    <p>Approved</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-square"></i>
                </div>
            </div>
            <div class="stats-card" data-status="rejected">
                <div class="stats-info">
                    <h3>{{ $rejectTasks ?? 0 }}</h3>
                    <p>Rejected</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-x-circle"></i>
                </div>
            </div>
            {{-- <div class="stats-card" data-status="cancelled">
                <div class="stats-info">
                    <h3>{{ $cancelledTasks ?? 0 }}</h3>
                    <p>Cancelled</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-slash"></i>
                </div>
            </div> --}}
        </div>

        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Tasks
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'priority', 'assigned_to', 'date_from', 'date_to']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    {{-- @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif --}}
                </div>
                @if (request()->hasAny(['status', 'priority', 'assigned_to', 'date_from', 'date_to']) && request('status') != 'all')
                    <a href="{{ route('task.assigned-by-me') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('task.assigned-by-me') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select name="status" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In
                                Progress</option>
                            <option value="hold" {{ request('status') == 'hold' ? 'selected' : '' }}>On Hold</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed
                            </option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected
                            </option>
                            {{-- <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                            </option> --}}
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div class="filter-item">
                        <select name="priority" class="filter-select">
                            <option value="all">All Priorities</option>
                            <option value="critical" {{ request('priority') == 'critical' ? 'selected' : '' }}>Critical
                            </option>
                            <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                        </select>
                    </div>
                    <!-- Project -->
                    <div class="filter-item">
                        {{-- <small class="text-muted d-block">Project</small> --}}
                        <select name="project_id" class="filter-select ">
                            <option value="all">All Project</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}"
                                    {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                            <option value="">N/A</option>
                        </select>
                    </div>

                    

                    <div class="filter-item">

                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('assigned_to') && ($selectedEmployee = $users->firstWhere('id', request('assigned_to'))))
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
                                    <a class="dropdown-item rounded {{ !request('assigned_to') ? 'active' : '' }}"
                                        href="{{ route('task.assigned-by-me', array_merge(request()->except(['assigned_to', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($users as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('assigned_to') == $user->id ? 'active' : '' }}"
                                            href="{{ route('task.assigned-by-me', array_merge(request()->except(['page']), ['assigned_to' => $user->id])) }}">
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


                    <div class="filter-item">
                        <select name="sort_by" class="filter-select" id="sortBySelect">
                            <option value="priority_desc" {{ request('sort_by') == 'priority_desc' ? 'selected' : '' }}>
                                Priority (High to Low)
                            </option>
                            <option value="priority_asc" {{ request('sort_by') == 'priority_asc' ? 'selected' : '' }}>
                                Priority (Low to High)
                            </option>
                            <option value="deadline_asc" {{ request('sort_by') == 'deadline_asc' ? 'selected' : '' }}>
                                Deadline (Earliest First)
                            </option>
                            <option value="deadline_desc" {{ request('sort_by') == 'deadline_desc' ? 'selected' : '' }}>
                                Deadline (Latest First)
                            </option>
                            <option value="created_desc" {{ request('sort_by') == 'created_desc' ? 'selected' : '' }}>
                                Newest First
                            </option>
                            <option value="created_asc" {{ request('sort_by') == 'created_asc' ? 'selected' : '' }}>
                                Oldest First
                            </option>
                        </select>
                    </div>

                    <!-- Date From Filter -->
                    <div class="filter-item date-range">
                        <input type="date" name="date_from" class="filter-date" value="{{ request('date_from') }}"
                            placeholder="From Date">
                    </div>

                    <!-- Date To Filter -->
                    <div class="filter-item date-range">
                        <input type="date" name="date_to" class="filter-date" value="{{ request('date_to') }}"
                            placeholder="To Date">
                    </div>

                    <!-- Reset Button -->
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('task.assigned-by-me') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'priority', 'assigned_to', 'date_from', 'date_to']) && request('status') != 'all')
                <div class="active-filters">
                    <span class="active-filters-label">Active Filters:</span>

                    @if (request('status') && request('status') != 'all')
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                            <a href="{{ route('task.assigned-by-me', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('priority') && request('priority') != 'all')
                        <span class="filter-tag">
                            <i class="feather-flag"></i>
                            Priority: {{ ucfirst(request('priority')) }}
                            <a href="{{ route('task.assigned-by-me', array_merge(request()->except(['priority', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('assigned_to') && request('assigned_to') != 'all')
                        @php
                            $assignedToUser = $users->where('id', request('assigned_to'))->first();
                            $userName = $assignedToUser ? $assignedToUser->name : 'Unknown';
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Assigned To: {{ $userName }}
                            <a href="{{ route('task.assigned-by-me', array_merge(request()->except(['assigned_to', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('date_from'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ \Carbon\Carbon::parse(request('date_from'))->format('d M Y') }}
                            <a href="{{ route('task.assigned-by-me', array_merge(request()->except(['date_from', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('date_to'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ \Carbon\Carbon::parse(request('date_to'))->format('d M Y') }}
                            <a href="{{ route('task.assigned-by-me', array_merge(request()->except(['date_to', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('task.assigned-by-me') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Tasks Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Tasks Assigned By Me</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-light text-dark">
                                <i class="feather-calendar me-1"></i>Sorted by: Priority then Deadline
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"
                                    aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr class="text-center">
                                        <th width="50">#</th>
                                        <th>Task Code</th>
                                        <th class="text-start" style="max-width: 10px; width: 10px;">Task Title</th>
                                        <th>Project</th>
                                        <th class="text-start">Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Task Date</th>
                                        <th>Deadline Date</th>
                                        <th>Time Left</th>
                                        {{-- <th>Files</th>
                                        <th class="audio-controls">Audio</th> --}}
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tasks as $task)
                                        @php
                                            $daysLeft = $task->days_remaining ?? 0;
                                            $rowClass = '';
                                            if (!in_array($task->status, ['completed', 'approved'])) {
                                                if ($daysLeft < 0) {
                                                    $rowClass = 'urgent-task';
                                                } elseif ($daysLeft <= 2) {
                                                    $rowClass = 'due-soon';
                                                }
                                            }
                                        @endphp
                                        <tr class="text-center clickable-row {{ $rowClass }}"
                                            data-task-id="{{ $task->id }}"
                                            data-href="{{ route('task.view-detail', ['id' => $task->id]) }}"
                                            style="cursor: pointer;">
                                            <td>{{ $loop->iteration + ($tasks->currentPage() - 1) * $tasks->perPage() }}
                                            </td>
                                            <td>
                                                <strong>{{ $task->task_code ?? 'N/A' }}</strong>
                                            </td>
                                            <td class="text-start">
                                                <div class="fw-semibold">{{ Str::limit($task->title, 30) ?? '' }}</div>
                                                @if ($task->description)
                                                    <small class="text-muted d-block" style="font-size: 10px;">
                                                        {{ Str::limit($task->description, 40) }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($task->project_name)
                                                    <div class="d-flex flex-column">
                                                        <span class="fs-13">{{ $task->project_name }}</span>
                                                        @if ($task->project_code)
                                                            <small
                                                                class="text-muted fs-11">{{ $task->project_code }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-start">
                                                @if ($task->task_mode === 'group')
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-primary">
                                                            <i class="feather-users me-1"></i>
                                                            Group ({{ $task->member_count }} members)
                                                        </span>
                                                        <button type="button" class="btn btn-sm btn-link p-0"
                                                            onclick="event.stopPropagation(); $('#members-{{ $task->id }}').toggle();">
                                                            <i class="feather-chevron-down"></i>
                                                        </button>
                                                    </div>
                                                    <div id="members-{{ $task->id }}"
                                                        style="display:none; margin-top:8px;">
                                                        @foreach ($task->members as $m)
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <div class="employee-avatar"
                                                                    style="width:24px;height:24px;background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;">
                                                                    {{ strtoupper(substr($m->name ?? 'U', 0, 2)) }}
                                                                </div>
                                                                <small>
                                                                    {{ $m->name }}
                                                                    @if ($m->member_role === 'lead')
                                                                        <span class="badge bg-warning text-dark"
                                                                            style="font-size:9px;">LEAD</span>
                                                                    @endif
                                                                    <span
                                                                        class="task-status status-{{ $m->individual_status ?? 'pending' }}"
                                                                        style="font-size:9px;padding:1px 5px;">
                                                                        {{ ucfirst(str_replace('_', ' ', $m->individual_status ?? 'pending')) }}
                                                                    </span>
                                                                </small>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif ($task->assigned_to_name)
                                                    {{-- your existing single-assignee block --}}
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                            {{ strtoupper(substr($task->assigned_to_name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">
                                                                {{ $task->assigned_to_name }}
                                                                <small
                                                                    class="text-muted">({{ $task->assigned_to_employee_id ?? 'N/A' }})</small>
                                                            </div>
                                                            <div class="employee-email">
                                                                {{ $task->assigned_to_email ?? '' }}</div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $priorityClass = 'priority-' . ($task->priority ?? 'medium');
                                                @endphp
                                                <span class="task-priority {{ $priorityClass }}">
                                                    {{ ucfirst($task->priority ?? 'N/A') }}
                                                </span>
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = 'status-' . ($task->status ?? 'pending');
                                                @endphp
                                                <span class="task-status {{ $statusClass }}">
                                                    {{ ucfirst(str_replace('_', ' ', $task->status ?? 'Pending')) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($task->task_date)
                                                    {{ \Carbon\Carbon::parse($task->task_date)->format('d M, Y') }}
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($task->deadline_date)
                                                    {{ \Carbon\Carbon::parse($task->deadline_date)->format('d M, Y') }}
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    @if (!empty($task->deadline_date))
                                                        @php
                                                            $status = $task->status;
                                                            $daysLeft = $task->days_remaining ?? null;
                                                        @endphp

                                                        @if (in_array($status, ['completed', 'approved']))
                                                            <span class="badge bg-success">{{ ucfirst($status) }}</span>
                                                        @elseif (!is_null($daysLeft))
                                                            @if ($daysLeft < 0)
                                                                <small class="text-danger">Overdue by {{ abs($daysLeft) }}
                                                                    days</small>
                                                            @elseif ($daysLeft === 0)
                                                                <small class="text-warning">Due today</small>
                                                            @else
                                                                <small class="text-success">{{ $daysLeft }} days
                                                                    left</small>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <span class="text-muted">Not set</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <a href="#" class="action-btn" data-bs-toggle="dropdown"
                                                        data-bs-offset="0,5" onclick="event.stopPropagation();">
                                                        <i class="feather-more-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        @if (in_array($task->status, ['completed']))
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    onclick="event.stopPropagation(); showStatusUpdateModal({{ $task->id }})">
                                                                    <i class="feather-edit text-warning me-2"></i>
                                                                    Update Status
                                                                </button>
                                                            </li>
                                                        @endif
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('task.view-detail', ['id' => $task->id]) }}">
                                                                <i class="feather-eye me-2"></i>
                                                                View Details
                                                            </a>
                                                        </li>
                                                        @if (in_array($task->status, ['pending', 'in_progress', 'hold']))
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    onclick="event.stopPropagation(); openEditTaskDrawer({{ $task->id }})">
                                                                    <i class="feather-edit-2 me-2"></i>
                                                                    Edit Task
                                                                </button>
                                                            </li>
                                                        @endif
                                                        @if (in_array($task->status, ['pending', 'in_progress']))
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#"
                                                                    onclick="event.stopPropagation(); confirmDelete({{ $task->id }})">
                                                                    <i class="feather-trash-2 me-2"></i>
                                                                    Delete
                                                                </a>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="feather-inbox"></i>
                                                    <h4>No Tasks Found</h4>
                                                    <p>No tasks assigned by you match the selected filters.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        @if ($tasks->hasPages())
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-muted small">
                                        Showing {{ $tasks->firstItem() }} to {{ $tasks->lastItem() }} of
                                        {{ $tasks->total() }} entries
                                    </div>
                                    <div>
                                        {{ $tasks->appends(request()->query())->links() }}
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

@section('create-modal')
    <!-- Add Task Drawer -->
    <x-ui.drawer id="addTaskDrawer" title="Create Task" width="480px">
            <form id="addTaskForm" enctype="multipart/form-data">
                @csrf
                <div id="addTaskError" class="alert alert-danger d-none"></div>

                <div class="form-group">
                    <label for="self_assigned">Assignment Type *</label>
                    <select class="form-control" name="self_assigned" id="self_assigned" required>
                        <option value="" disabled selected>Select Assignment Type</option>
                        @feature('task_single')
                            @if (!in_array($authUser->role, ['admin']))
                                <option value="1">Self Assigned (Task for myself)</option>
                            @endif
                            @if (!in_array($authUser->role, ['employee']))
                                <option value="0">Assign to Someone Else</option>
                            @endif
                        @endfeature
                        @feature('task_group')
                            @if (!in_array($authUser->role, ['employee']))
                                <option value="2">Assign to Group (Multiple Members)</option>
                            @endif
                        @endfeature
                    </select>
                    <small class="form-hint">Self-assigned tasks go to your reporting head for approval.</small>
                    <small class="error-text self_assigned_error"></small>
                </div>

                <div class="form-group" id="assignedToWrapper" style="display:none;">
                    <label for="assigned_to">Assigned To *</label>
                    <select class="form-control" name="assigned_to" id="assigned_to">
                        <option value="" disabled selected>Select User</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <small class="error-text assigned_to_error"></small>
                </div>

                <div class="form-group" id="groupMembersWrapper" style="display:none;">
                    <label>Group Members * <small class="text-muted">(pick at least 2)</small></label>
                    <select name="group_members[]" id="group_members" class="form-control" multiple size="5">
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->employee_id ?? '' }})</option>
                        @endforeach
                    </select>
                    <small class="error-text group_members_error"></small>
                </div>

                <div class="form-group" id="groupRuleWrapper" style="display:none;">
                    <label for="group_completion_rule">Completion Rule *</label>
                    <select name="group_completion_rule" id="group_completion_rule" class="form-control">
                        <option value="" disabled selected>How is this task considered complete?</option>
                        <option value="all_must_complete">All members must complete</option>
                        <option value="any_one">Any one member's completion is enough</option>
                        <option value="percentage">Percentage threshold</option>
                        <option value="lead_decides">Group lead decides</option>
                    </select>
                    <small class="error-text group_completion_rule_error"></small>
                </div>

                <div class="form-group" id="thresholdWrapper" style="display:none;">
                    <label for="completion_threshold">Threshold % *</label>
                    <input type="number" name="completion_threshold" id="completion_threshold" class="form-control" min="1" max="100" value="75">
                    <small class="error-text completion_threshold_error"></small>
                </div>

                <div class="form-group" id="groupLeadWrapper" style="display:none;">
                    <label for="group_lead_id">Group Lead (optional)</label>
                    <select name="group_lead_id" id="group_lead_id" class="form-control">
                        <option value="">No lead</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="add_project_id">Project</label>
                            <select class="form-control" name="project_id" id="add_project_id">
                                <option value="">No project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="add_priority">Priority *</label>
                            <select class="form-control" name="priority" id="add_priority" required>
                                <option value="" disabled selected>Select Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                            <small class="error-text priority_error"></small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_title">Task Title *</label>
                    <input type="text" class="form-control" id="add_title" name="title" placeholder="Task Title" required>
                    <small class="error-text title_error"></small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="add_task_date">Task Date *</label>
                            <input type="date" class="form-control" id="add_task_date" name="task_date" max="{{ date('Y-m-d') }}" required>
                            <small class="error-text task_date_error"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="add_deadline_date">Task Deadline *</label>
                            <input type="date" class="form-control" id="add_deadline_date" name="deadline_date" min="{{ date('Y-m-d') }}" required>
                            <small class="error-text deadline_date_error"></small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_description">Task Description *</label>
                    <textarea class="form-control" id="add_description" name="description" rows="4" placeholder="Task Description" required></textarea>
                    <small class="error-text description_error"></small>
                </div>

                <div class="form-group">
                    <label for="add_file">Attachment</label>
                    <input type="file" class="form-control" id="add_file" name="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                    <small class="error-text file_error"></small>
                </div>

                <div class="form-group">
                    <label for="add_voice_file">Voice Note</label>
                    <div>
                        <button class="btn btn-light-brand mb-2" type="button" id="recordButton">🎤 Start Recording</button>
                        <input type="file" class="form-control" id="add_voice_file" name="voice_file" accept=".mp3,.wav,.m4a,.webm" style="display:none;">
                        <audio id="audioPlayer" controls style="display:none; width:100%;"></audio>
                    </div>
                    <small class="error-text voice_file_error"></small>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primary" type="submit" id="addTaskSubmitBtn">Create Task</button>
                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="offcanvas">Cancel</button>
                </div>
            </form>
    </x-ui.drawer>

    <!-- Edit Task Drawer -->
    <div class="modal fade-scale" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered compact-modal" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="d-flex flex-column mb-0">
                        <span class="fs-18 fw-bold mb-1">Edit Task</span>
                    </h2>
                    <a href="#" class="avatar-text avatar-md bg-soft-danger close-icon" data-bs-dismiss="modal">
                        <i class="feather-x text-danger"></i>
                    </a>
                </div>
                <div class="modal-body p-0">
                    <div class="card m-0">
                        <div class="card-body">
                            <form id="editTaskForm">
                                @csrf
                                <input type="hidden" id="edit_task_id" name="task_id">
                                <div id="editTaskError" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_task_title">Title *</label>
                                            <input type="text" class="form-control" name="title" id="edit_task_title" required>
                                            <small class="text-danger error-text edit_task_title_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_task_project">Project</label>
                                            <select class="form-control" name="project_id" id="edit_task_project">
                                                <option value="">No project</option>
                                                @foreach ($projects as $project)
                                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-danger error-text edit_task_project_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_task_priority">Priority *</label>
                                            <select class="form-control" name="priority" id="edit_task_priority" required>
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="critical">Critical</option>
                                            </select>
                                            <small class="text-danger error-text edit_task_priority_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_task_deadline">Deadline *</label>
                                            <input type="date" class="form-control" name="deadline_date" id="edit_task_deadline"
                                                min="{{ date('Y-m-d') }}" required>
                                            <small class="text-danger error-text edit_task_deadline_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-semibold" for="edit_task_description">Description *</label>
                                            <textarea class="form-control" name="description" id="edit_task_description" rows="4" required></textarea>
                                            <small class="text-danger error-text edit_task_description_error"></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" type="submit" id="editTaskSubmitBtn">Save Changes</button>
                                    </div>
                                    <div class="col-6">
                                        <a href="#" class="btn btn-modal-cancel float-end" data-bs-dismiss="modal">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="deleteModalLabel">Confirm Delete</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    Are you sure you want to delete this task? This action cannot be undone.
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusUpdateModal" tabindex="-1" aria-labelledby="statusUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusUpdateModalLabel">Update Task Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="statusUpdateForm" method="POST">
                    @csrf
                    <input type="hidden" id="statusTaskIds" name="task_id">
                    <div class="modal-body">
                        <div id="statusUpdateError" class="alert alert-danger d-none" role="alert">
                            <ul id="errorList" class="mb-0"></ul>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">New Status *</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="" disabled selected>Select Status</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <div class="invalid-feedback" id="statusError"></div>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea name="remarks" id="remarks" class="form-control" rows="3"
                                placeholder="Add remarks about this status update..."></textarea>
                            <div class="invalid-feedback" id="remarksError"></div>
                            <small class="text-muted">Add any comments or notes about the status change.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="statusUpdateBtn">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status"
                                aria-hidden="true"></span>
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Global audio management
        let currentAudio = null;
        let currentAudioId = null;
        let progressInterval = null;

        function toggleAudio(audioId, taskId) {
            const audio = document.getElementById(audioId);
            const playBtn = document.getElementById(`play-btn-${taskId}`);
            const btnIcon = document.getElementById(`btn-icon-${taskId}`);

            if (currentAudio && currentAudio !== audio) {
                currentAudio.pause();
                resetAudioUI(currentAudioId);
                if (progressInterval) clearInterval(progressInterval);
            }

            if (audio.paused) {
                audio.play();
                playBtn.classList.add('playing');
                btnIcon.className = 'feather-pause fs-10';
                currentAudio = audio;
                currentAudioId = taskId;
                progressInterval = setInterval(() => updateProgress(audioId, taskId), 100);
                setupAudioEvents(audio, audioId, taskId);
            } else {
                audio.pause();
                playBtn.classList.remove('playing');
                btnIcon.className = 'feather-play fs-10';
                clearInterval(progressInterval);
            }
        }
        $('.clickable-row').on('click', function(e) {
            // Don't trigger if clicking on dropdown or action buttons
            if ($(e.target).closest('.dropdown, .action-btn, .dropdown-menu, .dropdown-item').length === 0) {
                window.location.href = $(this).data('href');
            }
        });

        function setupAudioEvents(audio, audioId, taskId) {
            audio.removeEventListener('timeupdate', () => {});
            audio.removeEventListener('ended', () => {});
            audio.addEventListener('timeupdate', () => updateProgress(audioId, taskId));
            audio.addEventListener('ended', () => {
                const playBtn = document.getElementById(`play-btn-${taskId}`);
                const btnIcon = document.getElementById(`btn-icon-${taskId}`);
                playBtn.classList.remove('playing');
                btnIcon.className = 'feather-play fs-10';
                resetAudioUI(taskId);
                clearInterval(progressInterval);
                currentAudio = null;
                currentAudioId = null;
            });
            audio.addEventListener('loadedmetadata', () => updateProgress(audioId, taskId));
        }

        function updateProgress(audioId, taskId) {
            const audio = document.getElementById(audioId);
            const progressBar = document.getElementById(`progress-${taskId}`);
            const timeDisplay = document.getElementById(`time-${taskId}`);
            if (audio.duration && !isNaN(audio.duration)) {
                const progress = (audio.currentTime / audio.duration) * 100;
                progressBar.style.width = `${progress}%`;
                timeDisplay.textContent = formatTime(audio.currentTime);
            }
        }

        function seekAudio(audioId, taskId, event) {
            const audio = document.getElementById(audioId);
            const progressContainer = event.currentTarget;
            const rect = progressContainer.getBoundingClientRect();
            const pos = (event.clientX - rect.left) / rect.width;
            audio.currentTime = pos * audio.duration;
            updateProgress(audioId, taskId);
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }

        function resetAudioUI(taskId) {
            const progressBar = document.getElementById(`progress-${taskId}`);
            const timeDisplay = document.getElementById(`time-${taskId}`);
            if (progressBar) progressBar.style.width = '0%';
            if (timeDisplay) timeDisplay.textContent = '0:00';
        }

        document.addEventListener('play', function(e) {
            if (e.target.tagName === 'AUDIO') {
                document.querySelectorAll('audio').forEach(audio => {
                    if (audio !== e.target) {
                        audio.pause();
                        const audioId = audio.id;
                        const taskId = audioId.split('_')[1];
                        if (taskId) {
                            const playBtn = document.getElementById(`play-btn-${taskId}`);
                            const btnIcon = document.getElementById(`btn-icon-${taskId}`);
                            if (playBtn) playBtn.classList.remove('playing');
                            if (btnIcon) btnIcon.className = 'feather-play fs-10';
                            resetAudioUI(taskId);
                        }
                    }
                });
            }
        }, true);

        $(document).ready(function() {
            @if (session('success'))
                showToast('{{ session('success') }}', 'success');
            @endif

            @if (session('error'))
                showToast('{{ session('error') }}', 'error');
            @endif

            // Auto-submit on filter change
            $('.filter-select, .filter-date').on('change', function() {
                $('#filterForm').submit();
            });

            // Stats card click filter
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('task.assigned-by-me') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('task.assigned-by-me') }}';
                }
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus && currentStatus !== 'all') {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else if (!currentStatus || currentStatus === 'all') {
                $('.stats-card[data-status="all"]').addClass('active');
            }

            // Status update form
            $('#statusUpdateForm').on('submit', function(e) {
                e.preventDefault();

                const taskId = $('#statusTaskIds').val();
                const status = $('#status').val();
                const remarks = $('#remarks').val();

                clearFormErrors();

                if (!status) {
                    showFieldError('status', 'Please select a status');
                    return;
                }

                showLoading();

                $.ajax({
                    url: '{{ route('task.approval-status') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        task_id: taskId,
                        status: status,
                        remarks: remarks
                    },
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        if (response.success) {
                            showToast(response.message || 'Status updated successfully!',
                                'success');
                            $('#statusUpdateModal').modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('Error: ' + response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        hideLoading();
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            displayFormErrors(xhr.responseJSON.errors);
                        } else {
                            showToast('Error updating status. Please try again.', 'error');
                        }
                    }
                });
            });

            $('#statusUpdateModal').on('hidden.bs.modal', function() {
                clearFormErrors();
                resetForm();
            });
        });

        function showFieldError(fieldName, message) {
            $(`#${fieldName}`).addClass('is-invalid');
            $(`#${fieldName}Error`).text(message).show();
        }

        function clearFormErrors() {
            $('#status').removeClass('is-invalid');
            $('#remarks').removeClass('is-invalid');
            $('.invalid-feedback').empty().hide();
        }

        function displayFormErrors(errors) {
            clearFormErrors();
            if (errors.status) showFieldError('status', errors.status[0]);
            if (errors.remarks) showFieldError('remarks', errors.remarks[0]);
        }

        function resetForm() {
            $('#status').val('');
            $('#remarks').val('');
            clearFormErrors();
        }

        function showStatusUpdateModal(taskId) {
            $('#statusTaskIds').val(taskId);
            resetForm();
            $('#statusUpdateModal').modal('show');
        }

        function showToast(message, type = 'success') {
            $('.toast-container').remove();
            const toastContainer = $('<div>', {
                class: 'toast-container position-fixed top-0 end-0 p-3',
                style: 'z-index: 9999'
            });
            const toast = $('<div>', {
                class: 'toast align-items-center text-white bg-' + (type === 'success' ? 'success' : 'danger') +
                    ' border-0',
                role: 'alert'
            });
            const toastBody = $('<div>', {
                class: 'd-flex'
            }).append(
                $('<div>', {
                    class: 'toast-body',
                    html: message
                }),
                $('<button>', {
                    type: 'button',
                    class: 'btn-close btn-close-white me-2 m-auto',
                    'data-bs-dismiss': 'toast',
                    'aria-label': 'Close'
                })
            );
            toast.append(toastBody);
            toastContainer.append(toast);
            $('body').append(toastContainer);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            setTimeout(() => bsToast.hide(), 3000);
        }

        function showLoading() {
            $('.main-content').append(
                '<div class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>'
            );
        }

        function hideLoading() {
            $('.loading-overlay').remove();
        }

        // ==================== ADD TASK DRAWER ====================
        function toggleAssignedFields() {
            const value = $('#self_assigned').val();

            $('#assignedToWrapper, #groupMembersWrapper, #groupRuleWrapper, #thresholdWrapper, #groupLeadWrapper').hide();
            $('#assigned_to').prop('required', false);
            $('#group_members').prop('required', false);
            $('#group_completion_rule').prop('required', false);

            if (value === '0') {
                $('#assignedToWrapper').show();
                $('#assigned_to').prop('required', true);
            }

            if (value === '2') {
                $('#groupMembersWrapper, #groupRuleWrapper, #groupLeadWrapper').show();
                $('#group_members').prop('required', true);
                $('#group_completion_rule').prop('required', true);
                if ($('#group_completion_rule').val() === 'percentage') {
                    $('#thresholdWrapper').show();
                }
            }
        }

        $('#self_assigned').on('change', toggleAssignedFields);
        $('#group_completion_rule').on('change', function() {
            $('#thresholdWrapper').toggle($(this).val() === 'percentage');
        });

        $('#addTaskDrawer').on('show.bs.offcanvas', function() {
            $('#addTaskForm')[0].reset();
            $('#addTaskForm .error-text').text('');
            $('#addTaskError').addClass('d-none').text('');
            $('#assignedToWrapper, #groupMembersWrapper, #groupRuleWrapper, #thresholdWrapper, #groupLeadWrapper').hide();
            $('#audioPlayer').hide();
        });

        // Task date / deadline min-date coupling
        $('#add_task_date').on('change', function() {
            const v = $(this).val();
            if (v) {
                $('#add_deadline_date').attr('min', v);
                if ($('#add_deadline_date').val() && $('#add_deadline_date').val() < v) {
                    $('#add_deadline_date').val(v);
                }
            }
        });

        // Voice recording
        (function() {
            let mediaRecorder, audioChunks = [], isRecording = false;
            const recordBtn = document.getElementById('recordButton');
            const audioPlayer = document.getElementById('audioPlayer');
            const voiceFileInput = document.getElementById('add_voice_file');
            if (!recordBtn) return;

            recordBtn.onclick = async () => {
                if (!isRecording) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks = [];
                        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                        mediaRecorder.onstop = () => {
                            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                            const audioFile = new File([audioBlob], 'voice_recording_' + Date.now() + '.webm', { type: 'audio/webm' });
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(audioFile);
                            voiceFileInput.files = dataTransfer.files;
                            audioPlayer.src = URL.createObjectURL(audioBlob);
                            audioPlayer.style.display = 'block';
                            recordBtn.textContent = '🎤 Start Recording';
                            recordBtn.classList.remove('btn-danger');
                            recordBtn.classList.add('btn-light-brand');
                            isRecording = false;
                        };
                        mediaRecorder.start();
                        recordBtn.textContent = '⏹ Stop Recording';
                        recordBtn.classList.remove('btn-light-brand');
                        recordBtn.classList.add('btn-danger');
                        isRecording = true;
                    } catch (error) {
                        showToast('Error accessing microphone: ' + error.message, 'error');
                    }
                } else if (mediaRecorder) {
                    mediaRecorder.stop();
                }
            };
        })();

        $('#addTaskForm').on('submit', function(e) {
            e.preventDefault();

            const selfAssigned = $('#self_assigned').val();
            if (selfAssigned === '2') {
                const selected = $('#group_members').val() || [];
                if (selected.length < 2) {
                    $('.group_members_error').text('Pick at least 2 group members.');
                    return;
                }
            }

            const submitBtn = $('#addTaskSubmitBtn');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating...');
            $('#addTaskForm .error-text').text('');
            $('#addTaskError').addClass('d-none').text('');

            const formData = new FormData(this);

            $.ajax({
                url: '{{ route('task.store') }}',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        showToast(response.message || 'Task created successfully!', 'success');
                        bootstrap.Offcanvas.getInstance(document.getElementById('addTaskDrawer'))?.hide();
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        showToast(response.message || 'Failed to create task', 'error');
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.' + field + '_error').text(Array.isArray(errors[field]) ? errors[field][0] : errors[field]);
                        });
                    }
                    showToast(xhr.responseJSON?.message || 'Error creating task', 'error');
                }
            });
        });

        function openEditTaskDrawer(taskId) {
            $('#editTaskForm')[0].reset();
            $('#editTaskForm .error-text').text('');
            $('#editTaskError').addClass('d-none').text('');

            $.get('{{ url('tasks') }}/' + taskId + '/edit', function(response) {
                if (!response.success) {
                    showToast(response.message || 'Unable to load task', 'error');
                    return;
                }
                const t = response.data;
                $('#edit_task_id').val(t.id);
                $('#edit_task_title').val(t.title);
                $('#edit_task_project').val(t.project_id || '');
                $('#edit_task_priority').val(t.priority);
                $('#edit_task_deadline').val(t.deadline_date);
                $('#edit_task_description').val(t.description);
                new bootstrap.Modal(document.getElementById('editTaskModal')).show();
            }).fail(function(xhr) {
                showToast(xhr.responseJSON?.message || 'Unable to load task', 'error');
            });
        }

        $('#editTaskForm').on('submit', function(e) {
            e.preventDefault();
            const taskId = $('#edit_task_id').val();
            const submitBtn = $('#editTaskSubmitBtn');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
            $('#editTaskForm .error-text').text('');
            $('#editTaskError').addClass('d-none').text('');

            $.ajax({
                url: '{{ url('tasks') }}/' + taskId + '/update',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        showToast(response.message || 'Task updated successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        showToast(response.message || 'Failed to update task', 'error');
                    }
                },
                error: function(xhr) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (xhr.status === 422 && xhr.responseJSON?.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.edit_task_' + field + '_error').text(errors[field][0]);
                        });
                    }
                    showToast(xhr.responseJSON?.message || 'Error updating task', 'error');
                }
            });
        });

        function confirmDelete(taskId) {
            const deleteModal = $('#deleteModal');
            $('#deleteForm').off('submit').attr('action', '{{ url('tasks/delete') }}/' + taskId);
            $('#deleteForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const deleteBtn = form.find('button[type="submit"]');
                const originalBtnText = deleteBtn.html();
                deleteBtn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...');
                const formData = new FormData(this);
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message || 'Task deleted successfully!', 'success');
                            deleteModal.modal('hide');
                            $(`tr[data-task-id="${taskId}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            showToast(response.message || 'Failed to delete task', 'error');
                            deleteBtn.prop('disabled', false).html(originalBtnText);
                        }
                    },
                    error: function(xhr) {
                        deleteBtn.prop('disabled', false).html(originalBtnText);
                        showToast(xhr.responseJSON?.message || 'Error deleting task', 'error');
                    }
                });
            });
            deleteModal.modal('show');
        }

        // Export functionality
        $('.export-btn').click(function(e) {
            e.preventDefault();
            const type = $(this).data('type');
            const params = new URLSearchParams(window.location.search);
            params.set('export', type);
            window.location.href = '{{ route('task.assigned-by-me') }}?' + params.toString();
        });
    </script>
@endsection
