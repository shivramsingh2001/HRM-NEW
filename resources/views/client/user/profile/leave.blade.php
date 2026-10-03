{{-- Employee 360 — leave (EmployeeProfileController::tabLeave) --}}
@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q);
@endphp

<div class="p360-toolbar">
    <h5 class="section-title mb-0 border-0 pb-0"><i class="feather-sun"></i> Leave balances</h5>
    @if ($can['leave_manage'])
        <div class="d-flex gap-2 flex-wrap">
            <a href="#" class="btn btn-sm btn-primary" data-p360-open="{{ $form('leave-apply') }}" data-title="Apply leave for {{ $user->name }}" data-size="md">
                <i class="feather-plus me-1"></i>Apply leave</a>
            <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('leave-credit') }}" data-title="Credit leave">Credit</a>
            <a href="#" class="btn btn-sm btn-light-brand" data-p360-open="{{ $form('leave-debit') }}" data-title="Debit leave">Debit</a>
        </div>
    @endif
</div>
@if ($balances->isEmpty())
    <p class="p360-note">No leave balance has been credited yet.</p>
@else
    <div class="p360-kpis">
        @foreach ($balances as $b)
            <div class="p360-kpi">
                <div class="v">{{ $fmt($b->balance) }}</div>
                <div class="l">{{ $b->name }}{{ $b->is_unpaid ? ' (unpaid)' : '' }}</div>
            </div>
        @endforeach
    </div>
@endif

<div class="p360-sub">Leave history</div>
@if ($leaves->isEmpty())
    <p class="p360-note">No leave applied.</p>
@else
    <div class="table-responsive">
        <table class="p360-table">
            <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($leaves as $l)
                    <tr>
                        <td>{{ $l->type_name ?? '—' }}</td>
                        <td>{{ \Carbon\Carbon::parse($l->start_date)->format('d M Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($l->end_date)->format('d M Y') }}</td>
                        <td>{{ $fmt($l->total_days ?? $l->leave_count) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($l->reason, 50) }}</td>
                        <td><x-ui.status-badge :status="$l->status" /></td>
                        <td class="text-end text-nowrap">
                            @if ($can['leave_approve'] && $l->status === 'pending')
                                <a href="#" class="btn btn-sm btn-primary" data-title="Approve leave"
                                    data-p360-open="{{ $form('leave-decide', ['leave' => $l->id, 'decision' => 'approved']) }}">Approve</a>
                                <a href="#" class="btn btn-sm btn-light-brand" data-title="Reject leave"
                                    data-p360-open="{{ $form('leave-decide', ['leave' => $l->id, 'decision' => 'cancelled']) }}">Reject</a>
                            @elseif ($can['leave_approve'] && $l->status === 'approved')
                                <a href="#" class="btn btn-sm btn-light-brand" data-title="Revoke approved leave"
                                    data-p360-open="{{ $form('leave-decide', ['leave' => $l->id, 'decision' => 'cancelled']) }}">Revoke</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="p360-sub">Recent balance changes</div>
@if ($transactions->isEmpty())
    <p class="p360-note">No transactions.</p>
@else
    <table class="p360-table">
        <thead><tr><th>Date</th><th>Type</th><th>Change</th><th>Before → After</th><th>Remarks</th></tr></thead>
        <tbody>
            @foreach ($transactions as $t)
                <tr>
                    <td>{{ $t->transaction_date ? \Carbon\Carbon::parse($t->transaction_date)->format('d M Y') : \Carbon\Carbon::parse($t->created_at)->format('d M Y') }}</td>
                    <td>{{ $t->type_name ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $t->transaction_type)) }} {{ $fmt($t->leaves_count) }}</td>
                    <td>{{ $fmt($t->before_leaves) }} → {{ $fmt($t->after_leaves) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($t->remarks, 50) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
