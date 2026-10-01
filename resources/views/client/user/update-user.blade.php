@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== STEP WIZARD STYLES ==================== */
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

        /* ==================== FORM SECTIONS ==================== */
        .form-section {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: #4f46e5;
            font-size: 18px;
        }

        /* ==================== FORM CONTROLS ==================== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
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
            font-size: 13px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control.is-invalid {
            border-color: #ef4444;
        }

        .invalid-feedback {
            color: #ef4444;
            font-size: 11px;
            margin-top: 4px;
        }

        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-right: none;
            border-radius: 8px 0 0 8px;
            padding: 8px 12px;
            color: #64748b;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }

        .input-group .form-control:focus {
            border-left: none;
        }

        /* ==================== PROFILE IMAGE ==================== */
        .profile-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
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
            background: #4f46e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            border: 2px solid #fff;
            transition: all 0.2s;
        }

        .profile-image-overlay:hover {
            background: #4338ca;
            transform: scale(1.1);
        }

        .profile-image-overlay i {
            font-size: 16px;
        }

        .profile-help-text {
            font-size: 12px;
            color: #64748b;
        }

        /* ==================== FILE PREVIEW ==================== */
        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
        }

        .existing-file {
            color: #4f46e5;
            text-decoration: none;
            margin-left: 5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .existing-file:hover {
            color: #4338ca;
            text-decoration: underline;
        }

        /* ==================== BADGES ==================== */
        .badge {
            padding: 4px 10px;
            font-weight: 500;
            font-size: 11px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge.bg-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-info {
            background: #e0f2fe !important;
            color: #0369a1;
        }

        /* ==================== EMPLOYEE ID BADGE ==================== */
        .employee-id-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            font-family: monospace;
            display: inline-block;
            border: 1px solid #e2e8f0;
        }

        .employee-id-badge i {
            color: #4f46e5;
            margin-right: 6px;
        }

        /* ==================== READONLY FIELD ==================== */
        .form-control[readonly] {
            background-color: #f8fafc;
            cursor: not-allowed;
        }

        /* ==================== NAVIGATION BUTTONS ==================== */
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
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4f46e5;
            color: #fff;
        }

        .btn-primary:hover:not(:disabled) {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.2);
        }

        .btn-secondary {
            background: #fff;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .btn-success {
            background: #10b981;
            color: #fff;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);
        }

        .btn-light-brand {
            background: #fff;
            color: #4f46e5;
            border: 1px solid #e2e8f0;
        }

        .btn-light-brand:hover {
            background: #f8fafc;
            border-color: #4f46e5;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ==================== STEP CONTENT ==================== */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        /* ==================== FORM ROW ==================== */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .step-indicator {
                gap: 10px;
            }

            .step-label {
                font-size: 10px;
            }
        }

        /* ==================== ALERTS ==================== */
        .alert-info {
            background: #e6f3ff;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ==================== PAYROLL STYLES ==================== */
        .salary-breakdown {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .salary-breakdown table {
            margin-bottom: 0;
        }

        .salary-breakdown td {
            padding: 8px 12px;
            font-size: 13px;
        }

        .salary-breakdown .table-primary {
            background: #e0f2fe !important;
        }

        .salary-breakdown .table-success {
            background: #d1fae5 !important;
        }

        .salary-breakdown .table-danger {
            background: #fee2e2 !important;
        }

        .salary-breakdown .table-warning {
            background: #fef3c7 !important;
        }

        .salary-breakdown .table-info {
            background: #e0f2fe !important;
        }

        .salary-breakdown .table-secondary {
            background: #f1f5f9 !important;
        }

        .btn-group .btn-outline-primary {
            border-color: #e2e8f0;
            color: #64748b;
        }

        .btn-group .btn-outline-primary.active {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                {{-- <h5 class="m-b-10">Edit Employee</h5> --}}
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('employee.index') }}">Employees</a></li>
                <li class="breadcrumb-item">Edit Employee</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <!-- Main Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('employee.complete.update', ['id' => encrypt($user->id)]) }}" method="post"
                    enctype="multipart/form-data" id="employeeForm" novalidate>
                    @csrf
                    <input type="hidden" name="current_step" id="current_step" value="1">
                    <input type="hidden" name="form_step" id="form_step" value="1">
                    <input type="hidden" name="employee_id" id="employee_id" value="{{ $user->employee_id }}">
                    <input type="hidden" name="user_id" id="user_id" value="{{ $user->id }}">

                    <!-- Step Wizard (7 steps now) -->
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
                            <h5 class="section-title">
                                <i class="feather-log-in"></i>
                                Login Details
                            </h5>

                            <!-- Profile Photo -->
                            <div class="profile-upload mb-4">
                                <div class="profile-image-container">
                                    @if ($user->basicDetails && $user->basicDetails->profile_image)
                                        <img src="{{ file_url($user->basicDetails->profile_image, 'profile_photo') }}"
                                            class="profile-image-preview" id="profileImagePreview" alt="Profile Image">
                                    @else
                                        <img src="{{ asset('assets/images/avatar/1.png') }}" class="profile-image-preview"
                                            id="profileImagePreview" alt="Profile Image">
                                    @endif
                                    <div class="profile-image-overlay" id="profileUploadBtn">
                                        <i class="feather-camera"></i>
                                    </div>
                                    <input type="file" name="profile_photo" id="profilePhotoInput" class="d-none"
                                        accept="image/*">
                                </div>
                                <div class="profile-help-text">
                                    <p class="mb-1"><i class="feather-info me-1"></i> Click to upload new photo</p>
                                    <small>Max size: 2MB (JPG, PNG)</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-user"></i></span>
                                        <input type="text" class="form-control" name="name"
                                            value="{{ old('name', $user->name) }}" required>
                                    </div>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Employee ID </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-hash"></i></span>
                                        <input type="text" class="form-control" value="{{ $user->employee_id }}"
                                            readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Official Email <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-mail"></i></span>
                                        <input type="email" class="form-control" name="email"
                                            value="{{ old('email', $user->email) }}" required>
                                    </div>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Phone Number <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-phone"></i></span>
                                        <input type="tel" class="form-control" name="contact"
                                            value="{{ old('contact', $user->contact) }}" required>
                                    </div>
                                    @error('contact')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="employee" {{ $user->role == 'employee' ? 'selected' : '' }}>Employee
                                    </option>
                                    <option value="manager" {{ $user->role == 'manager' ? 'selected' : '' }}>Manager
                                    </option>
                                    <option value="hr" {{ $user->role == 'hr' ? 'selected' : '' }}>HR</option>
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Personal Information (with Aadhaar, PAN, Passport) -->
                    <div class="step-content" id="step2">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-user"></i>
                                Personal Information
                            </h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Personal Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-mail"></i></span>
                                        <input type="email" class="form-control" name="personal_email"
                                            value="{{ old('personal_email', $user->basicDetails->personal_email ?? '') }}">
                                    </div>
                                    @error('personal_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Alternate Phone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-phone"></i></span>
                                        <input type="tel" class="form-control" name="alternate_phone"
                                            value="{{ old('alternate_phone', $user->basicDetails->alternate_phone ?? '') }}">
                                    </div>
                                    @error('alternate_phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-control">
                                        <option value="">Select Gender</option>
                                        <option value="m"
                                            {{ old('gender', $user->basicDetails->gender ?? '') == 'm' ? 'selected' : '' }}>
                                            Male</option>
                                        <option value="f"
                                            {{ old('gender', $user->basicDetails->gender ?? '') == 'f' ? 'selected' : '' }}>
                                            Female</option>
                                        <option value="o"
                                            {{ old('gender', $user->basicDetails->gender ?? '') == 'o' ? 'selected' : '' }}>
                                            Other</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Date of Birth</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-calendar"></i></span>
                                        <input type="date" class="form-control" name="dob"
                                            value="{{ old('dob', $user->basicDetails->dob ?? '') }}">
                                    </div>
                                    @error('dob')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Blood Group</label>
                                    <input type="text" class="form-control" name="blood_group"
                                        value="{{ old('blood_group', $user->basicDetails->blood_group ?? '') }}">
                                    @error('blood_group')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="single"
                                            {{ old('marital_status', $user->basicDetails->marital_status ?? '') == 'single' ? 'selected' : '' }}>
                                            Single</option>
                                        <option value="married"
                                            {{ old('marital_status', $user->basicDetails->marital_status ?? '') == 'married' ? 'selected' : '' }}>
                                            Married</option>
                                        <option value="divorced"
                                            {{ old('marital_status', $user->basicDetails->marital_status ?? '') == 'divorced' ? 'selected' : '' }}>
                                            Divorced</option>
                                    </select>
                                    @error('marital_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Father's Name</label>
                                    <input type="text" class="form-control" name="father_name"
                                        value="{{ old('father_name', $user->basicDetails->father_name ?? '') }}">
                                    @error('father_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Mother's Name</label>
                                    <input type="text" class="form-control" name="mother_name"
                                        value="{{ old('mother_name', $user->basicDetails->mother_name ?? '') }}">
                                    @error('mother_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- MOVED FIELDS FROM STEP 5 -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Aadhaar Number</label>
                                    <input type="text" class="form-control" name="aadhaar_no"
                                        value="{{ old('aadhaar_no', $user->basicDetails->aadhaar_no ?? '') }}">
                                    @error('aadhaar_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">PAN Number</label>
                                    <input type="text" class="form-control" name="pan_no"
                                        value="{{ old('pan_no', $user->basicDetails->pan_no ?? '') }}">
                                    @error('pan_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Passport Number</label>
                                    <input type="text" class="form-control" name="passport_number"
                                        value="{{ old('passport_number', $user->basicDetails->passport_number ?? '') }}">
                                    @error('passport_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Nationality</label>
                                    <select class="form-control" name="nationality">
                                        <option value="Indian"
                                            {{ old('nationality', $user->basicDetails->nationality ?? '') == 'Indian' ? 'selected' : '' }}>
                                            Indian</option>
                                    </select>
                                    @error('nationality')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>
                            <div class="form-group">
                                <label class="form-label">Languages</label>
                                <select class="form-control select2" name="language[]" multiple>
                                    @php
                                        $selectedLanguages = [];
                                        if ($user->basicDetails && $user->basicDetails->language) {
                                            $selectedLanguages = json_decode($user->basicDetails->language, true);
                                        }
                                    @endphp
                                    @foreach ($languages as $language)
                                        <option value="{{ $language->id }}"
                                            {{ in_array($language->id, old('language', $selectedLanguages ?? [])) ? 'selected' : '' }}>
                                            {{ $language->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('language')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>


                            <div class="form-group">
                                <label class="form-label">About</label>
                                <textarea class="form-control" name="about" rows="3">{{ old('about', $user->basicDetails->about ?? '') }}</textarea>
                                @error('about')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Job Details -->
                    <div class="step-content" id="step3">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-briefcase"></i>
                                Job Details
                            </h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Department</label>
                                    <select name="department" class="form-control">
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}"
                                                {{ old('department', $user->jobDetails->department ?? '') == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('department')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Designation</label>
                                    <select name="designation" class="form-control">
                                        <option value="">Select Designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->id }}"
                                                {{ old('designation', $user->jobDetails->designation ?? '') == $designation->id ? 'selected' : '' }}>
                                                {{ $designation->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('designation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Reporting Head(s)</label>
                                    @php
                                        $selectedReportingHeadIds = old('reporting_head', $user->reportingHeads->pluck('id')->all());
                                    @endphp
                                    <select name="reporting_head[]" class="form-control select2" multiple>
                                        @foreach ($employees as $employee)
                                            @if ($employee->id != $user->id)
                                                <option value="{{ $employee->id }}"
                                                    {{ in_array($employee->id, $selectedReportingHeadIds) ? 'selected' : '' }}>
                                                    {{ $employee->name }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('reporting_head')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Employment Type</label>
                                    <select name="employment_type" class="form-control">
                                        <option value="full-time"
                                            {{ old('employment_type', $user->jobDetails->employment_type ?? '') == 'full-time' ? 'selected' : '' }}>
                                            Full-Time</option>
                                        <option value="part-time"
                                            {{ old('employment_type', $user->jobDetails->employment_type ?? '') == 'part-time' ? 'selected' : '' }}>
                                            Part-Time</option>
                                        <option value="contract"
                                            {{ old('employment_type', $user->jobDetails->employment_type ?? '') == 'contract' ? 'selected' : '' }}>
                                            Contract</option>
                                    </select>
                                    @error('employment_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Work Type</label>
                                    <select name="type" class="form-control">
                                        <option value="office"
                                            {{ old('type', $user->jobDetails->type ?? '') == 'office' ? 'selected' : '' }}>
                                            Office</option>
                                        <option value="field"
                                            {{ old('type', $user->jobDetails->type ?? '') == 'field' ? 'selected' : '' }}>
                                            Field</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Attendance Location</label>
                                    <select name="branch" class="form-control">
                                        <option value="">{{ $branches->count() > 1 ? 'All Locations' : '-- None --' }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                {{ old('branch', $user->jobDetails->office_branch ?? '') == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach

                                    </select>
                                    @error('branch')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Branch</label>
                                    <select name="company_branch" class="form-control">
                                        <option value="">-- None --</option>
                                        @foreach ($companyBranches ?? [] as $companyBranch)
                                            <option value="{{ $companyBranch->id }}"
                                                {{ old('company_branch', $user->jobDetails->branch_id ?? '') == $companyBranch->id ? 'selected' : '' }}>
                                                {{ $companyBranch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('company_branch')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Date of Joining</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-calendar"></i></span>
                                        <input type="date" class="form-control" name="joining_date"
                                            value="{{ old('joining_date', $user->jobDetails->joining_date ?? '') }}">
                                    </div>
                                    @error('joining_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Leave Types Assigned </label>
                                    <select name="leave_type_assigned[]" class="form-control select2" multiple>
                                        <option value="">Select Leave Types</option>
                                        @foreach ($leave_types as $leave_type)
                                            @php
                                                $selectedLeaveTypes = old(
                                                    'leave_type_assigned',
                                                    json_decode($user->jobDetails->leave_assigned ?? '[]', true),
                                                );
                                                $isSelected =
                                                    is_array($selectedLeaveTypes) &&
                                                    in_array($leave_type->id, $selectedLeaveTypes);
                                            @endphp
                                            <option value="{{ $leave_type->id }}" {{ $isSelected ? 'selected' : '' }}>
                                                {{ $leave_type->name }} ({{ ucfirst($leave_type->credit_type) }}:
                                                {{ $leave_type->credit_value }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('leave_type_assigned')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- <div class="form-group">
                                    <label class="form-label">Status <span class="required">*</span></label>
                                    <select name="status" class="form-control" required>
                                        <option value="1"
                                            {{ old('status', $user->status) == '1' ? 'selected' : '' }}>Active</option>
                                        <option value="0"
                                            {{ old('status', $user->status) == '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div> --}}
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Location -->
                    <div class="step-content" id="step4">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-map-pin"></i>
                                Address Details
                            </h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Country</label>
                                    <select class="form-control" name="country" id="country">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->country_code }}"
                                                {{ old('country', $user->location->country ?? '') == $country->country_code ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <select class="form-control" name="state" id="state">
                                        <option value="">Select State</option>
                                        @if ($user->location && $user->location->state)
                                            <option value="{{ $user->location->state }}" selected>
                                                {{ $user->location->state }}</option>
                                        @endif
                                    </select>
                                    @error('state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City</label>
                                    <select class="form-control" name="city" id="city">
                                        <option value="">Select City</option>
                                        @if ($user->location && $user->location->city)
                                            <option value="{{ $user->location->city }}" selected>
                                                {{ $user->location->city }}</option>
                                        @endif
                                    </select>
                                    @error('city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Pin Code</label>
                                    <input type="text" class="form-control" name="pin_code"
                                        value="{{ old('pin_code', $user->location->pincode ?? '') }}">
                                    @error('pin_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Permanent Address</label>
                                <textarea class="form-control" name="permanent_address" rows="2">{{ old('permanent_address', $user->location->permanent_address ?? '') }}</textarea>
                                @error('permanent_address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Current Address</label>
                                <textarea class="form-control" name="current_address" rows="2">{{ old('current_address', $user->location->address ?? '') }}</textarea>
                                @error('current_address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Bank Details (Now only Bank Info) -->
                    <div class="step-content" id="step5">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-credit-card"></i>
                                Bank Details
                            </h5>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" name="bank_name"
                                        value="{{ old('bank_name', $user->bankDetails->bank_name ?? '') }}">
                                    @error('bank_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" name="account_number"
                                        value="{{ old('account_number', $user->bankDetails->account_number ?? '') }}">
                                    @error('account_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" class="form-control" name="ifsc"
                                        value="{{ old('ifsc', $user->bankDetails->ifsc ?? '') }}">
                                    @error('ifsc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Branch Name</label>
                                    <input type="text" class="form-control" name="branch_name"
                                        value="{{ old('branch_name', $user->bankDetails->branch_name ?? '') }}">
                                    @error('branch_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">UAN Number</label>
                                    <input type="text" class="form-control" name="uan_no" placeholder="UAN Number"
                                        value="{{ old('uan_no', $user->bankDetails->uan_no ?? '') }}">
                                    @error('uan_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">PF Number</label>
                                    <input type="text" class="form-control" name="pf_no" placeholder="PF number"
                                        value="{{ old('pf_no', $user->bankDetails->pf_no ?? '') }}">
                                    @error('pf_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ESIC Number</label>
                                    <input type="text" class="form-control" name="esic_no" placeholder="ESIC Number"
                                        value="{{ old('esic_no', $user->bankDetails->esic_no ?? '') }}">
                                    @error('esic_no')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Salary</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="feather-dollar-sign"></i></span>
                                        <input type="number" step="0.01" class="form-control" name="salary"
                                            value="{{ old('salary', $user->jobDetails->salary ?? '') }}">
                                    </div>
                                    <small class="text-muted">Monthly gross salary (will be updated from payroll)</small>
                                    @error('salary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div> --}}
                        </div>
                    </div>

                    <!-- Step 6: Payroll Information -->
                    <div class="step-content" id="step6">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-dollar-sign"></i>
                                Payroll & CTC Details
                            </h5>

                            <div class="alert-info mb-4">
                                <i class="feather-info"></i>
                                <span>Enter Annual CTC - All salary components will be auto-calculated. Basic salary will be
                                    calculated as the remaining amount after all allowances.</span>
                            </div>


                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Payroll Structure</label>
                                    <select name="payroll_structure_id" id="payroll_structure_id" class="form-control">
                                        <option value="">Select Payroll Structure (optional)</option>
                                        @php
                                            $currentStructureId = $currentPayroll->payroll_structure_id ?? old('payroll_structure_id');
                                        @endphp
                                        @foreach ($payrollStructures ?? [] as $structure)
                                            <option value="{{ $structure->id }}"
                                                {{ $currentStructureId == $structure->id ? 'selected' : '' }}
                                                data-calc='{{ json_encode($structure->calculatorComponents()) }}'>
                                                {{ $structure->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Optional — used only to pre-fill the calculator below
                                        from that structure's configured components. The figures saved are always
                                        whatever is shown below.</small>
                                    @error('payroll_structure_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Annual CTC (₹) <span class="required">*</span></label>
                                    <input type="number" step="1000" class="form-control" name="annual_ctc"
                                        id="annual_ctc" placeholder="e.g., 600000"
                                        value="{{ old('annual_ctc', $currentPayroll->ctc ?? '') }}" required>
                                    <small class="text-muted">Enter total yearly CTC (including all components)</small>
                                    @error('annual_ctc')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Effective From <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="salary_effective_date"
                                        id="salary_effective_date"
                                        value="{{ old('salary_effective_date', isset($payrollData['salary_effective_date']) ? $payrollData['salary_effective_date'] : date('Y-m-d')) }}"
                                        required>
                                    @error('salary_effective_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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

                            <!-- Hidden fields to store calculated values -->
                            <input type="hidden" name="basic_salary" id="basic_salary"
                                value="{{ old('basic_salary', $currentPayroll->basic_salary ?? '') }}">
                            <input type="hidden" name="hra" id="hra"
                                value="{{ old('hra', $currentPayroll->hra ?? '') }}">
                            <input type="hidden" name="conveyence" id="conveyence"
                                value="{{ old('conveyence', $currentPayroll->conveyence ?? '') }}">
                            <input type="hidden" name="medical_allowance" id="medical_allowance"
                                value="{{ old('medical_allowance', $currentPayroll->medical_allowance ?? '') }}">
                            <input type="hidden" name="children_allowance" id="children_allowance"
                                value="{{ old('children_allowance', $currentPayroll->children_allowance ?? '') }}">
                            <input type="hidden" name="post_allowance" id="post_allowance"
                                value="{{ old('post_allowance', $currentPayroll->post_allowance ?? '') }}">
                            <input type="hidden" name="leave_travel_allowance" id="leave_travel_allowance"
                                value="{{ old('leave_travel_allowance', $currentPayroll->leave_travel_allowance ?? '') }}">
                            <input type="hidden" name="monthly_incentive" id="monthly_incentive"
                                value="{{ old('monthly_incentive', $currentPayroll->monthly_incentive ?? '') }}">
                            <input type="hidden" name="special_allowance" id="special_allowance"
                                value="{{ old('special_allowance', $currentPayroll->special_allowance ?? '') }}">
                            <input type="hidden" name="provident_fund" id="provident_fund"
                                value="{{ old('provident_fund', $currentPayroll->provident_fund ?? '') }}">
                            <input type="hidden" name="employer_provident_fund" id="employer_provident_fund"
                                value="{{ old('employer_provident_fund', $currentPayroll->employer_provident_fund ?? '') }}">
                            <input type="hidden" name="esi" id="esi"
                                value="{{ old('esi', $currentPayroll->esi ?? '') }}">
                            <input type="hidden" name="employer_esi" id="employer_esi"
                                value="{{ old('employer_esi', $currentPayroll->employer_esi ?? '') }}">
                            <input type="hidden" name="professional_tax" id="professional_tax"
                                value="{{ old('professional_tax', $currentPayroll->professional_tax ?? '') }}">
                            <input type="hidden" name="gross_salary" id="gross_salary"
                                value="{{ old('gross_salary', $currentPayroll->gross_salary ?? '') }}">
                            <input type="hidden" name="net_salary" id="net_salary"
                                value="{{ old('net_salary', $currentPayroll->net_salary ?? '') }}">
                            <input type="hidden" name="ctc" id="ctc"
                                value="{{ old('ctc', $currentPayroll->ctc ?? '') }}">
                        </div>
                    </div>

                    <!-- Step 7: Documents -->
                    <div class="step-content" id="step7">
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="feather-file"></i>
                                Document Uploads
                            </h5>

                            <div class="form-group">
                                <label class="form-label">Experience Letter</label>
                                <input type="file" class="form-control" name="experience_letter">
                                @if ($user->basicDetails && $user->basicDetails->experience_letter)
                                    <div class="file-preview">
                                        <span>Current file:</span>
                                        <a href="{{ file_url($user->basicDetails->experience_letter, 'employee_document') }}" target="_blank"
                                            class="existing-file">
                                            <i class="feather-file-text"></i> View Document
                                        </a>
                                    </div>
                                @endif
                                @error('experience_letter')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">10th Marksheet</label>
                                <input type="file" class="form-control" name="tenth_marksheet">
                                @if ($user->basicDetails && $user->basicDetails->tenth_marksheet)
                                    <div class="file-preview">
                                        <span>Current file:</span>
                                        <a href="{{ file_url($user->basicDetails->tenth_marksheet, 'employee_document') }}" target="_blank"
                                            class="existing-file">
                                            <i class="feather-file-text"></i> View Document
                                        </a>
                                    </div>
                                @endif
                                @error('tenth_marksheet')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">12th Marksheet</label>
                                <input type="file" class="form-control" name="twelfth_marksheet">
                                @if ($user->basicDetails && $user->basicDetails->twelfth_marksheet)
                                    <div class="file-preview">
                                        <span>Current file:</span>
                                        <a href="{{ file_url($user->basicDetails->twelfth_marksheet, 'employee_document') }}" target="_blank"
                                            class="existing-file">
                                            <i class="feather-file-text"></i> View Document
                                        </a>
                                    </div>
                                @endif
                                @error('twelfth_marksheet')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Highest Qualification</label>
                                <input type="file" class="form-control" name="highest_qualification_certificate">
                                @if ($user->basicDetails && $user->basicDetails->highest_qualification_certificate)
                                    <div class="file-preview">
                                        <span>Current file:</span>
                                        <a href="{{ file_url($user->basicDetails->highest_qualification_certificate, 'employee_document') }}"
                                            target="_blank" class="existing-file">
                                            <i class="feather-file-text"></i> View Document
                                        </a>
                                    </div>
                                @endif
                                @error('highest_qualification_certificate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert-info mt-4">
                                <i class="feather-info"></i>
                                <span>Upload new files only if you want to replace existing documents.</span>
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
                                <i class="feather-check"></i> Update Employee
                            </button>
                        </div>
                        <a href="{{ route('employee.index') }}" class="btn btn-light-brand">
                            <i class="feather-x"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <script>
        // Configure toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "3000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        let currentStep = 1;
        const totalSteps = 7;
        let employeeId = '{{ $user->employee_id }}';
        let userId = '{{ $user->id }}';

        $(document).ready(function() {
            // Initialize Select2
            // $('.select2').select2({
            //     placeholder: 'Select Languages',
            //     allowClear: true,
            //     width: '100%'
            // });

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

                            @if ($user->location && $user->location->state)
                                $('#state').val('{{ $user->location->state }}');
                            @endif
                        }
                    });
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

                            @if ($user->location && $user->location->city)
                                $('#city').val('{{ $user->location->city }}');
                            @endif
                        }
                    });
                }
            });

            // Trigger country change if country exists
            @if ($user->location && $user->location->country)
                $('#country').trigger('change');
            @endif

            // ==================== PAYROLL CALCULATION FUNCTIONS ====================

            function calculateSalaryBreakdown() {
                const annualCTC = parseFloat($('#annual_ctc').val()) || 0;

                if (annualCTC <= 0) {
                    resetAllFields();
                    return;
                }

                const selectedOption = $('#payroll_structure_id option:selected');
                let calc = {};
                try {
                    calc = JSON.parse(selectedOption.attr('data-calc') || '{}');
                } catch (e) {
                    calc = {};
                }

                const monthlyCTC = annualCTC / 12;

                // Which structure component code feeds which calculator field.
                const ALLOWANCE_FIELDS = {
                    hra: 'hra',
                    conveyance: 'conveyence',
                    medical_allowance: 'medical',
                    children_allowance: 'children',
                    post_allowance: 'post',
                    leave_travel_allowance: 'lta',
                    monthly_incentive: 'incentive'
                };

                let pctSum = 0;
                let fixedSum = 0;
                const pctByKey = {};
                const fixedByKey = {};
                let hasCustomAllowance = false;

                $.each(ALLOWANCE_FIELDS, function(code, key) {
                    const entry = calc[code];
                    if (entry && entry.type === 'percentage' && entry.base === 'basic') {
                        pctByKey[key] = entry.value;
                        pctSum += entry.value;
                        hasCustomAllowance = true;
                    } else if (entry && entry.type === 'fixed') {
                        fixedByKey[key] = entry.value;
                        fixedSum += entry.value;
                        hasCustomAllowance = true;
                    }
                });

                let basic, hra, conveyence, medical, children, post, lta, incentive;

                if (hasCustomAllowance) {
                    // Solve for Basic where Basic + fixed allowances + (Basic * pctSum/100) = monthlyCTC
                    basic = Math.max(0, (monthlyCTC - fixedSum) / (1 + (pctSum / 100)));
                    hra = fixedByKey.hra !== undefined ? fixedByKey.hra : basic * ((pctByKey.hra || 0) / 100);
                    conveyence = fixedByKey.conveyence !== undefined ? fixedByKey.conveyence : basic * ((pctByKey.conveyence || 0) / 100);
                    medical = fixedByKey.medical !== undefined ? fixedByKey.medical : basic * ((pctByKey.medical || 0) / 100);
                    children = fixedByKey.children !== undefined ? fixedByKey.children : basic * ((pctByKey.children || 0) / 100);
                    post = fixedByKey.post !== undefined ? fixedByKey.post : basic * ((pctByKey.post || 0) / 100);
                    lta = fixedByKey.lta !== undefined ? fixedByKey.lta : basic * ((pctByKey.lta || 0) / 100);
                    incentive = fixedByKey.incentive !== undefined ? fixedByKey.incentive : basic * ((pctByKey.incentive || 0) / 100);
                } else {
                    // No Payroll Structure selected, or it has no allowance
                    // components configured yet — standard starting split.
                    basic = monthlyCTC * 0.5;
                    hra = basic * 0.4;
                    conveyence = 1600;
                    medical = 1250;
                    children = 0;
                    post = 0;
                    lta = 0;
                    incentive = 0;
                }

                const totalAllowances = hra + conveyence + medical + children + post + lta + incentive;
                const grossSalary = basic + totalAllowances;

                function percentageDeduction(entry, base, defaultPct, defaultCeiling, defaultRule) {
                    let pct = defaultPct;
                    let ceilingAmount = defaultCeiling;
                    let rule = defaultRule;

                    if (entry && entry.type === 'fixed') {
                        return entry.value;
                    }
                    if (entry && entry.type === 'percentage') {
                        pct = entry.value;
                        ceilingAmount = entry.ceiling_amount != null ? entry.ceiling_amount : null;
                        rule = entry.ceiling_rule || null;
                    }
                    if (!pct || pct <= 0) {
                        return 0;
                    }
                    if (rule === 'ceiling_exclude' && ceilingAmount && base > ceilingAmount) {
                        return 0;
                    }
                    const effectiveBase = (rule === 'cap_base_before_percentage' && ceilingAmount)
                        ? Math.min(base, ceilingAmount)
                        : base;

                    return effectiveBase * (pct / 100);
                }

                const pfDeduction = percentageDeduction(calc.pf_employee, basic, 12, 15000, 'cap_base_before_percentage');
                const employerPf = percentageDeduction(calc.pf_employer, basic, 12, 15000, 'cap_base_before_percentage');
                const esiDeduction = percentageDeduction(calc.esi_employee, grossSalary, 1.75, 21000, 'ceiling_exclude');
                const employerEsi = percentageDeduction(calc.esi_employer, grossSalary, 3.25, 21000, 'ceiling_exclude');

                let professionalTax;
                if (calc.pt && calc.pt.type === 'fixed') {
                    professionalTax = calc.pt.value;
                } else if (calc.pt && calc.pt.type === 'percentage') {
                    professionalTax = grossSalary * (calc.pt.value / 100);
                } else {
                    professionalTax = 200;
                }

                const totalDeductions = pfDeduction + esiDeduction + professionalTax;
                const netSalary = grossSalary - totalDeductions;
                const totalMonthlyCost = grossSalary + employerPf + employerEsi;

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
            }

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

            function setHiddenFields(values) {
                $('#basic_salary').val(values.basic.toFixed(2));
                $('#hra').val(values.hra.toFixed(2));
                $('#conveyence').val(values.conveyence.toFixed(2));
                $('#medical_allowance').val(values.medical.toFixed(2));
                $('#children_allowance').val((values.children || 0).toFixed(2));
                $('#post_allowance').val((values.post || 0).toFixed(2));
                $('#leave_travel_allowance').val((values.lta || 0).toFixed(2));
                $('#monthly_incentive').val((values.incentive || 0).toFixed(2));
                $('#special_allowance').val('0.00');
                $('#gross_salary').val(values.gross.toFixed(2));
                $('#provident_fund').val(values.pf.toFixed(2));
                $('#esi').val(values.esi.toFixed(2));
                $('#professional_tax').val(values.pt.toFixed(2));
                $('#net_salary').val(values.net.toFixed(2));
                $('#employer_provident_fund').val(values.employerPf.toFixed(2));
                $('#employer_esi').val(values.employerEsi.toFixed(2));
                $('#ctc').val((values.gross + values.employerPf + values.employerEsi).toFixed(2));

                // Update salary field in step 5 if it exists
                if ($('input[name="salary"]').length) {
                    $('input[name="salary"]').val(values.gross.toFixed(2));
                }
            }

            function formatNumber(num) {
                if (num === 0) return '0.00';
                let numStr = num.toFixed(2);
                let parts = numStr.split('.');
                let integerPart = parts[0];
                let decimalPart = parts[1];
                let lastThree = integerPart.slice(-3);
                let otherNumbers = integerPart.slice(0, -3);
                if (otherNumbers !== '') {
                    integerPart = otherNumbers.replace(/\B(?=(\d{2})+(?!\d))/g, ",") + "," + lastThree;
                }
                return integerPart + '.' + decimalPart;
            }

            function resetAllFields() {
                // Reset display fields
                const displayFields = ['monthly_basic', 'monthly_hra', 'monthly_conveyance', 'monthly_medical',
                    'monthly_children', 'monthly_post', 'monthly_lta', 'monthly_incentive',
                    'monthly_total_allowances', 'monthly_gross', 'monthly_pf', 'monthly_esi',
                    'monthly_pt', 'monthly_total_deductions', 'monthly_net', 'monthly_employer_pf',
                    'monthly_employer_esi', 'monthly_total_ctc', 'yearly_basic', 'yearly_hra',
                    'yearly_conveyance', 'yearly_medical', 'yearly_children', 'yearly_post',
                    'yearly_lta', 'yearly_incentive', 'yearly_total_allowances', 'yearly_gross',
                    'yearly_pf', 'yearly_esi', 'yearly_pt', 'yearly_total_deductions', 'yearly_net',
                    'yearly_employer_pf', 'yearly_employer_esi', 'yearly_total_ctc'
                ];

                displayFields.forEach(field => {
                    $('#' + field).text('₹0.00');
                });
            }

            // Payroll event listeners
            $('#annual_ctc, #payroll_structure_id').on('change keyup', function() {
                calculateSalaryBreakdown();
            });

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

            // Show first step
            showStep(currentStep);

            // Calculate payroll if data exists
            @if ($user->payrolls && $user->payrolls->where('is_current', true)->first())
                setTimeout(function() {
                    calculateSalaryBreakdown();
                }, 500);
            @endif

            // ==================== STEP NAVIGATION ====================

            // Next button click
            $('#nextBtn').click(function() {
                if (validateStep(currentStep)) {
                    saveStep(currentStep, function() {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            showStep(currentStep);
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });

                            // Trigger calculation when reaching step 6
                            if (currentStep === 6) {
                                setTimeout(calculateSalaryBreakdown, 100);
                            }
                        }
                    });
                }
            });

            // Previous button click
            $('#prevBtn').click(function() {
                if (currentStep > 1) {
                    // Save current step before going back
                    if (validateStep(currentStep)) {
                        saveStep(currentStep, function() {
                            currentStep--;
                            showStep(currentStep);
                            window.scrollTo({
                                top: 0,
                                behavior: 'smooth'
                            });
                        });
                    } else {
                        currentStep--;
                        showStep(currentStep);
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                }
            });

            // Form submission - only on last step
            $('#employeeForm').submit(function(e) {
                e.preventDefault();

                if (currentStep === totalSteps) {
                    if (validateStep(currentStep)) {
                        saveStep(currentStep, function() {
                            submitForm();
                        });
                    }
                } else {
                    toastr.info('Please complete all steps before submitting');
                }
            });
        });

        // ==================== STEP DISPLAY FUNCTION ====================

        function showStep(step) {
            // Hide all steps
            $('.step-content').removeClass('active');
            $(`#step${step}`).addClass('active');

            // Update step indicators
            $('.step-item').removeClass('active completed');
            for (let i = 1; i <= totalSteps; i++) {
                if (i < step) {
                    $(`.step-item[data-step="${i}"]`).removeClass('active').addClass('completed');
                } else if (i === step) {
                    $(`.step-item[data-step="${i}"]`).addClass('active').removeClass('completed');
                }
            }

            // Update buttons
            $('#prevBtn').prop('disabled', step === 1);

            if (step === totalSteps) {
                $('#nextBtn').hide();
                $('#submitBtn').show();
            } else {
                $('#nextBtn').show();
                $('#submitBtn').hide();
            }

            $('#current_step').val(step);
        }

        // ==================== VALIDATION FUNCTIONS ====================

        function validateStep(step) {
            // Remove existing errors
            $(`#step${step} .is-invalid`).removeClass('is-invalid');
            $(`#step${step} .invalid-feedback`).remove();

            let isValid = true;
            const currentStepElement = $(`#step${step}`);

            if (step === 1) {
                // Validate Step 1
                const name = currentStepElement.find('input[name="name"]');
                if (!name.val().trim()) {
                    showError(name, 'Name is required');
                    isValid = false;
                }

                const email = currentStepElement.find('input[name="email"]');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email.val()) {
                    showError(email, 'Email is required');
                    isValid = false;
                } else if (!emailRegex.test(email.val())) {
                    showError(email, 'Please enter a valid email');
                    isValid = false;
                }

                const phone = currentStepElement.find('input[name="contact"]');
                const phoneRegex = /^\d{10}$/;
                if (!phone.val()) {
                    showError(phone, 'Phone number is required');
                    isValid = false;
                } else if (!phoneRegex.test(phone.val())) {
                    showError(phone, 'Phone must be 10 digits');
                    isValid = false;
                }
            }

            if (step === 2) {
                // Validate Step 2 (Personal Information is optional — only
                // validate the format of whatever was actually filled in)

                // Add Aadhaar validation
                const aadhaar = currentStepElement.find('input[name="aadhaar_no"]');
                if (aadhaar.val() && !/^\d{12}$/.test(aadhaar.val())) {
                    showError(aadhaar, 'Please enter a valid 12-digit Aadhaar number');
                    isValid = false;
                }

                // Add PAN validation
                const pan = currentStepElement.find('input[name="pan_no"]');
                if (pan.val() && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.val().toUpperCase())) {
                    showError(pan, 'Please enter a valid PAN number');
                    isValid = false;
                }
            }

            if (step === 3) {
                // Validate Step 3
            }

            if (step === 4) {
                // Validate Step 4
                const permanentAddress = currentStepElement.find('textarea[name="permanent_address"]');
                if (permanentAddress.val() && permanentAddress.val().trim().length > 0) {
                    // Optional validation if needed
                }
            }

            if (step === 5) {
                // Validate Step 5 (Bank Details) - only IFSC format check
                const ifsc = currentStepElement.find('input[name="ifsc"]');
                if (ifsc.val() && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.val().toUpperCase())) {
                    showError(ifsc, 'Please enter a valid IFSC code');
                    isValid = false;
                }
            }

            if (step === 6) {
                // Validate Step 6 (Payroll) — Payroll Structure is optional,
                // only used to pre-fill the calculator.
                const annualCTC = currentStepElement.find('input[name="annual_ctc"]');
                if (!annualCTC.val()) {
                    showError(annualCTC, 'Annual CTC is required');
                    isValid = false;
                } else if (parseFloat(annualCTC.val()) < 10000) {
                    showError(annualCTC, 'Annual CTC must be at least ₹10,000');
                    isValid = false;
                }

                const effectiveDate = currentStepElement.find('input[name="salary_effective_date"]');
                if (!effectiveDate.val()) {
                    showError(effectiveDate, 'Effective date is required');
                    isValid = false;
                }
            }

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

            // Show loading state
            if (nextBtn.is(':visible')) {
                nextBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);
            } else {
                submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled',
                    true);
            }

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('step', step);
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('employee_id', employeeId);
            formData.append('user_id', userId);

            $.ajax({
                url: "{{ route('employee.update.step', ['id' => encrypt($user->id)]) }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Restore button state
                    if (nextBtn.is(':visible')) {
                        nextBtn.html(originalText).prop('disabled', false);
                    } else {
                        submitBtn.html(originalText).prop('disabled', false);
                    }

                    if (response.success) {
                        toastr.success(response.message || `Step ${step} saved successfully`);
                        if (callback) callback();
                    }
                },
                error: function(xhr) {
                    // Restore button state
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
                        $.each(errors, function(field, messages) {
                            const input = $(`[name="${field}"]`);
                            if (input.length) {
                                input.addClass('is-invalid');
                                if (!input.next('.invalid-feedback').length) {
                                    input.after(`<div class="invalid-feedback">${messages[0]}</div>`);
                                }
                            } else {
                                // Handle array fields like language[]
                                const arrayInput = $(`[name="${field}[]"]`);
                                if (arrayInput.length) {
                                    arrayInput.addClass('is-invalid');
                                    if (!arrayInput.next('.invalid-feedback').length) {
                                        arrayInput.after(
                                            `<div class="invalid-feedback">${messages[0]}</div>`);
                                    }
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

        function submitForm() {
            $('#submitBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...').prop(
                'disabled', true);

            const formData = new FormData($('#employeeForm')[0]);
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('employee_id', employeeId);
            formData.append('user_id', userId);

            $.ajax({
                url: "{{ route('employee.complete.update', ['id' => encrypt($user->id)]) }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        toastr.success('Employee updated successfully!');

                        setTimeout(function() {
                            window.location.href = "{{ route('employee.index') }}";
                        }, 1500);
                    } else {
                        $('#submitBtn').html('<i class="feather-check"></i> Update Employee').prop('disabled',
                            false);
                        toastr.error(response.message || 'Error updating employee');
                    }
                },
                error: function(xhr) {
                    $('#submitBtn').html('<i class="feather-check"></i> Update Employee').prop('disabled',
                        false);

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
    </script>
@endsection
