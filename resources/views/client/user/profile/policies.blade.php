{{-- Employee 360 — the policies this employee follows (EmployeeProfileController::tabPolicies): the company value of
     every setting, and this employee's custom value where one is set (employee_policy_overrides). --}}
@php
    use App\Http\Controllers\User\EmployeeProfileController as P;
    use App\Services\EmployeePolicyService as Policy;
    $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q);
    $titles = ['attendance' => 'Attendance policy', 'overtime' => 'Overtime', 'performance' => 'Performance scoring',
        'requests' => 'WFH & regularization limits', 'expense' => 'Expense limit'];
    $leaveCompany = fn ($type, string $key) => $key === 'allowed' ? true : ($type->{$key} ?? 0);
@endphp

<div class="alert alert-info mb-3"><i class="feather-info"></i>
    {{ $user->name }} follows the company policy unless a custom value is set here. A custom value applies to everything calculated after it is saved.</div>

@foreach ($sections as $section => $data)
    <div class="p360-toolbar {{ $loop->first ? '' : 'mt-4' }} mb-2">
        <div class="p360-sub m-0">{{ $titles[$section] }}
            @if ($data['custom'])<span class="p360-chip extra ms-1">{{ count($data['custom']) }} custom</span>@endif
        </div>
        <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('policy-' . $section) }}"
            data-title="{{ $titles[$section] }} — {{ $user->name }}" data-size="lg"><i class="feather-sliders me-1"></i>Customise</a>
    </div>
    <div class="table-responsive">
        <table class="p360-table mb-2">
            <thead><tr><th>Setting</th><th>Company</th><th>This employee</th></tr></thead>
            <tbody>
                @foreach ($data['fields'] as $key => $field)
                    <tr>
                        <td>{{ $field['label'] }}</td>
                        <td>{{ Policy::display($field, $data['company'][$key] ?? null) }}</td>
                        <td>
                            @if (array_key_exists($key, $data['custom']))
                                <strong>{{ Policy::display($field, $data['custom'][$key]) }}</strong> <span class="p360-chip extra">Custom</span>
                            @else
                                <span class="p360-note">Same as company</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if ($leaveEnabled)
    <div class="p360-toolbar mt-4 mb-2">
        <div class="p360-sub m-0">Leave rules
            @if ($leaveCustom)<span class="p360-chip extra ms-1">{{ array_sum(array_map('count', $leaveCustom)) }} custom</span>@endif
        </div>
        <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('policy-leave') }}"
            data-title="Leave rules — {{ $user->name }}" data-size="lg"><i class="feather-sliders me-1"></i>Customise</a>
    </div>
    @if ($leaveTypes->isEmpty())
        <p class="p360-note">No leave types configured.</p>
    @else
        <div class="table-responsive">
            <table class="p360-table mb-2">
                <thead><tr><th>Leave type</th>@foreach (Policy::LEAVE_FIELDS as $field)<th>{{ $field['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($leaveTypes as $type)
                        @php $own = (array) ($leaveCustom['type:' . $type->id] ?? []); @endphp
                        <tr>
                            <td>{{ $type->name }}{{ $type->is_unpaid ? ' (unpaid)' : '' }}
                                @if ($type->credit_type && $type->credit_type !== 'no')<div class="p360-note">credited {{ $type->credit_type }}</div>@endif
                            </td>
                            @foreach (Policy::LEAVE_FIELDS as $key => $field)
                                <td>
                                    @if (array_key_exists($key, $own))
                                        <strong>{{ Policy::display($field, $own[$key]) }}</strong> <span class="p360-chip extra">Custom</span>
                                        <div class="p360-note">company: {{ Policy::display($field, $leaveCompany($type, $key)) }}</div>
                                    @else
                                        {{ Policy::display($field, $leaveCompany($type, $key)) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="p360-note">Which leave types are credited to this employee is set under Job → Edit ("Leave types this employee can use").</p>
    @endif
@endif

<div class="p360-sub">Shift</div>
<table class="p360-table mb-3">
    <tbody>
        <tr><td>Shift mode</td><td>{{ $shiftMode }}</td></tr>
        @if ($defaultShift)<tr><td>Company shift</td><td>{{ $defaultShift->name }} · {{ P::shiftLabel($defaultShift) }}</td></tr>@endif
    </tbody>
</table>

<div class="p360-sub">This employee</div>
<table class="p360-table">
    <tbody>
        <tr><td>Attendance type</td><td>{{ $attendanceType ? ucfirst(str_replace('_', ' ', $attendanceType)) : '—' }}</td></tr>
        <tr><td>Work type</td><td>{{ $workType ? ucfirst($workType) : '—' }}</td></tr>
        <tr><td>GPS field tracking</td><td>{{ $fieldTracking ? 'On' : 'Off' }}</td></tr>
    </tbody>
</table>
