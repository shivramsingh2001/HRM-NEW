@extends('client.layout.master')

@section('style')
<style>
/* Bulk Actions */
.bulk-actions {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

/* Quick Filter Buttons */
.quick-filter-btn {
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    font-size: 0.7rem;
    border: 1px solid var(--gray-200);
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--gray-600);
}
.quick-filter-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Table Checkbox */
.table input[type="checkbox"] {
    cursor: pointer;
    width: 16px;
    height: 16px;
}

/* Status Badge in Table */
.badge-status {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
}
    /* ==================== GLOBAL STYLES ==================== */
    :root {
        --primary: #4b7bec;
        --primary-light: #f0f4ff;
        --gray-50: #f9fafb;
        --gray-100: #f2f4f6;
        --gray-200: #e9ecef;
        --gray-300: #d1d5db;
        --gray-400: #94a3b8;
        --gray-500: #64748b;
        --gray-600: #475569;
        --gray-700: #334155;
        --gray-800: #1e293b;
        --gray-900: #0f172a;
        --success: #0b5e42;
        --success-light: #e8f7ed;
        --danger: #9b1c1c;
        --danger-light: #fee9e7;
        --warning: #b45f06;
        --warning-light: #fff3e0;
        --info: #0d47a1;
        --info-light: #e3f2fd;
    }

    body {
        background-color: #f5f7fa;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* ==================== CARD STYLES WITH FIXED FOOTER ==================== */
    .shift-card {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        transition: all 0.2s ease;
        height: 100%;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .shift-card:hover {
        border-color: var(--gray-300);
        box-shadow: 0 8px 20px rgba(0,0,0,0.03);
    }
    .shift-color-indicator {
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), #a55eea);
        flex-shrink: 0;
    }
    .shift-card-body {
        padding: 1.25rem;
        flex: 1;
    }
    .shift-card-footer {
        padding: 0.75rem 1.25rem;
        background-color: var(--gray-50);
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
    }
    .shift-card-footer .badge-status {
        font-size: 0.75rem;
        padding: 0.25rem 0.6rem;
        margin: 0;
    }
    .shift-card-footer .badge-status i {
        font-size: 0.7rem;
    }
    .shift-card-footer .text-muted-light {
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        color: var(--gray-400);
    }
    .shift-card-footer .text-muted-light i {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .shift-card:hover .shift-card-footer {
        background-color: var(--gray-100);
        border-top-color: var(--gray-300);
    }

    .shift-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    .shift-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--gray-800);
        margin: 0;
        line-height: 1.4;
    }
    .shift-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .shift-time {
        background: var(--gray-50);
        padding: 0.35rem 0.75rem;
        border-radius: 30px;
        font-size: 0.85rem;
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid var(--gray-200);
    }
    .shift-time i {
        color: var(--primary);
        font-size: 0.85rem;
    }
    .shift-detail {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: var(--gray-500);
    }
    .shift-detail i {
        color: var(--primary);
        width: 18px;
        font-size: 0.9rem;
    }
    .shift-detail span {
        color: var(--gray-700);
        font-weight: 500;
    }

    /* ==================== STAT CARDS ==================== */
    .stat-card {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 1rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .stat-card:hover {
        border-color: var(--gray-300);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        background: var(--primary-light);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .stat-content {
        flex: 1;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gray-800);
        line-height: 1.2;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.85rem;
        color: var(--gray-500);
        font-weight: 500;
    }

    /* ==================== TABLE STYLES ==================== */
    .table-container {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        overflow: hidden;
    }
    .table {
        margin-bottom: 0;
    }
    .table thead th {
        background: var(--gray-50);
        color: var(--gray-600);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
    }
    .table tbody td {
        padding: 1rem;
        color: var(--gray-700);
        font-size: 0.9rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--gray-100);
    }
    .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* ==================== BADGES ==================== */
    .badge-status {
        padding: 0.35rem 0.75rem;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        line-height: 1;
    }
    .badge-active {
        background: var(--success-light);
        color: var(--success);
    }
    .badge-inactive {
        background: var(--danger-light);
        color: var(--danger);
    }
    .badge-upcoming {
        background: var(--info-light);
        color: var(--info);
    }
    .badge-ongoing {
        background: var(--warning-light);
        color: var(--warning);
    }
    .badge-completed {
        background: var(--success-light);
        color: var(--success);
    }
    .badge-missed {
        background: var(--danger-light);
        color: var(--danger);
    }

    /* ==================== BUTTONS ==================== */
    .btn-action {
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid var(--gray-200);
        background: #ffffff;
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
    }
    .btn-action:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }
    .btn-action:hover i {
        color: #ffffff;
    }
    .btn-action i {
        font-size: 0.9rem;
        transition: color 0.2s;
        color: var(--primary);
    }
    
    .btn-action-primary {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }
    .btn-action-primary:hover {
        background: #3a6bd9;
        border-color: #3a6bd9;
    }
    .btn-action-primary i {
        color: #ffffff;
    }
    
    .btn-action-danger {
        background: var(--danger);
        border-color: var(--danger);
        color: #ffffff;
    }
    .btn-action-danger:hover {
        background: #c82333;
        border-color: #c82333;
    }

    /* ==================== FILTER SECTION ==================== */
    .filter-card {
        background: #ffffff;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    .filter-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--gray-600);
        margin-bottom: 0.35rem;
    }

    /* ==================== MODAL STYLES ==================== */
    .modal-custom .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .modal-custom .modal-header {
        background: #ffffff;
        border-bottom: 1px solid var(--gray-200);
        padding: 0.75rem 1rem;
    }
    .modal-custom .modal-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-800);
    }
    .modal-custom .modal-title i {
        font-size: 1.1rem;
        color: var(--primary);
    }
    .modal-custom .modal-body {
        padding: 1rem;
    }
    .modal-custom .modal-footer {
        border-top: 1px solid var(--gray-200);
        padding: 0.75rem 1rem;
        background: var(--gray-50);
    }

    /* ==================== FORM ELEMENTS ==================== */
    .form-group {
        margin-bottom: 0.75rem;
    }
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--gray-600);
        margin-bottom: 0.2rem;
    }
    .form-control, .form-select {
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
        height: 36px;
        width: 100%;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(75,123,236,0.1);
        outline: none;
    }
    textarea.form-control {
        height: auto;
        min-height: 60px;
    }
    .form-control-color {
        padding: 0.25rem;
        height: 36px;
    }

    /* ==================== TABS ==================== */
    .nav-tabs-custom {
        border-bottom: 1px solid var(--gray-200);
        margin-bottom: 1.5rem;
        display: flex;
        gap: 1rem;
    }
    .nav-tabs-custom .nav-link {
        color: var(--gray-500);
        font-weight: 500;
        padding: 0.6rem 0;
        border: none;
        background: transparent;
        position: relative;
        font-size: 0.9rem;
        cursor: pointer;
    }
    .nav-tabs-custom .nav-link.active {
        color: var(--primary);
    }
    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary);
    }

    /* ==================== PAGINATION ==================== */
    .pagination-custom {
        display: flex;
        gap: 0.35rem;
        justify-content: center;
        margin-top: 2rem;
    }
    .pagination-custom .page-item .page-link {
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 0.4rem 0.8rem;
        color: var(--gray-600);
        font-size: 0.85rem;
        background: #ffffff;
        text-decoration: none;
    }
    .pagination-custom .page-item.active .page-link {
        background: var(--primary);
        border-color: var(--primary);
        color: #ffffff;
    }

    /* ==================== SELECT2 CUSTOMIZATION ==================== */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--gray-200) !important;
        border-radius: 8px !important;
        min-height: 36px;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--primary) !important;
    }

    /* ==================== UTILITY CLASSES ==================== */
    .text-muted-light {
        color: var(--gray-400);
    }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .p-2 { padding: 0.5rem; }
    .p-3 { padding: 0.75rem; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .shift-card-footer {
            padding: 0.6rem 1rem;
        }
        .stat-value {
            font-size: 1.2rem;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
    }
</style>
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('content-area')

<div class="content-area-header sticky-top">
    <div class="page-header-left d-flex align-items-center gap-2">
        <div class="page-header-title">
           <h5 class="m-b-10">Shift Management</h5>
        </div>
        <ul class="breadcrumb" style="margin-bottom:0.5rem !important;">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item">Shift Management</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="hstack gap-2">
             <button class="btn btn-action" data-bs-toggle="modal" data-bs-target="#assignShiftModal">
                <i class="feather-users"></i> Assign
            </button>
            <button class="btn btn-action btn-action-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                <i class="feather-plus"></i> Add Shift
            </button>
        </div>
    </div>
</div>

<div class="content-area-body" style="padding: 1.5rem;">
    <!-- Statistics Cards -->
    <!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <a href="{{ route('shift.index') }}" class="stat-card" style="cursor: pointer; text-decoration: none; color: inherit;">
            <div class="stat-icon">
                <i class="feather-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $totalShifts ?? 0 }}</div>
                <div class="stat-label">Total Shifts</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('shift.index', ['status' => 1]) }}" class="stat-card" style="cursor: pointer; text-decoration: none; color: inherit;">
            <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
                <i class="feather-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $activeShifts ?? 0 }}</div>
                <div class="stat-label">Active Shifts</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('shift.index', ['status' => 0]) }}" class="stat-card" style="cursor: pointer; text-decoration: none; color: inherit;">
            <div class="stat-icon" style="background: var(--danger-light); color: var(--danger);">
                <i class="feather-x-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $inactiveShifts ?? 0 }}</div>
                <div class="stat-label">Inactive Shifts</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info-light); color: var(--info);">
                <i class="feather-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">{{ $assignedShiftsCount ?? 0 }}</div>
                <div class="stat-label">Assigned This Month</div>
            </div>
        </div>
    </div>
</div>

    <!-- Tabs -->
    <ul class="nav nav-tabs-custom" id="shiftTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="shifts-tab" data-bs-toggle="tab" data-bs-target="#shifts" type="button" role="tab">Shifts List</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned" type="button" role="tab">Assigned Shifts</button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="shiftTabsContent">
        <!-- Shifts List Tab -->
        <div class="tab-pane fade show active" id="shifts" role="tabpanel">
           
            <!-- Filters -->
<div class="filter-card">
    <form id="filterForm" class="row g-2" method="GET" action="{{ route('shift.index') }}">
        @csrf
        <div class="col-md-3">
            <div class="filter-label">Search</div>
            <input type="text" class="form-control" name="search" placeholder="Search by name..." 
                   value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <div class="filter-label">Status</div>
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <div class="filter-label">From Date</div>
            <input type="date" class="form-control" name="from_date" value="{{ request('from_date') }}">
        </div>
        <div class="col-md-2">
            <div class="filter-label">To Date</div>
            <input type="date" class="form-control" name="to_date" value="{{ request('to_date') }}">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-action me-2">
                <i class="feather-filter"></i> Filter
            </button>
            <a href="{{ route('shift.index') }}" class="btn btn-action">
                <i class="feather-refresh-ccw"></i> Reset
            </a>
        </div>
    </form>
</div>

            <!-- Shifts Grid with Fixed Footer -->
            <div class="row">
                @forelse($shifts as $shift)
                <div class="col-md-4 mb-3">
                    <div class="shift-card">
                        <div class="shift-color-indicator" style="background: {{ $shift->color_code ?? 'linear-gradient(90deg, #4b7bec, #a55eea)' }}"></div>
                        <div class="shift-card-body">
                            <div class="shift-header">
                                <h5 class="shift-title">{{ $shift->name }}</h5>
                                <div class="dropdown">
                                    <a href="#" class="text-muted" data-bs-toggle="dropdown" style="color: var(--gray-400);">
                                        <i class="feather-more-vertical"></i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item edit-shift" href="#" 
                                               data-id="{{ $shift->id }}"
                                               data-name="{{ $shift->name }}"
                                               data-start-time="{{ $shift->start_time }}"
                                               data-end-time="{{ $shift->end_time }}"
                                               data-total-hours="{{ $shift->total_hours }}"
                                               data-description="{{ $shift->description }}"
                                               data-grace-minutes="{{ $shift->grace_minutes }}"
                                               data-color-code="{{ $shift->color_code }}"
                                               data-break-time="{{ $shift->break_time }}"
                                               data-status="{{ $shift->status }}">
                                                <i class="feather-edit-3 me-2"></i>Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item change-status" href="#" data-id="{{ $shift->id }}">
                                                <i class="feather-toggle-{{ $shift->status == 1 ? 'right' : 'left' }} me-2"></i>
                                                {{ $shift->status == 1 ? 'Deactivate' : 'Activate' }}
                                            </a>
                                        </li>
                                        <!--<li><hr class="dropdown-divider"></li>-->
                                        <!--<li>-->
                                        <!--    <a class="dropdown-item text-danger delete-shift" href="#" data-id="{{ $shift->id }}">-->
                                        <!--        <i class="feather-trash-2 me-2"></i>Delete-->
                                        <!--    </a>-->
                                        <!--</li>-->
                                    </ul>
                                </div>
                            </div>

                            <div class="shift-meta">
                                <span class="shift-time">
                                    <i class="feather-clock"></i>
                                    {{ \Carbon\Carbon::parse($shift->start_time)->format('h:i A') }}
                                </span>
                                <span class="shift-time">
                                    <i class="feather-clock"></i>
                                    {{ \Carbon\Carbon::parse($shift->end_time)->format('h:i A') }}
                                </span>
                            </div>

                            <div class="shift-detail">
                                <i class="feather-hourglass"></i>
                                <span>{{ $shift->total_hours }} hours</span>
                            </div>

                            @if($shift->grace_minutes > 0)
                            <div class="shift-detail">
                                <i class="feather-alert-circle"></i>
                                <span>{{ $shift->grace_minutes }} mins grace time</span>
                            </div>
                            @endif

                            @if($shift->break_time > 0)
                            <div class="shift-detail">
                                <i class="feather-coffee"></i>
                                <span>{{ $shift->break_time }} mins break</span>
                            </div>
                            @endif

                            @if($shift->description)
                            <div class="mt-3 pt-2 border-top" style="border-color: var(--gray-100) !important;">
                                <small class="text-muted-light">{{ Str::limit($shift->description, 60) }}</small>
                            </div>
                            @endif
                        </div>
                        
                        <!-- Fixed Footer -->
                        <div class="shift-card-footer">
                            <span class="badge-status {{ $shift->status == 1 ? 'badge-active' : 'badge-inactive' }}">
                                <i class="feather-{{ $shift->status == 1 ? 'check-circle' : 'x-circle' }}"></i>
                                {{ $shift->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                            <small class="text-muted-light">
                                <i class="feather-calendar"></i>
                                {{ $shift->created_at ? $shift->created_at->format('d M Y') : 'N/A' }}
                            </small>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12">
                    <div class="text-center py-5">
                        <div style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;">
                            <i class="feather-clock"></i>
                        </div>
                        <h5 style="color: var(--gray-700); margin-bottom: 0.5rem;">No Shifts Found</h5>
                        <p style="color: var(--gray-500); margin-bottom: 1.5rem;">Click the "Add Shift" button to create your first shift.</p>
                        <button class="btn btn-action btn-action-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                            <i class="feather-plus me-1"></i>Add Shift
                        </button>
                    </div>
                </div>
                @endforelse
            </div>
            
            <!-- Pagination -->
            @if(isset($shifts) && method_exists($shifts, 'links'))
            <div class="pagination-custom">
                {{ $shifts->links() }}
            </div>
            @endif
        </div>

        <!-- Assigned Shifts Tab -->
       <!-- Assigned Shifts Tab -->
<div class="tab-pane fade" id="assigned" role="tabpanel">
    <!-- Advanced Filters for Assigned Shifts -->
    <div class="filter-card mb-3">
        <form id="assignedFilterForm" class="row g-2">
            <div class="col-md-3">
                <label class="filter-label">
                    <i class="feather-user"></i> Employee
                </label>
                <select class="form-select" name="filter_user_id" id="filter_user_id">
                    <option value="">All Employees</option>
                    @foreach($users ?? [] as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->employee_id ?? 'N/A' }})</option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">
                    <i class="feather-calendar"></i> From Date
                </label>
                <input type="date" class="form-control" name="filter_from_date" id="filter_from_date" 
                       value="{{ date('Y-m-01') }}">
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">
                    <i class="feather-calendar"></i> To Date
                </label>
                <input type="date" class="form-control" name="filter_to_date" id="filter_to_date" 
                       value="{{ date('Y-m-t') }}">
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">
                    <i class="feather-clock"></i> Shift
                </label>
                <select class="form-select" name="filter_shift_id" id="filter_shift_id">
                    <option value="">All Shifts</option>
                    @foreach($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">
                    <i class="feather-activity"></i> Status
                </label>
                <select class="form-select" name="filter_status" id="filter_status">
                    <option value="">All Status</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="complete">Completed</option>
                    <option value="missed">Missed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-action me-1" title="Apply Filters">
                    <i class="feather-filter"></i>
                </button>
                <button type="button" class="btn btn-action" id="resetAssignedFilters" title="Reset Filters">
                    <i class="feather-refresh-ccw"></i>
                </button>
            </div>
        </form>
        
        <!-- Quick Date Filters -->
        <div class="mt-2 d-flex gap-2">
            <span class="text-muted-light" style="font-size: 0.75rem;">Quick:</span>
            <button type="button" class="quick-filter-btn" data-range="today">Today</button>
            <button type="button" class="quick-filter-btn" data-range="tomorrow">Tomorrow</button>
            <button type="button" class="quick-filter-btn" data-range="this-week">This Week</button>
            <button type="button" class="quick-filter-btn" data-range="next-week">Next Week</button>
            <button type="button" class="quick-filter-btn" data-range="this-month">This Month</button>
            <button type="button" class="quick-filter-btn" data-range="next-month">Next Month</button>
        </div>
    </div>
    
    <!-- Summary Cards for Assigned Shifts -->
    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem;">
                        <i class="feather-users"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="totalAssignedCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Total</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem; background: var(--info-light); color: var(--info);">
                        <i class="feather-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="upcomingCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Upcoming</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem; background: var(--warning-light); color: var(--warning);">
                        <i class="feather-activity"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="ongoingCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Ongoing</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem; background: var(--success-light); color: var(--success);">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="completedCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Completed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem; background: var(--danger-light); color: var(--danger);">
                        <i class="feather-x-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="missedCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Missed</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="stat-card p-2">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 1rem; background: var(--gray-200); color: var(--gray-600);">
                        <i class="feather-slash"></i>
                    </div>
                    <div>
                        <div class="stat-value" style="font-size: 1.2rem;" id="cancelledCount">0</div>
                        <div class="stat-label" style="font-size: 0.65rem;">Cancelled</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions for Assigned Shifts -->
    <div class="bulk-actions mb-3" id="assignedBulkActions" style="display: none;">
        <div class="d-flex align-items-center gap-2">
            <span><i class="feather-check-square"></i> <span id="selectedAssignedCount">0</span> shifts selected</span>
            
            <select class="form-select" id="bulkAssignedShift" style="width: 150px;">
                <option value="">Change Shift</option>
                @foreach($shifts as $shift)
                <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                @endforeach
            </select>
            
            <select class="form-select" id="bulkAssignedStatus" style="width: 150px;">
                <option value="">Change Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="complete">Completed</option>
                <option value="missed">Missed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            
            <button class="btn btn-action btn-action-primary" id="applyBulkAssignedUpdate">
                <i class="feather-check"></i> Apply
            </button>
            
            <button class="btn btn-action" id="clearAssignedSelection">
                <i class="feather-x"></i> Clear
            </button>
            
            <button class="btn btn-action" id="exportAssignedBtn">
                <i class="feather-download"></i> Export
            </button>
        </div>
    </div>
    
    <!-- Assigned Shifts Table -->
    <div class="table-container">
        <div class="p-2 bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0" style="color: var(--gray-700);">
                <i class="feather-users me-2"></i>Assigned Shifts
            </h6>
            <div class="d-flex gap-2">
                <select class="form-select" id="assignedPerPage" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-muted-light" id="showingInfo">Loading...</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table" id="assignedShiftsTable">
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="selectAllAssigned">
                        </th>
                        <th>Employee</th>
                        <th>Shift</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Assigned By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assignedTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="text-muted">Loading shifts...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="p-3 d-flex justify-content-between align-items-center">
            <div class="text-muted-light" id="paginationInfo"></div>
            <div id="assignedPagination" class="pagination-custom"></div>
        </div>
    </div>
</div>
    </div>
</div>
@endsection

@section('create-modal')
<!-- Add Shift Modal -->
<div class="modal fade modal-custom" id="addShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather-plus-circle me-2"></i>Add New Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="addFormError" class="alert alert-danger d-none"></div>
                
                <form action="{{ route('shift.store') }}" method="POST" id="addShiftForm">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Shift Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required placeholder="Morning">
                                <small class="text-danger error-text name_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" name="color_code" value="#4b7bec">
                                <small class="text-danger error-text color_code_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" required>
                                <small class="text-danger error-text start_time_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" required>
                                <small class="text-danger error-text end_time_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Total Hours <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" min="0.5" max="24" class="form-control" name="total_hours" required placeholder="8">
                                <small class="text-danger error-text total_hours_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Grace (mins)</label>
                                <input type="number" min="0" max="120" class="form-control" name="grace_minutes" value="0" placeholder="15">
                                <small class="text-danger error-text grace_minutes_error"></small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label">Break (mins)</label>
                                <input type="number" min="0" max="180" class="form-control" name="break_time" value="0" placeholder="30">
                                <small class="text-danger error-text break_time_error"></small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Optional..."></textarea>
                                <small class="text-danger error-text description_error"></small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addShiftForm" class="btn btn-action btn-action-primary">
                    <i class="feather-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Shift Modal -->
<div class="modal fade modal-custom" id="editShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather-edit-3 me-2"></i>Edit Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editFormError" class="alert alert-danger d-none"></div>
                
                <form id="editShiftForm">
                    @csrf
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Shift Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="edit_name" required>
                                <small class="text-danger error-text edit_name_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" name="color_code" id="edit_color_code">
                                <small class="text-danger error-text edit_color_code_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="edit_start_time" required>
                                <small class="text-danger error-text edit_start_time_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" id="edit_end_time" required>
                                <small class="text-danger error-text edit_end_time_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Total Hours <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" min="0.5" max="24" class="form-control" name="total_hours" id="edit_total_hours" required>
                                <small class="text-danger error-text edit_total_hours_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Grace (mins)</label>
                                <input type="number" min="0" max="120" class="form-control" name="grace_minutes" id="edit_grace_minutes">
                                <small class="text-danger error-text edit_grace_minutes_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Break (mins)</label>
                                <input type="number" min="0" max="180" class="form-control" name="break_time" id="edit_break_time">
                                <small class="text-danger error-text edit_break_time_error"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <small class="text-danger error-text edit_status_error"></small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                                <small class="text-danger error-text edit_description_error"></small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editShiftForm" class="btn btn-action btn-action-primary">
                    <i class="feather-save"></i> Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Shift Modal -->
<div class="modal fade modal-custom" id="assignShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather-users me-2"></i>Assign Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="assignFormError" class="alert alert-danger d-none"></div>
                
                <form action="{{ route('shift.assign') }}" method="POST" id="assignShiftForm">
                    @csrf
                    
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">Assignment Type <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="assign_type" id="assignTypeUser" value="user" checked>
                                    <label class="form-check-label" for="assignTypeUser">Users</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="assign_type" id="assignTypeDepartment" value="department">
                                    <label class="form-check-label" for="assignTypeDepartment">Department</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="assign_type" id="assignTypeAll" value="all">
                                    <label class="form-check-label" for="assignTypeAll">All</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12" id="departmentSection" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-control" name="department_id" id="department_id">
                                    <option value="">Select Department</option>
                                    @foreach($departments ?? [] as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12" id="userSection">
                            <div class="form-group">
                                <label class="form-label">Users <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="user_ids[]" id="user_ids" multiple>
                                    @foreach($users ?? [] as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-">
                            <div class="form-group">
                                <label class="form-label">Shift <span class="text-danger">*</span></label>
                                <select class="form-control" name="shift_id" id="shift_id" required>
                                    <option value="">Select Shift</option>
                                    @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Week Off</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="week_off_type" id="weekOffNone" value="" checked>
                                    <label class="form-check-label" for="weekOffNone">None</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="week_off_type" id="weekOffDayBased" value="day_based">
                                    <label class="form-check-label" for="weekOffDayBased">Days</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="week_off_type" id="weekOffDateBased" value="date_based">
                                    <label class="form-check-label" for="weekOffDateBased">Dates</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="dayBasedSection" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Select Days</label>
                                <div class="row">
                                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day)
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="week_off_days[]" value="{{ $day }}" id="day_{{ $day }}">
                                            <label class="form-check-label" for="day_{{ $day }}">{{ $day }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="dateBasedSection" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Select Dates</label>
                                <div id="dateBasedContainer">
                                    <div class="input-group mb-2">
                                        <input type="date" class="form-control" name="week_off_dates[]">
                                        <button class="btn btn-outline-secondary add-date" type="button">
                                            <i class="feather-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="override_existing" id="override_existing" value="1">
                                <label class="form-check-label" for="override_existing">Override existing shifts</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="apply_to_future_only" id="apply_to_future_only" value="1">
                                <label class="form-check-label" for="apply_to_future_only">Apply to future dates only</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="assignShiftForm" class="btn btn-action btn-action-primary">
                    <i class="feather-save"></i> Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Assigned Shift Modal -->
<div class="modal fade modal-custom" id="editAssignedShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather-edit-3 me-2"></i>Edit Assigned Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editAssignedShiftForm">
                    @csrf
                    <input type="hidden" name="user_shift_id" id="edit_assigned_id">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Shift</label>
                        <select class="form-control" name="shift_id" id="edit_assigned_shift_id" required>
                            @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="edit_assigned_status">
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="complete">Completed</option>
                            <option value="missed">Missed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="Optional..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editAssignedShiftForm" class="btn btn-action btn-action-primary">
                    Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-custom" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather-alert-circle me-2" style="color: var(--danger);"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-3">
                <div style="font-size: 2.5rem; color: var(--danger); margin-bottom: 0.5rem;">
                    <i class="feather-trash-2"></i>
                </div>
                <h6 style="color: var(--gray-700);">Are you sure?</h6>
                <p style="color: var(--gray-500);">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-action btn-action-danger" id="confirmDelete">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-area')
<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    $(document).ready(function() {
        
        // ============== ENHANCED ASSIGNED SHIFTS MANAGEMENT ==============
        let assignedCurrentPage = 1;
        let assignedPerPage = 25;
        let assignedSelectedIds = [];
        let assignedTotalPages = 1;

        // Format date helper
        function formatDate(date) {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function formatDisplayDate(date) {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function formatTime(time) {
            if (!time) return 'N/A';
            const [hours, minutes] = time.split(':');
            const d = new Date();
            d.setHours(hours, minutes);
            return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function ucfirst(str) {
            return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
        }

        // Load assigned shifts with filters
        function loadAssignedShifts() {
            // Get date values
            let fromDate = $('#filter_from_date').val();
            let toDate = $('#filter_to_date').val();
            
            // If dates are empty, set default values (current month)
            if (!fromDate) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                fromDate = formatDate(firstDay);
                $('#filter_from_date').val(fromDate);
            }
            
            if (!toDate) {
                const today = new Date();
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                toDate = formatDate(lastDay);
                $('#filter_to_date').val(toDate);
            }
            
            const params = {
                user_id: $('#filter_user_id').val() || '',
                from_date: fromDate,
                to_date: toDate,
                shift_id: $('#filter_shift_id').val() || '',
                status: $('#filter_status').val() || '',
                page: assignedCurrentPage,
                per_page: assignedPerPage
            };

            // Show loading
            $('#assignedTableBody').html('<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" style="width: 2rem; height: 2rem;"></div></td></tr>');

            $.ajax({
                url: "{{ route('shift.user-shifts.data') }}",
                type: "GET",
                data: params,
                success: function(response) {
                    if (response.status) {
                        renderAssignedTable(response.data);
                        renderAssignedPagination(response.pagination);
                        updateAssignedSummary(response.data);
                        
                        $('#showingInfo').text(`Showing ${response.pagination.from || 0} - ${response.pagination.to || 0} of ${response.pagination.total} shifts`);
                    } else {
                        $('#assignedTableBody').html('<tr><td colspan="8" class="text-center py-4 text-danger">Error loading data</td></tr>');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX Error:', xhr);
                    let errorMessage = 'Failed to load data';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMessage = Object.values(xhr.responseJSON.errors).join(', ');
                    }
                    $('#assignedTableBody').html(`<tr><td colspan="8" class="text-center py-4 text-danger">${errorMessage}</td></tr>`);
                }
            });
        }

        // Render assigned shifts table
        function renderAssignedTable(data) {
            if (!data || data.length === 0) {
                $('#assignedTableBody').html('<tr><td colspan="8" class="text-center py-4">No shifts found</td></tr>');
                return;
            }

            let html = '';
            data.forEach(item => {
                const shiftColor = item.shift?.color_code || 'var(--primary)';
                const statusClass = getAssignedStatusClass(item.status);
                const isChecked = assignedSelectedIds.includes(item.id.toString()) ? 'checked' : '';
                
                html += `
                    <tr>
                        <td>
                            <input type="checkbox" class="assigned-select" value="${item.id}" ${isChecked}>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="stat-icon me-2" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                    <i class="feather-user"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 500;">${item.user?.name || 'N/A'}</div>
                                    <small class="text-muted-light">${item.user?.employee_id || ''}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color: ${shiftColor}; font-weight: 500;">
                                ${item.shift?.name || 'N/A'}
                            </span>
                        </td>
                        <td>${formatDisplayDate(item.date)}</td>
                        <td>
                            <span class="shift-time" style="font-size: 0.75rem;">
                                <i class="feather-clock"></i>
                                ${item.shift?.start_time ? formatTime(item.shift.start_time) : 'N/A'} - 
                                ${item.shift?.end_time ? formatTime(item.shift.end_time) : 'N/A'}
                            </span>
                        </td>
                        <td>
                            <span class="badge-status ${statusClass}">
                                ${ucfirst(item.status)}
                            </span>
                        </td>
                        <td>
                            <small class="text-muted-light">
                                ${item.created_by ? 'Admin' : 'System'}
                            </small>
                        </td>
                        <td>
                            <button class="btn-action edit-assigned-shift" 
                                    data-id="${item.id}"
                                    data-shift-id="${item.shift_id}"
                                    data-user-id="${item.user_id}"
                                    data-user-name="${item.user?.name || ''}"
                                    data-date="${formatDisplayDate(item.date)}"
                                    data-status="${item.status}">
                                <i class="feather-edit-3"></i>
                            </button>
                            <button class="btn-action text-danger delete-assigned-shift" 
                                    data-id="${item.id}">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            $('#assignedTableBody').html(html);
        }

        // Get status class for assigned shifts
        function getAssignedStatusClass(status) {
            const classes = {
                'upcoming': 'badge-upcoming',
                'ongoing': 'badge-ongoing',
                'completed': 'badge-completed',
                'missed': 'badge-missed',
                'cancelled': 'badge-inactive'
            };
            return classes[status] || '';
        }

        // Update summary cards
        function updateAssignedSummary(data) {
            const counts = {
                total: data.length,
                upcoming: data.filter(d => d.status === 'upcoming').length,
                ongoing: data.filter(d => d.status === 'ongoing').length,
                completed: data.filter(d => d.status === 'complete').length,
                missed: data.filter(d => d.status === 'missed').length,
                cancelled: data.filter(d => d.status === 'cancelled').length
            };
            
            $('#totalAssignedCount').text(counts.total);
            $('#upcomingCount').text(counts.upcoming);
            $('#ongoingCount').text(counts.ongoing);
            $('#completedCount').text(counts.completed);
            $('#missedCount').text(counts.missed);
            $('#cancelledCount').text(counts.cancelled);
        }

        // Render pagination
        function renderAssignedPagination(pagination) {
            assignedTotalPages = pagination.last_page;
            assignedCurrentPage = pagination.current_page;
            
            let html = '<ul class="pagination">';
            
            // Previous button
            html += `<li class="page-item ${assignedCurrentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${assignedCurrentPage - 1}">Previous</a>
            </li>`;
            
            // Page numbers
            for (let i = 1; i <= assignedTotalPages; i++) {
                if (i === 1 || i === assignedTotalPages || (i >= assignedCurrentPage - 2 && i <= assignedCurrentPage + 2)) {
                    html += `<li class="page-item ${i === assignedCurrentPage ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>`;
                } else if (i === assignedCurrentPage - 3 || i === assignedCurrentPage + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Next button
            html += `<li class="page-item ${assignedCurrentPage === assignedTotalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${assignedCurrentPage + 1}">Next</a>
            </li>`;
            
            html += '</ul>';
            
            $('#assignedPagination').html(html);
        }

        // Quick date filters
        $('.quick-filter-btn').on('click', function() {
            const range = $(this).data('range');
            const today = new Date();
            let startDate, endDate;
            
            switch(range) {
                case 'today':
                    startDate = new Date();
                    endDate = new Date();
                    break;
                case 'tomorrow':
                    startDate = new Date();
                    startDate.setDate(startDate.getDate() + 1);
                    endDate = new Date(startDate);
                    break;
                case 'this-week':
                    // Get Monday of current week
                    const firstDay = new Date(today);
                    const day = today.getDay(); // 0 = Sunday, 1 = Monday, etc.
                    const diff = day === 0 ? 6 : day - 1; // Adjust to get Monday
                    firstDay.setDate(today.getDate() - diff);
                    startDate = new Date(firstDay);
                    endDate = new Date(firstDay);
                    endDate.setDate(firstDay.getDate() + 6);
                    break;
                case 'next-week':
                    const nextWeekStart = new Date(today);
                    const currentDay = today.getDay();
                    const daysToAdd = currentDay === 0 ? 1 : 8 - currentDay;
                    nextWeekStart.setDate(today.getDate() + daysToAdd);
                    startDate = new Date(nextWeekStart);
                    endDate = new Date(nextWeekStart);
                    endDate.setDate(nextWeekStart.getDate() + 6);
                    break;
                case 'this-month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'next-month':
                    startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
                    break;
            }
            
            $('#filter_from_date').val(formatDate(startDate));
            $('#filter_to_date').val(formatDate(endDate));
            assignedCurrentPage = 1;
            loadAssignedShifts();
        });

        // Apply filters
        $('#assignedFilterForm').on('submit', function(e) {
            e.preventDefault();
            
            // Validate dates
            const fromDate = $('#filter_from_date').val();
            const toDate = $('#filter_to_date').val();
            
            if (!fromDate || !toDate) {
                toastr.error('Please select both from and to dates');
                return;
            }
            
            assignedCurrentPage = 1;
            loadAssignedShifts();
        });

        // Reset filters
        $('#resetAssignedFilters').on('click', function() {
            $('#filter_user_id').val('');
            $('#filter_shift_id').val('');
            $('#filter_status').val('');
            
            // Reset to current month
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            $('#filter_from_date').val(formatDate(firstDay));
            $('#filter_to_date').val(formatDate(lastDay));
            
            assignedCurrentPage = 1;
            loadAssignedShifts();
        });

        // Per page change
        $('#assignedPerPage').on('change', function() {
            assignedPerPage = $(this).val();
            assignedCurrentPage = 1;
            loadAssignedShifts();
        });

        // Select all
        $('#selectAllAssigned').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.assigned-select').prop('checked', isChecked);
            
            if (isChecked) {
                assignedSelectedIds = [];
                $('.assigned-select').each(function() {
                    assignedSelectedIds.push($(this).val());
                });
            } else {
                assignedSelectedIds = [];
            }
            
            updateAssignedBulkActions();
        });

        // Individual select
        $(document).on('change', '.assigned-select', function() {
            const id = $(this).val();
            if ($(this).prop('checked')) {
                assignedSelectedIds.push(id);
            } else {
                assignedSelectedIds = assignedSelectedIds.filter(item => item != id);
            }
            $('#selectAllAssigned').prop('checked', $('.assigned-select:checked').length === $('.assigned-select').length);
            updateAssignedBulkActions();
        });

        // Update bulk actions visibility
        function updateAssignedBulkActions() {
            if (assignedSelectedIds.length > 0) {
                $('#assignedBulkActions').show();
                $('#selectedAssignedCount').text(assignedSelectedIds.length);
            } else {
                $('#assignedBulkActions').hide();
            }
        }

        // Clear selection
        $('#clearAssignedSelection').on('click', function() {
            assignedSelectedIds = [];
            $('.assigned-select').prop('checked', false);
            $('#selectAllAssigned').prop('checked', false);
            updateAssignedBulkActions();
        });

        // Apply bulk update
        $('#applyBulkAssignedUpdate').on('click', function() {
            const shiftId = $('#bulkAssignedShift').val();
            const status = $('#bulkAssignedStatus').val();
            
            if (!shiftId && !status) {
                toastr.error('Please select shift or status to update');
                return;
            }
            
            if (assignedSelectedIds.length === 0) {
                toastr.error('No shifts selected');
                return;
            }
            
            const updates = assignedSelectedIds.map(id => ({
                id: id,
                shift_id: shiftId || null,
                status: status || null
            }));
            
            $.ajax({
                url: "{{ route('shift.user-shifts.bulk-update') }}",
                type: "POST",
                data: {
                    _token: '{{ csrf_token() }}',
                    user_shifts: updates
                },
                success: function(response) {
                    if (response.status) {
                        toastr.success(response.message);
                        $('#clearAssignedSelection').click();
                        loadAssignedShifts();
                    }
                },
                error: function() {
                    toastr.error('Failed to update shifts');
                }
            });
        });

        // Delete single assigned shift
        $(document).on('click', '.delete-assigned-shift', function() {
            const id = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this shift assignment?')) {
                $.ajax({
                    url: "{{ route('shift.destroy-assigned', '') }}/" + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.status) {
                            toastr.success('Shift deleted successfully');
                            loadAssignedShifts();
                        }
                    },
                    error: function() {
                        toastr.error('Failed to delete shift');
                    }
                });
            }
        });

        // Export assigned shifts
        $('#exportAssignedBtn').on('click', function() {
            const params = {
                user_id: $('#filter_user_id').val(),
                from_date: $('#filter_from_date').val(),
                to_date: $('#filter_to_date').val(),
                shift_id: $('#filter_shift_id').val(),
                status: $('#filter_status').val()
            };
            
            const queryString = $.param(params);
            window.location.href = "{{ route('shift.user-shifts.export') }}?" + queryString;
        });

        // Pagination click handler
        $(document).on('click', '#assignedPagination .page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && page !== assignedCurrentPage) {
                assignedCurrentPage = page;
                loadAssignedShifts();
            }
        });

        // Load assigned shifts when tab is shown
        $('#assigned-tab').on('shown.bs.tab', function() {
            // Ensure dates are set
            const fromDate = $('#filter_from_date').val();
            const toDate = $('#filter_to_date').val();
            
            if (!fromDate || !toDate) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                $('#filter_from_date').val(formatDate(firstDay));
                $('#filter_to_date').val(formatDate(lastDay));
            }
            
            loadAssignedShifts();
        });

        // Rest of your existing code (deleteId, toastr, select2, etc.)
        let deleteId = null;

        // Toastr configuration
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        // Display session messages
        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        @if(session('error'))
            toastr.error('{{ session('error') }}');
        @endif

        // Initialize Select2
        $('.select2').select2({
            placeholder: 'Select users',
            allowClear: true,
            dropdownParent: $('#assignShiftModal'),
            width: '100%'
        });

        // Assignment Type Change
        $('input[name="assign_type"]').on('change', function() {
            const type = $(this).val();
            
            if (type === 'department') {
                $('#departmentSection').slideDown();
                $('#userSection').slideUp();
                $('#user_ids').prop('required', false);
                $('#department_id').prop('required', true);
            } else if (type === 'user') {
                $('#departmentSection').slideUp();
                $('#userSection').slideDown();
                $('#user_ids').prop('required', true);
                $('#department_id').prop('required', false);
            } else {
                $('#departmentSection').slideUp();
                $('#userSection').slideUp();
                $('#user_ids').prop('required', false);
                $('#department_id').prop('required', false);
            }
        });

        // Week Off Type Change
        $('input[name="week_off_type"]').on('change', function() {
            const type = $(this).val();
            
            $('#dayBasedSection, #dateBasedSection').slideUp();
            
            if (type === 'day_based') {
                $('#dayBasedSection').slideDown();
            } else if (type === 'date_based') {
                $('#dateBasedSection').slideDown();
            }
        });

        // Add Date Field
        $(document).on('click', '.add-date', function() {
            const html = `
                <div class="input-group mb-2">
                    <input type="date" class="form-control" name="week_off_dates[]">
                    <button class="btn btn-outline-danger remove-date" type="button">
                        <i class="feather-minus"></i>
                    </button>
                </div>
            `;
            $('#dateBasedContainer').append(html);
        });

        // Remove Date Field
        $(document).on('click', '.remove-date', function() {
            $(this).closest('.input-group').remove();
        });

        // Filter Form Submit
        $('#filterForm').on('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    var formData = $(this).serialize();
    
    // Redirect with query parameters
    window.location.href = "{{ route('shift.index') }}?" + formData;
});

        // Reset Filter
        $('#filterForm button[type="reset"]').on('click', function(e) {
    e.preventDefault();
    window.location.href = "{{ route('shift.index') }}";
});

        // Edit Shift
        $(document).on('click', '.edit-shift', function(e) {
            e.preventDefault();
            const data = $(this).data();
            
            $('#edit_id').val(data.id);
            $('#edit_name').val(data.name);
            $('#edit_start_time').val(data.startTime);
            $('#edit_end_time').val(data.endTime);
            $('#edit_total_hours').val(data.totalHours);
            $('#edit_description').val(data.description);
            $('#edit_grace_minutes').val(data.graceMinutes);
            $('#edit_color_code').val(data.colorCode);
            $('#edit_break_time').val(data.breakTime);
            $('#edit_status').val(data.status);
            
            $('#editShiftModal').modal('show');
        });

        // Edit Shift Form Submit
        $('#editShiftForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#edit_id').val();
            
            var formData = $(this).serializeArray();
            
            $.each(formData, function(i, field) {
                if (field.name === 'start_time' || field.name === 'end_time') {
                    if (field.value && field.value.includes(':')) {
                        var timeParts = field.value.split(':');
                        if (timeParts.length >= 2) {
                            field.value = timeParts[0] + ':' + timeParts[1];
                        }
                    }
                }
            });
            
            var serializedData = $.param(formData);
            
            $.ajax({
                url: "{{ route('shift.update', '') }}/" + id,
                type: "POST",
                data: serializedData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status) {
                        $('#editShiftModal').modal('hide');
                        toastr.success('Shift updated successfully');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            $('.edit_' + key + '_error').text(value[0]);
                        });
                        toastr.error('Please fix validation errors');
                    } else {
                        toastr.error('Failed to update shift');
                    }
                }
            });
        });

        // Add Shift Form Submit
        $('#addShiftForm').on('submit', function(e) {
            e.preventDefault();
            
            // if (!$('#total_hours').val() || $('#total_hours').val() <= 0) {
            //     toastr.error('Please set valid start and end times');
            //     return;
            // }
            
            var formData = $(this).serializeArray();
            
            $.each(formData, function(i, field) {
                if (field.name === 'start_time' || field.name === 'end_time') {
                    if (field.value && field.value.includes(':')) {
                        var timeParts = field.value.split(':');
                        if (timeParts.length >= 2) {
                            field.value = timeParts[0] + ':' + timeParts[1];
                        }
                    }
                }
            });
            
            var serializedData = $.param(formData);
            
            $.ajax({
                url: $(this).attr('action'),
                type: "POST",
                data: serializedData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status) {
                        $('#addShiftModal').modal('hide');
                        toastr.success('Shift created successfully');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            $('.' + key + '_error').text(value[0]);
                        });
                        toastr.error('Please fix validation errors');
                    } else {
                        toastr.error('Failed to create shift');
                    }
                }
            });
        });

        // Assign Shift Form Submit
        $('#assignShiftForm').on('submit', function(e) {
            e.preventDefault();
            
            $('.error-text').text('');
            $('#assignFormError').addClass('d-none').empty();
            
            let formData = $(this).serializeArray();
            
            const assignType = $('input[name="assign_type"]:checked').val();
            const weekOffType = $('input[name="week_off_type"]:checked').val();
            
            if (weekOffType === 'day_based') {
                formData = formData.filter(field => !field.name.startsWith('week_off_dates'));
                
                const selectedDays = formData.filter(field => field.name === 'week_off_days[]');
                if (selectedDays.length === 0) {
                    toastr.error('Please select at least one week off day');
                    $('.week_off_days_error').text('At least one day is required');
                    return;
                }
            } 
            else if (weekOffType === 'date_based') {
                formData = formData.filter(field => field.name !== 'week_off_days[]');
                
                const selectedDates = formData.filter(field => field.name === 'week_off_dates[]' && field.value);
                if (selectedDates.length === 0) {
                    toastr.error('Please select at least one week off date');
                    $('.week_off_dates_error').text('At least one date is required');
                    return;
                }
            }
            else {
                formData = formData.filter(field => 
                    field.name !== 'week_off_days[]' && 
                    !field.name.startsWith('week_off_dates')
                );
            }
            
            if (assignType === 'all') {
                formData = formData.filter(field => 
                    field.name !== 'department_id' && 
                    field.name !== 'user_ids[]'
                );
            } else if (assignType === 'department') {
                formData = formData.filter(field => field.name !== 'user_ids[]');
                
                const deptId = $('#department_id').val();
                if (!deptId) {
                    toastr.error('Please select a department');
                    $('.department_id_error').text('Department is required');
                    return;
                }
            } else if (assignType === 'user') {
                formData = formData.filter(field => field.name !== 'department_id');
                
                const userIds = $('#user_ids').val();
                if (!userIds || userIds.length === 0) {
                    toastr.error('Please select at least one user');
                    $('.user_ids_error').text('Users are required');
                    return;
                }
            }
            
            const serializedData = $.param(formData);
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="feather-loader"></i> Assigning...').prop('disabled', true);
            
            $.ajax({
                url: $(this).attr('action'),
                type: "POST",
                data: serializedData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status) {
                        $('#assignShiftModal').modal('hide');
                        toastr.success(response.message);
                        setTimeout(() => location.reload(), 1500);
                    }
                },
                error: function(xhr) {
                    submitBtn.html(originalText).prop('disabled', false);
                    
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        
                        if (errors) {
                            $.each(errors, function(key, value) {
                                if (key.includes('.')) {
                                    const baseKey = key.split('.')[0];
                                    $('.' + baseKey + '_error').text(value[0]);
                                } else {
                                    $('.' + key + '_error').text(value[0]);
                                }
                                toastr.error(value[0]);
                            });
                        }
                    } else {
                        $('#assignFormError')
                            .removeClass('d-none')
                            .html(xhr.responseJSON?.message || 'Something went wrong. Please try again.');
                        toastr.error(xhr.responseJSON?.message || 'Assignment failed');
                    }
                }
            });
        });

        // Assignment type change handler
        $('input[name="assign_type"]').on('change', function() {
            const type = $(this).val();
            
            $('#departmentSection').slideUp();
            $('#userSection').slideUp();
            
            $('#user_ids').prop('required', false);
            $('#department_id').prop('required', false);
            
            $('#user_ids').val(null).trigger('change');
            $('#department_id').val('');
            
            if (type === 'department') {
                $('#departmentSection').slideDown();
                $('#department_id').prop('required', true);
            } else if (type === 'user') {
                $('#userSection').slideDown();
                $('#user_ids').prop('required', true);
            }
        });

        // Edit Assigned Shift
        $(document).on('click', '.edit-assigned-shift', function() {
            const data = $(this).data();
            
            $('#edit_assigned_id').val(data.id);
            $('#edit_assigned_shift_id').val(data.shiftId);
            $('#edit_assigned_status').val(data.status);
            
            $('#editAssignedShiftModal').modal('show');
        });

        // Edit Assigned Shift Form Submit
        $('#editAssignedShiftForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: "{{ route('shift.update-user-shift') }}",
                type: "POST",
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.status) {
                        $('#editAssignedShiftModal').modal('hide');
                        toastr.success('Shift updated successfully');
                        setTimeout(() => location.reload(), 1000);
                    }
                },
                error: function() {
                    toastr.error('Failed to update shift');
                }
            });
        });

        // Change Status
        $(document).on('click', '.change-status', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            
            $.post("{{ route('shift.change-status', '') }}/" + id, {
                _token: '{{ csrf_token() }}'
            }, function(response) {
                if (response.status) {
                    toastr.success('Status updated successfully');
                    setTimeout(() => location.reload(), 1000);
                }
            }).fail(function() {
                toastr.error('Failed to update status');
            });
        });

        // Delete Click
        $(document).on('click', '.delete-shift', function(e) {
            e.preventDefault();
            deleteId = $(this).data('id');
            $('#deleteModal').modal('show');
        });

        // Confirm Delete
        $('#confirmDelete').on('click', function() {
            if (deleteId) {
                $.ajax({
                    url: "{{ route('shift.destroy', '') }}/" + deleteId,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.status) {
                            $('#deleteModal').modal('hide');
                            toastr.success(response.message || 'Shift deleted successfully');
                            setTimeout(() => location.reload(), 1000);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Delete failed');
                    }
                });
            }
        });

        // Clear modals on close
        $('#addShiftModal, #editShiftModal, #assignShiftModal').on('hidden.bs.modal', function() {
            $(this).find('form')[0].reset();
            $(this).find('.error-text').text('');
            $(this).find('.alert').addClass('d-none');
            if ($(this).find('.select2').length) {
                $(this).find('.select2').val(null).trigger('change');
            }
        });
    });
</script>
@endsection