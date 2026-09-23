{{--
    Shared Add/Edit Job Opening form, embedded once inside #jobDrawer (see
    job-openings/index.blade.php @section('create-modal')). Ported from the
    former standalone create.blade.php/update.blade.php full pages onto the
    480px drawer pattern (matches partials.employee-wizard-form) — one plain
    POST form, JS just swaps the action URL + pre-fills fields for edit mode
    (see openAddJobDrawer()/openEditJobDrawer() below).
--}}
<form action="{{ route('job-openings.store') }}" method="POST" id="jobDrawerForm">
    @csrf

    <div class="form-group">
        <label class="form-label">Job Title <span class="required">*</span></label>
        <input type="text" class="form-control form-control-sm" name="title" id="jf_title"
            placeholder="e.g., Senior Software Engineer" required>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Department</label>
            <select class="form-select form-select-sm" name="department_id" id="jf_department_id">
                <option value="">Select</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Designation</label>
            <select class="form-select form-select-sm" name="designation_id" id="jf_designation_id">
                <option value="">Select</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Employment Type <span class="required">*</span></label>
            <select class="form-select form-select-sm" name="employment_type" id="jf_employment_type" required>
                <option value="" disabled selected>Select</option>
                @foreach ($employmentTypes as $key => $value)
                    <option value="{{ $key }}">{{ $value }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Location</label>
            <input type="text" class="form-control form-control-sm" name="location" id="jf_location"
                placeholder="e.g., Mumbai or Remote">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Vacancies <span class="required">*</span></label>
            <input type="number" class="form-control form-control-sm" name="no_of_vacancies" id="jf_no_of_vacancies"
                min="1" value="1" required>
        </div>
        <div class="form-group">
            <label class="form-label">Status <span class="required">*</span></label>
            <select class="form-select form-select-sm" name="status" id="jf_status" required>
                @foreach ($statuses as $key => $value)
                    <option value="{{ $key }}" @selected($key === 'draft')>{{ $value }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Description <span class="required">*</span></label>
        <textarea class="form-control form-control-sm" name="description" id="jf_description" rows="3"
            placeholder="Brief description of the role..." required></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Experience</label>
            <input type="text" class="form-control form-control-sm" name="experience_required"
                id="jf_experience_required" placeholder="e.g., 3-5 years">
        </div>
        <div class="form-group">
            <label class="form-label">Qualification</label>
            <input type="text" class="form-control form-control-sm" name="qualification_required"
                id="jf_qualification_required" placeholder="e.g., B.Tech, MBA">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Skills</label>
        <textarea class="form-control form-control-sm" name="skills_required" id="jf_skills_required" rows="2"
            placeholder="PHP, Laravel, MySQL, JavaScript"></textarea>
        <div class="profile-help-text">Separate skills with commas</div>
    </div>

    <div class="form-group">
        <label class="form-label">Responsibilities</label>
        <textarea class="form-control form-control-sm" name="responsibilities" id="jf_responsibilities" rows="2"></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">Requirements</label>
        <textarea class="form-control form-control-sm" name="requirements" id="jf_requirements" rows="2"></textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Salary Min</label>
            <input type="number" class="form-control form-control-sm" name="salary_range_min" id="jf_salary_range_min"
                step="10000">
        </div>
        <div class="form-group">
            <label class="form-label">Salary Max</label>
            <input type="number" class="form-control form-control-sm" name="salary_range_max" id="jf_salary_range_max"
                step="10000">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Hiring Lead</label>
        <select class="form-select form-select-sm" name="hiring_lead" id="jf_hiring_lead">
            <option value="">Select Hiring Lead</option>
            @foreach ($hiringLeads as $lead)
                <option value="{{ $lead->id }}">{{ $lead->name }} ({{ $lead->role }})</option>
            @endforeach
        </select>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-sm" id="jobDrawerSubmitBtn">Create Job</button>
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
    </div>
</form>
