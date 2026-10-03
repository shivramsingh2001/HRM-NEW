{{-- Employee 360 — customise one policy section (attendance / overtime / performance) for this employee
     (employee.profile.policy). A ticked row is saved as this employee's own value; an unticked row follows the company. --}}
@php use App\Services\EmployeePolicyService as Policy; @endphp
<x-p360.form :action="route('employee.profile.policy', ['id' => $encId, 'section' => $section])" reload="policies,attendance" submit="Save">
    <p class="p360-note">Tick a setting to give {{ $user->name }} their own value. Everything left unticked follows the company policy, including later changes to it.</p>
    <div class="table-responsive">
        <table class="p360-table p360-policy-form">
            <thead><tr><th style="width:28px"></th><th>Setting</th><th>Company</th><th style="width:190px">This employee</th></tr></thead>
            <tbody>
                @foreach ($fields as $key => $field)
                    @php
                        $isCustom = array_key_exists($key, $custom);
                        $value = $isCustom ? $custom[$key] : ($company[$key] ?? null);
                    @endphp
                    <tr>
                        <td><input type="checkbox" name="custom[{{ $key }}]" value="1" id="p360c-{{ $key }}" @checked($isCustom)></td>
                        <td>
                            <label for="p360c-{{ $key }}" class="mb-0">{{ $field['label'] }}</label>
                            @isset($field['help'])<div class="p360-note">{{ $field['help'] }}</div>@endisset
                        </td>
                        <td>{{ Policy::display($field, $company[$key] ?? null) }}</td>
                        <td>
                            @if ($field['type'] === 'bool')
                                <select name="value[{{ $key }}]" class="form-control" @disabled(!$isCustom)>
                                    <option value="1" @selected((bool) $value)>Yes</option>
                                    <option value="0" @selected(!$value)>No</option>
                                </select>
                            @elseif ($field['type'] === 'select')
                                <select name="value[{{ $key }}]" class="form-control" @disabled(!$isCustom)>
                                    @foreach ($field['options'] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="number" name="value[{{ $key }}]" class="form-control" @disabled(!$isCustom) required
                                    value="{{ $value === null ? '' : (float) $value }}" min="{{ $field['min'] ?? 0 }}" max="{{ $field['max'] ?? '' }}"
                                    step="{{ $field['type'] === 'int' ? 1 : ($field['step'] ?? 0.01) }}">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-2">
        <a href="#" class="p360-note" data-p360-policy-reset><i class="feather-rotate-ccw"></i> Reset everything to the company policy</a>
    </div>
</x-p360.form>

@include('client.user.profile.forms._policy-script')
