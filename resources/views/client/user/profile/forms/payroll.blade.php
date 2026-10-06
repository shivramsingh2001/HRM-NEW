{{-- Employee 360 — set / revise the salary (employee.update.step, step 6 — the same save as the employee wizard's Payroll step) --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $rows = [
        'Earnings' => [
            'basic_salary' => 'Basic salary', 'hra' => 'HRA', 'conveyence' => 'Conveyance', 'medical_allowance' => 'Medical',
            'children_allowance' => 'Children allowance', 'post_allowance' => 'Post allowance',
            'leave_travel_allowance' => 'LTA', 'monthly_incentive' => 'Monthly incentive',
        ],
        'Deductions' => ['provident_fund' => 'Employee PF', 'esi' => 'Employee ESI', 'professional_tax' => 'Professional tax'],
        'Employer' => ['employer_provident_fund' => 'Employer PF', 'employer_esi' => 'Employer ESI'],
    ];
@endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page" submit="Save salary">
    <input type="hidden" name="step" value="6">

    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label">Annual CTC (₹) <span class="text-danger">*</span></label>
            <input type="number" name="annual_ctc" class="form-control" min="100000" step="1000" required
                value="{{ $current['ctc'] ? (int) round($current['ctc']) : '' }}" placeholder="e.g. 600000">
        </div>
        <div class="col-md-4">
            <label class="form-label">Effective from <span class="text-danger">*</span></label>
            <input type="date" name="salary_effective_date" class="form-control" required value="{{ $current['effective_from'] }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Payroll structure</label>
            <select name="payroll_structure_id" class="form-control">
                <option value="" data-calc="{}">Default split</option>
                @foreach ($payrollStructures as $structure)
                    <option value="{{ $structure->id }}" data-calc='{{ json_encode($structure->calculatorComponents()) }}'
                        @selected((int) $current['payroll_structure_id'] === (int) $structure->id)>{{ $structure->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="p360-note mt-2 mb-2">
        @if ($current['effective_from'])
            Keep the same date to correct the current salary. Pick a new date to record a salary revision; the earlier salary stays in the history.
        @else
            No salary is set for this employee yet.
        @endif
        The components below are worked out from the CTC and the structure.
    </p>

    <div class="row g-2 p360-salary-preview">
        @foreach ($rows as $title => $fields)
            <div class="{{ $title === 'Earnings' ? 'col-md-6' : 'col-md-3' }}">
                <table class="p360-table mb-0">
                    <thead><tr><th colspan="2">{{ $title }} (monthly)</th></tr></thead>
                    <tbody>
                        @foreach ($fields as $field => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end">₹ <span data-show="{{ $field }}">{{ $money(0) }}</span></td>
                            </tr>
                        @endforeach
                        @if ($title === 'Earnings')
                            <tr><td><strong>Gross salary</strong></td><td class="text-end"><strong>₹ <span data-show="gross_salary">{{ $money(0) }}</span></strong></td></tr>
                        @elseif ($title === 'Deductions')
                            <tr><td><strong>Net salary</strong></td><td class="text-end"><strong>₹ <span data-show="net_salary">{{ $money(0) }}</span></strong></td></tr>
                        @else
                            <tr><td><strong>Monthly CTC</strong></td><td class="text-end"><strong>₹ <span data-show="monthly_ctc">{{ $money(0) }}</span></strong></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    {{-- The figures that are saved (same field names as the wizard's Payroll step). --}}
    @foreach (['basic_salary', 'hra', 'conveyence', 'medical_allowance', 'children_allowance', 'post_allowance', 'leave_travel_allowance',
        'monthly_incentive', 'special_allowance', 'provident_fund', 'employer_provident_fund', 'esi', 'employer_esi',
        'professional_tax', 'gross_salary', 'net_salary'] as $field)
        <input type="hidden" name="{{ $field }}" value="0">
    @endforeach
</x-p360.form>

<script>
    (function() {
        const $form = $('#p360Modal form');
        const format = value => (parseFloat(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const recalc = () => {
            let calc = {};
            try {
                calc = JSON.parse($form.find('[name=payroll_structure_id] option:selected').attr('data-calc') || '{}');
            } catch (e) {
                calc = {};
            }
            // An empty structure serialises as [] — treat it as "no rules".
            if (Array.isArray(calc)) calc = {};

            const ctc = parseFloat($form.find('[name=annual_ctc]').val()) || 0;
            const values = window.computeSalaryBreakdown(ctc > 0 ? ctc : 0, calc);

            Object.keys(values).forEach(key => {
                const amount = ctc > 0 ? values[key] : 0;
                $form.find('[data-show="' + key + '"]').text(format(amount));
                $form.find('input[type=hidden][name="' + key + '"]').val((parseFloat(amount) || 0).toFixed(2));
            });
        };

        $form.on('input', '[name=annual_ctc]', recalc).on('change', '[name=payroll_structure_id]', recalc);
        recalc();
    })();
</script>
