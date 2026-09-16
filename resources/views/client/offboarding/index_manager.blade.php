{{-- resources/views/client/offboarding/index_manager.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== OFFBOARDING STATUS BADGES ==================== */
        .offboarding-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending_approval {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background: #f1f5f9;
            color: #475569;
        }

        /* ==================== STAGE BADGES ==================== */
        .stage-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .stage-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .stage-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .stage-pending {
            background: #fef3c7;
            color: #92400e;
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

        /* ==================== FILTER SECTION ==================== */
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

        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
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

        /* ==================== TABLE STYLES ==================== */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #edf2f7;
            background: #fafbfc;
        }

        .card-header h5 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            color: #1e293b;
        }

        .card-header small {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
            display: block;
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

        .table tbody tr.approval-pending {
            background: #eef2ff;
            border-left: 3px solid #4f46e5;
        }

        /* ==================== EMPLOYEE AVATAR ==================== */
        .employee-avatar {
            width: 36px;
            height: 36px;
            background: #4f46e5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-details {
            line-height: 1.4;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
        }

        .employee-id {
            font-size: 11px;
            color: #64748b;
        }

        /* ==================== BADGE STYLES ==================== */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* ==================== BUTTON STYLES ==================== */
        .btn-group-sm {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 11px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
            padding: 15px;
            display: flex;
            justify-content: flex-end;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 11px;
            padding: 5px 10px;
            border-radius: 6px !important;
            text-decoration: none;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .page-item.active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        /* ==================== MODAL STYLES ==================== */
        .modal-header {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h6 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
        }

        .modal-header.bg-danger {
            background: #ef4444 !important;
        }

        .modal-header.bg-danger h6 {
            color: white;
        }

        .modal-body {
            padding: 16px;
        }

        .modal-body p {
            font-size: 13px;
            margin-bottom: 12px;
            color: #1e293b;
        }

        .modal-footer {
            padding: 12px 16px;
            border-top: 1px solid #e2e8f0;
        }

        .modal-form-group {
            margin-bottom: 12px;
        }

        .modal-form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 5px;
            display: block;
        }

        .modal-form-group .required:after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }

        .form-control-sm {
            padding: 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 100%;
        }

        .form-control-sm:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .reject-stage-info {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 12px;
            color: #9a3412;
        }

        /* ==================== EMPTY STATE ==================== */
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
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 12px;
            color: #94a3b8;
        }

        /* ==================== ACTION BUTTON ==================== */
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

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
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

            .btn-group-sm {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
@endsection

@section('content-area')
    @php
        $userRole = auth()->user()->role;
        $currentStatus = request('status');
    @endphp

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Team Offboarding Requests</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Team Offboarding</li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Statistics Cards -->
        {{-- <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $stats['total'] ?? 0 }}</h3>
                    <p>Total Requests</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-file-text"></i>
                </div>
            </div>
            <div class="stats-card" data-status="pending_approval">
                <div class="stats-info">
                    <h3>{{ $stats['pending_manager'] ?? 0 }}</h3>
                    <p>Pending Your Approval</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-clock"></i>
                </div>
            </div>
            <div class="stats-card" data-status="approved">
                <div class="stats-info">
                    <h3>{{ $stats['approved'] ?? 0 }}</h3>
                    <p>Approved by You</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="rejected">
                <div class="stats-info">
                    <h3>{{ $stats['rejected'] ?? 0 }}</h3>
                    <p>Rejected</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-x-circle"></i>
                </div>
            </div>
        </div> --}}

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Offboarding Requests
                    @php
                        $activeFilterCount = collect(request()->only(['status', 'search']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'search']))
                    <a href="{{ route('offboarding.manager') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('offboarding.manager') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>
                                Pending Approval
                            </option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>
                                Approved
                            </option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>
                                Rejected
                            </option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>
                                Cancelled
                            </option>
                        </select>
                    </div>
                     <div class="filter-item">
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('employee_id') && ($selectedEmployee = $employees->firstWhere('id', request('employee_id'))))
                                        <span class="employee-initials">{{ strtoupper(substr($selectedEmployee->name, 0, 2)) }}</span>
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
                                        href="{{ route('offboarding.manager', array_merge(request()->except(['employee_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('offboarding.manager', array_merge(request()->except(['page']), ['employee_id' => $user->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $user->name }} (<small class="text-muted">{{ $user->employee_id ?? 'N/A' }}</small>)</span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- <div class="filter-item" style="flex: 1;">
                        <input type="text" name="search" class="filter-select"
                            placeholder="Search by name, email or employee ID..." value="{{ request('search') }}"
                            style="background-image: none; padding-left: 32px;">
                    </div> --}}

                    <div class="filter-item">
                        <a href="{{ route('offboarding.manager') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Team Offboarding Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Team Offboarding Requests</h5>
                <small>Requests requiring your approval are highlighted</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Reason</th>
                                <th>Last Working Date</th>
                                <th>Request Date</th>
                                <th>Status / Stage</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($offboardings as $index => $offboarding)
                                @php
                                    // Determine current stage for display
                                    if ($offboarding->manager_review_status == 'pending') {
                                        $currentStage = 'Manager Review';
                                        $stageClass = 'badge-warning';
                                        $stageIcon = 'feather-clock';
                                    } elseif ($offboarding->manager_review_status == 'approved') {
                                        if ($offboarding->hr_review_status == 'pending') {
                                            $currentStage = 'HR Review';
                                            $stageClass = 'badge-info';
                                            $stageIcon = 'feather-users';
                                        } elseif ($offboarding->status == 'approved') {
                                            $currentStage = 'In Progress';
                                            $stageClass = 'badge-info';
                                            $stageIcon = 'feather-loader';
                                        } elseif ($offboarding->status == 'completed') {
                                            $currentStage = 'Completed';
                                            $stageClass = 'badge-success';
                                            $stageIcon = 'feather-check-circle';
                                        } else {
                                            $currentStage = ucfirst(str_replace('_', ' ', $offboarding->status));
                                            $stageClass = 'badge-secondary';
                                            $stageIcon = 'feather-info';
                                        }
                                    } elseif ($offboarding->manager_review_status == 'rejected') {
                                        $currentStage = 'Rejected by You';
                                        $stageClass = 'badge-danger';
                                        $stageIcon = 'feather-x-circle';
                                    } else {
                                        $currentStage = ucfirst(str_replace('_', ' ', $offboarding->status));
                                        $stageClass = 'badge-secondary';
                                        $stageIcon = 'feather-info';
                                    }
                                    
                                    // Permission flags for manager (mirroring show blade)
                                    $canApproveManager = $offboarding->manager_review_status == 'pending' && 
                                        $offboarding->status == 'pending_approval';
                                    
                                    $canManagerReject = $offboarding->manager_review_status == 'pending' && 
                                        $offboarding->status == 'pending_approval';
                                @endphp
                                <tr class="{{ $offboarding->manager_review_status == 'pending' ? 'approval-pending' : '' }}">
                                    <td>{{ $offboardings->firstItem() + $index }}</td>
                                    <td><strong class="text-primary">{{ $offboarding->request_code }}</strong></td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($offboarding->employee->name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">{{ $offboarding->employee->name ?? 'N/A' }} <small>( {{ $offboarding->employee->employee_id ?? 'N/A' }} )</small></div>
                                                {{-- <div class="employee-id">{{ $offboarding->employee->employee_id ?? 'N/A' }}</div> --}}
                                                <div class="employee-id" style="font-size: 10px;">{{ $offboarding->employee->email ?? '' }}</div>
                                            </div>
                                        </div>
                                     </div>
                                    <td>
                                        <span class="badge badge-secondary">
                                            {{ $offboarding->employee->jobDetails->Department->name ?? 'N/A' }}
                                        </span>
                                     </div>
                                    <td>{{ $offboarding->reason_label ?? ucfirst($offboarding->reason ?? 'N/A') }}</div>
                                    <td>{{ $offboarding->last_working_date ? \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M, Y') : 'N/A' }}</div>
                                    <td>{{ $offboarding->created_at ? $offboarding->created_at->format('d M, Y') : 'N/A' }}</div>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="offboarding-status status-{{ $offboarding->status }}">
                                                {{ ucfirst(str_replace('_', ' ', $offboarding->status)) }}
                                            </span>
                                            {{-- <span class="badge {{ $stageClass }}" style="display: inline-flex; align-items: center; gap: 4px; width: fit-content;">
                                                <i class="{{ $stageIcon }}" style="font-size: 10px;"></i>
                                                {{ $currentStage }}
                                            </span> --}}
                                            {{-- @if($offboarding->manager_review_status == 'rejected' && $offboarding->manager_review_comments)
                                                <small class="text-danger mt-1" style="font-size: 10px;">
                                                    <i class="feather-message-circle"></i> {{ \Illuminate\Support\Str::limit($offboarding->manager_review_comments, 50) }}
                                                </small>
                                            @endif --}}
                                        </div>
                                     </div>
                                    <td>
                                        @if ($canApproveManager)
                                            <div class="btn-group-sm">
                                                <button class="btn btn-success btn-sm" onclick="approveRequest({{ $offboarding->id }})">
                                                    <i class="feather-check"></i> Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="showManagerRejectModal({{ $offboarding->id }})">
                                                    <i class="feather-x"></i> Reject
                                                </button>
                                            </div>
                                        @else
                                            <a href="{{ route('offboarding.show', $offboarding->id) }}" class="btn btn-secondary btn-sm">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                        @endif
                                     </div>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="feather-inbox"></i>
                                            <h4>No Team Offboarding Requests</h4>
                                            <p>No offboarding requests from your team members found.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($offboardings->hasPages())
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Showing {{ $offboardings->firstItem() }} to {{ $offboardings->lastItem() }} of
                                {{ $offboardings->total() }} entries
                            </div>
                            <div>
                                {{ $offboardings->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Approve — Manager Review</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveForm" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-body">
                        <p>Approve this offboarding request at the Manager Review stage?</p>
                        <div class="modal-form-group">
                            <label>Comments (Optional)</label>
                            <textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="Add any comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manager Reject Modal (with stage info) -->
    <div class="modal fade" id="managerRejectModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="feather-x-circle me-2"></i>Reject Offboarding Request</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="managerRejectForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="reject-stage-info">
                            <strong><i class="feather-info"></i> Rejecting at: Manager Review Stage</strong>
                            This will reject the offboarding request at the Manager Review stage. The employee will be notified.
                        </div>
                        <div class="modal-form-group">
                            <label class="required">Rejection Reason <span style="color:red">*</span></label>
                            <textarea name="comments" class="form-control form-control-sm" rows="3" 
                                placeholder="Provide a clear reason for rejection..." required minlength="3"></textarea>
                            <small class="text-muted">This reason will be visible to the employee.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        function approveRequest(id) {
            $('#approveForm').attr('action', '/offboarding/' + id + '/manager-review');
            $('#approveModal').modal('show');
        }

        function showManagerRejectModal(id) {
            $('#managerRejectForm').attr('action', '/offboarding/' + id + '/manager-review');
            $('#managerRejectModal').modal('show');
        }

        // Auto-submit on search (with debounce)
        let searchTimeout;
        $('input[name="search"]').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                $('#filterForm').submit();
            }, 500);
        });

        // Stats card click filter
        $(document).ready(function() {
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('offboarding.manager') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('offboarding.manager') }}';
                }
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus === 'pending_approval') {
                $('.stats-card[data-status="pending_approval"]').addClass('active');
            } else if (currentStatus === 'approved') {
                $('.stats-card[data-status="approved"]').addClass('active');
            } else if (currentStatus === 'rejected') {
                $('.stats-card[data-status="rejected"]').addClass('active');
            } else {
                $('.stats-card[data-status="all"]').addClass('active');
            }
        });
    </script>
@endsection