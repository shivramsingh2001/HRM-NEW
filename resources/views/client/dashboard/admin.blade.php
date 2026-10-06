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
        /* ============================================
           NEEDS YOUR ACTION — one tile per module with pending approvals
           ============================================ */
        .act-total { margin-left: 8px; font-size: 10px; font-weight: 700; padding: 1px 8px; border-radius: 20px; background: #fef3c7; color: #b45309; }
        .act-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
        .act-tile { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #fff; border: 1px solid var(--d-border, #eaeef5);
            border-radius: 10px; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
        .act-tile:hover { border-color: #93c5fd; box-shadow: 0 2px 8px rgba(13, 110, 253, .08); }
        .act-icon { width: 32px; height: 32px; flex: none; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
            background: #f1f5f9; color: #94a3b8; font-size: 14px; }
        .act-tile.has .act-icon { background: #EFF6FF; color: #0D6EFD; }
        .act-body { display: flex; flex-direction: column; min-width: 0; flex: 1; }
        .act-count { font-size: 16px; font-weight: 800; line-height: 1.1; color: #94a3b8; }
        .act-tile.has .act-count { color: #0f172a; }
        .act-label { font-size: 10.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .act-go { color: #cbd5e1; font-size: 13px; }
        .act-tile:hover .act-go { color: #0D6EFD; }
        .act-empty { grid-column: 1 / -1; padding: 10px 14px; border: 1px dashed #bbf7d0; background: #f0fdf4; color: #15803d; border-radius: 10px; font-size: 12px; font-weight: 600; }

        /* Period filter — chip + ⋮ menu in the page header */
        .dash-period-chip { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; border-radius: 8px;
            background: #EFF6FF; color: #0D6EFD; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .dash-period-chip i { font-size: 11px; }
        .dash-period-chip.muted { background: #f1f5f9; color: #64748b; }
        .dash-reset { text-decoration: none; }
        .dash-reset i { font-size: 13px; }
        .dash-kebab { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #dfe5f0; background: #fff; color: #475569;
            display: inline-flex; align-items: center; justify-content: center; padding: 0; transition: background .15s, color .15s, border-color .15s; }
        .dash-kebab:hover, .dash-kebab[aria-expanded="true"] { background: #EFF6FF; color: #0D6EFD; border-color: #93c5fd; }
        .dash-kebab i { font-size: 15px; }
        .dash-period-menu { width: 210px; padding: 5px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
        .dash-period-head { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; padding: 3px 7px 5px; }
        /* Beats the theme's `.dropdown .dropdown-menu .dropdown-item` (padding 10px 15px, margin 3px 10px, 13px/600) — this menu only. */
        .dash-period .dash-period-menu .dropdown-item { display: flex; align-items: center; gap: 7px; margin: 0; padding: 5px 7px;
            font-size: 11px; font-weight: 500; line-height: 1.35; color: #334155; border-radius: 6px; width: 100%; background: none; border: 0; text-align: left; }
        .dash-period .dash-period-menu .dropdown-item i { font-size: 11.5px; color: #94a3b8; }
        .dash-period .dash-period-menu .dropdown-item:hover { background: #f1f5f9; color: #0D6EFD; }
        .dash-period .dash-period-menu .dropdown-item.active { background: #EFF6FF; color: #0D6EFD; font-weight: 700; }
        .dash-period .dash-period-menu .dropdown-item.active i { color: #0D6EFD; }
        .dash-period-menu .dropdown-divider { margin: 3px 0; }
        .dash-custom { padding: 3px 7px 5px; }
        .dash-custom-label { display: block; font-size: 9.5px; font-weight: 600; color: #64748b; margin: 3px 0 2px; }
        .dash-date { width: 100%; border: 1px solid #dfe5f0; border-radius: 7px; padding: 3px 7px; font-size: 11px; height: 28px; }
        .dash-date:focus { border-color: #0D6EFD; outline: none; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); }
        .dash-custom .btn { font-size: 11px; padding: 4px 8px; }
        .dash-period-foot { font-size: 9.5px; line-height: 1.35; color: #94a3b8; padding: 5px 7px 2px; border-top: 1px solid #f1f5f9; margin-top: 3px; }
        .kpi5-period { font-size: 9.5px; color: #94a3b8; margin-top: -2px; }

        /* Today's celebrations (only rendered when someone has a birthday / anniversary today) */
        .celebrate-card { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 12px 16px; border-radius: 12px;
            background: linear-gradient(90deg, #EFF6FF 0%, #fff 70%); border: 1px solid #bfdbfe; }
        .celebrate-head { display: flex; align-items: center; gap: 10px; flex: none; }
        .celebrate-emoji { font-size: 26px; line-height: 1; }
        .celebrate-title { font-size: 13px; font-weight: 800; color: #0f172a; }
        .celebrate-sub { font-size: 10.5px; color: #64748b; }
        .celebrate-list { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; min-width: 0; }
        .celebrate-person { display: flex; align-items: center; gap: 8px; padding: 5px 12px 5px 5px; background: #fff; border: 1px solid #dbeafe;
            border-radius: 30px; text-decoration: none; transition: border-color .15s, box-shadow .15s; max-width: 260px; }
        .celebrate-person:hover { border-color: #0D6EFD; box-shadow: 0 2px 8px rgba(13, 110, 253, .12); }
        .celebrate-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex: none; display: inline-flex; align-items: center;
            justify-content: center; background: #0D6EFD; color: #fff; font-size: 12px; font-weight: 700; }
        .celebrate-info { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
        .celebrate-name { font-size: 12px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .celebrate-what { font-size: 10.5px; color: #0D6EFD; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .celebrate-what i { font-size: 10px; }

        /* Today at a glance */
        .glance-stats { display: grid; grid-template-columns: repeat(3, 1fr); border-bottom: 1px solid var(--d-border, #eaeef5); }
        .glance-stats > div { padding: 10px 6px; text-align: center; }
        .glance-stats > div + div { border-left: 1px solid var(--d-border, #eaeef5); }
        .glance-stats .n { display: block; font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.1; }
        .glance-stats .l { font-size: 9.5px; color: #64748b; text-transform: uppercase; letter-spacing: .02em; }
        .glance-sub { padding: 8px 14px 0; font-size: 9.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; border-top: 1px solid var(--d-border, #eaeef5); }

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
        /* The theme gives every .card margin-bottom: 24px (and .stretch-full = 100% - 24px). Cards with a fixed
           height (.fixed-h-card) or stretch-full therefore pushed 24px extra below their row, unlike the h-100
           rows. Rows here space themselves (mb-2 + gutter), so cards inside them carry no margin of their own. */
        .main-content .row.g-compact > [class*="col"] > .card,
        .main-content .row.g-compact > [class*="col"] > a > .card { margin-bottom: 0; }
        .main-content .row.g-compact .card.stretch-full:not(.fixed-h-card) { height: 100%; }

        a { text-decoration: none; }
        .text-dark { color: var(--d-text) !important; }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    @php
        $rg = $range ?? ['preset' => 'none', 'label' => 'All data', 'from' => now()->toDateString(), 'to' => now()->toDateString(), 'note' => null, 'is_today' => true, 'days' => 1, 'applied' => false];
        $ap = $rg['applied'] ?? false; // a period filter is applied
        $presets = ['today' => ['Today', 'sun'], 'last_7_days' => ['Last 7 days', 'clock'], 'this_month' => ['This month', 'calendar'], 'previous_month' => ['Previous month', 'rotate-ccw']];
        $keep = request()->only('performance_month'); // other widgets' own choices survive a period change
        $rangeText = \Carbon\Carbon::parse($rg['from'])->format('d M Y') . ($rg['from'] !== $rg['to'] ? ' – ' . \Carbon\Carbon::parse($rg['to'])->format('d M Y') . ' (' . $rg['days'] . ' days)' : '');
    @endphp
    <x-ui.page-header title="Admin Dashboard" :back="false">
        <x-slot:actions>
            {{-- Period filter: current period chip + ⋮ menu (Today / Last 7 days / This month / Previous month / Custom range) --}}
            @if ($ap)
                <span class="dash-period-chip" title="{{ $rangeText }}"><i class="feather-calendar"></i> {{ $rg['label'] }}</span>
                <a href="{{ route('dashboard', $keep) }}" class="dash-kebab dash-reset" title="Reset filter — show all data" aria-label="Reset filter">
                    <i class="feather-rotate-ccw"></i>
                </a>
            @else
                <span class="dash-period-chip muted" title="No period filter — each card shows its normal data"><i class="feather-layers"></i> All data</span>
            @endif
            <div class="dropdown dash-period">
                <button type="button" class="dash-kebab" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                    aria-label="Dashboard period" title="Change period">
                    <i class="feather-more-vertical"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end dash-period-menu">
                    <div class="dash-period-head">Show data for</div>
                    @foreach ($presets as $key => [$text, $icon])
                        <a href="{{ route('dashboard', array_merge($keep, ['range' => $key])) }}" class="dropdown-item {{ $rg['preset'] === $key ? 'active' : '' }}">
                            <i class="feather-{{ $icon }}"></i><span>{{ $text }}</span>
                            @if ($rg['preset'] === $key)<i class="feather-check ms-auto"></i>@endif
                        </a>
                    @endforeach
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item {{ $rg['preset'] === 'custom' ? 'active' : '' }}" id="dashCustomBtn">
                        <i class="feather-sliders"></i><span>Custom range</span>
                        @if ($rg['preset'] === 'custom')<i class="feather-check ms-auto"></i>@else<i class="feather-chevron-down ms-auto"></i>@endif
                    </button>
                    <form method="GET" action="{{ route('dashboard') }}" class="dash-custom" id="dashCustom" style="{{ $rg['preset'] === 'custom' ? '' : 'display:none' }}">
                        @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <input type="hidden" name="range" value="custom">
                        <label class="dash-custom-label">From</label>
                        <input type="date" name="from" value="{{ $rg['preset'] === 'custom' ? $rg['from'] : '' }}" max="{{ now()->toDateString() }}" class="dash-date" aria-label="From date" required>
                        <label class="dash-custom-label">To</label>
                        <input type="date" name="to" value="{{ $rg['preset'] === 'custom' ? $rg['to'] : '' }}" max="{{ now()->toDateString() }}" class="dash-date" aria-label="To date" required>
                        <button type="submit" class="btn btn-sm btn-primary w-100 mt-2">Apply</button>
                    </form>
                    @if ($ap)
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('dashboard', $keep) }}" class="dropdown-item"><i class="feather-rotate-ccw"></i><span>Reset filter</span></a>
                    @endif
                    <div class="dash-period-foot">Pending approvals, celebrations and upcoming items always show "now".</div>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>
    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 20px !important;">

        <!-- Welcome Banner -->
        <div class="row g-compact mb-2">
            <div class="col-12">
                <div class="welcome-banner text-white">
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

        @if (!empty($rg['note']))
            <div class="alert alert-info py-2 mb-2" style="font-size:11px;border-radius:10px;"><i class="feather-info me-1"></i>{{ $rg['note'] }}</div>
        @endif

        <!-- Today's celebrations: shown only on a day someone has a birthday / work anniversary -->
        @if (($today_celebrations['total'] ?? 0) > 0)
            <div class="celebrate-card mb-2">
                <div class="celebrate-head">
                    <span class="celebrate-emoji" aria-hidden="true">🎉</span>
                    <div>
                        <div class="celebrate-title">Today's celebrations</div>
                        <div class="celebrate-sub">Wish them today — {{ \Carbon\Carbon::parse($today_celebrations['date'])->format('l, d F') }}</div>
                    </div>
                </div>
                <div class="celebrate-list">
                    @foreach ($today_celebrations['birthdays'] as $p)
                        <a href="{{ route('employee.show', encrypt($p['id'])) }}" class="celebrate-person">
                            @if ($p['profile_image'])
                                <img src="{{ $p['profile_image'] }}" alt="{{ $p['name'] }}" class="celebrate-avatar">
                            @else
                                <span class="celebrate-avatar">{{ strtoupper(mb_substr($p['name'], 0, 1)) }}</span>
                            @endif
                            <span class="celebrate-info">
                                <span class="celebrate-name">{{ $p['name'] }}</span>
                                <span class="celebrate-what"><i class="feather-gift"></i> Birthday{{ $p['designation'] ? ' · ' . $p['designation'] : '' }}</span>
                            </span>
                        </a>
                    @endforeach
                    @foreach ($today_celebrations['anniversaries'] as $p)
                        <a href="{{ route('employee.show', encrypt($p['id'])) }}" class="celebrate-person">
                            @if ($p['profile_image'])
                                <img src="{{ $p['profile_image'] }}" alt="{{ $p['name'] }}" class="celebrate-avatar">
                            @else
                                <span class="celebrate-avatar">{{ strtoupper(mb_substr($p['name'], 0, 1)) }}</span>
                            @endif
                            <span class="celebrate-info">
                                <span class="celebrate-name">{{ $p['name'] }}</span>
                                <span class="celebrate-what"><i class="feather-award"></i> {{ $p['years'] }} {{ $p['years'] > 1 ? 'years' : 'year' }} at work</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Employee Statistics Cards -->
        @php
            $empBase = $total_employees ?? 0;
            $pct = fn ($n) => $empBase > 0 ? round(($n / $empBase) * 100) : 0;
            $inactiveEmployees = $inactive_employees ?? 0;
            $show = $show ?? [];
            $offToday = ($today['week_off'] ?? 0) + ($today['holiday'] ?? 0);
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
                            <span class="kpi5-pill" title="Present ÷ (present + leave + absent) — week-offs and holidays excluded">{{ $today['rate'] ?? 0 }}% Rate</span>
                        </div>
                        <div class="kpi5-value">{{ $present_today ?? 0 }}</div>
                        <div class="kpi5-label">{{ ($rg['is_today'] ?? true) ? 'Present Today' : 'Present · ' . $rg['label'] }}</div>
                        @unless ($rg['is_today'] ?? true)<div class="kpi5-period">employee-days</div>@endunless
                        <div class="kpi5-divider"></div>
                        {{-- Each employee counted once: present → leave → holiday/week-off → absent --}}
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $absent_today ?? 0 }}</span><span class="l">Absent</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $on_leave_today ?? 0 }}</span><span class="l">Leave</span></div>
                            <div class="kpi5-stat" title="{{ ($today['holiday_name'] ?? null) ? 'Holiday: ' . $today['holiday_name'] : 'Week-off / holiday' }}"><span class="n">{{ $offToday }}</span><span class="l">Off</span></div>
                        </div>
                    </div>
                </a>
            </div>

            @if ($show['tasks'] ?? false)
            <div class="col">
                <a href="{{ route('task.assigned-by-me') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-info"><i class="feather-list"></i></span>
                            <span class="kpi5-pill">{{ $taskPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_tasks ?? 0 }}</div>
                        <div class="kpi5-label">{{ $ap ? 'Tasks · ' . $rg['label'] : 'Total Tasks' }}</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $pending_tasks ?? 0 }}</span><span class="l">Pending</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $completed_tasks ?? 0 }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>
            @endif

            @if ($show['projects'] ?? false)
            <div class="col">
                <a href="{{ route('project.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-purple"><i class="feather-briefcase"></i></span>
                            <span class="kpi5-pill">{{ $projPct }}% Done</span>
                        </div>
                        <div class="kpi5-value">{{ $total_projects ?? 0 }}</div>
                        <div class="kpi5-label">{{ $ap ? 'Projects · ' . $rg['label'] : 'Total Projects' }}</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $ongoing_projects ?? 0 }}</span><span class="l">Ongoing</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $completed_projects ?? 0 }}</span><span class="l">Completed</span></div>
                        </div>
                    </div>
                </a>
            </div>
            @endif

            <div class="col">
                <a href="{{ route('employee.index') }}" class="text-decoration-none">
                    <div class="kpi5-card">
                        <div class="kpi5-top">
                            <span class="kpi5-icon bg-soft-warning"><i class="feather-user-plus"></i></span>
                            <span class="kpi5-pill">{{ $ap ? $rg['label'] : 'This month' }}</span>
                        </div>
                        <div class="kpi5-value">{{ $monthly_joinings ?? 0 }}</div>
                        <div class="kpi5-label">New Joinings</div>
                        <div class="kpi5-divider"></div>
                        <div class="kpi5-foot">
                            <div class="kpi5-stat"><span class="n">{{ $today['late_count'] ?? 0 }}</span><span class="l">Late today</span></div>
                            <div class="kpi5-stat"><span class="n">{{ $today['early_count'] ?? 0 }}</span><span class="l">Left early</span></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        {{-- "Needs your action" (pending approvals per module) now lives in the top header,
             as a warning icon just before the notification bell — see the push at the end of this file. --}}

        <!-- Today / People / Holidays / Recent leave -->
        <div class="row g-compact mb-2">
            @if ($show['attendance'] ?? false)
            <div class="col-xl-3 col-md-6">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">{{ ($rg['is_today'] ?? true) ? 'Today at a glance' : 'Punctuality' }}</h5>
                        <span class="badge bg-soft-primary">{{ ($rg['is_today'] ?? true) ? now()->format('d M') : $rg['label'] }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="glance-stats">
                            <div><span class="n text-warning">{{ $today['late_count'] ?? 0 }}</span><span class="l">Late</span></div>
                            <div><span class="n">{{ $today['early_count'] ?? 0 }}</span><span class="l">Left early</span></div>
                            <div title="{{ ($rg['is_today'] ?? true) ? 'Clocked in yesterday but never clocked out' : 'Days in this period with a clock-in but no clock-out' }}"><span class="n text-danger">{{ $today['missed_clock_out'] ?? 0 }}</span><span class="l">{{ ($rg['is_today'] ?? true) ? 'No clock-out (yday)' : 'No clock-out' }}</span></div>
                        </div>
                        <div class="ann-list">
                            @forelse ($today['late_list'] ?? [] as $late)
                                <div class="ann-row d-flex justify-content-between align-items-center">
                                    <span class="ann-title">{{ $late->name }} <span class="stat-sub">({{ $late->employee_id }})</span></span>
                                    <span class="badge bg-soft-warning text-warning">{{ $late->late_days > 1 ? $late->late_days . ' days · ' : '' }}{{ $late->late_minutes }}m late</span>
                                </div>
                            @empty
                                <div class="text-center py-3 text-muted fs-12">No late arrivals today</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-xl-3 col-md-6">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">birthdays &amp; anniversaries</h5>
                        <span class="badge bg-soft-info">Next 30 days</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="ann-list">
                            @php
                                $celebrations = collect($upcoming_birthdays ?? [])->map(fn ($b) => ['icon' => 'gift', 'name' => $b['user']->name, 'what' => 'Birthday', 'days' => $b['days_until'], 'date' => $b['date']])
                                    ->concat(collect($upcoming_anniversaries ?? [])->map(fn ($a) => ['icon' => 'award', 'name' => $a['user']->name, 'what' => $a['years'] . ' yr' . ($a['years'] > 1 ? 's' : '') . ' at work', 'days' => $a['days_until'], 'date' => $a['date']]))
                                    ->sortBy('days')->take(6);
                            @endphp
                            @forelse ($celebrations as $c)
                                <div class="ann-row d-flex justify-content-between align-items-center">
                                    <span class="ann-title"><i class="feather-{{ $c['icon'] }} text-primary me-1"></i>{{ $c['name'] }} <span class="stat-sub">· {{ $c['what'] }}</span></span>
                                    <span class="ann-date">{{ $c['days'] === 0 ? 'Today' : ($c['days'] === 1 ? 'Tomorrow' : $c['date']->format('d M')) }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted fs-12">No birthdays or work anniversaries soon</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">New joiners</h5>
                        <a href="{{ route('employee.index') }}" class="badge bg-soft-primary">View all</a>
                    </div>
                    <div class="card-body p-0">
                        {{-- Same employee cell as the Top 5 Performer card --}}
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Joined</th></tr></thead>
                                <tbody>
                                    @forelse ($recent_joinings ?? [] as $j)
                                        <tr onclick="window.location.href='{{ route('team.member-detail', ['id' => encrypt($j->id)]) }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-primary">
                                                        @if ($j->profile_image)
                                                            <img src="{{ file_url($j->profile_image, 'profile_photo') }}">
                                                        @else
                                                            {{ strtoupper(substr($j->name, 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $j->name }}</span>
                                                        <span class="stat-sub">{{ $j->employee_id ?: '—' }}{{ $j->designation_name ? ' · ' . $j->designation_name : '' }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="fw-bold text-primary">{{ \Carbon\Carbon::parse($j->joined_on ?: $j->created_at)->format('d M') }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">No employees yet</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if (($show['holiday'] ?? false) && count($upcoming_holidays ?? []) > 0)
                            <div class="glance-sub">Upcoming holidays</div>
                            <div class="ann-list">
                                @foreach ($upcoming_holidays as $h)
                                    <div class="ann-row d-flex justify-content-between align-items-center">
                                        <span class="ann-title"><i class="feather-sun text-primary me-1"></i>{{ $h->name }}</span>
                                        <span class="ann-date">{{ \Carbon\Carbon::parse($h->start_date)->format('d M') }}{{ $h->end_date && $h->end_date != $h->start_date ? ' – ' . \Carbon\Carbon::parse($h->end_date)->format('d M') : '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @if ($show['leave'] ?? false)
            <div class="col-xl-3 col-md-6">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">{{ $ap ? 'Leave requests · ' . $rg['label'] : 'Recent leave requests' }}</h5>
                        <a href="{{ route('leave.view-all') }}" class="badge bg-soft-primary">View all</a>
                    </div>
                    <div class="card-body p-0">
                        {{-- Same employee cell as the Top 5 Performer card --}}
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Employee</th><th>Leave</th></tr></thead>
                                <tbody>
                                    @forelse ($recent_leaves ?? [] as $lv)
                                        @php $st = strtolower((string) $lv->status); @endphp
                                        <tr onclick="window.location.href='{{ route('leave.view-all') }}'">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="tbl-avatar bg-soft-info">
                                                        @if ($lv->profile_image)
                                                            <img src="{{ file_url($lv->profile_image, 'profile_photo') }}">
                                                        @else
                                                            {{ strtoupper(substr($lv->user_name ?? '—', 0, 2)) }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="d-block fw-bold">{{ $lv->user_name ?? '—' }}</span>
                                                        <span class="stat-sub">{{ $lv->employee_id ?: '—' }} · {{ $lv->leave_type_name ?? 'Leave' }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="d-block fw-bold text-primary">{{ \Carbon\Carbon::parse($lv->start_date)->format('d M') }}{{ $lv->end_date && $lv->end_date != $lv->start_date ? ' – ' . \Carbon\Carbon::parse($lv->end_date)->format('d M') : '' }}</span>
                                                <span class="badge {{ ['approved' => 'bg-soft-success text-success', 'pending' => 'bg-soft-warning text-warning', 'rejected' => 'bg-soft-danger text-danger'][$st] ?? 'bg-soft-secondary text-secondary' }}">{{ ucfirst($st) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center py-4 text-muted">{{ $ap ? 'No leave requests in this period' : 'No leave requests yet' }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        @if ($show['expenses'] ?? false)
        <!-- Expense Overview -->
        <div class="section-hdr">
            <div class="section-hdr-left">
                <span class="section-hdr-icon"><i class="feather-credit-card"></i></span>
                <h6 class="section-hdr-title">Expense Overview @if ($ap)<span class="stat-sub fw-normal">· balances now, expenses dated {{ $rg['label'] }}</span>@endif</h6>
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
        @endif

        <!-- Task Overview / Company Trend / Calendar Row -->
        <div class="row g-compact mb-2">
            <!-- Task Overview -->
            @if ($show['tasks'] ?? false)
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
            @endif

            <!-- Company Overview Trend -->
            <div class="{{ ($show['tasks'] ?? false) ? 'col-md-6' : 'col-md-9' }}">
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
                ['label'=>'Pending','val'=>$pending_projects ?? 0],
                ['label'=>'Ongoing','val'=>$ongoing_projects ?? 0],
                ['label'=>'Completed','val'=>$completed_projects ?? 0],
                ['label'=>'On Hold','val'=>$on_hold_projects ?? 0],
                ['label'=>'Cancelled','val'=>$cancelled_projects ?? 0],
            ];
        @endphp
        <div class="row g-compact mb-2">
            @if ($show['attendance'] ?? false)
            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Attendance Overview</h5>
                        <span class="badge bg-soft-primary">{{ $ap ? $rg['label'] : 'Last 7 days' }}</span>
                    </div>
                    <div class="card-body p-2">
                        <div id="attendance-chart" style="height: 200px;"></div>
                    </div>
                </div>
            </div>
            @endif

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

            @if ($show['projects'] ?? false)
            <div class="col-md-3">
                <div class="card stretch-full h-100">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Project Status Distribution</h5>
                        <span class="view-toggle-btn" onclick="toggleChartTable(this, 'project-status-chart', 'project-status-table-view')">View as table</span>
                    </div>
                    <div class="card-body">
                        <div id="project-status-chart" style="height: 190px; width: 100%;"></div>
                        @php
                            $statusColors = ['pending'=>'#93c5fd','active'=>'#0D6EFD','ongoing'=>'#0D6EFD','completed'=>'#3b82f6','on_hold'=>'#60a5fa','cancelled'=>'#bfdbfe'];
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
            @endif
        </div>

        <!-- Regularization / Performance / Calendar Row -->
        <div class="row g-compact mb-2">
            <!-- Most Regularization Request -->
            @if ($show['regularization'] ?? false)
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Most Regularization Requests</h5>
                        <span class="badge bg-soft-success">{{ $ap ? $rg['label'] : 'All time' }}</span>
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
            @endif

            <!-- Top 5 Performer -->
            @if ($show['attendance'] ?? false)
            <div class="col-md-3">
                <div class="card stretch-full fixed-h-card">
                    <div class="card-header">
                        <h5 class="card-title card-title-sm">Top 5 Performer</h5>
                        <select class="month-filter" onchange="dashSetParam('performance_month', this.value)">
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
            @endif

            <!-- Least Tasks Assigned -->
            @if ($show['tasks'] ?? false)
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
            @endif

            <!-- Recent Announcements -->
            @if ($show['announcements'] ?? false)
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
            @endif
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
                'pending': '#93c5fd', 'active': '#0D6EFD', 'ongoing': '#0D6EFD', 'completed': '#3b82f6', 'on_hold': '#60a5fa', 'cancelled': '#bfdbfe'
            };

            // Period filter: "Custom" reveals the from/to dates; changing the Top-performer month keeps the period.
            (function () {
                const btn = document.getElementById('dashCustomBtn');
                const box = document.getElementById('dashCustom');
                if (btn && box) btn.addEventListener('click', function () {
                    box.style.display = box.style.display === 'none' ? '' : 'none';
                    const first = box.querySelector('input[name="from"]');
                    if (box.style.display !== 'none' && first) first.focus();
                });
                window.dashSetParam = function (key, value) {
                    const p = new URLSearchParams(location.search);
                    p.set(key, value);
                    location.search = p.toString();
                };
            })();

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

{{-- Needs your action: pending approvals per module (enabled modules the viewer may open),
     shown as a warning icon before the notification bell. Click = small list with a count per module. --}}
@if (!empty($pending_approvals))
    @php $pendingTotal = collect($pending_approvals)->sum('count'); @endphp
    @push('header-before-bell')
        <div class="dropdown nxl-h-item" id="needsActionWrapper">
            <a href="javascript:void(0);" class="nxl-head-link me-0 needs-action-toggle" data-bs-toggle="dropdown"
                aria-expanded="false" aria-label="Needs your action" title="Needs your action">
                <i class="feather-alert-triangle {{ $pendingTotal > 0 ? 'text-warning' : '' }}"></i>
                @if ($pendingTotal > 0)
                    <span class="badge bg-warning text-dark rounded-pill needs-action-badge">{{ $pendingTotal > 99 ? '99+' : $pendingTotal }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown needs-action-menu">
                <div class="needs-action-head">
                    Needs your action
                    @if ($pendingTotal > 0)<span class="needs-action-total">{{ $pendingTotal }} pending</span>@endif
                </div>
                @if ($pendingTotal === 0)
                    <div class="needs-action-empty"><i class="feather-check-circle me-1"></i>All caught up — nothing is waiting for approval.</div>
                @else
                    @foreach ($pending_approvals as $item)
                        <a href="{{ $item['url'] }}" class="needs-action-row {{ $item['count'] > 0 ? 'has' : '' }}">
                            <span class="needs-action-count">{{ $item['count'] }}</span>
                            <span class="needs-action-label"><i class="feather-{{ $item['icon'] }} me-1"></i>{{ $item['label'] }}</span>
                            <i class="feather-chevron-right needs-action-go"></i>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
        <style>
            .needs-action-toggle { position: relative; }
            .needs-action-badge { position: absolute; top: 2px; right: 2px; font-size: 9px; min-width: 16px; line-height: 1.3; padding: 1px 4px; }
            .needs-action-menu { width: 250px; padding: 0; }
            .needs-action-head { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px;
                border-bottom: 1px solid #f1f5f9; font-size: 12.5px; font-weight: 700; color: #0f172a; }
            .needs-action-total { font-size: 10px; font-weight: 700; padding: 1px 8px; border-radius: 20px; background: #fef3c7; color: #b45309; }
            .needs-action-row { display: flex; align-items: center; gap: 10px; padding: 7px 12px; border-bottom: 1px solid #f8fafc;
                text-decoration: none; color: #94a3b8; }
            .needs-action-row:last-child { border-bottom: 0; }
            .needs-action-row:hover { background: #f8fafc; }
            .needs-action-count { min-width: 26px; text-align: center; font-size: 12px; font-weight: 800; padding: 1px 6px;
                border-radius: 6px; background: #f1f5f9; color: #94a3b8; }
            .needs-action-row.has .needs-action-count { background: #EFF6FF; color: #0D6EFD; }
            .needs-action-label { flex: 1; min-width: 0; font-size: 11.5px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .needs-action-row.has .needs-action-label { color: #0f172a; font-weight: 600; }
            .needs-action-go { font-size: 12px; color: #cbd5e1; }
            .needs-action-row:hover .needs-action-go { color: #0D6EFD; }
            .needs-action-empty { padding: 12px; font-size: 11.5px; font-weight: 600; color: #15803d; }
        </style>
    @endpush
@endif
