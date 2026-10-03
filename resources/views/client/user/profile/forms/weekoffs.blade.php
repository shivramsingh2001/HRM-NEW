{{-- Employee 360 — weekly off days of this employee (employee.profile.weekoffs) --}}
<x-p360.form :action="route('employee.profile.weekoffs', $encId)" reload="shift" submit="Save week-offs">
    <p class="p360-note">Tick the days {{ $user->name }} is off every week. This replaces their current weekly offs from tomorrow; single week-off dates are not changed.</p>
    <div class="row g-2">
        @foreach ($weekdays as $day)
            <div class="col-6 col-md-4">
                <label><input type="checkbox" name="days[]" value="{{ $day }}" @checked(in_array($day, $selected, true))> {{ $day }}</label>
            </div>
        @endforeach
    </div>
</x-p360.form>
