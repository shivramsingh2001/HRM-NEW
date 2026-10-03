{{-- Employee 360 — customise the leave rules for this employee, per leave type (employee.profile.policy, section "leave").
     A ticked box is saved as this employee's own value; an unticked one follows the leave type. --}}
@php
    use App\Services\EmployeePolicyService as Policy;
    $companyValue = fn ($type, string $key) => $key === 'allowed' ? true : ($type->{$key} ?? 0);
@endphp
<x-p360.form :action="route('employee.profile.policy', ['id' => $encId, 'section' => 'leave'])" reload="policies,leave" submit="Save">
    <p class="p360-note">Tick a box to give {{ $user->name }} their own rule for that leave type. Everything left unticked follows the leave type, including later changes to it.</p>

    @if ($leaveTypes->isEmpty())
        <div class="alert alert-info mb-0">No leave types are configured.</div>
    @endif

    @foreach ($leaveTypes as $type)
        @php $own = (array) ($custom['type:' . $type->id] ?? []); @endphp
        <div class="p360-sub {{ $loop->first ? 'mt-0' : '' }}">{{ $type->name }}{{ $type->is_unpaid ? ' (unpaid)' : '' }}
            @if ($type->credit_type && $type->credit_type !== 'no')<span class="p360-note fw-normal">· credited {{ $type->credit_type }}</span>@endif
        </div>
        <div class="row g-2">
            @foreach ($fields as $key => $field)
                @php
                    $isCustom = array_key_exists($key, $own);
                    $value = $isCustom ? $own[$key] : $companyValue($type, $key);
                    $name = "[{$type->id}][{$key}]";
                @endphp
                <div class="col-md-6" data-policy-cell>
                    <label class="mb-1">
                        <input type="checkbox" name="custom{{ $name }}" value="1" @checked($isCustom)>
                        {{ $field['label'] }}
                        <span class="p360-note">· company: {{ Policy::display($field, $companyValue($type, $key)) }}{{ isset($field['help']) ? ' (' . $field['help'] . ')' : '' }}</span>
                    </label>
                    @if ($field['type'] === 'bool')
                        <select name="value{{ $name }}" class="form-control" @disabled(!$isCustom)>
                            <option value="1" @selected((bool) $value)>Yes</option>
                            <option value="0" @selected(!$value)>No</option>
                        </select>
                    @else
                        <input type="number" name="value{{ $name }}" class="form-control" @disabled(!$isCustom) required
                            value="{{ (float) $value }}" min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? '' }}"
                            step="{{ $field['type'] === 'int' ? 1 : ($field['step'] ?? 0.01) }}">
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="mt-3">
        <a href="#" class="p360-note" data-p360-policy-reset><i class="feather-rotate-ccw"></i> Reset everything to the company policy</a>
    </div>
</x-p360.form>

@include('client.user.profile.forms._policy-script')
