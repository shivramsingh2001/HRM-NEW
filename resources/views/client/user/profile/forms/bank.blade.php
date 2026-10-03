{{-- Employee 360 — edit bank details (employee.update.step, step 5) --}}
@php $v = fn (string $field) => $bank->{$field} ?? ''; @endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page">
    <input type="hidden" name="step" value="5">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label">Bank name</label>
            <input type="text" name="bank_name" class="form-control" value="{{ $v('bank_name') }}" maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label">Branch name</label>
            <input type="text" name="branch_name" class="form-control" value="{{ $v('branch_name') }}" maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label">Account number</label>
            <input type="text" name="account_number" class="form-control" value="{{ $v('account_number') }}" maxlength="20">
        </div>
        <div class="col-md-6">
            <label class="form-label">IFSC code</label>
            <input type="text" name="ifsc" class="form-control" value="{{ $v('ifsc') }}" maxlength="11">
        </div>
        <div class="col-md-4">
            <label class="form-label">UAN number</label>
            <input type="text" name="uan_no" class="form-control" value="{{ $v('uan_no') }}" maxlength="20">
        </div>
        <div class="col-md-4">
            <label class="form-label">PF number</label>
            <input type="text" name="pf_no" class="form-control" value="{{ $v('pf_no') }}" maxlength="20">
        </div>
        <div class="col-md-4">
            <label class="form-label">ESIC number</label>
            <input type="text" name="esic_no" class="form-control" value="{{ $v('esi_no') }}" maxlength="20">
        </div>
    </div>
</x-p360.form>
