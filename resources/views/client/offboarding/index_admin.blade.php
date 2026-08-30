{{-- resources/views/client/offboarding/index_admin.blade.php --}}
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

        .stage-not_started {
            background: #f1f5f9;
            color: #64748b;
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
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 32px;
            height: 32px;
            background: #4f46e5;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            font-size: 12px;
            color: #1e293b;
        }

        .employee-email {
            font-size: 10px;
            color: #64748b;
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

        /* ==================== CUSTOM EMPLOYEE DROPDOWN ==================== */
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

        .employee-initials {
            width: 32px;
            height: 32px;
            background: #eef2ff;
            color: #4f46e5;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
        }

        /* ==================== BUTTON STYLES ==================== */
        .btn-xs {
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 4px;
        }

        .btn-xs i {
            font-size: 10px;
            margin-right: 2px;
        }

        .stage-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
            padding: 15px;
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

        /* ==================== MODAL STYLES ==================== */
        .modal-form-group {
            margin-bottom: 15px;
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

        .reject-stage-info {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 12px;
            color: #9a3412;
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
            
            .stage-buttons {
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content-area')
    @php
        $userRole = auth()->user()->role ?? 'employee';
        $isAdminOrHR = in_array($userRole, ['admin', 'hr']);
    @endphp

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Offboarding Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Offboarding</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('offboarding.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-user-minus me-1"></i>
                <span>New Offboarding Request</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" style="padding: 30px !important;">

        <!-- Statistics Cards -->
        <div class="stats-grid">
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
                    <h3>{{ $stats['pending_approval'] ?? 0 }}</h3>
                    <p>Pending Approval</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-clock"></i>
                </div>
            </div>
            <div class="stats-card" data-status="approved">
                <div class="stats-info">
                    <h3>{{ $stats['approved'] ?? 0 }}</h3>
                    <p>Approved</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>
            <div class="stats-card" data-status="completed">
                <div class="stats-info">
                    <h3>{{ $stats['completed'] ?? 0 }}</h3>
                    <p>Completed</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-square"></i>
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
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Offboarding Requests
                    @php
                        $activeFilterCount = collect(request()->only(['status', 'employee_id', 'search', 'stage']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'employee_id', 'search', 'stage']))
                    <a href="{{ route('offboarding.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('offboarding.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="pending_approval" {{ request('status') == 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
                                        href="{{ route('offboarding.index', array_merge(request()->except(['employee_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('offboarding.index', array_merge(request()->except(['page']), ['employee_id' => $user->id])) }}">
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

                    <div class="filter-item">
                        <a href="{{ route('offboarding.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Offboarding Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Offboarding Requests</h5>
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
                                <th>Resignation Date</th>
                                <th>Last Working Day</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($offboardings as $index => $offboarding)
                                @php
                                    // Determine which actions to show based on status and review states (mirroring show blade logic)
                                    $canApproveManager = $isAdminOrHR && 
                                        $offboarding->manager_review_status == 'pending' && 
                                        $offboarding->status == 'pending_approval';
                                    
                                    $canApproveHR = $isAdminOrHR && 
                                        $offboarding->manager_review_status == 'approved' && 
                                        $offboarding->hr_review_status == 'pending';
                                    
                                    $canReject = $isAdminOrHR && 
                                        !in_array($offboarding->status, ['completed', 'cancelled', 'rejected']);
                                    
                                    $canStartKT = $isAdminOrHR && 
                                        $offboarding->status == 'approved' && 
                                        $offboarding->knowledge_transfer_status == 'not_started';
                                    
                                    $canCompleteKT = $isAdminOrHR && 
                                        $offboarding->knowledge_transfer_status == 'in_progress';
                                    
                                    $canUpdateClearance = $isAdminOrHR && $offboarding->status == 'approved';
                                    
                                    $canProcessSettlement = $isAdminOrHR && 
                                        $offboarding->asset_return_status == 'completed' && 
                                        $offboarding->final_settlement_status == 'pending';
                                    
                                    $canMarkSettlementPaid = $isAdminOrHR && 
                                        $offboarding->final_settlement_status == 'processing';
                                    
                                    $canComplete = $isAdminOrHR && 
                                        $offboarding->knowledge_transfer_status == 'completed' && 
                                        $offboarding->asset_return_status == 'completed' && 
                                        $offboarding->final_settlement_status == 'paid' && 
                                        $offboarding->status == 'approved';
                                    
                                    $canCancel = $isAdminOrHR && 
                                        in_array($offboarding->status, ['pending_approval', 'approved']);
                                    
                                    // Determine current stage label
                                    if ($offboarding->manager_review_status == 'pending') {
                                        $currentStage = 'Manager Review';
                                    } elseif ($offboarding->manager_review_status == 'approved' && $offboarding->hr_review_status == 'pending') {
                                        $currentStage = 'HR Review';
                                    } elseif ($offboarding->status == 'approved') {
                                        $currentStage = 'Approved - In Progress';
                                    } elseif ($offboarding->status == 'completed') {
                                        $currentStage = 'Completed';
                                    } elseif ($offboarding->status == 'rejected') {
                                        $currentStage = 'Rejected';
                                    } elseif ($offboarding->status == 'cancelled') {
                                        $currentStage = 'Cancelled';
                                    } else {
                                        $currentStage = 'Initiated';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $offboardings->firstItem() + $index }}</td>
                                    <td><strong class="text-primary">{{ $offboarding->request_code }}</strong></td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($offboarding->employee->name ?? 'U', 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">
                                                    {{ $offboarding->employee->name }}
                                                    <small class="text-muted">({{ $offboarding->employee->employee_id ?? 'N/A' }})</small>
                                                </div>
                                                <div class="employee-email">{{ $offboarding->employee->email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $offboarding->employee->jobDetails->Department->name ?? 'N/A' }}</td>
                                    <td>{{ $offboarding->resignation_date ? \Carbon\Carbon::parse($offboarding->resignation_date)->format('d M Y') : 'N/A' }}</td>
                                    <td>{{ $offboarding->last_working_date ? \Carbon\Carbon::parse($offboarding->last_working_date)->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <span class="offboarding-status status-{{ $offboarding->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $offboarding->status)) }}
                                        </span>
                                        {{-- <small class="d-block text-muted mt-1">{{ $currentStage }}</small> --}}
                                    </td>
                                    <td>
                                        <div class="stage-buttons">
                                            @if ($canApproveManager)
                                                <button class="btn btn-success btn-xs" onclick="approveManager({{ $offboarding->id }})">
                                                    <i class="feather-check"></i> Approve (Mgr)
                                                </button>
                                            @endif

                                            @if ($canApproveHR)
                                                <button class="btn btn-primary btn-xs" onclick="approveHR({{ $offboarding->id }})">
                                                    <i class="feather-check-circle"></i> Approve (HR)
                                                </button>
                                            @endif

                                            @if ($canReject)
                                                <button class="btn btn-danger btn-xs" onclick="showRejectModal({{ $offboarding->id }})">
                                                    <i class="feather-x"></i> Reject
                                                </button>
                                            @endif

                                            @if ($canStartKT)
                                                <button class="btn btn-info btn-xs" onclick="showStartKTModal({{ $offboarding->id }})">
                                                    <i class="feather-upload"></i> Start KT
                                                </button>
                                            @endif

                                            @if ($canCompleteKT)
                                                <button class="btn btn-success btn-xs" onclick="showCompleteKTModal({{ $offboarding->id }})">
                                                    <i class="feather-check-square"></i> Complete KT
                                                </button>
                                            @endif

                                            @if ($canUpdateClearance)
                                                <button class="btn btn-warning btn-xs" onclick="showClearanceModal({{ $offboarding->id }})">
                                                    <i class="feather-shield"></i> Update Clearance
                                                </button>
                                            @endif

                                            @if ($canProcessSettlement)
                                                <button class="btn btn-info btn-xs" onclick="showSettlementModal({{ $offboarding->id }})">
                                                    <i class="feather-dollar-sign"></i> Process Settlement
                                                </button>
                                            @endif

                                            @if ($canMarkSettlementPaid)
                                                <button class="btn btn-success btn-xs" onclick="showMarkPaidModal({{ $offboarding->id }})">
                                                    <i class="feather-credit-card"></i> Mark Paid
                                                </button>
                                            @endif

                                            @if ($canComplete)
                                                <button class="btn btn-primary btn-xs" onclick="showCompleteModal({{ $offboarding->id }})">
                                                    <i class="feather-check-square"></i> Complete
                                                </button>
                                            @endif

                                            @if ($canCancel)
                                                <button class="btn btn-secondary btn-xs" onclick="showCancelModal({{ $offboarding->id }})">
                                                    <i class="feather-slash"></i> Cancel
                                                </button>
                                            @endif

                                            <!-- View Details Button -->
                                            <a href="{{ route('offboarding.show', $offboarding->id) }}" class="btn btn-outline-secondary btn-xs">
                                                <i class="feather-eye fs-11"></i> 
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">No offboarding requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center">
                    {{ $offboardings->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- ==================== MODAL 1: Approve Manager Modal ==================== -->
    <div class="modal fade" id="approveManagerModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Approve — Manager Review</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveManagerForm" method="POST">
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

    <!-- ==================== MODAL 2: Approve HR Modal ==================== -->
    <div class="modal fade" id="approveHRModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Approve — HR Review</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveHRForm" method="POST">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <div class="modal-body">
                        <div class="modal-form-group">
                            <label>Last Working Date</label>
                            <input type="date" name="last_working_date" class="form-control form-control-sm"
                                value="{{ old('last_working_date') }}">
                        </div>
                        <div class="modal-form-group">
                            <label>HR Comments</label>
                            <textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="Add HR comments..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 3: Reject Modal ==================== -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title"><i class="feather-x-circle me-2"></i>Reject Offboarding Request</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="reject-stage-info">
                            <strong><i class="feather-info"></i> Rejection Notice</strong>
                            This will reject the offboarding request. The employee will be notified.
                        </div>
                        <div class="modal-form-group">
                            <label class="required">Rejection Reason <span style="color:red">*</span></label>
                            <textarea name="rejection_reason" class="form-control form-control-sm" rows="3"
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

    <!-- ==================== MODAL 4: Start Knowledge Transfer Modal ==================== -->
    <div class="modal fade" id="startKTModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Start Knowledge Transfer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="startKTForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>Start the knowledge transfer process?</p>
                        <div class="modal-form-group">
                            <label>Notes (Optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add any notes about KT..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Start KT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 5: Complete Knowledge Transfer Modal ==================== -->
    <div class="modal fade" id="completeKTModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Complete Knowledge Transfer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="completeKTForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>Mark knowledge transfer as completed?</p>
                        <div class="modal-form-group">
                            <label>Completion Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Add completion notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Complete KT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 6: Update Clearance Modal ==================== -->
    <div class="modal fade" id="clearanceModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Update Clearance Status</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="clearanceForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="feather-info"></i> Update the clearance status for this offboarding request.
                        </div>

                        <div class="modal-form-group">
                            <label class="required">Asset Return Status</label>
                            <select name="asset_return_status" class="form-control form-control-sm" required>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="modal-form-group">
                            <label class="required">Document Return Status</label>
                            <select name="document_return_status" class="form-control form-control-sm" required>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="modal-form-group">
                            <label class="required">Overall Clearance Status</label>
                            <select name="clearance_status" class="form-control form-control-sm" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div class="modal-form-group">
                            <label>Remarks (Optional)</label>
                            <textarea name="clearance_remarks" class="form-control form-control-sm" rows="2"
                                placeholder="Add any remarks about clearance..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Update Clearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 7: Process Final Settlement Modal ==================== -->
    <div class="modal fade" id="settlementModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Process Final Settlement</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="settlementForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="feather-alert-triangle"></i>
                            <strong>Note:</strong> Asset clearance must be completed before processing settlement.
                        </div>

                        <div class="modal-form-group">
                            <label class="required">Full & Final Settlement Amount (₹)</label>
                            <input type="number" name="full_final_settlement" class="form-control form-control-sm"
                                step="1000" placeholder="Enter settlement amount" required>
                        </div>

                        <div class="modal-form-group">
                            <label>Settlement Notes</label>
                            <textarea name="settlement_notes" class="form-control form-control-sm" rows="2"
                                placeholder="Add any notes about the settlement..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm">Process Settlement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 8: Mark Settlement as Paid Modal ==================== -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Mark Settlement as Paid</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="markPaidForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>Mark the final settlement as paid?</p>
                        <div class="modal-form-group">
                            <label class="required">Payment Date</label>
                            <input type="date" name="settlement_paid_date" class="form-control form-control-sm"
                                value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Payment Reference / Transaction ID</label>
                            <input type="text" name="payment_reference" class="form-control form-control-sm"
                                placeholder="Transaction ID / Cheque No / UTR">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm">Mark as Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 9: Complete Offboarding Modal ==================== -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Complete Offboarding Process</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="completeForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-success mb-3">
                            <i class="feather-check-circle"></i>
                            <strong>Ready to complete offboarding!</strong>
                            <ul class="mb-0 mt-2">
                                <li>✓ Knowledge Transfer: Completed</li>
                                <li>✓ Asset Clearance: Completed</li>
                                <li>✓ Final Settlement: Paid</li>
                            </ul>
                        </div>

                        <div class="modal-form-group">
                            <label>Completion Remarks</label>
                            <textarea name="completion_remarks" class="form-control form-control-sm" rows="3"
                                placeholder="Any final remarks about the offboarding process..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Complete Offboarding</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 10: Cancel Request Modal ==================== -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Cancel Offboarding Request</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="cancelForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this offboarding request? This action restores the employee to active status.</p>
                        <div class="modal-form-group">
                            <label>Cancellation Reason (Optional)</label>
                            <textarea name="cancellation_reason" class="form-control form-control-sm" rows="2"
                                placeholder="Enter reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">Cancel Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        // Modal functions with proper action URL setting
        function approveManager(id) {
            $('#approveManagerForm').attr('action', '/offboarding/' + id + '/manager-review');
            $('#approveManagerModal').modal('show');
        }

        function approveHR(id) {
            $('#approveHRForm').attr('action', '/offboarding/' + id + '/hr-review');
            $('#approveHRModal').modal('show');
        }

        function showRejectModal(id) {
            $('#rejectForm').attr('action', '/offboarding/' + id + '/reject');
            $('#rejectModal').modal('show');
        }

        function showStartKTModal(id) {
            $('#startKTForm').attr('action', '/offboarding/' + id + '/knowledge-transfer/start');
            $('#startKTModal').modal('show');
        }

        function showCompleteKTModal(id) {
            $('#completeKTForm').attr('action', '/offboarding/' + id + '/knowledge-transfer/complete');
            $('#completeKTModal').modal('show');
        }

        function showClearanceModal(id) {
            $('#clearanceForm').attr('action', '/offboarding/' + id + '/asset-clearance/update');
            $('#clearanceModal').modal('show');
        }

        function showSettlementModal(id) {
            $('#settlementForm').attr('action', '/offboarding/' + id + '/final-settlement/process');
            $('#settlementModal').modal('show');
        }

        function showMarkPaidModal(id) {
            $('#markPaidForm').attr('action', '/offboarding/' + id + '/final-settlement/paid');
            $('#markPaidModal').modal('show');
        }

        function showCompleteModal(id) {
            $('#completeForm').attr('action', '/offboarding/' + id + '/complete');
            $('#completeModal').modal('show');
        }

        function showCancelModal(id) {
            $('#cancelForm').attr('action', '/offboarding/' + id + '/cancel');
            $('#cancelModal').modal('show');
        }

        // Stats card click filter
        $(document).ready(function() {
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('offboarding.index') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('offboarding.index') }}';
                }
            });

            // Auto-submit on filter change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus) {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else {
                $('.stats-card[data-status="all"]').addClass('active');
            }
        });
    </script>
@endsection