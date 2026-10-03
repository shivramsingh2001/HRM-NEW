@extends('client.layout.master')

@section('style')
    <style>





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
            border-color: var(--primary-mid, #0D6EFD);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
            outline: none;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #94a3b8;
        }

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
            border-color: var(--primary-mid, #0D6EFD);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
            outline: none;
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
            text-decoration: none;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
        }

        .filter-tag.clear-all {
            background: var(--primary-light, #EFF6FF);
            border-color: var(--primary-mid, #0D6EFD);
            color: var(--primary-mid, #0D6EFD);
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        .filter-tag.clear-all:hover {
            background: var(--primary-mid, #0D6EFD);
            color: white;
        }

        .filter-tag.clear-all i {
            color: currentColor;
        }

        /* Employee Info Styles */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-mid);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-details {
            flex: 1;
        }

        .employee-name {
            font-weight: 600;
            color: #111827;
            font-size: 14px;
        }

        .employee-name small {
            font-weight: normal;
            color: #6b7280;
            font-size: 11px;
        }

        .employee-id {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Custom Employee Dropdown */
        .custom-employee-dropdown .btn {
            height: 38px;
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

        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: var(--primary-mid);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
        }

        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 8px;
            max-height: 320px;
            overflow-y: auto;
            min-width: 280px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: var(--primary-light);
            color: var(--primary-mid);
        }





        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        /* Action Buttons — icon-only, matches Loan Management's .action-btn pattern */
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
        }


        .btn-approve {
            background: var(--success-light);
            color: var(--success);
        }

        .btn-approve:hover {
            background: #a7f3d0;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: var(--danger-light);
            color: var(--danger);
        }

        .btn-reject:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }

        .btn-view {
            background: var(--primary-light);
            color: var(--primary-mid);
        }

        .btn-view:hover {
            background: #c7d2fe;
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #edf2f7;
            padding: 20px 24px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid #edf2f7;
            padding: 16px 24px;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #475569;
        }

        .info-value {
            flex: 1;
            color: #1e293b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        /* Responsive — matches Tasks "Assigned By Me" page */
        @media (max-width: 992px) {


        }

        @media (max-width: 768px) {


            .action-btns {
                flex-direction: column;
            }
        }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Overtime Management" current="All Overtime Requests">
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                @if (in_array($userRole, ['admin', 'hr']))
                    <a href="{{ route('overtime.settings') }}" class="btn btn-sm btn-light-brand">
                        <i class="feather-settings me-2"></i>
                        <span>Settings</span>
                    </a>
                @endif
                @if (!in_array($userRole, ['admin']))
                <a href="{{ route('overtime.index') }}" class="btn btn-sm btn-primary">
                    <i class="feather-plus me-2"></i>
                    <span>My Requests</span>
                </a>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card" data-status="all">
                <div class="stats-info">
                    <h3>{{ $totalRequests }}</h3>
                    <p>Total Requests</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-file-text"></i>
                </div>
            </div>

            <div class="stats-card" data-status="pending">
                <div class="stats-info">
                    <h3>{{ $pendingRequests }}</h3>
                    <p>Pending</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-clock"></i>
                </div>
            </div>

            <div class="stats-card" data-status="approved">
                <div class="stats-info">
                    <h3>{{ $approvedRequests }}</h3>
                    <p>Approved</p>
                </div>
                <div class="stats-icon">
                    <i class="feather-check-circle"></i>
                </div>
            </div>

            <div class="stats-card" data-status="rejected">
                <div class="stats-info">
                    <h3>{{ $rejectedRequests }}</h3>
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
                    Filter Requests
                    @php
                        $activeFilterCount = collect(
                            request()->only(['status', 'employee', 'from_date', 'to_date'])
                        )->filter()->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['status', 'employee', 'from_date', 'to_date']))
                    <a href="{{ route('overtime.view-all') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('overtime.view-all') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- Status Filter -->
                    <div class="filter-item">
                        <label class="form-label">Status</label>
                        <select name="status" class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    @if (in_array($userRole, ['admin', 'manager', 'hr']))
                        <!-- Employee Filter -->
                        <div class="filter-item" style="min-width: 220px;">
                            <label class="form-label">Employee</label>
                            <div class="custom-employee-dropdown">
                                <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                    type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                        @if (request('employee') && ($selectedEmployee = $employees->firstWhere('id', request('employee'))))
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
                                        <a class="dropdown-item rounded {{ !request('employee') ? 'active' : '' }}"
                                            href="{{ route('overtime.view-all', array_merge(request()->except(['employee', 'page']))) }}">
                                            <span>All Employees</span>
                                        </a>
                                    </li>
                                    @foreach ($employees as $employee)
                                        @php
                                            $initials = strtoupper(substr($employee->name, 0, 2));
                                        @endphp
                                        <li>
                                            <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('employee') == $employee->id ? 'active' : '' }}"
                                                href="{{ route('overtime.view-all', array_merge(request()->except(['page']), ['employee' => $employee->id])) }}">
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
                    @endif

                    <!-- Date From Filter -->
                    <div class="filter-item date-range">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="filter-date" value="{{ request('from_date') }}">
                    </div>

                    <!-- Date To Filter -->
                    <div class="filter-item date-range">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="filter-date" value="{{ request('to_date') }}">
                    </div>

                    <!-- Reset Button -->
                    <div class="filter-item" style="min-width: auto; flex: 0 0 auto;">
                        <a href="{{ route('overtime.view-all') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['status', 'employee', 'from_date', 'to_date']))
                <div class="active-filters">
                    <span class="active-filters-label">Active Filters:</span>

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-activity"></i>
                            Status: {{ ucfirst(request('status')) }}
                            <a href="{{ route('overtime.view-all', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('employee') && $selectedEmployee = $employees->firstWhere('id', request('employee')))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            Employee: {{ $selectedEmployee->name }}
                            <a href="{{ route('overtime.view-all', array_merge(request()->except(['employee', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('from_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            From: {{ \Carbon\Carbon::parse(request('from_date'))->format('d M Y') }}
                            <a href="{{ route('overtime.view-all', array_merge(request()->except(['from_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('to_date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            To: {{ \Carbon\Carbon::parse(request('to_date'))->format('d M Y') }}
                            <a href="{{ route('overtime.view-all', array_merge(request()->except(['to_date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('overtime.view-all') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Requests Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Hours</th>
                                <th>Approved Hrs</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $index => $request)
                                <tr>
                                    <td>{{ $requests->firstItem() + $index }}</td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                {{ strtoupper(substr($request->user_name, 0, 2)) }}
                                            </div>
                                            <div class="employee-details">
                                                <div class="employee-name">{{ $request->user_name }} <small>({{ $request->employee_id ?? 'N/A' }})</small></div>
                                                <div class="employee-id">{{ $request->user_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($request->date)->format('d M Y') }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ number_format($request->overtime_hours, 1) }}</span>
                                        <span class="text-muted">hrs</span>
                                    </td>
                                    <td>
                                        @if ($request->approved_hours)
                                            <span class="text-success fw-semibold">{{ number_format($request->approved_hours, 1) }} hrs</span>
                                        @elseif($request->status == 'approved')
                                            <span class="text-success fw-semibold">{{ number_format($request->overtime_hours, 1) }} hrs</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="reason-cell" style="max-width: 200px;">
                                            {{ Str::limit($request->reason, 50) }}
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($request->created_at)->format('d M Y') }}</td>
                                    <td>
                                        <x-ui.status-badge :status="$request->status" />
                                        @if ($request->status == 'approved' && $request->approved_hours && $request->approved_hours != $request->overtime_hours)
                                            <div class="small text-muted mt-1">
                                                ({{ number_format($request->approved_hours, 1) }} hrs approved)
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn btn-view" onclick="viewRequest({{ $request->id }})" title="View Details" data-bs-toggle="tooltip">
                                                <i class="feather-eye"></i>
                                            </button>

                                            @if ($request->status == 'pending' && in_array($userRole, ['admin', 'manager']))
                                                <button class="action-btn btn-approve" onclick="approveRequest({{ $request->id }}, {{ $request->overtime_hours }}, '{{ addslashes($request->user_name) }}')" title="Approve" data-bs-toggle="tooltip">
                                                    <i class="feather-check"></i>
                                                </button>
                                                <button class="action-btn btn-reject" onclick="rejectRequest({{ $request->id }}, '{{ addslashes($request->user_name) }}')" title="Reject" data-bs-toggle="tooltip">
                                                    <i class="feather-x"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="empty-state">
                                            <i class="feather-clock"></i>
                                            <h5>No Overtime Requests Found</h5>
                                            <p class="text-muted">No overtime requests match your filters</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($requests->hasPages())
                <x-ui.pagination-footer :paginator="$requests" label="requests" />
            @endif
        </div>
    </div>
@endsection

@section('create-modal')
    <!-- View Request Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Overtime Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewRequestContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Loading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Overtime Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveForm">
                    @csrf
                    <input type="hidden" name="request_id" id="approve_request_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Requested Hours</label>
                            <input type="text" class="form-control" id="requested_hours" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Approved Hours</label>
                            <input type="number" class="form-control" name="approved_hours" id="approved_hours"
                                step="0.5" min="0">
                            <small class="text-muted">Leave empty to approve all requested hours</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" rows="3" placeholder="Add any comments for the employee"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Overtime Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm">
                    @csrf
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" name="rejection_reason" rows="3" required
                                placeholder="Please provide reason for rejection"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000"
        };

        $(document).ready(function() {
            // Auto-submit on filter change
            $('.filter-select, .filter-date').on('change', function() {
                $('#filterForm').submit();
            });

            // Stats card click filter (same pattern as Tasks "Assigned By Me")
            $('.stats-card').on('click', function() {
                const status = $(this).data('status');
                if (status && status !== 'all') {
                    window.location.href = '{{ route('overtime.view-all') }}?status=' + status;
                } else if (status === 'all') {
                    window.location.href = '{{ route('overtime.view-all') }}';
                }
            });

            // Highlight active stats card
            const currentStatus = '{{ request('status') }}';
            if (currentStatus) {
                $(`.stats-card[data-status="${currentStatus}"]`).addClass('active');
            } else {
                $('.stats-card[data-status="all"]').addClass('active');
            }

            // Approve Form Submit
            $('#approveForm').on('submit', function(e) {
                e.preventDefault();
                let requestId = $('#approve_request_id').val();

                $.ajax({
                    url: "{{ url('overtime/approve') }}/" + requestId,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#approveModal').modal('hide');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to approve request');
                    }
                });
            });

            // Reject Form Submit
            $('#rejectForm').on('submit', function(e) {
                e.preventDefault();
                let requestId = $('#reject_request_id').val();

                $.ajax({
                    url: "{{ url('overtime/reject') }}/" + requestId,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            $('#rejectModal').modal('hide');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to reject request');
                    }
                });
            });
        });

        // View Request Details
        function viewRequest(id) {
            $('#viewRequestModal').modal('show');
            $('#viewRequestContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading request details...</p>
                </div>
            `);

            $.ajax({
                url: "{{ url('overtime/show') }}/" + id,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        let data = response.data;
                        let html = `
                            <div class="info-row">
                                <div class="info-label">Employee:</div>
                                <div class="info-value">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            ${data.user_name ? data.user_name.substring(0, 2).toUpperCase() : '--'}
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">${data.user_name || 'N/A'} <small>(${data.employee_id || 'N/A'})</small></div>
                                            <div class="employee-id">${data.user_email || ''}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                           
                            <div class="info-row">
                                <div class="info-label">Date:</div>
                                <div class="info-value">${new Date(data.date).toLocaleDateString('en-GB', {day:'numeric', month:'long', year:'numeric'})}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Requested Hours:</div>
                                <div class="info-value"><strong>${data.overtime_hours} hours</strong></div>
                            </div>
                            ${data.approved_hours ? `
                            <div class="info-row">
                                <div class="info-label">Approved Hours:</div>
                                <div class="info-value"><span class="text-success">${data.approved_hours} hours</span></div>
                            </div>
                            ` : ''}
                            <div class="info-row">
                                <div class="info-label">Reason:</div>
                                <div class="info-value">${data.reason || 'N/A'}</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <span class="badge ${data.status === 'pending' ? 'badge-pending' : (data.status === 'approved' ? 'badge-approved' : 'badge-rejected')}">
                                        ${data.status ? data.status.toUpperCase() : 'N/A'}
                                    </span>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Applied On:</div>
                                <div class="info-value">${new Date(data.created_at).toLocaleDateString('en-GB', {day:'numeric', month:'long', year:'numeric'})}</div>
                            </div>
                            ${data.approved_at ? `
                            <div class="info-row">
                                <div class="info-label">Reviewed On:</div>
                                <div class="info-value">${new Date(data.approved_at).toLocaleString()}</div>
                            </div>
                            ` : ''}
                            ${data.approver_name ? `
                            <div class="info-row">
                                <div class="info-label">Reviewed By:</div>
                                <div class="info-value">${data.approver_name}</div>
                            </div>
                            ` : ''}
                            ${data.rejection_reason ? `
                            <div class="info-row">
                                <div class="info-label">Rejection Reason:</div>
                                <div class="info-value text-danger">${data.rejection_reason}</div>
                            </div>
                            ` : ''}
                        `;
                        $('#viewRequestContent').html(html);
                    } else {
                        $('#viewRequestContent').html(`
                            <div class="text-center py-4 text-danger">
                                <i class="feather-alert-circle" style="font-size: 48px;"></i>
                                <p>${response.message || 'Failed to load request details'}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    $('#viewRequestContent').html(`
                        <div class="text-center py-4 text-danger">
                            <i class="feather-alert-circle" style="font-size: 48px;"></i>
                            <p>Failed to load request details</p>
                            <p class="small text-muted">Please try again</p>
                        </div>
                    `);
                }
            });
        }

        // Approve Request
        function approveRequest(id, hours, employeeName) {
            $('#approve_request_id').val(id);
            $('#requested_hours').val(hours + ' hours');
            $('#approved_hours').val(hours);
            $('#approveModal').modal('show');
        }

        // Reject Request
        function rejectRequest(id, employeeName) {
            $('#reject_request_id').val(id);
            $('#rejectModal').modal('show');
        }
    </script>
@endsection