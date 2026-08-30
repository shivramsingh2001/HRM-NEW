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

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .avatar-text {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bg-soft-primary {
            background: rgba(75, 123, 236, 0.1);
            color: #4b7bec;
        }

        .bg-soft-success {
            background: rgba(38, 222, 129, 0.1);
            color: #26de81;
        }

        .bg-soft-danger {
            background: rgba(252, 92, 101, 0.1);
            color: #fc5c65;
        }

        .bg-soft-warning {
            background: rgba(253, 150, 68, 0.1);
            color: #fd9644;
        }

        .bg-soft-info {
            background: rgba(45, 152, 218, 0.1);
            color: #2d98da;
        }

        .wd-7 {
            width: 7px;
            height: 7px;
        }

        .ht-7 {
            height: 7px;
        }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Manager Dashboard</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Manager Dashboard</li>
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
        <div class="row mb-2">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="text-white mb-2">Welcome back, {{ Auth::user()->name }}!</h4>
                                <p class="text-white-50 mb-0">{{ \Carbon\Carbon::now()->format('l, d F Y') }} •
                                    {{ \Carbon\Carbon::now()->format('h:i A') }}</p>
                            </div>
                            <div class="text-end">
                                <h2 class="text-white mb-2">{{ $total_employees ?? 0 }}</h2>
                                <p class="text-white-50 mb-0">Total Employees</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <!-- Team Statistics Cards -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg bg-soft-primary">
                                <i class="feather-users"></i>
                            </div>
                            <div>
                                <a href="{{ route('team.index') }}" class="text-dark text-decoration-none"
                                    style="cursor: pointer;">
                                    <div class="fs-4 fw-bold text-dark">{{ $team_size ?? 0 }}</div>
                                    <h3 class="fs-13 fw-semibold">Team Size</h3>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg bg-soft-success">
                                <i class="feather-check-circle"></i>
                            </div>
                            <div>
                                <a href="{{ route('team.index', ['status' => 'present']) }}"
                                    class="text-dark text-decoration-none" style="cursor: pointer;">
                                    <div class="fs-4 fw-bold text-dark">{{ $team_present ?? 0 }}</div>
                                    <h3 class="fs-13 fw-semibold">Present Today</h3>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg bg-soft-warning">
                                <i class="feather-calendar"></i>
                            </div>
                            <div>
                                <a href="{{ route('team.index', ['status' => 'on_leave']) }}"
                                    class="text-dark text-decoration-none" style="cursor: pointer;">
                                    <div class="fs-4 fw-bold text-dark">{{ $team_on_leave ?? 0 }}</div>
                                    <h3 class="fs-13 fw-semibold">On Leave</h3>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-lg bg-soft-danger">
                                <i class="feather-clock"></i>
                            </div>
                            <div>
                                <a href="{{ route('team.index', ['status' => 'absent']) }}"
                                    class="text-dark text-decoration-none" style="cursor: pointer;">
                                    <div class="fs-4 fw-bold text-dark">{{ $team_absent ?? 0 }}</div>
                                    <h3 class="fs-13 fw-semibold">Absent</h3>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Team Members -->
            <div class="col-xxl-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Team Members</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <!--<th>Actions</th>-->
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($team_members ?? [] as $member)
                                        <tr>

                                            <td>
                                                <a href="{{ route('team.member-detail', ['id' => encrypt($member->id)]) }}">
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                            {{ strtoupper(substr($member->name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">
                                                                {{ $member->name ?? 'N/A' }}
                                                                <small
                                                                    class="text-muted employee-email">({{ $member->employee_id ?? 'N/A' }})</small>
                                                            </div>
                                                            <div class="employee-email">
                                                                {{ $member->email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>{{ $member->designation_name ?? 'N/A' }}</td>
                                            <td>{{ $member->department_name ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-soft-success text-success">Active</span>
                                            </td>
                                            <!--<td>-->
                                            <!--    <a href="{{ route('team.member-detail', ['id' => encrypt($member->id)]) }}" class="pe-2 btn-action">-->
                                            <!--        <i class="feather-eye"></i>-->
                                            <!--    </a>-->
                                            <!--</td>-->
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No team members found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Pending Approvals Cards -->
            <div class="col-xxl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Pending Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4 p-3 border rounded">
                            <a href="{{ route('leave.view-all', ['status' => 'pending']) }}"
                                style="text-decoration:none !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="feather-calendar text-warning me-2"></i>
                                        <span class="fw-semibold">Leave Requests</span>
                                    </div>
                                    <span class="badge bg-warning">{{ $pending_leave_requests ?? 0 }}</span>
                                </div>
                            </a>
                        </div>
                        <div class="mb-4 p-3 border rounded">
                            <a href="{{ route('task.assigned-by-me', ['status' => 'completed']) }}"
                                style="text-decoration:none !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="feather-check-square text-info me-2"></i>
                                        <span class="fw-semibold">Task Approvals</span>
                                    </div>
                                    <span class="badge bg-info">{{ $pending_task_approvals ?? 0 }}</span>
                                </div>
                            </a>
                        </div>
                        <div class="p-3 border rounded">
                            <a href="{{ route('expense.view-all', ['status' => 'pending']) }}"
                                style="text-decoration:none !important;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-rupee-sign text-danger me-2"></i>
                                        <span class="fw-semibold">Expense Approvals</span>
                                    </div>
                                    <span class="badge bg-danger">{{ $pending_expense_approvals ?? 0 }}</span>
                                </div>
                            </a>
                        </div>
                    </div>
                    <!--<a href="#" class="card-footer fs-11 fw-bold text-center py-3">View All Approvals</a>-->
                </div>
            </div>


            <!-- Recent Team Leaves -->
            <div class="col-xxl-8">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Recent Leave Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recent_team_leaves ?? [] as $leave)
                                        <tr>
                                            <td>
                                                <a href="{{ route('leave.view-all', ['id' => $leave->id]) }}">
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                            {{ strtoupper(substr($leave->user_name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">
                                                                {{ $leave->user_name ?? 'N/A' }}
                                                                <small
                                                                    class="text-muted employee-email">({{ $leave->employee_id ?? 'N/A' }})</small>
                                                            </div>
                                                            <div class="employee-email">
                                                                {{ $leave->user_email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <!--<td>{{ $leave->user_name ?? 'N/A' }}</td>-->
                                            <td>{{ $leave->leave_type_name ?? 'N/A' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d M') }} </td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $leave->status == 'approved' ? 'success' : ($leave->status == 'pending' ? 'warning' : 'danger') }}">
                                                    {{ ucfirst($leave->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No leave requests</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Tasks -->
            <div class="col-xxl-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Team Tasks</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Task</th>
                                        <th>Task Date</th>
                                        <!--<th>Assigned To</th>-->
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($team_tasks ?? [] as $task)
                                        <tr>
                                            <td>
                                                <a href="{{ route('task.view-detail', ['id' => $task->id]) }}">
                                                    <div class="employee-info">
                                                        <div class="employee-avatar"
                                                            style="background: #4f46e5; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                            {{ strtoupper(substr($task->user_name ?? 'U', 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name">
                                                                {{ $task->user_name ?? 'N/A' }}
                                                                <small
                                                                    class="text-muted employee-email">({{ $task->user_employee_id ?? 'N/A' }})</small>
                                                            </div>
                                                            <div class="employee-email">
                                                                {{ $task->user_email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ Str::limit($task->title, 30) ?? '' }}</div>
                                                @if ($task->description)
                                                    <small class="text-muted d-block" style="font-size: 10px;">
                                                        {{ Str::limit($task->description, 40) }}
                                                    </small>
                                                @endif
                                            </td>
                                            <!--<td>{{ $task->assigned_to_name ?? 'N/A' }}</td>-->
                                            <td>{{ \Carbon\Carbon::parse($task->task_date)->format('d M Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($task->deadline_date)->format('d M Y') }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $task->status == 'completed' ? 'success' : 'warning' }}">
                                                    {{ str_replace('_', ' ', ucfirst($task->status)) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No tasks assigned</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
