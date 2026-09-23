{{--
    Shared look for the expense finance pages (Payment Management, Payment Vouchers):
    dashboard-style stat tiles, a single-row filter bar with captions + search, a
    compact blue table and round icon action buttons. Everything is scoped under
    `.ex-page` so it never leaks into the rest of the theme.
    Usage: put class="main-content ex-page" on the page wrapper and
    @include('client.expense._ui') inside @section('style').
--}}
<style>
    .ex-page {
        --x-card: #ffffff;
        --x-border: #eaeef5;
        --x-border-strong: #dfe5f0;
        --x-text: #1a2236;
        --x-soft: #6b7385;
        --x-muted: #9aa1b1;
        --x-blue: #1e3a8a;
        --x-blue-2: #2563eb;
        --x-chip: #e3edfe;
        --x-shadow: 0 1px 2px rgba(20, 30, 60, .04), 0 2px 8px rgba(20, 30, 60, .04);
        --x-shadow-hover: 0 6px 22px rgba(30, 50, 110, .10);
    }

    /* ---------- section header + stat tiles (same look as the admin dashboard) ---------- */
    .ex-page .ex-hdr {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        background: #f7f8fb;
        border: 1px solid var(--x-border);
        border-radius: 10px;
        padding: 7px 12px;
        margin-bottom: 8px;
    }

    .ex-page .ex-hdr-left {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .ex-page .ex-hdr-icon {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        flex: none;
        background: var(--x-chip);
        color: var(--x-blue);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
    }

    .ex-page .ex-hdr-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--x-text);
        margin: 0;
    }

    .ex-page .ex-hdr-hint {
        font-size: 10.5px;
        color: var(--x-soft);
    }

    .ex-page .ex-tiles {
        --bs-gutter-x: 8px;
        --bs-gutter-y: 8px;
        margin-bottom: 14px;
    }

    .ex-page .ex-tile {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--x-card);
        border: 1px solid var(--x-border);
        border-radius: 14px;
        padding: 10px 12px;
        box-shadow: var(--x-shadow);
        height: 100%;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .ex-page .ex-tile:hover {
        transform: translateY(-3px);
        box-shadow: var(--x-shadow-hover);
        border-color: var(--x-border-strong);
    }

    .ex-page .ex-tile-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        background: var(--x-chip);
        color: var(--x-blue);
    }

    .ex-page .ex-tile-val {
        font-size: 14px;
        font-weight: 800;
        color: var(--x-text);
        line-height: 1.15;
        letter-spacing: -.3px;
        margin: 0;
    }

    .ex-page .ex-tile-label {
        font-size: 10px;
        font-weight: 600;
        color: var(--x-soft);
        line-height: 1.2;
    }

    .ex-page .ex-tile-sub {
        font-size: 9.5px;
        color: var(--x-muted);
    }

    /* ---------- filter bar: ONE row on desktop, wraps only below 1200px ---------- */
    .ex-page .filter-wrapper {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #edf2f7;
        padding: 14px 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .ex-page .filter-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
    }

    .ex-page .filter-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }

    .ex-page .filter-title i {
        color: var(--x-blue);
        font-size: 15px;
    }

    .ex-page .filter-title span {
        background: var(--x-chip);
        color: var(--x-blue);
        font-size: 10.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
    }

    .ex-page .filter-desc {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .ex-page .clear-all-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #64748b;
        font-size: 11.5px;
        padding: 3px 10px;
        border-radius: 20px;
        text-decoration: none;
        white-space: nowrap;
        transition: all .2s;
    }

    .ex-page .clear-all-link:hover {
        background: var(--x-chip);
        color: var(--x-blue);
    }

    .ex-page .filter-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: 8px;
    }

    .ex-page .filter-item {
        flex: 1 1 0;
        min-width: 0;
    }

    .ex-page .filter-item.search {
        flex: 1.8 1 0;
    }

    .ex-page .filter-item.reset {
        flex: 0 0 auto;
    }

    .ex-page .filter-label {
        display: block;
        font-size: 10.5px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 3px;
        white-space: nowrap;
    }

    .ex-page .search-wrapper {
        position: relative;
        width: 100%;
    }

    .ex-page .search-wrapper i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
        pointer-events: none;
    }

    .ex-page .search-wrapper .form-control {
        width: 100%;
        height: 34px;
        padding: 5px 10px 5px 30px;
        font-size: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .ex-page .filter-select {
        width: 100%;
        height: 34px;
        padding: 5px 24px 5px 9px;
        font-size: 12px;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 8px center;
        background-size: 14px;
        appearance: none;
        cursor: pointer;
    }

    .ex-page .filter-select.filter-date {
        padding-right: 6px;
        background-image: none;
    }

    .ex-page .search-wrapper .form-control:focus,
    .ex-page .filter-select:focus {
        background-color: #fff;
        border-color: var(--x-blue);
        box-shadow: 0 0 0 3px rgba(30, 58, 138, .10);
        outline: none;
    }

    .ex-page .reset-btn {
        height: 34px;
        padding: 0 12px;
        background: #fff;
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
        transition: all .2s;
    }

    .ex-page .reset-btn:hover {
        background: var(--x-chip);
        border-color: var(--x-blue);
        color: var(--x-blue);
    }

    .ex-page .active-filters {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e2e8f0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .ex-page .active-filters-label {
        font-size: 10.5px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .3px;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 20px;
    }

    .ex-page .filter-tag {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 30px;
        padding: 2px 9px 2px 8px;
        font-size: 11px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #334155;
        text-decoration: none;
    }

    .ex-page .filter-tag i {
        color: var(--x-blue);
        font-size: 11px;
    }

    .ex-page .filter-tag .remove-tag {
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
    }

    .ex-page .filter-tag .remove-tag:hover {
        color: var(--x-blue);
    }

    .ex-page .filter-tag.clear-all {
        background: var(--x-chip);
        border-color: var(--x-blue);
        color: var(--x-blue);
        font-weight: 600;
    }

    /* employee picker inside the filter row */
    .ex-page .custom-employee-dropdown .btn {
        height: 34px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #1e293b;
        font-size: 12px;
        padding: 0 9px;
    }

    .ex-page .custom-employee-dropdown .btn:hover {
        background: #fff;
        border-color: #cbd5e1;
    }

    .ex-page .custom-employee-dropdown .btn:focus {
        border-color: var(--x-blue);
        box-shadow: 0 0 0 3px rgba(30, 58, 138, .10);
    }

    .ex-page .custom-employee-dropdown .dropdown-menu {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
        padding: 8px;
        max-height: 300px;
        overflow-y: auto;
        min-width: 250px;
    }

    .ex-page .custom-employee-dropdown .dropdown-item {
        padding: 7px 10px;
        border-radius: 6px;
        font-size: 12.5px;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .ex-page .custom-employee-dropdown .dropdown-item:hover {
        background: #f1f5f9;
    }

    .ex-page .custom-employee-dropdown .dropdown-item.active {
        background: var(--x-chip);
        color: var(--x-blue);
    }

    .ex-page .custom-employee-dropdown .dropdown-item.active .text-muted {
        color: var(--x-blue) !important;
        opacity: .8;
    }

    .ex-page .employee-initials,
    .ex-page .employee-initials-sm {
        width: 28px;
        height: 28px;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 11px;
        flex-shrink: 0;
    }

    .ex-page .employee-initials-sm {
        width: 22px;
        height: 22px;
        font-size: 10px;
    }

    /* ---------- table ---------- */
    .ex-page .card {
        border: 1px solid var(--x-border);
        border-radius: 14px;
        box-shadow: var(--x-shadow);
    }

    .ex-page .card-header {
        padding: 10px 14px;
        background: #fff;
        border-bottom: 1px solid var(--x-border);
    }

    .ex-page .card-header .card-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--x-text);
    }

    .ex-page .table {
        margin-bottom: 0;
    }

    .ex-page .table th {
        background: #f7f8fb;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #64748b;
        border-bottom: 1px solid var(--x-border);
        padding: 9px 12px;
        white-space: nowrap;
    }

    .ex-page .table td {
        padding: 8px 12px;
        vertical-align: middle;
        font-size: 12px;
        color: var(--x-text);
        border-bottom: 1px solid #eef1f7;
    }

    .ex-page .table tbody tr:hover {
        background: #f6f8fd;
    }

    .ex-page .table tbody tr:last-child td {
        border-bottom: none;
    }

    .ex-page .col-sr {
        width: 60px;
        white-space: nowrap;
        color: #94a3b8;
    }

    .ex-page .code-link {
        font-weight: 700;
        color: var(--x-blue);
        text-decoration: none;
    }

    .ex-page a.code-link:hover {
        color: var(--x-blue-2);
        text-decoration: underline;
    }

    .ex-page .employee-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ex-page .employee-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 11px;
        flex-shrink: 0;
    }

    .ex-page .employee-details {
        line-height: 1.3;
    }

    .ex-page .employee-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 12.5px;
    }

    .ex-page .employee-email {
        font-size: 10.5px;
        color: #64748b;
    }

    /* badges: one blue family (grey for the negative state) */
    .ex-page .badge {
        padding: 3px 10px;
        font-weight: 600;
        font-size: 10.5px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ex-page .badge.bg-success {
        background: rgba(29, 78, 216, .14) !important;
        color: #1d4ed8;
    }

    .ex-page .badge.bg-info {
        background: rgba(14, 165, 233, .14) !important;
        color: #0c87c4;
    }

    .ex-page .badge.bg-primary {
        background: var(--x-chip) !important;
        color: var(--x-blue);
    }

    .ex-page .badge.bg-warning {
        background: rgba(96, 165, 250, .20) !important;
        color: #2563eb;
    }

    .ex-page .badge.bg-danger {
        background: #eef1f7 !important;
        color: #64748b;
        text-decoration: line-through;
    }

    /* round icon buttons */
    .ex-page .action-btn {
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: var(--x-blue);
        border: 1px solid #bcd0f5;
        transition: all .18s ease;
        cursor: pointer;
        text-decoration: none;
    }

    .ex-page .action-btn i {
        font-size: 14px;
        line-height: 1;
    }

    .ex-page .action-btn:hover {
        background: var(--x-chip);
        border-color: var(--x-blue);
        color: var(--x-blue);
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(30, 58, 138, .18);
    }

    .ex-page .action-btn.primary {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border-color: transparent;
    }

    .ex-page .action-btn.primary:hover {
        background: linear-gradient(135deg, #172c6b, #1d4ed8);
        color: #fff;
    }

    .ex-page .action-btn.muted {
        color: #475569;
        border-color: #dfe5f0;
    }

    .ex-page .action-btn.muted:hover {
        color: var(--x-blue);
        border-color: var(--x-blue);
    }

    .ex-page .card-footer {
        background: #fafbfe;
        border-top: 1px solid var(--x-border);
        padding: 8px 14px;
    }

    .ex-page .page-link {
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 12px;
        padding: 5px 11px;
        border-radius: 6px !important;
    }

    .ex-page .page-item.active .page-link {
        background: var(--x-blue);
        border-color: var(--x-blue);
        color: #fff;
    }

    .ex-page .pagination {
        margin: 0;
        gap: 4px;
    }

    /* ---------- responsive ---------- */
    @media (max-width: 1199.98px) {
        .ex-page .filter-row {
            flex-wrap: wrap;
        }

        .ex-page .filter-item {
            flex: 1 1 calc(25% - 8px);
            min-width: 130px;
        }

        .ex-page .filter-item.search {
            flex: 1 1 calc(50% - 8px);
        }

        .ex-page .filter-item.reset {
            flex: 0 0 auto;
            min-width: 0;
        }
    }

    @media (max-width: 767.98px) {
        .ex-page .filter-item,
        .ex-page .filter-item.search {
            flex: 1 1 100%;
        }

        .ex-page .filter-item.reset {
            flex: 1 1 100%;
        }

        .ex-page .reset-btn {
            justify-content: center;
        }
    }
</style>
