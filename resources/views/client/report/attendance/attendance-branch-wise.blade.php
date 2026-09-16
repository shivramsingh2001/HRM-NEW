@extends('client.layout.master')

@section('style')
<style>
    /* .stats-grid/.stats-card/.stats-icon-wrapper/.stats-content/.stats-amount-main/.stats-label
       are centralized in client.layout.head (single blue-only theme) — no local copy. */

    .filter-wrapper {
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
        padding: 7px 11px;
        margin-bottom: 11px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .filter-item.date-picker {
        min-width: 160px;
        flex: 0 0 auto;
    }

    .filter-item.date-picker input {
        height: 32px;
        padding: 3px 7px;
        font-size: 10px;
        border: 1.5px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        transition: all 0.3s;
        width: 100%;
        color: #0f172a;
        font-weight: 500;
        cursor: pointer;
    }

    .filter-item.date-picker input:hover {
        background: white;
        border-color: #cbd5e1;
    }

    .filter-item.date-picker input:focus {
        border-color: #1e3a8a;
        outline: none;
        background: white;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
    }

    .reset-btn {
        height: 32px;
        padding: 0 12px;
        background: white;
        color: #64748b;
        border: 1.5px solid #e2e8f0;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .reset-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .reset-btn i {
        font-size: 10px;
    }

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
        color: #1e3a8a;
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
        border-color: #1e3a8a;
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
        color: #1e3a8a;
        margin-bottom: 6px;
        padding: 2px 7px;
        background: #e3edfe;
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
    .stat-mini.leave .number { color: #2563eb; }
    .stat-mini.holiday .number { color: #2563eb; }
    .stat-mini.weekoff .number { color: #1e3a8a; }

    .mt-3 {
        margin-top: 8px !important;
    }

    .badge {
        font-size: 9px !important;
        padding: 3px 10px !important;
        border-radius: 12px !important;
    }

    .badge-success { background: #e3edfe !important; color: #1e3a8a !important; }
    .badge-warning { background: #bfd3f7 !important; color: #1e3a8a !important; }
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

    .summary-footer .text-primary { color: #1e3a8a !important; }
    .summary-footer .text-success { color: #1e3a8a !important; }
    .summary-footer .text-danger { color: #475569 !important; }
    .summary-footer .text-warning { color: #2563eb !important; }
    .summary-footer .text-info { color: #2563eb !important; }

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

    .breadcrumb {
        padding: 6px 0 !important;
        margin-bottom: 0 !important;
        font-size: 10px !important;
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
        .filter-row { flex-direction: column; align-items: stretch; }
        .filter-item.date-picker { min-width: auto; width: 100%; }
        .reset-btn { width: 100%; justify-content: center; }
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
        .filter-item.date-picker input { font-size: 9.5px; height: 28px; }
        .reset-btn { font-size: 9.5px; height: 28px; }
    }
</style>
@endsection

@section('content-area')
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
                <h5 class="m-b-10">Branch Reports</h5>
            </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
            <li class="breadcrumb-item active">Branch Wise Attendance</li>
        </ul>
    </div>
</div>

<div class="main-content" style="padding: 16px 20px !important;">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stats-card total-card">
            <div class="stats-icon-wrapper"><i class="feather-home"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_branches'] ?? 0 }}</div>
                <div class="stats-label">Branches</div>
            </div>
        </div>
        <div class="stats-card branch-card">
            <div class="stats-icon-wrapper"><i class="feather-users"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_employees'] ?? 0 }}</div>
                <div class="stats-label">Employees</div>
            </div>
        </div>
        <div class="stats-card present-card">
            <div class="stats-icon-wrapper"><i class="feather-check-circle"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_present'] ?? 0 }}</div>
                <div class="stats-label">Present</div>
            </div>
        </div>
        <div class="stats-card absent-card">
            <div class="stats-icon-wrapper"><i class="feather-x-circle"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_absent'] ?? 0 }}</div>
                <div class="stats-label">Absent</div>
            </div>
        </div>
        <div class="stats-card leave-card">
            <div class="stats-icon-wrapper"><i class="feather-calendar"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_leave'] ?? 0 }}</div>
                <div class="stats-label">Leave</div>
            </div>
        </div>
        <div class="stats-card holiday-card">
            <div class="stats-icon-wrapper"><i class="feather-home"></i></div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['total_holiday'] ?? 0 }}</div>
                <div class="stats-label">Holiday</div>
            </div>
        </div>
    </div>

    <!-- Filter Section - Date Picker -->
    <div class="filter-wrapper">
        <form action="{{ route('report.attendance.branch-wise') }}" method="GET">
            <div class="filter-row">
                <div class="filter-item date-picker">
                    <input type="date" 
                           name="date" 
                           value="{{ $selectedDate }}" 
                           max="{{ now()->format('Y-m-d') }}"
                           onchange="this.form.submit()">
                </div>
                <span class="date-indicator">
                    <i class="feather-calendar"></i>
                    {{ $dateObj->format('d M Y') }}
                    @if($dateObj->format('Y-m-d') == now()->format('Y-m-d'))
                        <span class="badge badge-success" style="font-size: 8.5px; padding: 2px 4px; margin-left: 3px;">Today</span>
                    @endif
                </span>
                <div class="ms-auto">
                    <a href="{{ route('report.attendance.branch-wise') }}" class="reset-btn">
                        <i class="feather-refresh-cw"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Branch Cards -->
    @if(empty($branchStats) || count($branchStats) == 0)
        <div class="alert alert-info text-center py-3" style="border-radius: 8px;">
            <i class="feather-building" style="font-size: 24px; display: block; margin-bottom: 6px; color: #94a3b8;"></i>
            <h5>No Branches Found</h5>
            <p class="text-muted">No active branches available for this tenant.</p>
        </div>
    @else
        <div class="branch-grid">
            @foreach($branchStats as $branchId => $data)
                @php
                    $branch = $data['branch'];
                    $attendancePercent = $data['total_employees'] > 0 ? round(($data['present'] / $data['total_employees']) * 100, 2) : 0;
                    $percentColor = $attendancePercent >= 80 ? 'success' : ($attendancePercent >= 60 ? 'warning' : 'danger');
                @endphp
                <a href="{{ route('report.attendance.branch-wise.detail', $branchId) }}?date={{ $selectedDate }}" 
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
                        <span style="font-size: 9px; color: #1e3a8a;">
                            View <i class="feather-arrow-right" style="font-size: 9.5px;"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
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
    $(document).ready(function() {
        // Auto-submit on date change
        $('input[name="date"]').on('change', function() {
            $(this).closest('form').submit();
        });
    });
</script>
@endsection