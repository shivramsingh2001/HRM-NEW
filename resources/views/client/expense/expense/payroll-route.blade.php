@extends('client.layout.master')

@section('content-area')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Reimbursements via Payroll</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('expense.payments.index') }}">Payments</a></li>
                <li class="breadcrumb-item active">Via payroll</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <a href="{{ route('expense.payments.index') }}" class="btn btn-light-brand btn-sm"><i
                    class="feather-arrow-left me-2"></i>Back</a>
        </div>
    </div>

    <div class="main-content" style="padding: 20px !important;">
        @unless ($enabled)
            <div class="alert alert-warning">
                Paying reimbursements through payroll is switched off for your company. Reimbursements already sent to
                payroll are listed below — you can still <b>release</b> them back to the voucher route.
            </div>
        @endunless

        @if ($enabled)
            <div class="card mb-4">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h6 class="mb-0 me-auto">Approved reimbursements waiting to be paid</h6>
                    <label class="small text-muted mb-0" for="payMonth">Pay with the salary of</label>
                    <input type="month" id="payMonth" class="form-control form-control-sm" style="width: 160px"
                        value="{{ $nextMonth }}">
                    <button type="button" id="sendBtn" class="btn btn-primary btn-sm" disabled><i
                            class="feather-send me-2"></i>Send selected to payroll</button>
                </div>
                <div class="card-body pb-0">
                    <p class="text-muted small mb-2">
                        The amount is added to the employee's payslip as a non-taxable earning and is marked paid
                        when the payslip is marked <b>Paid</b>. Only reimbursements with no voucher payment yet can be
                        sent. If the month's payslip already exists (still pending), open and save it to include the
                        amount.
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 36px"><input type="checkbox" id="checkAll"></th>
                                <th>Expense</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($waiting as $e)
                                <tr>
                                    <td><input type="checkbox" class="row-check" value="{{ $e->id }}"></td>
                                    <td class="fw-semibold">{{ $e->expense_number }}</td>
                                    <td>{{ $e->user?->name }} <span
                                            class="text-muted small">{{ $e->user?->employee_id }}</span></td>
                                    <td>{{ $e->date }}</td>
                                    <td>{{ $e->expenseType?->name ?? '-' }}</td>
                                    <td class="text-end">₹{{ number_format((float) $e->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No approved, unpaid reimbursements.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Sent to payroll</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Expense</th>
                            <th>Employee</th>
                            <th>Month</th>
                            <th class="text-end">Amount</th>
                            <th>State</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($routed as $l)
                            <tr>
                                <td class="fw-semibold">{{ $l->expense->expense_number }}</td>
                                <td>{{ $l->expense->user?->name }} <span
                                        class="text-muted small">{{ $l->expense->user?->employee_id }}</span></td>
                                <td>{{ $l->target_month }}</td>
                                <td class="text-end">₹{{ number_format((float) $l->amount, 2) }}</td>
                                <td>
                                    @if ($l->status === 'paid')
                                        <span class="badge bg-success">Paid with {{ $l->monthlyPayroll?->payroll_month }}
                                            salary</span>
                                    @elseif ($l->status === 'linked')
                                        <span class="badge bg-info">On {{ $l->monthlyPayroll?->payroll_month }} payslip
                                            ({{ $l->monthlyPayroll?->payment_status }})</span>
                                    @else
                                        <span class="badge bg-secondary">Waiting for the {{ $l->target_month }}
                                            payroll</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($l->status !== 'paid')
                                        <button type="button" class="btn btn-sm btn-light release-btn"
                                            data-id="{{ $l->expense_id }}" data-number="{{ $l->expense->expense_number }}"
                                            title="Give back to the voucher route"><i
                                                class="feather-corner-up-left me-1"></i>Release to voucher</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Nothing is routed through payroll.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    <div class="modal fade" id="releaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Release <span id="relNumber"></span> to the voucher route</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="relId">
                    <p class="text-muted small">It will no longer be added to a payslip and can be paid by voucher. If it is
                        already on a pending payslip, that line is removed and the payslip totals are corrected.</p>
                    <label class="form-label">Reason</label>
                    <input type="text" id="relReason" class="form-control" maxlength="255">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="relConfirm">Release</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-area')
    <script>
        toastr.options = {
            closeButton: true,
            progressBar: true,
            timeOut: 5000
        };
        const csrf = $('meta[name="csrf-token"]').attr('content');

        function refreshSend() {
            $('#sendBtn').prop('disabled', $('.row-check:checked').length === 0);
        }
        $('#checkAll').on('change', function() {
            $('.row-check').prop('checked', this.checked);
            refreshSend();
        });
        $('.row-check').on('change', refreshSend);

        function fail(xhr) {
            const j = xhr.responseJSON || {};
            let msg = j.message || 'Something went wrong.';
            if (j.line_errors && j.line_errors.length) {
                msg += '\n' + j.line_errors.map(e => (e.expense_number || '#' + e.expense_id) + ': ' + e.message).join('\n');
            }
            toastr.error(msg.replace(/\n/g, '<br>'), '', {
                escapeHtml: false
            });
        }

        $('#sendBtn').on('click', function() {
            const ids = $('.row-check:checked').map((i, el) => el.value).get();
            if (!ids.length) return;
            $(this).prop('disabled', true);
            $.post(@json(route('expense.payroll.send')), {
                    _token: csrf,
                    expense_ids: ids,
                    month: $('#payMonth').val()
                })
                .done(function(res) {
                    toastr.success(res.message);
                    setTimeout(() => location.reload(), 1600);
                })
                .fail(function(xhr) {
                    fail(xhr);
                    refreshSend();
                });
        });

        $('.release-btn').on('click', function() {
            $('#relId').val($(this).data('id'));
            $('#relNumber').text($(this).data('number'));
            $('#relReason').val('');
            new bootstrap.Modal(document.getElementById('releaseModal')).show();
        });

        $('#relConfirm').on('click', function() {
            $.post(@json(url('expense/payroll-route/release')) + '/' + $('#relId').val(), {
                    _token: csrf,
                    reason: $('#relReason').val()
                })
                .done(function(res) {
                    toastr.success(res.message);
                    setTimeout(() => location.reload(), 900);
                })
                .fail(function(xhr) {
                    fail(xhr);
                });
        });
    </script>
@endsection
