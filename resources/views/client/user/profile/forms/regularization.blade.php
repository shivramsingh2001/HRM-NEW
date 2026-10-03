{{-- Employee 360 — approve / reject a regularization request (attendance-regularization.approval) --}}
@php $approve = $decision === 'approved'; @endphp
<x-p360.form :action="route('attendance-regularization.approval')" reload="attendance"
    :submit="$approve ? 'Approve' : 'Reject'" :submitClass="$approve ? 'btn-primary' : 'btn-danger'">
    <input type="hidden" name="id" value="{{ $regularization->id }}">
    <input type="hidden" name="status" value="{{ $decision }}">
    <table class="p360-table mb-3">
        <tbody>
            <tr><td>Date</td><td>{{ \Carbon\Carbon::parse($regularization->date)->format('d M Y') }}</td></tr>
            <tr><td>Type</td><td>{{ str_replace('_', ' ', (string) $regularization->request_type) }}</td></tr>
            <tr><td>In / Out</td><td>{{ $regularization->in_time ?: '—' }} / {{ $regularization->out_time ?: '—' }}</td></tr>
            <tr><td>Reason</td><td>{{ $regularization->reason ?: '—' }}</td></tr>
        </tbody>
    </table>
    <label class="form-label">Remarks</label>
    <textarea name="remarks" class="form-control" rows="2" maxlength="500"></textarea>
    @if ($approve)
        <p class="p360-note mt-2 mb-0">Approving rewrites that day's attendance with the requested times.</p>
    @endif
</x-p360.form>
