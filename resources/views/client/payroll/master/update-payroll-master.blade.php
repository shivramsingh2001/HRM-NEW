@extends('client.layout.master')

@section('style')
    <style>
        .translate-middle {
            transform: translate(-50%, 0%) !important;
        }

        .error-message {
            color: #dc3545;
            font-size: 80%;
            margin-top: 0.25rem;
            width: 100%;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .is-invalid:focus {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        .code-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .code-badge i {
            font-size: 14px;
        }
        
        /* New styles for calculation type card */
        .calculation-type-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 10px 0 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .calculation-type-card h6 {
            margin-bottom: 15px;
            color: #495057;
            border-left: 4px solid #667eea;
            padding-left: 12px;
        }
        
        .info-text {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .required-field::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }
        
        .badge-info {
            background: #e7f3ff;
            color: #0066cc;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Payroll Master Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('payroll-masters.index') }}">Payroll Master</a></li>
                <li class="breadcrumb-item">Edit Payroll Master</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row">
            <div class="col-12">
                <div class="card invoice-container">
                    <div class="card-header">
                        <h5>Edit Payroll Master:
                            <div class="code-badge">
                                <i class="feather-tag"></i>
                                {{ $payrollMaster->payroll_code ?? 'N/A' }}
                            </div>
                        </h5>
                    </div>

                    <!-- Display success/error messages -->
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Display validation errors summary -->
                    @if ($errors->any())
                        <div class="alert alert-danger m-3">
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('payroll-masters.update', $payrollMaster->id) }}" method="POST"
                        id="payrollMasterForm">
                        @csrf
                        @method('PUT')

                        <div class="card-body p-0">
                            <div class="px-4 pt-4">
                                <div class="row">
                                    <div class="form-group mb-3 mb-md-0 col-md-12">
                                        <label class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="name_of_master"
                                            class="form-control @error('name') is-invalid @enderror"
                                            placeholder="Enter Name of Master"
                                            value="{{ old('name', $payrollMaster->name) }}">
                                        @error('name')
                                            <div class="error-message">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- ✅ NEW SECTION: Payroll Calculation Settings -->
                            <hr class="border-dashed">
                            <div class="px-4">
                                <div class="calculation-type-card">
                                    <h6 class="fw-bold mb-3">📊 Payroll Calculation Settings:</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label required-field">Payroll Calculation Type</label>
                                                <select name="payroll_calculation_type" id="payroll_calculation_type" 
                                                    class="form-control @error('payroll_calculation_type') is-invalid @enderror" required>
                                                    <option value="">Select Calculation Type</option>
                                                    <option value="day_based" {{ old('payroll_calculation_type', $payrollMaster->payroll_calculation_type) == 'day_based' ? 'selected' : '' }}>
                                                        Day Based - Salary calculated based on working days
                                                    </option>
                                                    <option value="hour_based" {{ old('payroll_calculation_type', $payrollMaster->payroll_calculation_type) == 'hour_based' ? 'selected' : '' }}>
                                                        Hour Based - Salary calculated based on working hours
                                                    </option>
                                                </select>
                                                @error('payroll_calculation_type')
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                                <div class="info-text">
                                                    <i class="feather-info"></i> 
                                                    Select how the salary should be calculated for employees under this payroll master.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6" id="working_hours_div" 
                                            style="{{ old('payroll_calculation_type', $payrollMaster->payroll_calculation_type) == 'hour_based' ? '' : 'display: none;' }}">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Working Hours Per Day</label>
                                                <input type="number" step="0.5" name="working_hours_per_day" id="working_hours_per_day"
                                                    class="form-control @error('working_hours_per_day') is-invalid @enderror"
                                                    placeholder="Enter working hours per day" 
                                                    value="{{ old('working_hours_per_day', $payrollMaster->working_hours_per_day ?? 8.00) }}" 
                                                    min="0" max="24">
                                                @error('working_hours_per_day')
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                                <div class="info-text">
                                                    <i class="feather-clock"></i>
                                                    Standard working hours per day (Default: 8 hours). Required for hourly calculation.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Overtime Rate Divisor</label>
                                                <select name="ot_rate_divisor_mode" id="ot_rate_divisor_mode"
                                                    class="form-control @error('ot_rate_divisor_mode') is-invalid @enderror">
                                                    <option value="calendar_days" {{ old('ot_rate_divisor_mode', $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days') == 'calendar_days' ? 'selected' : '' }}>
                                                        Calendar Days in Month (Default)
                                                    </option>
                                                    <option value="fixed_working_days" {{ old('ot_rate_divisor_mode', $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days') == 'fixed_working_days' ? 'selected' : '' }}>
                                                        Fixed Working Days
                                                    </option>
                                                </select>
                                                @error('ot_rate_divisor_mode')
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                                <div class="info-text">
                                                    <i class="feather-info"></i>
                                                    Controls how the day-based overtime hourly rate is derived from basic salary. Leave as
                                                    Calendar Days unless you specifically want a fixed working-days convention.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="ot_fixed_working_days_div"
                                            style="{{ old('ot_rate_divisor_mode', $payrollMaster->ot_rate_divisor_mode ?? 'calendar_days') == 'fixed_working_days' ? '' : 'display: none;' }}">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Fixed Working Days</label>
                                                <input type="number" step="1" name="ot_fixed_working_days" id="ot_fixed_working_days"
                                                    class="form-control @error('ot_fixed_working_days') is-invalid @enderror"
                                                    placeholder="Enter fixed working days"
                                                    value="{{ old('ot_fixed_working_days', $payrollMaster->ot_fixed_working_days ?? 26) }}" min="1" max="31">
                                                @error('ot_fixed_working_days')
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                                <div class="info-text">
                                                    <i class="feather-calendar"></i>
                                                    Used as the divisor instead of calendar days (commonly 26).
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Hourly Rate Applied</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" step="0.01" name="hourly_rate_applied" id="hourly_rate_applied"
                                                        class="form-control @error('hourly_rate_applied') is-invalid @enderror"
                                                        placeholder="Enter hourly rate" 
                                                        value="{{ old('hourly_rate_applied', $payrollMaster->hourly_rate_applied) }}" 
                                                        min="0">
                                                </div>
                                                @error('hourly_rate_applied')
                                                    <div class="error-message">{{ $message }}</div>
                                                @enderror
                                                <div class="info-text">
                                                    <i class="feather-dollar-sign"></i> 
                                                    This rate will be used for overtime calculations and hourly-based payroll.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label class="form-label">Current Calculation Summary</label>
                                                <div class="p-2 bg-white rounded">
                                                    @php
                                                        $calcType = old('payroll_calculation_type', $payrollMaster->payroll_calculation_type);
                                                    @endphp
                                                    @if($calcType == 'day_based')
                                                        <span class="badge-info p-2 d-inline-block">
                                                            <i class="feather-calendar"></i> Day-based calculation active
                                                        </span>
                                                    @elseif($calcType == 'hour_based')
                                                        <span class="badge-info p-2 d-inline-block">
                                                            <i class="feather-clock"></i> Hour-based calculation active
                                                        </span>
                                                        @if($payrollMaster->working_hours_per_day)
                                                            <small class="d-block mt-1">Working hours: {{ $payrollMaster->working_hours_per_day }} hrs/day</small>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">No calculation type selected yet</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> --}}

                            <hr class="border-dashed">

                            <div class="row px-4 justify-content-between">
                                <div class="col-lg-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold">Earnings:</h6>
                                    </div>
                                    <table class="table table-bordered" id="earnings_table">
                                        <thead>
                                            <tr class="text-center">
                                                <th>Component</th>
                                                <th>Amount (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>HRA</td>
                                                <td>
                                                    <input type="number" step="0.01" name="hra" id="hra_percent"
                                                        class="form-control @error('hra') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)" placeholder="Enter HRA %"
                                                        value="{{ old('hra', $payrollMaster->hra) }}">
                                                    @error('hra')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Conveyence</td>
                                                <td>
                                                    <input type="number" step="0.01" name="conveyence"
                                                        id="conveyence_percent"
                                                        class="form-control @error('conveyence') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)" placeholder="Enter Conveyence %"
                                                        value="{{ old('conveyence', $payrollMaster->conveyence) }}">
                                                    @error('conveyence')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Medical Allowance</td>
                                                <td>
                                                    <input type="number" step="0.01" name="medical_allowance"
                                                        id="medical_allowance_percent"
                                                        class="form-control @error('medical_allowance') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Medical Allowance %"
                                                        value="{{ old('medical_allowance', $payrollMaster->medical_allowance) }}">
                                                    @error('medical_allowance')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Children Allowance</td>
                                                <td>
                                                    <input type="number" step="0.01" name="children_allowance"
                                                        id="children_allowance_percent"
                                                        class="form-control @error('children_allowance') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Children Allowance %"
                                                        value="{{ old('children_allowance', $payrollMaster->children_allowance) }}">
                                                    @error('children_allowance')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Post Allowance</td>
                                                <td>
                                                    <input type="number" step="0.01" name="post_allowance"
                                                        id="post_allowance_percent"
                                                        class="form-control @error('post_allowance') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Post Allowance %"
                                                        value="{{ old('post_allowance', $payrollMaster->post_allowance) }}">
                                                    @error('post_allowance')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Leave Travel Allowance</td>
                                                <td>
                                                    <input type="number" step="0.01" name="leave_travel_allowance"
                                                        id="leave_travel_allowance_percent"
                                                        class="form-control @error('leave_travel_allowance') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Leave Travel Allowance %"
                                                        value="{{ old('leave_travel_allowance', $payrollMaster->leave_travel_allowance) }}">
                                                    @error('leave_travel_allowance')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Monthly Incentive</td>
                                                <td>
                                                    <input type="number" step="0.01" name="monthly_incentive"
                                                        id="monthly_incentive_percent"
                                                        class="form-control @error('monthly_incentive') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Monthly Incentive %"
                                                        value="{{ old('monthly_incentive', $payrollMaster->monthly_incentive) }}">
                                                    @error('monthly_incentive')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="col-lg-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold">Deductions:</h6>
                                    </div>
                                    <table class="table table-bordered" id="deductions_table">
                                        <thead>
                                            <tr class="text-center">
                                                <th>Component</th>
                                                <th>Amount (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Provident Fund</td>
                                                <td>
                                                    <input type="number" step="0.01" name="provident_fund"
                                                        id="provident_funds_percent"
                                                        class="form-control @error('provident_fund') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Provident Fund %"
                                                        value="{{ old('provident_fund', $payrollMaster->provident_fund) }}">
                                                    @error('provident_fund')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Employer Provident Fund</td>
                                                <td>
                                                    <input type="number" step="0.01" name="employer_provident_fund"
                                                        id="employer_provident_funds_percent"
                                                        class="form-control @error('employer_provident_fund') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Employer Provident Fund %"
                                                        value="{{ old('employer_provident_fund', $payrollMaster->employer_provident_fund) }}">
                                                    @error('employer_provident_fund')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>ESI</td>
                                                <td>
                                                    <input type="number" step="0.01" name="esi" id="esi_percent"
                                                        class="form-control @error('esi') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)" placeholder="Enter ESI %"
                                                        value="{{ old('esi', $payrollMaster->esi) }}">
                                                    @error('esi')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Employer ESI</td>
                                                <td>
                                                    <input type="number" step="0.01" name="employer_esi"
                                                        id="employer_esi_percent"
                                                        class="form-control @error('employer_esi') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)"
                                                        placeholder="Enter Employer ESI %"
                                                        value="{{ old('employer_esi', $payrollMaster->employer_esi) }}">
                                                    @error('employer_esi')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>PT</td>
                                                <td>
                                                    <input type="number" step="0.01" name="pt" id="pt_percent"
                                                        class="form-control @error('pt') is-invalid @enderror"
                                                        onkeyup="restrictPercentInput(this)"
                                                        onblur="restrictPercentInput(this)" placeholder="Enter PT %"
                                                        value="{{ old('pt', $payrollMaster->pt) }}">
                                                    @error('pt')
                                                        <div class="error-message">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <hr class="border-dashed">

                            <div class="px-4 pb-4">
                                <div class="form-group">
                                    <label for="description" class="form-label">Description:</label>
                                    <textarea rows="6" class="form-control @error('description') is-invalid @enderror" id="description"
                                        name="description" placeholder="Enter description...">{{ old('description', $payrollMaster->description) }}</textarea>
                                    @error('description')
                                        <div class="error-message">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="card-body pass-info">
                            <div class="buttons mb-5">
                                <button class="btn btn-lg btn-primary float-start" type="submit">
                                    <i class="feather-send me-2"></i>
                                    Update Payroll Master
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        let formModified = false;
        
        // Track form modifications
        document.querySelectorAll('#payrollMasterForm input, #payrollMasterForm select, #payrollMasterForm textarea').forEach(function(element) {
            element.addEventListener('change', function() {
                formModified = true;
            });
            element.addEventListener('keyup', function() {
                formModified = true;
            });
        });
        
        function restrictPercentInput(input) {
            let earning_table = document.getElementById('earnings_table');
            let deduction_table = document.getElementById('deductions_table');

            // Get the table containing the input
            let table_name = input.closest('table') ? input.closest('table').id : '';

            if (table_name === 'earnings_table') {
                let calculated_sum = 0;
                // Get all inputs in earnings table
                let earningInputs = earning_table.querySelectorAll('tbody input');
                for (let input of earningInputs) {
                    calculated_sum += parseFloat(input.value) || 0;
                }
                if (calculated_sum > 100) {
                    input.value = "0";
                    alert('Total earnings percentage cannot be greater than 100%!');

                    // Trigger error styling
                    input.classList.add('is-invalid');
                    let errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = 'Total earnings cannot exceed 100%';

                    // Remove any existing error message
                    let parent = input.closest('td');
                    let existingError = parent.querySelector('.error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                    parent.appendChild(errorDiv);
                } else {
                    input.classList.remove('is-invalid');
                    let parent = input.closest('td');
                    let existingError = parent.querySelector('.error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            } else if (table_name === 'deductions_table') {
                let calculated_sum = 0;
                // Get all inputs in deductions table
                let deductionInputs = deduction_table.querySelectorAll('tbody input');
                for (let input of deductionInputs) {
                    calculated_sum += parseFloat(input.value) || 0;
                }
                if (calculated_sum > 100) {
                    input.value = "0";
                    alert('Total deductions percentage cannot be greater than 100%!');

                    // Trigger error styling
                    input.classList.add('is-invalid');
                    let errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = 'Total deductions cannot exceed 100%';

                    // Remove any existing error message
                    let parent = input.closest('td');
                    let existingError = parent.querySelector('.error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                    parent.appendChild(errorDiv);
                } else {
                    input.classList.remove('is-invalid');
                    let parent = input.closest('td');
                    let existingError = parent.querySelector('.error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            }
        }

        // ✅ NEW: Toggle working hours field based on calculation type
        function toggleWorkingHoursField() {
            var calculationType = $('#payroll_calculation_type').val();
            if (calculationType === 'hour_based') {
                $('#working_hours_div').slideDown();
                $('#working_hours_per_day').prop('required', true);
                $('#working_hours_per_day').attr('required', 'required');
            } else {
                $('#working_hours_div').slideUp();
                $('#working_hours_per_day').prop('required', false);
                $('#working_hours_per_day').removeAttr('required');
                // Clear value if day_based is selected (optional)
                // $('#working_hours_per_day').val('');
            }
        }

        // Toggle the fixed-working-days field based on the OT rate divisor mode
        function toggleOtFixedWorkingDaysField() {
            var mode = $('#ot_rate_divisor_mode').val();
            if (mode === 'fixed_working_days') {
                $('#ot_fixed_working_days_div').slideDown();
            } else {
                $('#ot_fixed_working_days_div').slideUp();
            }
        }

        // Add form submit validation
        document.getElementById('payrollMasterForm').addEventListener('submit', function(e) {
            let earningsTotal = 0;
            let deductionsTotal = 0;
            
            // ✅ NEW: Validate calculation type is selected
            let calculationType = $('#payroll_calculation_type').val();
            if (!calculationType) {
                e.preventDefault();
                alert('Please select a payroll calculation type');
                $('#payroll_calculation_type').focus();
                return false;
            }
            
            // ✅ NEW: Validate working hours for hour-based calculation
            if (calculationType === 'hour_based') {
                let workingHours = $('#working_hours_per_day').val();
                if (!workingHours || workingHours <= 0) {
                    e.preventDefault();
                    alert('Please enter working hours per day for hour-based calculation');
                    $('#working_hours_per_day').focus();
                    return false;
                }
                if (workingHours > 24) {
                    e.preventDefault();
                    alert('Working hours per day cannot exceed 24 hours');
                    $('#working_hours_per_day').focus();
                    return false;
                }
            }

            // Calculate earnings total
            document.querySelectorAll('#earnings_table tbody input').forEach(input => {
                earningsTotal += parseFloat(input.value) || 0;
            });

            // Calculate deductions total
            document.querySelectorAll('#deductions_table tbody input').forEach(input => {
                deductionsTotal += parseFloat(input.value) || 0;
            });

            if (earningsTotal > 100) {
                e.preventDefault();
                alert('Total earnings cannot exceed 100%');
                return false;
            }

            if (deductionsTotal > 100) {
                e.preventDefault();
                alert('Total deductions cannot exceed 100%');
                return false;
            }

            // Reset form modified flag after successful submission
            formModified = false;
            return true;
        });

        $(document).ready(function() {
            // Toggle working hours field on page load
            toggleWorkingHoursField();
            toggleOtFixedWorkingDaysField();

            $('#ot_rate_divisor_mode').on('change', function() {
                toggleOtFixedWorkingDaysField();
                formModified = true;
            });

            // On calculation type change
            $('#payroll_calculation_type').on('change', function() {
                toggleWorkingHoursField();
                formModified = true;
                
                // Update placeholder for hourly rate
                if ($(this).val() === 'hour_based') {
                    $('#hourly_rate_applied').attr('placeholder', 'Enter hourly rate (recommended for hourly calculation)');
                } else {
                    $('#hourly_rate_applied').attr('placeholder', 'Enter hourly rate (optional)');
                }
            });
            
            // Validate hourly rate on blur
            $('#hourly_rate_applied').on('blur', function() {
                let calculationType = $('#payroll_calculation_type').val();
                let hourlyRate = $(this).val();
                
                if (calculationType === 'hour_based' && (!hourlyRate || hourlyRate <= 0)) {
                    $(this).addClass('is-invalid');
                    let parent = $(this).closest('.form-group');
                    if (parent.find('.error-message').length === 0) {
                        parent.append('<div class="error-message">Hourly rate is recommended for hour-based calculation</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).closest('.form-group').find('.error-message').remove();
                }
            });
            
            // Initial trigger for hourly rate placeholder
            if ($('#payroll_calculation_type').val() === 'hour_based') {
                $('#hourly_rate_applied').attr('placeholder', 'Enter hourly rate (recommended for hourly calculation)');
            }
        });

        window.addEventListener('beforeunload', function(e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
@endsection