@extends('client.layout.master')

@section('style')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap');

        /* ─── Tokens ─── */
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --primary-mid: #bfdbfe;
            --primary-dark: #1d4ed8;
            --success: #059669;
            --success-light: #ecfdf5;
            --success-mid: #a7f3d0;
            --warning: #d97706;
            --warning-light: #fffbeb;
            --warning-mid: #fde68a;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --danger-mid: #fca5a5;
            --purple: #7c3aed;
            --purple-light: #f5f3ff;
            --purple-mid: #ddd6fe;
            --border: #e5e7eb;
            --border-hover: #d1d5db;
            --surface: #ffffff;
            --surface-2: #f9fafb;
            --surface-3: #f3f4f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .06), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-card: 0 2px 8px rgba(0, 0, 0, .06);
            --font: 'DM Sans', system-ui, sans-serif;
            --transition: .15s ease;
        }

        .meetings-page * {
            font-family: var(--font);
            box-sizing: border-box;
        }

        /* ─── Page header ─── */
        .meetings-page {
            padding: 20px 0 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .meetings-page h5 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px;
        }

        .meetings-page .breadcrumb {
            font-size: 12px;
            margin: 0;
            padding: 0;
            background: none;
        }

        .meetings-page .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }

        .meetings-page .breadcrumb-item+.breadcrumb-item::before {
            color: var(--text-muted);
        }

        .meetings-page .breadcrumb-item.active {
            color: var(--text-muted);
        }

        .btn-new-meeting {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 38px;
            padding: 0 18px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font);
            text-decoration: none;
            cursor: pointer;
            transition: background var(--transition), box-shadow var(--transition), transform var(--transition);
        }

        .btn-new-meeting:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 12px rgba(37, 99, 235, .3);
            transform: translateY(-1px);
            color: #fff;
        }

        .btn-new-meeting:active {
            transform: translateY(0);
        }

        /* MOM Button Styles */
        .btn-mom {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 32px;
            padding: 0 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            font-family: var(--font);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .btn-mom.create {
            background: var(--purple-light);
            color: var(--purple);
            border: 1px solid var(--purple-mid);
        }

        .btn-mom.create:hover {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple);
        }

        .btn-mom.edit {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-mid);
        }

        .btn-mom.edit:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .mom-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--purple-light);
            color: var(--purple);
            border: 1px solid var(--purple-mid);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .02em;
        }

        /* ─── Alerts ─── */
        .meetings-page .alert {
            border-radius: var(--radius-md);
            border: none;
            padding: 12px 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .meetings-page .alert-success {
            background: var(--success-light);
            color: #065f46;
            border-left: 3px solid var(--success);
        }

        .meetings-page .alert-danger {
            background: var(--danger-light);
            color: #991b1b;
            border-left: 3px solid var(--danger);
        }

        /* ─── Stat cards ─── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        @media(max-width:991px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width:575px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition), transform var(--transition);
            position: relative;
            overflow: hidden;
        }


        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-icon.blue {
            background: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.green {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-icon.amber {
            background: var(--warning-light);
            color: var(--warning);
        }

        .stat-icon.purple {
            background: var(--purple-light);
            color: var(--purple);
        }

        .stat-body {
            flex: 1;
            min-width: 0;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.1;
            letter-spacing: -.02em;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 500;
        }

        /* ─── Filter bar ─── */
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .filter-bar-header {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 14px;
        }

        .filter-bar-header span {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-bar-header i {
            font-size: 14px;
            color: var(--text-muted);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        @media(max-width:767px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media(max-width:575px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-size: 11.5px;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .filter-label i {
            font-size: 11px;
            color: var(--text-muted);
        }

        .filter-input {
            height: 38px;
            padding: 0 11px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-family: var(--font);
            color: var(--text-primary);
            background: var(--surface-2);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
        }

        .filter-input:focus {
            border-color: var(--primary);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        select.filter-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
            cursor: pointer;
        }

        .btn-filter {
            height: 38px;
            padding: 0 18px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background var(--transition);
            white-space: nowrap;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
        }

        .btn-reset {
            height: 38px;
            padding: 0 14px;
            background: var(--surface);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-family: var(--font);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background var(--transition), border-color var(--transition);
        }

        .btn-reset:hover {
            background: var(--surface-3);
            border-color: var(--border-hover);
            color: var(--text-primary);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            padding-bottom: 0;
        }

        /* active-filter chips */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-mid);
            padding: 3px 10px 3px 9px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 500;
        }

        .filter-chip a {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            line-height: 1;
        }

        .filter-chip a:hover {
            color: var(--primary-dark);
        }

        /* ─── Section title row ─── */
        .section-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .result-count {
            font-size: 12px;
            color: var(--text-muted);
            background: var(--surface-3);
            border: 1px solid var(--border);
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* ─── Meeting card ─── */
        .meeting-card-wrap {
            margin-bottom: 20px;
        }

        .mc {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
            transition: box-shadow var(--transition), transform var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .mc::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
        }



        /* card top strip: type icon */
        .mc-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 16px 16px 0 18px;
            gap: 10px;
        }

        .mc-type-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .mc-type-icon.physical {
            background: var(--primary-light);
            color: var(--primary);
        }

        .mc-type-icon.virtual {
            background: var(--success-light);
            color: var(--success);
        }

        .mc-type-icon.hybrid {
            background: var(--purple-light);
            color: var(--purple);
        }

        .mc-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .02em;
            flex-shrink: 0;
        }

        .mc-status-badge.scheduled {
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary-mid);
        }

        .mc-status-badge.completed {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid var(--success-mid);
        }

        .mc-status-badge.cancelled {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid var(--danger-mid);
        }

        .mc-body {
            padding: 12px 16px 14px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* title */
        .mc-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            text-decoration: none;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: color var(--transition);
        }

        .mc-title:hover {
            color: var(--primary);
        }

        /* meta chips */
        .mc-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mc-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .mc-meta-row i {
            font-size: 13px;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .mc-meta-row span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* date chip */
        .mc-date-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 4px 9px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-secondary);
            width: fit-content;
        }

        .mc-date-chip i {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* divider */
        .mc-divider {
            height: 1px;
            background: var(--border);
            margin: 0;
        }

        /* participants strip */
        .mc-participants {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .avatars {
            display: flex;
            align-items: center;
        }

        .p-av {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--surface-3);
            border: 2px solid var(--surface);
            color: var(--text-secondary);
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: -7px;
            position: relative;
            text-transform: uppercase;
            transition: transform var(--transition);
            cursor: default;
        }

        .p-av:first-child {
            margin-left: 0;
        }

        .p-av:hover {
            transform: translateY(-2px);
            z-index: 5;
        }

        .p-av.overflow {
            background: var(--surface-3);
            color: var(--text-muted);
            font-size: 9px;
            font-weight: 700;
        }

        .participants-count {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }

        .mom-chip {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: var(--purple-light);
            color: var(--purple);
            border: 1px solid var(--purple-mid);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        /* ─── Card footer / actions ─── */
        .mc-footer {
            padding: 10px 14px 12px 18px;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .mc-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 32px;
            padding: 0 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            font-family: var(--font);
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .mc-btn i {
            font-size: 13px;
        }

        .mc-btn.view {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-mid);
        }

        .mc-btn.view:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .mc-btn.edit {
            background: var(--surface);
            color: var(--text-secondary);
            border-color: var(--border);
        }

        .mc-btn.edit:hover {
            background: var(--surface-3);
            border-color: var(--border-hover);
            color: var(--text-primary);
        }

        .mc-btn.cancel-btn {
            background: var(--surface);
            color: var(--text-muted);
            border-color: var(--border);
        }

        .mc-btn.cancel-btn:hover {
            background: var(--danger-light);
            border-color: var(--danger-mid);
            color: var(--danger);
        }

        /* MOM button in footer */
        .mom-action-btn {
            margin-left: auto;
        }

        /* ─── Empty state ─── */
        .empty-meetings {
            grid-column: 1/-1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 64px 24px;
            text-align: center;
        }

        .empty-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-xl);
            background: var(--surface-3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-meetings h5 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 6px;
        }

        .empty-meetings p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0 0 20px;
            max-width: 320px;
        }

        /* ─── Meetings grid ─── */
        .meetings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media(max-width:1199px) {
            .meetings-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width:575px) {
            .meetings-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ─── Pagination ─── */
        .pagination-wrap {
            display: flex;
            justify-content: center;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* ─── Cancel Modal ─── */
        .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
            overflow: hidden;
        }

        .modal-header {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            padding: 18px 22px;
        }

        .modal-header .modal-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            font-family: var(--font);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header .modal-title i {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-sm);
            background: var(--danger-light);
            color: var(--danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .modal-body {
            padding: 22px;
        }

        .modal-body p {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            font-family: var(--font);
            line-height: 1.6;
        }

        .modal-body p strong {
            color: var(--text-primary);
        }

        .modal-body .field-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
            display: block;
            font-family: var(--font);
        }

        .modal-body textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 13px;
            font-family: var(--font);
            color: var(--text-primary);
            resize: vertical;
            min-height: 80px;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .modal-body textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .modal-footer {
            background: var(--surface-2);
            border-top: 1px solid var(--border);
            padding: 14px 22px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .modal-btn {
            height: 38px;
            padding: 0 18px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all var(--transition);
            border: 1px solid transparent;
        }

        .modal-btn.secondary {
            background: var(--surface);
            color: var(--text-secondary);
            border-color: var(--border);
        }

        .modal-btn.secondary:hover {
            background: var(--surface-3);
            border-color: var(--border-hover);
        }

        .modal-btn.danger {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
        }

        .modal-btn.danger:hover {
            background: #b91c1c;
            box-shadow: 0 4px 12px rgba(220, 38, 38, .3);
        }
    </style>
@endsection

@section('content-area')

    {{-- ─── Page header ─── --}}
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center gap-3">
            <div class="page-header-title">
                <h5 class="m-b-10">Meeting Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Meetings</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('meetings.create') }}" class="btn-new-meeting">
                        <i class="feather-plus-circle"></i> Schedule Meeting
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle"></i>
                {{ session('success') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle"></i>
                {{ session('error') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ─── Stat cards ─── --}}
        <div class="stats-row">
            <div class="stat-card blue">
                <div class="stat-icon blue"><i class="feather-calendar"></i></div>
                <div class="stat-body">
                    <div class="stat-number">{{ $upcomingCount ?? 0 }}</div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green"><i class="feather-sun"></i></div>
                <div class="stat-body">
                    <div class="stat-number">{{ $todayCount ?? 0 }}</div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon amber"><i class="feather-alert-circle"></i></div>
                <div class="stat-body">
                    <div class="stat-number">{{ $completeMeetings ?? 0 }}</div>
                    <div class="stat-label">Complete Meetings</div>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple"><i class="feather-layers"></i></div>
                <div class="stat-body">
                    <div class="stat-number">{{ $totalMeetings ?? 0 }}</div>
                    <div class="stat-label">Total Meetings</div>
                </div>
            </div>
        </div>

        {{-- ─── Filter bar ─── --}}
        <div class="filter-bar">
            <div class="filter-bar-header">
                <i class="feather-sliders"></i>
                <span>Filter Meetings</span>
            </div>
            <form method="GET" action="{{ route('meetings.index') }}">
                <div class="filter-grid">
                    <div class="filter-field">
                        {{-- <label class="filter-label"><i class="feather-flag"></i> Status</label> --}}
                        <select name="meeting_id" class="filter-input">
                            <option value="">All Meeting</option>
                            @foreach ($meetings as $meeting)
                                <option value="{{ $meeting->id }}" {{ request('meeting_id') == $meeting->id ? 'selected' : '' }}>{{ $meeting->meeting_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        {{-- <label class="filter-label"><i class="feather-flag"></i> Status</label> --}}
                        <select name="status" class="filter-input">
                            <option value="">All Status</option>
                            <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled
                            </option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed
                            </option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                            </option>
                        </select>
                    </div>
                    <div class="filter-field">
                        {{-- <label class="filter-label"><i class="feather-calendar"></i> From Date</label> --}}
                        <input type="date" name="date_from" class="filter-input" value="{{ request('date_from') }}">
                    </div>
                    <div class="filter-field">
                        {{-- <label class="filter-label"><i class="feather-calendar"></i> To Date</label> --}}
                        <input type="date" name="date_to" class="filter-input" value="{{ request('date_to') }}">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="feather-filter"></i> Apply
                        </button>
                        {{-- @if (request()->hasAny(['status', 'date_from', 'date_to'])) --}}
                        <a href="{{ route('meetings.index') }}" class="btn-reset">
                            <i class="fa fa-refresh"></i> Clear
                        </a>
                        {{-- @endif --}}
                    </div>
                </div>

                {{-- Active filter chips --}}
                @if (request()->hasAny(['status', 'date_from', 'date_to']))
                    <div class="active-filters">
                        @if (request('status'))
                            <span class="filter-chip">
                                Status: {{ ucfirst(request('status')) }}
                                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}">×</a>
                            </span>
                        @endif
                        @if (request('date_from'))
                            <span class="filter-chip">
                                From: {{ request('date_from') }}
                                <a href="{{ request()->fullUrlWithQuery(['date_from' => null]) }}">×</a>
                            </span>
                        @endif
                        @if (request('date_to'))
                            <span class="filter-chip">
                                To: {{ request('date_to') }}
                                <a href="{{ request()->fullUrlWithQuery(['date_to' => null]) }}">×</a>
                            </span>
                        @endif
                    </div>
                @endif
            </form>
        </div>

        {{-- ─── Section title ─── --}}
        <div class="section-title-row">
            <span class="section-title">
                <i class="feather-calendar" style="font-size:16px;color:var(--primary)"></i>
                All Meetings
            </span>
            <span class="result-count">{{ $meetings->total() }} result{{ $meetings->total() != 1 ? 's' : '' }}</span>
        </div>

        {{-- ─── Meetings grid ─── --}}
        <div class="meetings-grid">
            @forelse($meetings as $meeting)
                @php
                    $typeIcon = match ($meeting->meeting_type ?? 'physical') {
                        'virtual' => 'feather-video',
                        'hybrid' => 'feather-globe',
                        default => 'feather-users',
                    };
                    $typeClass = $meeting->meeting_type ?? 'physical';

                    // Check if MOM exists for this meeting (has mom_content or tasks)
                    $hasMom = !empty($meeting->mom_content) || ($meeting->tasks && $meeting->tasks->count() > 0);
                @endphp

                <div>
                    <div class="mc {{ $meeting->status }}">
                        <div class="mc-top">
                            <div class="mc-type-icon {{ $typeClass }}">
                                <i class="{{ $typeIcon }}"></i>
                            </div>
                            <span class="mc-status-badge {{ $meeting->status }}">
                                {{ ucfirst($meeting->status) }}
                            </span>
                        </div>

                        <div class="mc-body">
                            <a href="{{ route('meetings.show', $meeting->id) }}" class="mc-title">
                                {{ $meeting->title }}
                            </a>

                            <div class="mc-date-chip">
                                <i class="feather-calendar"></i>
                                {{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}
                            </div>

                            <div class="mc-meta">
                                <div class="mc-meta-row">
                                    <i class="feather-clock"></i>
                                    <span>
                                        {{ \Carbon\Carbon::parse($meeting->start_time)->format('h:i A') }}
                                        &mdash;
                                        {{ \Carbon\Carbon::parse($meeting->end_time)->format('h:i A') }}
                                    </span>
                                </div>
                                @if ($meeting->location)
                                    <div class="mc-meta-row">
                                        <i class="feather-map-pin"></i>
                                        <span>{{ $meeting->location }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="mc-divider"></div>

                            <div class="mc-participants">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="avatars">
                                        @foreach ($meeting->participants->take(4) as $p)
                                            @php
                                                $name = $p->user->name ?? '?';
                                                $initials = collect(explode(' ', $name))
                                                    ->take(2)
                                                    ->map(fn($w) => strtoupper($w[0]))
                                                    ->join('');
                                                $colors = [
                                                    '#bfdbfe',
                                                    '#a7f3d0',
                                                    '#fde68a',
                                                    '#ddd6fe',
                                                    '#fecaca',
                                                    '#fed7aa',
                                                ];
                                                $bg = $colors[$loop->index % count($colors)];
                                            @endphp
                                            <div class="p-av" title="{{ $name }}"
                                                style="background:{{ $bg }}">{{ $initials }}</div>
                                        @endforeach
                                        @if ($meeting->participants->count() > 4)
                                            <div class="p-av overflow">+{{ $meeting->participants->count() - 4 }}</div>
                                        @endif
                                    </div>
                                    <span class="participants-count">
                                        {{ $meeting->participants->count() }}
                                        attendee{{ $meeting->participants->count() != 1 ? 's' : '' }}
                                    </span>
                                </div>

                                {{-- MOM Badge if exists --}}
                                @if ($hasMom)
                                    <span class="mom-chip">
                                        <i class="feather-file-text"></i> MOM Created
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mc-footer">
                            <a href="{{ route('meetings.show', $meeting->id) }}" class="mc-btn view">
                                <i class="feather-eye"></i>
                            </a>

                           
                                {{-- Create MOM Button --}}
                                @if ($meeting->status != 'cancelled')
                                    <a href="{{ route('meetings.mom.create', ['id' => $meeting->id]) }}"
                                        class="btn-mom create mom-action-btn">
                                        <i class="feather-file-plus"></i> Create MOM
                                    </a>
                                @endif
                          

                            @if ($meeting->status == 'scheduled')
                                <a href="{{ route('meetings.edit', $meeting->id) }}" class="mc-btn edit">
                                    <i class="feather-edit-2"></i> Edit
                                </a>
                                <button type="button" class="mc-btn cancel-btn"
                                    onclick="openCancelModal({{ $meeting->id }}, '{{ addslashes($meeting->title) }}')">
                                    <i class="feather-x-circle"></i> Cancel
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

            @empty
                <div class="empty-meetings" style="grid-column:1/-1">
                    <div class="empty-icon-wrap"><i class="feather-calendar"></i></div>
                    <h5>No meetings found</h5>
                    <p>
                        @if (request()->hasAny(['status', 'date_from', 'date_to']))
                            No meetings match your current filters. Try adjusting or clearing them.
                        @else
                            You haven't scheduled any meetings yet. Get started below.
                        @endif
                    </p>
                    @if (request()->hasAny(['status', 'date_from', 'date_to']))
                        <a href="{{ route('meetings.index') }}" class="btn-new-meeting" style="text-decoration:none">
                            <i class="feather-x"></i> Clear Filters
                        </a>
                    @else
                        <a href="{{ route('meetings.create') }}" class="btn-new-meeting" style="text-decoration:none">
                            <i class="feather-plus-circle"></i> Schedule Meeting
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        {{-- ─── Pagination ─── --}}
        @if ($meetings->hasPages())
            <div class="pagination-wrap">
                {{ $meetings->links() }}
            </div>
        @endif

    </div>

@endsection

{{-- ─── Cancel Modal (Outside the loop - Single modal) ─── --}}
@section('create-modal')
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="" method="POST" id="cancelForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="feather-x-circle"></i>
                            Cancel Meeting
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to cancel <strong id="meetingTitle"></strong>. This action will
                            notify all participants. Please provide a reason.</p>
                        <label class="field-label">Cancellation Reason *</label>
                        <textarea name="reason" rows="3" required placeholder="e.g. Rescheduled to next week, venue unavailable…"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn secondary" data-bs-dismiss="modal">
                            <i class="feather-arrow-left"></i> Go Back
                        </button>
                        <button type="submit" class="modal-btn danger">
                            <i class="feather-x-circle"></i> Cancel Meeting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCancelModal(meetingId, meetingTitle) {
            // Set the meeting title in the modal
            document.getElementById('meetingTitle').innerText = meetingTitle;
            // Set the form action URL
            document.getElementById('cancelForm').action = '/meetings/' + meetingId + '/cancel';
            // Show the modal
            var myModal = new bootstrap.Modal(document.getElementById('cancelModal'));
            myModal.show();
        }
    </script>
@endsection

@section('script-area')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelectorAll('.alert .btn-close').forEach(btn => btn.click());
            }, 5000);
        });
    </script>
@endsection
