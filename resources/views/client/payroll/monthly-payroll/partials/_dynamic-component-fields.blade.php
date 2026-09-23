{{--
    Dynamic-engine Earnings/Deductions/Employer Contributions — only the
    components actually in this employee's assigned PayrollStructure are
    rendered here (via PayrollCalculationEngine::resolveEmployeeComponents(),
    the same resolver the salary slip uses), never a fixed/hardcoded field
    list. $dynamicComponents is null when the employee has no effective
    structure for this month (edit() already falls back to $isDynamic=false
    in that case, so this partial is never reached without data).

    $show: which of 'earnings' / 'deductions' / 'employer_contributions' this
    include call renders — the caller places Earnings before the Overtime
    section and Deductions+Employer after Loan Deductions, matching the
    legacy layout's own section order.
--}}
@php
    $dynamicGroups = [
        'earnings' => ['label' => 'Earnings', 'icon' => 'feather-trending-up', 'color' => 'var(--primary)', 'editable' => true],
        'deductions' => ['label' => 'Deductions', 'icon' => 'feather-trending-down', 'color' => 'var(--gray-600)', 'editable' => true],
        'employer_contributions' => ['label' => 'Employer', 'icon' => 'feather-briefcase', 'color' => '#1e3a8a', 'editable' => false],
    ];
@endphp

@foreach ($show as $groupKey)
    @php
        $groupMeta = $dynamicGroups[$groupKey];
        $groupComponents = $dynamicComponents[$groupKey] ?? [];
    @endphp
    <div class="form-section" @if ($groupKey === 'employer_contributions') style="border-color:#93c5fd;" @endif>
        <div class="section-title">
            <span><i class="{{ $groupMeta['icon'] }} me-1" style="color: {{ $groupMeta['color'] }};"></i>{{ $groupMeta['label'] }}</span>
            <span style="font-size: 10px; color: var(--gray-500);">
                <i class="feather-{{ $groupMeta['editable'] ? 'edit-2' : 'info' }}"></i>
                {{ $groupMeta['editable'] ? 'Editable' : 'Part of CTC — auto-calculated' }}
            </span>
        </div>
        @if (empty($groupComponents))
            <div class="text-muted-small" style="padding: 8px 0;">No {{ strtolower($groupMeta['label']) }} components in this employee's payroll structure.</div>
        @else
            <div class="component-card" @if ($groupKey === 'employer_contributions') style="border-color:#93c5fd;" @endif>
                <div class="component-header" @if ($groupKey === 'employer_contributions') style="background:#dbeafe;border-bottom-color:#93c5fd;" @endif>
                    <span class="component-title" @if ($groupKey === 'employer_contributions') style="color:#1e3a8a;" @endif>
                        <i class="feather-dollar-sign"></i> Salary Components
                    </span>
                    <span style="font-size: 9px; color: {{ $groupKey === 'employer_contributions' ? '#1e3a8a' : 'var(--gray-500)' }};">₹</span>
                </div>
                <div class="component-body">
                    <div class="component-row">
                        @foreach ($groupComponents as $component)
                            <div class="input-group" @if ($groupKey === 'employer_contributions') style="border-color:#93c5fd;" @endif>
                                <span class="input-group-text" @if ($groupKey === 'employer_contributions') style="background:#dbeafe;border-right-color:#93c5fd;color:#1e3a8a;" @endif>{{ $component['name'] }}</span>
                                @if ($groupMeta['editable'])
                                    <input type="number" step="0.01" name="components[{{ $component['code'] }}]"
                                        class="form-control dynamic-component-field" data-code="{{ $component['code'] }}"
                                        value="{{ old('components.' . $component['code'], $component['amount']) }}">
                                @else
                                    <input type="number" step="0.01" class="form-control dynamic-component-field"
                                        data-code="{{ $component['code'] }}" style="background:#eef3fd;"
                                        value="{{ $component['amount'] }}" readonly>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@endforeach
