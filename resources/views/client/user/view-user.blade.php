@extends('client.layout.master')

@section('style')
    <style>
     /* Simple consistent styling */
        .custom-employee-dropdown .btn {
            height: 36px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 13px;
            padding: 0 12px;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
        }

        /* Consistent initials style - same color for all */
        .employee-initials,
        .employee-initials-sm {
            width: 28px;
            height: 28px;
            background: var(--primary);
            /* Single consistent color */
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .employee-initials-sm {
            width: 24px;
            height: 24px;
            font-size: 11px;
        }

        /* Dropdown menu styling */
        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 8px;
            max-height: 300px;
            overflow-y: auto;

            min-width: 250px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: var(--primary) !important;
            opacity: 0.8;
        }

        /* .employee-name/.employee-email are centralized in client.layout.head
           (12px/600-weight name, 8px email) — no local override here. */

        .text-muted {
            color: #64748b !important;
        }

        /* ==================== STATUS TOGGLE ==================== */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-switch {
            position: relative;
            width: 32px;
            height: 17px;
            background: #e2e8f0;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .toggle-switch.active {
            background: var(--primary);
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        .toggle-switch .toggle-circle {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 13px;
            height: 13px;
            background: white;
            border-radius: 50%;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .toggle-switch.active .toggle-circle {
            left: 17px;
        }

        .status-label {
            font-size: 11px;
            font-weight: 500;
        }

        .status-label.active {
            color: var(--primary);
        }

        .status-label.inactive {
            color: #ef4444;
        }

        /* Add to your style section */
        .status-label.registered {
            color: var(--primary);
        }

        .status-label.not-registered {
            color: #64748b;
        }

        .toggle-switch.face-toggle.active {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        .toggle-switch.location-toggle.active {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
        }

        /* ==================== ATTENDANCE TYPE SELECT ==================== */
        .attendance-type-select {
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 140px;
        }

        .attendance-type-select:hover {
            border-color: var(--primary);
            background-color: white !important;
        }

        .attendance-type-select:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
            outline: none;
        }

        .attendance-type-select:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .attendance-type-select option {
            padding: 4px 8px;
        }

        /* ==================== MODERN FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .filter-title span {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
        }

        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 12px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .clear-all-link:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .clear-all-link i {
            font-size: 14px;
        }

        /* ==================== COMPACT FILTER ROW ==================== */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 140px;
        }

        .filter-item.search {
            flex: 1 1 200px;
            min-width: 180px;
        }

        .filter-item .form-label {
            display: none;
        }

        /* Search Box */
        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }

        .search-wrapper .form-control {
            width: 100%;
            height: 36px;
            padding: 6px 12px 6px 32px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .search-wrapper .form-control:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
            outline: none;
        }

        .search-wrapper .form-control::placeholder {
            color: #94a3b8;
            font-size: 12px;
        }

        /* Select Dropdowns */
        .filter-select {
            width: 100%;
            height: 36px;
            padding: 6px 28px 6px 10px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-select:focus {
            background-color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-focus);
            outline: none;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #94a3b8;
        }

        /* Apply Button */
        .apply-btn {
            height: 36px;
            padding: 0 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .apply-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .apply-btn i {
            font-size: 14px;
        }

        /* Reset Button */
        .reset-btn {
            height: 36px;
            padding: 0 12px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .reset-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        /* ==================== ACTIVE FILTER TAGS ==================== */
        .active-filters {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .active-filters-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .filter-tag {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 3px 10px 3px 8px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .filter-tag i {
            color: var(--primary);
            font-size: 11px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .filter-tag .remove-tag:hover {
            color: #ef4444;
        }

        .filter-tag.clear-all {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        .filter-tag.clear-all:hover {
            background: var(--primary);
            color: white;
        }

        .filter-tag.clear-all i {
            color: currentColor;
        }

        /* .stats-grid/.stats-card/.stats-icon/.stats-info are centralized
           in client.layout.head (single blue-only theme) — no local copy. */

        /* .employee-info/.employee-details/.employee-name/.employee-email are
           centralized in client.layout.head — no local copy. .employee-avatar
           IS overridden below (matches the Monthly Payroll list's no-image
           initials style instead of the centralized photo-only version, since
           this column falls back to initials, not an external avatar image). */

        /* ==================== TABLE STYLES ==================== */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
            border-bottom-width: 1px;
            padding: 12px 16px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            /* font-size: 13px; */
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* ==================== BADGES ==================== */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge i {
            font-size: 11px;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .badge.bg-primary {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .badge.bg-info {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        /* ==================== ROLE TAGS ==================== */
        .role-tag {
            padding: 4px 10px;
            background: #f1f5f9;
            color: #334155;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }

        .role-tag.admin {
            background: var(--primary);
            color: white;
        }

        .role-tag.manager {
            background: var(--primary);
            color: white;
        }

        /* "Add Employee" — theme.min.css's .btn-primary sets
           background-color/border-color with !important (vendor default
           #3454d1), which beats any plain override regardless of source
           order or specificity. This page's theme is #1e3a8a, so the
           override has to fight !important with !important. */
        .add-employee-btn {
            background: linear-gradient(135deg, #1e3a8a, #2563eb) !important;
            border-color: #1e3a8a !important;
            font-size: 12px;
        }

        .add-employee-btn:hover {
            background: linear-gradient(135deg, #16306f, #1e40af) !important;
            border-color: #16306f !important;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .action-btn:hover {
            background: white;
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(30, 58, 138, 0.1);
        }

        .action-btn i {
            font-size: 14px;
        }

        .dropdown-item {
            font-size: 12px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-item i {
            font-size: 14px;
            color: #64748b;
        }

        .dropdown-item:hover i {
            color: var(--primary);
        }

        /* ==================== EMPLOYEE ID BADGE ==================== */
        .employee-id {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            font-family: monospace;
            display: inline-block;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 6px !important;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            margin: 24px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: #334155;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .filter-row {
                gap: 8px;
            }

            .filter-item {
                flex: 1 1 calc(33.333% - 8px);
                min-width: 120px;
            }

            .filter-item.search {
                flex: 1 1 100%;
                min-width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .filter-wrapper {
                padding: 12px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-item {
                width: 100%;
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .apply-btn,
            .reset-btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table th,
            .table td {
                padding: 8px 12px;
                /* font-size: 12px; */
            }
        }

        /* ==================== ADD/EDIT EMPLOYEE DRAWER — STEP WIZARD ====================
           Shared by partials.employee-wizard-form, embedded once in
           #employeeDrawer (see @@section('create-modal')). Ported from the
           former standalone add-user/update-user pages, retoned onto the
           app's blue design tokens instead of ad hoc indigo/bootstrap-blue. */
        .ui-drawer,
        .ui-drawer .form-control,
        .ui-drawer .form-select,
        .ui-drawer label,
        .ui-drawer p,
        .ui-drawer span,
        .ui-drawer .btn {
            font-size: 10px;
        }

        .ui-drawer .form-label {
            font-weight: 400;
        }

        .ui-drawer .form-group {
            margin-bottom: 10px;
        }

        /* Select2 multi-select styling now lives globally in
           public/assets/css/theme-custom.css — applies to every Select2
           field app-wide, not just this drawer. Do not re-add a scoped
           copy here; edit the global rules instead. */

        .ui-drawer .step-content {
            display: none;
        }

        .ui-drawer .step-content.active {
            display: block;
        }

        /* Next / Previous / Cancel / Submit — smaller buttons, unified to
           the app's #1e3a8a blue theme instead of the default
           secondary-gray/success-green Bootstrap variants. theme.min.css
           sets .btn-primary/.btn-secondary/.btn-success background-color
           and border-color with !important (vendor defaults), which beats
           any plain override here regardless of selector specificity — so
           every color property below also has to be !important. */
        .ui-drawer .form-navigation .btn {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 6px;
        }

        .ui-drawer #nextBtn,
        .ui-drawer #submitBtn {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
            color: #fff !important;
        }

        .ui-drawer #nextBtn:hover,
        .ui-drawer #submitBtn:hover {
            background: var(--primary-dark) !important;
            border-color: var(--primary-dark) !important;
        }

        .ui-drawer #prevBtn,
        .ui-drawer .form-navigation > .btn-light {
            background: #fff !important;
            border: 1px solid var(--primary) !important;
            color: var(--primary) !important;
        }

        .ui-drawer #prevBtn:hover,
        .ui-drawer .form-navigation > .btn-light:hover {
            background: var(--primary-light) !important;
            color: var(--primary) !important;
        }

        .ui-drawer #prevBtn:disabled {
            background: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #94a3b8 !important;
        }

        .ui-drawer .step-wizard {
            background: var(--surface-2, #f8fafc);
            padding: 16px 0;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .ui-drawer .step-indicator {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 0 auto;
        }

        .ui-drawer .step-indicator::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 30px;
            right: 30px;
            height: 2px;
            background: var(--border, #e2e8f0);
            z-index: 1;
        }

        .ui-drawer .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .ui-drawer .step-number {
            width: 32px;
            height: 32px;
            background: #fff;
            border: 2px solid #cbd5e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            font-weight: 600;
            font-size: 12px;
            color: #64748b;
            transition: all 0.2s;
        }

        .ui-drawer .step-item.active .step-number {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .ui-drawer .step-item.completed .step-number {
            background: var(--success, #059669);
            border-color: var(--success, #059669);
            color: #fff;
        }

        .ui-drawer .step-label {
            font-size: 10px;
            font-weight: 500;
            color: #64748b;
            letter-spacing: 0.2px;
        }

        .ui-drawer .step-item.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .ui-drawer .step-item.completed .step-label {
            color: var(--success, #059669);
        }

        .ui-drawer .form-section {
            background: #fff;
            padding: 4px 2px;
        }

        .ui-drawer .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
        }

        .ui-drawer .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border, #e2e8f0);
        }

        .ui-drawer .nav-buttons {
            display: flex;
            gap: 8px;
        }

        .ui-drawer .profile-upload {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px;
            background: var(--surface-2, #f8fafc);
            border-radius: 8px;
        }

        .ui-drawer .profile-image-container {
            position: relative;
            width: 80px;
            height: 80px;
        }

        .ui-drawer .profile-image-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .ui-drawer .profile-image-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 26px;
            height: 26px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            border: 2px solid #fff;
        }

        .ui-drawer .profile-image-overlay i {
            font-size: 13px;
        }

        .ui-drawer .profile-help-text {
            font-size: 11px;
            color: #64748b;
        }

        .ui-drawer .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .ui-drawer .file-input {
            padding: 6px;
            background: #fff;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 6px;
            width: 100%;
            font-size: 12px;
        }

        .ui-drawer .document-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .ui-drawer .document-row .form-group {
            flex: 1 1 160px;
            min-width: 140px;
            margin-bottom: 0;
        }

        .ui-drawer #addDocumentRowBtn,
        .ui-drawer .remove-document-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 1px solid var(--border, #e2e8f0);
            background: #fff;
            color: var(--primary);
            flex: 0 0 auto;
        }

        .ui-drawer .remove-document-row {
            color: #ef4444;
            align-self: center;
        }

        .ui-drawer .doc-current-file a {
            color: var(--primary);
        }

        .ui-drawer .alert-info {
            background: var(--primary-light);
            border: 1px solid #bfd3f7;
            color: var(--primary);
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ui-drawer .form-control.is-invalid {
            border-color: #ef4444;
        }

        .ui-drawer .invalid-feedback {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }

        .ui-drawer .required {
            color: #ef4444;
            margin-left: 2px;
        }

        @media (max-width: 480px) {
            .ui-drawer .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content-area')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <!--<h5 class="m-b-10">Employees</h5>-->
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">View Employees</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="#" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <div class="dropdown">
                        <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10"
                            data-bs-auto-close="outside">
                            <i class="feather-download"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" >
                            <a href="#" class="dropdown-item" onclick="exportToCSV()">
                                <i class="bi bi-filetype-csv me-3"></i>
                                <span>Export CSV</span>
                            </a>
                        </div>
                    </div>
                    @if (in_array(auth()->user()->role, ['admin', 'hr']))
                        <button type="button" class="btn btn-sm btn-light-brand" data-bs-toggle="modal" data-bs-target="#importEmployeeModal">
                            <i class="feather-upload me-2"></i>
                            <span>Import Employees</span>
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-primary add-employee-btn" onclick="openAddEmployeeDrawer()">
                        <i class="feather-plus me-2"></i>
                        <span>Add Employee</span>
                    </button>
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="#" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- [ page-header ] end -->

    <!-- [ Main Content ] start -->
    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        @php
            $empBase = $totalEmployees ?? 0;
            $pct = fn ($n) => $empBase > 0 ? round(($n / $empBase) * 100) : 0;
            $notFaceRegistered = max(0, $empBase - ($faceRegisteredEmployees ?? 0));
        @endphp

        <div class="stats-grid">
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="feather-users"></i></span>
                    <span class="kpi5-pill">{{ $pct($activeEmployees ?? 0) }}% Active</span>
                </div>
                <div class="kpi5-value">{{ $totalEmployees }}</div>
                <div class="kpi5-label">Total Employees</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $activeEmployees }}</span><span class="l">Active</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $inactiveEmployees }}</span><span class="l">Inactive</span></div>
                </div>
            </div>

            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="feather-briefcase"></i></span>
                    <span class="kpi5-pill">{{ $pct($officeEmployees ?? 0) }}%</span>
                </div>
                <div class="kpi5-value">{{ $officeEmployees }}</div>
                <div class="kpi5-label">Office Employees</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $fieldEmployees }}</span><span class="l">Field</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $totalEmployees }}</span><span class="l">Total</span></div>
                </div>
            </div>

            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="feather-camera"></i></span>
                    <span class="kpi5-pill">{{ $pct($faceRegisteredEmployees ?? 0) }}%</span>
                </div>
                <div class="kpi5-value">{{ $faceRegisteredEmployees }}</div>
                <div class="kpi5-label">Face Registered</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $faceRegisteredEmployees }}</span><span class="l">Registered</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $notFaceRegistered }}</span><span class="l">Not Registered</span></div>
                </div>
            </div>

            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="feather-users"></i></span>
                    <span class="kpi5-pill">{{ ($hrCount ?? 0) + ($managerCount ?? 0) + ($employeeCount ?? 0) }} Total</span>
                </div>
                <div class="kpi5-value">{{ $employeeCount ?? 0 }}</div>
                <div class="kpi5-label">Employees by Role</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    <div class="kpi5-stat"><span class="n">{{ $hrCount ?? 0 }}</span><span class="l">HR</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $managerCount ?? 0 }}</span><span class="l">Manager</span></div>
                    <div class="kpi5-stat"><span class="n">{{ $employeeCount ?? 0 }}</span><span class="l">Employee</span></div>
                </div>
            </div>

            @if (!empty($fieldTrackingEnabled))
                @php
                    $ftAvailable = max(0, ($fieldTrackingSeats ?? 0) - ($fieldTrackingSeatsUsed ?? 0));
                    $ftPct = ($fieldTrackingSeats ?? 0) > 0 ? round((($fieldTrackingSeatsUsed ?? 0) / $fieldTrackingSeats) * 100) : 0;
                @endphp
                <div class="kpi5-card">
                    <div class="kpi5-top">
                        <span class="kpi5-icon"><i class="feather-crosshair"></i></span>
                        <span class="kpi5-pill">{{ $ftPct }}% Used</span>
                    </div>
                    <div class="kpi5-value" id="ltSeatsUsedKpi">{{ $fieldTrackingSeatsUsed }}</div>
                    <div class="kpi5-label">Field Tracking Seats In Use</div>
                    <div class="kpi5-divider"></div>
                    <div class="kpi5-foot">
                        <div class="kpi5-stat"><span class="n">{{ $fieldTrackingSeats }}</span><span class="l">Purchased</span></div>
                        <div class="kpi5-stat"><span class="n">{{ $ftAvailable }}</span><span class="l">Available</span></div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Compact Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Employees
                    @php
                        $activeFilterCount = collect(
                            request()->only(['search', 'department', 'role', 'status', 'designation']),
                        )
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['search', 'department', 'role', 'status', 'designation']))
                    <a href="{{ route('employee.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('employee.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item" style="min-width: 220px;">

                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light w-100 d-flex align-items-center justify-content-between"
                                type="button" id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('user_id') && ($selectedEmployee = $allEmployees->firstWhere('id', request('user_id'))))
                                        @php
                                            $selectedInitials = strtoupper(substr($selectedEmployee->name, 0, 2));
                                        @endphp
                                        <span class="employee-initials-sm">{{ $selectedInitials }}</span>
                                        <span class="employee-name">{{ $selectedEmployee->name }}</span>
                                    @else
                                        <span class="text-muted">All Employees</span>
                                    @endif
                                </span>
                                <i class="feather-chevron-down text-muted"></i>
                            </button>

                            <ul class="dropdown-menu w-80 p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('user_id') ? 'active' : '' }}"
                                        href="{{ route('employee.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($allEmployees  as $user)
                                    @php
                                        $initials = strtoupper(substr($user->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $user->id ? 'active' : '' }}"
                                            href="{{ route('employee.index', array_merge(request()->except(['page']), ['user_id' => $user->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $user->name }} (<small
                                                        class="text-muted">{{ $user->employee_id }})</small></span>
                                                <small class="text-muted">{{ $user->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <!-- Search Field -->
                    <div class="filter-item search">
                        <div class="search-wrapper">
                            <i class="feather-search"></i>
                            <input type="text" class="form-control" name="search"
                                placeholder="Search by name, email, ID..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <!-- Department Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="department">
                            <option value="">All Departments</option>
                            @foreach ($departments ?? [] as $department)
                                <option value="{{ $department->id }}"
                                    {{ request('department') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Designation Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="designation">
                            <option value="">All Designation</option>
                            @foreach ($designations as $designation)
                                <option value="{{ $designation->id }}"
                                    {{ request('designation') == $designation->id ? 'selected' : '' }}>
                                    {{ $designation->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="role">
                            <option value="">All Roles</option>
                            <option value="employee" {{ request('role') == 'employee' ? 'selected' : '' }}>Employee
                            </option>
                            <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="hr" {{ request('role') == 'hr' ? 'selected' : '' }}>HR</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    {{-- <div class="filter-item" style="min-width: auto;">
                        <button type="submit" class="apply-btn">
                            <i class="feather-search"></i>
                            Apply
                        </button>
                    </div> --}}

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('employee.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['search', 'department', 'role', 'status']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('employee.index', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('department'))
                        <span class="filter-tag">
                            <i class="feather-briefcase"></i>
                            {{ request('department') }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['department', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('role'))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            {{ ucfirst(request('role')) }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['role', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('status') !== null && request('status') !== '')
                        <span class="filter-tag">
                            <i class="feather-toggle-right"></i>
                            {{ request('status') == '1' ? 'Active' : 'Inactive' }}
                            <a href="{{ route('employee.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('employee.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Employees Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Employees List</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info">
                                <i class="feather-list me-1"></i>Total: {{ $totalEmployees }}
                            </span>
                            <span class="badge bg-primary">
                                <i class="feather-check me-1"></i>Active: {{ $activeEmployees }}
                            </span>
                            @if (!empty($fieldTrackingEnabled))
                                <span class="badge bg-primary" title="Field-tracking seats in use / purchased">
                                    <i class="feather-map-pin me-1"></i>Field tracking:
                                    <span id="ltSeatsUsed">{{ $fieldTrackingSeatsUsed }}</span> / {{ $fieldTrackingSeats }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if (!empty($fieldTrackingEnabled) || $pushDevices->isNotEmpty())
                            <div id="ltBulkBar" class="d-none align-items-center gap-2 px-3 py-2"
                                 style="background:var(--primary-light);border-bottom:1px solid #c7d2fe;font-size:13px;">
                                <span id="ltBulkCount" class="fw-semibold">0 selected</span>
                                @if (!empty($fieldTrackingEnabled))
                                    <button type="button" class="btn btn-sm btn-primary" onclick="bulkLocationTracking(1)">Enable tracking</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkLocationTracking(0)">Disable tracking</button>
                                @endif
                                @if ($pushDevices->isNotEmpty())
                                    <select id="pushDeviceSelect" class="form-control form-control-sm" style="width:auto;display:inline-block;">
                                        @foreach ($pushDevices as $dev)
                                            <option value="{{ $dev->id }}">{{ $dev->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkPushToDevice()">Push to device</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-link" onclick="ltClearSelection()">Clear</button>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table" id="employeeList1">
                                <thead>
                                    <tr>
                                        @if (!empty($fieldTrackingEnabled) || $pushDevices->isNotEmpty())
                                            <th style="width:28px"><input type="checkbox" id="ltSelectAll"></th>
                                        @endif
                                        <th width="50">#</th>
                                        <th>Employee</th>
                                        {{-- <th>Employee ID</th> --}}
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Face Register</th>
                                        <th>Attendance Type</th>
                                        @if (!empty($fieldTrackingEnabled))
                                            <th>Field Tracking</th>
                                        @endif
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($users as $user)
                                        <tr>
                                            @if (!empty($fieldTrackingEnabled) || $pushDevices->isNotEmpty())
                                                <td><input type="checkbox" class="lt-row-sel" value="{{ $user->id }}"></td>
                                            @endif
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <a href="{{ route('employee.show', ['id' => encrypt($user->id)]) }}">
                                                <div class="employee-info">
                                                    @if ($user->profile_image)
                                                        <img src="{{ file_url($user->profile_image, 'profile_photo') }}"
                                                            class="employee-avatar" alt="{{ $user->name }}">
                                                    @else
                                                        <div class="employee-avatar">
                                                            {{ strtoupper(substr($user->name ?? 'NA', 0, 2)) }}
                                                        </div>
                                                    @endif
                                                    <div class="employee-details">
                                                        <div class="employee-name-text">{{ ucfirst($user->name) }}
                                                                <small class="employee-id-text">(
                                                        {{ $user->employee_id ?? 'N/A' }} )</small>
                                                        </div>
                                                        <div class="employee-email-text">{{ $user->email }}</div>
                                                        
                                                    </div>
                                                </div>
                                                </a>
                                            </td>
                                            {{-- <td>
                                                <span class="employee-id">{{ $user->employee_id ?? 'N/A' }}</span>
                                            </td> --}}
                                            <td>{{ $user->designation ?? 'N/A' }}</td>
                                            <td>{{ $user->department ?? 'N/A' }}</td>
                                            <td>
                                                <span class="role-tag {{ $user->role }}">
                                                    {{ ucfirst($user->role) }}
                                                </span>
                                            </td>
                                            <td class="text-nowrap">
                                                <i class="feather-phone me-1"
                                                    style="font-size: 11px;"></i>{{ $user->contact }}
                                            </td>
                                            <!-- Replace the status column (around line 280) with this: -->
                                            <td>
                                                <div class="status-toggle">
                                                    <div class="toggle-switch {{ $user->status == 1 ? 'active' : '' }}"
                                                        onclick="toggleEmployeeStatus({{ $user->id }}, {{ $user->status }})">
                                                        <div class="toggle-circle"></div>
                                                    </div>
                                                    <span
                                                        class="status-label {{ $user->status == 1 ? 'active' : 'inactive' }}"
                                                        id="status-label-{{ $user->id }}">
                                                        {{ $user->status == 1 ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="status-toggle">
                                                    <div class="toggle-switch face-toggle {{ $user->face_register == 1 ? 'active' : '' }}"
                                                        onclick="toggleFaceRegister({{ $user->id }}, {{ $user->face_register }})">
                                                        <div class="toggle-circle"></div>
                                                    </div>
                                                    <span
                                                        class="status-label {{ $user->face_register == 1 ? 'registered' : 'not-registered' }}"
                                                        id="face-status-label-{{ $user->id }}">
                                                        {{ $user->face_register == 1 ? 'Registered' : 'Not Registered' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <!-- Attendance Type Column -->
                                            <td>
                                                @php
                                                    $canManualAttendance = app(\App\Services\FeatureService::class)->enabledForCurrentTenant('attendance');
                                                    $canFaceAttendance = app(\App\Services\FeatureService::class)->enabledForCurrentTenant('attendance_face');
                                                @endphp
                                                @if ($canManualAttendance && $canFaceAttendance)
                                                    <select class="form-select form-select-sm attendance-type-select"
                                                            style="width: 100%; min-width: 140px; font-size: 12px; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; background-color: #f8fafc;"
                                                            data-user-id="{{ $user->id }}"
                                                            onchange="updateAttendanceType(this)">
                                                        <option value="manual_attendance" {{ $user->attendance_type == 'manual_attendance' ? 'selected' : '' }}>
                                                            📝 Manual
                                                        </option>
                                                        <option value="face_verification" {{ $user->attendance_type == 'face_verification' ? 'selected' : '' }}>
                                                            👤 Face Verification
                                                        </option>
                                                    </select>
                                                @elseif ($canFaceAttendance)
                                                    <span class="badge bg-light text-dark">👤 Face Verification</span>
                                                @else
                                                    <span class="badge bg-light text-dark">📝 Manual</span>
                                                @endif
                                            </td>
                                            @if (!empty($fieldTrackingEnabled))
                                                <td>
                                                    <div class="status-toggle">
                                                        <div class="toggle-switch location-toggle {{ $user->location_tracking_enabled ? 'active' : '' }}"
                                                            onclick="toggleLocationTracking({{ $user->id }}, {{ (int) $user->location_tracking_enabled }})">
                                                            <div class="toggle-circle"></div>
                                                        </div>
                                                        <span class="status-label {{ $user->location_tracking_enabled ? 'registered' : 'not-registered' }}"
                                                            id="loc-status-label-{{ $user->id }}">
                                                            {{ $user->location_tracking_enabled ? 'Tracking' : 'Off' }}
                                                        </span>
                                                        @if (($user->type ?? '') !== 'field')
                                                            <small class="text-muted d-block" style="font-size:10px;">office employee</small>
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="{{ route('employee.show', ['id' => encrypt($user->id)]) }}"
                                                        class="action-btn" title="View" data-bs-toggle="tooltip">
                                                        <i class="feather-eye"></i>
                                                    </a>
                                                    <button type="button" class="action-btn" title="Edit"
                                                        data-bs-toggle="tooltip"
                                                        onclick="openEditEmployeeDrawer('{{ encrypt($user->id) }}', '{{ $user->employee_id }}')">
                                                        <i class="feather-edit-3"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Employees Found</h4>
                                                    <p class="text-muted">Get started by adding your first employee</p>
                                                    {{-- <a href="{{ route('employee.create') }}" class="btn btn-primary">
                                                        <i class="feather-plus me-2"></i>
                                                        Add Employee
                                                    </a> --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if (method_exists($users, 'links') && $users->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of
                                    {{ $users->total() }} entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $users->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('create-modal')
    <!-- Add/Edit Employee Drawer (shared instance, mode toggled via JS) -->
    <x-ui.drawer id="employeeDrawer" title="Add Employee" width="480px">
        @include('client.user.partials.employee-wizard-form')
    </x-ui.drawer>

    <!-- Import Employees (users table only: Name, Email, Mobile No, Password) -->
    <x-ui.modal id="importEmployeeModal" title="Import Employees" size="md">
        <form id="importEmployeeForm" enctype="multipart/form-data">
            @csrf
            <p class="fs-12 text-muted mb-2">
                Upload an Excel (.xlsx) or CSV file with the columns
                <strong>Name, Email, Mobile No, Password</strong>. Employee IDs are generated automatically.
            </p>
            <a href="{{ route('employee.import.template') }}" class="fs-12 d-inline-block mb-3">
                <i class="feather-download me-1"></i>Download sample file
            </a>
            <div class="form-group mb-3">
                <input type="file" class="form-control" name="file" accept=".xlsx,.csv" required>
            </div>
            <div id="importEmployeeErrors" class="alert alert-danger fs-12 d-none" style="max-height:220px;overflow:auto"></div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm" id="importEmployeeBtn">
                    <i class="feather-upload me-2"></i>Import
                </button>
                <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </x-ui.modal>
@endsection

@section('script-area')
    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000"
        };

        $(document).ready(function() {
            // Auto-submit on select change
            $('.filter-select').on('change', function() {
                $('#filterForm').submit();
            });

            // Debounce search input
            let searchTimeout;
            $('input[name="search"]').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    $('#filterForm').submit();
                }, 500);
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Deep link support (e.g. the sidebar's "Add Employee" link, or the
            // "Edit Employee" button on the employee detail page):
            // ?open=add opens the Add drawer, ?edit_id=...&edit_code=... opens
            // the Edit drawer pre-filled for that employee.
            const deepLinkParams = new URLSearchParams(window.location.search);
            if (deepLinkParams.get('open') === 'add' && typeof openAddEmployeeDrawer === 'function') {
                openAddEmployeeDrawer();
            } else if (deepLinkParams.get('edit_id') && typeof openEditEmployeeDrawer === 'function') {
                openEditEmployeeDrawer(deepLinkParams.get('edit_id'), deepLinkParams.get('edit_code'));
            }
        });

        // Direct Status Update Function for Employee Status
        function toggleEmployeeStatus(userId, currentStatus) {
            // Get the event to find the clicked element
            const event = window.event;
            const row = event.target.closest('tr');
            const toggle = row ? row.querySelector('.toggle-switch:not(.face-toggle)') : null;
            const statusLabel = document.getElementById(`status-label-${userId}`);

            // Disable the toggle during update
            if (toggle) {
                toggle.style.pointerEvents = 'none';
                toggle.style.opacity = '0.6';
            }

            // Calculate new status
            const newStatus = currentStatus == 1 ? 0 : 1;
            const newStatusText = newStatus == 1 ? 'Active' : 'Inactive';

            $.ajax({
                url: "{{ route('employee.toggle-status') }}",
                type: "POST",
                data: {
                    id: userId,
                    status: currentStatus,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success('Status updated successfully');

                        // Update the toggle UI
                        if (toggle && statusLabel) {
                            // Update toggle state
                            if (newStatus == 1) {
                                toggle.classList.add('active');
                            } else {
                                toggle.classList.remove('active');
                            }

                            // Update status label
                            statusLabel.textContent = newStatusText;
                            statusLabel.className = `status-label ${newStatus == 1 ? 'active' : 'inactive'}`;

                            // Update the onclick attribute with new status
                            toggle.setAttribute('onclick', `toggleEmployeeStatus(${userId}, ${newStatus})`);
                        }

                        // Re-enable the toggle
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    } else {
                        toastr.error(response.message || 'Error updating status');
                        // Re-enable the toggle on error
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating status. Please try again.');
                    console.error(xhr);

                    // Re-enable the toggle on error
                    if (toggle) {
                        toggle.style.pointerEvents = '';
                        toggle.style.opacity = '';
                    }
                }
            });
        }

        // Direct Status Update Function for Face Register Status
        function toggleFaceRegister(userId, currentFaceStatus) {
            // Get the event to find the clicked element
            const event = window.event;
            const row = event.target.closest('tr');
            const toggle = row ? row.querySelector('.toggle-switch.face-toggle') : null;
            const statusLabel = document.getElementById(`face-status-label-${userId}`);

            // Disable the toggle during update
            if (toggle) {
                toggle.style.pointerEvents = 'none';
                toggle.style.opacity = '0.6';
            }

            // Calculate new status
            const newStatus = currentFaceStatus == 1 ? 0 : 1;
            const newStatusText = newStatus == 1 ? 'Registered' : 'Not Registered';

            $.ajax({
                url: "{{ route('employee.toggle-face-register') }}",
                type: "POST",
                data: {
                    id: userId,
                    face_status: currentFaceStatus,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(`Face register status updated successfully`);

                        // Update the toggle UI
                        if (toggle && statusLabel) {
                            // Update toggle state
                            if (newStatus == 1) {
                                toggle.classList.add('active');
                            } else {
                                toggle.classList.remove('active');
                            }

                            // Update status label
                            statusLabel.textContent = newStatusText;
                            statusLabel.className =
                                `status-label ${newStatus == 1 ? 'registered' : 'not-registered'}`;

                            // Update the onclick attribute with new status
                            toggle.setAttribute('onclick', `toggleFaceRegister(${userId}, ${newStatus})`);
                        }

                        // Re-enable the toggle
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    } else {
                        toastr.error(response.message || 'Error updating face register status');
                        // Re-enable the toggle on error
                        if (toggle) {
                            toggle.style.pointerEvents = '';
                            toggle.style.opacity = '';
                        }
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating face register status. Please try again.');
                    console.error(xhr);

                    // Re-enable the toggle on error
                    if (toggle) {
                        toggle.style.pointerEvents = '';
                        toggle.style.opacity = '';
                    }
                }
            });
        }

        // ==================== FIELD GPS TRACKING ====================
        function toggleLocationTracking(userId, current) {
            const event = window.event;
            const row = event.target.closest('tr');
            const toggle = row ? row.querySelector('.toggle-switch.location-toggle') : null;
            const label = document.getElementById(`loc-status-label-${userId}`);
            const newStatus = current == 1 ? 0 : 1;

            if (toggle) { toggle.style.pointerEvents = 'none'; toggle.style.opacity = '0.6'; }

            $.ajax({
                url: "{{ route('employee.toggle-location-tracking') }}",
                type: "POST",
                data: { id: userId, enabled: newStatus, _token: "{{ csrf_token() }}" },
                success: function (response) {
                    if (response.success) {
                        if (toggle && label) {
                            toggle.classList.toggle('active', newStatus == 1);
                            label.textContent = newStatus == 1 ? 'Tracking' : 'Off';
                            label.className = `status-label ${newStatus == 1 ? 'registered' : 'not-registered'}`;
                            toggle.setAttribute('onclick', `toggleLocationTracking(${userId}, ${newStatus})`);
                        }
                        if (typeof response.seats_used !== 'undefined') {
                            const el = document.getElementById('ltSeatsUsed');
                            if (el) el.textContent = response.seats_used;
                            const kpiEl = document.getElementById('ltSeatsUsedKpi');
                            if (kpiEl) kpiEl.textContent = response.seats_used;
                        }
                        toastr.success(response.message || 'Updated');
                    } else {
                        toastr.error(response.message || 'Could not update field tracking');
                    }
                    if (toggle) { toggle.style.pointerEvents = ''; toggle.style.opacity = ''; }
                },
                error: function (xhr) {
                    toastr.error('Error updating field tracking. Please try again.');
                    console.error(xhr);
                    if (toggle) { toggle.style.pointerEvents = ''; toggle.style.opacity = ''; }
                }
            });
        }

        function ltSelectedIds() {
            return Array.from(document.querySelectorAll('.lt-row-sel:checked')).map(c => c.value);
        }
        function ltSyncBulkBar() {
            const bar = document.getElementById('ltBulkBar');
            if (!bar) return;
            const n = ltSelectedIds().length;
            bar.classList.toggle('d-none', n === 0);
            bar.classList.toggle('d-flex', n > 0);
            const c = document.getElementById('ltBulkCount');
            if (c) c.textContent = `${n} selected`;
        }
        function ltClearSelection() {
            document.querySelectorAll('.lt-row-sel:checked').forEach(c => (c.checked = false));
            const all = document.getElementById('ltSelectAll');
            if (all) all.checked = false;
            ltSyncBulkBar();
        }
        function bulkLocationTracking(enabled) {
            const ids = ltSelectedIds();
            if (!ids.length) { toastr.info('Select employees first'); return; }
            $.ajax({
                url: "{{ route('employee.bulk-location-tracking') }}",
                type: "POST",
                data: { user_ids: ids, enabled: enabled, _token: "{{ csrf_token() }}" },
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        if (response.data && typeof response.data.seats_used !== 'undefined') {
                            const el = document.getElementById('ltSeatsUsed');
                            if (el) el.textContent = response.data.seats_used;
                            const kpiEl = document.getElementById('ltSeatsUsedKpi');
                            if (kpiEl) kpiEl.textContent = response.data.seats_used;
                        }
                        setTimeout(() => window.location.reload(), 900);
                    } else {
                        toastr.error(response.message || 'Bulk update failed');
                    }
                },
                error: function (xhr) {
                    toastr.error('Bulk update failed. Please try again.');
                    console.error(xhr);
                }
            });
        }
        function bulkPushToDevice() {
            const ids = ltSelectedIds();
            if (!ids.length) { toastr.info('Select employees first'); return; }
            const deviceId = document.getElementById('pushDeviceSelect')?.value;
            if (!deviceId) { toastr.error('Choose a device'); return; }
            $.ajax({
                url: "{{ route('employee.bulk-push-to-device') }}",
                type: "POST",
                data: { user_ids: ids, device_id: deviceId, _token: "{{ csrf_token() }}" },
                success: function (response) {
                    if (response.status) { toastr.success(response.message); }
                    else { toastr.error(response.message || 'Push failed'); }
                },
                error: function () { toastr.error('Push failed. Please try again.'); }
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            const all = document.getElementById('ltSelectAll');
            if (all) {
                all.addEventListener('change', function () {
                    document.querySelectorAll('.lt-row-sel').forEach(c => (c.checked = all.checked));
                    ltSyncBulkBar();
                });
            }
            document.querySelectorAll('.lt-row-sel').forEach(c => c.addEventListener('change', ltSyncBulkBar));
        });

        // Update Attendance Type
        function updateAttendanceType(selectElement) {
            const userId = selectElement.getAttribute('data-user-id');
            const attendanceType = selectElement.value;
            
            const originalBg = selectElement.style.backgroundColor;
            selectElement.style.backgroundColor = '#fef3c7';
            selectElement.disabled = true;
            
            $.ajax({
                url: "{{ route('employee.update-attendance-type') }}",
                type: "POST",
                data: {
                    id: userId,
                    attendance_type: attendanceType,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Attendance type updated successfully');
                        selectElement.style.backgroundColor = '#d1fae5';
                        
                        setTimeout(() => {
                            selectElement.style.backgroundColor = originalBg || '#f8fafc';
                            selectElement.disabled = false;
                        }, 1000);
                    } else {
                        toastr.error(response.message || 'Error updating attendance type');
                        const previousValue = selectElement.getAttribute('data-previous-value');
                        if (previousValue) {
                            selectElement.value = previousValue;
                        }
                        selectElement.style.backgroundColor = '#fee2e2';
                        selectElement.disabled = false;
                        
                        setTimeout(() => {
                            selectElement.style.backgroundColor = originalBg || '#f8fafc';
                        }, 2000);
                    }
                },
                error: function(xhr) {
                    toastr.error('Error updating attendance type. Please try again.');
                    console.error(xhr);
                    
                    const previousValue = selectElement.getAttribute('data-previous-value');
                    if (previousValue) {
                        selectElement.value = previousValue;
                    }
                    selectElement.style.backgroundColor = '#fee2e2';
                    selectElement.disabled = false;
                    
                    setTimeout(() => {
                        selectElement.style.backgroundColor = originalBg || '#f8fafc';
                    }, 2000);
                }
            });
        }

        // Store previous value when dropdown opens
        $(document).on('focus', '.attendance-type-select', function() {
            $(this).attr('data-previous-value', $(this).val());
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('employeeList1');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            let rows = table.querySelectorAll('tr');
            let csv = [];

            // Add headers
            let headers = [];
            let headerCells = rows[0].querySelectorAll('th');
            for (let i = 0; i < headerCells.length; i++) {
                // Skip empty headers or actions if needed
                let headerText = headerCells[i].innerText.trim();
                if (headerText && headerText !== 'Actions') {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length; j++) {
                    // Skip actions column if needed (last column)
                    if (j === cols.length - 1) continue;

                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                    rowData.push('"' + cellText + '"');
                }

                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            }

            let csvFile = new Blob([csv.join('\n')], {
                type: 'text/csv'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'employees_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }

        // Apply filters
        function applyFilters() {
            $('#filterForm').submit();
        }

        // Reset filters
        function resetFilters() {
            window.location.href = "{{ route('employee.index') }}";
        }
    </script>

    {{-- ==================== ADD/EDIT EMPLOYEE DRAWER (shared wizard) ====================
         One #employeeForm instance lives in #employeeDrawer for the whole page.
         wizardMode toggles which existing backend endpoints it talks to:
           add  -> employee.save.step / employee.complete.store
           edit -> employee.update.step/{id} / employee.complete.update/{id},
                   pre-filled via the existing employee.load.data endpoint. --}}
    <script>
        let wizardMode = 'add';
        let currentStep = 1;
        const totalSteps = 7;
        let employeeId = '';
        let editEncryptedId = '';

        const editUpdateStepUrlTemplate = "{{ route('employee.update.step', ['id' => '__ID__']) }}";
        const editCompleteUpdateUrlTemplate = "{{ route('employee.complete.update', ['id' => '__ID__']) }}";
        const defaultAvatarUrl = "{{ asset('assets/images/avatar/1.png') }}";

        function getDrawerInstance() {
            return bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('employeeDrawer'));
        }


        // Import Employees modal
        $('#importEmployeeForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#importEmployeeBtn');
            const $errors = $('#importEmployeeErrors').addClass('d-none').empty();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Importing...');

            $.ajax({
                url: '{{ route('employee.import') }}',
                method: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                success: function (res) {
                    toastr.success(res.message);
                    $('#importEmployeeModal').modal('hide');
                    setTimeout(() => window.location.reload(), 800);
                },
                error: function (xhr) {
                    const res = xhr.responseJSON || {};
                    let html = '<div class="fw-semibold mb-1">' + $('<div>').text(res.message || 'Import failed.').html() + '</div>';
                    if (Array.isArray(res.errors)) {
                        html += '<ul class="mb-0 ps-3">' + res.errors.map(function (r) {
                            return '<li>Row ' + r.row + ': ' + r.messages.map(m => $('<div>').text(m).html()).join(' ') + '</li>';
                        }).join('') + '</ul>';
                    } else if (res.errors && typeof res.errors === 'object') {
                        html += Object.values(res.errors).flat().map(m => $('<div>').text(m).html()).join('<br>');
                    }
                    $errors.html(html).removeClass('d-none');
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="feather-upload me-2"></i>Import');
                }
            });
        });

        $('#importEmployeeModal').on('hidden.bs.modal', function () {
            $('#importEmployeeForm')[0].reset();
            $('#importEmployeeErrors').addClass('d-none').empty();
        });
        function openAddEmployeeDrawer() {
            wizardMode = 'add';
            resetWizardForm();
            $('#employeeDrawerLabel').text('Add Employee');
            $('#statusFieldWrapper').addClass('d-none');
            $('#passwordRow').removeClass('d-none');
            $('#submitBtnLabel').text('Submit');
            getDrawerInstance().show();
        }

        function openEditEmployeeDrawer(encryptedId, employeeCode) {
            wizardMode = 'edit';
            resetWizardForm();
            editEncryptedId = encryptedId;
            $('#employeeDrawerLabel').text('Edit Employee');
            $('#statusFieldWrapper').removeClass('d-none');
            $('#passwordRow').addClass('d-none');
            $('#submitBtnLabel').text('Save Changes');
            getDrawerInstance().show();
            if (employeeCode) {
                loadSavedData(employeeCode);
            }
        }

        function resetWizardForm() {
            const formEl = document.getElementById('employeeForm');
            if (formEl) formEl.reset();
            $('#employeeForm .is-invalid').removeClass('is-invalid');
            $('#employeeForm .invalid-feedback').remove();
            $('#employeeFormError').addClass('d-none').text('');
            $('#profileImagePreview').attr('src', defaultAvatarUrl);
            $('#employee_id').val('');
            employeeId = '';
            editEncryptedId = '';
            if ($.fn.select2) {
                $('select[name="language[]"]').val(null).trigger('change');
                $('select[name="leave_type_assigned[]"]').val(null).trigger('change');
                $('select[name="reporting_head[]"]').val(null).trigger('change');
            }
            $('#state').html('<option value="">Select State</option>');
            $('#city').html('<option value="">Select City</option>');
            populateDocumentRows([]);
            resetAllFields();
            $('#monthlyViewBtn').addClass('active');
            $('#yearlyViewBtn').removeClass('active');
            $('#monthlyView').show();
            $('#yearlyView').hide();
            currentStep = 1;
            showStep(currentStep);
        }

        $(document).ready(function() {
            // Profile image upload
            $('#profileUploadBtn, #profileImagePreview').on('click', function() {
                $('#profilePhotoInput').click();
            });

            $('#profilePhotoInput').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        toastr.error('File size must be less than 2MB');
                        $(this).val('');
                        return;
                    }

                    if (!file.type.match('image.*')) {
                        toastr.error('Only image files are allowed');
                        $(this).val('');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#profileImagePreview').attr('src', e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Load states
            $('#country').on('change', function() {
                const countryCode = $(this).val();
                if (countryCode) {
                    $.ajax({
                        url: "{{ route('get.states') }}",
                        type: "GET",
                        data: {
                            country_code: countryCode
                        },
                        success: function(data) {
                            $('#state').html('<option value="">Select State</option>');
                            $.each(data, function(key, state) {
                                $('#state').append('<option value="' + state.state_code + '">' + state
                                    .name + '</option>');
                            });
                        },
                        error: function() {
                            toastr.error('Error loading states');
                        }
                    });
                } else {
                    $('#state').html('<option value="">Select State</option>');
                    $('#city').html('<option value="">Select City</option>');
                }
            });

            // Load cities
            $('#state').on('change', function() {
                const stateCode = $(this).val();
                if (stateCode) {
                    $.ajax({
                        url: "{{ route('get.cities') }}",
                        type: "GET",
                        data: {
                            state_code: stateCode
                        },
                        success: function(data) {
                            $('#city').html('<option value="">Select City</option>');
                            $.each(data, function(key, city) {
                                $('#city').append('<option value="' + city.city_code + '">' + city
                                    .name + '</option>');
                            });
                        },
                        error: function() {
                            toastr.error('Error loading cities');
                        }
                    });
                } else {
                    $('#city').html('<option value="">Select City</option>');
                }
            });

            // ==================== PAYROLL CALCULATION ====================

            function calculateSalaryBreakdown() {
                const annualCTC = parseFloat($('#annual_ctc').val()) || 0;

                if (annualCTC <= 0) {
                    resetAllFields();
                    return;
                }

                const selectedOption = $('#payroll_structure_id option:selected');
                let calc = {};
                try {
                    calc = JSON.parse(selectedOption.attr('data-calc') || '{}');
                } catch (e) {
                    calc = {};
                }

                const monthlyCTC = annualCTC / 12;

                const ALLOWANCE_FIELDS = {
                    hra: 'hra',
                    conveyance: 'conveyence',
                    medical_allowance: 'medical',
                    children_allowance: 'children',
                    post_allowance: 'post',
                    leave_travel_allowance: 'lta',
                    monthly_incentive: 'incentive'
                };

                let pctSum = 0;
                let fixedSum = 0;
                const pctByKey = {};
                const fixedByKey = {};
                let hasCustomAllowance = false;

                $.each(ALLOWANCE_FIELDS, function(code, key) {
                    const entry = calc[code];
                    if (entry && entry.type === 'percentage' && entry.base === 'basic') {
                        pctByKey[key] = entry.value;
                        pctSum += entry.value;
                        hasCustomAllowance = true;
                    } else if (entry && entry.type === 'fixed') {
                        fixedByKey[key] = entry.value;
                        fixedSum += entry.value;
                        hasCustomAllowance = true;
                    }
                });

                let basic, hra, conveyence, medical, children, post, lta, incentive;

                if (hasCustomAllowance) {
                    basic = Math.max(0, (monthlyCTC - fixedSum) / (1 + (pctSum / 100)));
                    hra = fixedByKey.hra !== undefined ? fixedByKey.hra : basic * ((pctByKey.hra || 0) / 100);
                    conveyence = fixedByKey.conveyence !== undefined ? fixedByKey.conveyence : basic * ((pctByKey
                        .conveyence || 0) / 100);
                    medical = fixedByKey.medical !== undefined ? fixedByKey.medical : basic * ((pctByKey.medical ||
                        0) / 100);
                    children = fixedByKey.children !== undefined ? fixedByKey.children : basic * ((pctByKey
                        .children || 0) / 100);
                    post = fixedByKey.post !== undefined ? fixedByKey.post : basic * ((pctByKey.post || 0) / 100);
                    lta = fixedByKey.lta !== undefined ? fixedByKey.lta : basic * ((pctByKey.lta || 0) / 100);
                    incentive = fixedByKey.incentive !== undefined ? fixedByKey.incentive : basic * ((pctByKey
                        .incentive || 0) / 100);
                } else {
                    basic = monthlyCTC * 0.5;
                    hra = basic * 0.4;
                    conveyence = 1600;
                    medical = 1250;
                    children = 0;
                    post = 0;
                    lta = 0;
                    incentive = 0;
                }

                const totalAllowances = hra + conveyence + medical + children + post + lta + incentive;
                const grossSalary = basic + totalAllowances;

                function percentageDeduction(entry, base, defaultPct, defaultCeiling, defaultRule) {
                    let pct = defaultPct;
                    let ceilingAmount = defaultCeiling;
                    let rule = defaultRule;

                    if (entry && entry.type === 'fixed') {
                        return entry.value;
                    }
                    if (entry && entry.type === 'percentage') {
                        pct = entry.value;
                        ceilingAmount = entry.ceiling_amount != null ? entry.ceiling_amount : null;
                        rule = entry.ceiling_rule || null;
                    }
                    if (!pct || pct <= 0) {
                        return 0;
                    }
                    if (rule === 'ceiling_exclude' && ceilingAmount && base > ceilingAmount) {
                        return 0;
                    }
                    const effectiveBase = (rule === 'cap_base_before_percentage' && ceilingAmount) ?
                        Math.min(base, ceilingAmount) : base;

                    return effectiveBase * (pct / 100);
                }

                const pfDeduction = percentageDeduction(calc.pf_employee, basic, 12, 15000,
                    'cap_base_before_percentage');
                const employerPf = percentageDeduction(calc.pf_employer, basic, 12, 15000,
                    'cap_base_before_percentage');
                const esiDeduction = percentageDeduction(calc.esi_employee, grossSalary, 1.75, 21000,
                    'ceiling_exclude');
                const employerEsi = percentageDeduction(calc.esi_employer, grossSalary, 3.25, 21000,
                    'ceiling_exclude');

                let professionalTax;
                if (calc.pt && calc.pt.type === 'fixed') {
                    professionalTax = calc.pt.value;
                } else if (calc.pt && calc.pt.type === 'percentage') {
                    professionalTax = grossSalary * (calc.pt.value / 100);
                } else {
                    professionalTax = 200;
                }

                const totalDeductions = pfDeduction + esiDeduction + professionalTax;
                const netSalary = grossSalary - totalDeductions;
                const totalMonthlyCost = grossSalary + employerPf + employerEsi;

                updateDisplayFields({
                    monthly: {
                        basic: basic,
                        hra: hra,
                        conveyence: conveyence,
                        medical: medical,
                        children: children,
                        post: post,
                        lta: lta,
                        incentive: incentive,
                        totalAllowances: totalAllowances,
                        gross: grossSalary,
                        pf: pfDeduction,
                        esi: esiDeduction,
                        pt: professionalTax,
                        totalDeductions: totalDeductions,
                        net: netSalary,
                        employerPf: employerPf,
                        employerEsi: employerEsi,
                        totalMonthlyCost: totalMonthlyCost
                    },
                    yearly: {
                        basic: basic * 12,
                        hra: hra * 12,
                        conveyence: conveyence * 12,
                        medical: medical * 12,
                        children: children * 12,
                        post: post * 12,
                        lta: lta * 12,
                        incentive: incentive * 12,
                        totalAllowances: totalAllowances * 12,
                        gross: grossSalary * 12,
                        pf: pfDeduction * 12,
                        esi: esiDeduction * 12,
                        pt: professionalTax * 12,
                        totalDeductions: totalDeductions * 12,
                        net: netSalary * 12,
                        employerPf: employerPf * 12,
                        employerEsi: employerEsi * 12,
                        totalCTCCost: totalMonthlyCost * 12
                    }
                });

                setHiddenFields({
                    basic: basic,
                    hra: hra,
                    conveyence: conveyence,
                    medical: medical,
                    children: children,
                    post: post,
                    lta: lta,
                    incentive: incentive,
                    gross: grossSalary,
                    pf: pfDeduction,
                    esi: esiDeduction,
                    pt: professionalTax,
                    net: netSalary,
                    employerPf: employerPf,
                    employerEsi: employerEsi
                });
            }
            window.calculateSalaryBreakdown = calculateSalaryBreakdown;

            function updateDisplayFields(values) {
                $('#monthly_basic').text('₹' + formatNumber(values.monthly.basic));
                $('#monthly_hra').text('₹' + formatNumber(values.monthly.hra));
                $('#monthly_conveyance').text('₹' + formatNumber(values.monthly.conveyence));
                $('#monthly_medical').text('₹' + formatNumber(values.monthly.medical));
                $('#monthly_children').text('₹' + formatNumber(values.monthly.children));
                $('#monthly_post').text('₹' + formatNumber(values.monthly.post));
                $('#monthly_lta').text('₹' + formatNumber(values.monthly.lta));
                $('#monthly_incentive').text('₹' + formatNumber(values.monthly.incentive));
                $('#monthly_total_allowances').text('₹' + formatNumber(values.monthly.totalAllowances));
                $('#monthly_gross').text('₹' + formatNumber(values.monthly.gross));
                $('#monthly_pf').text('₹' + formatNumber(values.monthly.pf));
                $('#monthly_esi').text('₹' + formatNumber(values.monthly.esi));
                $('#monthly_pt').text('₹' + formatNumber(values.monthly.pt));
                $('#monthly_total_deductions').text('₹' + formatNumber(values.monthly.totalDeductions));
                $('#monthly_net').text('₹' + formatNumber(values.monthly.net));
                $('#monthly_employer_pf').text('₹' + formatNumber(values.monthly.employerPf));
                $('#monthly_employer_esi').text('₹' + formatNumber(values.monthly.employerEsi));
                $('#monthly_total_ctc').text('₹' + formatNumber(values.monthly.totalMonthlyCost));

                $('#yearly_basic').text('₹' + formatNumber(values.yearly.basic));
                $('#yearly_hra').text('₹' + formatNumber(values.yearly.hra));
                $('#yearly_conveyance').text('₹' + formatNumber(values.yearly.conveyence));
                $('#yearly_medical').text('₹' + formatNumber(values.yearly.medical));
                $('#yearly_children').text('₹' + formatNumber(values.yearly.children));
                $('#yearly_post').text('₹' + formatNumber(values.yearly.post));
                $('#yearly_lta').text('₹' + formatNumber(values.yearly.lta));
                $('#yearly_incentive').text('₹' + formatNumber(values.yearly.incentive));
                $('#yearly_total_allowances').text('₹' + formatNumber(values.yearly.totalAllowances));
                $('#yearly_gross').text('₹' + formatNumber(values.yearly.gross));
                $('#yearly_pf').text('₹' + formatNumber(values.yearly.pf));
                $('#yearly_esi').text('₹' + formatNumber(values.yearly.esi));
                $('#yearly_pt').text('₹' + formatNumber(values.yearly.pt));
                $('#yearly_total_deductions').text('₹' + formatNumber(values.yearly.totalDeductions));
                $('#yearly_net').text('₹' + formatNumber(values.yearly.net));
                $('#yearly_employer_pf').text('₹' + formatNumber(values.yearly.employerPf));
                $('#yearly_employer_esi').text('₹' + formatNumber(values.yearly.employerEsi));
                $('#yearly_total_ctc').text('₹' + formatNumber(values.yearly.totalCTCCost));
            }

            function setHiddenFields(values) {
                $('#basic_salary').val(values.basic.toFixed(2));
                $('#hra').val(values.hra.toFixed(2));
                $('#conveyence').val(values.conveyence.toFixed(2));
                $('#medical_allowance').val(values.medical.toFixed(2));
                $('#children_allowance').val((values.children || 0).toFixed(2));
                $('#post_allowance').val((values.post || 0).toFixed(2));
                $('#leave_travel_allowance').val((values.lta || 0).toFixed(2));
                $('#monthly_incentive').val((values.incentive || 0).toFixed(2));
                $('#special_allowance').val('0.00');
                $('#gross_salary').val(values.gross.toFixed(2));
                $('#provident_fund').val(values.pf.toFixed(2));
                $('#esi').val(values.esi.toFixed(2));
                $('#professional_tax').val(values.pt.toFixed(2));
                $('#net_salary').val(values.net.toFixed(2));
                $('#employer_provident_fund').val(values.employerPf.toFixed(2));
                $('#employer_esi').val(values.employerEsi.toFixed(2));
                $('#ctc').val((values.gross + values.employerPf + values.employerEsi).toFixed(2));
                $('input[name="salary"]').val(values.gross.toFixed(2));
            }

            $('#monthlyViewBtn').on('click', function() {
                $(this).addClass('active');
                $('#yearlyViewBtn').removeClass('active');
                $('#monthlyView').show();
                $('#yearlyView').hide();
            });

            $('#yearlyViewBtn').on('click', function() {
                $(this).addClass('active');
                $('#monthlyViewBtn').removeClass('active');
                $('#yearlyView').show();
                $('#monthlyView').hide();
            });

            $('#annual_ctc').on('input', function() {
                calculateSalaryBreakdown();
            });

            $('#payroll_structure_id').on('change', function() {
                calculateSalaryBreakdown();
            });

            // ==================== STEP NAVIGATION ====================

            $('#nextBtn').on('click', function() {
                if (validateStep(currentStep)) {
                    saveStep(currentStep, function() {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            showStep(currentStep);
                            if (currentStep === 6) {
                                setTimeout(calculateSalaryBreakdown, 100);
                            }
                        }
                    });
                }
            });

            $('#prevBtn').on('click', function() {
                if (currentStep > 1) {
                    if (validateStep(currentStep)) {
                        saveStep(currentStep, function() {
                            currentStep--;
                            showStep(currentStep);
                        });
                    } else {
                        currentStep--;
                        showStep(currentStep);
                    }
                }
            });

            $('#employeeForm').on('submit', function(e) {
                e.preventDefault();

                if (currentStep === totalSteps) {
                    if (validateStep(currentStep)) {
                        saveStep(currentStep, function() {
                            submitForm();
                        });
                    }
                } else {
                    if (validateStep(currentStep)) {
                        saveStep(currentStep, function() {
                            toastr.info('Please complete all steps to submit the form');
                        });
                    }
                }
            });
        });

        // ==================== STEP DISPLAY ====================

        function scrollDrawerBodyTo(targetOffsetTop) {
            const container = $('#employeeDrawer .offcanvas-body');
            if (!container.length) return;
            const delta = targetOffsetTop - container.offset().top;
            container.animate({
                scrollTop: container.scrollTop() + delta - 20
            }, 300);
        }

        function showStep(step) {
            $('.step-content').removeClass('active');
            $(`#step${step}`).addClass('active');

            $('.step-item').removeClass('active completed');
            for (let i = 1; i <= totalSteps; i++) {
                if (i < step) {
                    $(`.step-item[data-step="${i}"]`).removeClass('active').addClass('completed');
                } else if (i === step) {
                    $(`.step-item[data-step="${i}"]`).addClass('active').removeClass('completed');
                }
            }

            $('#prevBtn').prop('disabled', step === 1);

            if (step === totalSteps) {
                $('#nextBtn').hide();
                $('#submitBtn').show();
            } else {
                $('#nextBtn').show();
                $('#submitBtn').hide();
            }

            $('#form_step').val(step);

            const container = $('#employeeDrawer .offcanvas-body');
            if (container.length) container.animate({
                scrollTop: 0
            }, 200);
        }

        // ==================== VALIDATION ====================

        function validateStep(step) {
            $(`#step${step} .is-invalid`).removeClass('is-invalid');
            $(`#step${step} .invalid-feedback`).remove();

            let isValid = true;
            const currentStepElement = $(`#step${step}`);

            if (step === 1) {
                const name = currentStepElement.find('input[name="name"]');
                if (!name.val().trim()) {
                    showError(name, 'Name is required');
                    isValid = false;
                } else if (name.val().trim().length < 2) {
                    showError(name, 'Name must be at least 2 characters');
                    isValid = false;
                }

                const email = currentStepElement.find('input[name="email"]');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email.val()) {
                    showError(email, 'Email is required');
                    isValid = false;
                } else if (!emailRegex.test(email.val())) {
                    showError(email, 'Please enter a valid email address');
                    isValid = false;
                }

                const phone = currentStepElement.find('input[name="contact"]');
                const phoneRegex = /^\d{10}$/;
                if (!phone.val()) {
                    showError(phone, 'Phone number is required');
                    isValid = false;
                } else if (!phoneRegex.test(phone.val())) {
                    showError(phone, 'Please enter a valid 10-digit phone number');
                    isValid = false;
                }

                if (wizardMode === 'add') {
                    const password = currentStepElement.find('input[name="password"]');
                    if (!password.val()) {
                        showError(password, 'Password is required');
                        isValid = false;
                    } else if (password.val().length < 6) {
                        showError(password, 'Password must be at least 6 characters');
                        isValid = false;
                    } else if (password.val().length > 10) {
                        showError(password, 'Password must not exceed 10 characters');
                        isValid = false;
                    }

                    const confirmPass = currentStepElement.find('input[name="password_confirmation"]');
                    if (password.val() !== confirmPass.val()) {
                        showError(confirmPass, 'Passwords do not match');
                        isValid = false;
                    }
                }
            }

            if (step === 2) {
                const dob = currentStepElement.find('input[name="dob"]');
                if (dob.val()) {
                    const selectedDate = new Date(dob.val());
                    const today = new Date();
                    if (selectedDate > today) {
                        showError(dob, 'Date of birth cannot be in the future');
                        isValid = false;
                    }
                }
            }

            if (step === 3) {
            }

            if (step === 4) {
                const permanentAddress = currentStepElement.find('textarea[name="permanent_address"]');
                if (!permanentAddress.val().trim()) {
                    showError(permanentAddress, 'Permanent address is required');
                    isValid = false;
                }
            }

            if (step === 5) {
                const ifsc = currentStepElement.find('input[name="ifsc"]');
                if (ifsc.val() && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.val().toUpperCase())) {
                    showError(ifsc, 'Please enter a valid IFSC code');
                    isValid = false;
                }

                const aadhaar = currentStepElement.find('input[name="aadhaar_no"]');
                if (aadhaar.val() && !/^\d{12}$/.test(aadhaar.val())) {
                    showError(aadhaar, 'Please enter a valid 12-digit Aadhaar number');
                    isValid = false;
                }

                const pan = currentStepElement.find('input[name="pan_no"]');
                if (pan.val() && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.val().toUpperCase())) {
                    showError(pan, 'Please enter a valid PAN number');
                    isValid = false;
                }
            }

            if (step === 6) {
                const annualCTC = currentStepElement.find('input[name="annual_ctc"]');
                if (!annualCTC.val()) {
                    showError(annualCTC, 'Annual CTC is required');
                    isValid = false;
                } else if (parseFloat(annualCTC.val()) < 100000) {
                    showError(annualCTC, 'Annual CTC must be at least ₹1,00,000');
                    isValid = false;
                }

                const effectiveDate = currentStepElement.find('input[name="salary_effective_date"]');
                if (!effectiveDate.val()) {
                    showError(effectiveDate, 'Effective date is required');
                    isValid = false;
                }
            }

            if (!isValid) {
                const firstError = $(`#step${step} .is-invalid:first`);
                if (firstError.length) {
                    scrollDrawerBodyTo(firstError.offset().top - 130);
                }
            }

            return isValid;
        }

        function showError(element, message) {
            element.addClass('is-invalid');
            if (!element.next('.invalid-feedback').length) {
                element.after(`<div class="invalid-feedback">${message}</div>`);
            } else {
                element.next('.invalid-feedback').text(message);
            }
        }

        // ==================== AJAX ====================

        function saveStep(step, callback) {
            const nextBtn = $('#nextBtn');
            const submitBtn = $('#submitBtn');
            const originalText = nextBtn.is(':visible') ? nextBtn.html() : submitBtn.html();

            if (nextBtn.is(':visible')) {
                nextBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop(
                    'disabled', true);
            } else {
                submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop(
                    'disabled', true);
            }

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('step', step);
            formData.append('_token', '{{ csrf_token() }}');

            let url;
            if (wizardMode === 'edit') {
                url = editUpdateStepUrlTemplate.replace('__ID__', encodeURIComponent(editEncryptedId));
            } else {
                url = "{{ route('employee.save.step') }}";
                if (employeeId) {
                    formData.append('employee_id', employeeId);
                }
            }

            $.ajax({
                url: url,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (nextBtn.is(':visible')) {
                        nextBtn.html(originalText).prop('disabled', false);
                    } else {
                        submitBtn.html(originalText).prop('disabled', false);
                    }

                    if (response.success) {
                        if (wizardMode === 'add' && response.employee_id) {
                            employeeId = response.employee_id;
                            $('#employee_id').val(employeeId);
                        }

                        toastr.success(response.message || `Step ${step} saved successfully`);

                        if (callback) callback();
                    }
                },
                error: function(xhr) {
                    if (nextBtn.is(':visible')) {
                        nextBtn.html(originalText).prop('disabled', false);
                    } else {
                        submitBtn.html(originalText).prop('disabled', false);
                    }

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;

                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();

                        $.each(errors, function(field, messages) {
                            const input = $(`#employeeForm [name="${field}"]`);
                            if (input.length) {
                                input.addClass('is-invalid');
                                if (!input.next('.invalid-feedback').length) {
                                    input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                                }
                            }
                        });

                        toastr.error('Please fix the errors and try again');

                        const firstError = $('#employeeForm .is-invalid:first');
                        if (firstError.length) {
                            scrollDrawerBodyTo(firstError.offset().top - 130);
                        }
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'An error occurred while saving');
                    }
                }
            });
        }

        function displayErrors(errors, step) {
            const stepElement = $(`#step${step}`);

            $.each(errors, function(field, messages) {
                const input = stepElement.find(`[name="${field}"]`);
                if (input.length) {
                    showError(input, messages[0]);
                } else {
                    const arrayInput = stepElement.find(`[name="${field}[]"]`);
                    if (arrayInput.length) {
                        showError(arrayInput, messages[0]);
                    }
                }
            });
        }

        function loadSavedData(employeeCode) {
            $.ajax({
                url: "{{ route('employee.load.data') }}",
                type: "GET",
                data: {
                    employee_id: employeeCode
                },
                success: function(response) {
                    if (response.data) {
                        populateFormData(response.data);

                        currentStep = 1;
                        showStep(currentStep);
                    }
                },
                error: function() {
                    toastr.error('Could not load employee data');
                }
            });
        }

        function populateFormData(data) {
            if (data.user) {
                $('#employeeForm input[name="name"]').val(data.user.name);
                $('#employeeForm input[name="email"]').val(data.user.email);
                $('#employeeForm input[name="contact"]').val(data.user.contact);
                $('#employeeForm select[name="role"]').val(data.user.role);
                $('#employeeForm select[name="status"]').val(String(data.user.status));
            }

            if (data.basic) {
                $('#employeeForm input[name="personal_email"]').val(data.basic.personal_email);
                $('#employeeForm input[name="alternate_phone"]').val(data.basic.alternate_phone);
                $('#employeeForm select[name="gender"]').val(data.basic.gender);
                $('#employeeForm input[name="dob"]').val(data.basic.dob);
                $('#employeeForm input[name="blood_group"]').val(data.basic.blood_group);
                $('#employeeForm select[name="marital_status"]').val(data.basic.marital_status);
                $('#employeeForm input[name="father_name"]').val(data.basic.father_name);
                $('#employeeForm input[name="mother_name"]').val(data.basic.mother_name);
                $('#employeeForm input[name="aadhaar_no"]').val(data.basic.aadhaar_no);
                $('#employeeForm input[name="pan_no"]').val(data.basic.pan_no);
                $('#employeeForm input[name="passport_number"]').val(data.basic.passport_number);

                if (data.basic.language && $.fn.select2) {
                    try {
                        const languages = JSON.parse(data.basic.language);
                        $('#employeeForm select[name="language[]"]').val(languages).trigger('change');
                    } catch (e) {
                        /* no saved languages */
                    }
                }

                $('#employeeForm textarea[name="about"]').val(data.basic.about);

                if (data.basic.profile_image) {
                    $('#profileImagePreview').attr('src', data.profile_image_url || ('/' + data.basic.profile_image));
                }
            }

            if (data.job) {
                $('#employeeForm select[name="department"]').val(data.job.department);
                $('#employeeForm select[name="designation"]').val(data.job.designation);
                $('#employeeForm select[name="reporting_head[]"]')
                    .val(data.job.reporting_head_ids || [])
                    .trigger('change');
                $('#employeeForm select[name="employment_type"]').val(data.job.employment_type);
                $('#employeeForm select[name="type"]').val(data.job.type);
                $('#employeeForm select[name="branch"]').val(data.job.office_branch || '');
                $('#employeeForm select[name="company_branch"]').val(data.job.branch_id || '');
                $('#employeeForm input[name="joining_date"]').val(data.job.joining_date);

                if (data.job.leave_assigned && $.fn.select2) {
                    try {
                        const leaveTypes = JSON.parse(data.job.leave_assigned);
                        $('#employeeForm select[name="leave_type_assigned[]"]').val(leaveTypes).trigger(
                            'change');
                    } catch (e) {
                        /* no saved leave types */
                    }
                }
            }

            if (data.location) {
                $('#employeeForm select[name="country"]').val(data.location.country);

                if (data.location.country) {
                    $.ajax({
                        url: "{{ route('get.states') }}",
                        type: "GET",
                        data: {
                            country_code: data.location.country
                        },
                        async: false,
                        success: function(states) {
                            $('#state').html('<option value="">Select State</option>');
                            $.each(states, function(key, state) {
                                $('#state').append('<option value="' + state.state_code + '">' + state
                                    .name + '</option>');
                            });
                            $('#state').val(data.location.state);

                            if (data.location.state) {
                                $.ajax({
                                    url: "{{ route('get.cities') }}",
                                    type: "GET",
                                    data: {
                                        state_code: data.location.state
                                    },
                                    async: false,
                                    success: function(cities) {
                                        $('#city').html('<option value="">Select City</option>');
                                        $.each(cities, function(key, city) {
                                            $('#city').append('<option value="' + city
                                                .city_code + '">' + city.name +
                                                '</option>');
                                        });
                                        $('#city').val(data.location.city);
                                    }
                                });
                            }
                        }
                    });
                }

                $('#employeeForm textarea[name="permanent_address"]').val(data.location.permanent_address);
                $('#employeeForm textarea[name="current_address"]').val(data.location.address || data
                    .location.current_address);
                $('#employeeForm input[name="pin_code"]').val(data.location.pincode || data.location
                    .pin_code);
            }

            if (data.bank) {
                $('#employeeForm input[name="bank_name"]').val(data.bank.bank_name);
                $('#employeeForm input[name="account_number"]').val(data.bank.account_number);
                $('#employeeForm input[name="ifsc"]').val(data.bank.ifsc);
                $('#employeeForm input[name="branch_name"]').val(data.bank.branch_name);
            }

            if (data.payroll) {
                $('#employeeForm select[name="payroll_structure_id"]').val(data.payroll.payroll_structure_id);
                $('#employeeForm input[name="annual_ctc"]').val(data.payroll.ctc);
                $('#employeeForm input[name="salary_effective_date"]').val(data.payroll.effective_from);

                setTimeout(function() {
                    if (typeof window.calculateSalaryBreakdown === 'function') {
                        window.calculateSalaryBreakdown();
                    }
                }, 400);
            } else if (data.job && data.job.salary) {
                $('#employeeForm input[name="annual_ctc"]').val((parseFloat(data.job.salary) * 12).toFixed(0));
                setTimeout(function() {
                    if (typeof window.calculateSalaryBreakdown === 'function') {
                        window.calculateSalaryBreakdown();
                    }
                }, 400);
            }

            populateDocumentRows(data.documents || []);
        }

        // ==================== Dynamic Documents (Step 7) ====================
        let documentRowIndex = 0;

        function addDocumentRow(existingDoc) {
            const index = documentRowIndex++;
            const tpl = document.getElementById('documentRowTemplate');
            const $row = $(tpl.content.cloneNode(true)).find('.document-row');

            $row.find('.doc-id-input').attr('name', `documents[${index}][id]`);
            $row.find('.doc-type-select').attr('name', `documents[${index}][document_type]`);
            $row.find('.doc-type-other-input').attr('name', `documents[${index}][document_type_other]`);
            $row.find('.doc-name-input').attr('name', `documents[${index}][document_name]`);
            $row.find('.doc-file-input').attr('name', `documents[${index}][file]`);

            if (existingDoc) {
                $row.find('.doc-id-input').val(existingDoc.id || '');
                $row.find('.doc-type-select').val(existingDoc.document_type || '');
                $row.find('.doc-type-other-input').val(existingDoc.document_type_other || '');
                $row.find('.doc-name-input').val(existingDoc.document_name || '');
                if (existingDoc.file_path) {
                    $row.find('.doc-current-file').html(
                        '<a href="' + existingDoc.file_path + '" target="_blank">' +
                        (existingDoc.filename || 'View current file') + '</a>'
                    );
                }
            }

            if ($row.find('.doc-type-select').val() === 'other') {
                $row.find('.doc-type-other-group').removeClass('d-none');
            }

            $('#documentsContainer').append($row);

            if ($.fn.select2) {
                $row.find('.doc-type-select').select2({
                    width: '100%',
                    dropdownParent: $('#documentsContainer')
                });
            }
        }

        function populateDocumentRows(documents) {
            $('#documentsContainer').empty();
            documentRowIndex = 0;
            (documents || []).forEach(function(doc) {
                addDocumentRow(doc);
            });
            if (!documents || documents.length === 0) {
                addDocumentRow(null);
            }
        }

        $(document).on('click', '#addDocumentRowBtn', function() {
            addDocumentRow(null);
        });

        $(document).on('click', '.remove-document-row', function() {
            $(this).closest('.document-row').remove();
        });

        $(document).on('change', '.doc-type-select', function() {
            const $group = $(this).closest('.document-row').find('.doc-type-other-group');
            $group.toggleClass('d-none', $(this).val() !== 'other');
        });

        function resetAllFields() {
            const spanIds = [
                'monthly_hra', 'monthly_conveyance', 'monthly_medical', 'monthly_children', 'monthly_post',
                'monthly_lta', 'monthly_incentive', 'monthly_total_allowances', 'monthly_basic',
                'monthly_gross', 'monthly_pf', 'monthly_esi', 'monthly_pt', 'monthly_total_deductions',
                'monthly_net', 'monthly_employer_pf', 'monthly_employer_esi', 'monthly_total_ctc',
                'yearly_hra', 'yearly_conveyance', 'yearly_medical', 'yearly_children', 'yearly_post',
                'yearly_lta', 'yearly_incentive', 'yearly_total_allowances', 'yearly_basic', 'yearly_gross',
                'yearly_pf', 'yearly_esi', 'yearly_pt', 'yearly_total_deductions', 'yearly_net',
                'yearly_employer_pf', 'yearly_employer_esi', 'yearly_total_ctc'
            ];
            spanIds.forEach(id => $('#' + id).text('0.00'));

            $('#employeeForm input[type="hidden"][id]').each(function() {
                const id = $(this).attr('id');
                if (['current_step', 'form_step', 'employee_id', 'csrf'].indexOf(id) === -1) {
                    $(this).val('');
                }
            });
        }

        function formatNumber(num) {
            if (num === 0) return '0.00';

            let numStr = num.toFixed(2);
            let parts = numStr.split('.');
            let integerPart = parts[0];
            let decimalPart = parts[1];

            let lastThree = integerPart.slice(-3);
            let otherNumbers = integerPart.slice(0, -3);

            if (otherNumbers !== '') {
                integerPart = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + "," + lastThree;
            }

            return integerPart + '.' + decimalPart;
        }

        function submitForm() {
            const label = wizardMode === 'edit' ? 'Save Changes' : 'Submit';
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...')
                .prop('disabled', true);

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('_token', '{{ csrf_token() }}');

            let url;
            if (wizardMode === 'edit') {
                url = editCompleteUpdateUrlTemplate.replace('__ID__', encodeURIComponent(editEncryptedId));
            } else {
                url = "{{ route('employee.complete.store') }}";
            }

            $.ajax({
                url: url,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        toastr.success(wizardMode === 'edit' ? 'Employee updated successfully!' :
                            'Employee created successfully!');

                        const offEl = document.getElementById('employeeDrawer');
                        const off = bootstrap.Offcanvas.getInstance(offEl);
                        if (off) off.hide();

                        setTimeout(function() {
                            window.location.reload();
                        }, 900);
                    } else {
                        $('#submitBtn').html(`<i class="feather-check"></i> <span id="submitBtnLabel">${label}</span>`)
                            .prop('disabled', false);
                        toastr.error(response.message || 'Error completing registration');
                    }
                },
                error: function(xhr) {
                    $('#submitBtn').html(`<i class="feather-check"></i> <span id="submitBtnLabel">${label}</span>`)
                        .prop('disabled', false);

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        displayErrors(errors, currentStep);
                        toastr.error('Please fix the errors and try again');

                        const firstError = $('#employeeForm .is-invalid:first');
                        if (firstError.length) {
                            scrollDrawerBodyTo(firstError.offset().top - 130);
                        }
                    } else {
                        toastr.error('An error occurred while submitting the form');
                    }
                }
            });
        }
    </script>
@endsection
