@extends('client.layout.master')

@section('style')
<style>




    .card-body { padding: 0; }








    .table-responsive {
        border-radius: 0 0 12px 12px;
        max-height: 600px;
        overflow-y: auto;
    }

    /* ==================== EMPLOYEE INFO ==================== */
    .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 140px;
    }

    .employee-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, #EFF6FF, #EFF6FF);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #0D6EFD;
        font-weight: 600;
        font-size: 10px;
        text-transform: uppercase;
        flex-shrink: 0;
        transition: all 0.3s;
    }

    .employee-info:hover .employee-avatar {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .employee-name-text { 
        font-weight: 600; 
        color: #0f172a; 
        font-size: 10.5px; 
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .employee-email-text { 
        font-size: 9.5px; 
        color: #64748b; 
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ==================== BADGES ==================== */
    .badge {
        padding: 3px 7px;
        font-weight: 600;
        font-size: 9px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        border: 1px solid transparent;
        transition: all 0.2s;
    }

    .badge-present { background: #EFF6FF !important; color: #0D6EFD; border-color: #93c5fd; }
    .badge-halfday { background: #EFF6FF !important; color: #0D6EFD; border-color: #93c5fd; }
    .badge-checked_in_only { background: #bfd3f7 !important; color: #0D6EFD; border-color: #60a5fa; }
    .badge-absent { background: #e2e8f0 !important; color: #475569; border-color: #cbd5e1; }
    .badge-on_leave { background: #bfd3f7 !important; color: #0D6EFD; border-color: #60a5fa; }
    .badge-holiday { background: #EFF6FF !important; color: #0D6EFD; border-color: #93c5fd; }
    .badge-week_off { background: #dbeafe !important; color: #0D6EFD; border-color: #bfdbfe; }

    /* ==================== STATUS DOTS ==================== */
    .status-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .status-dot.present { background: #0D6EFD; }
    .status-dot.halfday { background: #0D6EFD; }
    .status-dot.checked_in_only { background: #0D6EFD; }
    .status-dot.absent { background: #475569; }
    .status-dot.on_leave { background: #0D6EFD; }
    .status-dot.holiday { background: #0D6EFD; }
    .status-dot.week_off { background: #3b82f6; }




    /* ==================== BADGE INFO ==================== */
    .badge-info-custom {
        background: #EFF6FF !important;
        color: #0D6EFD !important;
        font-weight: 600 !important;
        padding: 4px 12px !important;
        border-radius: 16px !important;
        border: 1px solid #93c5fd !important;
        font-size: 9.5px !important;
    }

    .badge-success-custom {
        background: #EFF6FF !important;
        color: #0D6EFD !important;
        font-weight: 600 !important;
        padding: 4px 12px !important;
        border-radius: 16px !important;
        border: 1px solid #93c5fd !important;
        font-size: 9.5px !important;
    }

    /* ==================== EMPTY STATE ==================== */
    .empty-state { padding: 28px 14px; text-align: center; }
    .empty-state i { font-size: 40px; color: #d1d5db; margin-bottom: 8px; opacity: 0.5; }
    .empty-state h4 { color: #0f172a; font-size: 14px; font-weight: 600; margin-bottom: 4px; }
    .empty-state p { color: #64748b; font-size: 10.5px; margin-bottom: 0; }

    /* ==================== RESPONSIVE ==================== */
    @media (max-width: 1400px) {
        .stats-grid { grid-template-columns: repeat(4, 1fr); }
    }

    @media (max-width: 992px) {
        .stats-grid { grid-template-columns: repeat(4, 1fr); }



    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }





        .table-responsive { max-height: 500px; }

        .employee-info { min-width: 120px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    }
</style>
@endsection

@section('content-area')
<x-ui.page-header title="Attendance Location Wise Report" :current="$branch->name"
    :crumbs="[['label' => 'Attendance Location Wise Report', 'url' => route('report.attendance.branch-wise')]]">
    <x-slot:actions>
        <a href="{{ route('report.attendance.branch-wise.detail.export', $branch->id) }}?{{ http_build_query(request()->all()) }}"
           class="btn btn-sm btn-primary" target="_blank">
            <i class="feather-download me-1"></i> Export CSV
        </a>
    </x-slot:actions>
</x-ui.page-header>

<div class="main-content" style="padding: 20px !important;">
    <!-- Statistics Cards - All Statuses -->
    <div class="stats-grid">
        <div class="stats-card total-card">
            <div class="stats-icon-wrapper"><i class="feather-users"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_employees'] ?? 0 }}</div>
                <div class="stats-label">Total</div>
            </div>
        </div>
        <div class="stats-card present-card">
            <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['present'] ?? 0 }}</div>
                <div class="stats-label">Present</div>
            </div>
        </div>
        <div class="stats-card halfday-card">
            <div class="stats-icon-wrapper"><i class="feather-sun"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['halfday'] ?? 0 }}</div>
                <div class="stats-label">Half Day</div>
            </div>
        </div>
        <!--<div class="stats-card checkedin-card">-->
        <!--    <div class="stats-icon-wrapper"><i class="feather-clock"></i></div>-->
        <!--    <div class="stats-content">-->
        <!--        <div class="stats-amount-main">{{ $stats['checked_in_only'] ?? 0 }}</div>-->
        <!--        <div class="stats-label">Checked In</div>-->
        <!--    </div>-->
        <!--</div>-->
        <div class="stats-card weekoff-card">
            <div class="stats-icon-wrapper"><i class="feather-calendar"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['week_off'] ?? 0 }}</div>
                <div class="stats-label">Week Off</div>
            </div>
        </div>
        <div class="stats-card absent-card">
            <div class="stats-icon-wrapper"><i class="feather-x-circle"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['absent'] ?? 0 }}</div>
                <div class="stats-label">Absent</div>
            </div>
        </div>
        <div class="stats-card leave-card">
            <div class="stats-icon-wrapper"><i class="feather-calendar"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['on_leave'] ?? 0 }}</div>
                <div class="stats-label">Leave</div>
            </div>
        </div>
        <div class="stats-card holiday-card">
            <div class="stats-icon-wrapper"><i class="feather-home"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['holiday'] ?? 0 }}</div>
                <div class="stats-label">Holiday</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <x-ui.filter-card title="Filter Report">
        <form action="{{ route('report.attendance.branch-wise.detail', $branch->id) }}" method="GET">
            <div class="filter-row">
                <div class="filter-item date-picker">
                    <input type="date" name="date" value="{{ $selectedDate }}" max="{{ now()->format('Y-m-d') }}" onchange="this.form.submit()">
                </div>
                <div class="filter-item status-filter">
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="present" {{ $statusFilter == 'present' ? 'selected' : '' }}>✅ Present</option>
                        <option value="halfday" {{ $statusFilter == 'halfday' ? 'selected' : '' }}>🌓 Half Day</option>
                        <option value="checked_in_only" {{ $statusFilter == 'checked_in_only' ? 'selected' : '' }}>⏳ Checked In Only</option>
                        <option value="absent" {{ $statusFilter == 'absent' ? 'selected' : '' }}>❌ Absent</option>
                        <option value="on_leave" {{ $statusFilter == 'on_leave' ? 'selected' : '' }}>🏖️ On Leave</option>
                        <option value="holiday" {{ $statusFilter == 'holiday' ? 'selected' : '' }}>🎉 Holiday</option>
                        <option value="week_off" {{ $statusFilter == 'week_off' ? 'selected' : '' }}>📅 Week Off</option>
                    </select>
                </div>
                <div class="filter-item">
                    <a href="{{ route('report.attendance.branch-wise.detail', $branch->id) }}" class="reset-btn" title="Reset filters" aria-label="Reset filters">
                        <i class="feather-refresh-cw"></i>
                    </a>
                </div>
            </div>
        </form>
    </x-ui.filter-card>

    <!-- Employee Table -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table" id="employeeTable">
                            <thead>
                                <tr>
                                    <th width="40">#</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Status</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Total Hours</th>
                                    <th width="80">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reportData as $index => $data)
                                    @php
                                        $employee = $data['employee'];
                                        $status = $data['status'] ?? 'absent';
                                        
                                        // Ensure status is not empty
                                        if (empty($status) || $status == '') {
                                            $status = 'absent';
                                        }
                                        
                                        $status = strtolower($status);
                                        $dailyRecord = reset($data['daily_records']);
                                        
                                        // Map status to display
                                        $statusClass = match($status) {
                                            'present' => 'badge-present',
                                            'halfday', 'half day' => 'badge-halfday',
                                            'checked_in_only' => 'badge-checked_in_only',
                                            'absent' => 'badge-absent',
                                            'on_leave' => 'badge-on_leave',
                                            'holiday' => 'badge-holiday',
                                            'week_off' => 'badge-week_off',
                                            default => 'badge-secondary',
                                        };
                                        
                                        $statusLabel = match($status) {
                                            'present' => 'Present',
                                            'halfday', 'half day' => 'Half Day',
                                            'checked_in_only' => 'Checked In Only',
                                            'absent' => 'Absent',
                                            'on_leave' => 'On Leave',
                                            'holiday' => 'Holiday',
                                            'week_off' => 'Week Off',
                                            default => ucfirst($status),
                                        };
                                        
                                        $statusDot = match($status) {
                                            'present' => 'present',
                                            'halfday', 'half day' => 'halfday',
                                            'checked_in_only' => 'checked_in_only',
                                            'absent' => 'absent',
                                            'on_leave' => 'on_leave',
                                            'holiday' => 'holiday',
                                            'week_off' => 'week_off',
                                            default => 'absent',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    {{ strtoupper(substr($employee->name ?? 'N/A', 0, 2)) }}
                                                </div>
                                                <div class="employee-details">
                                                    <div class="employee-name-text">
                                                        {{ $employee->name ?? 'N/A' }}
                                                        <small>({{ $employee->employee_id ?? 'N/A' }})</small>
                                                    </div>
                                                    <div class="employee-email-text">{{ $employee->email ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $employee->department_name ?? 'N/A' }}</td>
                                        <td>{{ $employee->designation_name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="badge {{ $statusClass }}">
                                                <span class="status-dot {{ $statusDot }}"></span>
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($dailyRecord['clock_in'])
                                                <span style="font-weight: 500; color: #0f172a;">
                                                    {{ \Carbon\Carbon::parse($dailyRecord['clock_in'])->format('d-m-Y h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dailyRecord['clock_out'])
                                                <span style="font-weight: 500; color: #0f172a;">
                                                    {{ \Carbon\Carbon::parse($dailyRecord['clock_out'])->format('d-m-Y h:i A') }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dailyRecord['total_hours'])
                                                <span style="font-weight: 600; color: #0D6EFD;">
                                                    {{ number_format((float)$dailyRecord['total_hours'], 2) }} hrs
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('report.attendance.detail.index', ['user_id' => $employee->id, 'date' => $selectedDate]) }}" 
                                               class="action-btn view-btn" title="View Details">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather-users"></i>
                                                <h4>No Employees Found</h4>
                                                <p>No employees found for this branch with the selected filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if(count($reportData) > 0)
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="text-muted small">
                                Showing <strong>1</strong> to <strong>{{ count($reportData) }}</strong>
                                of <strong>{{ count($reportData) }}</strong> employees
                            </div>
                            <div>
                                <span class="badge badge-info-custom">
                                    <i class="feather-calendar me-1"></i>
                                    {{ $dateObj->format('d M Y') }}
                                    @if($dateObj->format('Y-m-d') == now()->format('Y-m-d'))
                                        <span class="badge badge-success-custom" style="font-size: 8.5px; padding: 2px 6px; margin-left: 3px;">
                                            <i class="feather-clock me-1"></i> Today
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-area')
<script>
    $(document).ready(function() {
        // Auto-submit on filter changes
        $('input[name="date"], select[name="status"]').on('change', function() {
            $(this).closest('form').submit();
        });

        // Search with enter key
        $('input[name="search"]').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('form').submit();
            }
        });
    });
</script>
@endsection