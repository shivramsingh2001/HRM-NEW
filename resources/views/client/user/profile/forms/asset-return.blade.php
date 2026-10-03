{{-- Employee 360 — take an asset back from this employee (assets.assignments.return) --}}
<x-p360.form :action="route('assets.assignments.return', encrypt($assignment->id))" reload="assets" submit="Mark returned">
    <p class="p360-note">Take back <strong>{{ $assignment->asset_code }} · {{ $assignment->asset_name }}</strong> from {{ $user->name }}.</p>
    <div class="mb-2">
        <label class="form-label">Condition on return</label>
        <select name="return_condition" class="form-control">
            <option value="">Not recorded</option>
            @foreach (['new', 'good', 'fair', 'poor', 'damaged'] as $condition)
                <option value="{{ $condition }}">{{ ucfirst($condition) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2" maxlength="1000"></textarea>
    </div>
</x-p360.form>
