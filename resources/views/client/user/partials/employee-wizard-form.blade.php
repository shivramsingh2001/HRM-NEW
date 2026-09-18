{{--
    Shared Add/Edit Employee wizard, embedded once inside the #employeeDrawer
    offcanvas on the employee list page (client.user.view-user). One DOM
    instance is reused for both modes:
      - Add:  openAddEmployeeDrawer() resets the form and posts to
              employee.save.step / employee.complete.store.
      - Edit: openEditEmployeeDrawer(encryptedId, employeeCode) fetches the
              employee via employee.load.data (existing endpoint) and posts
              to employee.update.step/{id} / employee.complete.update/{id}.
    See the wizard JS in view-user.blade.php's script-area for the mode logic.
--}}
<form id="employeeForm" enctype="multipart/form-data" novalidate>
    @csrf
    <input type="hidden" name="current_step" id="current_step" value="1">
    <input type="hidden" name="form_step" id="form_step" value="1">
    <input type="hidden" name="employee_id" id="employee_id" value="">

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

    <div id="employeeFormError" class="alert alert-danger d-none"></div>

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
                    <input type="file" name="profile_photo" id="profilePhotoInput" class="d-none" accept="image/*">
                </div>
                <div class="profile-help-text">
                    <p class="mb-1"><i class="feather-info me-1"></i> Upload profile picture</p>
                    <small>Max size: 2MB (JPG, PNG)</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Full Name <span class="required">*</span></label>
                <input type="text" class="form-control" name="name" placeholder="Enter full name" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Official Email <span class="required">*</span></label>
                    <input type="email" class="form-control" name="email" placeholder="official@company.com"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number <span class="required">*</span></label>
                    <input type="tel" class="form-control" name="contact" placeholder="10 digit mobile" required>
                </div>
            </div>

            <div class="form-row" id="passwordRow">
                <div class="form-group">
                    <label class="form-label">Password <span class="required password-required">*</span></label>
                    <input type="password" class="form-control" name="password" placeholder="6-10 characters">
                    <small class="text-muted d-none" id="editPasswordHint">Leave blank to keep the current
                        password.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password <span
                            class="required password-required">*</span></label>
                    <input type="password" class="form-control" name="password_confirmation"
                        placeholder="Re-enter password">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                        <option value="hr">HR</option>
                    </select>
                </div>

                <div class="form-group d-none" id="statusFieldWrapper">
                    <label class="form-label">Status <span class="required">*</span></label>
                    <select name="status" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
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
                    <input type="email" class="form-control" name="personal_email" placeholder="personal@email.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Alternate Phone</label>
                    <input type="tel" class="form-control" name="alternate_phone" placeholder="Alternate number">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="m">Male</option>
                        <option value="f">Female</option>
                        <option value="o">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="dob">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Blood Group</label>
                    <input type="text" class="form-control" name="blood_group" placeholder="e.g., O+">
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
                    <label class="form-label">Father's Name</label>
                    <input type="text" class="form-control" name="father_name" placeholder="Father's name">
                </div>

                <div class="form-group">
                    <label class="form-label">Mother's Name</label>
                    <input type="text" class="form-control" name="mother_name" placeholder="Mother's name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Aadhaar Number</label>
                    <input type="text" class="form-control" name="aadhaar_no" placeholder="12 digit Aadhaar">
                </div>

                <div class="form-group">
                    <label class="form-label">PAN Number</label>
                    <input type="text" class="form-control" name="pan_no" placeholder="PAN number">
                </div>
            </div>
            <div class="form-group">
                <div class="form-group">
                    <label class="form-label">Passport Number</label>
                    <input type="text" class="form-control" name="passport_number" placeholder="Passport number">
                </div>
                <label class="form-label">Languages</label>
                <select class="form-control select2" name="language[]" multiple>
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
                    <label class="form-label">Reporting Head(s)</label>
                    <select name="reporting_head[]" class="form-control select2" multiple>
                        @foreach ($reportingHeads as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">First selected is treated as the primary reporting head.</small>
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
                    <label class="form-label">Attendance Location <span class="required">*</span></label>
                    <select name="branch" class="form-control" required>
                        <option value="">Select Attendance Location</option>
                        <option value="0">All Locations</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Where this employee is allowed to check in from.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Branch</label>
                    <select name="company_branch" class="form-control">
                        <option value="">-- None --</option>
                        @foreach ($companyBranches ?? [] as $companyBranch)
                            <option value="{{ $companyBranch->id }}">{{ $companyBranch->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Optional — only needed for companies with multiple physical
                        branches.</small>
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
                        @foreach ($leave_types as $leave_type)
                            <option value="{{ $leave_type->id }}">
                                {{ $leave_type->name }} ({{ ucfirst($leave_type->credit_type) }}:
                                {{ $leave_type->credit_value }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Select multiple leave types that this employee is eligible for</small>
                </div>
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
                            <option value="{{ $country->country_code }}">{{ $country->name }}</option>
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
                    <input type="text" class="form-control" name="pin_code" placeholder="6 digit pin code">
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
                    <input type="text" class="form-control" name="account_number" placeholder="Account number">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">IFSC Code</label>
                    <input type="text" class="form-control" name="ifsc" placeholder="IFSC code">
                </div>

                <div class="form-group">
                    <label class="form-label">Branch Name</label>
                    <input type="text" class="form-control" name="branch_name" placeholder="Branch name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">UAN Number</label>
                    <input type="text" class="form-control" name="uan_no" placeholder="UAN Number">
                </div>

                <div class="form-group">
                    <label class="form-label">PF Number</label>
                    <input type="text" class="form-control" name="pf_no" placeholder="PF number">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ESIC Number</label>
                    <input type="text" class="form-control" name="esic_no" placeholder="ESIC Number">
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
                    <label class="form-label">Payroll Structure</label>
                    <select name="payroll_structure_id" id="payroll_structure_id" class="form-control">
                        <option value="" selected>Select Payroll Structure (optional)</option>
                        @foreach ($payrollStructures ?? [] as $structure)
                            <option value="{{ $structure->id }}"
                                data-calc='{{ json_encode($structure->calculatorComponents()) }}'>
                                {{ $structure->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Optional — used only to pre-fill the calculator below
                        from that structure's configured components. The figures saved are always
                        whatever is shown below.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Annual CTC (₹) <span class="required">*</span></label>
                    <input type="number" step="1000" class="form-control" name="annual_ctc" id="annual_ctc"
                        placeholder="e.g., 600000">
                    <small class="text-muted">Enter total yearly CTC (including all components)</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Effective From <span class="required">*</span></label>
                    <input type="date" class="form-control" name="salary_effective_date" id="salary_effective_date"
                        required>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="d-flex justify-content-end mb-3">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="monthlyViewBtn">Monthly</button>
                    <button type="button" class="btn btn-outline-primary" id="yearlyViewBtn">Yearly</button>
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
                                    <td class="text-end"><strong><span id="monthly_basic">0.00</span></strong></td>
                                </tr>
                                <tr class="table-warning">
                                    <td><strong>Gross Salary:</strong></td>
                                    <td class="text-end"><strong><span id="monthly_gross">0.00</span></strong></td>
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
                                    <td class="text-end text-danger"><span id="monthly_pf">0.00</span></td>
                                </tr>
                                <tr>
                                    <td>Employee ESI:</td>
                                    <td class="text-end text-danger"><span id="monthly_esi">0.00</span></td>
                                </tr>
                                <tr>
                                    <td>Professional Tax:</td>
                                    <td class="text-end text-danger"><span id="monthly_pt">0.00</span></td>
                                </tr>
                                <tr class="table-danger">
                                    <td><strong>Total Deductions:</strong></td>
                                    <td class="text-end"><strong><span
                                                id="monthly_total_deductions">0.00</span></strong></td>
                                </tr>
                                <tr class="table-success">
                                    <td><strong>Net Salary:</strong></td>
                                    <td class="text-end"><strong><span id="monthly_net">0.00</span></strong></td>
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
                                    <td class="text-end"><strong><span id="monthly_total_ctc">0.00</span></strong>
                                    </td>
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
                                    <td class="text-end"><strong><span id="yearly_basic">0.00</span></strong></td>
                                </tr>
                                <tr class="table-warning">
                                    <td><strong>Gross Salary:</strong></td>
                                    <td class="text-end"><strong><span id="yearly_gross">0.00</span></strong></td>
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
                                    <td class="text-end text-danger"><span id="yearly_esi">0.00</span></td>
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
                                    <td class="text-end"><strong><span id="yearly_net">0.00</span></strong></td>
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
                                    <td class="text-end"><strong><span id="yearly_total_ctc">0.00</span></strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

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
            <input type="hidden" name="employer_provident_fund" id="employer_provident_fund">
            <input type="hidden" name="esi" id="esi">
            <input type="hidden" name="employer_esi" id="employer_esi">
            <input type="hidden" name="professional_tax" id="professional_tax">
            <input type="hidden" name="gross_salary" id="gross_salary">
            <input type="hidden" name="net_salary" id="net_salary">
            <input type="hidden" name="ctc" id="ctc">
        </div>
    </div>

    <!-- Step 7: Documents -->
    <div class="step-content" id="step7">
        <div class="form-section">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="section-title mb-0">Document Uploads</h5>
                <button type="button" class="btn-icon" id="addDocumentRowBtn" title="Add Document">
                    <i class="feather-plus"></i>
                </button>
            </div>

            <div id="documentsContainer" class="mt-3"></div>

            <div class="alert-info mt-4">
                <i class="feather-info"></i>
                <span>Please review all information before final submission.</span>
            </div>
        </div>
    </div>

    <template id="documentRowTemplate">
        <div class="form-row document-row align-items-start">
            <input type="hidden" class="doc-id-input">
            <div class="form-group">
                <label class="form-label">Document Type</label>
                <select class="form-control doc-type-select">
                    <option value="">Select Type</option>
                    @foreach (\App\Models\EmployeeDocument::$documentTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group doc-type-other-group d-none">
                <label class="form-label">Please Specify</label>
                <input type="text" class="form-control doc-type-other-input" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">Document Name (optional)</label>
                <input type="text" class="form-control doc-name-input" maxlength="150">
            </div>
            <div class="form-group">
                <label class="form-label">File</label>
                <div class="doc-current-file small mb-1"></div>
                <input type="file" class="file-input doc-file-input">
            </div>
            <button type="button" class="btn-icon remove-document-row" title="Remove Document">
                <i class="feather-trash-2"></i>
            </button>
        </div>
    </template>

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
                <i class="feather-check"></i> <span id="submitBtnLabel">Submit</span>
            </button>
        </div>
        <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">
            <i class="feather-x"></i> Cancel
        </button>
    </div>
</form>
