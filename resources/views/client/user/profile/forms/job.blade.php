{{-- Employee 360 — edit job details (employee.update.step, step 3) --}}
@php $v = fn (string $field) => $job->{$field} ?? ''; @endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page">
    <input type="hidden" name="step" value="3">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Department</label>
            <select name="department" class="form-control">
                <option value="">-- None --</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected($v('department') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Designation</label>
            <select name="designation" class="form-control">
                <option value="">-- None --</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" @selected($v('designation') == $designation->id)>{{ $designation->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Reporting head(s)</label>
            <select name="reporting_head[]" class="form-control p360-select2" multiple>
                {{-- Selected heads first, primary on top: the first one is saved as the primary head. --}}
                @foreach ($heads->sortBy(fn ($h) => ($i = array_search($h->id, $selectedHeads)) === false ? 999 : $i) as $head)
                    <option value="{{ $head->id }}" @selected(in_array($head->id, $selectedHeads))>{{ $head->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Employment type</label>
            <select name="employment_type" class="form-control">
                @foreach (['full-time' => 'Full-Time', 'part-time' => 'Part-Time', 'contract' => 'Contract'] as $value => $label)
                    <option value="{{ $value }}" @selected(($v('employment_type') ?: 'full-time') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Joining date</label>
            <input type="date" name="joining_date" class="form-control" value="{{ $v('joining_date') ? \Carbon\Carbon::parse($v('joining_date'))->toDateString() : '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Work type</label>
            <select name="type" class="form-control">
                @foreach (['office' => 'Office', 'field' => 'Field'] as $value => $label)
                    <option value="{{ $value }}" @selected(($v('type') ?: 'office') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Attendance location</label>
            <select name="branch" class="form-control">
                <option value="">{{ $locations->count() > 1 ? 'All Locations' : '-- None --' }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected($v('office_branch') == $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Branch</label>
            <select name="company_branch" class="form-control">
                <option value="">-- None --</option>
                @foreach ($companyBranches as $companyBranch)
                    <option value="{{ $companyBranch->id }}" @selected($v('branch_id') == $companyBranch->id)>{{ $companyBranch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Leave types this employee can use</label>
            <select name="leave_type_assigned[]" class="form-control p360-select2" multiple>
                @foreach ($leaveTypes as $leaveType)
                    <option value="{{ $leaveType->id }}" @selected(in_array($leaveType->id, $selectedLeaveTypes, true))>{{ $leaveType->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</x-p360.form>
