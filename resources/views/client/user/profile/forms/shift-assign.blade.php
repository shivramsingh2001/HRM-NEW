{{-- Employee 360 — assign a shift to this employee (shift.assign with user_ids = [this employee]) --}}
@php use App\Http\Controllers\User\EmployeeProfileController as P; @endphp
<x-p360.form :action="route('shift.assign')" reload="shift" submit="Assign shift">
    <input type="hidden" name="assign_type" value="user">
    <input type="hidden" name="user_ids[]" value="{{ $user->id }}">

    @if ($shifts->isEmpty())
        <div class="alert alert-info mb-2">No active shift exists yet. <a href="{{ route('shift.index') }}">Create one in Manage Shifts</a>.</div>
    @endif

    <div class="row g-2">
        <div class="col-12">
            <label class="form-label">Shift(s) <span class="text-danger">*</span></label>
            <select name="shift_ids[]" class="form-control p360-select2" multiple data-placeholder="Select one or more shifts">
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->name }} · {{ P::shiftLabel($shift) }}</option>
                @endforeach
            </select>
            <div class="p360-note mt-1">With two or more, the one that starts earliest is the main shift and the others are added as additional shifts on the same days.</div>
        </div>
        <div class="col-12">
            <label class="form-label d-block">How long?</label>
            <label class="me-3"><input type="radio" name="type" value="permanent" checked> Permanent — every working day until changed</label>
            <label><input type="radio" name="type" value="flexible"> Only for selected dates</label>
        </div>
        <div class="col-md-6">
            <label class="form-label">From <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-md-6 d-none" data-flexible>
            <label class="form-label">To</label>
            <input type="date" name="end_date" class="form-control" disabled>
            <div class="p360-note mt-1">Up to 90 days. Empty = the From date only.</div>
        </div>
        <div class="col-12">
            <label><input type="checkbox" name="is_additional" value="1"> Add as an additional shift (2nd shift on the same days — the main shift stays)</label>
        </div>
        <div class="col-12 d-none" data-flexible>
            <label><input type="checkbox" name="override_existing" value="1" disabled> Replace shifts already assigned on these dates</label>
        </div>
    </div>

    @if ($activePermanent)
        <p class="p360-note mt-2 mb-0" data-permanent-note>Current permanent shift: <strong>{{ $activePermanent->shift_name }}</strong>
            since {{ \Carbon\Carbon::parse($activePermanent->start_date)->format('d M Y') }}. A new permanent shift ends it and keeps it in history.</p>
    @endif
</x-p360.form>

<script>
    (function() {
        const $form = $('#p360Modal form');
        const sync = () => {
            const flexible = $form.find('[name=type]:checked').val() === 'flexible';
            const additional = $form.find('[name=is_additional]').is(':checked');
            $form.find('[data-flexible]').toggleClass('d-none', !flexible).find('input').prop('disabled', !flexible);
            // An additional shift never replaces anything.
            $form.find('[name=override_existing]').closest('[data-flexible]').toggleClass('d-none', !flexible || additional);
            $form.find('[data-permanent-note]').toggleClass('d-none', flexible || additional);
        };
        $form.on('change', '[name=type], [name=is_additional]', sync);
        sync();
    })();
</script>
