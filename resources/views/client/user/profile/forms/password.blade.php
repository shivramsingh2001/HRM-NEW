{{-- Employee 360 — set a new login password (employee.profile.reset-password) --}}
<x-p360.form :action="route('employee.profile.reset-password', $encId)" submit="Change password">
    <p class="p360-note">Set a new password for {{ $user->name }} ({{ $user->email }}). Share it with the employee yourself — no email is sent.</p>
    <div class="mb-2">
        <label class="form-label">New password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" required minlength="6" maxlength="10" autocomplete="new-password">
        <div class="p360-note mt-1">6 to 10 characters.</div>
    </div>
    <div>
        <label class="form-label">Confirm password <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="6" maxlength="10" autocomplete="new-password">
    </div>
</x-p360.form>
