{{-- Employee 360 — mark this employee's attendance by hand (team.mark-attendance) --}}
<x-p360.form :action="route('team.mark-attendance')" reload="attendance,leave" submit="Mark attendance">
    <input type="hidden" name="user_id" value="{{ $user->id }}">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" name="date" class="form-control" value="{{ $date }}" max="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Until (optional)</label>
            <input type="date" name="end_date" class="form-control" max="{{ now()->toDateString() }}">
        </div>
        <div class="col-12">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" class="form-control" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6" data-for="present half_day">
            <label class="form-label">Clock in <span class="text-danger">*</span></label>
            <input type="time" name="clock_in" class="form-control">
        </div>
        <div class="col-md-6" data-for="present half_day">
            <label class="form-label">Clock out <span class="text-danger">*</span></label>
            <input type="time" name="clock_out" class="form-control">
        </div>
        <div class="col-12 d-none" data-for="on_leave first_half_leave second_half_leave">
            <label class="form-label">Leave type <span class="text-danger">*</span></label>
            <select name="leave_type_id" class="form-control" disabled>
                <option value="">Select leave type</option>
                @foreach ($leaveTypes as $leaveType)
                    <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                @endforeach
            </select>
            <div class="p360-note mt-1">An approved leave is created and the balance is deducted.</div>
        </div>
        <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
    </div>
    <p class="p360-note mt-2 mb-0">This replaces whatever is recorded for the day, including the employee's own punches.</p>
</x-p360.form>

<script>
    (function() {
        const $form = $('#p360Modal form');
        const sync = () => {
            const status = $form.find('[name=status]').val();
            $form.find('[data-for]').each(function() {
                const show = $(this).data('for').split(' ').includes(status);
                $(this).toggleClass('d-none', !show).find('input, select').prop('disabled', !show).prop('required', show);
            });
        };
        $form.on('change', '[name=status]', sync);
        sync();
    })();
</script>
