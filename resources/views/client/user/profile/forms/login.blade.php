{{-- Employee 360 — edit name / login details (employee.update.step, step 1) --}}
<x-p360.form :action="route('employee.update.step', $encId)" reload="page">
    <input type="hidden" name="step" value="1">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Full name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ $user->name }}" required maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-control" required>
                @foreach (['employee' => 'Employee', 'manager' => 'Manager', 'hr' => 'HR'] as $value => $label)
                    <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Official email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Phone <span class="text-danger">*</span></label>
            <input type="text" name="contact" class="form-control" value="{{ $user->contact }}" required maxlength="10" inputmode="numeric">
        </div>
        <div class="col-12">
            <label class="form-label">Profile photo</label>
            <input type="file" name="profile_photo" class="form-control" accept="image/*">
            <div class="p360-note mt-1">Leave empty to keep the current photo.</div>
        </div>
    </div>
</x-p360.form>
