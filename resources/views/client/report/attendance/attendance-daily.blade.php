@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== MODERN STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 21px;
        }

        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 15px 14px;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #eef2f6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
            cursor: default;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }

        .stats-card.total-card::before {
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
        }

        .stats-card.present-card::before {
            background: linear-gradient(90deg, #1e3a8a, #2563eb);
        }

        .stats-card.absent-card::before {
            background: linear-gradient(90deg, #475569, #94a3b8);
        }

        .stats-card.leave-card::before {
            background: linear-gradient(90deg, #2563eb, #2563eb);
        }

        .stats-icon-wrapper {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .stats-card:hover .stats-icon-wrapper {
            transform: scale(1.05);
        }

        .total-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.12), rgba(79, 70, 229, 0.05));
        }

        .total-card .stats-icon-wrapper i {
            color: #1e3a8a;
            font-size: 18px;
        }

        .present-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.05));
        }

        .present-card .stats-icon-wrapper i {
            color: #1e3a8a;
            font-size: 18px;
        }

        .absent-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(239, 68, 68, 0.05));
        }

        .absent-card .stats-icon-wrapper i {
            color: #475569;
            font-size: 18px;
        }

        .leave-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.05));
        }

        .leave-card .stats-icon-wrapper i {
            color: #2563eb;
            font-size: 18px;
        }

        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 2px;
            letter-spacing: -0.5px;
        }

        .stats-label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }

        .stats-change {
            font-size: 9.5px;
            font-weight: 500;
            margin-top: 3px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .stats-change.up {
            color: #1e3a8a;
            background: rgba(16, 185, 129, 0.1);
        }

        .stats-change.down {
            color: #475569;
            background: rgba(239, 68, 68, 0.1);
        }

        /* ==================== MODERN FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            padding: 14px 17px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .filter-wrapper:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 11px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-title i {
            color: #1e3a8a;
            font-size: 14px;
            background: #e3edfe;
            padding: 4px;
            border-radius: 8px;
        }

        .filter-title span {
            background: #e3edfe;
            color: #1e3a8a;
            font-size: 9.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 20px;
            margin-left: 3px;
        }

        .clear-all-link {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 10px;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 20px;
            transition: all 0.2s;
            background: #f8fafc;
            border: 1px solid #eef2f6;
        }

        .clear-all-link:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            color: #475569;
        }

        .clear-all-link i {
            font-size: 11px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .filter-item {
            flex: 0 0 auto;
            min-width: 150px;
        }

        .filter-item.search-filter {
            flex: 1;
            min-width: 200px;
        }

        /* Search Wrapper */
        .search-wrapper {
            position: relative;
            width: 100%;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
            pointer-events: none;
            transition: color 0.3s;
        }

        .search-wrapper:focus-within i {
            color: #1e3a8a;
        }

        .search-wrapper .form-control {
            width: 100%;
            height: 40px;
            padding: 6px 10px 6px 27px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.3s;
            color: #0f172a;
        }

        .search-wrapper .form-control:focus {
            border-color: #1e3a8a;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .search-wrapper .form-control::placeholder {
            color: #94a3b8;
            font-size: 10px;
        }

        /* Select Dropdowns */
        .filter-select {
            width: 100%;
            height: 40px;
            padding: 6px 22px 6px 10px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 12px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-select:hover {
            background-color: white;
            border-color: #cbd5e1;
        }

        .filter-select:focus {
            border-color: #1e3a8a;
            outline: none;
            background-color: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .filter-select option {
            padding: 6px;
        }

        /* date Picker */
        .date-picker {
            min-width: 170px;
        }

        .date-picker input {
            height: 40px;
            padding: 6px 10px;
            font-size: 10.5px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.3s;
            width: 100%;
            color: #0f172a;
            font-weight: 500;
            cursor: pointer;
        }

        .date-picker input:hover {
            background: white;
            border-color: #cbd5e1;
        }

        .date-picker input:focus {
            border-color: #1e3a8a;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        /* Buttons */
        .apply-btn {
            height: 40px;
            padding: 0 20px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .apply-btn:hover {
            background: #16295e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .apply-btn:active {
            transform: translateY(0);
        }

        .reset-btn {
            height: 40px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .reset-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .reset-btn i {
            font-size: 11px;
        }

        /* ==================== EMPLOYEE DROPDOWN ==================== */
        .custom-employee-dropdown .btn {
            height: 40px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            color: #0f172a;
            font-size: 10.5px;
            padding: 0 14px;
            width: 100%;
            transition: all 0.3s;
            font-weight: 500;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .custom-employee-dropdown .btn .feather-chevron-down {
            font-size: 11px;
            color: #94a3b8;
            transition: transform 0.3s;
        }

        .custom-employee-dropdown .btn.show .feather-chevron-down {
            transform: rotate(180deg);
        }

        .employee-initials {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #1e3a8a, #1e3a8a);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 10px;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
        }

        .employee-initials-sm {
            width: 26px;
            height: 26px;
            font-size: 9.5px;
            background: linear-gradient(135deg, #1e3a8a, #1e3a8a);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25);
        }

        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #eef2f6;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
            padding: 6px;
            max-height: 320px;
            overflow-y: auto;
            min-width: 260px;
            margin-top: 3px;
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 10.5px;
            color: #1e293b;
            margin-bottom: 2px;
            transition: all 0.2s;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #e3edfe;
            color: #1e3a8a;
            font-weight: 500;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #1e3a8a !important;
            opacity: 0.8;
        }

        .employee-name {
            color: #0f172a;
            font-weight: 500;
        }

        /* ==================== ACTIVE FILTER TAGS ==================== */
        .active-filters {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1.5px dashed #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .active-filters-label {
            font-size: 9.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .filter-tag {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 3px 8px 3px 7px;
            font-size: 10px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            color: #334155;
        }

        .filter-tag:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .filter-tag i {
            color: #1e3a8a;
            font-size: 10px;
        }

        .filter-tag .remove-tag {
            color: #94a3b8;
            margin-left: 2px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            padding: 2px;
            border-radius: 50%;
            line-height: 1;
        }

        .filter-tag .remove-tag:hover {
            color: #475569;
            background: rgba(239, 68, 68, 0.08);
        }

        .filter-tag.clear-all {
            background: #e3edfe;
            border-color: #1e3a8a;
            color: #1e3a8a;
            font-weight: 600;
            text-decoration: none;
            padding: 3px 10px;
        }

        .filter-tag.clear-all:hover {
            background: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }

        .filter-tag.clear-all i {
            color: currentColor;
        }

        /* ==================== TABLE STYLES ==================== */
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            padding: 8px 8px;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #e2e8f0;
            border-top: none;
        }

        .table tbody td {
            vertical-align: middle;
            font-size: 10px;
            padding: 7px 8px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        .table tbody tr {
            transition: all 0.2s;
        }

        .table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-responsive {
            border-radius: 0 0 16px 16px;
        }

        /* Location text */
        .location-text {
            max-width: 180px;
            display: inline-block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            font-size: 9.5px;
            padding: 2px 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .location-text:hover {
            white-space: normal;
            overflow: visible;
            background: #f1f5f9;
            position: relative;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 150px;
        }

        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #e3edfe, #e3edfe);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.1);
            transition: all 0.3s;
        }

        .employee-info:hover .employee-avatar {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .employee-details {
            line-height: 1.3;
            min-width: 0;
        }

        .employee-name-text {
            font-weight: 600;
            color: #0f172a;
            font-size: 10.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s;
        }

        .employee-info:hover .employee-name-text {
            color: #1e3a8a;
        }

        .employee-email-text {
            font-size: 9.5px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        /* ==================== BADGES ==================== */
        .badge {
            padding: 3px 8px;
            font-weight: 600;
            font-size: 9px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .badge-present {
            background: #e3edfe !important;
            color: #1e3a8a;
            border-color: #93c5fd;
        }

        .badge-absent {
            background: #e2e8f0 !important;
            color: #475569;
            border-color: #cbd5e1;
        }

        .badge-on_leave {
            background: #bfd3f7 !important;
            color: #1e3a8a;
            border-color: #60a5fa;
        }
         .badge-halfday {
            background: #e3edfe !important;
            color: #2563eb;
            border-color: #2563eb;
        }

        .badge-holiday {
            background: #dbeafe !important;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .badge-week_off {
            background: #e3edfe !important;
            color: #16295e;
            border-color: #93c5fd;
        }

        .badge-checked_in_only {
            background: #bfd3f7 !important;
            color: #1e3a8a;
            border-color: #60a5fa;
        }

        .badge-present small,
        .badge-absent small,
        .badge-on_leave small,
        .badge-holiday small,
        .badge-halfday halfday,
        .badge-week_off small,
        .badge-checked_in_only small {
            font-weight: 400;
            opacity: 0.8;
        }

        /* Status Color Dots */
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 2px;
            flex-shrink: 0;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .status-dot.present {
            background: #1e3a8a;
        }

        .status-dot.absent {
            background: #475569;
        }

        .status-dot.on_leave {
            background: #2563eb;
        }

        .status-dot.holiday {
            background: #3b82f6;
        }

        .status-dot.week_off {
            background: #2563eb;
        }

        .status-dot.checked_in_only {
            background: #2563eb;
        }
         .status-dot.halfday {
            background: #2563eb;
        }

        /* ==================== SHIFT TIME BADGE ==================== */
        .shift-badge {
            font-size: 9.5px;
            background: #f1f5f9;
            color: #475569;
            padding: 2px 7px;
            border-radius: 12px;
            display: inline-block;
            white-space: nowrap;
            font-weight: 500;
            border: 1px solid #e2e8f0;
        }

        /* ==================== WEEKEND HIGHLIGHT ==================== */
        .weekend-row td {
            background-color: #fafafa !important;
            color: #94a3b8;
        }

        .weekend-row:hover td {
            background-color: #f1f5f9 !important;
        }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            padding: 42px 17px;
            text-align: center;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            margin: 14px;
        }

        .empty-state i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 14px;
            opacity: 0.5;
        }

        .empty-state h4 {
            color: #0f172a;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 17px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            margin: 0;
            gap: 4px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            border: 1px solid #e2e8f0;
            border-radius: 8px !important;
            color: #475569;
            font-size: 10.5px;
            padding: 6px 10px;
            transition: all 0.2s;
            background: white;
            font-weight: 500;
        }

        .page-link:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
        }

        .page-item.active .page-link {
            background: #1e3a8a;
            border-color: #1e3a8a;
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .page-item.disabled .page-link {
            background: #f8fafc;
            color: #94a3b8;
            pointer-events: none;
            opacity: 0.6;
        }

        /* ==================== CARD ==================== */
        .card {
            border: 1px solid #eef2f6;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 13px 17px;
        }

        .card-title {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }

        .card-body {
            padding: 0;
        }

        .card-footer {
            background: white;
            border-top: 1px solid #f1f5f9;
            padding: 10px 17px;
        }

        /* ==================== TOOLTIP ==================== */
        .text-truncate {
            max-width: 120px;
            display: inline-block;
        }

        .badge-info-custom {
            background: #e3edfe !important;
            color: #1e3a8a !important;
            font-weight: 600 !important;
            padding: 6px 14px !important;
            border-radius: 20px !important;
            border: 1px solid #93c5fd !important;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }

        @media (max-width: 992px) {
            .filter-row {
                gap: 10px;
            }

            .filter-item {
                flex: 1 1 calc(50% - 10px);
                min-width: 120px;
            }

            .filter-item.search-filter {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-wrapper {
                padding: 11px;
            }

            .filter-row {
                flex-direction: column;
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

            .table-responsive {
                max-height: 500px;
            }

            .table th,
            .table td {
                padding: 6px 7px;
                font-size: 9.5px;
                white-space: nowrap;
            }

            .employee-info {
                min-width: 120px;
            }

            .location-text {
                max-width: 80px;
            }

            .shift-badge {
                font-size: 9px;
                padding: 2px 4px;
            }
        }

        /* Scrollbar styling for dropdown and table */
        .custom-employee-dropdown .dropdown-menu::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-employee-dropdown .dropdown-menu::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }

        .custom-employee-dropdown .dropdown-menu::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 8px;
        }

        .custom-employee-dropdown .dropdown-menu::-webkit-scrollbar-thumb:hover,
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Attendance Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('report.attendance.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Attendance Report</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="dropdown">
                    <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10">
                        <i class="feather-download"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="{{ route('report.attendance.day.export', request()->query()) }}" class="dropdown-item"
                            target="_blank">
                            <i class="bi bi-filetype-csv me-3"></i>
                            <span>Export CSV</span>
                        </a>
                        <a href="#" class="dropdown-item" onclick="exportToExcel()">
                            <i class="bi bi-file-earmark-excel me-3"></i>
                            <span>Export Excel</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Stats Cards -->
        {{-- <div class="stats-grid">
        <div class="stats-card total-card">
            <div class="stats-icon-wrapper">
                <i class="feather-users"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['totalEmployees'] ?? 0 }}</div>
                <div class="stats-label">Total Employees</div>
            </div>
        </div>

        <div class="stats-card present-card">
            <div class="stats-icon-wrapper">
                <i class="feather-check-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['presentCount'] ?? 0 }}</div>
                <div class="stats-label">Present</div>
            </div>
        </div>

        <div class="stats-card absent-card">
            <div class="stats-icon-wrapper">
                <i class="feather-x-circle"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['absentCount'] ?? 0 }}</div>
                <div class="stats-label">Absent</div>
            </div>
        </div>

        <div class="stats-card leave-card">
            <div class="stats-icon-wrapper">
                <i class="feather-calendar"></i>
            </div>
            <div class="stats-content">
                <div class="stats-amount-main">{{ $stats['leaveCount'] ?? 0 }}</div>
                <div class="stats-label">On Leave</div>
            </div>
        </div>
    </div> --}}

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Report
                    @php
                        $activeFilterCount = collect(request()->only(['date', 'user_id', 'status', 'search']))
                            ->filter()
                            ->count();
                    @endphp
                    @if ($activeFilterCount > 0)
                        <span>{{ $activeFilterCount }} active</span>
                    @endif
                </div>
                @if (request()->hasAny(['date', 'user_id', 'status', 'search']))
                    <a href="{{ route('report.attendance.day.index') }}" class="clear-all-link">
                        <i class="feather-x"></i>
                        Clear All
                    </a>
                @endif
            </div>

            <form action="{{ route('report.attendance.day.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <!-- date Picker -->
                    <div class="filter-item date-picker">
                        <input type="date" name="date" class="form-control"
                            value="{{ request('date', now()->format('Y-m-d')) }}" onchange="this.form.submit()">
                    </div>

                    <!-- Employee Dropdown -->
                    <div class="filter-item" style="min-width: 220px;">
                        <div class="custom-employee-dropdown">
                            <button class="btn btn-light d-flex align-items-center justify-content-between" type="button"
                                id="employeeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                    @if (request('user_id') &&
                                            isset($employees) &&
                                            ($selectedEmployee = $employees->firstWhere('id', (int) request('user_id'))))
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

                            <ul class="dropdown-menu p-2" aria-labelledby="employeeDropdown">
                                <li>
                                    <a class="dropdown-item rounded {{ !request('user_id') ? 'active' : '' }}"
                                        href="{{ route('report.attendance.day.index', array_merge(request()->except(['user_id', 'page']))) }}">
                                        <span>All Employees</span>
                                    </a>
                                </li>
                                @foreach ($employees ?? [] as $employee)
                                    @php
                                        $initials = strtoupper(substr($employee->name, 0, 2));
                                    @endphp
                                    <li>
                                        <a class="dropdown-item rounded d-flex align-items-center gap-2 {{ request('user_id') == $employee->id ? 'active' : '' }}"
                                            href="{{ route('report.attendance.day.index', array_merge(request()->except(['page']), ['user_id' => $employee->id])) }}">
                                            <span class="employee-initials">{{ $initials }}</span>
                                            <div class="d-flex flex-column">
                                                <span>{{ $employee->name }} (<small
                                                        class="text-muted">{{ $employee->employee_id }})</small></span>
                                                <small class="text-muted">{{ $employee->email }}</small>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="filter-item">
                        <select class="filter-select status-filter" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>✅ Present
                            </option>
                            <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>❌ Absent</option>
                            <option value="on_leave" {{ request('status') == 'on_leave' ? 'selected' : '' }}>🏖️ On Leave
                            </option>
                            <option value="holiday" {{ request('status') == 'holiday' ? 'selected' : '' }}>🎉 Holiday
                            </option>
                            <option value="week_off" {{ request('status') == 'week_off' ? 'selected' : '' }}>📅 Week Off
                            </option>
                             <option value="halfday" {{ request('status') == 'halfday' ? 'selected' : '' }}>📅 Halfday
                            </option>
                            <option value="checked_in_only" {{ request('status') == 'checked_in_only' ? 'selected' : '' }}>
                                ⏳ Checked In Only</option>
                        </select>
                    </div>

                    <!-- Search Filter -->
                    {{-- <div class="filter-item search-filter">
                    <div class="search-wrapper">
                        <i class="feather-search"></i>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search employee by name or ID..." 
                               value="{{ request('search') }}"
                               onkeyup="if(event.keyCode==13) this.form.submit();">
                    </div>
                </div> --}}

                    <div class="filter-item" style="min-width: auto;">
                        <a href="{{ route('report.attendance.day.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i>
                            Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Active Filter Tags -->
            @if (request()->hasAny(['date', 'user_id', 'status', 'search']))
                <div class="active-filters">
                    <span class="active-filters-label">Active:</span>

                    @if (request('date'))
                        <span class="filter-tag">
                            <i class="feather-calendar"></i>
                            {{ \Carbon\Carbon::createFromFormat('Y-m-d', request('date'))->format('d M Y') }}
                            <a href="{{ route('report.attendance.day.index', array_merge(request()->except(['date', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('user_id') && ($selectedEmployee = $employees->firstWhere('id', (int) request('user_id'))))
                        <span class="filter-tag">
                            <i class="feather-user"></i>
                            {{ $selectedEmployee->name }}
                            <a href="{{ route('report.attendance.day.index', array_merge(request()->except(['user_id', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('status'))
                        <span class="filter-tag">
                            <i class="feather-flag"></i>
                            Status: {{ ucfirst(str_replace('_', ' ', request('status'))) }}
                            <a href="{{ route('report.attendance.day.index', array_merge(request()->except(['status', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    @if (request('search'))
                        <span class="filter-tag">
                            <i class="feather-search"></i>
                            "{{ request('search') }}"
                            <a href="{{ route('report.attendance.day.index', array_merge(request()->except(['search', 'page']))) }}"
                                class="remove-tag">
                                <i class="feather-x"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('report.attendance.day.index') }}" class="filter-tag clear-all">
                        <i class="feather-refresh-cw"></i>
                        Clear All
                    </a>
                </div>
            @endif
        </div>

        <!-- Attendance Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="feather-calendar me-2" style="color: #1e3a8a;"></i>
                            Attendance Report -
                            <span style="color: #1e3a8a;">
                                {{ \Carbon\Carbon::createFromFormat('Y-m-d', request('date', now()->format('Y-m-d')))->format('d F Y') }}
                            </span>
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge badge-info-custom">
                                <i class="feather-list me-1"></i>Total: {{ $reportData->total() ?? $reportData->count() }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="attendanceReportTable">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Status</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Total Hours</th>
                                        <th>Shift</th>
                                        <th>Clock In Location</th>
                                        <th>Clock Out Location</th>
                                        <th>Late</th>
                                        <th>Early Exit</th>
                                        <th>OT</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $weekends = ['Saturday', 'Sunday'];
                                    @endphp

                                    @forelse ($reportData as $index => $record)
                                        @php
                                            $isWeekend = in_array(
                                                \Carbon\Carbon::parse($record['date'])->format('l'),
                                                $weekends,
                                            );
                                            $statusClass = match ($record['status']) {
                                                'present' => 'badge-present',
                                                'absent' => 'badge-absent',
                                                'on_leave' => 'badge-on_leave',
                                                'half day', 'halfday' => 'badge-halfday',
                                                'holiday' => 'badge-holiday',
                                                'week_off' => 'badge-week_off',
                                                'checked_in_only' => 'badge-checked_in_only',
                                                default => 'badge-secondary',
                                            };
                                            $statusLabel = match ($record['status']) {
                                                'present' => 'Present',
                                                'absent' => 'Absent',
                                                'on_leave' => 'On Leave',
                                                'half day', 'halfday' => 'Half Day',
                                                'holiday' => 'Holiday',
                                                'week_off' => 'Week Off',
                                                'checked_in_only' => 'Checked In Only',
                                                default => ucfirst($record['status']),
                                            };
                                        @endphp
                                        <tr
                                            class="{{ $isWeekend && $record['status'] == 'absent' ? 'weekend-row' : '' }}">
                                            <td>{{ ($reportData->currentPage() - 1) * $reportData->perPage() + $loop->iteration }}
                                            </td>
                                            <td>
                                                <a
                                                    href="{{ route('attendance.sessions', ['user_id' => encrypt($record['user_id']), 'date' => $record['date']]) }}">
                                                    <div class="employee-info">
                                                        <div class="employee-avatar">
                                                            {{ strtoupper(substr($record['name'], 0, 2)) }}
                                                        </div>
                                                        <div class="employee-details">
                                                            <div class="employee-name-text">{{ $record['name'] }}</div>
                                                            <div class="employee-email-text">
                                                                {{ $record['employee_id'] ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($record['date'])->format('d M Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($record['date'])->format('D') }}</td>
                                            <td>
                                                <span class="badge {{ $statusClass }}">
                                                    <span class="status-dot {{ $record['status'] }}"></span>
                                                    {{ $statusLabel }}
                                                    @if ($record['status'] == 'on_leave' && $record['leave_type'])
                                                        <small>({{ $record['leave_type'] }})</small>
                                                    @endif
                                                    @if ($record['status'] == 'holiday' && $record['holiday_name'])
                                                        <small>({{ $record['holiday_name'] }})</small>
                                                    @endif
                                                </span>
                                            </td>
                                            <td>
                                                @if ($record['clock_in'])
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($record['clock_in'])->format('d M Y h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['clock_out'])
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($record['clock_out'])->format('d M Y h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['total_hours'])
                                                    <span style="font-weight: 600; color: #1e3a8a;">
                                                        {{ $record['total_hours'] }} hrs
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['shift_start_time'] || $record['shift_end_time'])
                                                    <span class="shift-badge">
                                                        {{ $record['shift_start_time'] ? \Carbon\Carbon::parse($record['shift_start_time'])->format('h:i A') : '--' }}
                                                        -
                                                        {{ $record['shift_end_time'] ? \Carbon\Carbon::parse($record['shift_end_time'])->format('h:i A') : '--' }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['clock_in_location'])
                                                    <span class="location-text"
                                                        title="{{ $record['clock_in_location'] }}">
                                                        <i class="feather-map-pin text-primary"
                                                            style="font-size: 10px;"></i>
                                                        {{ Str::limit($record['clock_in_location'], 28) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['clock_out_location'])
                                                    <span class="location-text"
                                                        title="{{ $record['clock_out_location'] }}">
                                                        <i class="feather-map-pin text-danger"
                                                            style="font-size: 10px;"></i>
                                                        {{ Str::limit($record['clock_out_location'], 28) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['late_minutes'] > 0)
                                                    <span class="text-danger"
                                                        style="font-weight: 600;">{{ $record['late_minutes'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['early_exit_minutes'] > 0)
                                                    <span class="text-warning"
                                                        style="font-weight: 600;">{{ $record['early_exit_minutes'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['overtime_minutes'] > 0)
                                                    <span class="text-success"
                                                        style="font-weight: 600;">{{ $record['overtime_minutes'] }}
                                                        Min</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($record['remarks'])
                                                    <span class="text-truncate" title="{{ $record['remarks'] }}"
                                                        style="cursor: help;">
                                                        {{ Str::limit($record['remarks'], 20) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="15" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-calendar"></i>
                                                    <h4>No Attendance Records Found</h4>
                                                    <p class="text-muted">No attendance data available for the selected
                                                        date. Try adjusting your filters.</p>

                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if (method_exists($reportData, 'links') && $reportData->hasPages())
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="text-muted small">
                                    Showing <strong>{{ $reportData->firstItem() }}</strong> to
                                    <strong>{{ $reportData->lastItem() }}</strong>
                                    of <strong>{{ $reportData->total() }}</strong> entries
                                </div>
                                <div class="remove-internal-para">
                                    {{ $reportData->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Auto-submit on date change
        $('input[name="date"]').on('change', function() {
            $('#filterForm').submit();
        });

        // Auto-submit on status change
        $('select[name="status"]').on('change', function() {
            $('#filterForm').submit();
        });

        // Search with Enter key
        $('input[name="search"]').on('keyup', function(e) {
            if (e.key === 'Enter') {
                $('#filterForm').submit();
            }
        });

        // Debounced search (optional - auto-submit after typing stops)
        let searchTimeout;
        $('input[name="search"]').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                $('#filterForm').submit();
            }, 500);
        });

        // Export to CSV
        function exportToCSV() {
            let table = document.getElementById('attendanceReportTable');
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
                let headerText = headerCells[i].innerText.trim();
                if (headerText) {
                    headers.push('"' + headerText.replace(/"/g, '""') + '"');
                }
            }
            csv.push(headers.join(','));

            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                let rowData = [];
                let cols = rows[i].querySelectorAll('td');

                for (let j = 0; j < cols.length; j++) {
                    let cellText = cols[j].innerText.replace(/"/g, '""').trim();
                    rowData.push('"' + cellText + '"');
                }

                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            }

            let csvFile = new Blob(['\uFEFF' + csv.join('\n')], {
                type: 'text/csv;charset=utf-8'
            });
            let downloadLink = document.createElement('a');
            downloadLink.download = 'attendance_report_' + new Date().getTime() + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();

            toastr.success('CSV exported successfully');
        }

        // Export to Excel
        function exportToExcel() {
            let table = document.getElementById('attendanceReportTable');
            if (!table) {
                toastr.error('Table not found');
                return;
            }

            // Get current filters
            let params = new URLSearchParams(window.location.search);
            let date = params.get('date') || '{{ now()->format('Y-m-d') }}';
            let userId = params.get('user_id') || '';
            let status = params.get('status') || '';
            let search = params.get('search') || '';

            // Redirect to server-side export
            let url = '{{ route('report.attendance.day.export') }}?date=' + date;
            if (userId) url += '&user_id=' + userId;
            if (status) url += '&status=' + status;
            if (search) url += '&search=' + encodeURIComponent(search);
            url += '&format=excel';

            window.open(url, '_blank');
            toastr.success('Excel export started');
        }
    </script>
@endsection
