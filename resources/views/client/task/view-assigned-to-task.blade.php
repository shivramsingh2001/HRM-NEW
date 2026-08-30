@extends('client.layout.master')

@section('style')
    <style>
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
            background-color: #f3e8ff;
            color: #5b21b6;
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
            color: #1e40af;
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

        /* ==================== STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stats-card {
            background: white;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            cursor: pointer;
        }

        .stats-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
            border-color: #cbd5e1;
        }

        .stats-card.active {
            border: 2px solid #4f46e5;
            background: #eef2ff;
        }

        .stats-info h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: #1e293b;
        }

        .stats-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-icon i {
            font-size: 24px;
            color: #4f46e5;
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

        /* Filter Row */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 160px;
        }

        .filter-item.date-range {
            min-width: 160px;
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
            border-color: #4f46e5;
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
            border-color: #4f46e5;
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

        .table tbody tr.urgent-task {
            background-color: #fee2e2;
        }

        /*
                    .table tbody tr.due-soon {
                        background-color: #fef3c7;
                    } */

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
            color: #4f46e5;
            border-color: #4f46e5;
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
            background: #4f46e5;
            border-color: #4f46e5;
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
                <li class="breadcrumb-item"><a href="{{ route('task.assigned-to-me') }}">Tasks</a></li>
                <li class="breadcrumb-item active">Assigned To Me</li>
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
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="padding: 30px !important;">

        <!-- Task Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $totalTasks ?? 0 }}</h3>
                    <p>Total Tasks</p>
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
        </div>

        <!-- Modern Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Tasks
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'priority', 'project_id', 'date_from', 'date_to']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'priority', 'project_id', 'date_from', 'date_to']) && request('status') != 'all')
                    <a href="{{ route('task.assigned-to-me') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('task.assigned-to-me') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        {{-- <label class="form-label">Status</label> --}}
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
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                            </option>
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div class="filter-item">
                        {{-- <label class="form-label">Priority</label> --}}
                        <select name="priority" class="filter-select">
                            <option value="all">All Priorities</option>
                            <option value="critical" {{ request('priority') == 'critical' ? 'selected' : '' }}>Critical
                            </option>
                            <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                        </select>
                    </div>

                    <!-- Project Filter -->
                    <div class="filter-item">
                        {{-- <label class="form-label">Project</label> --}}
                        <select name="project_id" class="filter-select">
                            <option value="all">All Projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                            <option value="">N/A</option>
                        </select>
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
                        {{-- <label class="form-label">From Date</label> --}}
                        <input type="date" name="date_from" class="filter-date" value="{{ request('date_from') }}">
                    </div>

                    <!-- Date To Filter -->
                    <div class="filter-item date-range">
                        {{-- <label class="form-label">To Date</label> --}}
                        <input type="date" name="date_to" class="filter-date" value="{{ request('date_to') }}">
                    </div>

                    <!-- Reset Button -->
                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('task.assigned-to-me') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'priority', 'project_id', 'date_from', 'date_to']) && request('status') != 'all')
                <div class="active-filters">
                    <span class="active-filters-label">Active Filters:</span>

                    @if (request('status') && request('status') != 'all')
                        <span class="filter-tag">
                            <i class="feather-tag"></i>
                            Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                            <a href="{{ route('task.assigned-to-me', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('priority') && request('priority') != 'all')
                        <span class="filter-tag">
                            <i class="feather-flag"></i>
                            Priority: {{ ucfirst(request('priority')) }}
                            <a href="{{ route('task.assigned-to-me', array_merge(request()->except(['priority', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('project_id') && request('project_id') != 'all')
                        @php
                            $projectName = $projects->where('id', request('project_id'))->first()->name ?? 'Unknown';
                        @endphp
                        <span class="filter-tag">
                            <i class="feather-briefcase"></i>
                            Project: {{ $projectName }}
                            <a href="{{ route('task.assigned-to-me', array_merge(request()->except(['project_id', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('date_from'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ \Carbon\Carbon::parse(request('date_from'))->format('d M Y') }}
                            <a href="{{ route('task.assigned-to-me', array_merge(request()->except(['date_from', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('date_to'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ \Carbon\Carbon::parse(request('date_to'))->format('d M Y') }}
                            <a href="{{ route('task.assigned-to-me', array_merge(request()->except(['date_to', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('task.assigned-to-me') }}" class="filter-tag clear-all">
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
                        <h5 class="card-title mb-0">Tasks Assigned To Me</h5>
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
                                        <th class="text-start">Task Title</th>
                                        <th>Project</th>
                                        <th class="text-start">Assigned By</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Task Date</th>
                                        <th>Deadline Date</th>
                                        <th>Time Left</th>
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
                                            data-task-id="{{ $task['id'] }}"
                                            data-href="{{ route('task.view-detail', ['id' => $task['id']]) }}"
                                            style="cursor: pointer;">
                                            <td>{{ $loop->iteration + ($tasks->currentPage() - 1) * $tasks->perPage() }}
                                            </td>
                                            <td>
                                                <strong>{{ $task['task_code'] ?? 'N/A' }}</strong>
                                            </td>
                                            <td class="text-start">
                                                <div class="fw-semibold">{{ Str::limit($task['title'],30) ?? '' }}</div>
                                                @if ($task['description'])
                                                    <small class="text-muted d-block" style="font-size: 10px;">
                                                        {{ Str::limit($task['description'], 40) }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($task['project_name'])
                                                    <div>
                                                        <span>{{ $task['project_name'] }}</span>
                                                        @if ($task['project_code'])
                                                            <br><small class="text-muted">{{ $task['project_code'] }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td class="text-start">
                                                <div class="employee-info">
                                                    <div class="employee-avatar"
                                                        style="background: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                        {{ strtoupper(substr($task['assigned_by_name'] ?? 'U', 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name">
                                                            {{ $task['assigned_by_name'] ?? 'N/A' }}
                                                            <small
                                                                class="text-muted employee-email">({{ $task['assigned_by_employee_id'] ?? 'N/A' }})</small>
                                                        </div>
                                                        <div class="employee-email">
                                                            {{ $task['assigned_by_email'] ?? '' }}
                                                        </div>
                                                    </div>
                                                </div>

                                            </td>
                                            <td>
                                                @php
                                                    $priorityClass = 'priority-' . ($task['priority'] ?? 'medium');
                                                @endphp
                                                <span class="task-priority {{ $priorityClass }}">
                                                    {{ ucfirst($task['priority'] ?? 'N/A') }}
                                                </span>
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = 'status-' . ($task['status'] ?? 'pending');
                                                @endphp
                                                <span class="task-status {{ $statusClass }}">
                                                    {{ ucfirst(str_replace('_', ' ', $task['status'] ?? 'Pending')) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($task['task_date'])
                                                    {{ \Carbon\Carbon::parse($task['task_date'])->format('d M, Y') }}
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($task['deadline_date'])
                                                    {{ \Carbon\Carbon::parse($task['deadline_date'])->format('d M, Y') }}
                                                @else
                                                    <span class="text-muted">Not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    @if (!empty($task['deadline_date']))
                                                        @php
                                                            $status = $task['status'];
                                                            $daysLeft = $task['days_remaining'] ?? null;
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
                                                        data-bs-offset="0,5">
                                                        <i class="feather-more-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        @if (!in_array($task['status'], ['completed', 'approved', 'rejected']))
                                                            <li>
                                                                <button type="button" class="dropdown-item"
                                                                    onclick="showStatusUpdateModal({{ $task['id'] }})">
                                                                    <i class="feather-edit text-warning me-2"></i>
                                                                    Update Status
                                                                </button>
                                                            </li>
                                                        @endif
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('task.view-detail', ['id' => $task['id']]) }}">
                                                                <i class="feather-eye me-2"></i>
                                                                View Details
                                                            </a>
                                                        </li>
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
                                                    <p>No tasks assigned to you match the selected filters.</p>
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
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                            <div class="invalid-feedback" id="statusError"></div>
                        </div>

                        <!-- Deadline Extension Section -->
                        <div id="deadlineExtensionSection" class="mb-3" style="display: none;">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="extendDeadline"
                                    name="extend_deadline" value="1">
                                <label class="form-check-label" for="extendDeadline">Extend Deadline</label>
                            </div>
                            <div id="deadlineFields" style="display: none;">
                                <div class="mb-2">
                                    <label for="newDeadline" class="form-label">New Deadline *</label>
                                    <input type="date" class="form-control" id="newDeadline" name="new_deadline">
                                    <div class="invalid-feedback" id="newDeadlineError"></div>
                                    <small class="text-muted">Current deadline: <span id="currentDeadline"></span></small>
                                </div>
                            </div>
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
        $(document).ready(function() {
            // Auto-submit on filter change
            $('.filter-select, .filter-date').on('change', function() {
                $('#filterForm').submit();
            });

            $('.clickable-row').on('click', function(e) {
                // Don't trigger if clicking on dropdown or action buttons
                if ($(e.target).closest('.dropdown, .action-btn, .dropdown-menu, .dropdown-item, a, button')
                    .length === 0) {
                    window.location.href = $(this).data('href');
                }
            });

            // Stats card click filter
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('task.assigned-to-me') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('task.assigned-to-me') }}';
                }
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus && currentStatus !== 'all') {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else if (!currentStatus || currentStatus === 'all') {
                $('.stats-card[data-status="all"]').addClass('active');
            }

            // ==================== STATUS UPDATE MODAL HANDLERS ====================

            $('#status').on('change', function() {
                const status = $(this).val();
                const canExtend = ['pending', 'in_progress', 'hold'].includes(status);
                if (canExtend) {
                    $('#deadlineExtensionSection').slideDown();
                } else {
                    $('#deadlineExtensionSection').slideUp();
                    $('#extendDeadline').prop('checked', false);
                    $('#deadlineFields').hide();
                }
            });

            $('#extendDeadline').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#deadlineFields').slideDown();
                    $('#newDeadline').prop('required', true);
                } else {
                    $('#deadlineFields').slideUp();
                    $('#newDeadline').prop('required', false);
                }
            });

            $('#statusUpdateForm').on('submit', function(e) {
                e.preventDefault();

                const taskId = $('#statusTaskIds').val();
                const status = $('#status').val();
                const remarks = $('#remarks').val();
                const extendDeadline = $('#extendDeadline').is(':checked');
                const newDeadline = $('#newDeadline').val();
                const updateBtn = $('#statusUpdateBtn');

                clearStatusFormErrors();

                if (!status) {
                    showFieldError('status', 'Please select a status');
                    return;
                }

                if (extendDeadline && !newDeadline) {
                    showFieldError('newDeadline', 'Please select a new deadline date');
                    return;
                }

                if (extendDeadline && newDeadline) {
                    const currentDeadlineText = $('#currentDeadline').text().trim();
                    let currentDeadlineDate = null;

                    if (currentDeadlineText !== 'Not set') {
                        const dateParts = currentDeadlineText.match(/(\d+)\s+(\w+),\s+(\d{4})/);
                        if (dateParts) {
                            const day = parseInt(dateParts[1]);
                            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
                                'Sep', 'Oct', 'Nov', 'Dec'
                            ];
                            const month = monthNames.indexOf(dateParts[2]);
                            const year = parseInt(dateParts[3]);
                            currentDeadlineDate = new Date(year, month, day);
                        }
                    }

                    const newDeadlineDate = new Date(newDeadline);

                    if (currentDeadlineDate && newDeadlineDate <= currentDeadlineDate) {
                        showFieldError('newDeadline', 'New deadline must be after the current deadline');
                        return;
                    }
                }

                updateBtn.prop('disabled', true);
                updateBtn.find('.spinner-border').removeClass('d-none');

                const postData = {
                    _token: '{{ csrf_token() }}',
                    task_id: taskId,
                    status: status,
                    remarks: remarks,
                    extend_deadline: extendDeadline ? 1 : 0
                };

                if (extendDeadline) {
                    postData.new_deadline = newDeadline;
                }

                $.ajax({
                    url: '{{ route('task.update-status') }}',
                    method: 'POST',
                    data: postData,
                    dataType: 'json',
                    success: function(response) {
                        updateBtn.prop('disabled', false);
                        updateBtn.find('.spinner-border').addClass('d-none');

                        if (response.success) {
                            $('#statusUpdateError').removeClass('alert-danger d-none')
                                .addClass('alert-success')
                                .html('<i class="feather-check-circle me-2"></i>' + (response
                                    .message || 'Status updated successfully!'));

                            setTimeout(function() {
                                $('#statusUpdateModal').modal('hide');
                                location.reload();
                            }, 1500);
                        } else {
                            showStatusFormError(response.message || 'Failed to update status.');
                        }
                    },
                    error: function(xhr) {
                        updateBtn.prop('disabled', false);
                        updateBtn.find('.spinner-border').addClass('d-none');

                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            displayStatusFormErrors(xhr.responseJSON.errors);
                        } else {
                            showStatusFormError('Error updating status. Please try again.');
                        }
                    }
                });
            });

            function clearStatusFormErrors() {
                $('#statusUpdateError').addClass('d-none').removeClass('alert-danger alert-success');
                $('#status').removeClass('is-invalid');
                $('#remarks').removeClass('is-invalid');
                $('#newDeadline').removeClass('is-invalid');
                $('.invalid-feedback').empty().hide();
            }

            function showStatusFormError(message) {
                $('#statusUpdateError').removeClass('d-none')
                    .addClass('alert-danger')
                    .html('<i class="feather-alert-circle me-2"></i>' + message);
            }

            function displayStatusFormErrors(errors) {
                clearStatusFormErrors();
                if (errors.status) showFieldError('status', errors.status[0]);
                if (errors.remarks) showFieldError('remarks', errors.remarks[0]);
                if (errors.new_deadline) showFieldError('newDeadline', errors.new_deadline[0]);
            }

            function showFieldError(fieldName, message) {
                const field = $(`#${fieldName}`);
                const errorDiv = $(`#${fieldName}Error`);
                field.addClass('is-invalid');
                errorDiv.text(message).show();
            }
        });

        function showStatusUpdateModal(taskId) {
            const row = $(`tr[data-task-id="${taskId}"]`);
            const deadlineCell = row.find('td:nth-child(8)').text().trim();

            $('#statusTaskIds').val(taskId);
            $('#currentDeadline').text(deadlineCell || 'Not set');

            $('#statusUpdateError').addClass('d-none').removeClass('alert-danger alert-success');
            $('#status').val('').removeClass('is-invalid');
            $('#remarks').val('').removeClass('is-invalid');
            $('#newDeadline').val('').removeClass('is-invalid');
            $('#extendDeadline').prop('checked', false);
            $('#deadlineFields').hide();
            $('#deadlineExtensionSection').hide();
            $('.invalid-feedback').empty().hide();

            if (deadlineCell && deadlineCell !== 'Not set') {
                try {
                    const currentDate = new Date(deadlineCell);
                    if (!isNaN(currentDate.getTime())) {
                        const minDate = new Date(currentDate);
                        minDate.setDate(minDate.getDate() + 1);
                        $('#newDeadline').attr('min', minDate.toISOString().split('T')[0]);
                    }
                } catch (e) {}
            }

            $('#statusUpdateModal').modal('show');
        }
    </script>
@endsection
