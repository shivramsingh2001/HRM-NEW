{{-- Employee 360 — approve / reject a pending leave, or revoke an approved one (leave.update-status) --}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $approve = $decision === 'approved';
    $revoke = !$approve && $leave->status === 'approved';
@endphp
<x-p360.form :action="route('leave.update-status', $leave->id)" reload="leave,attendance"
    :submit="$approve ? 'Approve' : ($revoke ? 'Revoke leave' : 'Reject')" :submitClass="$approve ? 'btn-primary' : 'btn-danger'">
    <input type="hidden" name="status" value="{{ $decision }}">
    <table class="p360-table mb-3">
        <tbody>
            <tr><td>Leave</td><td>{{ $leave->type_name ?? '—' }} · {{ $fmt($leave->total_days ?? $leave->leave_count) }} day(s)</td></tr>
            <tr><td>Dates</td><td>{{ \Carbon\Carbon::parse($leave->start_date)->format('d M Y') }} – {{ \Carbon\Carbon::parse($leave->end_date)->format('d M Y') }}</td></tr>
            <tr><td>Reason</td><td>{{ $leave->reason ?: '—' }}</td></tr>
        </tbody>
    </table>
    <label class="form-label">Remarks</label>
    <textarea name="remarks" class="form-control" rows="2" maxlength="500"></textarea>
    @if ($revoke)
        <p class="p360-note mt-2 mb-0">Revoking an approved leave gives the deducted days back to the balance.</p>
    @endif
</x-p360.form>
