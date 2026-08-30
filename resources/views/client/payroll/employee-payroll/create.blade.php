{{-- resources/views/client/payroll/employee-payroll/create.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== SIMPLE CLASSIC DESIGN ==================== */
        .form-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .form-card-header {
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .form-card-header h5 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-card-header h5 i {
            color: #4f46e5;
            font-size: 18px;
        }

        .form-card-body {
            padding: 24px;
        }

        .form-card-footer {
            padding: 16px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
        }

        /* ==================== FORM GROUPS ==================== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #ffffff;
            transition: all 0.2s;
        }

        .form-control:hover {
            border-color: #94a3b8;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
            padding-right: 36px;
        }

        .error-message {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
        }

        .help-text {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
            display: block;
        }

        /* ==================== FORM CHECK ==================== */
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
        }

        .form-check-input {
            width: 16px;
            height: 16px;
            border: 2px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }

        .form-check-label {
            font-size: 13px;
            color: #334155;
            cursor: pointer;
        }

        /* ==================== FORM ROW ==================== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* ==================== BASIC SALARY CARD ==================== */
        .basic-salary-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .basic-salary-label {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .basic-salary-input {
            width: 300px;
        }

        @media (max-width: 768px) {
            .basic-salary-input {
                width: 100%;
            }
        }

        /* ==================== COMPONENT CARDS ==================== */
        .component-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .component-header {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .component-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .component-title i {
            color: #4f46e5;
            font-size: 16px;
        }

        .component-badge {
            background: #eef2ff;
            color: #4f46e5;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .component-body {
            padding: 20px;
        }

        .component-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 992px) {
            .component-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .component-row {
                grid-template-columns: 1fr;
            }
        }

        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-right: none;
            border-radius: 6px 0 0 6px;
            padding: 7px 12px;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 6px 6px 0;
        }

        /* ==================== SUMMARY SECTION ==================== */
        .summary-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
        }

        .summary-title {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-title i {
            color: #4f46e5;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 992px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        .summary-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
        }

        .summary-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-value {
            color: #1e293b;
            font-size: 20px;
            font-weight: 600;
            line-height: 1.2;
        }

        .summary-value small {
            font-size: 12px;
            font-weight: 400;
            color: #64748b;
            margin-left: 4px;
        }

        /* ==================== VIEW TOGGLE ==================== */
        .view-toggle {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }

        .btn-group .btn {
            padding: 6px 16px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
        }

        .btn-group .btn.active {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        .btn-group .btn:first-child {
            border-radius: 6px 0 0 6px;
        }

        .btn-group .btn:last-child {
            border-radius: 0 6px 6px 0;
        }

        /* ==================== ALERTS ==================== */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .alert-info {
            background: #e6f3ff;
            border: 1px solid #b8daff;
            color: #004085;
        }

        .alert-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-danger ul {
            margin: 8px 0 0 20px;
        }

        /* ==================== BUTTONS ==================== */
        .btn {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-outline-secondary {
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #334155;
        }

        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }

        /* ==================== TOOLTIP ==================== */
        .tooltip-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            background: #e2e8f0;
            border-radius: 50%;
            color: #64748b;
            font-size: 10px;
            cursor: help;
            margin-left: 4px;
        }

        /* ==================== PAYROLL BREAKDOWN TABLE ==================== */
        .breakdown-table {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-top: 20px;
        }

        .breakdown-table h6 {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .breakdown-table table {
            width: 100%;
            font-size: 12px;
        }

        .breakdown-table td {
            padding: 6px 0;
        }

        .breakdown-table .text-end {
            text-align: right;
            font-weight: 500;
        }

        .breakdown-table .text-primary {
            color: #4f46e5;
        }

        .breakdown-table .text-danger {
            color: #ef4444;
        }

        .breakdown-table .text-success {
            color: #10b981;
        }

        .breakdown-table .border-top {
            border-top: 1px dashed #e2e8f0;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
             <div class="page-header-title">
                <h5 class="m-b-10">Employee Payroll Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('employee-payrolls.index') }}">Employee Payroll</a></li>
                <li class="breadcrumb-item">New Payroll Assignment</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="form-card">
            <!-- Display validation errors -->
            @if ($errors->any())
                <div class="alert alert-danger m-4 mb-0">
                    <i class="feather-alert-circle"></i>
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

            <form action="{{ route('employee-payrolls.store') }}" method="POST" id="payrollForm">
                @csrf
                <div class="form-card-header">
                    <h5>
                        <i class="feather-plus-circle"></i>
                        New Payroll Assignment
                    </h5>
                    <span class="component-badge">Create New</span>
                </div>

                <div class="form-card-body">
                    <!-- Info Alert -->
                    <div class="alert alert-info">
                        <i class="feather-info"></i>
                        <span>Enter Annual CTC - All components will be auto-calculated. You can manually adjust any value.</span>
                    </div>

                    <!-- Employee Selection Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Select Employee <span class="required">*</span>
                                <span class="tooltip-icon" title="Select the employee for payroll assignment">?</span>
                            </label>
                            <select name="user_id" id="user_id"
                                class="form-control form-select @error('user_id') is-invalid @enderror" required>
                                <option value="">Choose Employee</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}"
                                        {{ old('user_id', $selectedUser->id ?? '') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} ({{ $user->employee_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payroll Template</label>
                            <select name="payroll_master_id" id="payroll_master_id"
                                class="form-control form-select @error('payroll_master_id') is-invalid @enderror">
                                <option value="">Select Template</option>
                                @foreach ($payrollMasters as $master)
                                    <option value="{{ $master->id }}" 
                                        data-hra="{{ $master->hra }}"
                                        data-conveyence="{{ $master->conveyence }}"
                                        data-medical="{{ $master->medical_allowance }}"
                                        data-children="{{ $master->children_allowance }}"
                                        data-post="{{ $master->post_allowance }}"
                                        data-lta="{{ $master->leave_travel_allowance }}"
                                        data-incentive="{{ $master->monthly_incentive }}"
                                        data-pf="{{ $master->provident_fund }}"
                                        data-employer-pf="{{ $master->employer_provident_fund }}"
                                        data-esi="{{ $master->esi }}"
                                        data-employer-esi="{{ $master->employer_esi }}"
                                        data-pt="{{ $master->pt }}"
                                        {{ old('payroll_master_id') == $master->id ? 'selected' : '' }}>
                                        {{ $master->name }} ({{ $master->payroll_code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="help-text">Select template to auto-calculate percentages</small>
                            @error('payroll_master_id')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- CTC and Dates Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Annual CTC (₹) <span class="required">*</span></label>
                            <input type="number" step="1000" name="annual_ctc" id="annual_ctc"
                                class="form-control @error('annual_ctc') is-invalid @enderror"
                                value="{{ old('annual_ctc') }}" placeholder="e.g., 600000" required>
                            <small class="help-text">Enter total yearly CTC</small>
                            @error('annual_ctc')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Effective From <span class="required">*</span></label>
                            <input type="date" name="effective_from" id="effective_from"
                                class="form-control @error('effective_from') is-invalid @enderror"
                                value="{{ old('effective_from', date('Y-m-d')) }}" required>
                            @error('effective_from')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Effective To</label>
                            <input type="date" name="effective_to" id="effective_to"
                                class="form-control @error('effective_to') is-invalid @enderror"
                                value="{{ old('effective_to') }}">
                            @error('effective_to')
                                <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Current Payroll Checkbox -->
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="is_current" id="is_current" class="form-check-input"
                                    value="1" {{ old('is_current', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_current">Set as Current Payroll</label>
                            </div>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <div class="view-toggle">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn active" id="monthlyViewBtn">Monthly</button>
                            <button type="button" class="btn" id="yearlyViewBtn">Yearly</button>
                        </div>
                    </div>

                    <!-- Monthly View Breakdown -->
                    <div id="monthlyView">
                        <!-- Basic Salary Card -->
                        <div class="basic-salary-card">
                            <div class="basic-salary-label">
                                <i class="fas fa-rupee-sign"></i>
                                Basic Salary (Monthly)
                            </div>
                            <div class="basic-salary-input">
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="basic_salary" id="basic_salary"
                                        class="form-control" value="0" placeholder="Auto-calculated" readonly>
                                </div>
                                <small class="help-text">Auto-calculated based on CTC and allowances</small>
                            </div>
                        </div>

                        <!-- Earnings Section -->
                        <div class="component-card">
                            <div class="component-header">
                                <h6 class="component-title">
                                    <i class="feather-trending-up"></i>
                                    Earnings (Monthly)
                                </h6>
                                <span class="component-badge">You can edit</span>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">HRA</span>
                                        <input type="number" step="0.01" name="hra" id="hra"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Conveyance</span>
                                        <input type="number" step="0.01" name="conveyence" id="conveyence"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Medical</span>
                                        <input type="number" step="0.01" name="medical_allowance" id="medical_allowance"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Children</span>
                                        <input type="number" step="0.01" name="children_allowance"
                                            id="children_allowance" class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Post</span>
                                        <input type="number" step="0.01" name="post_allowance" id="post_allowance"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">LTA</span>
                                        <input type="number" step="0.01" name="leave_travel_allowance" id="lta"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Incentive</span>
                                        <input type="number" step="0.01" name="monthly_incentive" id="incentive"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Special</span>
                                        <input type="number" step="0.01" name="special_allowance" id="special"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deductions Section -->
                        <div class="component-card">
                            <div class="component-header">
                                <h6 class="component-title">
                                    <i class="feather-trending-down"></i>
                                    Deductions (Monthly)
                                </h6>
                                <span class="component-badge">You can edit</span>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">Employee PF</span>
                                        <input type="number" step="0.01" name="provident_fund" id="pf"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employer PF</span>
                                        <input type="number" step="0.01" name="employer_provident_fund" id="employer_pf"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employee ESI</span>
                                        <input type="number" step="0.01" name="esi" id="esi"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employer ESI</span>
                                        <input type="number" step="0.01" name="employer_esi" id="employer_esi"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">PT</span>
                                        <input type="number" step="0.01" name="professional_tax" id="pt"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">TDS</span>
                                        <input type="number" step="0.01" name="tds" id="tds"
                                            class="form-control" value="0" onkeyup="calculateAll()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Yearly View (Hidden by default) -->
                    <div id="yearlyView" style="display: none;">
                        <!-- Basic Salary Card -->
                        <div class="basic-salary-card">
                            <div class="basic-salary-label">
                                <i class="fas fa-rupee-sign"></i>
                                Basic Salary (Yearly)
                            </div>
                            <div class="basic-salary-input">
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" id="yearly_basic_display"
                                        class="form-control" value="0" placeholder="Auto-calculated" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Earnings Section (Yearly) -->
                        <div class="component-card">
                            <div class="component-header">
                                <h6 class="component-title">
                                    <i class="feather-trending-up"></i>
                                    Earnings (Yearly)
                                </h6>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">HRA</span>
                                        <input type="number" step="0.01" id="yearly_hra_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Conveyance</span>
                                        <input type="number" step="0.01" id="yearly_conveyance_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Medical</span>
                                        <input type="number" step="0.01" id="yearly_medical_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Children</span>
                                        <input type="number" step="0.01" id="yearly_children_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Post</span>
                                        <input type="number" step="0.01" id="yearly_post_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">LTA</span>
                                        <input type="number" step="0.01" id="yearly_lta_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Incentive</span>
                                        <input type="number" step="0.01" id="yearly_incentive_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Special</span>
                                        <input type="number" step="0.01" id="yearly_special_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deductions Section (Yearly) -->
                        <div class="component-card">
                            <div class="component-header">
                                <h6 class="component-title">
                                    <i class="feather-trending-down"></i>
                                    Deductions (Yearly)
                                </h6>
                            </div>
                            <div class="component-body">
                                <div class="component-row">
                                    <div class="input-group">
                                        <span class="input-group-text">Employee PF</span>
                                        <input type="number" step="0.01" id="yearly_pf_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employer PF</span>
                                        <input type="number" step="0.01" id="yearly_employer_pf_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employee ESI</span>
                                        <input type="number" step="0.01" id="yearly_esi_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">Employer ESI</span>
                                        <input type="number" step="0.01" id="yearly_employer_esi_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">PT</span>
                                        <input type="number" step="0.01" id="yearly_pt_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text">TDS</span>
                                        <input type="number" step="0.01" id="yearly_tds_display"
                                            class="form-control" value="0" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation Summary -->
                    <div class="summary-section">
                        <div class="summary-title">
                            <i class="feather-pie-chart"></i>
                            Salary Summary
                        </div>
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Gross Salary</div>
                                <div class="summary-value">
                                    <span id="gross_display">0.00</span>
                                    <small>₹/month</small>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Total Deductions</div>
                                <div class="summary-value">
                                    <span id="deductions_display">0.00</span>
                                    <small>₹/month</small>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Net Salary</div>
                                <div class="summary-value">
                                    <span id="net_display">0.00</span>
                                    <small>₹/month</small>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Annual CTC</div>
                                <div class="summary-value">
                                    <span id="ctc_display">0.00</span>
                                    <small>₹/year</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group mt-4">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="3"
                            placeholder="Enter any additional notes or comments...">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="form-card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save"></i>
                        Assign Payroll
                    </button>
                    <a href="{{ route('employee-payrolls.index') }}" class="btn btn-outline-secondary">
                        <i class="feather-x"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Initialize with zeros
            calculateAll();

            // Set default effective date
            if (!$('#effective_from').val()) {
                $('#effective_from').val(new Date().toISOString().slice(0, 10));
            }

            // View toggle
            $('#monthlyViewBtn').click(function() {
                $(this).addClass('active');
                $('#yearlyViewBtn').removeClass('active');
                $('#monthlyView').show();
                $('#yearlyView').hide();
            });

            $('#yearlyViewBtn').click(function() {
                $(this).addClass('active');
                $('#monthlyViewBtn').removeClass('active');
                $('#yearlyView').show();
                $('#monthlyView').hide();
            });

            // Load master data when payroll master is selected
            $('#payroll_master_id').change(function() {
                let selected = $(this).find(':selected');
                let annualCTC = parseFloat($('#annual_ctc').val()) || 0;

                if (selected.val() && annualCTC > 0) {
                    let monthlyCTC = annualCTC / 12;
                    
                    // Calculate total allowance percentage
                    let totalAllowancePercent = 
                        (parseFloat(selected.data('hra')) || 0) +
                        (parseFloat(selected.data('conveyence')) || 0) +
                        (parseFloat(selected.data('medical')) || 0) +
                        (parseFloat(selected.data('children')) || 0) +
                        (parseFloat(selected.data('post')) || 0) +
                        (parseFloat(selected.data('lta')) || 0) +
                        (parseFloat(selected.data('incentive')) || 0);
                    
                    // Calculate basic (where basic + allowances = monthlyCTC)
                    let basic = totalAllowancePercent > 0 
                        ? monthlyCTC / (1 + (totalAllowancePercent / 100))
                        : monthlyCTC;

                    $('#basic_salary').val(basic.toFixed(2));

                    // Calculate earnings based on master percentages
                    $('#hra').val(selected.data('hra') ? (basic * selected.data('hra') / 100).toFixed(2) : '0');
                    $('#conveyence').val(selected.data('conveyence') ? (basic * selected.data('conveyence') / 100).toFixed(2) : '0');
                    $('#medical_allowance').val(selected.data('medical') ? (basic * selected.data('medical') / 100).toFixed(2) : '0');
                    $('#children_allowance').val(selected.data('children') ? (basic * selected.data('children') / 100).toFixed(2) : '0');
                    $('#post_allowance').val(selected.data('post') ? (basic * selected.data('post') / 100).toFixed(2) : '0');
                    $('#lta').val(selected.data('lta') ? (basic * selected.data('lta') / 100).toFixed(2) : '0');
                    $('#incentive').val(selected.data('incentive') ? (basic * selected.data('incentive') / 100).toFixed(2) : '0');

                    // Calculate deductions based on master percentages
                    $('#pf').val(selected.data('pf') ? (basic * selected.data('pf') / 100).toFixed(2) : '0');
                    $('#employer_pf').val(selected.data('employer-pf') ? (basic * selected.data('employer-pf') / 100).toFixed(2) : '0');
                    $('#esi').val(selected.data('esi') ? (basic * selected.data('esi') / 100).toFixed(2) : '0');
                    $('#employer_esi').val(selected.data('employer-esi') ? (basic * selected.data('employer-esi') / 100).toFixed(2) : '0');
                    $('#pt').val(selected.data('pt') ? (basic * selected.data('pt') / 100).toFixed(2) : '0');
                }

                calculateAll();
            });

            // Calculate when Annual CTC changes
            $('#annual_ctc').on('input', function() {
                if ($('#payroll_master_id').val()) {
                    $('#payroll_master_id').trigger('change');
                } else {
                    calculateAll();
                }
            });

            // Calculate when any field changes
            $('input').on('keyup change', function() {
                calculateAll();
            });
        });

        function calculateAll() {
            let annualCTC = parseFloat($('#annual_ctc').val()) || 0;
            let monthlyCTC = annualCTC / 12;
            
            // Get all earnings
            let earnings = {
                hra: parseFloat($('#hra').val()) || 0,
                conveyence: parseFloat($('#conveyence').val()) || 0,
                medical: parseFloat($('#medical_allowance').val()) || 0,
                children: parseFloat($('#children_allowance').val()) || 0,
                post: parseFloat($('#post_allowance').val()) || 0,
                lta: parseFloat($('#lta').val()) || 0,
                incentive: parseFloat($('#incentive').val()) || 0,
                special: parseFloat($('#special').val()) || 0
            };

            // Get all deductions
            let deductions = {
                pf: parseFloat($('#pf').val()) || 0,
                employer_pf: parseFloat($('#employer_pf').val()) || 0,
                esi: parseFloat($('#esi').val()) || 0,
                employer_esi: parseFloat($('#employer_esi').val()) || 0,
                pt: parseFloat($('#pt').val()) || 0,
                tds: parseFloat($('#tds').val()) || 0
            };

            // Calculate totals
            let grossMonthly = earnings.hra + earnings.conveyence + earnings.medical + 
                              earnings.children + earnings.post + earnings.lta + 
                              earnings.incentive + earnings.special;
            
            // Update basic salary (if not set by master)
            let basic = parseFloat($('#basic_salary').val()) || 0;
            if (basic === 0) {
                basic = monthlyCTC - grossMonthly;
                $('#basic_salary').val(basic.toFixed(2));
            }

            // Recalculate gross with basic
            grossMonthly = basic + earnings.hra + earnings.conveyence + earnings.medical + 
                          earnings.children + earnings.post + earnings.lta + 
                          earnings.incentive + earnings.special;

            let totalMonthlyDeductions = deductions.pf + deductions.esi + deductions.pt + deductions.tds;
            let netMonthly = grossMonthly - totalMonthlyDeductions;
            let monthlyCTC_calculated = grossMonthly + deductions.employer_pf + deductions.employer_esi;

            // Update monthly display
            $('#gross_display').text(grossMonthly.toFixed(2));
            $('#deductions_display').text(totalMonthlyDeductions.toFixed(2));
            $('#net_display').text(netMonthly.toFixed(2));
            $('#ctc_display').text((monthlyCTC_calculated * 12).toFixed(2));

            // Update yearly display
            $('#yearly_basic_display').val((basic * 12).toFixed(2));
            $('#yearly_hra_display').val((earnings.hra * 12).toFixed(2));
            $('#yearly_conveyance_display').val((earnings.conveyence * 12).toFixed(2));
            $('#yearly_medical_display').val((earnings.medical * 12).toFixed(2));
            $('#yearly_children_display').val((earnings.children * 12).toFixed(2));
            $('#yearly_post_display').val((earnings.post * 12).toFixed(2));
            $('#yearly_lta_display').val((earnings.lta * 12).toFixed(2));
            $('#yearly_incentive_display').val((earnings.incentive * 12).toFixed(2));
            $('#yearly_special_display').val((earnings.special * 12).toFixed(2));
            
            $('#yearly_pf_display').val((deductions.pf * 12).toFixed(2));
            $('#yearly_employer_pf_display').val((deductions.employer_pf * 12).toFixed(2));
            $('#yearly_esi_display').val((deductions.esi * 12).toFixed(2));
            $('#yearly_employer_esi_display').val((deductions.employer_esi * 12).toFixed(2));
            $('#yearly_pt_display').val((deductions.pt * 12).toFixed(2));
            $('#yearly_tds_display').val((deductions.tds * 12).toFixed(2));
        }
    </script>
@endsection