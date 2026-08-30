@extends('client.layout.master')

@section('style')
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
        margin-bottom: 16px;
    }

    .stats-card {
        background: white;
        border-radius: 8px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #eef2f6;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px -6px rgba(0, 0, 0, 0.1);
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
    }

    .stats-card.total-card::before { background: linear-gradient(90deg, #4f46e5, #818cf8); }
    .stats-card.branch-card::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .stats-card.present-card::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .stats-card.absent-card::before { background: linear-gradient(90deg, #ef4444, #f87171); }
    .stats-card.leave-card::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .stats-card.holiday-card::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

    .stats-icon-wrapper {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .total-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(79,70,229,0.12), rgba(79,70,229,0.05)); }
    .total-card .stats-icon-wrapper i { color: #4f46e5; font-size: 16px; }
    .branch-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(16,185,129,0.05)); }
    .branch-card .stats-icon-wrapper i { color: #10b981; font-size: 16px; }
    .present-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(59,130,246,0.12), rgba(59,130,246,0.05)); }
    .present-card .stats-icon-wrapper i { color: #3b82f6; font-size: 16px; }
    .absent-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.05)); }
    .absent-card .stats-icon-wrapper i { color: #ef4444; font-size: 16px; }
    .leave-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(245,158,11,0.12), rgba(245,158,11,0.05)); }
    .leave-card .stats-icon-wrapper i { color: #f59e0b; font-size: 16px; }
    .holiday-card .stats-icon-wrapper { background: linear-gradient(135deg, rgba(139,92,246,0.12), rgba(139,92,246,0.05)); }
    .holiday-card .stats-icon-wrapper i { color: #8b5cf6; font-size: 16px; }

    .stats-content { flex: 1; min-width: 0; }
    .stats-amount-main { font-size: 18px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stats-label { font-size: 9px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }

    .filter-wrapper {
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
        padding: 10px 16px;
        margin-bottom: 16px;
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
        padding: 4px 10px;
        font-size: 12px;
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
        border-color: #4f46e5;
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
        font-size: 12px;
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
        font-size: 12px;
    }

    .date-indicator {
        background: #f8fafc;
        padding: 2px 10px;
        border-radius: 6px;
        font-size: 12px;
        color: #0f172a;
        font-weight: 500;
    }

    .date-indicator i {
        margin-right: 4px;
        color: #4f46e5;
        font-size: 12px;
    }

    .date-indicator .badge {
        font-size: 9px;
        padding: 2px 6px;
    }

    .branch-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-top: 16px;
    }

    .branch-card-item {
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
        padding: 14px 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .branch-card-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.08);
        border-color: #4f46e5;
    }

    .branch-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 6px;
    }

    .branch-name {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 1px;
    }

    .branch-code {
        font-size: 10px;
        color: #64748b;
        background: #f1f5f9;
        padding: 1px 8px;
        border-radius: 10px;
        font-weight: 500;
        flex-shrink: 0;
        margin-left: 6px;
    }

    .branch-description {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 6px;
        line-height: 1.3;
    }

    .branch-employees {
        font-size: 11px;
        font-weight: 600;
        color: #4f46e5;
        margin-bottom: 8px;
        padding: 3px 10px;
        background: #eef2ff;
        border-radius: 6px;
        display: inline-block;
    }

    .branch-employees i {
        font-size: 11px;
    }

    .branch-stats {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 4px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
    }

    .stat-mini {
        text-align: center;
        padding: 4px 2px;
        border-radius: 6px;
        background: #f8fafc;
        transition: all 0.3s;
    }

    .stat-mini:hover {
        background: #f1f5f9;
    }

    .stat-mini .number {
        font-size: 14px;
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
    .stat-mini.absent .number { color: #ef4444; }
    .stat-mini.leave .number { color: #f59e0b; }
    .stat-mini.holiday .number { color: #8b5cf6; }
    .stat-mini.weekoff .number { color: #10b981; }

    .mt-3 {
        margin-top: 8px !important;
    }

    .badge {
        font-size: 10px !important;
        padding: 3px 10px !important;
        border-radius: 12px !important;
    }

    .badge-success { background: #d1fae5 !important; color: #065f46 !important; }
    .badge-warning { background: #fef3c7 !important; color: #92400e !important; }
    .badge-danger { background: #fee2e2 !important; color: #991b1b !important; }

    .summary-footer {
        margin-top: 12px;
        padding: 10px 16px;
        background: white;
        border-radius: 8px;
        border: 1px solid #eef2f6;
    }

    .summary-footer .small {
        font-size: 10px !important;
        color: #64748b;
    }

    .summary-footer .fw-bold {
        font-size: 14px !important;
    }

    .summary-footer .text-primary { color: #4f46e5 !important; }
    .summary-footer .text-success { color: #10b981 !important; }
    .summary-footer .text-danger { color: #ef4444 !important; }
    .summary-footer .text-warning { color: #f59e0b !important; }
    .summary-footer .text-info { color: #8b5cf6 !important; }

    .alert {
        padding: 20px !important;
        border-radius: 8px !important;
    }

    .alert i {
        font-size: 32px !important;
        display: block !important;
        margin-bottom: 8px !important;
    }

    .alert h5 {
        font-size: 16px !important;
        margin-bottom: 4px !important;
    }

    .alert p {
        font-size: 13px !important;
        margin-bottom: 0 !important;
    }

    .page-header {
        margin-bottom: 12px !important;
    }

    .breadcrumb {
        padding: 6px 0 !important;
        margin-bottom: 0 !important;
        font-size: 12px !important;
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
        .stats-card { padding: 8px 12px; }
        .stats-amount-main { font-size: 16px; }
        .stat-mini .number { font-size: 13px; }
        .main-content { padding: 10px 12px !important; }
        .branch-card-item { padding: 12px; }
        .summary-footer .row { gap: 6px; }
    }

    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
        .stats-icon-wrapper { width: 28px; height: 28px; }
        .stats-icon-wrapper i { font-size: 14px !important; }
        .stats-amount-main { font-size: 14px; }
        .stats-label { font-size: 8px; }
        .stats-card { padding: 6px 10px; gap: 6px; }
        .branch-grid { grid-template-columns: 1fr; }
        .branch-card-item { padding: 10px; }
        .branch-name { font-size: 12px; }
        .stat-mini .number { font-size: 12px; }
        .date-indicator { font-size: 11px; }
        .filter-item.date-picker input { font-size: 11px; height: 28px; }
        .reset-btn { font-size: 11px; height: 28px; }
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
                        <span class="badge badge-success" style="font-size: 9px; padding: 2px 6px; margin-left: 4px;">Today</span>
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
            <i class="feather-building" style="font-size: 32px; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
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
                        <span style="font-size: 10px; color: #4f46e5;">
                            View <i class="feather-arrow-right" style="font-size: 11px;"></i>
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