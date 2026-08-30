@extends('client.layout.master')

@section('style')
    <style>
        /* Classic Design */
        .step-wizard {
            background: #f8fafc;
            padding: 20px 0;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 40px;
            position: relative;
            max-width: 900px;
            margin: 0 auto;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 60px;
            right: 60px;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: #fff;
            border: 2px solid #cbd5e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }

        .step-item.active .step-number {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }

        .step-item.completed .step-number {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }

        .step-label {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            letter-spacing: 0.3px;
        }

        .step-item.active .step-label {
            color: #0d6efd;
            font-weight: 600;
        }

        .step-item.completed .step-label {
            color: #10b981;
        }

        /* Form Sections */
        .form-section {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }

        .form-label .required {
            color: #ef4444;
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .invalid-feedback {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
        }

        /* Profile Image */
        .profile-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .profile-image-container {
            position: relative;
            width: 100px;
            height: 100px;
        }

        .profile-image-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 32px;
            background: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            border: 2px solid #fff;
        }

        .profile-image-overlay i {
            font-size: 16px;
        }

        .profile-help-text {
            font-size: 12px;
            color: #64748b;
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #0d6efd;
            color: #fff;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0b5ed7;
        }

        .btn-secondary {
            background: #fff;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e0;
        }

        .btn-success {
            background: #10b981;
            color: #fff;
        }

        .btn-success:hover {
            background: #0d9488;
        }

        .btn-light {
            background: #fff;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-light:hover {
            background: #f8fafc;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Step Content */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        /* Form Layout */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Document Upload */
        .file-input {
            padding: 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 100%;
        }

        .file-input::-webkit-file-upload-button {
            background: #f1f5f9;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            color: #334155;
            font-weight: 500;
            margin-right: 10px;
            cursor: pointer;
        }

        /* Alert */
        .alert-info {
            background: #e6f3ff;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
@endsection

@section('content-area')
    <!-- Page Header -->

    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                {{-- <h5 class="m-b-10">Apply Leave</h5> --}}
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('employee.index') }}">Employees</a></li>
                <li class="breadcrumb-item active">Add Employee</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        {{-- <div class="container-fluid"> --}}
        <!-- Main Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('employee.store.step') }}" method="post" enctype="multipart/form-data"
                    id="employeeForm" novalidate>
                    @csrf
                    <input type="hidden" name="current_step" id="current_step" value="1">
                    <input type="hidden" name="form_step" id="form_step" value="1">
                    <input type="hidden" name="employee_id" id="employee_id" value="{{ session('employee_id') }}">

                    <!-- Step Indicator -->
                    <div class="step-wizard">
                        <div class="step-indicator">
                            <div class="step-item active" data-step="1">
                                <div class="step-number">1</div>
                                <div class="step-label">Login Details</div>
                            </div>
                            <div class="step-item" data-step="2">
                                <div class="step-number">2</div>
                                <div class="step-label">Personal Info</div>
                            </div>
                            <div class="step-item" data-step="3">
                                <div class="step-number">3</div>
                                <div class="step-label">Job Details</div>
                            </div>
                            <div class="step-item" data-step="4">
                                <div class="step-number">4</div>
                                <div class="step-label">Location</div>
                            </div>
                            <div class="step-item" data-step="5">
                                <div class="step-number">5</div>
                                <div class="step-label">Bank Details</div>
                            </div>
                            <div class="step-item" data-step="6">
                                <div class="step-number">6</div>
                                <div class="step-label">Payroll</div>
                            </div>
                            <div class="step-item" data-step="7">
                                <div class="step-number">7</div>
                                <div class="step-label">Documents</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: Login Details -->
                    <div class="step-content active" id="step1">
                        <div class="form-section">
                            <h5 class="section-title">Login Credentials</h5>

                            <div class="profile-upload mb-4">
                                <div class="profile-image-container">
                                    <img src="{{ asset('assets/images/avatar/1.png') }}" class="profile-image-preview"
                                        id="profileImagePreview" alt="Profile">
                                    <div class="profile-image-overlay" id="profileUploadBtn">
                                        <i class="feather-camera"></i>
                                    </div>
                                    <input type="file" name="profile_photo" id="profilePhotoInput" class="d-none"
                                        accept="image/*">
                                </div>
                                <div class="profile-help-text">
                                    <p class="mb-1"><i class="feather-info me-1"></i> Upload profile picture</p>
                                    <small>Max size: 2MB (JPG, PNG)</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" name="name" placeholder="Enter full name"
                                    value="{{ old('name') }}" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Official Email <span class="required">*</span></label>
                                    <input type="email" class="form-control" name="email"
                                        placeholder="official@company.com" value="{{ old('email') }}" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Phone Number <span class="required">*</span></label>
                                    <input type="tel" class="form-control" name="contact"
                                        placeholder="10 digit mobile" value="{{ old('contact') }}" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Password <span class="required">*</span></label>
                                    <input type="password" class="form-control" name="password"
                                        placeholder="6-10 characters" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                                    <input type="password" class="form-control" name="password_confirmation"
                                        placeholder="Re-enter password" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="employee">Employee</option>
                                    <option value="manager">Manager</option>
                                    <option value="hr">HR</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Personal Information -->
                    <div class="step-content" id="step2">
                        <div class="form-section">
                            <h5 class="section-title">Personal Details</h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Personal Email</label>
                                    <input type="email" class="form-control" name="personal_email"
                                        placeholder="personal@email.com">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Alternate Phone</label>
                                    <input type="tel" class="form-control" name="alternate_phone"
                                        placeholder="Alternate number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Gender <span class="required">*</span></label>
                                    <select name="gender" class="form-control" required>
                                        <option value="">Select Gender</option>
                                        <option value="m">Male</option>
                                        <option value="f">Female</option>
                                        <option value="o">Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Date of Birth <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="dob" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Blood Group</label>
                                    <input type="text" class="form-control" name="blood_group"
                                        placeholder="e.g., O+">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="single">Single</option>
                                        <option value="married">Married</option>
                                        <option value="divorced">Divorced</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Father's Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="father_name"
                                        placeholder="Father's name" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Mother's Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="mother_name"
                                        placeholder="Mother's name" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Aadhaar Number</label>
                                    <input type="text" class="form-control" name="aadhaar_no"
                                        placeholder="12 digit Aadhaar">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">PAN Number</label>
                                    <input type="text" class="form-control" name="pan_no" placeholder="PAN number">
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-group">
                                    <label class="form-label">Passport Number</label>
                                    <input type="text" class="form-control" name="passport_number"
                                        placeholder="Passport number">
                                </div>
                                <label class="form-label">Languages <span class="required">*</span></label>
                                <select class="form-control select2" name="language[]" multiple required>
                                    @foreach ($languages as $language)
                                        <option value="{{ $language->id }}">{{ $language->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">About</label>
                                <textarea class="form-control" name="about" rows="3" placeholder="Brief description"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Job Details -->
                    <div class="step-content" id="step3">
                        <div class="form-section">
                            <h5 class="section-title">Employment Details</h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Department <span class="required">*</span></label>
                                    <select name="department" class="form-control" required>
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Designation <span class="required">*</span></label>
                                    <select name="designation" class="form-control" required>
                                        <option value="">Select Designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Reporting Head <span class="required">*</span></label>
                                    <select name="reporting_head" class="form-control" required>
                                        <option value="">Select Reporting Head</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Employment Type</label>
                                    <select name="employment_type" class="form-control">
                                        <option value="full-time">Full-Time</option>
                                        <option value="part-time">Part-Time</option>
                                        <option value="contract">Contract</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Work Type <span class="required">*</span></label>
                                    <select name="type" class="form-control" required>
                                        <option value="office">Office</option>
                                        <option value="field">Field</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Office Branch <span class="required">*</span></label>
                                    <select name="branch" class="form-control" required>
                                        <option value="">Select Branch</option>
                                        <option value="0">All Branch</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Date of Joining</label>
                                    <input type="date" class="form-control" name="joining_date">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Leave Types Assigned <span class="required">*</span></label>
                                    <select name="leave_type_assigned[]" class="form-control select2" multiple required>
                                        <option value="">Select Leave Types</option>
                                        @foreach ($leave_types as $leave_type)
                                            <option value="{{ $leave_type->id }}" 
                                                {{ old('leave_type_assigned') && in_array($leave_type->id, old('leave_type_assigned')) ? 'selected' : '' }}>
                                                {{ $leave_type->name }} ({{ ucfirst($leave_type->credit_type) }}: {{ $leave_type->credit_value }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Select multiple leave types that this employee is eligible for</small>
                                    @error('leave_type_assigned')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- <div class="form-group">
                                    <label class="form-label">Status <span class="required">*</span></label>
                                    <select name="status" class="form-control" required>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div> --}}
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Location -->
                    <div class="step-content" id="step4">
                        <div class="form-section">
                            <h5 class="section-title">Address Information</h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Country</label>
                                    <select class="form-control" name="country" id="country">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->country_code }}">{{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <select class="form-control" name="state" id="state">
                                        <option value="">Select State</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <select class="form-control" name="city" id="city">
                                        <option value="">Select City</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Pin Code</label>
                                    <input type="text" class="form-control" name="pin_code"
                                        placeholder="6 digit pin code">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Permanent Address</label>
                                <textarea class="form-control" name="permanent_address" rows="2" placeholder="Full permanent address"></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Current Address</label>
                                <textarea class="form-control" name="current_address" rows="2" placeholder="Full current address"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Bank Details -->
                    <div class="step-content" id="step5">
                        <div class="form-section">
                            <h5 class="section-title">Bank Information</h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" name="bank_name" placeholder="Bank name">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" name="account_number"
                                        placeholder="Account number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control" name="ifsc" placeholder="IFSC code">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Branch Name</label>
                                    <input type="text" class="form-control" name="branch_name"
                                        placeholder="Branch name">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">UAN Number</label>
                                    <input type="text" class="form-control" name="uan_no" placeholder="UAN Number" value="{{ old('uan_no') }}">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">PF Number</label>
                                    <input type="text" class="form-control" name="pf_no"
                                        placeholder="PF number" value="{{ old('pf_no') }}">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ESIC Number</label>
                                    <input type="text" class="form-control" name="esic_no" placeholder="ESIC Number" value="{{ old('esic_no') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Payroll Information -->
                    <div class="step-content" id="step6">
                        <div class="form-section">
                            <h5 class="section-title">Payroll & CTC Details</h5>

                            <div class="alert-info mb-4">
                                <i class="feather-info"></i>
                                <span>Enter Annual CTC - All salary components will be auto-calculated. Basic salary will be
                                    calculated as the remaining amount after all allowances.</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Payroll Master <span class="required">*</span></label>
                                    <select name="payroll_master_id" id="payroll_master_id" class="form-control"
                                        required>
                                        <option value="" selected disabled>Select Payroll Structure</option>
                                        @foreach ($payrollMasters ?? [] as $master)
                                            <option value="{{ $master->id }}" data-hra="{{ $master->hra }}"
                                                data-conveyence="{{ $master->conveyence }}"
                                                data-medical-allowance="{{ $master->medical_allowance }}"
                                                data-children-allowance="{{ $master->children_allowance }}"
                                                data-post-allowance="{{ $master->post_allowance }}"
                                                data-leave-travel-allowance="{{ $master->leave_travel_allowance }}"
                                                data-monthly-incentive="{{ $master->monthly_incentive }}"
                                                data-provident-fund="{{ $master->provident_fund }}"
                                                data-employer-provident-fund="{{ $master->employer_provident_fund }}"
                                                data-esi="{{ $master->esi }}"
                                                data-employer-esi="{{ $master->employer_esi }}"
                                                data-pt="{{ $master->pt }}">
                                                {{ $master->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Select the payroll structure that defines allowance
                                        percentages</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Annual CTC (₹) <span class="required">*</span></label>
                                    <input type="number" step="1000" class="form-control" name="annual_ctc"
                                        id="annual_ctc" placeholder="e.g., 600000" >
                                    <small class="text-muted">Enter total yearly CTC (including all components)</small>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Effective From <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="salary_effective_date"
                                        id="salary_effective_date" required>
                                </div>
                            </div>

                            <!-- View Toggle -->
                            <div class="d-flex justify-content-end mb-3">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active"
                                        id="monthlyViewBtn">Monthly</button>
                                    <button type="button" class="btn btn-outline-primary"
                                        id="yearlyViewBtn">Yearly</button>
                                </div>
                            </div>

                            <!-- Salary Breakdown Preview -->
                            <div class="salary-breakdown mt-4 p-3 bg-light rounded">
                                <h6 class="mb-3">Salary Breakdown</h6>

                                <!-- Monthly View -->
                                <div id="monthlyView">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr class="table-primary">
                                                    <th colspan="2">Earnings (Monthly)</th>
                                                </tr>
                                                <tr>
                                                    <td>HRA:</td>
                                                    <td class="text-end"><span id="monthly_hra">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Conveyance Allowance:</td>
                                                    <td class="text-end"><span id="monthly_conveyance">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Medical Allowance:</td>
                                                    <td class="text-end"><span id="monthly_medical">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Children Allowance:</td>
                                                    <td class="text-end"><span id="monthly_children">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Post Allowance:</td>
                                                    <td class="text-end"><span id="monthly_post">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>LTA:</td>
                                                    <td class="text-end"><span id="monthly_lta">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Monthly Incentive:</td>
                                                    <td class="text-end"><span id="monthly_incentive">0.00</span></td>
                                                </tr>
                                                <tr class="table-info">
                                                    <td><strong>Total Allowances:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_total_allowances">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-success">
                                                    <td><strong>Basic Salary (Remaining):</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_basic">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-warning">
                                                    <td><strong>Gross Salary:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_gross">0.00</span></strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr class="table-danger">
                                                    <th colspan="2">Deductions (Monthly)</th>
                                                </tr>
                                                <tr>
                                                    <td>Employee PF (12% of Basic):</td>
                                                    <td class="text-end text-danger"><span id="monthly_pf">0.00</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Employee ESI:</td>
                                                    <td class="text-end text-danger"><span id="monthly_esi">0.00</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Professional Tax:</td>
                                                    <td class="text-end text-danger"><span id="monthly_pt">0.00</span>
                                                    </td>
                                                </tr>
                                                <tr class="table-danger">
                                                    <td><strong>Total Deductions:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_total_deductions">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-success">
                                                    <td><strong>Net Salary:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_net">0.00</span></strong></td>
                                                </tr>
                                            </table>

                                            <table class="table table-sm table-borderless mt-3">
                                                <tr class="table-secondary">
                                                    <th colspan="2">Employer Contributions</th>
                                                </tr>
                                                <tr>
                                                    <td>Employer PF:</td>
                                                    <td class="text-end"><span id="monthly_employer_pf">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Employer ESI:</td>
                                                    <td class="text-end"><span id="monthly_employer_esi">0.00</span></td>
                                                </tr>
                                                <tr class="table-info">
                                                    <td><strong>Total Monthly CTC:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="monthly_total_ctc">0.00</span></strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Yearly View (Initially Hidden) -->
                                <div id="yearlyView" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr class="table-primary">
                                                    <th colspan="2">Earnings (Yearly)</th>
                                                </tr>
                                                <tr>
                                                    <td>HRA:</td>
                                                    <td class="text-end"><span id="yearly_hra">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Conveyance Allowance:</td>
                                                    <td class="text-end"><span id="yearly_conveyance">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Medical Allowance:</td>
                                                    <td class="text-end"><span id="yearly_medical">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Children Allowance:</td>
                                                    <td class="text-end"><span id="yearly_children">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Post Allowance:</td>
                                                    <td class="text-end"><span id="yearly_post">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>LTA:</td>
                                                    <td class="text-end"><span id="yearly_lta">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Monthly Incentive:</td>
                                                    <td class="text-end"><span id="yearly_incentive">0.00</span></td>
                                                </tr>
                                                <tr class="table-info">
                                                    <td><strong>Total Allowances:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_total_allowances">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-success">
                                                    <td><strong>Basic Salary:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_basic">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-warning">
                                                    <td><strong>Gross Salary:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_gross">0.00</span></strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr class="table-danger">
                                                    <th colspan="2">Deductions (Yearly)</th>
                                                </tr>
                                                <tr>
                                                    <td>Employee PF:</td>
                                                    <td class="text-end text-danger"><span id="yearly_pf">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Employee ESI:</td>
                                                    <td class="text-end text-danger"><span id="yearly_esi">0.00</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Professional Tax:</td>
                                                    <td class="text-end text-danger"><span id="yearly_pt">0.00</span></td>
                                                </tr>
                                                <tr class="table-danger">
                                                    <td><strong>Total Deductions:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_total_deductions">0.00</span></strong></td>
                                                </tr>
                                                <tr class="table-success">
                                                    <td><strong>Net Salary:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_net">0.00</span></strong></td>
                                                </tr>
                                            </table>

                                            <table class="table table-sm table-borderless mt-3">
                                                <tr class="table-secondary">
                                                    <th colspan="2">Employer Contributions</th>
                                                </tr>
                                                <tr>
                                                    <td>Employer PF:</td>
                                                    <td class="text-end"><span id="yearly_employer_pf">0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Employer ESI:</td>
                                                    <td class="text-end"><span id="yearly_employer_esi">0.00</span></td>
                                                </tr>
                                                <tr class="table-info">
                                                    <td><strong>Total CTC:</strong></td>
                                                    <td class="text-end"><strong><span
                                                                id="yearly_total_ctc">0.00</span></strong></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden fields to store calculated values (matching user_payrolls table) -->
                            <!-- Hidden fields to store calculated values (matching user_payrolls table) -->
                            <input type="hidden" name="basic_salary" id="basic_salary">
                            <input type="hidden" name="hra" id="hra">
                            <input type="hidden" name="conveyence" id="conveyence">
                            <input type="hidden" name="medical_allowance" id="medical_allowance">
                            <input type="hidden" name="children_allowance" id="children_allowance">
                            <input type="hidden" name="post_allowance" id="post_allowance">
                            <input type="hidden" name="leave_travel_allowance" id="leave_travel_allowance">
                            <input type="hidden" name="monthly_incentive" id="monthly_incentive">
                            <input type="hidden" name="special_allowance" id="special_allowance">
                            <input type="hidden" name="provident_fund" id="provident_fund">
                            <!-- Changed from pf_employee -->
                            <input type="hidden" name="employer_provident_fund" id="employer_provident_fund">
                            <!-- Changed from pf_employer -->
                            <input type="hidden" name="esi" id="esi"> <!-- Changed from esi_employee -->
                            <input type="hidden" name="employer_esi" id="employer_esi">
                            <!-- Changed from esi_employer -->
                            <input type="hidden" name="professional_tax" id="professional_tax">
                            <!-- Changed from professional_tax_amt -->
                            <input type="hidden" name="gross_salary" id="gross_salary">
                            <input type="hidden" name="net_salary" id="net_salary">
                            <input type="hidden" name="ctc" id="ctc">
                        </div>
                    </div>
                    <!-- Step 6: Documents -->
                    <div class="step-content" id="step7">
                        <div class="form-section">
                            <h5 class="section-title">Document Uploads</h5>

                            <div class="form-group">
                                <label class="form-label">Experience Letter</label>
                                <input type="file" class="file-input" name="experience_letter">
                            </div>

                            <div class="form-group">
                                <label class="form-label">10th Marksheet</label>
                                <input type="file" class="file-input" name="tenth_marksheet">
                            </div>

                            <div class="form-group">
                                <label class="form-label">12th Marksheet</label>
                                <input type="file" class="file-input" name="twelfth_marksheet">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Highest Qualification</label>
                                <input type="file" class="file-input" name="highest_qualification_certificate">
                            </div>

                            <div class="alert-info mt-4">
                                <i class="feather-info"></i>
                                <span>Please review all information before final submission.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="form-navigation">
                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" id="prevBtn" disabled>
                                <i class="feather-arrow-left"></i> Previous
                            </button>
                            <button type="button" class="btn btn-primary" id="nextBtn">
                                Next <i class="feather-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                                <i class="feather-check"></i> Submit
                            </button>
                        </div>
                        <a href="{{ route('employee.index') }}" class="btn btn-light">
                            <i class="feather-x"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        {{-- </div> --}}
    </div>
@endsection

@section('script-area')
    <!-- Toastr CSS and JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Select2 CSS and JS (if not already loaded) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        let currentStep = 1;
        const totalSteps = 7;
        let employeeId = '{{ session('employee_id') }}';
        let isManualEdit = false;

        $(document).ready(function() {
            // Initialize Select2
            if ($.fn.select2) {
                $('.select2').select2({
                    placeholder: 'Select Languages',
                    allowClear: true,
                    width: '100%'
                });
            }

            // Profile image upload
            $('#profileUploadBtn, #profileImagePreview').click(function() {
                $('#profilePhotoInput').click();
            });

            $('#profilePhotoInput').change(function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        toastr.error('File size must be less than 2MB');
                        $(this).val('');
                        return;
                    }

                    const fileType = file.type;
                    if (!fileType.match('image.*')) {
                        toastr.error('Only image files are allowed');
                        $(this).val('');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#profileImagePreview').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Load states
            $('#country').change(function() {
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
                                $('#state').append('<option value="' + state
                                    .state_code + '">' + state.name + '</option>');
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
            $('#state').change(function() {
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
                                $('#city').append('<option value="' + city.city_code +
                                    '">' + city.name + '</option>');
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
            // ==================== PAYROLL CALCULATION FUNCTIONS ====================

            function calculateSalaryBreakdown() {
                const annualCTC = parseFloat($('#annual_ctc').val()) || 0;
                const payrollMasterId = $('#payroll_master_id').val();

                console.log('========== PAYROLL CALCULATION START ==========');
                console.log('Annual CTC:', annualCTC);
                console.log('Payroll Master ID:', payrollMasterId);

                if (annualCTC > 0) {
                    // Monthly CTC
                    const monthlyCTC = annualCTC / 12;
                    console.log('Monthly CTC:', monthlyCTC);

                    let basic, hra, conveyence, medical, children, post, lta, incentive;
                    let pfPercentage = 0,
                        employerPfPercentage = 0,
                        esiPercentage = 0,
                        employerEsiPercentage = 0,
                        ptAmount = 0;

                    if (payrollMasterId) {
                        // Get selected payroll master data
                        const selectedOption = $('#payroll_master_id option:selected');

                        // Log all data attributes to see what's coming from the database
                        console.log('Raw data attributes:');
                        console.log('data-hra:', selectedOption.data('hra'));
                        console.log('data-conveyence:', selectedOption.data('conveyence'));
                        console.log('data-medical-allowance:', selectedOption.data('medical-allowance'));
                        console.log('data-provident-fund:', selectedOption.data('provident-fund'));
                        console.log('data-esi:', selectedOption.data('esi'));
                        console.log('data-pt:', selectedOption.data('pt'));

                        // Get percentages from master - use EXACT values from database
                        const hraPercentage = parseFloat(selectedOption.data('hra')) || 0;
                        const conveyencePercentage = parseFloat(selectedOption.data('conveyence')) || 0;
                        const medicalPercentage = parseFloat(selectedOption.data('medical-allowance')) || 0;
                        const childrenPercentage = parseFloat(selectedOption.data('children-allowance')) || 0;
                        const postPercentage = parseFloat(selectedOption.data('post-allowance')) || 0;
                        const ltaPercentage = parseFloat(selectedOption.data('leave-travel-allowance')) || 0;
                        const incentivePercentage = parseFloat(selectedOption.data('monthly-incentive')) || 0;

                        console.log('Parsed allowance percentages:', {
                            hra: hraPercentage,
                            conveyence: conveyencePercentage,
                            medical: medicalPercentage,
                            children: childrenPercentage,
                            post: postPercentage,
                            lta: ltaPercentage,
                            incentive: incentivePercentage
                        });

                        // Get deduction percentages - use EXACT values from database
                        pfPercentage = parseFloat(selectedOption.data('provident-fund'));
                        employerPfPercentage = parseFloat(selectedOption.data('employer-provident-fund'));
                        esiPercentage = parseFloat(selectedOption.data('esi'));
                        employerEsiPercentage = parseFloat(selectedOption.data('employer-esi'));
                        ptPercentage = parseFloat(selectedOption.data('pt'));

                        console.log('Raw deduction values:', {
                            pf: selectedOption.data('provident-fund'),
                            employerPf: selectedOption.data('employer-provident-fund'),
                            esi: selectedOption.data('esi'),
                            employerEsi: selectedOption.data('employer-esi'),
                            pt: selectedOption.data('pt')
                        });

                        // Handle cases where data attributes might be undefined or null
                        pfPercentage = !isNaN(pfPercentage) ? pfPercentage : 0;
                        employerPfPercentage = !isNaN(employerPfPercentage) ? employerPfPercentage : 0;
                        esiPercentage = !isNaN(esiPercentage) ? esiPercentage : 0;
                        employerEsiPercentage = !isNaN(employerEsiPercentage) ? employerEsiPercentage : 0;
                        ptAmount = !isNaN(ptAmount) ? ptAmount : 0;

                        console.log('Final deduction percentages (after parsing):', {
                            pf: pfPercentage,
                            employerPf: employerPfPercentage,
                            esi: esiPercentage,
                            employerEsi: employerEsiPercentage,
                            pt: ptAmount
                        });

                        // Total allowance percentage
                        const totalAllowancePercentage = hraPercentage + conveyencePercentage + medicalPercentage +
                            childrenPercentage + postPercentage + ltaPercentage + incentivePercentage;

                        console.log('Total allowance percentage:', totalAllowancePercentage);

                        // Check if all allowances are zero
                        if (totalAllowancePercentage === 0) {
                            // If all allowances are 0, then 100% goes to basic salary
                            basic = monthlyCTC;
                            hra = 0;
                            conveyence = 0;
                            medical = 0;
                            children = 0;
                            post = 0;
                            lta = 0;
                            incentive = 0;

                            console.log('All allowances are zero, setting 100% to basic:', basic);
                        } else {
                            // Calculate Basic (where Basic + (Basic * totalAllowancePercentage/100) = monthlyCTC)
                            basic = monthlyCTC / (1 + (totalAllowancePercentage / 100));
                            console.log('Calculated basic (with allowances):', basic);

                            // Calculate all allowances as percentage of Basic
                            hra = basic * (hraPercentage / 100);
                            conveyence = basic * (conveyencePercentage / 100);
                            medical = basic * (medicalPercentage / 100);
                            children = basic * (childrenPercentage / 100);
                            post = basic * (postPercentage / 100);
                            lta = basic * (ltaPercentage / 100);
                            incentive = basic * (incentivePercentage / 100);

                            console.log('Calculated allowances:', {
                                hra,
                                conveyence,
                                medical,
                                children,
                                post,
                                lta,
                                incentive
                            });
                        }
                    } else {
                        // No master selected - use standard calculation
                        console.log('No payroll master selected, using standard calculation');
                        basic = monthlyCTC * 0.5;
                        hra = basic * 0.4;
                        conveyence = 1600;
                        medical = 1250;
                        children = 0;
                        post = 0;
                        lta = 0;
                        incentive = 0;

                        // Standard deduction percentages when no master is selected
                        pfPercentage = 12;
                        employerPfPercentage = 12;
                        esiPercentage = 1.75;
                        employerEsiPercentage = 3.25;
                        ptAmount = 200;
                    }

                    // Total allowances
                    const totalAllowances = hra + conveyence + medical + children + post + lta + incentive;
                    console.log('Total allowances:', totalAllowances);

                    // Gross salary (should equal monthlyCTC)
                    const grossSalary = basic + totalAllowances;
                    console.log('Gross salary:', grossSalary);
                    console.log('Expected monthly CTC:', monthlyCTC);
                    console.log('Difference:', Math.abs(grossSalary - monthlyCTC));

                    // Calculate deductions - only if percentages are > 0
                    let pfDeduction = 0;
                    if (pfPercentage > 0) {
                        pfDeduction = Math.min(basic * (pfPercentage / 100), 1800);
                        console.log('Calculating PF:', basic, '*', pfPercentage, '% =', basic * (pfPercentage /
                            100), 'capped at:', pfDeduction);
                    } else {
                        console.log('PF percentage is 0, skipping PF deduction');
                    }

                    let esiDeduction = 0;
                    if (esiPercentage > 0 && grossSalary <= 21000) {
                        esiDeduction = grossSalary * (esiPercentage / 100);
                        console.log('Calculating ESI:', grossSalary, '*', esiPercentage, '% =', esiDeduction);
                    } else {
                        console.log('ESI condition not met:', {
                            esiPercentage,
                            grossSalary,
                            condition: esiPercentage > 0 && grossSalary <= 21000
                        });
                    }

                    // Professional Tax (as percentage of gross salary)
                    let professionalTax = 0;
                    if (ptPercentage > 0) {
                        professionalTax = grossSalary * (ptPercentage / 100);
                        console.log('PT Calculation:', grossSalary, '*', ptPercentage, '% =', professionalTax);
                    }
                    console.log('Professional Tax:', professionalTax);

                    // Total deductions
                    const totalDeductions = pfDeduction + esiDeduction + professionalTax;
                    console.log('Total deductions:', totalDeductions);

                    // Net salary
                    const netSalary = grossSalary - totalDeductions;
                    console.log('Net salary:', netSalary);

                    // Employer contributions - only if percentages are > 0
                    let employerPf = 0;
                    if (employerPfPercentage > 0) {
                        employerPf = Math.min(basic * (employerPfPercentage / 100), 1800);
                        console.log('Employer PF:', employerPf);
                    }

                    let employerEsi = 0;
                    if (employerEsiPercentage > 0 && grossSalary <= 21000) {
                        employerEsi = grossSalary * (employerEsiPercentage / 100);
                        console.log('Employer ESI:', employerEsi);
                    }

                    // Total monthly cost to company
                    const totalMonthlyCost = grossSalary + employerPf + employerEsi;
                    console.log('Total monthly cost:', totalMonthlyCost);
                    console.log('========== PAYROLL CALCULATION END ==========');

                    // Update both monthly and yearly views
                    updateDisplayFields({
                        // Monthly values
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
                        // Yearly values (monthly × 12)
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

                    // Set hidden fields (store monthly values in DB)
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
                } else {
                    console.log('Annual CTC is 0, resetting fields');
                    resetAllFields();
                }
            }
            /**
             * Update display fields with calculated values (both monthly and yearly)
             */
            function updateDisplayFields(values) {
                // Monthly view
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

                // Yearly view
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

            /**
             * Set hidden form fields with calculated monthly values
             */
            function setHiddenFields(values) {
                $('#basic_salary').val(values.basic.toFixed(2));
                $('#hra').val(values.hra.toFixed(2));
                $('#conveyence').val(values.conveyence.toFixed(2));
                $('#medical_allowance').val(values.medical.toFixed(2));
                $('#children_allowance').val((values.children || 0).toFixed(2));
                $('#post_allowance').val((values.post || 0).toFixed(2));
                $('#leave_travel_allowance').val((values.lta || 0).toFixed(2));
                $('#monthly_incentive').val((values.incentive || 0).toFixed(2));
                $('#special_allowance').val('0.00'); // Add this if you have special allowance
                $('#gross_salary').val(values.gross.toFixed(2));
                $('#provident_fund').val(values.pf.toFixed(2));
                $('#esi').val(values.esi.toFixed(2));
                $('#professional_tax').val(values.pt.toFixed(2));
                $('#net_salary').val(values.net.toFixed(2));
                $('#employer_provident_fund').val(values.employerPf.toFixed(2));
                $('#employer_esi').val(values.employerEsi.toFixed(2));
                $('#ctc').val((values.gross + values.employerPf + values.employerEsi).toFixed(2));

                // Also set the salary field in step 5 if needed
                $('input[name="salary"]').val(values.gross.toFixed(2));
            } // Add view toggle functionality
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
            /**
             * Reset all display fields to zero/default
             */
            function resetAllFields() {
                const zeroFields = ['basic_amount', 'hra_amount', 'special_amount', 'gross_amount',
                    'employee_pf', 'employee_esi', 'net_salary', 'employer_pf',
                    'employer_esi', 'total_ctc', 'children_amount', 'post_amount',
                    'lta_amount', 'incentive_amount'
                ];

                zeroFields.forEach(field => {
                    $('#' + field).text('₹0.00');
                });

                $('#conveyance_amount').text('₹0.00');
                $('#medical_amount').text('₹0.00');
                $('#professional_tax').text('₹0.00');

                // Reset hidden fields
                $('#basic_salary, #hra, #conveyance, #medical_allowance, #children_allowance, #post_allowance, #leave_travel_allowance, #monthly_incentive, #special_allowance, #gross_salary, #pf_employee, #esi_employee, #professional_tax_amt, #net_salary, #pf_employer, #esi_employer, #bonus')
                    .val('');
            }

            /**
             * Format number with commas (Indian numbering system)
             */
            function formatNumber(num) {
                if (num === 0) return '0.00';

                // Convert to 2 decimal places
                let numStr = num.toFixed(2);

                // Split into integer and decimal parts
                let parts = numStr.split('.');
                let integerPart = parts[0];
                let decimalPart = parts[1];

                // Format integer part with Indian numbering system (last 3 digits grouped, then 2 digits)
                let lastThree = integerPart.slice(-3);
                let otherNumbers = integerPart.slice(0, -3);

                if (otherNumbers !== '') {
                    integerPart = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + "," + lastThree;
                }

                return integerPart + '.' + decimalPart;
            }

            // ==================== PAYROLL EVENT LISTENERS ====================

            // Calculate when Annual CTC changes
            $('#annual_ctc').on('input', function() {
                calculateSalaryBreakdown();
            });

            // Calculate when Payroll Master changes
            $('#payroll_master_id').change(function() {
                calculateSalaryBreakdown();
            });

            // Set default effective date to today
            $('#salary_effective_date').val(new Date().toISOString().slice(0, 10));

            // ==================== STEP NAVIGATION ====================

            $('#nextBtn').click(function() {
                if (validateStep(currentStep)) {
                    saveStep(currentStep, function() {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            showStep(currentStep);

                            // Trigger calculation when reaching step 6
                            if (currentStep === 6) {
                                setTimeout(calculateSalaryBreakdown, 100);
                            }
                        }
                    });
                }
            });

            $('#prevBtn').click(function() {
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

            $('#employeeForm').submit(function(e) {
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

            showStep(currentStep);

            if (employeeId) {
                loadSavedData(employeeId);
            }
        });

        // ==================== STEP DISPLAY FUNCTION ====================

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

            $('html, body').animate({
                scrollTop: $('.step-wizard').offset().top - 50
            }, 300);
        }

        // ==================== VALIDATION FUNCTIONS ====================

        function validateStep(step) {
            $(`#step${step} .is-invalid`).removeClass('is-invalid');
            $(`#step${step} .invalid-feedback`).remove();

            let isValid = true;
            const currentStepElement = $(`#step${step}`);

            // Step 1 validation
            if (step === 1) {
                // Name validation
                const name = currentStepElement.find('input[name="name"]');
                if (!name.val().trim()) {
                    showError(name, 'Name is required');
                    isValid = false;
                } else if (name.val().trim().length < 2) {
                    showError(name, 'Name must be at least 2 characters');
                    isValid = false;
                }

                // Email validation
                const email = currentStepElement.find('input[name="email"]');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email.val()) {
                    showError(email, 'Email is required');
                    isValid = false;
                } else if (!emailRegex.test(email.val())) {
                    showError(email, 'Please enter a valid email address');
                    isValid = false;
                }

                // Phone validation
                const phone = currentStepElement.find('input[name="contact"]');
                const phoneRegex = /^\d{10}$/;
                if (!phone.val()) {
                    showError(phone, 'Phone number is required');
                    isValid = false;
                } else if (!phoneRegex.test(phone.val())) {
                    showError(phone, 'Please enter a valid 10-digit phone number');
                    isValid = false;
                }

                // Password validation
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

                // Confirm password validation
                const confirmPass = currentStepElement.find('input[name="password_confirmation"]');
                if (password.val() !== confirmPass.val()) {
                    showError(confirmPass, 'Passwords do not match');
                    isValid = false;
                }
            }

            // Step 2 validation
            if (step === 2) {
                const gender = currentStepElement.find('select[name="gender"]');
                if (!gender.val()) {
                    showError(gender, 'Please select gender');
                    isValid = false;
                }

                const dob = currentStepElement.find('input[name="dob"]');
                if (!dob.val()) {
                    showError(dob, 'Date of birth is required');
                    isValid = false;
                } else {
                    const selectedDate = new Date(dob.val());
                    const today = new Date();
                    if (selectedDate > today) {
                        showError(dob, 'Date of birth cannot be in the future');
                        isValid = false;
                    }
                }

                const fatherName = currentStepElement.find('input[name="father_name"]');
                if (!fatherName.val().trim()) {
                    showError(fatherName, 'Father\'s name is required');
                    isValid = false;
                }

                const motherName = currentStepElement.find('input[name="mother_name"]');
                if (!motherName.val().trim()) {
                    showError(motherName, 'Mother\'s name is required');
                    isValid = false;
                }

                const languages = currentStepElement.find('select[name="language[]"]');
                if (!languages.val() || languages.val().length === 0) {
                    showError(languages, 'Please select at least one language');
                    isValid = false;
                }
            }

            // Step 3 validation
            if (step === 3) {
                const workType = currentStepElement.find('select[name="type"]');
                if (!workType.val()) {
                    showError(workType, 'Please select work type');
                    isValid = false;
                }

                const branch = currentStepElement.find('select[name="branch"]');
                if (!branch.val()) {
                    showError(branch, 'Please select office branch');
                    isValid = false;
                }

                // const status = currentStepElement.find('select[name="status"]');
                // if (!status.val() && status.val() !== '0') {
                //     showError(status, 'Please select status');
                //     isValid = false;
                // }
            }

            // Step 4 validation
            if (step === 4) {
                const permanentAddress = currentStepElement.find('textarea[name="permanent_address"]');
                if (!permanentAddress.val().trim()) {
                    showError(permanentAddress, 'Permanent address is required');
                    isValid = false;
                }
            }

            // Step 5 validation (optional - bank details can be partial)
            if (step === 5) {
                // Bank details are optional, but validate format if provided
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

            // Step 6 validation (Payroll)
            if (step === 6) {
                const payrollMaster = currentStepElement.find('select[name="payroll_master_id"]');
                if (!payrollMaster.val()) {
                    showError(payrollMaster, 'Please select a payroll master');
                    isValid = false;
                }

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

            // Show error summary if validation fails
            if (!isValid) {
                const firstError = $(`#step${step} .is-invalid:first`);
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 150
                    }, 400);
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

        // ==================== AJAX FUNCTIONS ====================

        function saveStep(step, callback) {
            const nextBtn = $('#nextBtn');
            const submitBtn = $('#submitBtn');
            const originalText = nextBtn.is(':visible') ? nextBtn.html() : submitBtn.html();

            if (nextBtn.is(':visible')) {
                nextBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
            } else {
                submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled',
                    true);
            }

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('step', step);
            formData.append('_token', '{{ csrf_token() }}');

            if (employeeId) {
                formData.append('employee_id', employeeId);
            }

            $.ajax({
                url: "{{ route('employee.save.step') }}",
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
                        if (response.employee_id) {
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

                        // Clear previous errors
                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();

                        // Display new errors
                        let errorMessage = '';
                        $.each(errors, function(field, messages) {
                            errorMessage += messages[0] + '\n';

                            // Find the input field and show error
                            const input = $(`[name="${field}"]`);
                            if (input.length) {
                                input.addClass('is-invalid');
                                if (!input.next('.invalid-feedback').length) {
                                    input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                                }
                            }
                        });

                        toastr.error('Please fix the errors and try again');

                        // Scroll to first error
                        const firstError = $('.is-invalid:first');
                        if (firstError.length) {
                            $('html, body').animate({
                                scrollTop: firstError.offset().top - 150
                            }, 400);
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

        function loadSavedData(employeeId) {
            $.ajax({
                url: "{{ route('employee.load.data') }}",
                type: "GET",
                data: {
                    employee_id: employeeId
                },
                success: function(response) {
                    if (response.data) {
                        populateFormData(response.data);

                        if (response.last_step) {
                            currentStep = response.last_step;
                            showStep(currentStep);
                        }

                        toastr.info('Loaded previously saved data');
                    }
                },
                error: function() {
                    console.log('No saved data found');
                }
            });
        }

        function populateFormData(data) {
            // Populate step 1
            if (data.user) {
                $('input[name="name"]').val(data.user.name);
                $('input[name="email"]').val(data.user.email);
                $('input[name="contact"]').val(data.user.contact);
                $('select[name="role"]').val(data.user.role);
            }

            // Populate step 2
            if (data.basic) {
                $('input[name="personal_email"]').val(data.basic.personal_email);
                $('input[name="alternate_phone"]').val(data.basic.alternate_phone);
                $('select[name="gender"]').val(data.basic.gender);
                $('input[name="dob"]').val(data.basic.dob);
                $('input[name="blood_group"]').val(data.basic.blood_group);
                $('select[name="marital_status"]').val(data.basic.marital_status);
                $('input[name="father_name"]').val(data.basic.father_name);
                $('input[name="mother_name"]').val(data.basic.mother_name);

                if (data.basic.language) {
                    try {
                        const languages = JSON.parse(data.basic.language);
                        $('select[name="language[]"]').val(languages).trigger('change');
                    } catch (e) {
                        console.log('Error parsing languages');
                    }
                }

                $('textarea[name="about"]').val(data.basic.about);

                if (data.basic.profile_image) {
                    $('#profileImagePreview').attr('src', '/' + data.basic.profile_image);
                }
            }

            // Populate step 3
            if (data.job) {
                $('select[name="department"]').val(data.job.department);
                $('select[name="designation"]').val(data.job.designation);
                $('select[name="reporting_head"]').val(data.job.reporting_head);
                $('select[name="employment_type"]').val(data.job.employment_type);
                $('select[name="type"]').val(data.job.type);
                $('select[name="branch"]').val(data.job.office_branch);
                $('input[name="joining_date"]').val(data.job.joining_date);
                // $('select[name="status"]').val(data.job.status);
            }

            // Populate step 4
            if (data.location) {
                $('select[name="country"]').val(data.location.country);

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

                $('textarea[name="permanent_address"]').val(data.location.permanent_address);
                $('textarea[name="current_address"]').val(data.location.address || data.location.current_address);
                $('input[name="pin_code"]').val(data.location.pincode || data.location.pin_code);
            }

            // Populate step 5
            if (data.bank) {
                $('input[name="bank_name"]').val(data.bank.bank_name);
                $('input[name="account_number"]').val(data.bank.account_number);
                $('input[name="ifsc"]').val(data.bank.ifsc);
                $('input[name="branch_name"]').val(data.bank.branch_name);
            }

            // Populate step 6 (Payroll)
            if (data.payroll) {
                $('select[name="payroll_master_id"]').val(data.payroll.payroll_master_id);
                $('input[name="annual_ctc"]').val(data.payroll.annual_ctc);
                $('input[name="salary_effective_date"]').val(data.payroll.effective_from || data.payroll.effective_date);

                // Trigger calculation after a short delay
                setTimeout(function() {
                    if (typeof calculateSalaryBreakdown === 'function') {
                        calculateSalaryBreakdown();
                    }
                }, 500);
            }
        }

        function submitForm() {
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...').prop(
                'disabled', true);

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: "{{ route('employee.complete.store') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        toastr.success('Employee created successfully!');

                        setTimeout(function() {
                            window.location.href = "{{ route('employee.index') }}";
                        }, 1500);
                    } else {
                        $('#submitBtn').html('<i class="feather-check"></i> Submit').prop('disabled', false);
                        toastr.error(response.message || 'Error completing registration');
                    }
                },
                error: function(xhr) {
                    $('#submitBtn').html('<i class="feather-check"></i> Submit').prop('disabled', false);

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        displayErrors(errors, currentStep);
                        toastr.error('Please fix the errors and try again');

                        const firstError = $(`.is-invalid:first`);
                        if (firstError.length) {
                            $('html, body').animate({
                                scrollTop: firstError.offset().top - 150
                            }, 400);
                        }
                    } else {
                        toastr.error('An error occurred while submitting the form');
                    }
                }
            });
        }
    </script>
@endsection
