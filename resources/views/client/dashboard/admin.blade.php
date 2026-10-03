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
            /* All-blue palette — shades only, no other hues, per dashboard request. */
            --d-primary: #0D6EFD;
            --d-primary-2: #0D6EFD;
            --d-success: #3b82f6;
            --d-warning: #60a5fa;
            --d-danger: #0B5ED7;
            --d-info: #0ea5e9;
            --d-purple: #93c5fd;
        }

        /* .main-content { padding: 20px !important; background: var(--d-bg); } */

        th { font-size: 10.5px !important; font-weight: 600 !important; letter-spacing: .2px; text-transform: uppercase; color: var(--d-text-muted) !important; }


        h5.card-title { font-size: 14px; font-weight: 700; color: var(--d-text); letter-spacing: -.2px; }

        /* ============================================
           SECTION HEADERS (Expense / Task / Project Overview)
           ============================================ */
        .section-hdr {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;
            background: #f7f8fb; border-bottom: 1px solid var(--d-border);
            border-radius: 10px 10px 0 0; padding: 7px 12px; margin-bottom: 0;
        }
        .section-hdr .section-hdr-left { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .section-hdr .section-hdr-icon {
            width: 26px; height: 26px; border-radius: 7px; flex: none;
            background: #EFF6FF; color: var(--icon-color, #0D6EFD);
            display: inline-flex; align-items: center; justify-content: center; font-size: 13px;
        }
        .section-hdr .section-hdr-title { font-size: 13px; font-weight: 700; color: var(--d-text); margin: 0; }
        .section-hdr .section-hdr-view {
            font-size: 10.5px; font-weight: 600; color: #0D6EFD; background: #fff;
            border: 1px solid var(--d-border-strong); padding: 3px 11px; border-radius: 999px;
            white-space: nowrap; transition: background .15s ease, color .15s ease;
        }
        .section-hdr .section-hdr-view:hover { background: #0D6EFD; color: #fff; border-color: #0D6EFD; }

        .card .card-header {
            padding: 10px 12px;
            border-bottom: 1px solid var(--d-border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card .card-body { padding: 10px; }
        .card .card-footer { padding: 6px 10px; border-top: 1px solid var(--d-border); background: #fafbfe; }

        .stat-card {
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            border: 1px solid var(--d-border);
        }
        .stat-card .card-body { padding: 10px; }
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
            width: 36px; height: 36px; border-radius: 10px; font-size: 15px;
        }
        .mini-stat-icon {
            width: 32px; height: 32px; border-radius: 9px; font-size: 13px;
        }

        /* One theme color: every icon chip gets the same light-blue background —
           no per-tone variation left. Icon glyphs stay a single blue too.
           !important because a global stylesheet defines .bg-soft-success (etc.)
           with its own !important color that otherwise wins regardless of source order. */
        .bg-soft-primary, .bg-soft-success, .bg-soft-danger, .bg-soft-warning,
        .bg-soft-info, .bg-soft-purple, .bg-soft-dark {
            background: #EFF6FF !important;
            color: var(--icon-color, #0D6EFD) !important;
        }

        /* Bootstrap's own text-* utilities carry their own colors — pin them to the same blue tokens. */
        .text-primary { color: var(--d-primary) !important; }
        .text-success { color: var(--d-success) !important; }
        .text-danger  { color: var(--d-danger) !important; }
        .text-warning { color: var(--d-warning) !important; }
        .text-info    { color: var(--d-info) !important; }

        /* numbers + labels */
        .stat-value { font-size: 18px; font-weight: 800; color: var(--d-text); line-height: 1.1; letter-spacing: -.5px; margin: 0; }
        .stat-value-sm { font-size: 14px; font-weight: 800; color: var(--d-text); line-height: 1.1; margin: 0; }
        .stat-label { font-size: 10px; font-weight: 600; color: var(--d-text-soft); }
        .stat-sub { font-size: 9.5px; color: var(--d-text-muted); }

        /* pill badges */
        .pill { padding: 1px 7px; border-radius: 999px; font-size: 9.5px; font-weight: 700; letter-spacing: .2px; }
        .pill-success { background: rgba(59,130,246,.14);  color: #0B5ED7; }
        .pill-danger  { background: rgba(13, 110, 253,.14);   color: #0D6EFD; }
        .pill-warning { background: rgba(96,165,250,.18);  color: #0D6EFD; }
        .pill-info    { background: rgba(14,165,233,.12);  color: #0c87c4; }

        /* TOP KPI CARDS: .kpi5-* is now centralized in
           resources/views/client/layout/head.blade.php (shared across the
           whole project) — no local copy needed here. */

        /* ============================================
           PROGRESS BARS
           ============================================ */
        .progress { background: #eef1f7; border-radius: 999px; overflow: hidden; }
        .progress-sm { height: 5px; }
        .progress-bar { border-radius: 999px; }

        /* compact chart-row card headers (Weekly Attendance / Department / Project Status / Project Overview) */
        .card-title-sm { font-size: 12px !important; }

        /* top-right "View as table" toggle on chart-row card headers */
        .view-toggle-btn {
            font-size: 10px; font-weight: 600; color: var(--d-primary-2);
            cursor: pointer; white-space: nowrap; user-select: none;
        }
        .view-toggle-btn:hover { text-decoration: underline; }

        /* top-right month filter on the Top 5 Performer card */
        .month-filter {
            font-size: 10.5px; font-weight: 600; color: var(--d-primary);
            background: #fff; border: 1px solid var(--d-border-strong); border-radius: 6px;
            padding: 2px 6px; cursor: pointer;
        }

        /* ============================================
           FIXED-HEIGHT CARDS (Regularization / Top Performer /
           Least Tasks / Calendar row) — same height regardless of
           row count, with the table area scrolling internally.
           ============================================ */
        .fixed-h-card { height: 320px; display: flex; flex-direction: column; }
        .fixed-h-card .card-header { flex: none; }
        .fixed-h-card .card-body { flex: 1; min-height: 0; overflow-y: auto; }

        /* ============================================
           RECENT ANNOUNCEMENTS — stacked rows instead of a table, so a
           long title/description never forces horizontal scroll in a
           narrow col-md-3 card.
           ============================================ */
        .ann-list { padding: 4px 14px; }
        .ann-row { padding: 9px 0; border-bottom: 1px dashed var(--d-border); }
        .ann-row:last-child { border-bottom: none; }
        .ann-title {
            font-size: 12px; font-weight: 700; color: var(--d-text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            min-width: 0; margin-right: 8px;
        }
        .ann-date { flex: none; font-size: 10.5px; font-weight: 600; color: var(--d-text-muted); white-space: nowrap; }
        .ann-desc { display: block; margin-top: 2px; font-size: 10.5px; color: var(--d-text-soft); }

        /* ============================================
           ATTENDANCE CALENDAR (top-right month nav, click a date
           to open Team Members filtered to that date)
           ============================================ */
        .cal-nav { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 600; color: var(--d-text-soft); }
        .cal-nav-btn {
            cursor: pointer; width: 18px; height: 18px; border-radius: 5px; flex: none;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .cal-nav-btn:hover { background: #EFF6FF; color: var(--d-primary); }
        .cal-nav-label { min-width: 62px; text-align: center; white-space: nowrap; }

        /* card-body stretches to fill the fixed-height card, and the day
           grid grows to fill whatever space is left below the weekday row */
        .cal-card-body { display: flex; flex-direction: column; padding: 10px; }
        .cal-weekdays {
            display: grid; grid-template-columns: repeat(7, 1fr); text-align: center;
            font-size: 9.5px; font-weight: 700; color: var(--d-text-muted); margin-bottom: 6px; flex: none;
        }
        .cal-grid {
            display: grid; grid-template-columns: repeat(7, 1fr); grid-auto-rows: 1fr;
            gap: 4px; flex: 1; min-height: 0;
        }
        .cal-day {
            position: relative; display: flex; align-items: center; justify-content: center;
            font-size: 11.5px; font-weight: 600; border-radius: 8px; cursor: pointer;
            color: var(--d-text); background: #f2f6fd; padding: 4px;
        }
        .cal-day:hover { background: #dbeafe; color: var(--d-primary); }
        .cal-day-empty { cursor: default; background: transparent; }
        .cal-day-empty:hover { background: transparent; }
        .cal-day-today { background: var(--d-primary); color: #fff; font-weight: 800; }

        /* holiday dates — dashed outline + small "H" badge, kept within the
           all-blue palette (darker shade) rather than introducing a new hue */
        .cal-day-holiday { background: #dbeafe; color: var(--d-primary); border: 1px dashed var(--d-primary-2); }
        .cal-day-holiday:hover { background: #bfdbfe; }
        .cal-day-holiday.cal-day-today { background: var(--d-primary); color: #fff; border-color: #fff; }
        .cal-day-holiday-badge {
            position: absolute; top: 1px; right: 2px; font-size: 7.5px; font-weight: 800;
            line-height: 1; color: var(--d-primary-2);
        }
        .cal-day-holiday.cal-day-today .cal-day-holiday-badge { color: #fff; }

        /* compact table view swapped in by the toggle */
        .mini-table { width: 100%; font-size: 11px; border-collapse: collapse; }
        .mini-table td { padding: 4px 2px; border-bottom: 1px dashed var(--d-border); color: var(--d-text); }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-table td:last-child { text-align: right; font-weight: 700; color: var(--d-primary); }

        /* ============================================
           PROJECT OVERVIEW — funnel-style progress bars
           (same anatomy as the Super Admin dashboard's Enquiry funnel)
           ============================================ */
        .funnel-list { display: flex; flex-direction: column; gap: 8px; }
        .funnel-row { display: flex; align-items: center; gap: 8px; }
        .funnel-label { flex: 0 0 5.2rem; font-size: 11px; font-weight: 600; color: var(--d-text-soft); }
        .funnel-track { flex: 1; height: 8px; border-radius: 999px; background: #EFF6FF; overflow: hidden; }
        .funnel-fill { background: #60a5fa; height: 100%; border-radius: 999px; transition: width .3s ease; }
        .funnel-count { flex: 0 0 1.8rem; text-align: right; font-size: 11px; font-weight: 700; color: var(--d-primary); }
        /* Bootstrap's own bg-* utilities on progress bars carry their own colors — blue-ify them too. */
        .progress-bar.bg-primary { background-color: #0D6EFD !important; }
        .progress-bar.bg-info    { background-color: #0D6EFD !important; }
        .progress-bar.bg-success { background-color: #3b82f6 !important; }
        .progress-bar.bg-warning { background-color: #60a5fa !important; }
        .progress-bar.bg-danger  { background-color: #93c5fd !important; }

        /* ============================================
           WELCOME BANNER
           ============================================ */
        .welcome-banner {
            background: linear-gradient(120deg, #0D6EFD, #0D6EFD);
            border-radius: var(--d-radius);
            position: relative;
            overflow: hidden;
            border: none;
            box-shadow: 0 8px 24px rgba(13, 110, 253, .22);
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
        .welcome-banner .card-body { padding: 16px 18px; position: relative; z-index: 2; }

        /* ============================================
           EXPENSE OVERVIEW (top summary tiles)
           ============================================ */
        .stats-card {
            background: var(--d-card);
            border-radius: var(--d-radius);
            padding: 12px;
            display: flex; align-items: center; gap: 14px;
            transition: transform .18s ease, box-shadow .18s ease;
            border: 1px solid var(--d-border);
            box-shadow: var(--d-shadow);
            position: relative; overflow: hidden;
        }
        .stats-card:hover { transform: translateY(-3px); box-shadow: var(--d-shadow-hover); }
        .stats-amount-main { font-size: 15px; font-weight: 800; color: var(--d-text); letter-spacing: -.4px; }
        .stats-label { font-size: 9.5px; color: var(--d-text-soft); font-weight: 600; }

        /* expense breakdown rows */
        .exp-card .breakdown-row { padding: 0; }
        .exp-card .breakdown-row + .breakdown-row { border-top: 1px dashed var(--d-border); }
        .exp-card a { transition: color .15s ease; }






        .table-responsive .table tr td { padding-top: 4px; padding-bottom: 4px; }

        /* avatar in tables */
        .tbl-avatar { width: 32px; height: 32px; border-radius: 9px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .tbl-avatar img { width: 32px; height: 32px; object-fit: cover; border-radius: 9px; }

        /* ============================================
           STATUS BADGES
           ============================================ */
        .badge { padding: 3px 10px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
        .badge-active, .badge-approved, .badge-completed { background: rgba(59,130,246,.14); color: #0B5ED7; }
        .badge-ongoing, .badge-in-progress { background: rgba(13, 110, 253,.12); color: var(--d-primary); }
        .badge-pending, .badge-on-hold { background: rgba(96,165,250,.18); color: #0D6EFD; }
        .badge-rejected, .badge-cancelled, .badge-on-danger { background: rgba(11, 94, 215,.14); color: #0D6EFD; }
        .badge-secondary { background: #eef1f7; color: var(--d-text-soft); }
        .bg-soft-success.badge, .bg-soft-warning.badge, .bg-soft-danger.badge { font-weight: 700; }

        /* ============================================
           RANK BADGE
           ============================================ */
        .rank-badge { width: 26px; height: 26px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; }
        .rank-1 { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; }
        .rank-2 { background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; }
        .rank-3 { background: linear-gradient(135deg,#bfdbfe,#93c5fd); color: #0D6EFD; }

        /* ============================================
           INFO / EVENT CARDS
           ============================================ */
        .info-card { background: #f8f9fd; border: 1px solid var(--d-border); border-radius: var(--d-radius-sm); padding: 8px 10px; transition: all .16s ease; cursor: pointer; }
        .info-card:hover { background: #fff; box-shadow: var(--d-shadow-hover); transform: translateX(2px); }

        .wd-7 { width: 8px; } .ht-7 { height: 8px; }
        .wd-50 { width: 46px; } .ht-50 { height: 46px; }

        .deadline-urgent  { color: #0D6EFD; font-weight: 700; }
        .deadline-warning { color: #0D6EFD; font-weight: 700; }
        .deadline-normal  { color: #60a5fa; font-weight: 700; }

        /* compact gutters */
        .row.g-compact { --bs-gutter-x: 8px; --bs-gutter-y: 8px; }

        a { text-decoration: none; }
        .text-dark { color: var(--d-text) !important; }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <x-ui.page-header title="Admin Dashboard" :back="false" />
    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Welcome Banner -->
        <div class="row g-compact mb-2">
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
        @php
            $empBase = $total_employees ?? 0;
            $pct = fn ($n) => $empBase > 0 ? round(($n / $empBase) * 100) : 0;
            $inactiveEmployees = max(0, ($total_employees ?? 0) - ($active_employees ?? 0));
            $taskBase = $total_tasks ?? 0;
            $taskPct = $taskBase > 0 ? round((($completed_tasks ?? 0) / $taskBase) * 100) : 0;
            $projBase = $total_projects ?? 0;
            $projPct = $projBase > 0 ? round((($completed_projects ?? 0) / $projBase) * 100) : 0;
        @endphp
        <div class="row row-cols-1 row-cols-md-2 row-cols-xxl-5 g-compact mb-2">
            <div class="col">
                <a href="{{ route('employee.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-primary"><i class="feather-users"></i></span>
                            <span class="kpi5-pill">{{ $pct($active_employees ?? 0) }}% Active</span>
                        </div>
                        <div class="kpi5-value">{{ $total_employees ?? 0 }}</div>
                        <div class="kpi5-label">Total Employees</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $active_employees ?? 0 }}</span><span class="l">Active</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $inactiveEmployees }}</span><span class="l">Inactive</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('team.index', ['status' => 'present']) }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-success"><i class="feather-check-circle"></i></span>
                            <span class="kpi5-pill">{{ $pct($present_today ?? 0) }}% Rate</span>
                        </div>
                        <div class="kpi5-value">{{ $present_today ?? 0 }}</div>
                        <div class="kpi5-label">Present Today</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $absent_today ?? 0 }}</span><span class="l">Absent</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $on_leave_today ?? 0 }}</span><span class="l">On leave</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('task.assigned-by-me') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-info"><i class="feather-list"></i></span>
                            <span class="kpi5-pill">{{ $taskPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_tasks ?? 0 }}</div>
                        <div class="kpi5-label">Total Tasks</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $pending_tasks ?? 0 }}</span><span class="l">Pending</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $completed_tasks ?? 0 }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('project.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-purple"><i class="feather-briefcase"></i></span>
                            <span class="kpi5-pill">{{ $projPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_projects ?? 0 }}</div>
                        <div class="kpi5-label">Total Projects</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $ongoing_projects ?? 0 }}</span><span class="l">Ongoing</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $completed_projects ?? 0 }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col">
                <a href="{{ route('employee.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-warning"><i class="feather-user-plus"></i></span>
                            <span class="kpi5-pill">This month</span>
                        </div>
                        <div class="kpi5-value">{{ $monthly_joinings ?? 0 }}</div>
                        <div class="kpi5-label">New Joinings</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $on_leave_today ?? 0 }}</span><span class="l">On leave</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $absent_today ?? 0 }}</span><span class="l">Absent</span></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Expense Overview -->
        <div class="section-hdr">
            <div class="section-hdr-left">
                <span class="section-hdr-icon"><i class="feather-credit-card"></i></span>
                <h6 class="section-hdr-title">Expense Overview</h6>
            </div>
            <a href="{{ route('expense.view-all') }}" class="section-hdr-view">View</a>
        </div>
        <div class="row g-compact mb-2">
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
        <div class="row g-compact mb-2">
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

        <!-- Task Overview / Company Trend / Calendar Row -->
        <div class="row g-compact mb-2">
            <!-- Task Overview -->
            <div class="col-md-3">
                <div class="card stretch-full h-100 proj-bar-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Task Overview</h5>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'task-overview-bars', 'task-overview-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        @php
                            $taskFunnel = [
                                ['label'=>'Total','val'=>$total_tasks ?? 0],
                                ['label'=>'Pending','val'=>$pending_tasks ?? 0],
                                ['label'=>'In Progress','val'=>$in_progress_tasks ?? 0],
                                ['label'=>'Completed','val'=>$completed_tasks ?? 0],
                                ['label'=>'Approved','val'=>$approved_tasks ?? 0],
                                ['label'=>'Rejected','val'=>$rejected_tasks ?? 0],
                            ];
                        @endphp
                        <div id="task-overview-bars" class="funnel-list">
                            @foreach ($taskFunnel as $t)
                                <div class="funnel-row">
                                    <div class="funnel-label">{{ $t['label'] }}</div>
                                    <div class="funnel-track">
                                        <div class="funnel-fill" style="width: {{ ($total_tasks ?? 0) > 0 ? round(($t['val'] / $total_tasks) * 100) : 0 }}%"></div>
                                    </div>
                                    <div class="funnel-count">{{ $t['val'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <table class="mini-table" id="task-overview-table-view" style="display:none;">
                            <tbody>
                                @foreach ($taskFunnel as $t)
                                    <tr>
                                        <td>{{ $t['label'] }}</td>
                                        <td>{{ $t['val'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Company Overview Trend -->
            <div class="col-md-6">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Company Overview Trend</h5>
                        <span class="stat-sub">Last 6 Months</span>
                    </div>
                    <div class="card-body p-2">
                        <div id="company-overview-chart" style="height: 240px; width: 100%;"></div>
                    </div>
                </div>
            </div>

            <!-- Attendance Calendar -->
            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Attendance Calendar</h5>
                        <div class="cal-nav">
                            <span class="cal-nav-btn" onclick="calChangeMonth(-1)">&#8249;</span>
                            <span class="cal-nav-label" id="cal-month-label"></span>
                            <span class="cal-nav-btn" onclick="calChangeMonth(1)">&#8250;</span>
                        </div>
                    </div>
                    <div class="card-body cal-card-body">
                        <div class="cal-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                        <div class="cal-grid" id="cal-grid"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        @php
            $projTiles = [
                ['label'=>'Total','val'=>$total_projects ?? 0],
                ['label'=>'Pending','val'=>$active_projects ?? 0],
                ['label'=>'Ongoing','val'=>$ongoing_projects ?? 0],
                ['label'=>'Completed','val'=>$completed_projects ?? 0],
                ['label'=>'On Hold','val'=>$on_hold_projects ?? 0],
                ['label'=>'Cancelled','val'=>$cancelled_projects ?? 0],
            ];
        @endphp
        <div class="row g-compact mb-2">
            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Weekly Attendance Overview</h5>
                    </div>
                    <div class="card-body p-2">
                        <div id="attendance-chart" style="height: 200px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Department Distribution</h5>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'department-donut-chart', 'department-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        <div id="department-donut-chart" style="height: 190px; width: 100%;"></div>
                        <table class="mini-table" id="department-table-view" style="display:none;">
                            <tbody>
                                @foreach ($department_distribution as $dept)
                                    <tr>
                                        <td>
                                            <span class="wd-7 ht-7 rounded-circle d-inline-block me-1" style="background-color: {{ $colorPalette[$loop->index % count($colorPalette)] }}"></span>
                                            {{ $dept->name }}
                                        </td>
                                        <td>{{ $dept->users_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Project Status Distribution</h5>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'project-status-chart', 'project-status-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        <div id="project-status-chart" style="height: 190px; width: 100%;"></div>
                        @php
                            $statusColors = ['active'=>'#0D6EFD','ongoing'=>'#0D6EFD','completed'=>'#3b82f6','on_hold'=>'#60a5fa','cancelled'=>'#bfdbfe'];
                        @endphp
                        <table class="mini-table" id="project-status-table-view" style="display:none;">
                            <tbody>
                                @foreach ($projects_by_status ?? [] as $status => $count)
                                    @if ($count > 0)
                                        <tr>
                                            <td>
                                                <span class="wd-7 ht-7 rounded-circle d-inline-block me-1" style="background-color: {{ $statusColors[$status] ?? '#6c757d' }}"></span>
                                                {{ ucfirst($status) }}
                                            </td>
                                            <td>{{ $count }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stretch-full h-100 proj-bar-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Project Overview</h5>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'project-overview-bars', 'project-overview-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        <div id="project-overview-bars" class="funnel-list">
                            @foreach ($projTiles as $p)
                                <div class="funnel-row">
                                    <div class="funnel-label">{{ $p['label'] }}</div>
                                    <div class="funnel-track">
                                        <div class="funnel-fill" style="width: {{ $projBase > 0 ? round(($p['val'] / $projBase) * 100) : 0 }}%"></div>
                                    </div>
                                    <div class="funnel-count">{{ $p['val'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <table class="mini-table" id="project-overview-table-view" style="display:none;">
                            <tbody>
                                @foreach ($projTiles as $p)
                                    <tr>
                                        <td>{{ $p['label'] }}</td>
                                        <td>{{ $p['val'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regularization / Performance / Calendar Row -->
        <div class="row g-compact mb-2">
            <!-- Most Regularization Request -->
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Most Regularization Requests</h5>
                        <span class="badge bg-soft-success">This Month</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Requests</th></tr></thead>
                                <tbody>
                                    @forelse($most_regularization_requests ?? [] as $employee)
                                        <tr onclick="window.location='{{ route('attendance-regularization.manage', ['user_id' => $employee->id]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-primary">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ file_url($employee->profile_image, 'profile_photo') }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $employee->name }}</span>
                                                        <span class="stat-sub">{{ $employee->employee_id }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold">{{ $employee->total_requests }}/{{ $employee->approved_requests }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">No regularization requests found</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top 5 Performer -->
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Top 5 Performer</h5>
                        <select class="month-filter" onchange="location.search = 'performance_month=' + this.value">
                            @foreach ($performance_month_options ?? [] as $opt)
                                <option value="{{ $opt['value'] }}" {{ ($selected_performance_month ?? '') === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Performance</th></tr></thead>
                                <tbody>
                                    @forelse($top_performers ?? [] as $employee)
                                        <tr onclick="window.location.href='{{ route('team.member-detail', ['id' => encrypt($employee->id)]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-success">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ file_url($employee->profile_image, 'profile_photo') }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $employee->name }}</span>
                                                        <span class="stat-sub">{{ $employee->employee_id }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold text-primary">{{ $employee->performance_percentage }}%</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">No data available</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Least Tasks Assigned -->
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Least Tasks Assigned</h5>
                        <span class="badge bg-soft-warning">Need Attention</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Tasks</th></tr></thead>
                                <tbody>
                                    @forelse($least_tasks_employees ?? [] as $employee)
                                        <tr onclick="window.location.href='{{ route('team.member-detail', ['id' => encrypt($employee->id)]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-warning">
                                                        @if ($employee->profile_image)
                                                            <img src="{{ file_url($employee->profile_image, 'profile_photo') }}">
                                                        @else
                                                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $employee->name }}</span>
                                                        <span class="stat-sub">{{ $employee->employee_id }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold">{{ $employee->total_tasks }}/{{ $employee->completed_tasks }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">No data available</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Recent Announcements</h5>
                        <span class="badge bg-soft-info">Last 5</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="ann-list">
                            @forelse($recent_announcements ?? [] as $announcement)
                                <div class="ann-row">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="ann-title">{{ $announcement->title }}</span>
                                        <span class="ann-date">{{ \Carbon\Carbon::parse($announcement->created_at)->format('d M') }}</span>
                                    </div>
                                    <span class="ann-desc">{{ \Illuminate\Support\Str::limit($announcement->description, 60) }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted fs-12">No recent announcements</div>
                            @endforelse
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
        function toggleChartTable(btn, chartId, tableId) {
            var chartEl = document.getElementById(chartId);
            var tableEl = document.getElementById(tableId);
            if (!chartEl || !tableEl) return;
            var showingTable = tableEl.style.display !== 'none';
            chartEl.style.display = showingTable ? '' : 'none';
            tableEl.style.display = showingTable ? 'none' : 'table';
            btn.textContent = showingTable ? 'View as table' : 'View as chart';
        }

        // Attendance Calendar — click a date to open Team Members filtered to that date
        var calDate = new Date();
        var calTeamRoute = @json(route('team.index'));
        var calHolidays = @json($calendar_holidays ?? []);
        var calMonthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        function calRender() {
            var year = calDate.getFullYear();
            var month = calDate.getMonth();
            var monthLabel = document.getElementById('cal-month-label');
            if (monthLabel) monthLabel.textContent = calMonthNames[month].slice(0, 3) + ' ' + year;

            var grid = document.getElementById('cal-grid');
            if (!grid) return;
            grid.innerHTML = '';

            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var todayStr = new Date().toDateString();

            for (var i = 0; i < firstDay; i++) {
                var blank = document.createElement('span');
                blank.className = 'cal-day cal-day-empty';
                grid.appendChild(blank);
            }
            for (var d = 1; d <= daysInMonth; d++) {
                (function(y, m, day) {
                    var mm = String(m + 1).padStart(2, '0');
                    var dd = String(day).padStart(2, '0');
                    var dateKey = y + '-' + mm + '-' + dd;
                    var holidayName = calHolidays[dateKey];

                    var cell = document.createElement('span');
                    cell.className = 'cal-day';
                    cell.textContent = day;

                    if (new Date(y, m, day).toDateString() === todayStr) {
                        cell.classList.add('cal-day-today');
                    }
                    if (holidayName) {
                        cell.classList.add('cal-day-holiday');
                        cell.title = holidayName;
                        var badge = document.createElement('sup');
                        badge.className = 'cal-day-holiday-badge';
                        badge.textContent = 'H';
                        cell.appendChild(badge);
                    }

                    cell.addEventListener('click', function() {
                        window.location.href = calTeamRoute + '?date=' + dateKey;
                    });
                    grid.appendChild(cell);
                })(year, month, d);
            }
        }

        function calChangeMonth(offset) {
            calDate.setMonth(calDate.getMonth() + offset);
            calRender();
        }

        calRender();

        $(document).ready(function() {
            const colorPalette = ['#0D6EFD', '#0B5ED7', '#0D6EFD', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#0ea5e9'];
            const statusColors = {
                'active': '#0D6EFD', 'ongoing': '#0D6EFD', 'completed': '#3b82f6', 'on_hold': '#60a5fa', 'cancelled': '#bfdbfe'
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
                            height: 200,
                            toolbar: { show: false },
                            fontFamily: 'inherit'
                        },
                        colors: ['#0D6EFD', '#93c5fd'],
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
                        plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', formatter: (w) => w.globals.seriesTotals.reduce((a,b)=>a+b,0) + " projects" } } } } },
                        tooltip: { y: { formatter: (val) => val + " projects" } },
                        responsive: [{ breakpoint: 480, options: { chart: { width: 200 } } }]
                    };
                    try { new ApexCharts(projectStatusElement, projectStatusOptions).render(); } catch(e) { console.error(e); }
                }
            @endif

            // Company Overview Trend (last 6 months) — Expense, Payroll and
            // Loan / Other Transactions as three ₹ lines on a shared axis.
            @if (isset($company_overview_trend))
                var companyTrendElement = document.getElementById("company-overview-chart");
                if (companyTrendElement) {
                    companyTrendElement.innerHTML = '';
                    var trendLabels = @json($company_overview_trend['labels'] ?? []);
                    var trendExpense = @json($company_overview_trend['expense'] ?? []);
                    var trendPayroll = @json($company_overview_trend['payroll'] ?? []);
                    var trendLoanOther = @json($company_overview_trend['loan_other'] ?? []);

                    var companyTrendOptions = {
                        series: [
                            { name: 'Expense (₹)', data: trendExpense },
                            { name: 'Payroll (₹)', data: trendPayroll },
                            { name: 'Loan / Other Transactions (₹)', data: trendLoanOther }
                        ],
                        chart: { type: 'line', height: 240, toolbar: { show: false }, fontFamily: 'inherit' },
                        colors: ['#0D6EFD', '#0D6EFD', '#60a5fa'],
                        stroke: { width: 2.5, curve: 'smooth' },
                        markers: { size: 3 },
                        xaxis: { categories: trendLabels, labels: { style: { fontSize: '10.5px' } } },
                        yaxis: {
                            title: { text: 'Amount (₹)', style: { fontSize: '10px' } },
                            labels: { formatter: (val) => '₹' + Math.round(val) }
                        },
                        legend: { position: 'top', fontSize: '10.5px' },
                        grid: { borderColor: '#eef1f7', strokeDashArray: 4 },
                        tooltip: { shared: true, intersect: false, y: { formatter: (val) => '₹' + Math.round(val) } }
                    };
                    try { new ApexCharts(companyTrendElement, companyTrendOptions).render(); } catch(e) { console.error(e); }
                }
            @endif
        });
    </script>
@endsection