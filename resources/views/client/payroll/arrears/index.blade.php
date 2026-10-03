@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header class="content-area-header sticky-top" title="Payroll Arrears" />

    <div class="content-area-body">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="alert alert-info" style="font-size:11px;">
            <i class="feather-info me-1"></i> These are queued automatically when a salary revision's effective date falls
            inside an already-processed month. Each pending row will be folded into that employee's next computed payslip
            as a single "Arrears" line item.
        </div>

        <x-ui.filter-card title="Filter Arrears">
<form method="GET">
            <div class="row">
                <div class="col-md-3">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'included_in_payroll' => 'Included in Payroll', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $val => $label)
                            <option value="{{ $val }}" {{ request('status', 'pending') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
</x-ui.filter-card>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Period</th>
                                <th>Original</th>
                                <th>Revised</th>
                                <th>Arrears Amount</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($arrears as $row)
                                <tr>
                                    <td>
                                        @if ($row->user)
                                            <div class="employee-info">
                                                <div class="employee-avatar"
                                                    style="background:#0D6EFD;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                    {{ strtoupper(substr($row->user->name, 0, 2)) }}</div>
                                                <div class="employee-details">
                                                    <div class="employee-name">
                                                        {{ $row->user->name }}
                                                        <small class="text-muted">({{ $row->user->employee_id ?? 'N/A' }})</small>
                                                    </div>
                                                    <div class="employee-email">{{ $row->user->email ?? '' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $row->arrears_type)) }}</td>
                                    <td>{{ $row->period_from }}{{ $row->period_from !== $row->period_to ? ' – ' . $row->period_to : '' }}</td>
                                    <td>₹{{ number_format($row->original_amount, 2) }}</td>
                                    <td>₹{{ number_format($row->revised_amount, 2) }}</td>
                                    <td class="{{ $row->arrears_amount < 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $row->arrears_amount >= 0 ? '+' : '' }}₹{{ number_format($row->arrears_amount, 2) }}
                                    </td>
                                    <td><span class="badge bg-{{ $row->status === 'pending' ? 'warning' : ($row->status === 'cancelled' ? 'danger' : 'success') }}">{{ ucfirst(str_replace('_', ' ', $row->status)) }}</span></td>
                                    <td class="text-end">
                                        @if ($row->status === 'pending')
                                            <form action="{{ route('payroll-arrears.cancel', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this arrears entry? It will not be paid out.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-danger">Cancel</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No arrears entries.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{ $arrears->links() }}
    </div>
@endsection
