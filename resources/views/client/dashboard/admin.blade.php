@extends('client.layout.master')

@section('style')
    <style>
        /* ============================================
           DESIGN TOKENS — Modern + Classic palette
           ============================================ */
        :root {
            --d-bg: #f4f6fb;
            --d-card: #ffffff;
            --d-border: #eaeef5;
            --d-border-strong: #dfe5f0;
            --d-text: #1a2236;
            --d-text-soft: #6b7385;
            --d-text-muted: #9aa1b1;
            --d-radius: 14px;
            --d-radius-sm: 10px;
            --d-shadow: 0 1px 2px rgba(20, 30, 60, .04), 0 2px 8px rgba(20, 30, 60, .04);
            --d-shadow-hover: 0 6px 22px rgba(30, 50, 110, .10);
            --d-primary: #4f46e5;
            --d-primary-2: #6366f1;
            --d-success: #10b981;
            --d-warning: #f59e0b;
            --d-danger: #ef4444;
            --d-info: #0ea5e9;
            --d-purple: #8b5cf6;
        }

        /* .main-content { padding: 20px !important; background: var(--d-bg); } */

        th { font-size: 10.5px !important; font-weight: 600 !important; letter-spacing: .2px; text-transform: uppercase; color: var(--d-text-muted) !important; }
        td { font-size: 12px !important; color: var(--d-text); }

        h5.card-title { font-size: 14px; font-weight: 700; color: var(--d-text); letter-spacing: -.2px; }
        .section-title { font-size: 13px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: var(--d-text-soft); margin: 0; display: flex; align-items: center; gap: 8px; }
        .section-title::before { content: ''; width: 4px; height: 16px; border-radius: 4px; background: linear-gradient(180deg, var(--d-primary), var(--d-primary-2)); display: inline-block; }

        /* ============================================
           CARD SYSTEM
           ============================================ */
        .card {
            background: var(--d-card);
            border: 1px solid var(--d-border);
            border-radius: var(--d-radius);
            box-shadow: var(--d-shadow);
            margin-bottom: 0% !important;
        }
        .card .card-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--d-border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card .card-body { padding: 16px; }
        .card .card-footer { padding: 10px 16px; border-top: 1px solid var(--d-border); background: #fafbfe; }

        .stat-card {
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            border: 1px solid var(--d-border);
        }
        .stat-card .card-body { padding: 16px; }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--d-shadow-hover);
            border-color: var(--d-border-strong);
        }

        /* ============================================
           ICON CHIPS
           ============================================ */
        .avatar-text, .mini-stat-icon {
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .avatar-text.avatar-lg {
            width: 46px; height: 46px; border-radius: 13px; font-size: 18px;
        }
        .mini-stat-icon {
            width: 40px; height: 40px; border-radius: 11px; font-size: 15px;
        }

        .bg-soft-primary { background: rgba(79, 70, 229, .10); color: var(--d-primary); }
        .bg-soft-success { background: rgba(16, 185, 129, .12); color: var(--d-success); }
        .bg-soft-danger  { background: rgba(239, 68, 68, .12);  color: var(--d-danger); }
        .bg-soft-warning { background: rgba(245, 158, 11, .14); color: var(--d-warning); }
        .bg-soft-info    { background: rgba(14, 165, 233, .12); color: var(--d-info); }
        .bg-soft-purple  { background: rgba(139, 92, 246, .12); color: var(--d-purple); }
        .bg-soft-dark    { background: rgba(26, 34, 54, .08);   color: var(--d-text); }

        /* numbers + labels */
        .stat-value { font-size: 24px; font-weight: 800; color: var(--d-text); line-height: 1.1; letter-spacing: -.5px; margin: 0; }
        .stat-value-sm { font-size: 17px; font-weight: 800; color: var(--d-text); line-height: 1.1; margin: 0; }
        .stat-label { font-size: 11.5px; font-weight: 600; color: var(--d-text-soft); }
        .stat-sub { font-size: 10.5px; color: var(--d-text-muted); }

        /* pill badges */
        .pill { padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 700; letter-spacing: .2px; }
        .pill-success { background: rgba(16,185,129,.12); color: #0f9d6e; }
        .pill-danger  { background: rgba(239,68,68,.12);  color: #dc2f2f; }
        .pill-warning { background: rgba(245,158,11,.14); color: #d98307; }
        .pill-info    { background: rgba(14,165,233,.12); color: #0c87c4; }

        /* ============================================
           PROGRESS BARS
           ============================================ */
        .progress { background: #eef1f7; border-radius: 999px; overflow: hidden; }
        .progress-sm { height: 5px; }
        .progress-bar { border-radius: 999px; }

        /* ============================================
           WELCOME BANNER
           ============================================ */
        .welcome-banner {
            background: linear-gradient(120deg, #4f46e5 0%, #6d28d9 55%, #7c3aed 100%);
            border-radius: var(--d-radius);
            position: relative;
            overflow: hidden;
            border: none;
            box-shadow: 0 8px 24px rgba(79, 70, 229, .22);
        }
        .welcome-banner::after {
            content: '';
            position: absolute; right: -40px; top: -60px;
            width: 240px; height: 240px; border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .welcome-banner::before {
            content: '';
            position: absolute; right: 90px; bottom: -90px;
            width: 180px; height: 180px; border-radius: 50%;
            background: rgba(255,255,255,.06);
        }
        .welcome-banner .card-body { padding: 22px 24px; position: relative; z-index: 2; }

        /* ============================================
           EXPENSE OVERVIEW (top summary tiles)
           ============================================ */
        .stats-card {
            background: var(--d-card);
            border-radius: var(--d-radius);
            padding: 16px;
            display: flex; align-items: center; gap: 14px;
            transition: transform .18s ease, box-shadow .18s ease;
            border: 1px solid var(--d-border);
            box-shadow: var(--d-shadow);
            position: relative; overflow: hidden;
        }
        .stats-card:hover { transform: translateY(-3px); box-shadow: var(--d-shadow-hover); }
        .stats-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .balance-card::before  { background: linear-gradient(180deg, #10b981, #34d399); }
        .total-card::before    { background: linear-gradient(180deg, #4f46e5, #818cf8); }
        .approved-card::before { background: linear-gradient(180deg, #0ea5e9, #38bdf8); }
        .stats-amount-main { font-size: 20px; font-weight: 800; color: var(--d-text); letter-spacing: -.4px; }
        .stats-label { font-size: 11.5px; color: var(--d-text-soft); font-weight: 600; }

        /* expense breakdown rows */
        .exp-card .breakdown-row { padding: 4px 0; }
        .exp-card .breakdown-row + .breakdown-row { border-top: 1px dashed var(--d-border); }
        .exp-card a { transition: color .15s ease; }

        /* ============================================
           TABLES
           ============================================ */
        .table { margin: 0; }
        .table thead th { background: #fafbfe; padding: 9px 14px; border-bottom: 1px solid var(--d-border); }
        .table tbody td { padding: 10px 14px; vertical-align: middle; border-bottom: 1px solid var(--d-border); }
        .table tbody tr { cursor: pointer; transition: background .14s ease; }
        .table tbody tr:hover { background: #f6f8fd; }
        .table tbody tr:last-child td { border-bottom: none; }

        /* avatar in tables */
        .tbl-avatar { width: 32px; height: 32px; border-radius: 9px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .tbl-avatar img { width: 32px; height: 32px; object-fit: cover; border-radius: 9px; }

        /* ============================================
           STATUS BADGES
           ============================================ */
        .badge { padding: 3px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
        .badge-active, .badge-approved, .badge-completed { background: rgba(16,185,129,.12); color: #0f9d6e; }
        .badge-ongoing, .badge-in-progress { background: rgba(79,70,229,.12); color: var(--d-primary); }
        .badge-pending, .badge-on-hold { background: rgba(245,158,11,.14); color: #d98307; }
        .badge-rejected, .badge-cancelled, .badge-on-danger { background: rgba(239,68,68,.12); color: #dc2f2f; }
        .badge-secondary { background: #eef1f7; color: var(--d-text-soft); }
        .bg-soft-success.badge, .bg-soft-warning.badge, .bg-soft-danger.badge { font-weight: 700; }

        /* ============================================
           RANK BADGE
           ============================================ */
        .rank-badge { width: 26px; height: 26px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; }
        .rank-1 { background: linear-gradient(135deg,#fde68a,#fbbf24); color: #92590b; }
        .rank-2 { background: linear-gradient(135deg,#e5e7eb,#cbd5e1); color: #475569; }
        .rank-3 { background: linear-gradient(135deg,#fed7aa,#fb923c); color: #9a3412; }

        /* ============================================
           INFO / EVENT CARDS
           ============================================ */
        .info-card { background: #f8f9fd; border: 1px solid var(--d-border); border-radius: var(--d-radius-sm); padding: 11px 12px; transition: all .16s ease; cursor: pointer; }
        .info-card:hover { background: #fff; box-shadow: var(--d-shadow-hover); transform: translateX(2px); }

        .wd-7 { width: 8px; } .ht-7 { height: 8px; }
        .wd-50 { width: 46px; } .ht-50 { height: 46px; }

        .deadline-urgent  { color: #dc2f2f; font-weight: 700; }
        .deadline-warning { color: #d98307; font-weight: 700; }
        .deadline-normal  { color: #0f9d6e; font-weight: 700; }

        /* compact gutters */
        .row.g-compact { --bs-gutter-x: 14px; --bs-gutter-y: 14px; }

        a { text-decoration: none; }
        .text-dark { color: var(--d-text) !important; }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Admin Dashboard</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Admin Dashboard</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Welcome Banner -->
        <div class="row g-compact mb-3">
            <div class="col-12">
                <div class="card welcome-banner text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h4 class="text-white mb-1 fw-bold">Welcome back, {{ Auth::user()->name }}!</h4>
                                <p class="text-white-50 mb-0 fs-12">{{ \Carbon\Carbon::now()->format('l, d F Y') }} •
                                    {{ \Carbon\Carbon::now()->format('h:i A') }}</p>
                            </div>
                            <div class="text-end">
                                <h2 class="text-white mb-0 fw-bold" style="font-size:30px;">{{ $total_employees ?? 0 }}</h2>
                                <p class="text-white-50 mb-0 fs-12">Total Employees</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Statistics Cards -->
        <div class="row g-compact mb-3">
            <div class="col-xxl-3 col-md-6">
                <div class="card stat-card stretch-full">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="avatar-text avatar-lg bg-soft-primary">
                                <i class="feather-users"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="stat-label">Employees</span>
                                    <span class="pill pill-success">{{ ($total_employees ?? 0) > 0 ? round((($active_employees ?? 0) / $total_employees) * 100) : 0 }}% Active</span>
                                </div>
                                <a href="{{ route('employee.index') }}">
                                    <h2 class="stat-value">{{ $total_employees ?? 0 }}</h2>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stat-card stretch-full">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="avatar-text avatar-lg bg-soft-success">
                                <i class="feather-check-circle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="stat-label">Present</span>
                                    <span class="pill pill-success">{{ ($total_employees ?? 0) > 0 ? round((($present_today ?? 0) / $total_employees) * 100) : 0 }}% Rate</span>
                                </div>
                                <a href="{{ route('team.index', ['status' => 'present']) }}">
                                    <h2 class="stat-value">{{ $present_today ?? 0 }}</h2>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stat-card stretch-full">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="avatar-text avatar-lg bg-soft-danger">
                                <i class="feather-x-circle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="stat-label">Absent</span>
                                    <span class="pill pill-danger">{{ ($total_employees ?? 0) > 0 ? round((($absent_today ?? 0) / ($total_employees ?? 1)) * 100) : 0 }}% Absent</span>
                                </div>
                                <a href="{{ route('team.index', ['status' => 'absent']) }}">
                                    <h2 class="stat-value">{{ $absent_today ?? 0 }}</h2>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-md-6">
                <div class="card stat-card stretch-full">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="avatar-text avatar-lg bg-soft-warning">
                                <i class="feather-clock"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="stat-label">On Leave</span>
                                    <span class="pill pill-warning">{{ ($total_employees ?? 0) > 0 ? round((($on_leave_today ?? 0) / ($total_employees ?? 1)) * 100) : 0 }}% Away</span>
                                </div>
                                <a href="{{ route('team.index', ['status' => 'on_leave']) }}">
                                    <h2 class="stat-value">{{ $on_leave_today ?? 0 }}</h2>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Statistics -->
        <div class="d-flex align-items-center mb-2"><h6 class="section-title">Task Overview</h6></div>
        <div class="row g-compact mb-3">
            @php
                $taskTiles = [
                    ['label'=>'Total Tasks','val'=>$total_tasks ?? 0,'icon'=>'feather-list','tone'=>'info','route'=>route('task.assigned-by-me'),'sub'=>'This Month','subval'=>$monthly_tasks ?? 0,'bar'=>$total_tasks > 0 ? round((($monthly_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-info'],
                    ['label'=>'Pending','val'=>$pending_tasks ?? 0,'icon'=>'feather-clock','tone'=>'warning','route'=>route('task.assigned-by-me',['status'=>'pending']),'sub'=>'Overdue','subval'=>$overdue_tasks ?? 0,'bar'=>$total_tasks > 0 ? round((($pending_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-warning'],
                    ['label'=>'In Progress','val'=>$in_progress_tasks ?? 0,'icon'=>'feather-play-circle','tone'=>'primary','route'=>route('task.assigned-by-me',['status'=>'in_progress']),'sub'=>'On Track','subval'=>(($total_tasks ?? 0)>0 ? round((($in_progress_tasks ?? 0)/$total_tasks)*100):0).'%','bar'=>($total_tasks ?? 0)>0 ? round((($in_progress_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-primary'],
                    ['label'=>'Completed','val'=>$completed_tasks ?? 0,'icon'=>'feather-check-circle','tone'=>'success','route'=>route('task.assigned-by-me',['status'=>'completed']),'sub'=>'Rate','subval'=>($total_tasks > 0 ? round((($completed_tasks ?? 0)/$total_tasks)*100):0).'%','bar'=>$total_tasks > 0 ? round((($completed_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-success'],
                    ['label'=>'Approved','val'=>$approved_tasks ?? 0,'icon'=>'feather-thumbs-up','tone'=>'success','route'=>route('task.assigned-by-me',['status'=>'approved']),'sub'=>'This Month','subval'=>$approved_tasks ?? 0,'bar'=>$total_tasks > 0 ? round((($approved_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-success'],
                    ['label'=>'Rejected','val'=>$rejected_tasks ?? 0,'icon'=>'feather-thumbs-down','tone'=>'danger','route'=>route('task.assigned-by-me',['status'=>'rejected']),'sub'=>'This Month','subval'=>$rejected_tasks ?? 0,'bar'=>$total_tasks > 0 ? round((($rejected_tasks ?? 0)/$total_tasks)*100):0,'barcls'=>'bg-danger'],
                ];
            @endphp
            @foreach ($taskTiles as $t)
                <div class="col-xxl-2 col-lg-4 col-md-6">
                    <div class="card stat-card stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="mini-stat-icon bg-soft-{{ $t['tone'] }}">
                                    <i class="{{ $t['icon'] }}"></i>
                                </div>
                                <div>
                                    <span class="stat-label d-block">{{ $t['label'] }}</span>
                                    <a href="{{ $t['route'] }}"><h3 class="stat-value-sm">{{ $t['val'] }}</h3></a>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="stat-sub">{{ $t['sub'] }}</span>
                                <span class="stat-sub fw-bold text-{{ $t['tone'] }}">{{ $t['subval'] }}</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar {{ $t['barcls'] }}" style="width: {{ $t['bar'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Expense Overview -->
        <div class="d-flex align-items-center mb-2"><h6 class="section-title">Expense Overview</h6></div>
        <div class="row g-compact mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="stats-card balance-card">
                    <div class="mini-stat-icon bg-soft-success"><i class="fas fa-wallet"></i></div>
                    <div>
                        <div class="stats-amount-main">₹{{ number_format($currentBalance ?? 0, 2) }}</div>
                        <div class="stats-label">Current Balance</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card total-card">
                    <div class="mini-stat-icon bg-soft-info"><i class="fas fa-arrow-up"></i></div>
                    <div>
                        <div class="stats-amount-main">₹{{ number_format($totalAdvanceTaken ?? 0, 2) }}</div>
                        <div class="stats-label">Total Advance</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card approved-card">
                    <div class="mini-stat-icon bg-soft-warning"><i class="fas fa-arrow-down"></i></div>
                    <div>
                        <div class="stats-amount-main">₹{{ number_format($totalSettlementDone ?? 0, 2) }}</div>
                        <div class="stats-label">Total Settlement</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card approved-card">
                    <div class="mini-stat-icon bg-soft-primary"><i class="fas fa-exchange-alt"></i></div>
                    <div>
                        <div class="stats-amount-main">₹{{ number_format($totalReimbursementDone ?? 0, 2) }}</div>
                        <div class="stats-label">Total Reimbursement</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Statistics with breakdown -->
        <div class="row g-compact mb-3">
            @php
                $expenseCards = [
                    ['title'=>'Advance','amount'=>$totalAdvanceAmount ?? 0,'count'=>$totalAdvanceCount ?? 0,'icon'=>'fa-arrow-up','tone'=>'primary','type'=>'advance',
                        'pending'=>['c'=>$pendingAdvanceCount ?? 0,'a'=>$pendingAdvanceAmount ?? 0],'approved'=>['c'=>$approvedAdvanceCount ?? 0,'a'=>$approvedAdvanceAmount ?? 0],
                        'completed'=>['c'=>$completedAdvanceCount ?? 0,'a'=>$completedAdvanceAmount ?? 0],'cancelled'=>['c'=>$cancelledAdvanceCount ?? 0,'a'=>$cancelledAdvanceAmount ?? 0]],
                    ['title'=>'Settlement','amount'=>$totalSettlementAmount ?? 0,'count'=>$totalSettlementCount ?? 0,'icon'=>'fa-arrow-down','tone'=>'success','type'=>'settlement',
                        'pending'=>['c'=>$pendingSettlementCount ?? 0,'a'=>$pendingSettlementAmount ?? 0],'approved'=>['c'=>$approvedSettlementCount ?? 0,'a'=>$approvedSettlementAmount ?? 0],
                        'completed'=>['c'=>$completedSettlementCount ?? 0,'a'=>$completedSettlementAmount ?? 0],'cancelled'=>['c'=>$cancelledSettlementCount ?? 0,'a'=>$cancelledSettlementAmount ?? 0]],
                    ['title'=>'Reimbursement','amount'=>$totalReimbursementAmount ?? 0,'count'=>$totalReimbursementCount ?? 0,'icon'=>'fa-exchange-alt','tone'=>'info','type'=>'reimbursement',
                        'pending'=>['c'=>$pendingReimbursementCount ?? 0,'a'=>$pendingReimbursementAmount ?? 0],'approved'=>['c'=>$approvedReimbursementCount ?? 0,'a'=>$approvedReimbursementAmount ?? 0],
                        'completed'=>['c'=>$completedReimbursementCount ?? 0,'a'=>$completedReimbursementAmount ?? 0],'cancelled'=>['c'=>$cancelledReimbursementCount ?? 0,'a'=>$cancelledReimbursementAmount ?? 0]],
                    ['title'=>'Total Expenses','amount'=>$totalAmount ?? 0,'count'=>$totalExpenses ?? 0,'icon'=>'fa-chart-pie','tone'=>'dark','type'=>null,
                        'pending'=>['c'=>$pendingCount ?? 0,'a'=>$pendingAmount ?? 0],'approved'=>['c'=>$approvedCount ?? 0,'a'=>$approvedAmount ?? 0],
                        'completed'=>['c'=>$completedCount ?? 0,'a'=>$completedAmount ?? 0],'cancelled'=>['c'=>$cancelledCount ?? 0,'a'=>$cancelledAmount ?? 0]],
                ];
            @endphp
            @foreach ($expenseCards as $ec)
                @php
                    $baseParams = request()->except(['page']);
                    $allParams = $ec['type'] ? array_merge($baseParams, ['requirement_type' => $ec['type']]) : $baseParams;
                    $mk = function($status) use ($baseParams, $ec) {
                        $p = $ec['type'] ? array_merge($baseParams, ['requirement_type' => $ec['type'], 'status' => $status]) : array_merge($baseParams, ['status' => $status]);
                        return route('expense.view-all', $p);
                    };
                @endphp
                <div class="col-xxl-3 col-md-6">
                    <div class="card stat-card exp-card stretch-full">
                        <div class="card-body">
                            <a href="{{ route('expense.view-all', $allParams) }}" class="d-block mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mini-stat-icon bg-soft-{{ $ec['tone'] }}"><i class="fas {{ $ec['icon'] }}"></i></div>
                                    <div>
                                        <span class="stat-label d-block">{{ $ec['title'] }}</span>
                                        <h6 class="stat-value-sm">₹{{ number_format($ec['amount'], 2) }}</h6>
                                        <span class="stat-sub">{{ $ec['count'] }} total requests</span>
                                    </div>
                                </div>
                            </a>
                            <div class="border-top pt-2">
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Pending</span>
                                    <a href="{{ $mk('pending') }}"><span class="fw-bold text-warning fs-12">{{ $ec['pending']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['pending']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Approved</span>
                                    <a href="{{ $mk('approved') }}"><span class="fw-bold text-success fs-12">{{ $ec['approved']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['approved']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Completed</span>
                                    <a href="{{ $mk('complete') }}"><span class="fw-bold text-info fs-12">{{ $ec['completed']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['completed']['a'], 2) }})</span></a>
                                </div>
                                <div class="breakdown-row d-flex justify-content-between align-items-center">
                                    <span class="stat-sub">Cancelled</span>
                                    <a href="{{ $mk('cancelled') }}"><span class="fw-bold text-danger fs-12">{{ $ec['cancelled']['c'] }}</span> <span class="stat-sub">(₹{{ number_format($ec['cancelled']['a'], 2) }})</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Project Statistics -->
        <div class="d-flex align-items-center mb-2"><h6 class="section-title">Project Overview</h6></div>
        <div class="row g-compact mb-3">
            @php
                $projTiles = [
                    ['label'=>'Total','val'=>$total_projects ?? 0,'icon'=>'feather-briefcase','tone'=>'primary','route'=>route('project.index')],
                    ['label'=>'Pending','val'=>$active_projects ?? 0,'icon'=>'feather-clock','tone'=>'warning','route'=>route('project.index')],
                    ['label'=>'Ongoing','val'=>$ongoing_projects ?? 0,'icon'=>'feather-play-circle','tone'=>'info','route'=>route('project.index',['status'=>'ongoing'])],
                    ['label'=>'Completed','val'=>$completed_projects ?? 0,'icon'=>'feather-check-square','tone'=>'success','route'=>route('project.index',['status'=>'completed'])],
                    ['label'=>'On Hold','val'=>$on_hold_projects ?? 0,'icon'=>'feather-pause-circle','tone'=>'purple','route'=>route('project.index',['status'=>'on_hold'])],
                    ['label'=>'Cancelled','val'=>$cancelled_projects ?? 0,'icon'=>'feather-slash','tone'=>'danger','route'=>route('project.index',['status'=>'cancelled'])],
                ];
            @endphp
            @foreach ($projTiles as $p)
                <div class="col-xxl-2 col-lg-4 col-md-6">
                    <div class="card stat-card stretch-full">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="mini-stat-icon bg-soft-{{ $p['tone'] }}"><i class="{{ $p['icon'] }}"></i></div>
                                <div>
                                    <span class="stat-label d-block">{{ $p['label'] }}</span>
                                    <a href="{{ $p['route'] }}"><h3 class="stat-value-sm">{{ $p['val'] }}</h3></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Charts Row -->
        <div class="row g-compact mb-3">
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Weekly Attendance Overview</h5>
                    </div>
                    <div class="card-body p-2">
                        <div id="attendance-chart" style="height: 290px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Department Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div id="department-donut-chart" style="height: 190px;"></div>
                        <div class="mt-2">
                            @php $totalDeptEmployees = $department_distribution->sum('users_count'); @endphp
                            @foreach ($department_distribution as $dept)
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wd-7 ht-7 rounded-circle d-inline-block" style="background-color: {{ $colorPalette[$loop->index % count($colorPalette)] }}"></span>
                                        <span class="fs-12">{{ $dept->name }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fs-12 fw-bold">{{ $dept->users_count }}</span>
                                        <span class="stat-sub" style="width: 40px;">{{ round(($dept->users_count / $totalDeptEmployees) * 100) }}%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Project Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div id="project-status-chart" style="height: 190px;"></div>
                        <div class="mt-2">
                            @php
                                $statusColors = ['active'=>'#4f46e5','ongoing'=>'#6366f1','completed'=>'#10b981','on_hold'=>'#f59e0b','cancelled'=>'#ef4444'];
                            @endphp
                            @foreach ($projects_by_status ?? [] as $status => $count)
                                @if ($count > 0)
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="wd-7 ht-7 rounded-circle d-inline-block" style="background-color: {{ $statusColors[$status] ?? '#6c757d' }}"></span>
                                            <span class="fs-12">{{ ucfirst($status) }}</span>
                                        </div>
                                        <span class="fs-12 fw-bold">{{ $count }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deadlines / Recent / Regularization Row -->
        <div class="row g-compact mb-3">
            <!-- Upcoming Deadlines -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Upcoming Deadlines</h5>
                        <span class="badge bg-soft-warning">Next 30 Days</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Project</th><th>Deadline</th><th>Days Left</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse($upcoming_deadlines ?? [] as $project)
                                        @php
                                            $deadlineDate = \Carbon\Carbon::parse($project->deadline_date);
                                            $daysLeft = (int) \Carbon\Carbon::now()->diffInDays($deadlineDate, false);
                                            $deadlineClass = $daysLeft <= 7 ? 'deadline-urgent' : ($daysLeft <= 15 ? 'deadline-warning' : 'deadline-normal');
                                        @endphp
                                        <tr onclick="window.location.href='{{ route('project.view-details', ['id' => encrypt($project->id)]) }}'">
                                            <td><span class="d-block fw-bold">{{ $project->name }}</span><small class="text-muted">{{ $project->project_code }}</small></td>
                                            <td>{{ \Carbon\Carbon::parse($project->deadline_date)->format('d M Y') }}</td>
                                            <td class="{{ $deadlineClass }}">{{ $daysLeft }} days</td>
                                            <td><span class="badge badge-ongoing">Ongoing</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No upcoming deadlines</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Recently Added Projects</h5>
                        <span class="badge bg-soft-success">Last 5</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Project</th><th>Start → Deadline</th><th>Status</th></tr></thead>
                                <tbody>
                                    @forelse($recent_projects ?? [] as $project)
                                        <tr onclick="window.location.href='{{ route('project.view-details', ['id' => encrypt($project->id)]) }}'">
                                            <td><span class="d-block fw-bold">{{ $project->name }}</span><small class="text-muted">{{ $project->project_code }}</small></td>
                                            <td>{{ \Carbon\Carbon::parse($project->start_date)->format('d M Y') }} → {{ \Carbon\Carbon::parse($project->deadline_date)->format('d M Y') }}</td>
                                            <td>
                                                @switch($project->status)
                                                    @case('active')<span class="badge badge-active">Active</span>@break
                                                    @case('ongoing')<span class="badge badge-ongoing">Ongoing</span>@break
                                                    @case('completed')<span class="badge badge-completed">Completed</span>@break
                                                    @case('on_hold')<span class="badge badge-on-hold">On Hold</span>@break
                                                    @case('cancelled')<span class="badge badge-on-danger">Cancelled</span>@break
                                                    @default<span class="badge badge-secondary">{{ $project->status }}</span>
                                                @endswitch
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center py-3 text-muted">No recent projects</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('project.index') }}" class="fs-11 fw-bold text-uppercase text-center d-block">View All Projects</a>
                    </div>
                </div>
            </div>

            <!-- Most Regularization Request -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Most Regularization Requests</h5>
                        <span class="badge bg-soft-success">This Month</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Total</th><th>Approved</th></tr></thead>
                                <tbody>
                                    @forelse($most_regularization_requests ?? [] as $employee)
                                        <tr onclick="window.location='{{ route('attendance-regularization.manage', ['user_id' => $employee->id]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-primary">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ $employee->profile_image }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $employee->name }} <small class="text-muted">({{ $employee->employee_id }})</small></span>
                                                        <span class="stat-sub">{{ $employee->email }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold">{{ $employee->total_requests }}</span></td>
                                            <td><span class="text-success fw-bold">{{ $employee->approved_requests }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center py-4 text-muted">No regularization requests found</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Least Tasks / Recent Joinings / Events Row -->
        <div class="row g-compact mb-2">
            <!-- Least Tasks Assigned -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Least Tasks Assigned</h5>
                        <span class="badge bg-soft-warning">Need Attention</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr>
                                    {{-- <th>Rank</th> --}}
                                    <th>Employee</th><th>Total</th><th>Completed</th></tr></thead>
                                <tbody>
                                    @forelse($least_tasks_employees ?? [] as $index => $employee)
                                        <tr onclick="window.location.href='{{ route('team.member-detail', ['id' => encrypt($employee->id)]) }}'">
                                            {{-- <td><div class="rank-badge {{ $index == 0 ? 'rank-1' : ($index == 1 ? 'rank-2' : 'rank-3') }}">{{ $index + 1 }}</div></td> --}}
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-warning">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ $employee->profile_image }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <span class="d-block fw-bold">{{ $employee->name }} <small class="text-muted">({{ $employee->employee_id }})</small></span>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold">{{ $employee->total_tasks }}</span></td>
                                            <td><span class="fw-bold">{{ $employee->completed_tasks }}</span> <span class="stat-sub">({{ round(($employee->completed_tasks / max($employee->total_tasks, 1)) * 100) }}%)</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No data available</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Joinings -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Recent Joinings</h5>
                        <span class="badge bg-soft-success">This Month: {{ $monthly_joinings ?? 0 }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Joining Date</th></tr></thead>
                                <tbody>
                                    @forelse($recent_joinings ?? [] as $employee)
                                        <tr onclick="window.location.href='{{ route('employee.show', ['id' => encrypt($employee->id)]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-primary">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ $employee->profile_image }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $employee->name }} <small class="text-muted">({{ $employee->employee_id }})</small></span>
                                                        <span class="stat-sub">{{ $employee->email }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold">{{ \Carbon\Carbon::parse($employee->created_at)->format('d M Y') }}</span>
                                                <span class="stat-sub d-block">{{ \Carbon\Carbon::parse($employee->created_at)->diffForHumans() }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">No recent joinings</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('employee.index') }}" class="fs-11 fw-bold text-uppercase text-center d-block">View All Employees</a>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="col-xxl-4">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title">Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="mb-3 fs-12 fw-bold">🎉 Holidays</h6>
                                @forelse($upcoming_holidays ?? [] as $holiday)
                                    <div class="info-card mb-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-primary text-primary lh-1 d-flex align-items-center justify-content-center flex-column rounded-2">
                                                <span class="fs-18 fw-bold mb-1 d-block">{{ \Carbon\Carbon::parse($holiday->start_date)->format('d') }}</span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">{{ \Carbon\Carbon::parse($holiday->start_date)->format('M') }}</span>
                                            </div>
                                            <div>
                                                <span class="fw-bold mb-1 d-block fs-12">{{ $holiday->name }}</span>
                                                <span class="stat-sub">{{ \Carbon\Carbon::parse($holiday->start_date)->format('l, d M Y') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted text-center py-3 fs-12">No upcoming holidays</p>
                                @endforelse
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3 fs-12 fw-bold">🎂 Birthdays</h6>
                                @forelse($upcoming_birthdays ?? [] as $birthday)
                                    <div class="info-card mb-2" onclick="window.location='{{ route('employee.show', ['id' => encrypt($birthday['user']->id)]) }}'">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="tbl-avatar bg-soft-success" style="width:40px;height:40px;">
                                                @if ($birthday['user']->profile_image)
                                                    <img src="{{ $birthday['user']->profile_image }}" style="width:40px;height:40px;">
                                                @else
                                                    {{ strtoupper(substr($birthday['user']->name, 0, 2)) }}
                                                @endif
                                            </div>
                                            <div>
                                                <span class="d-block fw-bold fs-12">{{ $birthday['user']->name }}</span>
                                                <span class="stat-sub">{{ $birthday['date']->format('d M') }} (in {{ $birthday['days_until'] }} days)</span>
                                                <span class="fs-11 text-primary d-block">Turning {{ $birthday['age'] ?? '' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted text-center py-3 fs-12">No upcoming birthdays</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- [ Main Content ] end -->
@endsection

@section('script-area')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.2/dist/apexcharts.min.js"></script>
    <script>
        $(document).ready(function() {
            const colorPalette = ['#4f46e5', '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#38bdf8', '#34d399'];
            const statusColors = {
                'active': '#4f46e5', 'ongoing': '#6366f1', 'completed': '#10b981', 'on_hold': '#f59e0b', 'cancelled': '#ef4444'
            };

            // Attendance Chart
           
            @if (isset($attendance_chart_data))
                var attendanceElement = document.getElementById("attendance-chart");
                if (attendanceElement) {
                    attendanceElement.innerHTML = '';
                    var presentData = [], absentData = [], days = [];
                    @if (isset($attendance_chart_data['present']) && is_array($attendance_chart_data['present']))
                        presentData = @json($attendance_chart_data['present']);
                    @else
                        presentData = [0,0,0,0,0,0,0];
                    @endif
                    @if (isset($attendance_chart_data['absent']) && is_array($attendance_chart_data['absent']))
                        absentData = @json($attendance_chart_data['absent']);
                    @else
                        absentData = [0,0,0,0,0,0,0];
                    @endif
                    @if (isset($attendance_chart_data['days']) && is_array($attendance_chart_data['days']))
                        days = @json($attendance_chart_data['days']);
                    @else
                        days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                    @endif
                    
                    // ✅ Ensure data length is 7
                    while (presentData.length < 7) presentData.push(0);
                    while (absentData.length < 7) absentData.push(0);
                    presentData = presentData.slice(0, 7);
                    absentData = absentData.slice(0, 7);
                    days = days.slice(0, 7);
                    
                    // ✅ Highlight today's column
                    var today = new Date();
                    var todayName = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][today.getDay()];
                    var orderedDays = days.map(function(day, index) {
                        // Check if this day matches today
                        var isToday = (day === todayName);
                        return isToday ? day + ' ★' : day;
                    });
            
                    var attendanceOptions = {
                        series: [
                            { name: 'Present', data: presentData },
                            { name: 'Absent', data: absentData }
                        ],
                        chart: {
                            type: 'bar',
                            height: 290,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        colors: ['#4f46e5', '#c7d2fe'],
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '50%',
                                borderRadius: 5
                            }
                        },
                        dataLabels: { enabled: false },
                        stroke: {
                            show: true,
                            width: 3,
                            colors: ['transparent']
                        },
                        xaxis: {
                            categories: orderedDays,
                            axisBorder: { show: false },
                            axisTicks: { show: false },
                            labels: {
                                style: {
                                    fontSize: '11px',
                                    fontWeight: 600
                                }
                            }
                        },
                        yaxis: {
                            title: {
                                text: 'Employees',
                                style: {
                                    fontSize: '11px',
                                    fontWeight: 600
                                }
                            }
                        },
                        fill: { opacity: 1 },
                        tooltip: {
                            y: {
                                formatter: function(val) {
                                    return val + " employees";
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            horizontalAlign: 'right',
                            fontSize: '12px',
                            markers: { radius: 4 }
                        },
                        grid: {
                            borderColor: '#eef1f7',
                            strokeDashArray: 4
                        }
                    };
                    try {
                        new ApexCharts(attendanceElement, attendanceOptions).render();
                    } catch(e) {
                        console.error(e);
                    }
                }
            @endif

            // Department Donut
            @if (isset($department_distribution) && $department_distribution && count($department_distribution) > 0)
                var deptElement = document.getElementById("department-donut-chart");
                if (deptElement) {
                    deptElement.innerHTML = '';
                    var deptNames = [], deptCounts = [];
                    @foreach ($department_distribution as $dept)
                        deptNames.push('{{ $dept->name }}');
                        deptCounts.push({{ $dept->users_count }});
                    @endforeach
                    var departmentOptions = {
                        series: deptCounts,
                        chart: { type: 'donut', height: 190, fontFamily: 'inherit' },
                        labels: deptNames,
                        colors: colorPalette.slice(0, deptCounts.length),
                        legend: { show: false },
                        dataLabels: { enabled: false },
                        stroke: { width: 2, colors: ['#fff'] },
                        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', formatter: (w) => w.globals.seriesTotals.reduce((a,b)=>a+b,0) + " emp" } } } } },
                        tooltip: { y: { formatter: (val) => val + " employees" } }
                    };
                    try { new ApexCharts(deptElement, departmentOptions).render(); } catch(e) { console.error(e); }
                }
            @endif

            // Project Status Pie
            @if (isset($projects_by_status) && count(array_filter($projects_by_status ?? [])) > 0)
                var projectStatusElement = document.getElementById("project-status-chart");
                if (projectStatusElement) {
                    projectStatusElement.innerHTML = '';
                    var statusLabels = [], statusData = [], statusColorArray = [];
                    @foreach ($projects_by_status as $status => $count)
                        @if ($count > 0)
                            statusLabels.push('{{ ucfirst($status) }} ({{ $count }})');
                            statusData.push({{ $count }});
                            statusColorArray.push(statusColors['{{ $status }}'] || '#6c757d');
                        @endif
                    @endforeach
                    var projectStatusOptions = {
                        series: statusData,
                        chart: { type: 'donut', height: 190, toolbar: { show: false }, fontFamily: 'inherit' },
                        labels: statusLabels,
                        colors: statusColorArray,
                        legend: { show: false },
                        dataLabels: { enabled: false },
                        stroke: { width: 2, colors: ['#fff'] },
                        plotOptions: { pie: { donut: { size: '62%' } } },
                        tooltip: { y: { formatter: (val) => val + " projects" } },
                        responsive: [{ breakpoint: 480, options: { chart: { width: 200 } } }]
                    };
                    try { new ApexCharts(projectStatusElement, projectStatusOptions).render(); } catch(e) { console.error(e); }
                }
            @endif
        });
    </script>
@endsection