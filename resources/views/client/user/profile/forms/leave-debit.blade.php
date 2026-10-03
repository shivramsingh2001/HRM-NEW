{{-- Employee 360 — take days off a leave balance (leave-credit.manual.debit) --}}
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
<x-p360.form :action="route('leave-credit.manual.debit')" reload="leave" submit="Debit leave" submitClass="btn-danger">
    <input type="hidden" name="user_id" value="{{ $user->id }}">
    <div class="mb-2">
        <label class="form-label">Leave type <span class="text-danger">*</span></label>
        <select name="leave_type_id" class="form-control" required>
            <option value="">Select leave type</option>
            @foreach ($leaveTypes as $leaveType)
                <option value="{{ $leaveType->id }}">{{ $leaveType->name }} — balance {{ $fmt($balances[$leaveType->id] ?? 0) }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-2">
        <label class="form-label">Days to remove <span class="text-danger">*</span></label>
        <input type="number" name="debit_value" class="form-control" min="0.5" step="0.5" required>
    </div>
    <div>
        <label class="form-label">Reason <span class="text-danger">*</span></label>
        <textarea name="remarks" class="form-control" rows="2" maxlength="500" required></textarea>
    </div>
</x-p360.form>
