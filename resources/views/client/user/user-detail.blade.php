@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== PROFILE SECTION ==================== */
        .profile-image-large {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: inline-block;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        /* ==================== INFO BOXES ==================== */
        .info-box {
            padding: 12px 10px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            text-align: center;
            transition: all 0.2s;
        }

        .info-box:hover {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.08);
        }

        .info-box h6 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .info-box p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 0;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* ==================== DETAIL ROWS ==================== */
        .detail-row {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        .detail-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
        }

        /* ==================== CONTACT LIST ==================== */
        .contact-list {
            margin-top: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }

        .contact-label i {
            color: var(--primary);
            font-size: 14px;
            width: 18px;
        }

        .contact-value {
            color: #1e293b;
            font-size: 13px;
            font-weight: 500;
        }

        /* ==================== TABS ==================== */
        .nav-tabs {
            border-bottom: 2px solid #f1f5f9;
            padding: 0 10px;
        }

        .nav-tabs .nav-link {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            border: none;
            padding: 12px 16px;
            margin: 0 2px;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
            background: transparent;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom: 2px solid var(--primary);
            margin-bottom: -2px;
        }

        .tab-content {
            padding: 24px 20px;
        }

        /* ==================== SECTION TITLES ==================== */
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .section-title i {
            color: var(--primary);
            font-size: 16px;
        }

        .about-text {
            font-size: 13px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 0;
            padding: 8px 0;
        }

        /* ==================== DOCUMENT CARDS ==================== */
        .document-card {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            transition: all 0.2s;
            height: 100%;
        }

        .document-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 20px rgba(30, 58, 138, 0.08);
            transform: translateY(-2px);
        }

        .document-preview-container {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .document-thumb {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            border-radius: 6px;
        }

        .pdf-icon, .file-icon {
            font-size: 48px !important;
        }

        .pdf-info small, .file-info small {
            font-size: 11px;
            color: #64748b;
        }

        .document-card h6 {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 6px;
        }

        /* ==================== PAYROLL STYLES ==================== */
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .payroll-header h5 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .payroll-code {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        .payroll-effective-date {
            color: #64748b;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .payroll-effective-date i {
            color: var(--primary);
            font-size: 13px;
        }

        .salary-breakdown {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 20px;
        }

        .salary-breakdown table {
            margin-bottom: 0;
        }

        .salary-breakdown td {
            padding: 8px 10px;
            font-size: 12px;
            border: none;
        }

        .salary-breakdown th {
            padding: 10px;
            font-size: 12px;
            font-weight: 600;
            border: none;
        }

        .salary-breakdown .table-primary {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        .salary-breakdown .table-success {
            background: #d1fae5 !important;
            color: #065f46;
        }

        .salary-breakdown .table-danger {
            background: #fee2e2 !important;
            color: #991b1b;
        }

        .salary-breakdown .table-warning {
            background: #fef3c7 !important;
            color: #92400e;
        }

        .salary-breakdown .table-info {
            background: var(--primary-light) !important;
            color: var(--primary);
        }

        .salary-breakdown .table-secondary {
            background: #f1f5f9 !important;
            color: #475569;
        }

        /* ==================== BUTTONS ==================== */
        .btn-group .btn-outline-primary {
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
            padding: 6px 16px;
        }

        .btn-group .btn-outline-primary:hover {
            background: #f8fafc;
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-group .btn-outline-primary.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-light-brand {
            background: #fff;
            color: var(--primary);
            border: 1px solid #e2e8f0;
            font-size: 12px;
            padding: 8px 16px;
            border-radius: 8px;
        }

        .btn-light-brand:hover {
            background: #f8fafc;
            border-color: var(--primary);
        }

        

        /* ==================== ALERTS ==================== */
        .alert-info {
            background: var(--primary-light);
            border: 1px solid #b8daff;
            color: var(--primary);
            padding: 16px;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info i {
            font-size: 18px;
        }

        /* ==================== CARD BODY PADDING ==================== */
        .card-body {
            padding: 24px;
        }

        /* ==================== EMPLOYEE NAME ==================== */
        .employee-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .employee-email {
            font-size: 12px;
            color: #64748b;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .profile-image-large {
                width: 120px;
                height: 120px;
            }

            .employee-name {
                font-size: 16px;
            }

            .tab-content {
                padding: 16px;
            }

            .salary-breakdown {
                padding: 12px;
            }
        }
    </style>
@endsection

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('employee.index') }}">Employees</a></li>
                <li class="breadcrumb-item">Employee Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('employee.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                    <a href="{{ route('employee.index', ['edit_id' => encrypt($user->id), 'edit_code' => $user->employee_id]) }}"
                        class="btn btn-sm btn-primary">
                        <i class="feather-edit me-2"></i>
                        <span>Edit Employee</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        <div class="row g-4">
            <!-- Left Column - Profile Card -->
            <div class="col-xxl-4 col-xl-6">
                <div class="card stretch-full">
                    <div class="card-body">
                        <div class="text-center">
                            <div class="wd-140 ht-140 mx-auto mb-3">
                                @if ($user->basicDetails && $user->basicDetails->profile_image)
                                    <img src="{{ file_url($user->basicDetails->profile_image, 'profile_photo') }}"
                                        class="profile-image-large" alt="{{ $user->name }}">
                                @else
                                    <img src="{{ asset('assets/images/avatar/1.png') }}"
                                        class="profile-image-large" alt="{{ $user->name }}">
                                @endif
                            </div>
                            
                            <h4 class="employee-name">{{ $user->name }}</h4>
                            <div class="employee-email mb-3">{{ $user->email }}</div>

                            <!-- Status Badge -->
                            <div class="mb-4">
                                <span class="status-badge {{ $user->status == 1 ? 'status-active' : 'status-inactive' }}">
                                    {{ $user->status == 1 ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <!-- Info Boxes -->
                            <div class="row g-3 mb-3">
                                <div class="col-5">
                                    <div class="info-box">
                                        <h6>{{ $user->employee_id }}</h6>
                                        <p>Employee ID</p>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <div class="info-box">
                                        <h6>{{ $user->jobDetails->designation_name ?? 'N/A' }}</h6>
                                        <p>Designation</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-box">
                                        <h6>{{ $user->jobDetails->department_name ?? 'N/A' }}</h6>
                                        <p>Department</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact List -->
                            <div class="contact-list">
                                <div class="contact-item">
                                    <span class="contact-label">
                                        <i class="feather-phone"></i>Phone
                                    </span>
                                    <span class="contact-value">{{ $user->contact }}</span>
                                </div>
                                <div class="contact-item">
                                    <span class="contact-label">
                                        <i class="feather-mail"></i>Personal Email
                                    </span>
                                    <span class="contact-value">{{ $user->basicDetails->personal_email ?? 'N/A' }}</span>
                                </div>
                                <div class="contact-item">
                                    <span class="contact-label">
                                        <i class="feather-calendar"></i>Joining Date
                                    </span>
                                    <span class="contact-value">
                                        @if ($user->jobDetails && $user->jobDetails->joining_date)
                                            {{ date('d M, Y', strtotime($user->jobDetails->joining_date)) }}
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Tabs Content -->
            <div class="col-xxl-8 col-xl-6">
                <div class="card">
                    <div class="card-header p-0">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab"
                                    data-bs-target="#overviewTab" role="tab">Overview</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#personalTab" role="tab">Personal</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#jobTab"
                                    role="tab">Job</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#bankTab" role="tab">Bank</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#payrollTab" role="tab">Payroll</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab"
                                    data-bs-target="#documentsTab" role="tab">Documents</a>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <!-- Overview Tab -->
                        <div class="tab-pane fade show active" id="overviewTab" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="section-title">
                                    <i class="feather-user"></i>
                                    About
                                </h5>
                                <p class="about-text">
                                    {{ $user->basicDetails->about ?? 'No description available for this employee.' }}
                                </p>
                            </div>

                            <div>
                                <h5 class="section-title">
                                    <i class="feather-info"></i>
                                    Basic Information
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Full Name</div>
                                        <div class="detail-value">{{ $user->name }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Employee ID</div>
                                        <div class="detail-value">{{ $user->employee_id }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Date of Birth</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->dob)
                                                {{ date('d M, Y', strtotime($user->basicDetails->dob)) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Gender</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->gender)
                                                @php
                                                    $genderMap = ['m' => 'Male', 'f' => 'Female', 'o' => 'Other'];
                                                @endphp
                                                {{ $genderMap[$user->basicDetails->gender] ?? $user->basicDetails->gender }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Official Email</div>
                                        <div class="detail-value">{{ $user->email }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Personal Email</div>
                                        <div class="detail-value">{{ $user->basicDetails->personal_email ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Phone</div>
                                        <div class="detail-value">{{ $user->contact }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Alternate Phone</div>
                                        <div class="detail-value">{{ $user->basicDetails->alternate_phone ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Blood Group</div>
                                        <div class="detail-value">{{ $user->basicDetails->blood_group ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade" id="personalTab" role="tabpanel">
                            <div class="mb-4">
                                <h5 class="section-title">
                                    <i class="feather-users"></i>
                                    Personal & Family Details
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Father's Name</div>
                                        <div class="detail-value">{{ $user->basicDetails->father_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Mother's Name</div>
                                        <div class="detail-value">{{ $user->basicDetails->mother_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Marital Status</div>
                                        <div class="detail-value">
                                            @if ($user->basicDetails && $user->basicDetails->marital_status)
                                                {{ ucfirst($user->basicDetails->marital_status) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Nationality</div>
                                        <div class="detail-value">{{ $user->basicDetails->nationality ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Languages</div>
                                        <div class="detail-value">
                                            @if (isset($languageNames) && !empty($languageNames))
                                                {{ implode(', ', $languageNames) }}
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Aadhaar Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->aadhaar_no ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">PAN Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->pan_no ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Passport Number</div>
                                        <div class="detail-value">{{ $user->basicDetails->passport_number ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h5 class="section-title">
                                    <i class="feather-map-pin"></i>
                                    Address Details
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="detail-label">Country</div>
                                        <div class="detail-value">{{ $user->location->countryDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">State</div>
                                        <div class="detail-value">{{ $user->location->stateDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">City</div>
                                        <div class="detail-value">{{ $user->location->cityDetail->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-label">Pin Code</div>
                                        <div class="detail-value">{{ $user->location->pincode ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">Current Address</div>
                                        <div class="detail-value">{{ $user->location->address ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="detail-label">Permanent Address</div>
                                        <div class="detail-value">{{ $user->location->permanent_address ?? 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Job Details Tab -->
                        <div class="tab-pane fade" id="jobTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-briefcase"></i>
                                Employment Details
                            </h5>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="detail-label">Designation</div>
                                    <div class="detail-value">{{ $user->jobDetails->designation_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Department</div>
                                    <div class="detail-value">{{ $user->jobDetails->department_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Reporting Head(s)</div>
                                    <div class="detail-value">
                                        @forelse (($user->jobDetails->reporting_heads ?? []) as $head)
                                            <span class="badge bg-light text-dark border me-1 mb-1">
                                                {{ $head['name'] }}{{ $head['is_primary'] ? ' (Primary)' : '' }}
                                            </span>
                                        @empty
                                            N/A
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Employment Type</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->employment_type)
                                            {{ ucfirst(str_replace('-', ' ', $user->jobDetails->employment_type)) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Joining Date</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->joining_date)
                                            {{ date('d M, Y', strtotime($user->jobDetails->joining_date)) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Salary</div>
                                    <div class="detail-value">
                                        @if ($user->jobDetails && $user->jobDetails->salary)
                                            ₹ {{ number_format($user->jobDetails->salary, 2) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Tab -->
                        <div class="tab-pane fade" id="bankTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-credit-card"></i>
                                Bank Account Details
                            </h5>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="detail-label">Bank Name</div>
                                    <div class="detail-value">{{ $user->bankDetails->bank_name ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">Account Number</div>
                                    <div class="detail-value">{{ $user->bankDetails->account_number ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">IFSC Code</div>
                                    <div class="detail-value">{{ $user->bankDetails->ifsc ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-12">
                                    <div class="detail-label">Branch Name</div>
                                    <div class="detail-value">{{ $user->bankDetails->branch_name ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payroll Tab -->
                        <div class="tab-pane fade" id="payrollTab" role="tabpanel">
                            @php
                                $currentPayroll = null;
                                if ($user->currentPayroll && $user->currentPayroll->count() > 0) {
                                    $currentPayroll = $user->currentPayroll;;
                                }
                            @endphp

                            @if ($currentPayroll)
                                <div class="payroll-header">
                                    <div>
                                        <h5 class="fw-bold mb-1">Current Payroll</h5>
                                        <div class="payroll-effective-date">
                                            <i class="feather-calendar"></i>
                                            Effective from {{ date('d M, Y', strtotime($currentPayroll->effective_from)) }}
                                        </div>
                                    </div>
                                    <span class="payroll-code">
                                        <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary active payroll-code" id="viewMonthlyBtn">Monthly</button>
                                        <button type="button" class="btn btn-outline-primary payroll-code" id="viewYearlyBtn">Yearly</button>
                                    </div>
                                    </span>
                                </div>
                                <!-- Monthly View -->
                                <div id="monthlyView">
                                    <div class="salary-breakdown">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-primary">
                                                        <th colspan="2">Earnings (Monthly)</th>
                                                    </tr>
                                                    <tr><td>Basic Salary</td><td class="text-end">₹ {{ number_format($currentPayroll->basic_salary, 2) }}</td></tr>
                                                    <tr><td>HRA</td><td class="text-end">₹ {{ number_format($currentPayroll->hra, 2) }}</td></tr>
                                                    <tr><td>Conveyance</td><td class="text-end">₹ {{ number_format($currentPayroll->conveyence, 2) }}</td></tr>
                                                    <tr><td>Medical</td><td class="text-end">₹ {{ number_format($currentPayroll->medical_allowance, 2) }}</td></tr>
                                                    <tr><td>Children Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->children_allowance, 2) }}</td></tr>
                                                    <tr><td>Post Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->post_allowance, 2) }}</td></tr>
                                                    <tr><td>LTA</td><td class="text-end">₹ {{ number_format($currentPayroll->leave_travel_allowance, 2) }}</td></tr>
                                                    <tr><td>Monthly Incentive</td><td class="text-end">₹ {{ number_format($currentPayroll->monthly_incentive, 2) }}</td></tr>
                                                    <tr><td>Special Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->special_allowance, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Total Allowances</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->hra + $currentPayroll->conveyence + $currentPayroll->medical_allowance + $currentPayroll->children_allowance + $currentPayroll->post_allowance + $currentPayroll->leave_travel_allowance + $currentPayroll->monthly_incentive + $currentPayroll->special_allowance, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-warning">
                                                        <td><strong>Gross Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-danger">
                                                        <th colspan="2">Deductions (Monthly)</th>
                                                    </tr>
                                                    <tr><td>Employee PF</td><td class="text-end">₹ {{ number_format($currentPayroll->provident_fund, 2) }}</td></tr>
                                                    <tr><td>Employee ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->esi, 2) }}</td></tr>
                                                    <tr><td>Professional Tax</td><td class="text-end">₹ {{ number_format($currentPayroll->professional_tax, 2) }}</td></tr>
                                                    <tr><td>TDS</td><td class="text-end">₹ {{ number_format($currentPayroll->tds, 2) }}</td></tr>
                                                    <tr class="table-danger">
                                                        <td><strong>Total Deductions</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->provident_fund + $currentPayroll->esi + $currentPayroll->professional_tax + $currentPayroll->tds, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-success">
                                                        <td><strong>Net Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->net_salary, 2) }}</strong></td>
                                                    </tr>
                                                </table>

                                                <table class="table table-sm mt-3">
                                                    <tr class="table-secondary">
                                                        <th colspan="2">Employer Contributions</th>
                                                    </tr>
                                                    <tr><td>Employer PF</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_provident_fund, 2) }}</td></tr>
                                                    <tr><td>Employer ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_esi, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Monthly CTC</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary + $currentPayroll->employer_provident_fund + $currentPayroll->employer_esi, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Yearly View (Initially Hidden) -->
                                <div id="yearlyView" style="display: none;">
                                    <div class="salary-breakdown">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-primary">
                                                        <th colspan="2">Earnings (Yearly)</th>
                                                    </tr>
                                                    <tr><td>Basic Salary</td><td class="text-end">₹ {{ number_format($currentPayroll->basic_salary * 12, 2) }}</td></tr>
                                                    <tr><td>HRA</td><td class="text-end">₹ {{ number_format($currentPayroll->hra * 12, 2) }}</td></tr>
                                                    <tr><td>Conveyance</td><td class="text-end">₹ {{ number_format($currentPayroll->conveyence * 12, 2) }}</td></tr>
                                                    <tr><td>Medical</td><td class="text-end">₹ {{ number_format($currentPayroll->medical_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Children Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->children_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Post Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->post_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>LTA</td><td class="text-end">₹ {{ number_format($currentPayroll->leave_travel_allowance * 12, 2) }}</td></tr>
                                                    <tr><td>Monthly Incentive</td><td class="text-end">₹ {{ number_format($currentPayroll->monthly_incentive * 12, 2) }}</td></tr>
                                                    <tr><td>Special Allowance</td><td class="text-end">₹ {{ number_format($currentPayroll->special_allowance * 12, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Total Allowances</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format(($currentPayroll->hra + $currentPayroll->conveyence + $currentPayroll->medical_allowance + $currentPayroll->children_allowance + $currentPayroll->post_allowance + $currentPayroll->leave_travel_allowance + $currentPayroll->monthly_incentive + $currentPayroll->special_allowance) * 12, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-warning">
                                                        <td><strong>Gross Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->gross_salary * 12, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr class="table-danger">
                                                        <th colspan="2">Deductions (Yearly)</th>
                                                    </tr>
                                                    <tr><td>Employee PF</td><td class="text-end">₹ {{ number_format($currentPayroll->provident_fund * 12, 2) }}</td></tr>
                                                    <tr><td>Employee ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->esi * 12, 2) }}</td></tr>
                                                    <tr><td>Professional Tax</td><td class="text-end">₹ {{ number_format($currentPayroll->professional_tax * 12, 2) }}</td></tr>
                                                    <tr><td>TDS</td><td class="text-end">₹ {{ number_format($currentPayroll->tds * 12, 2) }}</td></tr>
                                                    <tr class="table-danger">
                                                        <td><strong>Total Deductions</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format(($currentPayroll->provident_fund + $currentPayroll->esi + $currentPayroll->professional_tax + $currentPayroll->tds) * 12, 2) }}</strong></td>
                                                    </tr>
                                                    <tr class="table-success">
                                                        <td><strong>Net Salary</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->net_salary * 12, 2) }}</strong></td>
                                                    </tr>
                                                </table>

                                                <table class="table table-sm mt-3">
                                                    <tr class="table-secondary">
                                                        <th colspan="2">Employer Contributions</th>
                                                    </tr>
                                                    <tr><td>Employer PF</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_provident_fund * 12, 2) }}</td></tr>
                                                    <tr><td>Employer ESI</td><td class="text-end">₹ {{ number_format($currentPayroll->employer_esi * 12, 2) }}</td></tr>
                                                    <tr class="table-info">
                                                        <td><strong>Annual CTC</strong></td>
                                                        <td class="text-end"><strong>₹ {{ number_format($currentPayroll->ctc, 2) }}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="feather-info me-2"></i>
                                    No payroll information available for this employee.
                                </div>
                            @endif
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documentsTab" role="tabpanel">
                            <h5 class="section-title">
                                <i class="feather-file-text"></i>
                                Employee Documents
                            </h5>

                            <div class="row g-3">
                                @if (isset($user->documents) && count($user->documents) > 0)
                                    @foreach ($user->documents as $document)
                                        @php
                                            $path = $document['path'];
                                            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
                                            $isImage = in_array($extension, $imageExtensions);
                                            $isPdf = $extension === 'pdf';
                                            $filename = basename($path);
                                        @endphp

                                        <div class="col-md-6 col-lg-4">
                                            <div class="document-card">
                                                <div class="card-body text-center p-3">
                                                    <div class="document-preview-container">
                                                        @if ($isImage)
                                                            <a href="{{ file_url($path, 'employee_document') }}" target="_blank">
                                                                <img src="{{ file_url($path, 'employee_document') }}" class="document-thumb"
                                                                    alt="{{ $document['label'] }}">
                                                            </a>
                                                        @elseif($isPdf)
                                                            <div class="text-center">
                                                                <i class="feather-file-text pdf-icon" style="color: #ff3b30;"></i>
                                                                <div class="mt-2">
                                                                    <small class="text-muted d-block">{{ $filename }}</small>
                                                                    <small class="text-muted">PDF Document</small>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="text-center">
                                                                <i class="feather-file file-icon" style="color: #6c757d;"></i>
                                                                <div class="mt-2">
                                                                    <small class="text-muted d-block">{{ $filename }}</small>
                                                                    <small class="text-muted">{{ strtoupper($extension) }} File</small>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <h6>{{ $document['label'] }}</h6>
                                                    @if (!empty($document['name']))
                                                        <small class="text-muted d-block mb-1">{{ $document['name'] }}</small>
                                                    @endif

                                                    <div class="d-flex gap-2 justify-content-center">
                                                        <a href="{{ file_url($path, 'employee_document') }}" target="_blank"
                                                            class="btn btn-sm btn-light-brand">
                                                            <i class="feather-eye"></i>
                                                        </a>
                                                        <a href="{{ file_url($path, 'employee_document') }}" download="{{ $filename }}"
                                                            class="btn btn-sm btn-primary">
                                                            <i class="feather-download"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="col-12">
                                        <x-ui.empty-state icon="file" title="No documents"
                                            subtitle="No documents have been uploaded for this employee yet." />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // View toggle for payroll
            $('#viewMonthlyBtn').click(function() {
                $(this).addClass('active');
                $('#viewYearlyBtn').removeClass('active');
                $('#monthlyView').show();
                $('#yearlyView').hide();
            });

            $('#viewYearlyBtn').click(function() {
                $(this).addClass('active');
                $('#viewMonthlyBtn').removeClass('active');
                $('#yearlyView').show();
                $('#monthlyView').hide();
            });
        });
    </script>
@endsection