{{-- resources/views/client/payroll/monthly-payroll/create.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== ALERT CARDS ==================== */
        .alert-card {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .alert-card.warning {
            background: linear-gradient(135deg, #eef3fd, #bfd3f7);
            border-left: 3px solid #1e3a8a;
        }

        .alert-card.info {
            background: linear-gradient(135deg, #f7faff, #e3edfe);
            border-left: 3px solid #2563eb;
        }

        .alert-card i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .alert-card.warning i {
            color: #1e3a8a;
        }

        .alert-card.info i {
            color: #2563eb;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .alert-text {
            font-size: 11px;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 0;
        }

        .alert-list {
            margin: 6px 0 0 0;
            padding-left: 16px;
            font-size: 11px;
            color: #475569;
        }

        .alert-list li {
            margin-bottom: 2px;
        }

        /* ==================== MONTH SELECTOR ==================== */
        .month-selector {
            background: linear-gradient(145deg, #1e3a8a, #1e3a8a);
            padding: 18px 16px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 16px;
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.3);
        }

        .month-title {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }

        .month-subtitle {
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 12px;
            font-weight: 400;
        }

        .month-selector select {
            height: 36px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: white;
            font-size: 12.5px;
            font-weight: 500;
            color: #1e293b;
            padding: 0 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .month-selector select:hover {
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-1px);
        }

        .month-selector select:focus {
            border-color: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
            outline: none;
        }

        /* ==================== EMPLOYEE SELECTION CARD ==================== */
        .employee-selection-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            background: white;
            margin-top: 10px;
        }

        .employee-selection-header {
            background: #f8fafc;
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .employee-selection-header h6 {
            font-size: 11.5px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .employee-selection-header h6 i {
            color: #1e3a8a;
        }

        .employee-count-badge {
            background: #1e3a8a;
            color: white;
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 10.5px;
            font-weight: 500;
        }

        .employee-list-container {
            max-height: 260px;
            overflow-y: auto;
            padding: 8px;
        }

        .employee-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .employee-item:hover {
            background: #f8fafc;
        }

        .employee-item:last-child {
            border-bottom: none;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .employee-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 11px;
        }

        .employee-details h6 {
            font-size: 11.5px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1px;
        }

        .employee-details p {
            font-size: 10px;
            color: #64748b;
            margin: 0;
        }

        .employee-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .select-all-row {
            padding: 8px 10px;
            background: #f1f5f9;
            border-radius: 6px;
            margin: 6px 8px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* ==================== SUMMARY CARD ==================== */
        .summary-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .summary-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .summary-label {
            font-size: 10.5px;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-value {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }

        .summary-subtext {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* ==================== CALCULATION PARAMETERS ==================== */
        .params-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .param-item {
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .param-label {
            font-size: 10.5px;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .param-label i {
            color: #1e3a8a;
        }

        .param-value {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }

        .param-note {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ==================== OPTION CARDS ==================== */
        .option-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.2s;
            height: 100%;
            background: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .option-card:hover {
            border-color: #1e3a8a;
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.08);
            transform: translateY(-2px);
        }

        .option-card .card-body {
            padding: 14px;
        }

        .option-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f1f5f9;
        }

        .option-title i {
            color: #1e3a8a;
            font-size: 15px;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0;
            margin: 0;
        }

        .form-check-input {
            width: 16px;
            height: 16px;
            margin: 0;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            transition: all 0.2s;
            cursor: pointer;
        }

        .form-check-input:checked {
            border-color: #1e3a8a;
            background-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .form-check-label {
            font-size: 11.5px;
            color: #334155;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-check-input[type="checkbox"] {
            border-radius: 4px;
        }

        .form-check-input[type="checkbox"]:checked {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e");
        }

        /* ==================== PREVIEW SECTION ==================== */
        .preview-card {
            background: linear-gradient(135deg, #e3edfe, #e0f2fe);
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid #1e3a8a;
        }

        .preview-card i {
            font-size: 16px;
            color: #1e3a8a;
            flex-shrink: 0;
        }

        .preview-text {
            font-size: 11.5px;
            color: #1e293b;
            font-weight: 500;
            line-height: 1.5;
            margin: 0;
        }

        .preview-text strong {
            color: #1e3a8a;
            font-weight: 600;
        }

        /* ==================== FORM ELEMENTS ==================== */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 10px 10px 0 0 !important;
        }

        .card-header h5 {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-header h5 i {
            color: #1e3a8a;
            font-size: 15px;
        }

        .card-body {
            padding: 14px;
        }

        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 12px 14px;
            border-radius: 0 0 10px 10px !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ==================== BUTTONS ==================== */
        .btn {
            padding: 6px 14px;
            font-size: 11.5px;
            font-weight: 500;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1e3a8a;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #16295e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }

        .btn-outline-secondary {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #1e293b;
        }

        .btn-info {
            background: #2563eb;
            color: white;
            border: none;
        }

        .btn-info:hover {
            background: #1e3a8a;
        }

        /* ==================== PROCESS PAYROLL BUTTON — enhanced, stands out
           against the compact button scale used everywhere else on this
           page (larger, bolder, gradient + glow, animated hover/press). ==================== */
        #submitBtn {
            padding: 11px 28px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #1e3a8a);
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border: none;
            position: relative;
            overflow: hidden;
        }

        #submitBtn i {
            font-size: 14px;
        }

        #submitBtn:hover {
            background: linear-gradient(135deg, #1e3a8a, #16295e);
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(30, 58, 138, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        #submitBtn:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(30, 58, 138, 0.35);
        }

        #submitBtn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        #submitBtn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, transparent 40%, rgba(255, 255, 255, 0.25) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        #submitBtn:hover::after {
            transform: translateX(100%);
        }

        /* ==================== LOADING ==================== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f1f5f9;
            border-top-color: #1e3a8a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .month-selector {
                padding: 14px 12px;
            }

            .month-title {
                font-size: 16px;
            }

            .card-body {
                padding: 10px 12px;
            }

            .alert-card {
                padding: 8px 10px;
            }

            .preview-card {
                padding: 8px 10px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .params-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==================== ANIMATIONS ==================== */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #previewSection,
        #employeeSelectionContainer,
        #calculationSummary {
            animation: slideIn 0.3s ease;
        }

        .hidden {
            display: none;
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
                <li class="breadcrumb-item active">Process New Month</li>
            </ul>
        </div>

    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="main-content" style="padding: 12px !important;">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="feather-play-circle"></i>
                            Process Monthly Payroll
                        </h5>
                    </div>

                    <!-- Display validation errors -->
                    @if ($errors->any())
                        <div class="alert alert-danger m-4 mb-0">
                            <i class="feather-alert-circle me-2"></i>
                            <div>
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <!-- Warning Card -->
                    <div class="alert-card warning mx-4 mt-4">
                        <i class="feather-alert-triangle"></i>
                        <div class="alert-content">
                            <div class="alert-title">Important Notice</div>
                            <p class="alert-text">Processing payroll will calculate salaries for all active employees based
                                on their attendance and leave records for the selected month. This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <!-- Info Card -->
                    <div class="alert-card info mx-4">
                        <i class="feather-info"></i>
                        <div class="alert-content">
                            <div class="alert-title">Before You Proceed</div>
                            <ul class="alert-list">
                                <li>Ensure all attendance records for the month are finalized</li>
                                <li>Verify all leave requests are approved/rejected</li>
                                <li>Check that all employees have active payroll assignments</li>
                                <li>Confirm overtime calculations if applicable</li>
                            </ul>
                        </div>
                    </div>

                    <form action="{{ route('monthly-payrolls.store') }}" method="POST" id="processForm">
                        @csrf
                        <input type="hidden" name="force_reprocess" id="force_reprocess" value="0">
                        <!-- Month Selector -->
                        <div class="month-selector mx-4">
                            <div class="month-title">📅 Select Payroll Month</div>
                            <div class="month-subtitle">Choose the month you want to process payroll for</div>

                            <div class="row mt-4">
                                <div class="col-md-8 col-lg-6 mx-auto">
                                    <select name="payroll_month" id="payroll_month" class="form-control" required>
                                        <option value="">-- Choose a month --</option>
                                        @foreach ($months as $value => $name)
                                            <option value="{{ $value }}"
                                                {{ old('payroll_month') == $value ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Processing Options -->
                        <div class="row px-4 pb-4 g-4">
                            <div class="col-md-12">
                                <div class="option-card">
                                    <div class="card-body">
                                        <h6 class="option-title">
                                            <i class="feather-users"></i>
                                            Employee Selection
                                        </h6>
                                        <div class="radio-group ms-3">
                                            <div class="form-check">
                                                <input type="radio" name="employee_selection" id="all_employees"
                                                    class="form-check-input" value="all" checked>
                                                <label class="form-check-label" for="all_employees">
                                                    Process for all active employees
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input type="radio" name="employee_selection" id="selected_employees"
                                                    class="form-check-input" value="selected">
                                                <label class="form-check-label" for="selected_employees">
                                                    Select specific employees
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="option-card">
                                    <div class="card-body">
                                        <h6 class="option-title">
                                            <i class="feather-settings"></i>
                                            Calculation Options
                                        </h6>
                                        <div class="checkbox-group">
                                            <div class="form-check">
                                                <input type="checkbox" name="include_overtime" id="include_overtime"
                                                    class="form-check-input" value="1">
                                                <label class="form-check-label" for="include_overtime">
                                                    Include overtime calculations
                                                </label>
                                            </div>
                                             <div class="form-check">
                                                <input type="checkbox" name="include_loan_deductions"
                                                    id="include_loan_deductions" class="form-check-input" value="1"
                                                    checked>
                                                <label class="form-check-label" for="include_loan_deductions">
                                                    Include loan deductions (EMI + lump sum; auto-capped to available salary)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employee Selection Container (Hidden by default) -->
                        <div id="employeeSelectionContainer" class="mx-4 mb-4" style="display: none;">
                            <div class="employee-selection-card">
                                <div class="employee-selection-header">
                                    <h6>
                                        <i class="feather-users"></i>
                                        Select Employees for Processing
                                    </h6>
                                    <span class="employee-count-badge" id="selectedCount">0 selected</span>
                                </div>

                                <div class="select-all-row">
                                    <div class="form-check">
                                        <input type="checkbox" id="selectAllEmployees" class="form-check-input">
                                        <label class="form-check-label" for="selectAllEmployees">
                                            <strong>Select All Employees</strong>
                                        </label>
                                    </div>
                                    <span id="totalEmployeesCount">{{ count($employees ?? []) }} total</span>
                                </div>

                                <div class="employee-list-container" id="employeeList">
                                    @foreach ($employees ?? [] as $employee)
                                        <div class="employee-item">
                                            <div class="employee-info">
                                                <div class="employee-avatar">
                                                    {{ $employee->name ? strtoupper(substr($employee->name, 0, 2)) : 'NA' }}
                                                </div>
                                                <div class="employee-details">
                                                    <h6>{{ $employee->name }} ({{ $employee->employee_id ?? 'N/A' }})</h6>
                                                    <p>
                                                        {{ $employee->email ?? 'N/A' }} |
                                                        {{ $employee->jobDetails->designationRel->name ?? 'No Designation' }}
                                                    </p>
                                                </div>
                                            </div>

                                            <input type="checkbox" name="selected_employees[]"
                                                value="{{ $employee->id }}" class="employee-checkbox"
                                                data-employee-id="{{ $employee->id }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Calculation Summary Section -->
                        <div id="calculationSummary" class="mx-4 mb-4" style="display: none;">
                            <div class="summary-card">
                                <h6 class="mb-2" style="font-size: 12px; font-weight: 600;">
                                    <i class="feather-bar-chart-2 me-2"></i>
                                    Payroll Calculation Summary
                                </h6>

                                <div class="summary-grid" id="summaryGrid">
                                    <div class="summary-item">
                                        <div class="summary-label">Selected Employees</div>
                                        <div class="summary-value" id="summarySelectedCount">0</div>
                                        <div class="summary-subtext">will be processed</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">Estimated Gross</div>
                                        <div class="summary-value" id="summaryEstimatedGross">₹ 0</div>
                                        <div class="summary-subtext">based on current payroll</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">Est. Deductions</div>
                                        <div class="summary-value" id="summaryEstimatedDeductions">₹ 0</div>
                                        <div class="summary-subtext">including loans & taxes</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">Est. Net Payable</div>
                                        <div class="summary-value" id="summaryEstimatedNet">₹ 0</div>
                                        <div class="summary-subtext">after all deductions</div>
                                    </div>
                                </div>

                                <!-- Calculation Parameters -->
                                <div class="params-grid mt-3">
                                    <div class="param-item">
                                        <div class="param-label">
                                            <i class="feather-clock"></i>
                                            Overtime
                                        </div>
                                        <div class="param-value" id="paramOvertime">Included</div>
                                        <div class="param-note">Based on attendance records</div>
                                    </div>
                                    <div class="param-item">
                                        <div class="param-label">
                                            <i class="fas fa-rupee-sign"></i>
                                            Loan Deductions
                                        </div>
                                        <div class="param-value" id="paramLoans">Included</div>
                                        <div class="param-note">Active loans will be deducted</div>
                                    </div>
                                    <div class="param-item">
                                        <div class="param-label">
                                            <i class="feather-calendar"></i>
                                            Working Days
                                        </div>
                                        <div class="param-value" id="paramWorkingDays">-</div>
                                        <div class="param-note">Total days in selected month</div>
                                    </div>
                                    <div class="param-item">
                                        <div class="param-label">
                                            <i class="feather-percent"></i>
                                            Proration
                                        </div>
                                        <div class="param-value" id="paramProration">Auto-calculated</div>
                                        <div class="param-note">For mid-month joiners/leavers</div>
                                    </div>
                                </div>

                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-info btn-sm" id="refreshSummaryBtn">
                                        <i class="feather-refresh-cw"></i> Refresh Summary
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Section -->
                        <div class="preview-card mx-4 mb-4" id="previewSection" style="display: none;">
                            <i class="feather-eye"></i>
                            <p class="preview-text" id="previewText"></p>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="feather-play"></i> Process Payroll
                            </button>
                            <a href="{{ route('monthly-payrolls.index') }}" class="btn btn-outline-secondary">
                                <i class="feather-x"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden data for JavaScript -->
    <input type="hidden" id="employeesData" value='@json($employees ?? [])'>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Configure toastr
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "timeOut": "5000",
                    "extendedTimeOut": "2000"
                };
            }

            // Initialize variables
            let employeesData = [];
            try {
                employeesData = JSON.parse($('#employeesData').val() || '[]');
            } catch (e) {
                console.error('Error parsing employees data', e);
            }

            let workingDaysCache = {};

            // Show/hide employee selection based on radio
            $('input[name="employee_selection"]').change(function() {
                if ($(this).val() === 'selected') {
                    $('#employeeSelectionContainer').slideDown(300);
                    updateCalculationSummary();
                } else {
                    $('#employeeSelectionContainer').slideUp(300);
                    // Clear all selections
                    $('.employee-checkbox').prop('checked', false);
                    updateSelectedCount();
                    updateCalculationSummary();
                }
            });

            // Month selection change - fetch working days and update summary
            $('#payroll_month').change(function() {
                let month = $(this).find('option:selected').text();
                let monthValue = $(this).val();

                if (monthValue) {
                    $('#previewSection').fadeIn(300);
                    $('#previewText').html(
                        `You are about to process payroll for <strong>${month}</strong>. Please verify all data before proceeding.`
                    );

                    // Fetch working days for the selected month
                    fetchWorkingDays(monthValue);

                    // Show calculation summary
                    $('#calculationSummary').slideDown(300);
                    updateCalculationSummary();
                } else {
                    $('#previewSection').fadeOut(300);
                    $('#calculationSummary').slideUp(300);
                }
            });

            // Fetch working days for selected month
            function fetchWorkingDays(month) {
                // You can make an AJAX call to get actual working days
                // For now, we'll calculate approximate days
                let [year, monthNum] = month.split('-');
                let daysInMonth = new Date(year, monthNum, 0).getDate();

                // Approximate working days (excluding Sundays)
                let workingDays = Math.floor(daysInMonth * 6 / 7);

                workingDaysCache[month] = {
                    total: daysInMonth,
                    working: workingDays
                };

                $('#paramWorkingDays').text(daysInMonth + ' days');
            }

            // Select/Deselect All functionality
            $('#selectAllEmployees').change(function() {
                $('.employee-checkbox').prop('checked', $(this).is(':checked'));
                updateSelectedCount();
                updateCalculationSummary();
            });

            // Individual checkbox change
            $(document).on('change', '.employee-checkbox', function() {
                updateSelectedCount();
                updateCalculationSummary();

                // Update select all checkbox
                let totalCheckboxes = $('.employee-checkbox').length;
                let checkedCheckboxes = $('.employee-checkbox:checked').length;

                if (checkedCheckboxes === totalCheckboxes) {
                    $('#selectAllEmployees').prop('checked', true);
                } else if (checkedCheckboxes === 0) {
                    $('#selectAllEmployees').prop('checked', false);
                } else {
                    $('#selectAllEmployees').prop('checked', false);
                }
            });

            // Update selected count display
            function updateSelectedCount() {
                let count = $('.employee-checkbox:checked').length;
                $('#selectedCount').text(count + ' selected');

                if (count > 0) {
                    $('#summarySelectedCount').text(count);
                } else {
                    $('#summarySelectedCount').text($('input[name="employee_selection"]:checked').val() === 'all' ?
                        'All employees' : '0');
                }
            }

            // Update calculation summary based on selections
            function updateCalculationSummary() {
                let selectionType = $('input[name="employee_selection"]:checked').val();
                let includeOvertime = $('#include_overtime').is(':checked');
                let includeLoans = $('#include_loan_deductions').is(':checked');

                // Update parameters display
                $('#paramOvertime').text(includeOvertime ? 'Included' : 'Excluded');
                $('#paramLoans').text(includeLoans ? 'Included' : 'Excluded');

                if (selectionType === 'selected') {
                    let selectedIds = $('.employee-checkbox:checked').map(function() {
                        return $(this).val();
                    }).get();

                    if (selectedIds.length > 0) {
                        calculateEstimates(selectedIds, includeOvertime, includeLoans);
                    } else {
                        resetEstimates();
                    }
                } else if (selectionType === 'all') {
                    // For all employees, we can show estimates based on all active employees
                    let allEmployeeIds = employeesData.map(emp => emp.id);
                    calculateEstimates(allEmployeeIds, includeOvertime, includeLoans);
                }
            }

            // Calculate estimates based on selected employees
            function calculateEstimates(employeeIds, includeOvertime, includeLoans) {
                // Show loading
                $('#loadingOverlay').fadeIn(200);

                $.ajax({
                    url: "{{ route('monthly-payrolls.calculate-estimates') }}",
                    type: "POST",
                    data: {
                        employee_ids: employeeIds,
                        payroll_month: $('#payroll_month').val(),
                        include_overtime: includeOvertime ? 1 : 0,
                        include_loans: includeLoans ? 1 : 0,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#summaryEstimatedGross').text('₹ ' + formatNumber(response.total_gross));
                            $('#summaryEstimatedDeductions').text('₹ ' + formatNumber(response
                                .total_deductions));
                            $('#summaryEstimatedNet').text('₹ ' + formatNumber(response.total_net));
                            restoreRealEstimateLabels();

                            if (response.working_days) {
                                $('#paramWorkingDays').text(response.working_days + ' days');
                            }

                            if (response.proration_info) {
                                $('#paramProration').text(response.proration_info);
                            }
                        }
                        $('#loadingOverlay').fadeOut(200);
                    },
                    error: function(xhr) {
                        console.error('Error calculating estimates', xhr);
                        $('#loadingOverlay').fadeOut(200);

                        // Fallback to basic estimation
                        basicEstimation(employeeIds.length);
                    }
                });
            }

            // Basic estimation fallback -- a rough placeholder (flat assumed
            // averages, not this tenant's real salary data), only shown when
            // the real calculate-estimates call fails. Clearly labeled as an
            // approximation so it's never mistaken for a real calculated
            // figure -- previously rendered identically to the real numbers.
            function basicEstimation(employeeCount) {
                let avgGross = 25000; // Average gross salary assumption
                let avgDeductions = 3000; // Average deductions assumption

                let totalGross = employeeCount * avgGross;
                let totalDeductions = employeeCount * avgDeductions;
                let totalNet = totalGross - totalDeductions;

                $('#summaryEstimatedGross').text('≈ ₹ ' + formatNumber(totalGross));
                $('#summaryEstimatedDeductions').text('≈ ₹ ' + formatNumber(totalDeductions));
                $('#summaryEstimatedNet').text('≈ ₹ ' + formatNumber(totalNet));

                $('#summaryEstimatedGross').closest('.summary-item').find('.summary-subtext')
                    .text('rough approximation -- live calculation unavailable').addClass('text-warning');
                $('#summaryEstimatedDeductions').closest('.summary-item').find('.summary-subtext')
                    .text('rough approximation -- live calculation unavailable').addClass('text-warning');
                $('#summaryEstimatedNet').closest('.summary-item').find('.summary-subtext')
                    .text('rough approximation -- live calculation unavailable').addClass('text-warning');
            }

            // Restore the normal subtext once a real calculate-estimates response lands.
            function restoreRealEstimateLabels() {
                $('#summaryEstimatedGross').closest('.summary-item').find('.summary-subtext')
                    .text('based on current payroll').removeClass('text-warning');
                $('#summaryEstimatedDeductions').closest('.summary-item').find('.summary-subtext')
                    .text('including loans & taxes').removeClass('text-warning');
                $('#summaryEstimatedNet').closest('.summary-item').find('.summary-subtext')
                    .text('after all deductions').removeClass('text-warning');
            }

            // Reset estimates to zero
            function resetEstimates() {
                $('#summaryEstimatedGross').text('₹ 0');
                $('#summaryEstimatedDeductions').text('₹ 0');
                $('#summaryEstimatedNet').text('₹ 0');
            }

            // Format number with commas
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }

            // Refresh summary button
            $('#refreshSummaryBtn').click(function() {
                updateCalculationSummary();
                toastr.info('Refreshing calculation summary...');
            });

            // Form validation before submit
            $('#processForm').submit(function(e) {
                let month = $('#payroll_month').val();
                let selectionType = $('input[name="employee_selection"]:checked').val();

                if (!month) {
                    e.preventDefault();
                    toastr.error('Please select a month to process');
                    return false;
                }

                if (selectionType === 'selected') {
                    let selectedCount = $('.employee-checkbox:checked').length;
                    if (selectedCount === 0) {
                        e.preventDefault();
                        toastr.error('Please select at least one employee');
                        return false;
                    }
                }

                // Show loading overlay before submit
                $('#loadingOverlay').fadeIn(200);

                return true;
            });

            // Confirm process function
            window.confirmProcess = function() {
                let month = $('#payroll_month option:selected').text();
                let monthValue = $('#payroll_month').val();
                let selectionType = $('input[name="employee_selection"]:checked').val();
                let employeeCount = selectionType === 'all' ? 'all' : $('.employee-checkbox:checked').length;

                if (!monthValue) {
                    toastr.error('Please select a month');
                    return false;
                }

                if (selectionType === 'selected' && employeeCount === 0) {
                    toastr.error('Please select at least one employee');
                    return false;
                }

                let message = `Are you sure you want to process payroll for ${month}?\n\n`;
                message +=
                    `This will calculate salaries for ${employeeCount === 'all' ? 'ALL active employees' : employeeCount + ' selected employees'}.\n`;
                message += `This action cannot be undone.`;

                return confirm(message);
            };

            // Update summary when calculation options change
            $('#include_overtime, #include_loan_deductions').change(function() {
                updateCalculationSummary();
            });

            // Initialize on page load
            updateSelectedCount();

            // If there was an old selection, restore it
            @if (old('employee_selection') === 'selected')
                $('input[name="employee_selection"][value="selected"]').prop('checked', true).trigger('change');

                @if (old('selected_employees'))
                    let oldSelected = @json(old('selected_employees'));
                    $('.employee-checkbox').each(function() {
                        if (oldSelected.includes($(this).val())) {
                            $(this).prop('checked', true);
                        }
                    });
                    updateSelectedCount();
                @endif
            @endif

            @if (old('payroll_month'))
                $('#payroll_month').val('{{ old('payroll_month') }}').trigger('change');
            @endif
        });
    </script>
@endsection
