{{-- Employee 360 — apply a leave for the employee (employee.profile.apply-leave) --}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $sessions = ['fullday' => 'Full day', 'session1' => 'First half', 'session2' => 'Second half'];
@endphp
<x-p360.form :action="route('employee.profile.apply-leave', $encId)" reload="leave,attendance" submit="Apply leave">
    <div class="row g-2">
        <div class="col-12">
            <label class="form-label">Leave type <span class="text-danger">*</span></label>
            <select name="leave_type" class="form-control" required>
                <option value="">Select leave type</option>
                @foreach ($leaveTypes as $leaveType)
                    <option value="{{ $leaveType->id }}">{{ $leaveType->name }} — balance {{ $fmt($balances[$leaveType->id] ?? 0) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">From <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">First day</label>
            <select name="start_session" class="form-control">
                @foreach ($sessions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">To <span class="text-danger">*</span></label>
            <input type="date" name="end_date" class="form-control" value="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Last day</label>
            <select name="end_session" class="form-control">
                @foreach ($sessions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" class="form-control" rows="2" maxlength="500" required></textarea>
        </div>
    </div>
    <p class="p360-note mt-2 mb-0">The leave is approved right away and the balance is deducted. Weekends and holidays in the range are not counted.</p>
</x-p360.form>
