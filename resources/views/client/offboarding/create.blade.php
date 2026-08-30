{{-- resources/views/client/offboarding/create.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== EMPLOYEE DROPDOWN STYLES ==================== */
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-details {
            line-height: 1.4;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        .employee-email {
            font-size: 11px;
            color: #64748b;
        }

        .employee-initials {
            width: 36px;
            height: 36px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-initials-sm {
            width: 32px;
            height: 32px;
            background: #4f46e5;
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .employee-detail-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        /* Custom Employee Dropdown */
        .custom-employee-dropdown {
            width: 100%;
        }

        .custom-employee-dropdown .btn {
            height: 45px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #1e293b;
            font-size: 14px;
            padding: 0 15px;
            width: 100%;
            text-align: left;
        }

        .custom-employee-dropdown .btn:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .custom-employee-dropdown .btn:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .custom-employee-dropdown .dropdown-menu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px;
            max-height: 350px;
            overflow-y: auto;
            min-width: 320px;
            width: 100%;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .custom-employee-dropdown .dropdown-item {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 4px;
            cursor: pointer;
        }

        .custom-employee-dropdown .dropdown-item:hover {
            background: #f1f5f9;
        }

        .custom-employee-dropdown .dropdown-item.active {
            background: #eef2ff;
            color: #4f46e5;
        }

        .custom-employee-dropdown .dropdown-item.active .text-muted {
            color: #4f46e5 !important;
            opacity: 0.8;
        }

        /* ==================== FORM STYLES ==================== */
        .form-section {
            background: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #eef2f6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4f46e5;
            display: inline-block;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
            color: #334155;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 3px;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 8px 12px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        @media (max-width: 768px) {


            .custom-employee-dropdown .dropdown-menu {
                min-width: 280px;
            }
        }

        .btn {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #4f46e5;
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-secondary {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .alert-info {
            background: #eef2ff;
            border-left: 4px solid #4f46e5;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .text-danger {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }

        .required-field::after {
            content: " *";
            color: #ef4444;
        }

        .info-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }

        .employee-self-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            background: #f1f5f9;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-5">Offboarding Management</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('offboarding.index') }}">Offboarding</a></li>
                <li class="breadcrumb-item active">Create Request</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <form action="{{ route('offboarding.store') }}" method="POST">
            @csrf

            <!-- Employee Selection Section -->
            <div class="form-section">
                <h5 class="section-title">Employee Information</h5>

                @php
                    $userRole = auth()->user()->role ?? 'employee';
                    $isAdminOrHR = in_array($userRole, ['admin', 'hr']);
                @endphp

                @if ($isAdminOrHR)
                    {{-- Admin/HR: Can select any employee --}}
                    <div class="custom-employee-dropdown">
                        <input type="hidden" name="employee_id" id="selected_employee_id"
                            value="{{ old('employee_id', $selectedEmployee->id ?? '') }}">
                        <button class="btn btn-light w-100 d-flex align-items-center justify-content-between" type="button"
                            id="employeeDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-flex align-items-center gap-2" id="selectedEmployeeDisplay">
                                @php
                                    $displayEmployee = $selectedEmployee ?? null;
                                @endphp
                                @if ($displayEmployee)
                                    <span
                                        class="employee-initials-sm">{{ strtoupper(substr($displayEmployee->name, 0, 2)) }}</span>
                                    <span class="employee-name">{{ $displayEmployee->name }}</span>
                                    <small class="text-muted">({{ $displayEmployee->employee_id ?? 'N/A' }})</small>
                                @else
                                    <span class="text-muted">Select Employee</span>
                                @endif
                            </span>
                            <i class="feather-chevron-down text-muted"></i>
                        </button>

                        <ul class="dropdown-menu w-100" aria-labelledby="employeeDropdownBtn" id="employeeDropdownMenu">
                            <li>
                                <a class="dropdown-item {{ !$selectedEmployee ? 'active' : '' }}" href="#"
                                    data-employee-id="" data-employee-name="Select Employee" data-employee-initials="">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="employee-initials-sm">--</span>
                                        <div class="d-flex flex-column">
                                            <span>Select Employee</span>
                                            <small class="text-muted">Choose an employee to offboard</small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            @foreach ($employees as $employee)
                                @php
                                    $initials = strtoupper(substr($employee->name, 0, 2));
                                @endphp
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 {{ $selectedEmployee && $selectedEmployee->id == $employee->id ? 'active' : '' }}"
                                        href="#" data-employee-id="{{ $employee->id }}"
                                        data-employee-name="{{ $employee->name }}"
                                        data-employee-email="{{ $employee->email }}"
                                        data-employee-id-code="{{ $employee->employee_id ?? 'N/A' }}"
                                        data-employee-initials="{{ $initials }}">
                                        <span class="employee-initials-sm">{{ $initials }}</span>
                                        <div class="d-flex flex-column flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="employee-name">{{ $employee->name }}</span>
                                                <small class="text-muted">{{ $employee->employee_id ?? 'N/A' }}</small>
                                            </div>
                                            <small class="text-muted">{{ $employee->email }}</small>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="info-text mt-2">
                        <i class="feather-info"></i> As an Admin/HR, you can create offboarding requests for any employee.
                    </div>
                @elseif(auth()->user()->role === 'manager')
                    {{-- Manager: Can only request for themselves --}}
                    <input type="hidden" name="employee_id" value="{{ auth()->user()->id }}">
                    <div class="employee-self-card">
                        <span class="employee-initials">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ auth()->user()->name }}</strong>
                                    <small class="text-muted d-block">{{ auth()->user()->employee_id ?? 'N/A' }}</small>
                                </div>
                                <span class="badge bg-warning text-dark">You</span>
                            </div>
                            <small class="text-muted">{{ auth()->user()->email }}</small>
                        </div>
                    </div>
                    <div class="alert-warning mt-3 p-3 rounded">
                        <i class="feather-alert-triangle"></i>
                        <strong>Note:</strong> As a Manager, you can only initiate offboarding requests for yourself.
                        Please contact HR for team member offboarding requests.
                    </div>
                @else
                    {{-- Employee: Can only request for themselves --}}
                    <input type="hidden" name="employee_id" value="{{ auth()->user()->id }}">
                    <div class="employee-self-card">
                        <span class="employee-initials">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ auth()->user()->name }}</strong>
                                    <small class="text-muted d-block">{{ auth()->user()->employee_id ?? 'N/A' }}</small>
                                </div>
                                <span class="badge bg-primary">You</span>
                            </div>
                            <small class="text-muted">{{ auth()->user()->email }}</small>
                        </div>
                    </div>
                    <div class="alert-info mt-3 p-3 rounded">
                        <i class="feather-info"></i>
                        You are initiating an offboarding request for yourself. Your manager and HR will review this
                        request.
                    </div>
                @endif

                @error('employee_id')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Offboarding Details Section -->
            <div class="form-section">
                <h5 class="section-title">Offboarding Details</h5>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label required-field">Last Working Date</label>
                            <input type="date" name="last_working_date" class="form-control"
                                value="{{ old('last_working_date') }}" min="{{ date('Y-m-d', strtotime('+15 days')) }}"
                                required>
                            <small class="info-text">Minimum notice period: 15 days</small>
                            @error('last_working_date')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">Resignation Date</label>
                            <input type="date" name="resignation_date" class="form-control"
                                value="{{ old('resignation_date', date('Y-m-d')) }}">
                            <small class="info-text">Date when resignation was submitted</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label required-field">Reason for Leaving</label>
                            <select name="reason" class="form-select" required>
                                <option value="">Select Reason</option>
                                @foreach ($reasons as $key => $value)
                                    <option value="{{ $key }}" {{ old('reason') == $key ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                @endforeach
                            </select>
                            @error('reason')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    @if ($isAdminOrHR)
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Eligible for Rehire</label>
                                <select name="eligible_for_rehire" class="form-select">
                                    <option value="1" {{ old('eligible_for_rehire', 1) == 1 ? 'selected' : '' }}>Yes
                                    </option>
                                    <option value="0" {{ old('eligible_for_rehire') == 0 ? 'selected' : '' }}>No
                                    </option>
                                </select>
                            </div>
                        </div>
                    @endif
                </div>


                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Reason Details <span class="text-muted">(Optional)</span></label>
                            <textarea name="reason_detail" class="form-control" rows="3"
                                placeholder="Please provide additional details about the reason for leaving...">{{ old('reason_detail') }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Additional Feedback <span
                                    class="text-muted">(Optional)</span></label>
                            <textarea name="feedback" class="form-control" rows="3"
                                placeholder="Any feedback or suggestions for the company...">{{ old('feedback') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="d-flex justify-content-between">
                <a href="{{ route('offboarding.index') }}" class="btn btn-secondary">
                    <i class="feather-x"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="feather-save"></i> Submit Offboarding Request
                </button>
            </div>
        </form>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            @if ($isAdminOrHR)
                // Employee dropdown selection handler (only for Admin/HR)
                $('#employeeDropdownMenu .dropdown-item').on('click', function(e) {
                    e.preventDefault();

                    const employeeId = $(this).data('employee-id');
                    const employeeName = $(this).data('employee-name');
                    const employeeInitials = $(this).data('employee-initials');
                    const employeeIdCode = $(this).data('employee-id-code');
                    const employeeEmail = $(this).data('employee-email');

                    // Update hidden input
                    $('#selected_employee_id').val(employeeId || '');

                    // Update button display
                    const displaySpan = $('#selectedEmployeeDisplay');
                    if (employeeId) {
                        displaySpan.html(`
                            <span class="employee-initials-sm">${employeeInitials}</span>
                            <span class="employee-name">${employeeName}</span>
                            <small class="text-muted">(${employeeIdCode})</small>
                        `);
                    } else {
                        displaySpan.html(`<span class="text-muted">Select Employee</span>`);
                    }

                    // Update active state in dropdown
                    $('#employeeDropdownMenu .dropdown-item').removeClass('active');
                    $(this).addClass('active');
                });

                // Pre-select employee if coming from employee list
                @if ($selectedEmployee)
                    $('#selected_employee_id').val('{{ $selectedEmployee->id }}');
                @endif
            @endif

            // Form validation before submit
            $('#submitBtn').on('click', function(e) {
                const employeeId = $('#selected_employee_id').val();
                const lastWorkingDate = $('input[name="last_working_date"]').val();
                const reason = $('select[name="reason"]').val();

                let errors = [];

                @if ($isAdminOrHR)
                    if (!employeeId) {
                        errors.push('Please select an employee');
                    }
                @endif

                if (!lastWorkingDate) {
                    errors.push('Please select last working date');
                }

                if (!reason) {
                    errors.push('Please select a reason for leaving');
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    alert(errors.join('\n'));
                    return false;
                }
            });

            // Set minimum date for last working date (15 days from now)
            const today = new Date();
            const minDate = new Date(today);
            minDate.setDate(today.getDate() + 15);
            const minDateStr = minDate.toISOString().split('T')[0];
            $('input[name="last_working_date"]').attr('min', minDateStr);
        });
    </script>
@endsection
