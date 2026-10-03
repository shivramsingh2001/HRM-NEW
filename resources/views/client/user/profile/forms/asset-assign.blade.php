{{-- Employee 360 — give a company asset to this employee (assets.assign; the chosen asset goes in the URL) --}}
<x-p360.form :action="route('assets.assign', '__ID__')" urlField="asset" reload="assets" submit="Assign asset">
    <input type="hidden" name="user_id" value="{{ $user->id }}">
    @if ($assets->isEmpty())
        <div class="alert alert-info mb-2">No asset is available right now. <a href="{{ route('assets.index') }}" target="_blank">Open Assets</a> to add one or take one back.</div>
    @endif
    <div class="mb-2">
        <label class="form-label">Asset <span class="text-danger">*</span></label>
        <select name="asset" class="form-control p360-select2" required>
            <option value="">Select an available asset</option>
            @foreach ($assets as $asset)
                <option value="{{ encrypt($asset->id) }}">{{ $asset->asset_code }} · {{ $asset->name }}{{ $asset->serial_number ? ' (' . $asset->serial_number . ')' : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-2">
        <label class="form-label">Expected return date</label>
        <input type="date" name="expected_return_date" class="form-control">
    </div>
    <div>
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2" maxlength="1000"></textarea>
    </div>
    <p class="p360-note mt-2 mb-0">The employee is asked to accept the asset.</p>
</x-p360.form>
