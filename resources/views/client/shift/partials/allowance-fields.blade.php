{{-- Shift allowance (paid in payroll by ShiftAllowanceCalculator). $prefix: 'add_' / 'edit_' --}}
@php $errPrefix = $prefix === 'edit_' ? 'edit_' : ''; @endphp
<div class="col-12">
    <label class="form-label">Shift allowance</label>
    <select name="allowance_type" id="{{ $prefix }}allowance_type" class="form-control allowance-type">
        <option value="none">No allowance</option>
        <option value="per_day">Fixed amount per day worked</option>
        <option value="per_hour">Amount per hour worked</option>
    </select>
    <small class="text-danger error-text {{ $errPrefix }}allowance_type_error"></small>
</div>
<div class="col-6 allowance-detail d-none">
    <label class="form-label">Amount (₹) <span class="allowance-unit text-muted">/ day</span></label>
    <input type="number" step="0.01" min="0" name="allowance_amount" id="{{ $prefix }}allowance_amount" class="form-control">
    <small class="text-danger error-text {{ $errPrefix }}allowance_amount_error"></small>
</div>
<div class="col-6 allowance-detail d-none">
    <label class="form-label">Min. hours worked</label>
    <input type="number" step="0.25" min="0" max="24" name="allowance_min_hours" id="{{ $prefix }}allowance_min_hours" class="form-control" placeholder="Any">
    <small class="text-danger error-text {{ $errPrefix }}allowance_min_hours_error"></small>
</div>
<div class="col-12 allowance-detail d-none">
    <small class="text-muted">Paid in monthly payroll for each day this shift is worked. A half day earns half the daily amount; leave, holiday and week-off days earn nothing.</small>
</div>
