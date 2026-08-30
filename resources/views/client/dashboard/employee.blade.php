@extends('client.layout.master')

@section('style')
    <style>
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .attendance-status {
            font-size: 3rem;
            font-weight: 700;
        }

        .check-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
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

        .wd-50 {
            width: 50px;
        }

        .ht-50 {
            height: 50px;
        }

        .ht-5 {
            height: 5px;
        }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">My Dashboard</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">My Dashboard</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 30px !important;">
        <div class="row">
            <!-- Profile Card -->
            <div class="col-xxl-4">
                <div class="card stretch stretch-full profile-card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <img src="{{ $profile_image ?? asset('assets/images/avatar/default.png') }}" alt=""
                                class="rounded-circle" width="100" height="100" style="object-fit: cover;">
                        </div>
                        <h4 class="text-white mb-1">{{ $user->name }}</h4>
                        <p class="text-white-50 mb-3">{{ $designation }}</p>
                        <div class="d-flex justify-content-center gap-3 mb-3">
                            <div class="text-center">
                                <div class="fs-5 fw-bold text-white">{{ $leave_balance ?? 0 }}</div>
                                <small class="text-white-50">Leave Balance</small>
                            </div>
                            <div class="text-center">
                                <div class="fs-5 fw-bold text-white">{{ $monthly_attendance['present'] ?? 0 }}</div>
                                <small class="text-white-50">Days Present</small>
                            </div>
                        </div>
                        <div class="progress ht-5 mb-3">
                            <div class="progress-bar bg-white" role="progressbar"
                                style="width: {{ $profile_completion['percentage'] ?? 0 }}%"></div>
                        </div>
                        <small class="text-white-50">Profile {{ $profile_completion['percentage'] ?? 0 }}% Complete</small>
                    </div>
                </div>
            </div>

            <!-- Today's Attendance Card -->
            <div class="col-xxl-4">
                <div class="card stretch stretch-full">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-4">Today's Attendance</h5>

                        @if ($today_attendance && $today_attendance->clock_in)
                            <div class="check-btn bg-success text-white mb-3">
                                <i class="feather-check"></i>
                            </div>
                            <h4 class="mb-2">Checked In</h4>
                            <p class="text-muted mb-3">
                                {{ \Carbon\Carbon::parse($today_attendance->clock_in)->format('h:i A') }}</p>

                            @if ($today_attendance->clock_out)
                                <div class="check-btn bg-info text-white mb-3">
                                    <i class="feather-check-circle"></i>
                                </div>
                                <h4 class="mb-2">Checked Out</h4>
                                <p class="text-muted">
                                    {{ \Carbon\Carbon::parse($today_attendance->clock_out)->format('h:i A') }}</p>
                                <p class="text-success">Total: {{ $today_attendance->total_hours }} hrs</p>
                            @else
                                <button class="btn btn-primary btn-lg w-100" onclick="clockOut()">
                                    <i class="feather-log-out me-2"></i>Clock Out
                                </button>
                            @endif
                        @else
                            <div class="check-btn bg-warning text-white mb-3">
                                <i class="feather-clock"></i>
                            </div>
                            <h4 class="mb-3">Not Checked In</h4>
                            <button class="btn btn-primary btn-lg w-100" onclick="clockIn()">
                                <i class="feather-log-in me-2"></i>Clock In
                            </button>
                        @endif

                        @if ($today_shift)
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">Shift: {{ $today_shift->name }}
                                    ({{ \Carbon\Carbon::parse($today_shift->start_time)->format('h:i A') }} -
                                    {{ \Carbon\Carbon::parse($today_shift->end_time)->format('h:i A') }})</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Monthly Summary -->
            <div class="col-xxl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Monthly Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="attendance-status text-primary">{{ $monthly_attendance['percentage'] ?? 0 }}%</div>
                            <p class="text-muted">Attendance Rate</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">
                                    <i class="feather-check-circle text-success fs-3 mb-2"></i>
                                    <h5>{{ $monthly_attendance['present'] ?? 0 }}</h5>
                                    <small class="text-muted">Present</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">
                                    <i class="feather-x-circle text-danger fs-3 mb-2"></i>
                                    <h5>{{ $monthly_attendance['absent'] ?? 0 }}</h5>
                                    <small class="text-muted">Absent</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">

                                    <i class="fa-regular fa-calendar text-primary fs-3 mb-2"></i>
                                    <h5>{{ $monthly_attendance['holiday_days'] ?? 0 }}</h5>
                                    <small class="text-muted">Holiday</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">
                                    <i class="fa-solid fa-calendar-week fs-3 mb-2"></i>
                                    <h5>{{ $monthly_attendance['weekoff_days'] ?? 0 }}</h5>
                                    <small class="text-muted">Week Offs Days</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">
                                    <i class="feather-calendar text-warning fs-3 mb-2"></i>
                                    <h5>{{ $monthly_attendance['leave_count'] ?? 0 }}</h5>
                                    <small class="text-muted">Leaves Days</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded text-center">
                                    <i class="feather-check-square text-info fs-3 mb-2"></i>
                                    <h5>{{ $pending_tasks ?? 0 }}</h5>
                                    <small class="text-muted">Pending Tasks</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Details -->
            <div class="col-xxl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Recent Leave Applications</h5>
                        <div class="card-header-actions">
                            <a href="{{ route('leave.view') }}" class="">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @forelse($recent_leaves ?? [] as $leave)
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div
                                        class="wd-50 ht-50 rounded-2 d-flex align-items-center justify-content-center 
                        @if ($leave->status == 'approved') bg-soft-success
                        @elseif($leave->status == 'pending') bg-soft-warning
                        @elseif($leave->status == 'rejected') bg-soft-danger
                        @else bg-soft-info @endif">
                                        <i
                                            class="feather-calendar fs-4 
                            @if ($leave->status == 'approved') text-success
                            @elseif($leave->status == 'pending') text-warning
                            @elseif($leave->status == 'rejected') text-danger
                            @else text-info @endif">
                                        </i>
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">{{ $leave->leave_type_name ?? 'Leave' }}</span>
                                        <small class="text-muted">
                                            {{ \Carbon\Carbon::parse($leave->start_date)->format('d M') }} -
                                            {{ $leave->end_date ? \Carbon\Carbon::parse($leave->end_date)->format('d M Y') : \Carbon\Carbon::parse($leave->start_date)->format('d M Y') }}
                                        </small>
                                        @php
                                            $start = \Carbon\Carbon::parse($leave->start_date);
                                            $end = \Carbon\Carbon::parse($leave->end_date);
                                            $days = $start->diffInDays($end) + 1;

                                            if ($leave->start_session == 1 || $leave->end_session == 1) {
                                                $days = $days - 0.5;
                                                $sessionText = ' (First Half)';
                                            } elseif ($leave->start_session == 2 || $leave->end_session == 2) {
                                                $days = $days - 0.5;
                                                $sessionText = ' (Second Half)';
                                            } else {
                                                $sessionText = '';
                                            }
                                        @endphp
                                        <small class="text-muted d-block">{{ $days }}
                                            day{{ $days > 1 ? 's' : '' }}{{ $sessionText }}</small>
                                    </div>
                                </div>
                                <div>
                                    @php
                                        $statusClass = match ($leave->status) {
                                            'approved' => 'success',
                                            'pending' => 'warning',
                                            'rejected' => 'danger',
                                            'cancelled' => 'secondary',
                                            default => 'info',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusClass }} px-3 py-2">
                                        {{ ucfirst($leave->status) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="feather-calendar fs-24 mb-2"></i>
                                    <p>No leave applications found</p>
                                    <a href="{{ route('leave.apply') }}" class="btn btn-sm btn-primary mt-2">
                                        Apply for Leave
                                    </a>
                                </div>
                            </div>
                        @endforelse

                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Leave Balance: <strong>{{ $leave_balance ?? 0 }}
                                        days</strong></span>
                                <a href="{{ route('leave.apply') }}" class="btn btn-primary btn-sm">
                                    <i class="feather-plus me-1"></i>New Application
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tasks -->
            <div class="col-xxl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">My Recent Tasks</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recent_tasks ?? [] as $task)
                                        <tr onclick="window.location='{{ route('task.view-detail', ['id' => $task->id]) }}'">
                                            <div>
                                                <td>{{ Str::limit($task->title, 30) ?? '' }}
                                            </div>
                                            @if ($task->description)
                                                <small class="text-muted d-block" style="font-size: 10px;">
                                                    {{ Str::limit($task->description, 40) }}
                                                </small>
                                            @endif
                                            </td>
                                            <td>{{ $task->project_name ?? 'N/A' }}</td>
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

            <!-- Upcoming Holidays & Announcements -->
            <div class="col-xxl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Upcoming Holidays</h5>
                    </div>
                    <div class="card-body">
                        @forelse($upcoming_holidays ?? [] as $holiday)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div
                                    class="wd-50 ht-50 bg-soft-primary rounded-2 d-flex align-items-center justify-content-center">
                                    <i class="feather-gift text-primary"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-semibold">{{ $holiday->name }}</span>
                                    <small
                                        class="text-muted">{{ \Carbon\Carbon::parse($holiday->start_date)->format('l, d M Y') }}</small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center py-3">No upcoming holidays</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="col-xxl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        @forelse($recent_announcements ?? [] as $announcement)
                            <div class="mb-3 pb-3 border-bottom">
                                <h6 class="fw-semibold mb-1">{{ $announcement->title }}</h6>
                                <p class="text-muted small mb-2">{{ Str::limit($announcement->description, 100) }}</p>
                                <small class="text-muted">Posted by {{ $announcement->user_name ?? 'System' }} •
                                    {{ \Carbon\Carbon::parse($announcement->created_at)->diffForHumans() }}</small>
                            </div>
                        @empty
                            <p class="text-muted text-center py-3">No announcements</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
