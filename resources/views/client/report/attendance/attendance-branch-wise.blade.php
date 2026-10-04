@extends('client.layout.master')

@section('style')
<style>









    /* One-row filters: start – end date, location search, reset (same look as the other reports) */
    .loc-filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .loc-input { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 10px; font-size: 11px; height: 32px; background-color: #fff; }
    .loc-input:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .loc-reset { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; height: 32px; display: inline-flex; align-items: center; text-decoration: none; }
    .loc-reset:hover { background: #EFF6FF; color: #0D6EFD; }
    .date-sep { font-size: 11px; color: #6b7385; flex: none; }

    .date-indicator {
        background: #f8fafc;
        padding: 2px 7px;
        border-radius: 6px;
        font-size: 10px;
        color: #0f172a;
        font-weight: 500;
    }

    .date-indicator i {
        margin-right: 3px;
        color: var(--icon-color, #0D6EFD);
        font-size: 10px;
    }

    .date-indicator .badge {
        font-size: 8.5px;
        padding: 2px 4px;
    }

    .branch-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-top: 11px;
    }

    .branch-card-item {
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
        padding: 10px 11px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .branch-card-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.08);
        border-color: #0D6EFD;
    }

    .branch-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 4px;
    }

    .branch-name {
        font-size: 10.5px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 1px;
    }

    .branch-code {
        font-size: 9px;
        color: #64748b;
        background: #f1f5f9;
        padding: 1px 6px;
        border-radius: 10px;
        font-weight: 500;
        flex-shrink: 0;
        margin-left: 4px;
    }

    .branch-description {
        font-size: 9.5px;
        color: #64748b;
        margin-bottom: 4px;
        line-height: 1.3;
    }

    .branch-employees {
        font-size: 9.5px;
        font-weight: 600;
        color: #0D6EFD;
        margin-bottom: 6px;
        padding: 2px 7px;
        background: #EFF6FF;
        border-radius: 6px;
        display: inline-block;
    }

    .branch-employees i {
        font-size: 9.5px;
    }

    .branch-stats {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 4px;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid #f1f5f9;
    }

    .stat-mini {
        text-align: center;
        padding: 3px 2px;
        border-radius: 6px;
        background: #f8fafc;
        transition: all 0.3s;
    }

    .stat-mini:hover {
        background: #f1f5f9;
    }

    .stat-mini .number {
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
    }

    .stat-mini .label {
        font-size: 7px;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 600;
        letter-spacing: 0.3px;
        display: block;
        margin-top: 1px;
    }

    .stat-mini.present .number { color: #3b82f6; }
    .stat-mini.absent .number { color: #475569; }
    .stat-mini.leave .number { color: #0D6EFD; }
    .stat-mini.holiday .number { color: #0D6EFD; }
    .stat-mini.weekoff .number { color: #0D6EFD; }

    .mt-3 {
        margin-top: 8px !important;
    }

    .badge {
        font-size: 9px !important;
        padding: 3px 10px !important;
        border-radius: 12px !important;
    }

    .badge-success { background: #EFF6FF !important; color: #0D6EFD !important; }
    .badge-warning { background: #bfd3f7 !important; color: #0D6EFD !important; }
    .badge-danger { background: #e2e8f0 !important; color: #475569 !important; }

    .summary-footer {
        margin-top: 8px;
        padding: 7px 11px;
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
    }

    .summary-footer .small {
        font-size: 9px !important;
        color: #64748b;
    }

    .summary-footer .fw-bold {
        font-size: 11px !important;
    }

    .summary-footer .text-primary { color: #0D6EFD !important; }
    .summary-footer .text-success { color: #0D6EFD !important; }
    .summary-footer .text-danger { color: #475569 !important; }
    .summary-footer .text-warning { color: #0D6EFD !important; }
    .summary-footer .text-info { color: #0D6EFD !important; }

    .alert {
        padding: 20px !important;
        border-radius: 8px !important;
    }

    .alert i {
        font-size: 24px !important;
        display: block !important;
        margin-bottom: 8px !important;
    }

    .alert h5 {
        font-size: 12px !important;
        margin-bottom: 4px !important;
    }

    .alert p {
        font-size: 10.5px !important;
        margin-bottom: 0 !important;
    }

    .page-header {
        margin-bottom: 12px !important;
    }


    /* Responsive */
    @media (max-width: 1200px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .branch-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (max-width: 992px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .branch-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .branch-grid { grid-template-columns: 1fr; gap: 10px; }



        .branch-stats { grid-template-columns: repeat(3, 1fr); }
        .stat-mini .number { font-size: 10.5px; }
        .main-content { padding: 10px 12px !important; }
        .branch-card-item { padding: 8px; }
        .summary-footer .row { gap: 6px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
        .branch-grid { grid-template-columns: 1fr; }
        .branch-card-item { padding: 7px; }
        .branch-name { font-size: 10px; }
        .stat-mini .number { font-size: 10px; }
        .date-indicator { font-size: 9.5px; }


    }
</style>
@endsection

@section('content-area')
<x-ui.page-header title="Attendance Location Wise Report" current="Attendance Location Wise Report" :crumbs="[['label' => 'Reports', 'url' => route('report.attendance.index')]]" />

<div class="main-content" style="padding: 16px 20px !important;">
    @include('client.report.partials.report-subnav', ['group' => 'attendance'])

    <!-- Filter Section - Date Picker -->
    <x-ui.filter-card title="Filter Report">
        <form action="{{ route('report.attendance.branch-wise') }}" method="GET" id="filterForm">
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
                        placeholder="Search location" aria-label="Search location" autocomplete="off">
                </div>
                @include('client.report.partials.employee-filters', ['selectClass' => 'loc-input'])
                <span class="date-indicator">
                    <i class="feather-calendar"></i>
                    @if ($startDate === $endDate)
                        {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}
                        @if ($startDate == now()->format('Y-m-d'))
                            <span class="badge badge-success" style="font-size: 8.5px; padding: 2px 4px; margin-left: 3px;">Today</span>
                        @endif
                    @else
                        {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }} ({{ $dayCount }} days)
                    @endif
                </span>
                <div class="filter-item">
                    <a href="{{ route('report.attendance.branch-wise') }}" class="loc-reset" title="Reset filters" aria-label="Reset filters">
                        <i class="feather-refresh-cw"></i></a>
                </div>
            </div>
        </form>
    </x-ui.filter-card>

    @if ($rangeNote)
        <div class="alert alert-info py-2" style="font-size:10.5px;border-radius:10px;"><i class="feather-info me-1"></i>{{ $rangeNote }}</div>
    @endif

    <!-- Attendance Location Cards -->
    @if(empty($branchStats) || count($branchStats) == 0)
        <div class="alert alert-info text-center py-3" style="border-radius: 8px;">
            <i class="feather-building" style="font-size: 24px; display: block; margin-bottom: 6px; color: #94a3b8;"></i>
            <h5>No Attendance Locations Found</h5>
            <p class="text-muted">{{ $search !== '' ? 'No attendance location matches "' . $search . '".' : 'No active attendance locations available for this company.' }}</p>
        </div>
    @else
        <div class="branch-grid">
            @foreach($branchStats as $branchId => $data)
                @php
                    $branch = $data['branch'];
                    // present employee-days ÷ (employees × days in range)
                    $attendancePercent = $data['employee_days'] > 0 ? round(($data['present'] / $data['employee_days']) * 100, 2) : 0;
                    $percentColor = $attendancePercent >= 80 ? 'success' : ($attendancePercent >= 60 ? 'warning' : 'danger');
                @endphp
                <a href="{{ route('report.attendance.branch-wise.detail', array_merge(request()->only(['branch_id', 'department', 'designation']), ['branchId' => $branchId, 'start_date' => $startDate, 'end_date' => $endDate])) }}" 
                   class="branch-card-item">
                    <div class="branch-header">
                        <div>
                            <div class="branch-name">{{ $branch->name }}</div>
                            @if($branch->description)
                                <div class="branch-description">{{ Str::limit($branch->description, 40) }}</div>
                            @endif
                        </div>
                        
                        <span class="branch-employees">
                            <i class="feather-users me-1"></i> 
                            {{ $data['total_employees'] }}
                        </span>
                    </div>

                    <!--<div class="branch-employees">-->
                    <!--    <i class="feather-users me-1"></i> -->
                    <!--    {{ $data['total_employees'] }}-->
                    <!--</div>-->

                    <div class="branch-stats">
                        <div class="stat-mini present">
                            <div class="number">{{ $data['present'] }}</div>
                            <div class="label">P</div>
                        </div>
                        <div class="stat-mini absent">
                            <div class="number">{{ $data['absent'] }}</div>
                            <div class="label">A</div>
                        </div>
                        <div class="stat-mini leave">
                            <div class="number">{{ $data['on_leave'] }}</div>
                            <div class="label">L</div>
                        </div>
                        <div class="stat-mini holiday">
                            <div class="number">{{ $data['holiday'] }}</div>
                            <div class="label">H</div>
                        </div>
                        <div class="stat-mini weekoff">
                            <div class="number">{{ $data['week_off'] }}</div>
                            <div class="label">WO</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <!--<span class="badge badge-{{ $percentColor }}">-->
                        <!--    {{ $attendancePercent }}%-->
                        <!--</span>-->
                        <span class="badge">
                            {{ $attendancePercent }}%
                        </span>
                        <span style="font-size: 9px; color: #0D6EFD;">
                            View <i class="feather-arrow-right" style="font-size: 9.5px;"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        <x-ui.pagination-footer :paginator="$locationPage" label="locations" :always="false" class="mt-2" />
    @endif

    <!-- Summary Footer -->
    <!--@if(!empty($branchStats) && count($branchStats) > 0)-->
    <!--    <div class="summary-footer">-->
    <!--        <div class="row text-center">-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Branches</div>-->
    <!--                <div class="fw-bold text-primary">{{ $stats['total_branches'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Employees</div>-->
    <!--                <div class="fw-bold text-primary">{{ $stats['total_employees'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Present</div>-->
    <!--                <div class="fw-bold text-success">{{ $stats['total_present'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Absent</div>-->
    <!--                <div class="fw-bold text-danger">{{ $stats['total_absent'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Leave</div>-->
    <!--                <div class="fw-bold text-warning">{{ $stats['total_leave'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--            <div class="col-4 col-md-2">-->
    <!--                <div class="small">Holiday</div>-->
    <!--                <div class="fw-bold text-info">{{ $stats['total_holiday'] ?? 0 }}</div>-->
    <!--            </div>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--@endif-->
</div>
@endsection

@section('script-area')
<script>
    $(function() {
        const form = $('#filterForm');
        form.find('.auto-submit').on('change', () => form.submit());

        // search: submit shortly after typing stops, then put the cursor back after the reload
        const key = 'locationReportSearchFocus';
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