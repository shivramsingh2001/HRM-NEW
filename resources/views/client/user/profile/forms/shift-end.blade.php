{{-- Employee 360 — end the employee's permanent shift (shift.assignments.end-permanent) --}}
<x-p360.form :action="route('shift.assignments.end-permanent', $assignment->id)" reload="shift" submit="End shift" submitClass="btn-danger">
    <p class="p360-note">End the permanent shift <strong>{{ $assignment->shift_name }}</strong>
        (since {{ \Carbon\Carbon::parse($assignment->start_date)->format('d M Y') }}). It stays in the history; days after the last day have no shift until you assign one.</p>
    <div class="mb-2">
        <label class="form-label">Last day on this shift</label>
        <input type="date" name="end_date" class="form-control" value="{{ now()->toDateString() }}">
    </div>
    <div>
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control" rows="2" maxlength="500"></textarea>
    </div>
</x-p360.form>
