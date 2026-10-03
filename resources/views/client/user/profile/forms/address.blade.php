{{-- Employee 360 — edit address (employee.update.step, step 4) --}}
@php $v = fn (string $field) => $location->{$field} ?? ''; @endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page">
    <input type="hidden" name="step" value="4">
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label">Country</label>
            <select name="country" class="form-control" id="p360Country">
                <option value="">Select country</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->country_code }}" @selected($v('country') == $country->country_code)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">State</label>
            <select name="state" class="form-control" id="p360State">
                <option value="">Select state</option>
                @foreach ($states as $state)
                    <option value="{{ $state->state_code }}" @selected($v('state') == $state->state_code)>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">City</label>
            <select name="city" class="form-control" id="p360City">
                <option value="">Select city</option>
                @foreach ($cities as $city)
                    <option value="{{ $city->city_code }}" @selected($v('city') == $city->city_code)>{{ $city->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Pin code</label>
            <input type="text" name="pin_code" class="form-control" value="{{ $v('pincode') }}" maxlength="6" inputmode="numeric">
        </div>
        <div class="col-12">
            <label class="form-label">Current address</label>
            <textarea name="current_address" class="form-control" rows="2">{{ $v('address') }}</textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Permanent address</label>
            <textarea name="permanent_address" class="form-control" rows="2">{{ $v('permanent_address') }}</textarea>
        </div>
    </div>
</x-p360.form>

<script>
    (function() {
        const fill = ($select, placeholder, rows, valueKey) => {
            $select.html('<option value="">' + placeholder + '</option>');
            rows.forEach(r => $select.append($('<option>').val(r[valueKey]).text(r.name)));
        };
        $('#p360Country').on('change', function() {
            fill($('#p360State'), 'Select state', [], 'state_code');
            fill($('#p360City'), 'Select city', [], 'city_code');
            if (this.value) $.get(@json(route('get.states')), { country_code: this.value }, rows => fill($('#p360State'), 'Select state', rows, 'state_code'));
        });
        $('#p360State').on('change', function() {
            fill($('#p360City'), 'Select city', [], 'city_code');
            if (this.value) $.get(@json(route('get.cities')), { state_code: this.value }, rows => fill($('#p360City'), 'Select city', rows, 'city_code'));
        });
    })();
</script>
