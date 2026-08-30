@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== MODERN STATS CARDS ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
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
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }

        .stats-card.total-card::before {
            background: linear-gradient(90deg, #4f46e5, #818cf8);
        }

        .stats-card.present-card::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stats-card.absent-card::before {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .stats-card.leave-card::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .stats-card.holiday-card::before {
            background: linear-gradient(90deg, #8b5cf6, #a78bfa);
        }

        .stats-card.weekoff-card::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .stats-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
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
            color: #4f46e5;
            font-size: 22px;
        }

        .present-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.05));
        }

        .present-card .stats-icon-wrapper i {
            color: #10b981;
            font-size: 22px;
        }

        .absent-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.12), rgba(239, 68, 68, 0.05));
        }

        .absent-card .stats-icon-wrapper i {
            color: #ef4444;
            font-size: 22px;
        }

        .leave-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.05));
        }

        .leave-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 22px;
        }

        .holiday-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12), rgba(139, 92, 246, 0.05));
        }

        .holiday-card .stats-icon-wrapper i {
            color: #8b5cf6;
            font-size: 22px;
        }

        .weekoff-card .stats-icon-wrapper {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.05));
        }

        .weekoff-card .stats-icon-wrapper i {
            color: #f59e0b;
            font-size: 22px;
        }

        .stats-content {
            flex: 1;
            min-width: 0;
        }

        .stats-amount-main {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 1px;
            letter-spacing: -0.5px;
        }

        .stats-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
        }

        /* ==================== FILTER SECTION ==================== */
        .filter-wrapper {
            background: white;
            border-radius: 12px;
            border: 1px solid #eef2f6;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .filter-title i {
            color: #4f46e5;
            font-size: 16px;
            background: #eef2ff;
            padding: 5px;
            border-radius: 8px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .filter-item {
            flex: 0 0 auto;
        }

        .filter-item.date-picker {
            min-width: 180px;
        }

        .filter-item.date-picker input {
            height: 38px;
            padding: 6px 12px;
            font-size: 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
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

        .filter-item.status-filter {
            min-width: 200px;
        }

        .filter-item.status-filter select {
            width: 100%;
            height: 38px;
            padding: 6px 32px 6px 12px;
            font-size: 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 10px center;
            background-size: 14px;
            appearance: none;
            cursor: pointer;
            transition: all 0.3s;
            color: #0f172a;
            font-weight: 500;
        }

        .filter-item.status-filter select:hover {
            background-color: white;
            border-color: #cbd5e1;
        }

        .filter-item.status-filter select:focus {
            border-color: #4f46e5;
            outline: none;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
        }

        .reset-btn {
            height: 38px;
            padding: 0 16px;
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .reset-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
            transform: translateY(-2px);
        }

        .reset-btn i {
            font-size: 14px;
        }

        /* ==================== TABLE STYLES ==================== */
        .card {
            border: 1px solid #eef2f6;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #f1f5f9;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 15px;
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
            padding: 12px 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            padding: 10px 12px;
            white-space: nowrap;
            border-bottom: 2px solid #e2e8f0;
        }

        .table tbody td {
            vertical-align: middle;
            font-size: 12px;
            padding: 10px 12px;
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
            border-radius: 0 0 12px 12px;
        }

        /* Employee Info */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 140px;
        }

        .employee-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .employee-details {
            line-height: 1.3;
            min-width: 0;
        }

        .employee-name-text {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .employee-email-text {
            font-size: 11px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            font-weight: 600;
            font-size: 10px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .badge-present {
            background: #d1fae5 !important;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .badge-absent {
            background: #fee2e2 !important;
            color: #991b1b;
            border-color: #fecaca;
        }

        .badge-on_leave {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-holiday {
            background: #dbeafe !important;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .badge-week_off {
            background: #ede9fe !important;
            color: #5b21b6;
            border-color: #ddd6fe;
        }

        .badge-checked_in_only {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-halfday {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-first_half {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .badge-second_half {
            background: #fef3c7 !important;
            color: #92400e;
            border-color: #fde68a;
        }

        .status-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.present {
            background: #10b981;
        }

        .status-dot.absent {
            background: #ef4444;
        }

        .status-dot.on_leave {
            background: #f59e0b;
        }

        .status-dot.holiday {
            background: #3b82f6;
        }

        .status-dot.week_off {
            background: #8b5cf6;
        }

        .status-dot.checked_in_only {
            background: #f59e0b;
        }

        .status-dot.halfday {
            background: #f59e0b;
        }

        .status-dot.first_half {
            background: #f59e0b;
        }

        .status-dot.second_half {
            background: #f59e0b;
        }

        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .action-btn.view-btn {
            background: #eef2ff;
            color: #4f46e5;
            border-color: #c7d2fe;
        }

        .action-btn.view-btn:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .action-btn.mark-btn {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .action-btn.mark-btn:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .action-btn.mark-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ==================== MODAL STYLES ==================== */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 18px 24px;
            background: #fafbfc;
            border-radius: 16px 16px 0 0;
        }

        .modal-header .modal-title {
            font-weight: 600;
            font-size: 18px;
            color: #0f172a;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 16px 24px;
            background: #fafbfc;
            border-radius: 0 0 16px 16px;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-control {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 13px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.08);
        }

        .invalid-feedback {
            font-size: 12px;
            color: #ef4444;
            margin-top: 4px;
        }

        /* Employee Info Card */
        .employee-info-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .employee-info-card .avatar-lg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-weight: 700;
            font-size: 18px;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .employee-info-card .info h6 {
            margin-bottom: 2px;
            font-weight: 600;
            color: #0f172a;
            font-size: 15px;
        }

        .employee-info-card .info p {
            margin-bottom: 0;
            font-size: 13px;
            color: #64748b;
        }

        /* Shift Info Card */
        .shift-info-card {
            background: #f0fdf4;
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid #bbf7d0;
        }

        .shift-info-card .shift-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
        }

        .shift-info-card .shift-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 13px;
        }

        .shift-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .shift-badge.overnight {
            background: #fef3c7;
            color: #92400e;
        }

        .shift-badge.regular {
            background: #dbeafe;
            color: #1e40af;
        }

        .shift-badge.no-shift {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-primary-custom {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
        }

        .btn-primary-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-secondary-custom {
            background: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-secondary-custom:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .admin-badge {
            background: #dbeafe;
            color: #1e40af;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-info-custom {
            background: #eef2ff !important;
            color: #4f46e5 !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            border-radius: 16px !important;
            border: 1px solid #c7d2fe !important;
            font-size: 11px !important;
        }

        /* Empty State */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 56px;
            color: #d1d5db;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state h4 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 0;
        }

        /* ==================== TOAST CUSTOMIZATIONS ==================== */
        #toast-container > div {
            opacity: 1 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            border-radius: 10px !important;
            padding: 14px 16px 14px 50px !important;
            background-position: 16px center !important;
            background-repeat: no-repeat !important;
            background-size: 20px !important;
            width: auto !important;
            max-width: 350px !important;
        }

        #toast-container > div:hover {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2) !important;
        }

        .toast-success {
            background-color: #10b981 !important;
        }

        .toast-error {
            background-color: #ef4444 !important;
        }

        .toast-warning {
            background-color: #f59e0b !important;
        }

        .toast-info {
            background-color: #4f46e5 !important;
        }

        /* Half Day Status Select Custom Styles */
        .attendance-status-select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }

        .attendance-status-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
            outline: none;
        }

        /* ==================== PAGINATION STYLES ==================== */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 12px 0;
        }

        .pagination-info {
            font-size: 13px;
            color: #64748b;
        }

        .pagination-info strong {
            color: #0f172a;
        }

        .pagination {
            display: flex;
            gap: 4px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .pagination .page-item {
            display: inline-block;
        }

        .pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination .page-link:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .pagination .active .page-link {
            background: #4f46e5;
            border-color: #4f46e5;
            color: white;
        }

        .pagination .active .page-link:hover {
            background: #4338ca;
            border-color: #4338ca;
        }

        .pagination .disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Responsive Pagination */
        @media (max-width: 768px) {
            .pagination-wrapper {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .pagination {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .pagination .page-link {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
                padding: 0 8px;
            }
        }

        @media (max-width: 480px) {
            .pagination .page-item.hide-mobile {
                display: none;
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .filter-wrapper {
                padding: 12px 14px;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-item {
                width: 100%;
            }

            .filter-item.date-picker,
            .filter-item.status-filter {
                min-width: auto;
            }

            .reset-btn {
                width: 100%;
                justify-content: center;
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .table th,
            .table td {
                padding: 6px 8px;
                font-size: 11px;
                white-space: nowrap;
            }

            .employee-info {
                min-width: 100px;
            }

            .employee-avatar {
                width: 28px;
                height: 28px;
                font-size: 10px;
            }

            .employee-name-text {
                font-size: 11px;
            }

            .employee-email-text {
                font-size: 10px;
            }

            .action-btn {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .modal-dialog {
                margin: 10px;
            }

            .employee-info-card {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stats-card {
                padding: 12px 14px;
                gap: 10px;
            }

            .stats-icon-wrapper {
                width: 36px;
                height: 36px;
            }

            .stats-icon-wrapper i {
                font-size: 18px !important;
            }

            .stats-amount-main {
                font-size: 18px;
            }

            .stats-label {
                font-size: 9px;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item active">Team Attendance</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card total-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-users"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['total'] ?? 0 }}</div>
                    <div class="stats-label">Total Team</div>
                </div>
            </div>
            <div class="stats-card present-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-check-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['present'] ?? 0 }}</div>
                    <div class="stats-label">Present</div>
                </div>
            </div>
            <div class="stats-card absent-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-x-circle"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['absent'] ?? 0 }}</div>
                    <div class="stats-label">Absent</div>
                </div>
            </div>
            <div class="stats-card leave-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-calendar"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['on_leave'] ?? 0 }}</div>
                    <div class="stats-label">On Leave</div>
                </div>
            </div>
            <div class="stats-card holiday-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-home"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['holiday'] ?? 0 }}</div>
                    <div class="stats-label">Holiday</div>
                </div>
            </div>
            <div class="stats-card weekoff-card">
                <div class="stats-icon-wrapper">
                    <i class="feather-sun"></i>
                </div>
                <div class="stats-content">
                    <div class="stats-amount-main">{{ $statusCount['weekoff'] ?? 0 }}</div>
                    <div class="stats-label">Week Off</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-wrapper">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="feather-filter"></i>
                    Filter Team Members
                </div>
                @if (request()->hasAny(['date', 'status']))
                    <a href="{{ route('team.index') }}" class="reset-btn"
                        style="height: auto; padding: 4px 12px; font-size: 12px;">
                        <i class="feather-x"></i> Clear Filters
                    </a>
                @endif
            </div>

            <form action="{{ route('team.index') }}" method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-item date-picker">
                        <input type="date" name="date" value="{{ request('date', date('Y-m-d')) }}"
                            max="{{ date('Y-m-d') }}" onchange="this.form.submit()">
                    </div>

                    <div class="filter-item status-filter">
                        <select name="status" onchange="this.form.submit()">
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

                    <div class="filter-item">
                        <a href="{{ route('team.index') }}" class="reset-btn">
                            <i class="feather-refresh-cw"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Team Members Table -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card stretch stretch-full">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="feather-users me-2" style="color: #4f46e5;"></i>
                            Team Members -
                            <span style="color: #4f46e5;">
                                {{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d F Y') }}
                            </span>
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge badge-info-custom">
                                <i class="feather-list me-1"></i>Total: {{ $teamData->total() ?? count($teamData) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table" id="teamTable">
                                <thead>
                                    <tr>
                                        <th width="40">#</th>
                                        <th>Employee</th>
                                        <th>Designation</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Total Hours</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($teamData as $index => $member)
                                        @php
                                            $status = strtolower($member->status ?? 'absent');
                                            $statusClass = match ($status) {
                                                'present' => 'badge-present',
                                                'absent' => 'badge-absent',
                                                'on_leave',
                                                'first half leave',
                                                'second half leave',
                                                'full day leave' => 'badge-on_leave',
                                                'halfday' => 'badge-halfday',
                                                'first_half' => 'badge-first_half',
                                                'second_half' => 'badge-second_half',
                                                'holiday' => 'badge-holiday',
                                                'week_off', 'week off' => 'badge-week_off',
                                                'checked in only' => 'badge-checked_in_only',
                                                default => 'badge-secondary',
                                            };
                                            $statusLabel = match ($status) {
                                                'present' => 'Present',
                                                'absent' => 'Absent',
                                                'on_leave' => 'On Leave',
                                                'first half leave' => 'First Half Leave',
                                                'second half leave' => 'Second Half Leave',
                                                'full day leave' => 'Full Day Leave',
                                                'halfday' => 'Half Day',
                                                'first_half' => 'First Half',
                                                'second_half' => 'Second Half',
                                                'holiday' => 'Holiday',
                                                'week_off', 'week off' => 'Week Off',
                                                'checked in only' => 'Checked In Only',
                                                default => ucfirst($status),
                                            };
                                            $statusDot = match ($status) {
                                                'present' => 'present',
                                                'absent' => 'absent',
                                                'on_leave',
                                                'first half leave',
                                                'second half leave',
                                                'full day leave' => 'on_leave',
                                                'halfday' => 'halfday',
                                                'first_half' => 'first_half',
                                                'second_half' => 'second_half',
                                                'holiday' => 'holiday',
                                                'week_off', 'week off' => 'week_off',
                                                'checked in only' => 'checked_in_only',
                                                default => 'absent',
                                            };
                                            $isAbsent = $status == 'absent';
                                            $isAdmin = auth()->user()->role == 'admin' || auth()->user()->role == 'hr';
                                            
                                            // Calculate serial number with pagination
                                            $serialNumber = ($teamData->currentPage() - 1) * $teamData->perPage() + $loop->index + 1;
                                        @endphp
                                        <tr>
                                            <td>{{ $serialNumber }}</td>
                                            <td>
                                                <div class="employee-info">
                                                    <div class="employee-avatar">
                                                        {{ strtoupper(substr($member->name ?? 'N/A', 0, 2)) }}
                                                    </div>
                                                    <div class="employee-details">
                                                        <div class="employee-name-text">{{ $member->name ?? 'N/A' }} <small>( {{ $member->employee_id ?? 'N/A' }} )</small></div>
                                                        <div class="employee-email-text">
                                                            {{ $member->email ?? 'N/A' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $member->designation ?? 'N/A' }}</td>
                                            <td>{{ $member->department ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge {{ $statusClass }}">
                                                    <span class="status-dot {{ $statusDot }}"></span>
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($member->punch_in)
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($member->punch_in)->format('h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($member->punch_out)
                                                    <span style="font-weight: 500; color: #0f172a;">
                                                        {{ \Carbon\Carbon::parse($member->punch_out)->format('h:i A') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($member->punch_in && $member->punch_out)
                                                    @php
                                                        $clockIn = \Carbon\Carbon::parse($member->punch_in);
                                                        $clockOut = \Carbon\Carbon::parse($member->punch_out);
                                                        $diff = $clockIn->diff($clockOut);
                                                        $hours = $diff->h + $diff->i / 60;
                                                    @endphp
                                                    <span style="font-weight: 600; color: #4f46e5;">
                                                        {{ $member->total_hours }} hrs
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <!-- View Button -->
                                                    <a href="{{ route('team.member-detail', ['id' => encrypt($member->id)]) }}"
                                                        class="action-btn view-btn" title="View Member">
                                                        <i class="feather-eye"></i>
                                                    </a>

                                                    <!-- Mark Attendance Button - Only for Admin/HR -->
                                                    @if ($isAdmin)
                                                        <button type="button" class="action-btn mark-btn"
                                                            onclick="openMarkAttendanceModal({{ $member->id }}, '{{ $member->name }}', '{{ $member->employee_id }}', '{{ $member->designation ?? 'N/A' }}')"
                                                            title="Mark Attendance">
                                                            <i class="feather-edit-2"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <div class="empty-state">
                                                    <i class="feather-users"></i>
                                                    <h4>No Team Members Found</h4>
                                                    <p>No team members available for the selected date.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @if ($teamData->count() > 0)
                        <div class="card-footer">
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    Showing <strong>{{ $teamData->firstItem() }}</strong> to <strong>{{ $teamData->lastItem() }}</strong>
                                    of <strong>{{ $teamData->total() }}</strong> members
                                </div>
                                <div>
                                    {{ $teamData->appends(request()->query())->links('pagination::bootstrap-4') }}
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
    <!-- Mark Attendance Modal -->
    <div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather-edit-2 me-2" style="color: #4f46e5;"></i>
                        Mark Attendance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Employee Info -->
                    <div class="employee-info-card mb-3">
                        <div class="avatar-lg" id="modalEmployeeAvatar">
                            {{-- Dynamic --}}
                        </div>
                        <div class="info">
                            <h6 id="modalEmployeeName">Employee Name</h6>
                            <p>
                                <span id="modalEmployeeId">ID</span> •
                                <span id="modalEmployeeDesignation">Designation</span>
                            </p>
                            <p class="text-muted" style="font-size: 12px;">
                                <i class="feather-calendar me-1"></i>
                                Date: {{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}
                            </p>
                        </div>
                    </div>

                    <!-- Shift Info -->
                    <div class="shift-info-card mb-3" id="shiftInfoCard">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div>
                                <span class="shift-label">Shift:</span>
                                <span class="shift-value" id="modalShiftName">Loading...</span>
                            </div>
                            <div>
                                <span class="shift-label">Start:</span>
                                <span class="shift-value" id="modalShiftStart">--:--</span>
                            </div>
                            <div>
                                <span class="shift-label">End:</span>
                                <span class="shift-value" id="modalShiftEnd">--:--</span>
                            </div>
                            <div>
                                <span class="shift-badge regular" id="modalShiftType">☀️ Regular Shift</span>
                            </div>
                        </div>
                        <div class="mt-2 small text-muted" id="shiftNote" style="display: none;">
                            <i class="feather-info me-1"></i>
                            <span id="shiftNoteText"></span>
                        </div>
                    </div>

                    <form id="markAttendanceForm" method="POST">
                        @csrf
                        <input type="hidden" name="user_id" id="markUserId">
                        <input type="hidden" name="date" id="attendanceDate" value="{{ request('date', date('Y-m-d')) }}">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="feather-clock me-1" style="color: #4f46e5;"></i>
                                    Clock In Time <span class="required">*</span>
                                </label>
                                <input type="time" class="form-control" name="clock_in" id="clockInTime" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="feather-clock me-1" style="color: #4f46e5;"></i>
                                    Clock Out Time <span class="required">*</span>
                                </label>
                                <input type="time" class="form-control" name="clock_out" id="clockOutTime" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>

                        <!-- Attendance Status Selection - Half Day Support -->
                        <div class="mt-3">
                            <label class="form-label">
                                <i class="feather-check-circle me-1" style="color: #4f46e5;"></i>
                                Attendance Status <span class="required">*</span>
                            </label>
                            <select class="attendance-status-select" name="attendance_status" id="attendanceStatus">
                                <option value="present">✅ Present (Full Day)</option>
                                <!--<option value="halfday">🌓 Half Day</option>-->
                                <!--<option value="first_half">🌅 First Half (Morning)</option>-->
                                <!--<option value="second_half">🌇 Second Half (Afternoon)</option>-->
                                <!--<option value="on_leave">📅 On Leave</option>-->
                            </select>
                            <div class="invalid-feedback"></div>
                            <small class="text-muted" style="font-size: 11px; display: block; margin-top: 4px;">
                                <i class="feather-info me-1"></i>
                                Select the appropriate attendance status for this employee
                            </small>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">
                                <i class="feather-message-square me-1" style="color: #4f46e5;"></i>
                                Remarks
                            </label>
                            <textarea class="form-control" name="remarks" id="attendanceRemarks" rows="2"
                                placeholder="e.g., Admin marked attendance for {{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}"></textarea>
                        </div>

                        <div class="mt-2">
                            <div class="alert alert-info"
                                style="background: #eef2ff; border-color: #c7d2fe; color: #1e40af; padding: 8px 12px; font-size: 12px; border-radius: 8px;">
                                <i class="feather-shield me-1"></i>
                                Attendance will be marked by: <strong>{{ auth()->user()->name }}</strong>
                                ({{ auth()->user()->role }})
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">
                        <i class="feather-x me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn-primary-custom" onclick="submitMarkAttendance()">
                        <i class="feather-check me-1"></i> Mark Attendance
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Auto-submit on date change
            $('input[name="date"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Auto-submit on status change
            $('select[name="status"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Set default time values
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = hours + ':' + minutes;

            // When modal is shown
            $('#markAttendanceModal').on('shown.bs.modal', function() {
                if (!$('#clockInTime').val()) {
                    $('#clockInTime').val(currentTime);
                }
                if (!$('#clockOutTime').val()) {
                    const clockOut = new Date();
                    clockOut.setHours(clockOut.getHours() + 1);
                    const outHours = String(clockOut.getHours()).padStart(2, '0');
                    const outMinutes = String(clockOut.getMinutes()).padStart(2, '0');
                    $('#clockOutTime').val(outHours + ':' + outMinutes);
                }
            });

            // Toastr options
            toastr.options = {
                "closeButton": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "timeOut": "5000",
                "extendedTimeOut": "2000",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut",
                "tapToDismiss": true,
                "showDuration": 300,
                "hideDuration": 1000,
                "closeHtml": '<button>&times;</button>'
            };
        });

        /**
         * Open the Mark Attendance Modal with employee details
         */
        function openMarkAttendanceModal(userId, name, employeeId, designation) {
            // Set employee details
            document.getElementById('modalEmployeeAvatar').textContent = name.substring(0, 2).toUpperCase();
            document.getElementById('modalEmployeeName').textContent = name;
            document.getElementById('modalEmployeeId').textContent = employeeId || 'N/A';
            document.getElementById('modalEmployeeDesignation').textContent = designation || 'N/A';
            document.getElementById('markUserId').value = userId;

            // Reset shift info to loading state
            document.getElementById('modalShiftName').textContent = 'Loading...';
            document.getElementById('modalShiftStart').textContent = '--:--';
            document.getElementById('modalShiftEnd').textContent = '--:--';
            const shiftType = document.getElementById('modalShiftType');
            shiftType.textContent = '⏳ Loading...';
            shiftType.className = 'shift-badge regular';

            // Fetch shift details
            const date = document.querySelector('input[name="date"]').value || '{{ date('Y-m-d') }}';
            
            fetch('{{ route('team.get-user-shift') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ user_id: userId, date: date })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.shift) {
                    const shift = data.shift;
                    document.getElementById('modalShiftName').textContent = shift.shift_name || 'Not Assigned';
                    document.getElementById('modalShiftStart').textContent = shift.start_time || '--:--';
                    document.getElementById('modalShiftEnd').textContent = shift.end_time || '--:--';
                    
                    const shiftType = document.getElementById('modalShiftType');
                    if (shift.is_overnight) {
                        shiftType.textContent = '🌙 Overnight Shift';
                        shiftType.className = 'shift-badge overnight';
                        document.getElementById('shiftNote').style.display = 'block';
                        document.getElementById('shiftNoteText').textContent = 
                            'This is an overnight shift. Clock out time will be on the next day.';
                    } else {
                        shiftType.textContent = '☀️ Regular Shift';
                        shiftType.className = 'shift-badge regular';
                        document.getElementById('shiftNote').style.display = 'none';
                    }
                    
                    // Set default clock in/out based on shift
                    if (shift.start_time) {
                        document.getElementById('clockInTime').value = shift.start_time;
                    }
                    if (shift.end_time) {
                        document.getElementById('clockOutTime').value = shift.end_time;
                    }
                } else {
                    document.getElementById('modalShiftName').textContent = 'No Shift Assigned';
                    document.getElementById('modalShiftStart').textContent = '--:--';
                    document.getElementById('modalShiftEnd').textContent = '--:--';
                    const shiftType = document.getElementById('modalShiftType');
                    shiftType.textContent = '⚠️ No Shift';
                    shiftType.className = 'shift-badge no-shift';
                    document.getElementById('shiftNote').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching shift:', error);
                document.getElementById('modalShiftName').textContent = 'Error Loading Shift';
                document.getElementById('modalShiftStart').textContent = '--:--';
                document.getElementById('modalShiftEnd').textContent = '--:--';
                const shiftType = document.getElementById('modalShiftType');
                shiftType.textContent = '⚠️ Error';
                shiftType.className = 'shift-badge no-shift';
                document.getElementById('shiftNote').style.display = 'none';
            });

            // Set default remarks
            const dateFormatted = '{{ \Carbon\Carbon::parse(request('date', date('Y-m-d')))->format('d M Y') }}';
            const adminName = '{{ auth()->user()->name }}';
            document.getElementById('attendanceRemarks').value =
                `Admin (${adminName}) marked attendance for ${dateFormatted}`;

            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('markAttendanceModal'));
            modal.show();
        }

        /**
         * Submit the mark attendance form via AJAX
         */
        function submitMarkAttendance() {
            const form = document.getElementById('markAttendanceForm');
            const formData = new FormData(form);

            // Get time values and format them correctly (HH:MM without seconds)
            let clockIn = document.getElementById('clockInTime').value;
            let clockOut = document.getElementById('clockOutTime').value;
            let attendanceStatus = document.getElementById('attendanceStatus').value;

            // Validate clock in time
            if (!clockIn) {
                toastr.error('Please select Clock In time');
                return;
            }

            // Validate clock out time
            if (!clockOut) {
                toastr.error('Please select Clock Out time');
                return;
            }

            // Validate attendance status
            if (!attendanceStatus) {
                toastr.error('Please select attendance status');
                return;
            }

            // Ensure time format is HH:MM (remove seconds if present)
            clockIn = clockIn.substring(0, 5);
            clockOut = clockOut.substring(0, 5);

            // Validate time format
            const timeRegex = /^([0-1][0-9]|2[0-3]):[0-5][0-9]$/;
            if (!timeRegex.test(clockIn)) {
                toastr.error('Invalid Clock In time format. Please use HH:MM (24-hour format)');
                return;
            }
            if (!timeRegex.test(clockOut)) {
                toastr.error('Invalid Clock Out time format. Please use HH:MM (24-hour format)');
                return;
            }

            // Check if it's an overnight shift
            const shiftType = document.getElementById('modalShiftType');
            const isOvernight = shiftType.textContent.includes('Overnight');
            
            // For regular shifts, validate that clock out is after clock in
            if (!isOvernight) {
                if (clockOut <= clockIn) {
                    toastr.error('Clock Out time must be after Clock In time for regular shifts');
                    return;
                }
            }

            // Update form data with formatted times and status
            formData.set('clock_in', clockIn);
            formData.set('clock_out', clockOut);
            formData.set('attendance_status', attendanceStatus);

            // Show loading state
            const submitBtn = document.querySelector('.btn-primary-custom');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="feather-loader me-1"></i> Saving...';
            submitBtn.disabled = true;

            // Get the selected date from the filter
            const selectedDate = document.querySelector('input[name="date"]').value || '{{ date('Y-m-d') }}';
            formData.set('date', selectedDate);

            // Log the data being sent for debugging
            console.log('Sending data:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            fetch('{{ route('team.mark-attendance') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;

                    if (data.success === true) {
                        let message = data.message || 'Attendance marked successfully!';
                        
                        // Add shift info to success message
                        if (data.shift_details && data.shift_details.is_overnight) {
                            message += ' (Overnight shift: ' + 
                                data.shift_details.clock_in + ' to ' + 
                                data.shift_details.clock_out + ')';
                        }
                        
                        // Add status info
                        const statusLabels = {
                            'present': 'Present (Full Day)',
                            'halfday': 'Half Day',
                            'first_half': 'First Half (Morning)',
                            'second_half': 'Second Half (Afternoon)',
                            'on_leave': 'On Leave'
                        };
                        const statusLabel = statusLabels[attendanceStatus] || attendanceStatus;
                        message += ' | Status: ' + statusLabel;
                        
                        toastr.success(message);
                        // Close modal
                        var modal = bootstrap.Modal.getInstance(document.getElementById('markAttendanceModal'));
                        if (modal) {
                            modal.hide();
                        }
                        // Reload page after 1.5 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        toastr.error(data.message || 'Failed to mark attendance');
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    toastr.error('An error occurred while marking attendance');
                    console.error('Error:', error);
                });
        }
    </script>
@endsection