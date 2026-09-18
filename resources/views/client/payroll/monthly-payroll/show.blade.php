{{-- resources/views/client/payroll/monthly-payroll/show.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== EMPLOYEE SUMMARY ====================
           Matches Monthly Payroll's own index-table employee column
           (.employee-info/.employee-avatar/.employee-name/.employee-id) —
           gradient initials avatar overriding the shared .employee-avatar
           class, which is otherwise styled for an <img>, not a text div. */
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 15px;
            flex-shrink: 0;
        }

        .payroll-meta-row {
            font-size: 11px;
            color: var(--gray-500);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--gray-200);
        }

        .payroll-meta-row i {
            margin-right: 4px;
            font-size: 11px;
            color: var(--gray-400);
        }

        .payment-info {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 6px;
        }

        /* ==================== CARDS ==================== */
        .detail-card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            padding: 12px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .detail-card:hover {
            border-color: var(--primary);
        }

        .card-header-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--gray-100);
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .card-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .card-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
        }

        .card-badge-hour {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
        }

        /* ==================== INFO ROWS ==================== */
        .info-grid {
            display: grid;
            gap: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed var(--gray-200);
            padding: 6px 0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray-500);
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-label i {
            color: var(--gray-400);
            font-size: 10px;
            width: 16px;
        }

        .info-value {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 11px;
            text-align: right;
        }

        .info-value small {
            font-weight: 400;
            color: var(--gray-500);
            font-size: 11px;
            display: block;
            margin-top: 2px;
        }

        .amount-positive {
            color: var(--primary);
            font-weight: 600;
        }

        .amount-negative {
            color: #475569;
            font-weight: 600;
        }

        .total-row {
            background: var(--gray-50);
            padding: 6px;
            border-radius: 10px;
            margin-top: 8px;
        }

        .total-row .info-label {
            color: var(--gray-700);
            font-weight: 600;
        }

        .total-row .info-value {
            font-size: 15px;
            font-weight: 700;
        }

        /* ==================== NET PAYABLE CARD ==================== */
        .net-payable-card {
            background: var(--gray-50);
            text-align: center;
            padding: 16px;
        }

        .net-amount {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
            margin: 8px 0 4px;
        }

        .amount-in-words {
            font-size: 12px;
            color: var(--gray-500);
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 30px;
            display: inline-block;
        }

        /* ==================== ATTENDANCE SUMMARY ==================== */
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .stat-item {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.3;
        }

        .stat-label {
            font-size: 10px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-positive {
            color: var(--primary);
        }

        .stat-negative {
            color: #475569;
        }

        .stat-warning {
            color: var(--primary-mid);
        }

        .stat-info {
            color: #60a5fa;
        }

        /* ==================== HOUR-BASED SPECIFIC ==================== */
        .hour-based-info-bar {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .hour-based-info-bar .info-item {
            font-size: 12px;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hour-based-info-bar .info-item i {
            font-size: 14px;
        }

        .hour-based-info-bar .info-item strong {
            font-weight: 600;
        }

        .hour-based-info-bar .info-item .highlight {
            background: #dbeafe;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* ==================== BANK DETAILS ==================== */
        .bank-details {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
        }

        .bank-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 13px;
            margin-bottom: 2px;
        }

        .bank-ifsc {
            font-size: 11px;
            color: var(--gray-500);
            font-family: monospace;
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-buttons {
            position: sticky;
            bottom: 25px;
            background: white;
            padding: 16px 24px;
            border-radius: 50px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
            border: 1px solid var(--gray-200);
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
        }

        .btn i {
            font-size: 14px;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }

        .btn-outline-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-800);
        }

        /* ==================== ALERTS ==================== */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .alert-success {
            background: var(--success-light);
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: var(--danger-light);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert .close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 18px;
            opacity: 0.5;
            cursor: pointer;
        }

        /* ==================== MODAL STYLES ==================== */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            padding: 18px 22px;
            border-bottom: 1px solid var(--gray-200);
            border-radius: 16px 16px 0 0;
        }

        .modal-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header i {
            color: var(--primary);
        }

        .modal-body {
            padding: 22px;
        }

        .modal-footer {
            padding: 16px 22px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            border-radius: 0 0 16px 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 500;
            color: var(--gray-500);
            margin-bottom: 4px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px !important;
            }

            .employee-header {
                padding: 20px;
            }

            .employee-name {
                font-size: 20px;
            }

            .employee-meta {
                flex-direction: column;
                gap: 8px;
            }

            .net-amount {
                font-size: 28px;
            }

            .action-buttons {
                border-radius: 16px;
                width: 100%;
                padding: 16px;
            }

            .action-buttons .row {
                flex-direction: column;
                gap: 10px;
            }

            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }

            .attendance-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .hour-based-info-bar {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
@endsection

@section('content-area')

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Monthly Payroll Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('monthly-payrolls.index') }}">Monthly Payroll</a></li>
                <li class="breadcrumb-item active">Payroll Details</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle"></i>
                <span>{{ session('error') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- Employee Summary -->
        <div class="detail-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="employee-info">
                    <div class="employee-avatar">{{ strtoupper(substr($monthlyPayroll->user->name ?? 'NA', 0, 2)) }}</div>
                    <div class="employee-details">
                        <div class="employee-name">{{ $monthlyPayroll->user->name ?? 'N/A' }} <small class="text-muted">({{ $monthlyPayroll->user->employee_id ?? 'N/A' }})</small></div>
                        <div class="employee-id">{{ $monthlyPayroll->user->email ?? '' }}</div>
                    </div>
                </div>

                <div class="text-end">
                    <x-ui.status-badge :status="$monthlyPayroll->payment_status" />
                    @if ($monthlyPayroll->payment_date)
                        <div class="payment-info">
                            <i class="feather-credit-card"></i> Paid on:
                            {{ \Carbon\Carbon::parse($monthlyPayroll->payment_date)->format('d M Y') }}
                        </div>
                    @endif
                </div>
            </div>
            <div class="payroll-meta-row">
                <span><i class="feather-calendar"></i>
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('F Y') }}</span>
                @if ($monthlyPayroll->UserPayroll)
                    <span><i class="feather-tag"></i> Code:
                        {{ $monthlyPayroll->UserPayroll->payroll_code ?? 'N/A' }}</span>
                @endif
                @php
                    $calcType = $userPayroll && $userPayroll->payrollMaster
                        ? ($userPayroll->payrollMaster->payroll_calculation_type ?? 'day_based')
                        : 'day_based';
                @endphp
                <span>
                    <i class="feather-{{ $calcType === 'hour_based' ? 'clock' : 'calendar' }}"></i>
                    {{ ucfirst(str_replace('_', ' ', $calcType)) }}
                    @if($calcType === 'hour_based' && $userPayroll && $userPayroll->payrollMaster)
                        ({{ $userPayroll->payrollMaster->working_hours_per_day ?? 8 }} hrs/day)
                    @endif
                </span>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column - Employee Info & Attendance -->
            <div class="col-lg-4">
                <!-- Employee Information Card -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h6 class="card-title">
                            <i class="feather-user"></i>
                            Employee Information
                        </h6>
                        <span class="card-badge">Details</span>
                    </div>
                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label"><i class="feather-user"></i> Name</span>
                            <span class="info-value">{{ $monthlyPayroll->user->name ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="feather-hash"></i> Employee Code</span>
                            <span class="info-value">{{ $monthlyPayroll->user->employee_id ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="feather-briefcase"></i> Department</span>
                            <span class="info-value">
                                @php
                                    $dept = \DB::table('user_job_details')
                                        ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
                                        ->where('user_job_details.user_id', $monthlyPayroll->user_id)
                                        ->select('departments.name')
                                        ->first();
                                @endphp
                                {{ $dept->name ?? 'N/A' }}
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><i class="feather-tag"></i> Payroll Code</span>
                            <span class="info-value">{{ $monthlyPayroll->UserPayroll->payroll_code ?? 'N/A' }}</span>
                        </div>

                        <div class="info-row">
                            <span class="info-label"><i class="feather-{{ $calcType === 'hour_based' ? 'clock' : 'calendar' }}"></i> Calculation Type</span>
                            <span class="info-value">
                                <span class="{{ $calcType === 'hour_based' ? 'badge-hour-based' : 'card-badge' }}" style="padding: 2px 10px; border-radius: 12px; font-size: 10px;">
                                    {{ ucfirst(str_replace('_', ' ', $calcType)) }}
                                    @if($calcType === 'hour_based' && $userPayroll && $userPayroll->payrollMaster)
                                        ({{ $userPayroll->payrollMaster->working_hours_per_day ?? 8 }} hrs/day)
                                    @endif
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    @php
                        $bank = \DB::table('user_bank_details')->where('user_id', $monthlyPayroll->user_id)->first();
                    @endphp
                    @if ($bank)
                        <div class="bank-details">
                            <div class="bank-name">{{ $bank->bank_name ?? '' }}</div>
                            <div class="info-row mt-2">
                                <span class="info-label">Account</span>
                                <span class="info-value">{{ $bank->account_number ?? 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">IFSC</span>
                                <span class="bank-ifsc">{{ $bank->ifsc ?? 'N/A' }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Attendance Summary Card -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h6 class="card-title">
                            <i class="feather-calendar"></i>
                            Attendance Summary
                        </h6>
                        <span class="card-badge">{{ $monthlyPayroll->total_working_days }} Days</span>
                    </div>

                    <!-- Hour-Based Info Bar (visible only for hour-based) -->
                    @if($calcType === 'hour_based')
                        <div class="hour-based-info-bar">
                            <span class="info-item">
                                <i class="feather-target"></i>
                                <strong>Expected:</strong> <span class="highlight">{{ $monthlyPayroll->expected_hours ?? 0 }}</span> hrs
                            </span>
                            <span class="info-item">
                                <i class="feather-check-circle"></i>
                                <strong>Worked:</strong> <span class="highlight">{{ $monthlyPayroll->actual_worked_hours ?? 0 }}</span> hrs
                            </span>
                            <span class="info-item">
                                <i class="feather-dollar-sign"></i>
                                <strong>Rate:</strong> ₹<span class="highlight">{{ number_format($monthlyPayroll->hourly_rate ?? 0, 2) }}</span>/hr
                            </span>
                            @php
                                $expected = $monthlyPayroll->expected_hours ?? 1;
                                $actual = $monthlyPayroll->actual_worked_hours ?? 0;
                                $workingHoursPerDay = $userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8;
                                $paidLeaveHours = ($monthlyPayroll->paid_leaves ?? 0) * $workingHoursPerDay;
                                $totalPayable = $actual + $paidLeaveHours;
                                $pct = $expected > 0 ? min(100, ($totalPayable / $expected) * 100) : 0;
                            @endphp
                            <span class="info-item">
                                <i class="feather-percent"></i>
                                <strong>Proration:</strong> <span class="highlight">{{ number_format($pct, 1) }}%</span>
                            </span>
                            @if(($monthlyPayroll->paid_leaves ?? 0) > 0)
                                <span class="info-item" style="background: var(--primary-light); padding: 2px 10px; border-radius: 4px; color: var(--primary);">
                                    <i class="feather-calendar"></i>
                                    <strong>Paid Leaves:</strong> {{ $monthlyPayroll->paid_leaves ?? 0 }} days
                                    ({{ $paidLeaveHours }} hrs)
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="attendance-stats">
                        <div class="stat-item">
                            <div class="stat-value stat-positive">{{ $monthlyPayroll->present_days }}</div>
                            <div class="stat-label">Present</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">{{ $monthlyPayroll->paid_leaves }}</div>
                            <div class="stat-label">Paid Leaves</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value stat-warning">{{ $monthlyPayroll->unpaid_leaves }}</div>
                            <div class="stat-label">Unpaid</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">{{ $monthlyPayroll->holidays }}</div>
                            <div class="stat-label">Holidays</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">{{ $monthlyPayroll->week_offs }}</div>
                            <div class="stat-label">Week Offs</div>
                        </div>
                       
                        <div class="stat-item">
                            <div class="stat-value stat-negative">{{ $monthlyPayroll->absent_days }}</div>
                            <div class="stat-label">Absent</div>
                        </div>
                    </div>

                    <!-- Hour-based extra info -->
                    @if($calcType === 'hour_based')
                        <div class="info-row mt-3" style="border-top: 1px dashed var(--gray-200); padding-top: 10px;">
                            <span class="info-label"><i class="feather-clock"></i> Total Payable Hours</span>
                            <span class="info-value stat-info">
                                {{ number_format(($monthlyPayroll->actual_worked_hours ?? 0) + (($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8)), 2) }} hrs
                            </span>
                        </div>
                    @endif

                    @if ($monthlyPayroll->overtime_hours > 0)
                        <div class="info-row mt-2">
                            <span class="info-label"><i class="feather-clock"></i> Overtime Hours</span>
                            <span class="info-value amount-positive">{{ $monthlyPayroll->overtime_hours }} hrs</span>
                        </div>
                    @endif

                    @if ($monthlyPayroll->remarks)
                        <div class="mt-3 p-2" style="background: var(--gray-50); border-radius: 8px;">
                            <span class="info-label"><i class="feather-info"></i> Remarks</span>
                            <p class="mt-1 mb-0" style="font-size: 11px; color: var(--gray-600);">
                                {{ $monthlyPayroll->remarks }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Middle Column - Earnings -->
            <div class="col-lg-4">
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h6 class="card-title">
                            <i class="feather-trending-up"></i>
                            Earnings
                        </h6>
                        <span class="card-badge">Monthly</span>
                    </div>

                    <div class="info-grid">
                        <div class="info-row">
                            <span class="info-label">Basic Salary</span>
                            <span class="info-value">₹{{ number_format($monthlyPayroll->basic_salary, 2) }}</span>
                        </div>

                        @if($calcType === 'hour_based' && $monthlyPayroll->expected_hours > 0)
                            <div class="info-row" style="background: #eff6ff; padding: 4px 8px; border-radius: 6px; border-bottom-color: #93c5fd;">
                                <span class="info-label" style="color: #1e40af;">
                                    <i class="feather-clock"></i> Hours Calculation
                                </span>
                                <span class="info-value" style="font-size: 10px; color: #1e40af;">
                                    {{ number_format($monthlyPayroll->actual_worked_hours ?? 0, 2) }} hrs × ₹{{ number_format($monthlyPayroll->hourly_rate ?? 0, 2) }}
                                    @if(($monthlyPayroll->paid_leaves ?? 0) > 0)
                                        + {{ ($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8) }} hrs (leaves)
                                    @endif
                                </span>
                            </div>
                        @endif

                        @foreach ([
                            'hra' => 'HRA',
                            'conveyence' => 'Conveyance',
                            'medical_allowance' => 'Medical',
                            'children_allowance' => 'Children Allowance',
                            'post_allowance' => 'Post Allowance',
                            'leave_travel_allowance' => 'LTA',
                            'monthly_incentive' => 'Incentive',
                            'special_allowance' => 'Special Allowance',
                            'overtime_amount' => 'Overtime',
                        ] as $field => $label)
                            @if ($monthlyPayroll->$field > 0)
                                <div class="info-row">
                                    <span class="info-label">{{ $label }}</span>
                                    <span class="info-value">₹{{ number_format($monthlyPayroll->$field, 2) }}</span>
                                </div>
                            @endif
                        @endforeach

                        <!-- Individual Components from payroll_components table -->
                        @if ($monthlyPayroll->components && $monthlyPayroll->components->where('component_type', 'earning')->count() > 0)
                            @foreach ($monthlyPayroll->components->where('component_type', 'earning') as $component)
                                @if (
                                    !in_array($component->component_name, [
                                        'Basic Salary',
                                        'HRA',
                                        'Conveyance Allowance',
                                        'Medical Allowance',
                                        'Children Allowance',
                                        'Post Allowance',
                                        'Leave Travel Allowance',
                                        'Monthly Incentive',
                                        'Special Allowance',
                                        'Overtime',
                                    ]))
                                    <div class="info-row">
                                        <span class="info-label">{{ $component->component_name }}</span>
                                        <span class="info-value">₹{{ number_format($component->amount, 2) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <div class="info-row total-row">
                            <span class="info-label">Gross Earnings</span>
                            <span class="info-value amount-positive">₹{{ number_format($monthlyPayroll->gross_earnings, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Deductions & Net -->
            <div class="col-lg-4">
                <!-- Deductions Card -->
                <div class="detail-card">
                    <div class="card-header-custom">
                        <h6 class="card-title">
                            <i class="feather-trending-down"></i>
                            Deductions
                        </h6>
                        <span class="card-badge">Monthly</span>
                    </div>

                    <div class="info-grid">
                        @foreach ([
                            'provident_fund' => 'Provident Fund',
                            'esi' => 'ESI',
                            'professional_tax' => 'Professional Tax',
                            'tds' => 'TDS',
                            'loan_deduction' => 'Loan Deduction',
                            'other_deductions' => 'Other Deductions',
                        ] as $field => $label)
                            @if ($monthlyPayroll->$field > 0)
                                <div class="info-row">
                                    <span class="info-label">{{ $label }}</span>
                                    <span class="info-value amount-negative">-
                                        ₹{{ number_format($monthlyPayroll->$field, 2) }}</span>
                                </div>
                            @endif
                        @endforeach

                        <!-- Individual Deduction Components -->
                        @if ($monthlyPayroll->components && $monthlyPayroll->components->where('component_type', 'deduction')->count() > 0)
                            @foreach ($monthlyPayroll->components->where('component_type', 'deduction') as $component)
                                @if (
                                    !in_array($component->component_name, [
                                        'Provident Fund',
                                        'ESI',
                                        'Professional Tax',
                                        'TDS',
                                        'Loan Deduction',
                                        'Other Deductions',
                                    ]))
                                    <div class="info-row">
                                        <span class="info-label">{{ $component->component_name }}</span>
                                        <span class="info-value amount-negative">-
                                            ₹{{ number_format($component->amount, 2) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <div class="info-row total-row">
                            <span class="info-label">Total Deductions</span>
                            <span class="info-value amount-negative">-
                                ₹{{ number_format($monthlyPayroll->total_deductions, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Net Payable Card -->
                <div class="detail-card net-payable-card">
                    <div class="card-header-custom" style="border-bottom-color: #93c5fd;">
                        <h6 class="card-title">
                            <i class="fa-solid fa-rupee-sign" style="color: var(--primary);"></i>
                            Net Payable
                        </h6>
                    </div>

                    <div class="net-amount">₹{{ number_format($monthlyPayroll->net_payable, 2) }}</div>
                    
                    @if($calcType === 'hour_based')
                        <div style="font-size: 10px; color: var(--gray-500); margin-top: 4px;">
                            <i class="feather-clock"></i> 
                            {{ number_format(($monthlyPayroll->actual_worked_hours ?? 0) + (($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8)), 2) }} 
                            payable hours @ ₹{{ number_format($monthlyPayroll->hourly_rate ?? 0, 2) }}/hr
                            @if(($monthlyPayroll->paid_leaves ?? 0) > 0)
                                <span style="color: var(--primary); background: var(--primary-light); padding: 1px 6px; border-radius: 4px;">
                                    +{{ ($monthlyPayroll->paid_leaves ?? 0) * ($userPayroll && $userPayroll->payrollMaster ? $userPayroll->payrollMaster->working_hours_per_day ?? 8 : 8) }} hrs leaves
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Employer Contributions Card -->
                @if ($monthlyPayroll->employer_provident_fund > 0 || $monthlyPayroll->employer_esi > 0)
                    <div class="detail-card">
                        <div class="card-header-custom">
                            <h6 class="card-title">
                                <i class="feather-briefcase"></i>
                                Employer Contributions
                            </h6>
                        </div>

                        <div class="info-grid">
                            @if ($monthlyPayroll->employer_provident_fund > 0)
                                <div class="info-row">
                                    <span class="info-label">Employer PF</span>
                                    <span class="info-value">₹{{ number_format($monthlyPayroll->employer_provident_fund, 2) }}</span>
                                </div>
                            @endif
                            @if ($monthlyPayroll->employer_esi > 0)
                                <div class="info-row">
                                    <span class="info-label">Employer ESI</span>
                                    <span class="info-value">₹{{ number_format($monthlyPayroll->employer_esi, 2) }}</span>
                                </div>
                            @endif
                            <div class="info-row total-row">
                                <span class="info-label">Total CTC</span>
                                <span class="info-value">₹{{ number_format($monthlyPayroll->UserPayroll->ctc ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div class="d-flex gap-2 flex-wrap justify-content-center">
                <a href="{{ route('monthly-payrolls.payslip', $monthlyPayroll->id) }}" class="btn btn-success" target="_blank">
                    <i class="feather-file-text"></i> Generate Payslip
                </a>

                @if ($monthlyPayroll->payment_status == 'pending')
                    <button type="button" class="btn btn-warning" onclick="showStatusModal({{ $monthlyPayroll->id }})">
                        <i class="feather-edit"></i> Update Status
                    </button>
                    <a href="{{ route('monthly-payrolls.edit', $monthlyPayroll->id) }}" class="btn btn-primary">
                        <i class="feather-edit-2"></i> Edit Payroll
                    </a>
                @endif

                <a href="{{ route('monthly-payrolls.index', ['month' => $monthlyPayroll->payroll_month]) }}"
                    class="btn btn-outline-secondary">
                    <i class="feather-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        @if ($canReopen)
            <div class="action-buttons">
                <div class="d-flex gap-2 flex-wrap justify-content-center">
                    <button type="button" class="btn btn-outline-warning" onclick="showReopenModal()">
                        <i class="feather-rotate-ccw"></i> Reopen for Correction
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if ($canReopen)
        <!-- Reopen for Correction Modal -->
        <div class="modal fade" id="reopenModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="feather-rotate-ccw"></i>
                            Reopen Payroll for Correction
                        </h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('monthly-payrolls.reopen', $monthlyPayroll->id) }}">
                        @csrf
                        <div class="modal-body">
                            <p class="text-muted" style="font-size: 12px;">
                                This payroll is currently <strong>{{ ucfirst($monthlyPayroll->payment_status) }}</strong>.
                                Reopening sets it back to <strong>Pending</strong> so it can be edited, and records who
                                reopened it and why. Use Edit Payroll afterward to make the actual correction.
                            </p>
                            <div class="form-group">
                                <label>Reason (required)</label>
                                <textarea name="reason" class="form-control" rows="3" minlength="5" maxlength="500"
                                    placeholder="Why does this payroll need to be reopened?" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Reopen for Correction</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather-edit-2"></i>
                        Update Payment Status
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="statusForm" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" id="payment_status" class="form-control" required>
                                <option value="pending"
                                    {{ $monthlyPayroll->payment_status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="processed"
                                    {{ $monthlyPayroll->payment_status == 'processed' ? 'selected' : '' }}>Processed
                                </option>
                                <option value="paid" {{ $monthlyPayroll->payment_status == 'paid' ? 'selected' : '' }}>
                                    Paid</option>
                                <option value="cancelled"
                                    {{ $monthlyPayroll->payment_status == 'cancelled' ? 'selected' : '' }}>Cancelled
                                </option>
                            </select>
                        </div>
                        <div class="form-group" id="paymentDetails">
                            <label>Payment Date</label>
                            <input type="date" name="payment_date" class="form-control"
                                value="{{ old('payment_date', $monthlyPayroll->payment_date ?? date('Y-m-d')) }}">

                            <label class="mt-3">Payment Mode</label>
                            <select name="payment_mode" class="form-control">
                                <option value="">Select Mode</option>
                                <option value="Bank Transfer"
                                    {{ $monthlyPayroll->payment_mode == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer
                                </option>
                                <option value="Cheque" {{ $monthlyPayroll->payment_mode == 'Cheque' ? 'selected' : '' }}>
                                    Cheque</option>
                                <option value="Cash" {{ $monthlyPayroll->payment_mode == 'Cash' ? 'selected' : '' }}>
                                    Cash</option>
                                <option value="Online" {{ $monthlyPayroll->payment_mode == 'Online' ? 'selected' : '' }}>
                                    Online</option>
                            </select>

                            <label class="mt-3">Transaction Reference</label>
                            <input type="text" name="transaction_reference" class="form-control"
                                value="{{ $monthlyPayroll->transaction_reference }}"
                                placeholder="Cheque/Transaction No.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Toggle payment details based on status
            $('#payment_status').change(function() {
                if ($(this).val() == 'paid') {
                    $('#paymentDetails').slideDown(200);
                } else {
                    $('#paymentDetails').slideUp(200);
                }
            }).trigger('change');
        });

        function showStatusModal(id) {
            let url = "{{ route('monthly-payrolls.status', ':id') }}".replace(':id', id);
            $('#statusForm').attr('action', url);
            $('#statusModal').modal('show');
        }

        function showReopenModal() {
            $('#reopenModal').modal('show');
        }
    </script>
@endsection