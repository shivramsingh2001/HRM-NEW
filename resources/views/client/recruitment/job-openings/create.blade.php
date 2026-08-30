{{-- resources/views/recruitment/job-openings/create.blade.php --}}
@extends('client.layout.master')

@section('style')
    <style>
        /* Form Validation Styles */
        .error-message {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 4px;
            display: block;
        }

        .has-error {
            border-color: #dc3545 !important;
        }

        /* Alert Styles */
        .alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 13px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }

        /* Form Sections */
        .form-section {
            background: #fff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #eef2f6;
        }

        .form-section-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eef2f6;
            color: #1e293b;
        }

        .form-section-title i {
            margin-right: 6px;
            font-size: 14px;
        }

        /* Form Row - Compact */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .form-label {
            flex: 0 0 140px;
            font-weight: 600;
            font-size: 13px;
            padding-top: 8px;
            color: #334155;
        }

        .form-label.required::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }

        .form-field {
            flex: 1;
        }

        /* Input Styles */
        .form-control,
        .form-select {
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 13px;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        select.form-select {
            padding: 6px 10px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }

        textarea.form-control.small-textarea {
            min-height: 50px;
        }

        /* Two Column Layout */
        .two-columns {
            display: flex;
            gap: 15px;
        }

        .column {
            flex: 1;
        }

        /* Help Text */
        .help-text {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Salary Row */
        .salary-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .salary-group .form-control {
            width: auto;
            flex: 1;
        }

        .salary-separator {
            color: #64748b;
            font-size: 13px;
        }

        /* Status Badge */
        .status-hint {
            background: #f1f5f9;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 8px;
        }

        /* Card Footer */
        .card-footer {
            background: #fff;
            padding: 12px 20px;
            border-top: 1px solid #eef2f6;
            border-radius: 0 0 8px 8px;
            margin-top: 15px;
        }

        /* Button Styles */
        .btn {
            padding: 6px 16px;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn i {
            margin-right: 5px;
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }

            .form-label {
                flex: none;
                margin-bottom: 5px;
                padding-top: 0;
            }

            .two-columns {
                flex-direction: column;
                gap: 0;
            }

            .main-content {
                padding: 15px !important;
            }

            .form-section {
                padding: 12px 15px;
            }
        }

        /* Select2 Custom */
        .select2-container--default .select2-selection--single {
            border-color: #e2e8f0;
            border-radius: 6px;
            height: 34px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            font-size: 13px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 32px;
        }
    </style>
@endsection

@php
    $user = Auth::user();
    $role = $user->role;
@endphp

@section('content-area')
    <div class="page-header" style="margin-bottom: 15px;">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-5" style="font-size: 18px;">Job Openings Management</h5>
            </div>
            <ul class="breadcrumb" style="margin-left: 15px;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('job-openings.index') }}">Job Openings</a></li>
                <li class="breadcrumb-item active">Create New</li>
            </ul>
        </div>
    </div>

    <div class="main-content" style="padding: 15px 20px !important;">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle" style="margin-right: 8px;"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                    style="font-size: 10px;"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle" style="margin-right: 8px;"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"
                    style="font-size: 10px;"></button>
            </div>
        @endif

        <form action="{{ route('job-openings.store') }}" method="POST" enctype="multipart/form-data" id="jobForm">
            @csrf

            <!-- Basic Information -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="feather-info"></i> Basic Information
                </div>

                <div class="form-row">
                    <div class="form-label required">Job Title</div>
                    <div class="form-field">
                        <input type="text" class="form-control @error('title') has-error @enderror" id="title"
                            name="title" placeholder="e.g., Senior Software Engineer" value="{{ old('title') }}"
                            required>
                        @error('title')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="two-columns">
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label">Department</div>
                            <div class="form-field">
                                <select class="form-control select2 @error('department_id') has-error @enderror"
                                    name="department_id" id="department_id">
                                    <option value="">Select</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                            {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label">Designation</div>
                            <div class="form-field">
                                <select class="form-control select2 @error('designation_id') has-error @enderror"
                                    name="designation_id" id="designation_id">
                                    <option value="">Select</option>
                                    @foreach ($designations as $designation)
                                        <option value="{{ $designation->id }}"
                                            {{ old('designation_id') == $designation->id ? 'selected' : '' }}>
                                            {{ $designation->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('designation_id')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="two-columns">
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label required">Employment Type</div>
                            <div class="form-field">
                                <select class="form-control select2 @error('employment_type') has-error @enderror"
                                    name="employment_type" id="employment_type" required>
                                    <option value="" disabled selected>Select</option>
                                    @foreach ($employmentTypes as $key => $value)
                                        <option value="{{ $key }}"
                                            {{ old('employment_type') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('employment_type')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label">Location</div>
                            <div class="form-field">
                                <input type="text" class="form-control @error('location') has-error @enderror"
                                    id="location" name="location" placeholder="e.g., Mumbai or Remote"
                                    value="{{ old('location') }}">
                                @error('location')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="two-columns">
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label required">Vacancies</div>
                            <div class="form-field">
                                <input type="number" class="form-control @error('no_of_vacancies') has-error @enderror"
                                    id="no_of_vacancies" name="no_of_vacancies" min="1"
                                    value="{{ old('no_of_vacancies', 1) }}" required>
                                @error('no_of_vacancies')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label required">Status</div>
                            <div class="form-field">
                                <select class="form-control select2 @error('status') has-error @enderror" name="status"
                                    id="status" required>
                                    @foreach ($statuses as $key => $value)
                                        <option value="{{ $key }}"
                                            {{ old('status', 'draft') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                                <div class="help-text"><i class="feather-info"></i> Draft: Internal only | Published:
                                    Visible on career page</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-label required">Description</div>
                    <div class="form-field">
                        <textarea class="form-control @error('description') has-error @enderror" id="description" name="description"
                            rows="3" placeholder="Brief description of the role..." required>{{ old('description') }}</textarea>
                        @error('description')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Requirements & Skills -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="feather-award"></i> Requirements & Skills
                </div>

                <div class="two-columns">
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label">Experience</div>
                            <div class="form-field">
                                <input type="text"
                                    class="form-control @error('experience_required') has-error @enderror"
                                    id="experience_required" name="experience_required" placeholder="e.g., 3-5 years"
                                    value="{{ old('experience_required') }}">
                                @error('experience_required')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="form-row">
                            <div class="form-label">Qualification</div>
                            <div class="form-field">
                                <input type="text"
                                    class="form-control @error('qualification_required') has-error @enderror"
                                    id="qualification_required" name="qualification_required"
                                    placeholder="e.g., B.Tech, MBA" value="{{ old('qualification_required') }}">
                                @error('qualification_required')
                                    <span class="error-message">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-label">Skills</div>
                    <div class="form-field">
                        <textarea class="form-control small-textarea @error('skills_required') has-error @enderror" id="skills_required"
                            name="skills_required" rows="2" placeholder="PHP, Laravel, MySQL, JavaScript">{{ old('skills_required') }}</textarea>
                        @error('skills_required')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                        <div class="help-text">Separate skills with commas</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-label">Responsibilities</div>
                    <div class="form-field">
                        <textarea class="form-control small-textarea @error('responsibilities') has-error @enderror" id="responsibilities"
                            name="responsibilities" rows="2" placeholder="Key responsibilities for this role">{{ old('responsibilities') }}</textarea>
                        @error('responsibilities')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-label">Requirements</div>
                    <div class="form-field">
                        <textarea class="form-control small-textarea @error('requirements') has-error @enderror" id="requirements"
                            name="requirements" rows="2" placeholder="Additional requirements or preferences">{{ old('requirements') }}</textarea>
                        @error('requirements')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Compensation -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="feather-dollar-sign"></i> Compensation
                </div>

                <div class="form-row">
                    <div class="form-label">Salary Range</div>
                    <div class="form-field">
                        <div class="salary-group">
                            <input type="number" class="form-control @error('salary_range_min') has-error @enderror"
                                id="salary_range_min" name="salary_range_min" step="10000" placeholder="Min"
                                value="{{ old('salary_range_min') }}">
                            <span class="salary-separator">-</span>
                            <input type="number" class="form-control @error('salary_range_max') has-error @enderror"
                                id="salary_range_max" name="salary_range_max" step="10000" placeholder="Max"
                                value="{{ old('salary_range_max') }}">
                        </div>
                        @error('salary_range_min')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                        @error('salary_range_max')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                        <div class="help-text">Leave blank if negotiable</div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="feather-settings"></i> Additional Information
                </div>

                <div class="form-row">
                    <div class="form-label">Hiring Lead</div>
                    <div class="form-field">
                        <select class="form-control select2 @error('hiring_lead') has-error @enderror" name="hiring_lead"
                            id="hiring_lead">
                            <option value="">Select Hiring Lead</option>
                            @foreach ($hiringLeads as $lead)
                                <option value="{{ $lead->id }}"
                                    {{ old('hiring_lead') == $lead->id ? 'selected' : '' }}>
                                    {{ $lead->name }} ({{ $lead->role }})
                                </option>
                            @endforeach
                        </select>
                        @error('hiring_lead')
                            <span class="error-message">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">

                <!-- Left Side Buttons -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-1"></i> Create Job
                    </button>

                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="feather-rotate-ccw me-1"></i> Reset
                    </button>
                </div>

                <!-- Right Side Button -->
                <button type="button" class="btn btn-outline-danger"
                    onclick="window.location.href='{{ route('job-openings.index') }}'">
                    <i class="feather-x me-1"></i> Cancel
                </button>

            </div>
        </form>
    </div>
@endsection

@section('script-area')
    <script>
        $(document).ready(function() {
            // Initialize select2 with compact styling
            $('.select2').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Select option'
            });

            // Salary range validation
            const minSalary = document.getElementById('salary_range_min');
            const maxSalary = document.getElementById('salary_range_max');

            function validateSalary() {
                if (minSalary.value && maxSalary.value) {
                    if (parseFloat(maxSalary.value) < parseFloat(minSalary.value)) {
                        maxSalary.setCustomValidity('Max must be >= Min');
                        maxSalary.classList.add('has-error');
                    } else {
                        maxSalary.setCustomValidity('');
                        maxSalary.classList.remove('has-error');
                    }
                } else {
                    maxSalary.setCustomValidity('');
                }
            }

            if (minSalary) minSalary.addEventListener('change', validateSalary);
            if (maxSalary) maxSalary.addEventListener('change', validateSalary);

            // Auto-hide alerts after 4 seconds
            setTimeout(function() {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        });
    </script>
@endsection
