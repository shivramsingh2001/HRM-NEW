{{-- Employee 360 — edit personal details (employee.update.step, step 2) --}}
@php $v = fn (string $field) => $basic->{$field} ?? ''; @endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page">
    <input type="hidden" name="step" value="2">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Personal email</label>
            <input type="email" name="personal_email" class="form-control" value="{{ $v('personal_email') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Alternate phone</label>
            <input type="text" name="alternate_phone" class="form-control" value="{{ $v('alternate_phone') }}" maxlength="10" inputmode="numeric">
        </div>
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control">
                <option value="">Select</option>
                @foreach (['m' => 'Male', 'f' => 'Female', 'o' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected($v('gender') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Date of birth</label>
            <input type="date" name="dob" class="form-control" value="{{ $v('dob') ? \Carbon\Carbon::parse($v('dob'))->toDateString() : '' }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Blood group</label>
            <input type="text" name="blood_group" class="form-control" value="{{ $v('blood_group') }}" maxlength="10">
        </div>
        <div class="col-md-4">
            <label class="form-label">Marital status</label>
            <select name="marital_status" class="form-control">
                <option value="">Select</option>
                @foreach (['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widow' => 'Widowed'] as $value => $label)
                    <option value="{{ $value }}" @selected($v('marital_status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Father's name</label>
            <input type="text" name="father_name" class="form-control" value="{{ $v('father_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Mother's name</label>
            <input type="text" name="mother_name" class="form-control" value="{{ $v('mother_name') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Nationality</label>
            <input type="text" name="nationality" class="form-control" value="{{ $v('nationality') ?: 'Indian' }}">
        </div>
        <div class="col-md-8">
            <label class="form-label">Languages</label>
            <select name="language[]" class="form-control p360-select2" multiple>
                @foreach ($languages as $language)
                    <option value="{{ $language->id }}" @selected(in_array($language->id, $selectedLanguages, true))>{{ $language->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Aadhaar number</label>
            <input type="text" name="aadhaar_no" class="form-control" value="{{ $v('aadhaar_no') }}" maxlength="12" inputmode="numeric">
        </div>
        <div class="col-md-4">
            <label class="form-label">PAN number</label>
            <input type="text" name="pan_no" class="form-control" value="{{ $v('pan_no') }}" maxlength="10">
        </div>
        <div class="col-md-4">
            <label class="form-label">Passport number</label>
            <input type="text" name="passport_number" class="form-control" value="{{ $v('passport_number') }}" maxlength="20">
        </div>
        <div class="col-12">
            <label class="form-label">About</label>
            <textarea name="about" class="form-control" rows="2" maxlength="255">{{ $v('about') }}</textarea>
        </div>
    </div>
</x-p360.form>
