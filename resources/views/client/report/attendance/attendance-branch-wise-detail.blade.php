@extends('client.layout.master')

@section('style')
<style>




    .card-body { padding: 0; }








    .table-responsive {
        border-radius: 0 0 12px 12px;
        max-height: 600px;
        overflow-y: auto;
    }

    /* One-row filters: start – end date, search, status, reset (same look as the other reports) */
    .loc-filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .loc-input { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
    .loc-input:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .loc-reset { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; height: 32px; display: inline-flex; align-items: center; text-decoration: none; }
    .loc-reset:hover { background: #EFF6FF; color: #0D6EFD; }
    .date-sep { font-size: 11px; color: #6b7385; flex: none; }

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
    @include('client.report.partials.report-subnav', ['group' => 'attendance'])

    <!-- Filter Section -->
    <x-ui.filter-card title="Filter Report">
        <form action="{{ route('report.attendance.branch-wise.detail', $branch->id) }}" method="GET" id="filterForm">
            <div class="filter-row loc-filter-row">
                <div class="filter-item">
                    <input type="date" name="start_date" value="{{ $startDate }}" max="{{ now()->format('Y-m-d') }}"
                        class="loc-input auto-submit" title="Start date" aria-label="Start date">
                </div>
                <span class="date-sep">to</span>
                <div class="filter-item">
                    <input type="date" name="end_date" value="{{ $endDate }}" max="{{ now()->format('Y-m-d') }}"
                        class="loc-input auto-submit" title="End date" aria-label="End date">
                </div>
                <div class="filter-item">
                    <input type="text" name="search" value="{{ $search }}" class="loc-input" style="min-width:200px"
                        placeholder="Search name / ID / email" aria-label="Search employee" autocomplete="off">
                </div>
                @include('client.report.partials.employee-filters', ['selectClass' => 'loc-input', 'except' => ['location']])
                <div class="filter-item">
                    <select name="status" class="loc-input auto-submit" aria-label="Status">
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
                    <a href="{{ route('report.attendance.branch-wise.detail', $branch->id) }}" class="loc-reset" title="Reset filters" aria-label="Reset filters">
                        <i class="feather-refresh-cw"></i>
                    </a>
                </div>
            </div>
        </form>
    </x-ui.filter-card>

    @if ($rangeNote)
        <div class="alert alert-info py-2" style="font-size:10.5px;border-radius:10px;"><i class="feather-info me-1"></i>{{ $rangeNote }}</div>
    @endif

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
                                    @feature('branches')<th>Branch</th>@endfeature
                                    <th>Attendance Location</th>
                                    <th>Date</th>
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
                                        $dailyRecord = $data;
                                        
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
                                        <td>{{ $reportData->firstItem() + $loop->index }}</td>
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
                                        @feature('branches')<td>{{ $employee->branch_name ?? '—' }}</td>@endfeature
                                        <td>{{ \App\Models\AttendanceLocation::labelFor($employee->office_branch, $employee->location_name) }}</td>
                                        <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($data['date'])->format('d M Y') }}<div class="text-muted" style="font-size:9.5px;">{{ \Carbon\Carbon::parse($data['date'])->format('l') }}</div></td>
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
                                            <a href="{{ route('report.attendance.detail.index', ['user_id' => $employee->id, 'date' => $data['date']]) }}" 
                                               class="action-btn view-btn" title="View Details">
                                                <i class="feather-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-5">
                                            <div class="empty-state">
                                                <i class="feather-users"></i>
                                                <h4>No Employees Found</h4>
                                                <p>No employees found for this attendance location with the selected filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <x-ui.pagination-footer :paginator="$reportData" label="records" />
            </div>
        </div>
    </div>
</div>
@endsection

@section('script-area')
<script>
    $(function() {
        const form = $('#filterForm');
        form.find('.auto-submit').on('change', () => form.submit());

        // search: submit shortly after typing stops, then put the cursor back after the reload
        const key = 'locationDetailSearchFocus';
        const box = form.find('input[name="search"]');
        let last = box.val(), timer;
        box.on('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                if (box.val() === last) return;
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
                form.submit();
            }, 600);
        });
        try {
            if (sessionStorage.getItem(key)) {
                sessionStorage.removeItem(key);
                const el = box.get(0);
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            }
        } catch (e) {}
    });
</script>
@endsection