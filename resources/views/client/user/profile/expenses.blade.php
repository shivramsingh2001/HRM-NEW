{{-- Employee 360 — expenses (EmployeeProfileController::tabExpenses) --}}
@php
    $money = fn ($v) => '₹ ' . number_format((float) $v, 2);
    $form = fn (string $f, array $q = []) => route('employee.profile.form', ['id' => $encId, 'form' => $f] + $q);
@endphp

<h5 class="section-title"><i class="feather-credit-card"></i> Expenses</h5>
<div class="p360-kpis">
    @foreach ($byStatus as $s)
        <div class="p360-kpi">
            <div class="v">{{ $money($s->total) }}</div>
            <div class="l">{{ ucfirst($s->status) }} · {{ $s->c }}</div>
        </div>
    @endforeach
    @if ($balance)
        <div class="p360-kpi">
            <div class="v">{{ $money($balance->current_balance) }}</div>
            <div class="l">Current balance</div>
        </div>
    @endif
</div>

@if ($expenses->isEmpty())
    <x-ui.empty-state icon="credit-card" title="No expenses" subtitle="This employee has not submitted any expense." />
@else
    <div class="table-responsive">
        <table class="p360-table">
            <thead><tr><th>No.</th><th>Date</th><th>Type</th><th>Amount</th><th>Paid</th><th>Description</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($expenses as $e)
                    <tr>
                        <td>{{ $e->expense_number }}</td>
                        <td>{{ $e->date ? \Carbon\Carbon::parse($e->date)->format('d M Y') : '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', (string) ($e->expense_type ?: $e->requirement_type))) }}</td>
                        <td>{{ $money($e->amount) }}</td>
                        <td>{{ $money($e->paid_amount) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($e->description, 40) }}</td>
                        <td><x-ui.status-badge :status="$e->status" /></td>
                        <td class="text-end text-nowrap">
                            @if ($can['expenses_approve'] && $e->status === 'pending')
                                <a href="#" class="btn btn-sm btn-primary" data-title="Approve expense {{ $e->expense_number }}"
                                    data-p360-open="{{ $form('expense-decide', ['expense' => $e->id, 'decision' => 'approved']) }}">Approve</a>
                                <a href="#" class="btn btn-sm btn-light-brand" data-title="Reject expense {{ $e->expense_number }}"
                                    data-p360-open="{{ $form('expense-decide', ['expense' => $e->id, 'decision' => 'cancelled']) }}">Reject</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
