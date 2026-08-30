@extends('client.layout.master')

@section('style')
<style>
    .stat-card {
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
</style>
@endsection

@section('content-area')
<!-- [ page-header ] start -->
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">HR Dashboard</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item">HR Dashboard</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather-plus me-2"></i>Add Employee
                </a>
            </div>
        </div>
    </div>
</div>

<!-- [ Main Content ] start -->
<div class="main-content" style="padding: 30px !important;">
    <div class="row">
        <!-- Statistics Cards -->
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-text avatar-lg bg-soft-primary">
                            <i class="feather-users"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold text-dark">{{ $totalEmployees }}</div>
                            <h3 class="fs-13 fw-semibold">Total Employees</h3>
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
                            <i class="feather-user-plus"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold text-dark">{{ $newJoinings }}</div>
                            <h3 class="fs-13 fw-semibold">New Joinings</h3>
                            <span class="fs-11 text-muted">This Month</span>
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
                            <i class="feather-calendar"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold text-dark">{{ $onLeaveToday }}</div>
                            <h3 class="fs-13 fw-semibold">On Leave Today</h3>
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
                            <i class="feather-clock"></i>
                        </div>
                        <div>
                            <div class="fs-4 fw-bold text-dark">{{ $pendingLeaves }}</div>
                            <h3 class="fs-13 fw-semibold">Pending Leaves</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gender Distribution Chart -->
        <div class="col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Gender Distribution</h5>
                </div>
                <div class="card-body">
                    <div id="gender-donut-chart" style="height: 250px;"></div>
                    <div class="row g-2 mt-3">
                        @foreach($genderChart as $item)
                        <div class="col-4">
                            <div class="p-2 hstack gap-2 rounded border border-dashed">
                                <span class="wd-7 ht-7 rounded-circle d-inline-block" 
                                      style="background-color: {{ $item['color'] }}"></span>
                                <span>{{ $item['label'] }} <span class="fs-10 text-muted">({{ $item['value'] }})</span></span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Distribution -->
        <div class="col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Department Distribution</h5>
                </div>
                <div class="card-body">
                    @foreach($departmentWise as $dept)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $dept->name }}</span>
                            <span class="fw-bold">{{ $dept->users_count }} employees</span>
                        </div>
                        <div class="progress ht-5">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: {{ $totalEmployees > 0 ? ($dept->users_count / $totalEmployees) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Designation Distribution -->
        <div class="col-xxl-4">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Designation Distribution</h5>
                </div>
                <div class="card-body">
                    @foreach($designationWise as $desig)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $desig->name }}</span>
                            <span class="fw-bold">{{ $desig->users_count }}</span>
                        </div>
                        <div class="progress ht-5">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: {{ $totalEmployees > 0 ? ($desig->users_count / $totalEmployees) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Joinings -->
        <div class="col-xxl-6">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Recent Joinings</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Joining Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentJoinings as $emp)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $emp->basicDetails->profile_image ?? asset('assets/images/avatar/default.png') }}" 
                                                 alt="" width="32" height="32" class="rounded-circle">
                                            <span>{{ $emp->name }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $emp->jobDetail->designation->name ?? 'N/A' }}</td>
                                    <td>{{ $emp->jobDetail->department->name ?? 'N/A' }}</td>
                                    <td>{{ $emp->jobDetail->joining_date ? \Carbon\Carbon::parse($emp->jobDetail->joining_date)->format('d M Y') : 'N/A' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center py-4">No recent joinings</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Leaves -->
        <div class="col-xxl-6">
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
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLeaves as $leave)
                                <tr>
                                    <td>{{ $leave->user->name ?? 'N/A' }}</td>
                                    <td>{{ $leave->leaveType->name ?? 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d M') }} - {{ \Carbon\Carbon::parse($leave->end_date)->format('d M') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $leave->status == 'approved' ? 'success' : ($leave->status == 'pending' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($leave->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center py-4">No recent leaves</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="col-xxl-12">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="mb-3">Upcoming Holidays</h6>
                            @forelse($upcomingHolidays as $holiday)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="wd-40 ht-40 bg-soft-primary rounded-2 d-flex align-items-center justify-content-center">
                                    <i class="feather-gift"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-semibold">{{ $holiday->name }}</span>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($holiday->start_date)->format('d M Y') }}</small>
                                </div>
                            </div>
                            @empty
                            <p class="text-muted">No upcoming holidays</p>
                            @endforelse
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-3">Upcoming Birthdays</h6>
                            @forelse($upcomingBirthdays as $birthday)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="wd-40 ht-40 bg-soft-success rounded-2 d-flex align-items-center justify-content-center">
                                    <i class="feather-gift"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-semibold">{{ $birthday['user']->name }}</span>
                                    <small class="text-muted">{{ $birthday['date']->format('d M') }}</small>
                                </div>
                            </div>
                            @empty
                            <p class="text-muted">No upcoming birthdays</p>
                            @endforelse
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-3">Work Anniversaries</h6>
                            @forelse($upcomingAnniversaries as $anniversary)
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="wd-40 ht-40 bg-soft-warning rounded-2 d-flex align-items-center justify-content-center">
                                    <i class="feather-award"></i>
                                </div>
                                <div>
                                    <span class="d-block fw-semibold">{{ $anniversary['user']->name }}</span>
                                    <small class="text-muted">{{ $anniversary['years'] }} years ({{ $anniversary['date']->format('d M') }})</small>
                                </div>
                            </div>
                            @empty
                            <p class="text-muted">No upcoming anniversaries</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-area')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    $(document).ready(function() {
        // Gender Distribution Chart
        var genderOptions = {
            series: [{{ $maleEmployees }}, {{ $femaleEmployees }}, {{ $totalEmployees - $maleEmployees - $femaleEmployees }}],
            chart: { type: 'donut', height: 250 },
            labels: ['Male', 'Female', 'Other'],
            colors: ['#3454d1', '#e83e8c', '#20c997'],
            legend: { show: false }
        };

        if(document.getElementById("gender-donut-chart")) {
            var genderChart = new ApexCharts(document.querySelector("#gender-donut-chart"), genderOptions);
            genderChart.render();
        }
    });
</script>
@endsection