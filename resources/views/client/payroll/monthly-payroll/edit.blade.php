@extends('client.layout.master')

@section('style')
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
            border: 1px solid var(--gray-200);
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
        }

        .section-title .badge-status {
            font-size: 10px;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 20px;
        }

        .form-group {
            margin-bottom: 8px;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 2px;
            display: block;
            letter-spacing: 0.2px;
        }

        .form-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 4px 8px;
            font-size: 12px;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            background: #ffffff;
            transition: all 0.2s;
            height: 30px;
        }

        .form-control:hover {
            border-color: var(--gray-400);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
        }

        .form-control[readonly] {
            background: var(--gray-50);
            cursor: not-allowed;
        }

        .form-control.prorated {
            background: #fefce8 !important;
            border-color: var(--warning) !important;
        }

        .form-control.hour-based {
            background: #eff6ff !important;
            border-color: var(--info) !important;
        }

        textarea.form-control {
            height: auto;
            min-height: 50px;
            padding: 4px 8px;
        }

        .invalid-feedback {
            color: var(--danger);
            font-size: 10px;
            margin-top: 2px;
        }

        .help-text {
            font-size: 10px;
            color: var(--gray-500);
            margin-top: 2px;
            display: block;
        }

        .employee-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--gray-50), #ffffff);
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            margin-bottom: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 2px;
        }

        .employee-info p {
            font-size: 11px;
            color: var(--gray-500);
            margin: 0;
        }

        .employee-info .employee-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 3px;
        }

        .employee-info .employee-meta span {
            font-size: 10px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .employee-info .employee-meta i {
            color: var(--primary);
            font-size: 11px;
        }

        .alert-info, .alert-warning, .alert-success {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .alert-info {
            background: #e6f3ff;
            border: 1px solid #b8daff;
            color: #004085;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-info i, .alert-warning i, .alert-success i {
            font-size: 14px;
        }

        .proration-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary-light);
            color: var(--primary);
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 500;
        }

        .proration-badge i {
            font-size: 11px;
        }

        .calculation-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 500;
        }

        .calculation-type-badge.day-based {
            background: #dbeafe;
            color: #1e40af;
        }

        .calculation-type-badge.hour-based {
            background: #d1fae5;
            color: #065f46;
        }

        .total-box {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
        }

        .total-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed var(--gray-200);
        }

        .total-item:last-child {
            border-bottom: none;
            border-top: 1px solid var(--gray-300);
            margin-top: 3px;
            padding-top: 8px;
        }

        .total-label {
            font-size: 11px;
            font-weight: 500;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .total-label i {
            font-size: 13px;
        }

        .total-value {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .total-value.net-salary {
            color: var(--primary);
            font-size: 16px;
        }

        .btn {
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-secondary {
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 3px 10px;
            font-size: 10px;
        }

        .component-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .component-header {
            padding: 6px 12px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .component-header .component-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .component-header .component-title i {
            color: var(--primary);
            font-size: 12px;
        }

        .component-body {
            padding: 10px 12px;
        }

        .component-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 6px;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            overflow: hidden;
            background: white;
            transition: all 0.2s;
        }

        .input-group:hover {
            border-color: var(--gray-400);
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .input-group-text {
            background: var(--gray-50);
            border-right: 1px solid var(--gray-200);
            padding: 3px 8px;
            color: var(--gray-600);
            font-size: 10px;
            font-weight: 500;
            min-width: 50px;
            white-space: nowrap;
        }

        .input-group .form-control {
            border: none;
            border-radius: 0;
            height: 26px;
            padding: 2px 6px;
            font-size: 11px;
        }

        .input-group .form-control:focus {
            box-shadow: none;
        }

        .employer-section {
            background: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 8px;
            padding: 10px 12px;
            margin-top: 4px;
        }

        .employer-section .section-title {
            border-bottom-color: #86efac;
            color: #166534;
        }

        .employer-section .input-group-text {
            background: #dcfce7;
            border-right-color: #86efac;
            color: #166534;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
        }

        .summary-item {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            padding: 10px 12px;
            transition: all 0.2s;
        }

        .summary-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .summary-item .summary-label {
            font-size: 9px;
            font-weight: 500;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }

        .summary-item .summary-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
        }

        .summary-item .summary-value small {
            font-size: 10px;
            font-weight: 400;
            color: var(--gray-500);
            margin-left: 2px;
        }

        .summary-item.highlight {
            background: linear-gradient(135deg, var(--primary-light), #ffffff);
            border-color: var(--primary);
        }

        .summary-item.highlight .summary-value {
            color: var(--primary);
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .form-section {
                padding: 10px 12px;
            }
            
            .employee-header {
                flex-direction: column;
                text-align: center;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .component-row {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 12px;
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .status-badge {
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.processed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .view-toggle {
            display: flex;
            gap: 3px;
            background: var(--gray-100);
            padding: 3px;
            border-radius: 6px;
        }

        .view-toggle .btn {
            padding: 4px 12px;
            font-size: 11px;
            border-radius: 4px;
            background: transparent;
            color: var(--gray-600);
        }

        .view-toggle .btn.active {
            background: white;
            color: var(--gray-800);
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .view-toggle .btn:hover:not(.active) {
            background: var(--gray-200);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        #inlineNotification {
            animation: slideIn 0.3s ease;
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 9999;
            padding: 10px 18px;
            border-radius: 6px;
            min-width: 250px;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 12px;
        }

        .ctc-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 6px;
            margin-top: 4px;
        }

        .ctc-breakdown .ctc-item {
            font-size: 10px;
            color: var(--gray-600);
        }

        .ctc-breakdown .ctc-item strong {
            color: var(--gray-800);
        }

        .ctc-breakdown .ctc-item .highlight {
            color: var(--primary);
            font-weight: 600;
        }

        .hour-based-section {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .hour-based-section .section-title {
            border-bottom-color: #93c5fd;
            color: #1e40af;
        }

        .hour-based-section .input-group-text {
            background: #dbeafe;
            border-right-color: #93c5fd;
            color: #1e40af;
        }

        .hour-based-info {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 8px 12px;
            background: #dbeafe;
            border-radius: 6px;
            font-size: 11px;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .hour-based-info strong {
            font-weight: 600;
        }

        .hour-based-info .info-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .hour-based-info .info-item i {
            font-size: 12px;
        }

        .page-header-title h5 {
            font-size: 16px !important;
            margin-bottom: 0 !important;
        }

        .breadcrumb {
            font-size: 11px !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .breadcrumb-item {
            font-size: 11px !important;
        }

        .card {
            border-radius: 8px !important;
        }

        .row {
            margin: 0 -6px !important;
        }

        .row > [class*="col-"] {
            padding: 0 6px !important;
        }

        .annual-ctc-box {
            padding: 10px 14px;
            margin-top: 10px;
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            border-radius: 8px;
            border: 1px solid #c4b5fd;
        }

        .annual-ctc-box .annual-label {
            font-size: 11px;
            color: #6d28d9;
            font-weight: 500;
        }

        .annual-ctc-box .annual-value {
            font-size: 22px;
            font-weight: 700;
            color: #7c3aed;
        }

        .annual-ctc-box .annual-breakdown {
            display: flex;
            gap: 12px;
            margin-top: 4px;
            flex-wrap: wrap;
            font-size: 10px;
            color: #6d28d9;
        }

        .calculation-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px;
            background: var(--gray-100);
            border-radius: 6px;
        }

        .calculation-toggle .toggle-label {
            font-size: 10px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .text-muted-small {
            font-size: 10px;
            color: var(--gray-500);
        }

        .badge-hour-based {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }

        .badge-day-based {
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .form-section {
                border: none;
                box-shadow: none;
                page-break-inside: avoid;
            }
            .btn {
                display: none !important;
            }
        }
    </style>
@endsection

@section('content-area')
    
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Monthly Payroll</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('monthly-payrolls.index') }}">Monthly Payroll</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ul>
        </div>
        <div class="page-header-right no-print">
            @php
                $calcType = $userPayroll && $userPayroll->payrollMaster 
                    ? ($userPayroll->payrollMaster->payroll_calculation_type ?? 'day_based') 
                    : 'day_based';
            @endphp
            <span class="calculation-type-badge {{ $calcType === 'hour_based' ? 'hour-based' : 'day-based' }}">
                <i class="feather-{{ $calcType === 'hour_based' ? 'clock' : 'calendar' }}"></i>
                {{ ucfirst(str_replace('_', ' ', $calcType)) }}
            </span>
            <span class="status-badge {{ $monthlyPayroll->payment_status }}">
                {{ ucfirst($monthlyPayroll->payment_status) }}
            </span>
        </div>
    </div>

    <div class="main-content" style="padding: 30px !important;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('monthly-payrolls.update', $monthlyPayroll->id) }}" method="POST" id="editPayrollForm">
                    @csrf
                    @method('PUT')

                    <!-- Employee Header -->
                    <div class="employee-header">
                        <div class="employee-avatar">
                            {{ strtoupper(substr($monthlyPayroll->user->name ?? 'NA', 0, 2)) }}
                        </div>
                        <div class="employee-info">
                            <h4>{{ $monthlyPayroll->user->name ?? 'N/A' }}</h4>
                            <p>{{ $monthlyPayroll->user->employee_id ?? 'N/A' }}</p>
                            <div class="employee-meta">
                                <span><i class="feather-briefcase"></i> {{ $monthlyPayroll->user->jobDetails->designationRel->name ?? 'N/A' }}</span>
                                <span><i class="feather-grid"></i> {{ $monthlyPayroll->user->jobDetails->departmentRel->name ?? 'N/A' }}</span>
                                <span><i class="feather-mail"></i> {{ $monthlyPayroll->user->email ?? 'N/A' }}</span>
                                <span><i class="feather-calendar"></i> {{ Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('F Y') }}</span>
                                <span>
                                    <i class="feather-{{ $calcType === 'hour_based' ? 'clock' : 'calendar' }}"></i>
                                    {{ ucfirst(str_replace('_', ' ', $calcType)) }}
                                    @if($calcType === 'hour_based' && $userPayroll && $userPayroll->payrollMaster)
                                        ({{ $userPayroll->payrollMaster->working_hours_per_day ?? 8 }} hrs/day)
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    @if($monthlyPayroll->payment_status !== 'pending')
                        <div class="alert-warning">
                            <i class="feather-alert-triangle"></i>
                            <span>⚠️ This payroll is already <strong>{{ ucfirst($monthlyPayroll->payment_status) }}</strong>. Only pending payrolls can be edited.</span>
                        </div>
                    @endif

                    <div class="alert-info">
                        <i class="feather-info"></i>
                        <span>Editing payroll record. Only pending payrolls can be edited.</span>
                    </div>

                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <span><i class="feather-file-text me-1" style="color: var(--primary);"></i>Basic Info</span>
                            <span class="badge-status status-badge {{ $monthlyPayroll->payment_status }}">
                                {{ ucfirst($monthlyPayroll->payment_status) }}
                            </span>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Payroll Month <span class="required">*</span></label>
                                    <select name="payroll_month" class="form-control @error('payroll_month') is-invalid @enderror" required>
                                        @foreach ($months as $value => $label)
                                            <option value="{{ $value }}" {{ $monthlyPayroll->payroll_month == $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('payroll_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Processing Date</label>
                                    <input type="text" class="form-control" value="{{ $monthlyPayroll->processing_date ? Carbon\Carbon::parse($monthlyPayroll->processing_date)->format('d M Y h:i A') : 'N/A' }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Processed By</label>
                                    <input type="text" class="form-control" value="{{ $monthlyPayroll->processor->name ?? 'N/A' }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="1">{{ old('remarks', $monthlyPayroll->remarks) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- DAY-BASED ATTENDANCE SECTION (visible only for day-based)    -->
                    <!-- ============================================================ -->
                    <div id="dayBasedAttendance" class="form-section" style="{{ $calcType === 'day_based' ? '' : 'display: none;' }}">
                        <div class="section-title">
                            <span><i class="feather-users me-1" style="color: var(--primary);"></i>Attendance (Day-Based)</span>
                            <span class="proration-badge" id="prorationBadge" style="display: none;">
                                <i class="feather-percent"></i>
                                <span id="prorationText"></span>
                            </span>
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Present <span class="required">*</span></label>
                                    <input type="number" step="0.5" name="present_days" id="present_days"
                                        class="form-control @error('present_days') is-invalid @enderror"
                                        value="{{ old('present_days', $monthlyPayroll->present_days) }}" required>
                                    @error('present_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Half Days</label>
                                    <input type="number" step="0.5" class="form-control"
                                        value="{{ old('half_days', $monthlyPayroll->half_days ?? 0) }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Paid Leaves</label>
                                    <input type="number" step="0.5" name="paid_leaves" id="paid_leaves" class="form-control"
                                        value="{{ old('paid_leaves', $monthlyPayroll->paid_leaves ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Unpaid Leaves</label>
                                    <input type="number" step="0.5" class="form-control"
                                        value="{{ old('unpaid_leaves', $monthlyPayroll->unpaid_leaves ?? 0) }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Working Days</label>
                                    <input type="number" class="form-control" 
                                        value="{{ $monthlyPayroll->total_working_days ?? 30 }}" readonly disabled>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Payable Days</label>
                                    <input type="number" class="form-control" id="payable_days_input"
                                        value="{{ $monthlyPayroll->payable_days ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Holidays</label>
                                    <input type="number" class="form-control" id="holidays_days"
                                        value="{{ $monthlyPayroll->holidays ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Week Offs</label>
                                    <input type="number" class="form-control" id="week_offs_days"
                                        value="{{ $monthlyPayroll->week_offs ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>OT Hours</label>
                                    <input type="number" step="0.5" name="overtime_hours" id="overtime_hours" class="form-control"
                                        value="{{ old('overtime_hours', $monthlyPayroll->overtime_hours ?? 0) }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>OT Rate</label>
                                    <input type="number" step="0.01" class="form-control" 
                                        value="{{ $monthlyPayroll->overtime_rate ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Absent</label>
                                    <input type="number" class="form-control" id="absent_days_input"
                                        value="{{ $monthlyPayroll->absent_days ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Proration</label>
                                    <input type="text" class="form-control" id="prorationFactorDisplay" 
                                        value="{{ $monthlyPayroll->payable_days && $monthlyPayroll->total_working_days ? number_format($monthlyPayroll->payable_days / $monthlyPayroll->total_working_days * 100, 1) : 100 }}%" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- HOUR-BASED ATTENDANCE SECTION (visible only for hour-based)  -->
                    <!-- ============================================================ -->
                    <div id="hourBasedAttendance" class="hour-based-section" style="{{ $calcType === 'hour_based' ? '' : 'display: none;' }}">
                        <div class="section-title">
                            <span><i class="feather-clock me-1" style="color: #1e40af;"></i>Attendance (Hour-Based)</span>
                            <span class="proration-badge" id="hourProrationBadge" style="display: none; background: #dbeafe; color: #1e40af;">
                                <i class="feather-percent"></i>
                                <span id="hourProrationText"></span>
                            </span>
                        </div>
                        
                        <div class="hour-based-info">
                            <span class="info-item"><i class="feather-clock"></i> <strong>Working Hours/Day:</strong> {{ $userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8 }} hrs</span>
                            <span class="info-item"><i class="feather-target"></i> <strong>Expected Hours:</strong> <span id="expected_hours_display">{{ $monthlyPayroll->expected_hours ?? 0 }}</span> hrs</span>
                            <span class="info-item"><i class="feather-check-circle"></i> <strong>Actual Worked:</strong> <span id="actual_hours_display">{{ $monthlyPayroll->actual_worked_hours ?? 0 }}</span> hrs</span>
                            <span class="info-item"><i class="feather-dollar-sign"></i> <strong>Hourly Rate:</strong> ₹<span id="hourly_rate_display">{{ number_format($monthlyPayroll->hourly_rate ?? 0, 2) }}</span></span>
                            <span class="info-item"><i class="feather-percent"></i> <strong>Proration:</strong> <span id="hour_proration_display">
                                @php
                                    $expected = $monthlyPayroll->expected_hours ?? 1;
                                    $actual = $monthlyPayroll->actual_worked_hours ?? 0;
                                    $paidLeaveHours = ($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8);
                                    $totalPayable = $actual + $paidLeaveHours;
                                    $pct = $expected > 0 ? min(100, ($totalPayable / $expected) * 100) : 0;
                                    echo number_format($pct, 1) . '%';
                                @endphp
                            </span></span>
                            @if(($monthlyPayroll->paid_leaves ?? 0) > 0)
                                <span class="info-item" style="background: #fef3c7; padding: 2px 8px; border-radius: 4px; color: #92400e;">
                                    <i class="feather-calendar"></i> <strong>Paid Leaves:</strong> {{ $monthlyPayroll->paid_leaves ?? 0 }} days
                                    ({{ ($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8) }} hrs)
                                </span>
                            @endif
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Expected Hours</label>
                                    <input type="number" step="0.01" class="form-control hour-based" 
                                        id="expected_hours" value="{{ $monthlyPayroll->expected_hours ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Actual Worked Hours <span class="required">*</span></label>
                                    <input type="number" step="0.01" name="actual_worked_hours" id="actual_worked_hours"
                                        class="form-control hour-based @error('actual_worked_hours') is-invalid @enderror"
                                        value="{{ old('actual_worked_hours', $monthlyPayroll->actual_worked_hours ?? 0) }}" required>
                                    <small class="help-text">Total hours actually worked from attendance</small>
                                    @error('actual_worked_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Paid Leave Hours (Auto)</label>
                                    <input type="number" step="0.01" class="form-control hour-based" 
                                        id="paid_leave_hours" 
                                        value="{{ ($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8) }}" 
                                        readonly>
                                    <small class="help-text">Paid leaves × Working hours/day</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Total Payable Hours</label>
                                    <input type="number" step="0.01" class="form-control hour-based" 
                                        id="total_payable_hours" 
                                        value="{{ ($monthlyPayroll->actual_worked_hours ?? 0) + (($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8)) }}" 
                                        readonly>
                                    <small class="help-text">Actual worked + Paid leave hours</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Hourly Rate</label>
                                    <input type="number" step="0.01" class="form-control hour-based" 
                                        id="hourly_rate" value="{{ $monthlyPayroll->hourly_rate ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Working Hours/Day</label>
                                    <input type="number" class="form-control hour-based" 
                                        id="working_hours_per_day" 
                                        value="{{ $userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8 }}" 
                                        readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Paid Leave Days</label>
                                    <input type="number" step="0.5" class="form-control hour-based" 
                                        id="paid_leave_days" value="{{ $monthlyPayroll->paid_leaves ?? 0 }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Proration Factor</label>
                                    <input type="text" class="form-control hour-based" id="hour_proration_factor"
                                        value="{{ number_format((($monthlyPayroll->actual_worked_hours ?? 0) + (($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8))) / max(1, $monthlyPayroll->expected_hours ?? 1) * 100, 1) }}%" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- EARNINGS SECTION (Common for both types)                    -->
                    <!-- ============================================================ -->
                    <div class="form-section">
                        <div class="section-title">
                            <span><i class="feather-trending-up me-1" style="color: var(--success);"></i>Earnings</span>
                            <span style="font-size: 10px; color: var(--gray-500);"><i class="feather-edit-2"></i> Editable</span>
                        </div>
                        <div class="component-card">
                            <div class="component-header">
                                <span class="component-title"><i class="feather-dollar-sign"></i> Salary Components</span>
                                <span style="font-size: 9px; color: var(--gray-500);">₹</span>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">Basic</span>
                                        <input type="number" step="0.01" name="basic_salary" id="basic_salary"
                                            class="form-control prorated-field @error('basic_salary') is-invalid @enderror"
                                            value="{{ old('basic_salary', $monthlyPayroll->basic_salary) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">HRA</span>
                                        <input type="number" step="0.01" name="hra" id="hra"
                                            class="form-control prorated-field"
                                            value="{{ old('hra', $monthlyPayroll->hra) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Conv.</span>
                                        <input type="number" step="0.01" name="conveyence" id="conveyence"
                                            class="form-control prorated-field"
                                            value="{{ old('conveyence', $monthlyPayroll->conveyence) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Medical</span>
                                        <input type="number" step="0.01" name="medical_allowance" id="medical_allowance"
                                            class="form-control prorated-field"
                                            value="{{ old('medical_allowance', $monthlyPayroll->medical_allowance) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Children</span>
                                        <input type="number" step="0.01" name="children_allowance" id="children_allowance"
                                            class="form-control prorated-field"
                                            value="{{ old('children_allowance', $monthlyPayroll->children_allowance ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Post</span>
                                        <input type="number" step="0.01" name="post_allowance" id="post_allowance"
                                            class="form-control prorated-field"
                                            value="{{ old('post_allowance', $monthlyPayroll->post_allowance ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">LTA</span>
                                        <input type="number" step="0.01" name="leave_travel_allowance" id="lta"
                                            class="form-control prorated-field"
                                            value="{{ old('leave_travel_allowance', $monthlyPayroll->leave_travel_allowance ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Incentive</span>
                                        <input type="number" step="0.01" name="monthly_incentive" id="monthly_incentive"
                                            class="form-control prorated-field"
                                            value="{{ old('monthly_incentive', $monthlyPayroll->monthly_incentive ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Special</span>
                                        <input type="number" step="0.01" name="special_allowance" id="special_allowance"
                                            class="form-control prorated-field"
                                            value="{{ old('special_allowance', $monthlyPayroll->special_allowance ?? 0) }}">
                                    </div>
                                    <div class="input-group" style="background: #f0fdf4; border-color: #86efac;">
                                        <span class="input-group-text" style="background: #dcfce7; color: #166534;">OT</span>
                                        <input type="number" step="0.01" name="overtime_amount" id="overtime_amount"
                                            class="form-control" style="background: #f0fdf4;"
                                            value="{{ old('overtime_amount', $monthlyPayroll->overtime_amount ?? 0) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- DEDUCTIONS SECTION                                           -->
                    <!-- ============================================================ -->
                    <div class="form-section">
                        <div class="section-title">
                            <span><i class="feather-trending-down me-1" style="color: var(--danger);"></i>Deductions</span>
                            <span style="font-size: 10px; color: var(--gray-500);"><i class="feather-edit-2"></i> Editable</span>
                        </div>
                        <div class="component-card">
                            <div class="component-header">
                                <span class="component-title"><i class="feather-minus-circle"></i> Employee</span>
                                <span style="font-size: 9px; color: var(--gray-500);">₹</span>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">PF</span>
                                        <input type="number" step="0.01" name="provident_fund" id="pf"
                                            class="form-control"
                                            value="{{ old('provident_fund', $monthlyPayroll->provident_fund) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">ESI</span>
                                        <input type="number" step="0.01" name="esi" id="esi"
                                            class="form-control"
                                            value="{{ old('esi', $monthlyPayroll->esi) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">PT</span>
                                        <input type="number" step="0.01" name="professional_tax" id="pt"
                                            class="form-control"
                                            value="{{ old('professional_tax', $monthlyPayroll->professional_tax) }}" required>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">TDS</span>
                                        <input type="number" step="0.01" name="tds" id="tds"
                                            class="form-control"
                                            value="{{ old('tds', $monthlyPayroll->tds ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Loan</span>
                                        <input type="number" step="0.01" name="loan_deduction" id="loan_deduction"
                                            class="form-control"
                                            value="{{ old('loan_deduction', $monthlyPayroll->loan_deduction ?? 0) }}">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Other</span>
                                        <input type="number" step="0.01" name="other_deductions" id="other_deductions"
                                            class="form-control"
                                            value="{{ old('other_deductions', $monthlyPayroll->other_deductions ?? 0) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- EMPLOYER CONTRIBUTIONS SECTION                              -->
                    <!-- ============================================================ -->
                    <div class="employer-section">
                        <div class="section-title">
                            <span><i class="feather-briefcase me-1" style="color: #166534;"></i>Employer</span>
                            <span style="font-size: 9px; color: #166534;"><i class="feather-info"></i> Part of CTC</span>
                        </div>
                        <div class="component-card" style="border-color: #86efac;">
                            <div class="component-header" style="background: #dcfce7; border-bottom-color: #86efac;">
                                <span class="component-title" style="color: #166534;">
                                    <i class="feather-plus-circle"></i> Employer Cost
                                </span>
                                <span style="font-size: 9px; color: #166534;">₹</span>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group" style="border-color: #86efac;">
                                        <span class="input-group-text" style="background: #dcfce7; border-right-color: #86efac; color: #166534;">Employer PF</span>
                                        <input type="number" step="0.01" name="employer_provident_fund" id="employer_pf"
                                            class="form-control" style="background: #f0fdf4;"
                                            value="{{ old('employer_provident_fund', $monthlyPayroll->employer_provident_fund ?? 0) }}" readonly>
                                    </div>
                                    <div class="input-group" style="border-color: #86efac;">
                                        <span class="input-group-text" style="background: #dcfce7; border-right-color: #86efac; color: #166534;">Employer ESI</span>
                                        <input type="number" step="0.01" name="employer_esi" id="employer_esi"
                                            class="form-control" style="background: #f0fdf4;"
                                            value="{{ old('employer_esi', $monthlyPayroll->employer_esi ?? 0) }}" readonly>
                                    </div>
                                    <div class="input-group" style="border-color: #86efac; grid-column: span 2;">
                                        <span class="input-group-text" style="background: #dcfce7; border-right-color: #86efac; color: #166534; min-width: 90px;">
                                            <i class="feather-pie-chart"></i> Total
                                        </span>
                                        <input type="text" class="form-control" id="total_employer_cost" 
                                            style="background: #f0fdf4; font-weight: 600; color: #166534;"
                                            value="₹{{ number_format(($monthlyPayroll->employer_provident_fund ?? 0) + ($monthlyPayroll->employer_esi ?? 0), 2) }}" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- SUMMARY SECTION                                              -->
                    <!-- ============================================================ -->
                    <div class="form-section">
                        <div class="section-title">
                            <span><i class="feather-pie-chart me-1" style="color: var(--primary);"></i>Summary</span>
                            <span style="font-size: 10px; color: var(--gray-500);"><i class="feather-clock"></i> Auto</span>
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label"><i class="feather-trending-up" style="color: var(--success);"></i> Gross</div>
                                <div class="summary-value" style="color: var(--success);">
                                    <span id="gross_display">₹{{ number_format($monthlyPayroll->gross_earnings, 2) }}</span>
                                    <small>/m</small>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label"><i class="feather-trending-down" style="color: var(--danger);"></i> Deductions</div>
                                <div class="summary-value" style="color: var(--danger);">
                                    <span id="deductions_display">₹{{ number_format($monthlyPayroll->total_deductions, 2) }}</span>
                                    <small>/m</small>
                                </div>
                            </div>
                            <div class="summary-item highlight">
                                <div class="summary-label"><i class="feather-check-circle" style="color: var(--primary);"></i> Net</div>
                                <div class="summary-value" style="color: var(--primary);">
                                    <span id="net_display">₹{{ number_format($monthlyPayroll->net_payable, 2) }}</span>
                                    <small>/m</small>
                                </div>
                            </div>
                            <div class="summary-item" style="border-left: 2px solid #f59e0b;">
                                <div class="summary-label"><i class="feather-briefcase" style="color: #f59e0b;"></i> Employer</div>
                                <div class="summary-value" style="color: #f59e0b;">
                                    <span id="employer_contributions_display">
                                        ₹{{ number_format(($monthlyPayroll->employer_provident_fund ?? 0) + ($monthlyPayroll->employer_esi ?? 0), 2) }}
                                    </span>
                                    <small>/m</small>
                                </div>
                            </div>
                            <div class="summary-item" style="border-left: 2px solid #8b5cf6;">
                                <div class="summary-label"><i class="feather-pie-chart" style="color: #8b5cf6;"></i> Monthly CTC</div>
                                <div class="summary-value" style="font-size: 18px; color: #8b5cf6;">
                                    <span id="monthly_ctc_display">
                                        ₹{{ number_format(($monthlyPayroll->gross_earnings ?? 0) + ($monthlyPayroll->employer_provident_fund ?? 0) + ($monthlyPayroll->employer_esi ?? 0), 2) }}
                                    </span>
                                    <small>/m</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields -->
                    <input type="hidden" name="gross_earnings" id="gross_earnings" value="{{ $monthlyPayroll->gross_earnings }}">
                    <input type="hidden" name="total_deductions" id="total_deductions" value="{{ $monthlyPayroll->total_deductions }}">
                    <input type="hidden" name="net_payable" id="net_payable" value="{{ $monthlyPayroll->net_payable }}">

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 no-print" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-200);">
                        <div>
                            <button type="reset" class="btn btn-secondary me-1">
                                <i class="feather-refresh-cw me-1"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-save me-1"></i>Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
<script>
    jQuery(document).ready(function($) {
        console.log('Payroll Edit Page Loaded');
        
        // ================================================================
        // CONFIGURATION
        // ================================================================
        const isHourBased = {{ $calcType === 'hour_based' ? 'true' : 'false' }};
        const workingHoursPerDay = {{ $userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8 }};
        
        console.log('Calculation Type:', isHourBased ? 'Hour-Based' : 'Day-Based');
        console.log('Working Hours Per Day:', workingHoursPerDay);

        // ================================================================
        // STORE ORIGINAL VALUES
        // ================================================================
        let originalPayroll = {
            // Day-based values
            full_month_basic: {{ $userPayroll ? $userPayroll->basic_salary : 0 }},
            full_month_hra: {{ $userPayroll ? $userPayroll->hra : 0 }},
            full_month_conveyence: {{ $userPayroll ? $userPayroll->conveyence : 0 }},
            full_month_medical: {{ $userPayroll ? $userPayroll->medical_allowance : 0 }},
            full_month_children: {{ $userPayroll ? $userPayroll->children_allowance : 0 }},
            full_month_post: {{ $userPayroll ? $userPayroll->post_allowance : 0 }},
            full_month_lta: {{ $userPayroll ? $userPayroll->leave_travel_allowance : 0 }},
            full_month_incentive: {{ $userPayroll ? $userPayroll->monthly_incentive : 0 }},
            full_month_special: {{ $userPayroll ? $userPayroll->special_allowance : 0 }},
            full_month_pf: {{ $userPayroll ? $userPayroll->provident_fund : 0 }},
            full_month_esi: {{ $userPayroll ? $userPayroll->esi : 0 }},
            full_month_pt: {{ $userPayroll ? $userPayroll->professional_tax : 0 }},
            full_month_tds: {{ $userPayroll ? $userPayroll->tds : 0 }},
            full_month_employer_pf: {{ $userPayroll ? $userPayroll->employer_provident_fund : 0 }},
            full_month_employer_esi: {{ $userPayroll ? $userPayroll->employer_esi : 0 }},
            total_working_days: {{ $monthlyPayroll->total_working_days ?? 31 }},
            week_offs: {{ $monthlyPayroll->week_offs ?? 0 }},
            holidays: {{ $monthlyPayroll->holidays ?? 0 }},
            present_days: parseFloat($('#present_days').val()) || 0,
            paid_leaves: parseFloat($('#paid_leaves').val()) || 0,
            
            // Hour-based values
            expected_hours: {{ $monthlyPayroll->expected_hours ?? 0 }},
            actual_worked_hours: {{ $monthlyPayroll->actual_worked_hours ?? 0 }},
            hourly_rate: {{ $monthlyPayroll->hourly_rate ?? 0 }},
        };

        console.log('Original Values:', originalPayroll);

        // ================================================================
        // HELPER FUNCTIONS
        // ================================================================
        function formatNumber(num) {
            return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function showNotification(message, type = 'info') {
            if (typeof toastr !== 'undefined') {
                if (type === 'error') {
                    toastr.error(message);
                } else if (type === 'warning') {
                    toastr.warning(message);
                } else if (type === 'success') {
                    toastr.success(message);
                } else {
                    toastr.info(message);
                }
            } else {
                alert(message);
            }
        }

        // ================================================================
        // DAY-BASED CALCULATIONS
        // ================================================================
        function calculateProratedSalary() {
            let presentDays = parseFloat($('#present_days').val()) || 0;
            let paidLeaves = parseFloat($('#paid_leaves').val()) || 0;
            let weekOffs = originalPayroll.week_offs || 0;
            let holidays = originalPayroll.holidays || 0;
            let totalDays = originalPayroll.total_working_days;
            
            let payableDays = presentDays + paidLeaves + weekOffs + holidays;
            let prorationFactor = Math.min(payableDays / totalDays, 1);
            
            console.log('Day-Based Proration:', {
                presentDays, paidLeaves, weekOffs, holidays,
                payableDays, totalDays, factor: prorationFactor
            });
            
            return { 
                factor: prorationFactor, 
                payableDays: payableDays, 
                totalDays: totalDays, 
                presentDays: presentDays, 
                paidLeaves: paidLeaves,
                weekOffs: weekOffs,
                holidays: holidays
            };
        }

        function recalculateDayBasedEarnings() {
            let proration = calculateProratedSalary();
            
            let basic = originalPayroll.full_month_basic * proration.factor;
            let hra = originalPayroll.full_month_hra * proration.factor;
            let conveyence = originalPayroll.full_month_conveyence * proration.factor;
            let medical = originalPayroll.full_month_medical * proration.factor;
            let children = originalPayroll.full_month_children * proration.factor;
            let post = originalPayroll.full_month_post * proration.factor;
            let lta = originalPayroll.full_month_lta * proration.factor;
            let incentive = originalPayroll.full_month_incentive * proration.factor;
            let special = originalPayroll.full_month_special * proration.factor;
            
            $('#basic_salary').val(basic.toFixed(2));
            $('#hra').val(hra.toFixed(2));
            $('#conveyence').val(conveyence.toFixed(2));
            $('#medical_allowance').val(medical.toFixed(2));
            $('#children_allowance').val(children.toFixed(2));
            $('#post_allowance').val(post.toFixed(2));
            $('#lta').val(lta.toFixed(2));
            $('#monthly_incentive').val(incentive.toFixed(2));
            $('#special_allowance').val(special.toFixed(2));
            
            // Recalculate deductions
            let pf = originalPayroll.full_month_pf * proration.factor;
            let esi = originalPayroll.full_month_esi * proration.factor;
            let pt = originalPayroll.full_month_pt * proration.factor;
            let tds = originalPayroll.full_month_tds * proration.factor;
            
            $('#pf').val(pf.toFixed(2));
            $('#esi').val(esi.toFixed(2));
            $('#pt').val(pt.toFixed(2));
            $('#tds').val(tds.toFixed(2));
            
            let employerPf = originalPayroll.full_month_employer_pf * proration.factor;
            let employerEsi = originalPayroll.full_month_employer_esi * proration.factor;
            
            $('#employer_pf').val(employerPf.toFixed(2));
            $('#employer_esi').val(employerEsi.toFixed(2));
            
            // Update attendance summary
            let absentDays = Math.max(0, proration.totalDays - proration.payableDays);
            $('#payable_days_input').val(proration.payableDays.toFixed(1));
            $('#absent_days_input').val(absentDays.toFixed(1));
            
            let percentage = (proration.factor * 100).toFixed(1);
            $('#prorationFactorDisplay').val(percentage + '%');
            
            // Update proration badge
            let badge = $('#prorationBadge');
            let text = $('#prorationText');
            if (proration.factor < 1) {
                text.text(`Prorated: ${percentage}% (${proration.payableDays.toFixed(1)}/${proration.totalDays} days)`);
                badge.show();
                $('.prorated-field').addClass('prorated');
            } else {
                text.text('Full month');
                badge.hide();
                $('.prorated-field').removeClass('prorated');
            }
            
            calculateTotals();
            
            console.log('✅ Day-Based Recalculated');
        }

        // ================================================================
        // HOUR-BASED CALCULATIONS
        // ================================================================
        function calculateHourBasedSalary() {
            let actualHours = parseFloat($('#actual_worked_hours').val()) || 0;
            let expectedHours = parseFloat($('#expected_hours').val()) || 1;
            let paidLeaveDays = parseFloat($('#paid_leave_days').val()) || 0;
            let hoursPerDay = workingHoursPerDay;
            
            // Calculate paid leave hours
            let paidLeaveHours = paidLeaveDays * hoursPerDay;
            
            // Total payable hours = actual worked + paid leave hours
            let totalPayableHours = actualHours + paidLeaveHours;
            
            // Cap at expected hours (100%)
            let payableHours = Math.min(totalPayableHours, expectedHours);
            
            // Hourly rate
            let hourlyRate = originalPayroll.full_month_basic / Math.max(1, expectedHours);
            
            // Basic salary = payable hours × hourly rate
            let basicSalary = payableHours * hourlyRate;
            
            // Cap at full month salary
            basicSalary = Math.min(basicSalary, originalPayroll.full_month_basic);
            
            // Proration factor
            let prorationFactor = expectedHours > 0 ? Math.min(1, totalPayableHours / expectedHours) : 0;
            
            console.log('Hour-Based Calculation:', {
                actualHours,
                paidLeaveDays,
                paidLeaveHours,
                totalPayableHours,
                payableHours,
                expectedHours,
                hourlyRate,
                basicSalary,
                prorationFactor
            });
            
            return {
                actualHours,
                paidLeaveHours,
                totalPayableHours,
                payableHours,
                expectedHours,
                hourlyRate,
                basicSalary,
                prorationFactor,
                paidLeaveDays
            };
        }

        function recalculateHourBasedEarnings() {
            let result = calculateHourBasedSalary();
            
            // Update basic salary
            $('#basic_salary').val(result.basicSalary.toFixed(2));
            
            // Update hourly rate display
            $('#hourly_rate').val(result.hourlyRate.toFixed(2));
            $('#hourly_rate_display').text(result.hourlyRate.toFixed(2));
            
            // Update expected hours display
            $('#expected_hours_display').text(result.expectedHours.toFixed(2));
            
            // Update actual hours display
            $('#actual_hours_display').text(result.actualHours.toFixed(2));
            
            // Update paid leave hours
            $('#paid_leave_hours').val(result.paidLeaveHours.toFixed(2));
            
            // Update total payable hours
            $('#total_payable_hours').val(result.totalPayableHours.toFixed(2));
            
            // Update proration
            let percentage = (result.prorationFactor * 100).toFixed(1);
            $('#hour_proration_factor').val(percentage + '%');
            $('#hour_proration_display').text(percentage + '%');
            
            // Update proration badge
            let badge = $('#hourProrationBadge');
            let text = $('#hourProrationText');
            if (result.prorationFactor < 1 && result.prorationFactor > 0) {
                text.text(`Prorated: ${percentage}% (${result.totalPayableHours.toFixed(1)}/${result.expectedHours.toFixed(1)} hrs)`);
                badge.show();
            } else if (result.prorationFactor === 0) {
                text.text('No hours worked');
                badge.show();
            } else {
                text.text('Full month');
                badge.hide();
            }
            
            // Calculate prorated other components
            let pf = originalPayroll.full_month_pf * result.prorationFactor;
            let esi = originalPayroll.full_month_esi * result.prorationFactor;
            let pt = originalPayroll.full_month_pt * result.prorationFactor;
            let tds = originalPayroll.full_month_tds * result.prorationFactor;
            
            $('#pf').val(pf.toFixed(2));
            $('#esi').val(esi.toFixed(2));
            $('#pt').val(pt.toFixed(2));
            $('#tds').val(tds.toFixed(2));
            
            let employerPf = originalPayroll.full_month_employer_pf * result.prorationFactor;
            let employerEsi = originalPayroll.full_month_employer_esi * result.prorationFactor;
            
            $('#employer_pf').val(employerPf.toFixed(2));
            $('#employer_esi').val(employerEsi.toFixed(2));
            
            // Update HRA and other allowances (prorated)
            let hra = originalPayroll.full_month_hra * result.prorationFactor;
            let conveyence = originalPayroll.full_month_conveyence * result.prorationFactor;
            let medical = originalPayroll.full_month_medical * result.prorationFactor;
            let children = originalPayroll.full_month_children * result.prorationFactor;
            let post = originalPayroll.full_month_post * result.prorationFactor;
            let lta = originalPayroll.full_month_lta * result.prorationFactor;
            let incentive = originalPayroll.full_month_incentive * result.prorationFactor;
            let special = originalPayroll.full_month_special * result.prorationFactor;
            
            $('#hra').val(hra.toFixed(2));
            $('#conveyence').val(conveyence.toFixed(2));
            $('#medical_allowance').val(medical.toFixed(2));
            $('#children_allowance').val(children.toFixed(2));
            $('#post_allowance').val(post.toFixed(2));
            $('#lta').val(lta.toFixed(2));
            $('#monthly_incentive').val(incentive.toFixed(2));
            $('#special_allowance').val(special.toFixed(2));
            
            calculateTotals();
            
            console.log('✅ Hour-Based Recalculated');
        }

        // ================================================================
        // COMMON FUNCTIONS
        // ================================================================
        function calculateOvertime() {
            let overtimeHours = parseFloat($('#overtime_hours').val()) || 0;
            let basicSalary = parseFloat($('#basic_salary').val()) || 0;
            if (overtimeHours > 0 && basicSalary > 0) {
                let hourlyRate;
                if (isHourBased) {
                    let expectedHours = parseFloat($('#expected_hours').val()) || 1;
                    hourlyRate = originalPayroll.full_month_basic / expectedHours;
                } else {
                    hourlyRate = basicSalary / originalPayroll.total_working_days / 8;
                }
                let overtimeAmount = overtimeHours * hourlyRate * 1.5;
                $('#overtime_amount').val(overtimeAmount.toFixed(2));
                return overtimeAmount;
            }
            return 0;
        }

        function calculateTotals() {
            let basic = parseFloat($('#basic_salary').val()) || 0;
            let hra = parseFloat($('#hra').val()) || 0;
            let conveyence = parseFloat($('#conveyence').val()) || 0;
            let medical = parseFloat($('#medical_allowance').val()) || 0;
            let children = parseFloat($('#children_allowance').val()) || 0;
            let post = parseFloat($('#post_allowance').val()) || 0;
            let lta = parseFloat($('#lta').val()) || 0;
            let incentive = parseFloat($('#monthly_incentive').val()) || 0;
            let special = parseFloat($('#special_allowance').val()) || 0;
            let overtime = parseFloat($('#overtime_amount').val()) || 0;
            let pf = parseFloat($('#pf').val()) || 0;
            let esi = parseFloat($('#esi').val()) || 0;
            let pt = parseFloat($('#pt').val()) || 0;
            let tds = parseFloat($('#tds').val()) || 0;
            let loan = parseFloat($('#loan_deduction').val()) || 0;
            let other = parseFloat($('#other_deductions').val()) || 0;
            let employerPf = parseFloat($('#employer_pf').val()) || 0;
            let employerEsi = parseFloat($('#employer_esi').val()) || 0;
            
            let gross = basic + hra + conveyence + medical + children + post + lta + incentive + special + overtime;
            let deductions = pf + esi + pt + tds + loan + other;
            let net = gross - deductions;
            let employerTotal = employerPf + employerEsi;
            let monthlyCTC = gross + employerTotal;
            
            $('#gross_display').text('₹' + formatNumber(gross));
            $('#deductions_display').text('₹' + formatNumber(deductions));
            $('#net_display').text('₹' + formatNumber(net));
            $('#employer_contributions_display').text('₹' + formatNumber(employerTotal));
            $('#monthly_ctc_display').text('₹' + formatNumber(monthlyCTC));
            $('#total_employer_cost').val('₹' + formatNumber(employerTotal));
            
            $('#gross_earnings').val(gross.toFixed(2));
            $('#total_deductions').val(deductions.toFixed(2));
            $('#net_payable').val(net.toFixed(2));
        }

        // ================================================================
        // EVENT HANDLERS
        // ================================================================
        
        // Day-based: Present days change
        $('#present_days').on('input change', function() {
            if (!isHourBased) {
                let presentDays = parseFloat($(this).val()) || 0;
                let paidLeaves = parseFloat($('#paid_leaves').val()) || 0;
                let weekOffs = originalPayroll.week_offs || 0;
                let holidays = originalPayroll.holidays || 0;
                let totalDays = originalPayroll.total_working_days;
                
                let payableDays = presentDays + paidLeaves + weekOffs + holidays;
                
                if (payableDays > totalDays) {
                    let maxAllowed = totalDays - weekOffs - holidays - paidLeaves;
                    showNotification(
                        `⚠️ Present Days would make Payable Days (${payableDays.toFixed(1)}) exceed Total Working Days (${totalDays})! Maximum allowed: ${maxAllowed.toFixed(1)}`,
                        'error'
                    );
                    $(this).val(originalPayroll.present_days || 0);
                    recalculateDayBasedEarnings();
                    return;
                }
                
                originalPayroll.present_days = parseFloat($(this).val()) || 0;
                recalculateDayBasedEarnings();
                
                let proration = calculateProratedSalary();
                if (proration.factor < 1 && proration.factor > 0) {
                    let percentage = (proration.factor * 100).toFixed(1);
                    showNotification(`Salary prorated to ${percentage}% (${proration.payableDays.toFixed(1)}/${proration.totalDays} days)`, 'info');
                } else if (proration.factor === 0) {
                    showNotification('⚠️ No working days - salary will be zero', 'warning');
                } else {
                    showNotification('✅ Full month salary applied', 'success');
                }
            }
        });

        // Day-based: Paid leaves change
        $('#paid_leaves').on('input change', function() {
            if (!isHourBased) {
                let presentDays = parseFloat($('#present_days').val()) || 0;
                let paidLeaves = parseFloat($(this).val()) || 0;
                let weekOffs = originalPayroll.week_offs || 0;
                let holidays = originalPayroll.holidays || 0;
                let totalDays = originalPayroll.total_working_days;
                
                let payableDays = presentDays + paidLeaves + weekOffs + holidays;
                
                if (payableDays > totalDays) {
                    let maxAllowed = totalDays - weekOffs - holidays - presentDays;
                    showNotification(
                        `⚠️ Paid Leaves would make Payable Days (${payableDays.toFixed(1)}) exceed Total Working Days (${totalDays})! Maximum allowed: ${maxAllowed.toFixed(1)}`,
                        'error'
                    );
                    $(this).val(originalPayroll.paid_leaves || 0);
                    recalculateDayBasedEarnings();
                    return;
                }
                
                originalPayroll.paid_leaves = parseFloat($(this).val()) || 0;
                recalculateDayBasedEarnings();
            }
        });

        // Hour-based: Actual worked hours change
        $('#actual_worked_hours').on('input change', function() {
            if (isHourBased) {
                let actualHours = parseFloat($(this).val()) || 0;
                let expectedHours = parseFloat($('#expected_hours').val()) || 1;
                
                if (actualHours > expectedHours * 1.5) {
                    showNotification(
                        `⚠️ Actual worked hours (${actualHours.toFixed(2)}) exceed expected hours (${expectedHours.toFixed(2)}) by more than 50%. Please verify.`,
                        'warning'
                    );
                }
                
                recalculateHourBasedEarnings();
            }
        });

        // Overtime hours change
        $('#overtime_hours').on('input change', function() {
            calculateOvertime();
            calculateTotals();
        });

        // Any earning/deduction change
        $('#basic_salary, #hra, #conveyence, #medical_allowance, #children_allowance, #post_allowance, #lta, #monthly_incentive, #special_allowance, #overtime_amount, #pf, #esi, #pt, #tds, #loan_deduction, #other_deductions')
            .on('input change', function() {
                calculateTotals();
            });

        // ================================================================
        // INITIALIZATION
        // ================================================================
        function initializeUI() {
            if (isHourBased) {
                // Update paid leave days from the payroll record
                $('#paid_leave_days').val({{ $monthlyPayroll->paid_leaves ?? 0 }});
                recalculateHourBasedEarnings();
                console.log('✅ Hour-Based UI Initialized');
            } else {
                // Store current values
                originalPayroll.present_days = parseFloat($('#present_days').val()) || 0;
                originalPayroll.paid_leaves = parseFloat($('#paid_leaves').val()) || 0;
                recalculateDayBasedEarnings();
                console.log('✅ Day-Based UI Initialized');
            }
        }

        initializeUI();

        // ================================================================
        // LOGGING
        // ================================================================
        console.log('Payroll Edit Page Initialized Successfully!');
        console.log('Is Hour-Based:', isHourBased);
        console.log('Working Hours Per Day:', workingHoursPerDay);
        console.log('Original Payroll Data:', originalPayroll);
    });
</script>
@endsection