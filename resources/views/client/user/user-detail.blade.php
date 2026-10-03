@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== PROFILE SECTION ==================== */
        .profile-image-large {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        /* ==================== INFO BOXES ==================== */
        .info-box {
            padding: 12px 10px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            text-align: center;
            transition: all 0.2s;
        }

        .info-box:hover {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
        }

        .info-box h6 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .info-box p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* ==================== DETAIL ROWS ==================== */
        .detail-row {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        .detail-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
        }

        /* ==================== CONTACT LIST ==================== */
        .contact-list {
            margin-top: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }

        .contact-label i {
            color: var(--icon-color, #0D6EFD);
            font-size: 14px;
            width: 18px;
        }

        .contact-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 500;
        }

        /* ==================== TABS ==================== */
        .nav-tabs {
            border-bottom: 2px solid #f1f5f9;
            padding: 0 10px;
        }

        .nav-tabs .nav-link {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            border: none;
            padding: 12px 16px;
            margin: 0 2px;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
            background: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom: 2px solid var(--primary);
            margin-bottom: -2px;
        }

        .tab-content {
            padding: 24px 20px;
        }

        /* ==================== SECTION TITLES ==================== */
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .section-title i {
            color: var(--icon-color, #0D6EFD);
            font-size: 16px;
        }

        .about-text {
            font-size: 13px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 0;
            padding: 8px 0;
        }

        /* ==================== DOCUMENT CARDS ==================== */
        .document-card {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            transition: all 0.2s;
            height: 100%;
        }

        .document-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.08);
            transform: translateY(-2px);
        }

        .document-preview-container {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .document-thumb {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 6px;
        }

        .pdf-icon, .file-icon {
            font-size: 48px !important;
        }

        .pdf-info small, .file-info small {
            font-size: 11px;
            color: #64748b;
        }

        .document-card h6 {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 6px;
        }

        /* ==================== PAYROLL STYLES ==================== */
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .payroll-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .payroll-code {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        .payroll-effective-date {
            color: #64748b;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .payroll-effective-date i {
            color: var(--icon-color, #0D6EFD);
            font-size: 13px;
        }

        .salary-breakdown {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 20px;
        }

        .salary-breakdown table {
            margin-bottom: 0;
        }

        .salary-breakdown td {
            padding: 8px 10px;
            font-size: 12px;
            border: none;
        }

        .salary-breakdown th {
            padding: 10px;
            font-size: 12px;
            font-weight: 600;
            border: none;
        }

        .salary-breakdown .table-primary {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        .salary-breakdown .table-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .salary-breakdown .table-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .salary-breakdown .table-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .salary-breakdown .table-info {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        .salary-breakdown .table-secondary {
            background: #f1f5f9 !important;
            color: #475569;
        }

        /* ==================== BUTTONS ==================== */
        .btn-group .btn-outline-primary {
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
            padding: 6px 16px;
        }

        .btn-group .btn-outline-primary:hover {
            background: #f8fafc;
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-group .btn-outline-primary.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-light-brand {
            background: #fff;
            color: var(--primary);
            border: 1px solid #e2e8f0;
            font-size: 12px;
            padding: 8px 16px;
            border-radius: 8px;
        }

        .btn-light-brand:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }

        

        /* ==================== ALERTS ==================== */
        .alert-info {
            background: var(--primary-light);
            border: 1px solid #b8daff;
            color: var(--primary);
            padding: 16px;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info i {
            font-size: 18px;
        }

        /* ==================== CARD BODY PADDING ==================== */
        .card-body {
            padding: 24px;
        }

        /* ==================== EMPLOYEE NAME ==================== */
        .employee-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .employee-email {
            font-size: 12px;
            color: #64748b;
        }

        /* ==================== EMPLOYEE 360 (lazy tabs) ==================== */
        .lazy-loading { padding: 40px 0; text-align: center; color: #64748b; font-size: 13px; }
        .p360-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 22px; }
        .p360-kpi { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 10px; padding: 12px; }
        .p360-kpi .v { font-size: 18px; font-weight: 700; color: #1e293b; }
        .p360-kpi .l { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
        .p360-table { width: 100%; font-size: 12px; }
        .p360-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 8px 10px; border-bottom: 1px solid #edf2f7; white-space: nowrap; }
        .p360-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: top; }
        .p360-chip { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: var(--primary-light, #EFF6FF); color: var(--primary, #0D6EFD); margin: 1px 2px 1px 0; }
        .p360-chip.extra { background: #fef3c7; color: #92400e; }
        .p360-chip.muted { background: #f1f5f9; color: #64748b; }
        .p360-sub { font-size: 13px; font-weight: 600; color: #1e293b; margin: 22px 0 10px; }
        .p360-note { font-size: 12px; color: #64748b; }
        .p360-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
        /* actions (Phase 2) */
        .section-title .p360-edit { margin-left: auto; font-size: 12px; font-weight: 600; letter-spacing: 0; white-space: nowrap; }
        .section-title .p360-edit i { font-size: 12px; }
        .p360-icon-btn { color: #64748b; padding: 2px 6px; border-radius: 6px; }
        .p360-icon-btn:hover { color: var(--icon-color, #0D6EFD); background: var(--primary-light, #EFF6FF); }
        .p360-table .btn-sm { padding: 3px 10px; font-size: 11px; }
        #p360Modal .form-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        #p360Modal .modal-title { font-size: 14px; font-weight: 600; }
        #p360Modal label { font-size: 12.5px; }
        #p360Modal .p360-form-errors { font-size: 12.5px; padding: 8px 12px; }
        #p360Modal .p360-form-errors ul { margin: 0; padding-left: 18px; }

        /* ==================== EMPLOYEE 360 LAYOUT (scoped to .p360-page) ====================
           Three columns: profile card · vertical tab menu · selected tab. Blue + white, compact. */
        .p360-page { --p360-gap: 12px; --p360-radius: 10px; --p360-line: #e8edf5; --p360-soft: #f6f8fc; --p360-ink: #1e293b; --p360-mute: #64748b;
            padding: 14px !important; font-size: 12.5px; }
        .p360-layout { display: grid; grid-template-columns: 250px 196px minmax(0, 1fr); gap: var(--p360-gap); align-items: start; }
        .p360-page .p360-panel { background: #fff; border: 1px solid var(--p360-line); border-radius: var(--p360-radius); box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }

        /* --- profile card --- */
        .p360-profile { position: sticky; /* top: 12px; max-height: calc(100vh - 96px); */ overflow-y: auto; }
        .p360-profile-top { position: relative; padding: 16px 14px 12px; text-align: center; border-bottom: 1px solid var(--p360-line);
            background: linear-gradient(180deg, var(--primary-light, #EFF6FF) 0, #fff 64px); border-radius: var(--p360-radius) var(--p360-radius) 0 0; }
        .p360-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(13, 110, 253, .18); background: #fff; }
        .p360-profile-top .p360-name { font-size: 15px; font-weight: 700; color: var(--p360-ink); margin: 8px 0 2px; line-height: 1.25; }
        .p360-profile-top .p360-email { font-size: 11.5px; color: var(--p360-mute); word-break: break-all; }
        .p360-status { display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .p360-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .p360-status.on { background: #e8f7ee; color: #15803d; }
        .p360-status.off { background: #fdecec; color: #b91c1c; }
        .p360-facts { margin: 0; padding: 6px 14px 4px; }
        .p360-fact { display: flex; align-items: flex-start; gap: 8px; padding: 6px 0; border-bottom: 1px dashed var(--p360-line); }
        .p360-fact:last-child { border-bottom: 0; }
        .p360-fact i { color: var(--icon-color, #0D6EFD); font-size: 13px; margin-top: 2px; width: 14px; flex: none; }
        .p360-fact dt { font-size: 10.5px; font-weight: 600; color: var(--p360-mute); text-transform: uppercase; letter-spacing: .3px; margin: 0; }
        .p360-fact dd { font-size: 12.5px; font-weight: 600; color: var(--p360-ink); margin: 0; word-break: break-word; }
        .p360-quick { padding: 10px 14px 14px; border-top: 1px solid var(--p360-line); }
        .p360-quick-title { font-size: 10.5px; font-weight: 700; color: var(--p360-mute); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
        .p360-quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .p360-quick a { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border: 1px solid var(--p360-line); border-radius: 7px; background: #fff;
            font-size: 11.5px; font-weight: 600; color: var(--primary, #0D6EFD); text-decoration: none; transition: background .15s, border-color .15s; line-height: 1.2; }
        .p360-quick a:hover { background: var(--primary-light, #EFF6FF); border-color: #bcd0f7; }
        .p360-quick a i { font-size: 12px; flex: none; }
        .p360-quick a.danger { color: #b91c1c; }
        .p360-quick a.danger:hover { background: #fdecec; border-color: #f5c2c2; }

        /* --- vertical tab menu --- */
        .p360-nav-wrap { position: sticky; /* top: 12px; max-height: calc(100vh - 96px); */ overflow-y: auto; padding: 8px 6px; }
        .p360-vnav { display: flex; flex-direction: column; flex-wrap: nowrap; gap: 1px; margin: 0; padding: 0; border: 0; }
        .p360-vnav .nav-link { display: flex; align-items: center; gap: 9px; padding: 7px 10px; border-radius: 7px; border: 0; margin: 0;
            font-size: 12.5px; font-weight: 500; color: #475569; white-space: nowrap; position: relative; transition: background .15s, color .15s; }
        .p360-vnav .nav-link i { font-size: 14px; color: #94a3b8; width: 16px; flex: none; transition: color .15s; }
        .p360-vnav .nav-link:hover { background: var(--p360-soft); color: var(--primary, #0D6EFD); }
        .p360-vnav .nav-link:hover i { color: var(--icon-color, #0D6EFD); }
        .p360-vnav .nav-link.active { background: var(--primary-light, #EFF6FF); color: var(--primary, #0D6EFD); font-weight: 600; }
        .p360-vnav .nav-link.active i { color: var(--icon-color, #0D6EFD); }
        .p360-vnav .nav-link.active::before { content: ''; position: absolute; left: -6px; top: 6px; bottom: 6px; width: 3px; border-radius: 0 3px 3px 0;
            background: linear-gradient(180deg, #0D6EFD, #0D6EFD); }
        .p360-vnav .nav-link:focus-visible { outline: 2px solid #93c5fd; outline-offset: 1px; }

        /* --- selected tab --- */
        .p360-main { min-width: 0; }
        .p360-pane-head { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-bottom: 1px solid var(--p360-line); }
        .p360-pane-head .ic { width: 26px; height: 26px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; font-size: 13px; }
        .p360-pane-head h5 { font-size: 14px; font-weight: 700; color: var(--p360-ink); margin: 0; }
        .p360-page .tab-content { padding: 14px 16px 16px; }
        .p360-page .tab-pane { animation: p360-in .18s ease-out; }
        @keyframes p360-in { from { opacity: 0; transform: translateY(3px); } to { opacity: 1; transform: none; } }

        /* --- compact content (existing partials, restyled only here) --- */
        .p360-page .section-title { font-size: 13px; margin-bottom: 10px; padding-bottom: 7px; border-bottom-width: 1px; border-bottom-color: var(--p360-line); }
        .p360-page .section-title i { font-size: 14px; }
        .p360-page .detail-label { font-size: 11px; margin-bottom: 2px; }
        .p360-page .detail-value { font-size: 12.5px; }
        .p360-page .row.g-3 { --bs-gutter-y: .6rem; --bs-gutter-x: 1rem; }
        .p360-page .about-text { font-size: 12.5px; padding: 2px 0; }
        .p360-page .mb-4 { margin-bottom: 1rem !important; }
        .p360-page .p360-kpis { grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 8px; margin-bottom: 14px; }
        .p360-page .p360-kpi { padding: 8px 10px; border-radius: 8px; background: var(--p360-soft); border-color: var(--p360-line); }
        .p360-page .p360-kpi .v { font-size: 16px; color: var(--primary, #0D6EFD); }
        .p360-page .p360-kpi .l { font-size: 10px; }
        .p360-page .p360-table { font-size: 11.5px; }
        .p360-page .p360-table th { padding: 6px 8px; font-size: 11px; background: var(--p360-soft); border-bottom-color: var(--p360-line); }
        .p360-page .p360-table td { padding: 6px 8px; }
        .p360-page .p360-table tbody tr:hover td { background: #fafcff; }
        .p360-page .p360-sub { font-size: 12.5px; margin: 16px 0 8px; }
        .p360-page .p360-note { font-size: 11.5px; }
        .p360-page .p360-toolbar { margin-bottom: 10px; }
        .p360-page .lazy-loading { padding: 28px 0; font-size: 12px; }
        .p360-page .salary-breakdown { padding: 12px; border-radius: 8px; }
        .p360-page .salary-breakdown td, .p360-page .salary-breakdown th { padding: 5px 8px; font-size: 11.5px; }
        .p360-page .payroll-header { margin-bottom: 12px; padding-bottom: 10px; border-bottom-width: 1px; }
        .p360-page .document-preview-container { min-height: 84px; padding: 10px; margin-bottom: 8px; }
        .p360-page .document-card .card-body { padding: 10px !important; }
        .p360-page .alert-info { padding: 10px 12px; font-size: 12px; }
        .p360-page .btn-light-brand { padding: 5px 12px; font-size: 11.5px; }

        /* --- responsive: profile on top (lg), then a horizontal tab strip (md and below) --- */
        @media (max-width: 1399.98px) { .p360-layout { grid-template-columns: 230px 184px minmax(0, 1fr); } }
        @media (max-width: 1199.98px) {
            .p360-layout { grid-template-columns: 184px minmax(0, 1fr); }
            .p360-profile { grid-column: 1 / -1; position: static; max-height: none; display: grid; grid-template-columns: 220px 1fr; }
            .p360-profile-top { border-bottom: 0; border-right: 1px solid var(--p360-line); border-radius: var(--p360-radius) 0 0 var(--p360-radius); }
            .p360-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 14px; align-content: start; padding-top: 8px; }
            .p360-fact { border-bottom: 0; }
            .p360-quick { grid-column: 1 / -1; }
            .p360-quick-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        }
        @media (max-width: 991.98px) {
            .p360-layout { grid-template-columns: minmax(0, 1fr); }
            .p360-profile { grid-template-columns: 1fr; }
            .p360-profile-top { border-right: 0; border-bottom: 1px solid var(--p360-line); border-radius: var(--p360-radius) var(--p360-radius) 0 0; }
            .p360-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .p360-nav-wrap { position: sticky; top: 0; z-index: 5; max-height: none; overflow-x: auto; overflow-y: hidden; padding: 6px; }
            .p360-vnav { flex-direction: row; gap: 4px; }
            .p360-vnav .nav-link { padding: 6px 10px; }
            .p360-vnav .nav-link.active::before { left: 8px; right: 8px; top: auto; bottom: -6px; width: auto; height: 3px; border-radius: 3px 3px 0 0; }
        }
        @media (max-width: 575.98px) {
            .p360-page { padding: 8px !important; }
            .p360-facts { grid-template-columns: 1fr; }
            .p360-page .tab-content { padding: 12px; }
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .profile-image-large {
                width: 120px;
                height: 120px;
            }

            .employee-name {
                font-size: 16px;
            }

            .tab-content {
                padding: 16px;
            }

            .salary-breakdown {
                padding: 12px;
            }
        }
    </style>
@endsection

@section('content-area')
    @php
        // Employee 360 action forms (EmployeeProfileActionController::form), opened in #p360Modal.
        $p360Id = encrypt($user->id);
        $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $p360Id, 'form' => $f] + $q);
        $profileTabs = $profileTabs ?? [];
        $can = $can ?? [];
    @endphp
    @php
        // Vertical tab menu: [pane id, label, feather icon], grouped. Lazy panes (p360-*) show only when the plan has them.
        $lazyTabs = [
            'shift' => 'Shift & Week-off', 'attendance' => 'Attendance', 'leave' => 'Leave',
            'holidays' => 'Holidays', 'payroll' => 'Payroll History', 'expenses' => 'Expenses',
            'tasks' => 'Tasks & Projects', 'assets' => 'Assets', 'performance' => 'Performance',
            'policies' => 'Policies', 'activity' => 'Activity',
        ];
        $lazyIcons = [
            'shift' => 'clock', 'attendance' => 'calendar', 'leave' => 'sun', 'holidays' => 'gift', 'payroll' => 'layers',
            'expenses' => 'shopping-bag', 'tasks' => 'check-square', 'assets' => 'box', 'performance' => 'trending-up',
            'policies' => 'shield', 'activity' => 'activity',
        ];
        $lazy = fn (array $keys) => collect($keys)->filter(fn ($k) => in_array($k, $profileTabs, true))
            ->map(fn ($k) => ['p360-' . $k, $lazyTabs[$k], $lazyIcons[$k]])->values()->all();
        $navGroups = array_filter([
            'Profile' => [
                ['overviewTab', 'Overview', 'grid'], ['personalTab', 'Personal', 'user'], ['jobTab', 'Job', 'briefcase'],
                ['bankTab', 'Bank', 'credit-card'], ['payrollTab', 'Payroll', 'dollar-sign'], ['documentsTab', 'Documents', 'file-text'],
            ],
            'Time & Leave' => $lazy(['shift', 'attendance', 'leave', 'holidays']),
            'Pay & Spend' => $lazy(['payroll', 'expenses']),
            'Work' => $lazy(['tasks', 'assets', 'performance']),
            'Rules & History' => $lazy(['policies', 'activity']),
        ]);
        $joining = $user->jobDetails && $user->jobDetails->joining_date ? date('d M, Y', strtotime($user->jobDetails->joining_date)) : 'N/A';
        $active = (int) $user->status === 1;
    @endphp

    <x-ui.page-header title="Employee Details" :crumbs="[['label' => 'Employees', 'url' => route('employee.index')]]" :back="route('employee.index')">
        <x-slot:actions>
            <a href="{{ route('employee.index', ['edit_id' => encrypt($user->id), 'edit_code' => $user->employee_id]) }}"
                class="btn btn-sm btn-primary">
                <i class="feather-edit me-2"></i>
                <span>Edit Employee</span>
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content p360-page">
        <div class="p360-layout">
            {{-- Left: compact profile card + quick actions --}}
            <aside class="p360-panel p360-profile" aria-label="Employee summary">
                <div class="p360-profile-top">
                    @if ($user->basicDetails && $user->basicDetails->profile_image)
                        <img src="{{ file_url($user->basicDetails->profile_image, 'profile_photo') }}" class="p360-avatar" alt="{{ $user->name }}">
                    @else
                        <img src="{{ asset('assets/images/avatar/1.png') }}" class="p360-avatar" alt="{{ $user->name }}">
                    @endif
                    <div class="p360-name">{{ $user->name }}</div>
                    <div class="p360-email">{{ $user->email }}</div>
                    <span class="p360-status {{ $active ? 'on' : 'off' }}">{{ $active ? 'Active' : 'Inactive' }}</span>
                </div>

                <dl class="p360-facts">
                    <div class="p360-fact"><i class="feather-hash"></i><div><dt>Employee ID</dt><dd>{{ $user->employee_id ?: 'N/A' }}</dd></div></div>
                    <div class="p360-fact"><i class="feather-award"></i><div><dt>Designation</dt><dd>{{ $user->jobDetails->designation_name ?? 'N/A' }}</dd></div></div>
                    <div class="p360-fact"><i class="feather-layers"></i><div><dt>Department</dt><dd>{{ $user->jobDetails->department_name ?? 'N/A' }}</dd></div></div>
                    <div class="p360-fact"><i class="feather-phone"></i><div><dt>Phone</dt><dd>{{ $user->contact ?: 'N/A' }}</dd></div></div>
                    <div class="p360-fact"><i class="feather-mail"></i><div><dt>Personal email</dt><dd>{{ $user->basicDetails->personal_email ?? 'N/A' }}</dd></div></div>
                    <div class="p360-fact"><i class="feather-calendar"></i><div><dt>Joining date</dt><dd>{{ $joining }}</dd></div></div>
                </dl>

                <div class="p360-quick">
                    <div class="p360-quick-title">Quick actions</div>
                    <div class="p360-quick-grid">
                        @if (in_array('attendance', $profileTabs, true))
                            <a href="#" data-p360-open="{{ $form('attendance-mark') }}" data-title="Mark attendance — {{ $user->name }}" data-size="md">
                                <i class="feather-check-square"></i>Mark attendance</a>
                        @endif
                        @if (in_array('shift', $profileTabs, true) && !empty($can['custom_shifts']))
                            <a href="#" data-p360-open="{{ $form('shift-assign') }}" data-title="Assign shift to {{ $user->name }}" data-size="md">
                                <i class="feather-clock"></i>Assign shift</a>
                        @endif
                        @if (in_array('leave', $profileTabs, true) && !empty($can['leave_manage']))
                            <a href="#" data-p360-open="{{ $form('leave-apply') }}" data-title="Apply leave for {{ $user->name }}" data-size="md">
                                <i class="feather-sun"></i>Apply leave</a>
                            <a href="#" data-p360-open="{{ $form('leave-credit') }}" data-title="Credit leave">
                                <i class="feather-plus-circle"></i>Credit leave</a>
                        @endif
                        @if (in_array('assets', $profileTabs, true) && !empty($can['assets_manage']))
                            <a href="#" data-p360-open="{{ $form('asset-assign') }}" data-title="Assign an asset to {{ $user->name }}">
                                <i class="feather-box"></i>Assign asset</a>
                        @endif
                        <a href="#" data-p360-open="{{ $form('password') }}" data-title="Reset password">
                            <i class="feather-key"></i>Reset password</a>
                        <a href="#" class="{{ $active ? 'danger' : '' }}" data-p360-open="{{ $form('status') }}"
                            data-title="{{ $active ? 'Deactivate employee' : 'Activate employee' }}">
                            <i class="feather-power"></i>{{ $active ? 'Deactivate' : 'Activate' }}</a>
                    </div>
                </div>
            </aside>

            {{-- Middle: vertical tab menu (Bootstrap tabs — same targets as before) --}}
            <nav class="p360-panel p360-nav-wrap" aria-label="Employee sections">
                <ul class="nav p360-vnav profile-tabs" id="myTab" role="tablist">
                    @foreach ($navGroups as $items)
                        @foreach ($items as [$target, $label, $icon])
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link {{ $target === 'overviewTab' ? 'active' : '' }}" data-bs-toggle="tab"
                                    data-bs-target="#{{ $target }}" data-icon="{{ $icon }}" role="tab"
                                    aria-selected="{{ $target === 'overviewTab' ? 'true' : 'false' }}"><i class="feather-{{ $icon }}"></i><span>{{ $label }}</span></a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </nav>

            {{-- Right: the selected tab --}}
            <section class="p360-panel p360-main">
                <div class="p360-pane-head">
                    <span class="ic"><i class="feather-grid" id="p360PaneIcon"></i></span>
                    <h5 id="p360PaneTitle">Overview</h5>
                </div>
                    <div class="tab-content">
                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="overviewTab" role="tabpanel">
                            <div id="p360-overview" data-lazy="{{ route('employee.profile.tab', ['id' => encrypt($user->id), 'tab' => 'overview']) }}">
                                <div class="lazy-loading">Loading summary…</div>
                            </div>
                            <div class="mb-4">
                                <h5 class="section-title">
                                    <i class="feather-user"></i>
                                    About
                                </h5>
                                <p class="about-text">
                                    {{ $user->basicDetails->about ?? 'No description available for this employee.' }}
                                </p>
                            </div>

                            <div>
                                <h5 class="section-title">
                                    <i class="feather-info"></i>
                                    Basic Information
                                    <a href="#" class="p360-edit" data-p360-open="{{ $form('login') }}" data-title="Edit name &amp; login details" data-size="md">
                                        <i class="feather-edit-2"></i> Edit</a>
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Full Name</div>
                                        <div class="detail-value">{{ $user->name }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Employee ID</div>
                                        <div class="detail-value">{{ $user->employee_id }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Date of Birth</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->dob)
                                                {{ date('d M, Y', strtotime($user->basicDetails->dob)) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Gender</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->gender)
                                                @php
                                                    $genderMap = ['m' => 'Male', 'f' => 'Female', 'o' => 'Other'];
                                                @endphp
                                                {{ $genderMap[$user->basicDetails->gender] ?? $user->basicDetails->gender }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Official Email</div>
                                        <div class="detail-value">{{ $user->email }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Personal Email</div>
                                        <div class="detail-value">{{ $user->basicDetails->personal_email ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Phone</div>
                                        <div class="detail-value">{{ $user->contact }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Alternate Phone</div>
                                        <div class="detail-value">{{ $user->basicDetails->alternate_phone ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Blood Group</div>
                                        <div class="detail-value">{{ $user->basicDetails->blood_group ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade" id="personalTab" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="section-title">
                                    <i class="feather-users"></i>
                                    Personal & Family Details
                                    <a href="#" class="p360-edit" data-p360-open="{{ $form('personal') }}" data-title="Edit personal details" data-size="lg">
                                        <i class="feather-edit-2"></i> Edit</a>
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Father's Name</div>
                                        <div class="detail-value">{{ $user->basicDetails->father_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Mother's Name</div>
                                        <div class="detail-value">{{ $user->basicDetails->mother_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Marital Status</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->marital_status)
                                                {{ ucfirst($user->basicDetails->marital_status) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Nationality</div>
                                        <div class="detail-value">{{ $user->basicDetails->nationality ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Languages</div>
                                        <div class="detail-value">
                                            @if (isset($languageNames) && !empty($languageNames))
                                                {{ implode(', ', $languageNames) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Aadhaar Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->aadhaar_no ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">PAN Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->pan_no ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Passport Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->passport_number ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h5 class="section-title">
                                    <i class="feather-map-pin"></i>
                                    Address Details
                                    <a href="#" class="p360-edit" data-p360-open="{{ $form('address') }}" data-title="Edit address" data-size="lg">
                                        <i class="feather-edit-2"></i> Edit</a>
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Country</div>
                                        <div class="detail-value">{{ $user->location->countryDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">State</div>
                                        <div class="detail-value">{{ $user->location->stateDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">City</div>
                                        <div class="detail-value">{{ $user->location->cityDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Pin Code</div>
                                        <div class="detail-value">{{ $user->location->pincode ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">Current Address</div>
                                        <div class="detail-value">{{ $user->location->address ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">Permanent Address</div>
                                        <div class="detail-value">{{ $user->location->permanent_address ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Job Details Tab -->
                        <div class="tab-pane fade" id="jobTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-briefcase"></i>
                                Employment Details
                                <a href="#" class="p360-edit" data-p360-open="{{ $form('job') }}" data-title="Edit job details" data-size="lg">
                                    <i class="feather-edit-2"></i> Edit</a>
                            </h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="detail-label">Designation</div>
                                    <div class="detail-value">{{ $user->jobDetails->designation_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Department</div>
                                    <div class="detail-value">{{ $user->jobDetails->department_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Reporting Head(s)</div>
                                    <div class="detail-value">
                                        @forelse (($user->jobDetails->reporting_heads ?? []) as $head)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $head['name'] }}{{ $head['is_primary'] ? ' (Primary)' : '' }}
                                            </span>
                                        @empty
                                            N/A
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Employment Type</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->employment_type)
                                            {{ ucfirst(str_replace('-', ' ', $user->jobDetails->employment_type)) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Joining Date</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->joining_date)
                                            {{ date('d M, Y', strtotime($user->jobDetails->joining_date)) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Salary</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->salary)
                                            ₹ {{ number_format($user->jobDetails->salary, 2) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="bankTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-credit-card"></i>
                                Bank Account Details
                                <a href="#" class="p360-edit" data-p360-open="{{ $form('bank') }}" data-title="Edit bank details" data-size="md">
                                    <i class="feather-edit-2"></i> Edit</a>
                            </h5>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="detail-label">Bank Name</div>
                                    <div class="detail-value">{{ $user->bankDetails->bank_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">Account Number</div>
                                    <div class="detail-value">{{ $user->bankDetails->account_number ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">IFSC Code</div>
                                    <div class="detail-value">{{ $user->bankDetails->ifsc ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">Branch Name</div>
                                    <div class="detail-value">{{ $user->bankDetails->branch_name ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payroll Tab -->
                        <div class="tab-pane fade" id="payrollTab" role="tabpanel">
                            @php
                                $currentPayroll = null;
                                if ($user->currentPayroll && $user->currentPayroll->count() > 0) {
                                    $currentPayroll = $user->currentPayroll;;
                                }
                            @endphp

                            @if ($currentPayroll)
                                <div class="payroll-header">
                                    <div>
                                        <h5 class="fw-bold mb-1">Current Payroll</h5>
                                        <div class="payroll-effective-date">
                                            <i class="feather-calendar"></i>
                                            Effective from {{ date('d M, Y', strtotime($currentPayroll->effective_from)) }}
                                        </div>
                                    </div>
                                    <span class="payroll-code">
                                        <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary active payroll-code" id="viewMonthlyBtn">Monthly</button>
                                        <button type="button" class="btn btn-outline-primary payroll-code" id="viewYearlyBtn">Yearly</button>
                                    </div>
                                    </span>
                                </div>
                                <!-- Monthly View -->
                                <div id="monthlyView">
                                    <div class="salary-breakdown">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-primary">
                                                        <th colspan="2">Earnings (Monthly)</th>
                                                    </tr>
                                                    <tr><td>Basic Salary</td><td class="text-end">₹ {{ number_format($currentPayroll->basic_salary, 2) }}</td></tr>
                                                    <tr><td>HRA</td><td class="text-end">₹ {{ number_format($currentPayroll->hra, 2) }}</td></tr>
                                                    <tr><td>Conveyance</td><td class="text-end">₹ {{ number_format($currentPayroll->conveyence, 2) }}</td></tr>
                                                    <tr><td>Medical</td><td class="text-end">₹ {{ number_format($currentPayroll->medical_allowance, 2) }}</td></tr>
                                                    <tr><td>Children Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->children_allowance, 2) }}</td></tr>
                                                    <tr><td>Post Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->post_allowance, 2) }}</td></tr>
                                                    <tr><td>LTA</td><td class="text-end">₹ {{ number_format($currentPayroll->leave_travel_allowance, 2) }}</td></tr>
                                                    <tr><td>Monthly Incentive</td><td class="text-end">₹ {{ number_format($currentPayroll->monthly_incentive, 2) }}</td></tr>
                                                    <tr><td>Special Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->special_allowance, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Total Allowances</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->hra + $currentPayroll->conveyence + $currentPayroll->medical_allowance + $currentPayroll->children_allowance + $currentPayroll->post_allowance + $currentPayroll->leave_travel_allowance + $currentPayroll->monthly_incentive + $currentPayroll->special_allowance, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-warning">
                                                        <td><strong>Gross Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-danger">
                                                        <th colspan="2">Deductions (Monthly)</th>
                                                    </tr>
                                                    <tr><td>Employee PF</td><td class="text-end">₹ {{ number_format($currentPayroll->provident_fund, 2) }}</td></tr>
                                                    <tr><td>Employee ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->esi, 2) }}</td></tr>
                                                    <tr><td>Professional Tax</td><td class="text-end">₹ {{ number_format($currentPayroll->professional_tax, 2) }}</td></tr>
                                                    <tr><td>TDS</td><td class="text-end">₹ {{ number_format($currentPayroll->tds, 2) }}</td></tr>
                                                    <tr class="table-danger">
                                                        <td><strong>Total Deductions</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->provident_fund + $currentPayroll->esi + $currentPayroll->professional_tax + $currentPayroll->tds, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-success">
                                                        <td><strong>Net Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->net_salary, 2) }}</strong></td>
                                                    </tr>
                                                </table>

                                                <table class="table table-sm mt-3">
                                                    <tr class="table-secondary">
                                                        <th colspan="2">Employer Contributions</th>
                                                    </tr>
                                                    <tr><td>Employer PF</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_provident_fund, 2) }}</td></tr>
                                                    <tr><td>Employer ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_esi, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Monthly CTC</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary + $currentPayroll->employer_provident_fund + $currentPayroll->employer_esi, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Yearly View (Initially Hidden) -->
                                <div id="yearlyView" style="display: none;">
                                    <div class="salary-breakdown">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-primary">
                                                        <th colspan="2">Earnings (Yearly)</th>
                                                    </tr>
                                                    <tr><td>Basic Salary</td><td class="text-end">₹ {{ number_format($currentPayroll->basic_salary * 12, 2) }}</td></tr>
                                                    <tr><td>HRA</td><td class="text-end">₹ {{ number_format($currentPayroll->hra * 12, 2) }}</td></tr>
                                                    <tr><td>Conveyance</td><td class="text-end">₹ {{ number_format($currentPayroll->conveyence * 12, 2) }}</td></tr>
                                                    <tr><td>Medical</td><td class="text-end">₹ {{ number_format($currentPayroll->medical_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Children Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->children_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Post Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->post_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>LTA</td><td class="text-end">₹ {{ number_format($currentPayroll->leave_travel_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Monthly Incentive</td><td class="text-end">₹ {{ number_format($currentPayroll->monthly_incentive * 12, 2) }}</td></tr>
                                                    <tr><td>Special Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->special_allowance * 12, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Total Allowances</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format(($currentPayroll->hra + $currentPayroll->conveyence + $currentPayroll->medical_allowance + $currentPayroll->children_allowance + $currentPayroll->post_allowance + $currentPayroll->leave_travel_allowance + $currentPayroll->monthly_incentive + $currentPayroll->special_allowance) * 12, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-warning">
                                                        <td><strong>Gross Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary * 12, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-danger">
                                                        <th colspan="2">Deductions (Yearly)</th>
                                                    </tr>
                                                    <tr><td>Employee PF</td><td class="text-end">₹ {{ number_format($currentPayroll->provident_fund * 12, 2) }}</td></tr>
                                                    <tr><td>Employee ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->esi * 12, 2) }}</td></tr>
                                                    <tr><td>Professional Tax</td><td class="text-end">₹ {{ number_format($currentPayroll->professional_tax * 12, 2) }}</td></tr>
                                                    <tr><td>TDS</td><td class="text-end">₹ {{ number_format($currentPayroll->tds * 12, 2) }}</td></tr>
                                                    <tr class="table-danger">
                                                        <td><strong>Total Deductions</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format(($currentPayroll->provident_fund + $currentPayroll->esi + $currentPayroll->professional_tax + $currentPayroll->tds) * 12, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-success">
                                                        <td><strong>Net Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->net_salary * 12, 2) }}</strong></td>
                                                    </tr>
                                                </table>

                                                <table class="table table-sm mt-3">
                                                    <tr class="table-secondary">
                                                        <th colspan="2">Employer Contributions</th>
                                                    </tr>
                                                    <tr><td>Employer PF</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_provident_fund * 12, 2) }}</td></tr>
                                                    <tr><td>Employer ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_esi * 12, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Annual CTC</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->ctc, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="feather-info me-2"></i>
                                    No payroll information available for this employee.
                                </div>
                            @endif
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documentsTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-file-text"></i>
                                Employee Documents
                                <a href="#" class="p360-edit" data-p360-open="{{ $form('document') }}" data-title="Upload a document" data-size="md">
                                    <i class="feather-upload"></i> Upload</a>
                            </h5>

                            <div class="row g-3">
                                @if (isset($user->documents) && count($user->documents) > 0)
                                    @foreach ($user->documents as $document)
                                        @php
                                            $path = $document['path'];
                                            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
                                            $isImage = in_array($extension, $imageExtensions);
                                            $isPdf = $extension === 'pdf';
                                            $filename = basename($path);
                                        @endphp

                                        <div class="col-md-6 col-lg-4">
                                            <div class="document-card">
                                                <div class="card-body text-center p-3">
                                                    <div class="document-preview-container">
                                                        @if ($isImage)
                                                            <a href="{{ file_url($path, 'employee_document') }}" target="_blank">
                                                                <img src="{{ file_url($path, 'employee_document') }}" class="document-thumb"
                                                                    alt="{{ $document['label'] }}">
                                                            </a>
                                                        @elseif($isPdf)
                                                            <div class="text-center">
                                                                <i class="feather-file-text pdf-icon" style="color: #ff3b30;"></i>
                                                                <div class="mt-2">
                                                                    <small class="text-muted d-block">{{ $filename }}</small>
                                                                    <small class="text-muted">PDF Document</small>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="text-center">
                                                                <i class="feather-file file-icon" style="color: #6c757d;"></i>
                                                                <div class="mt-2">
                                                                    <small class="text-muted d-block">{{ $filename }}</small>
                                                                    <small class="text-muted">{{ strtoupper($extension) }} File</small>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <h6>{{ $document['label'] }}</h6>
                                                    @if (!empty($document['name']))
                                                        <small class="text-muted d-block mb-1">{{ $document['name'] }}</small>
                                                    @endif

                                                    <div class="d-flex gap-2 justify-content-center">
                                                        <a href="{{ file_url($path, 'employee_document') }}" target="_blank"
                                                            class="btn btn-sm btn-light-brand">
                                                            <i class="feather-eye"></i>
                                                        </a>
                                                        <a href="{{ file_url($path, 'employee_document') }}" download="{{ $filename }}"
                                                            class="btn btn-sm btn-primary">
                                                            <i class="feather-download"></i>
                                                        </a>
                                                        <a href="#" class="btn btn-sm btn-light-brand text-danger" title="Remove"
                                                            data-p360-open="{{ $form('document', ['remove' => $document['id']]) }}" data-title="Remove document">
                                                            <i class="feather-trash-2"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="col-12">
                                        <x-ui.empty-state icon="file" title="No documents"
                                            subtitle="No documents have been uploaded for this employee yet." />
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Employee 360: loaded on first open (EmployeeProfileController::tab) --}}
                        @foreach ($lazyTabs as $key => $label)
                            @if (in_array($key, $profileTabs, true))
                                <div class="tab-pane fade" id="p360-{{ $key }}" role="tabpanel"
                                    data-lazy="{{ route('employee.profile.tab', ['id' => encrypt($user->id), 'tab' => $key]) }}">
                                    <div class="lazy-loading">Loading {{ strtolower($label) }}…</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
            </section>
        </div>
    </div>
@endsection

@section('create-modal')
    {{-- Employee 360: every action form opens here (see the script below) --}}
    <div class="modal fade" id="p360Modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // View toggle for payroll
            $('#viewMonthlyBtn').click(function() {
                $(this).addClass('active');
                $('#viewYearlyBtn').removeClass('active');
                $('#monthlyView').show();
                $('#yearlyView').hide();
            });

            // Employee 360 lazy tabs: fetch a pane's partial the first time it is shown.
            // A pane remembers the URL it last showed (e.g. a chosen month) so a refresh keeps it.
            function loadPane($pane, url) {
                url = url || $pane.data('current') || $pane.data('lazy');
                $pane.data('current', url).html('<div class="lazy-loading">Loading…</div>');
                $.get(url)
                    .done(html => { $pane.html(html).attr('data-loaded', '1'); })
                    .fail(xhr => $pane.html('<div class="alert alert-info"><i class="feather-alert-circle"></i>' +
                        (xhr.status === 403 ? 'This module is not included in your company\'s plan.' : 'Could not load this section. Please try again.') + '</div>'));
            }
            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
                const target = $(this).data('bs-target');
                const $pane = $(target);
                if ($pane.data('lazy') && !$pane.attr('data-loaded')) loadPane($pane);
                history.replaceState(null, '', target); // a page refresh comes back to this tab
                // Panel heading follows the chosen tab; on narrow screens keep it visible in the strip.
                $('#p360PaneTitle').text($(this).text().trim());
                $('#p360PaneIcon').attr('class', 'feather-' + ($(this).data('icon') || 'grid'));
                this.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            });
            // In-tab navigation (month / year pickers) reloads just that pane.
            $(document).on('click', '[data-p360-reload]', function(e) {
                e.preventDefault();
                loadPane($(this).closest('[data-lazy]'), $(this).attr('href'));
            });
            loadPane($('#p360-overview'));

            if (/^#[\w-]+$/.test(location.hash)) {
                const link = document.querySelector('#myTab [data-bs-target="' + location.hash + '"]');
                if (link) bootstrap.Tab.getOrCreateInstance(link).show();
            }
            const flash = sessionStorage.getItem('p360Flash');
            if (flash) {
                sessionStorage.removeItem('p360Flash');
                toastr.success(flash);
            }

            // ---- Employee 360 actions: a form partial loaded into #p360Modal, posted by AJAX
            // to the module's own endpoint (EmployeeProfileActionController::form).
            const $modal = $('#p360Modal');
            const modal = () => bootstrap.Modal.getOrCreateInstance($modal[0]);

            $(document).on('click', '[data-p360-open]', function(e) {
                e.preventDefault();
                const $trigger = $(this);
                $modal.find('.modal-title').text($trigger.data('title') || 'Update');
                $modal.find('.modal-dialog').toggleClass('modal-lg', $trigger.data('size') === 'lg');
                const $body = $modal.find('.modal-body').html('<div class="lazy-loading">Loading…</div>');
                modal().show();
                $.get($trigger.attr('data-p360-open'))
                    .done(html => {
                        $body.html(html);
                        $body.find('.p360-select2').select2({ dropdownParent: $modal, width: '100%' });
                    })
                    .fail(xhr => $body.html($('<div class="alert alert-info mb-0">').text(
                        (xhr.responseJSON && xhr.responseJSON.message) || 'Could not open this form. Please try again.')));
            });

            function showErrors($box, res) {
                const messages = [];
                if (res && res.errors) Object.values(res.errors).forEach(v => [].concat(v).forEach(m => messages.push(m)));
                if (!messages.length) messages.push((res && res.message) || 'Something went wrong. Please try again.');
                $box.html($('<ul>').append(messages.map(m => $('<li>').text(m)))).removeClass('d-none');
                $box[0].scrollIntoView({ block: 'nearest' });
            }

            function afterAction(keys, message) {
                if (keys.includes('page')) { // server-rendered tabs
                    sessionStorage.setItem('p360Flash', message);
                    return location.reload();
                }
                toastr.success(message);
                keys.forEach(key => {
                    const $pane = $('#p360-' + key);
                    if ($pane.length && $pane.attr('data-loaded')) loadPane($pane);
                });
                loadPane($('#p360-overview'));
            }

            $(document).on('submit', 'form[data-p360-form]', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $button = $form.find('[type=submit]');
                const $errors = $form.find('.p360-form-errors').addClass('d-none').empty();
                const label = $button.html();

                let url = $form.attr('action');
                const urlField = $form.data('url-field');
                if (urlField) url = url.replace('__ID__', encodeURIComponent($form.find('[name="' + urlField + '"]').val() || ''));

                $button.prop('disabled', true).text('Saving…');
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                }).done(res => {
                    if (res && (res.success === false || res.status === false)) return showErrors($errors, res);
                    modal().hide();
                    afterAction(String($form.data('reload') || '').split(',').filter(Boolean), (res && res.message) || 'Saved.');
                }).fail(xhr => showErrors($errors, xhr.responseJSON || {
                    message: xhr.status === 403 ? 'You do not have permission to do this.' : null,
                })).always(() => $button.prop('disabled', false).html(label));
            });

            $('#viewYearlyBtn').click(function() {
                $(this).addClass('active');
                $('#viewMonthlyBtn').removeClass('active');
                $('#yearlyView').show();
                $('#monthlyView').hide();
            });
        });
    </script>
@endsection