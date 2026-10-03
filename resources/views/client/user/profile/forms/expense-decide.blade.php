{{-- Employee 360 — approve / reject a pending expense (expense.update-status) --}}
@php $approve = $decision === 'approved'; @endphp
<x-p360.form :action="route('expense.update-status', $expense->id)" reload="expenses"
    :submit="$approve ? 'Approve' : 'Reject'" :submitClass="$approve ? 'btn-primary' : 'btn-danger'">
    <input type="hidden" name="status" value="{{ $decision }}">
    <table class="p360-table mb-3">
        <tbody>
            <tr><td>Expense</td><td>{{ $expense->expense_number }} · {{ ucfirst(str_replace('_', ' ', (string) ($expense->expense_type ?: $expense->requirement_type))) }}</td></tr>
            <tr><td>Amount</td><td>₹ {{ number_format((float) $expense->amount, 2) }}</td></tr>
            <tr><td>Date</td><td>{{ $expense->date ? \Carbon\Carbon::parse($expense->date)->format('d M Y') : '—' }}</td></tr>
            <tr><td>Description</td><td>{{ $expense->description ?: '—' }}</td></tr>
        </tbody>
    </table>
    <label class="form-label">Remarks</label>
    <textarea name="remarks" class="form-control" rows="2" maxlength="500"></textarea>
    <p class="p360-note mt-2 mb-0">Receipts and payments are on the <a href="{{ route('expense.view-all') }}" target="_blank">Expenses page</a>.</p>
</x-p360.form>
