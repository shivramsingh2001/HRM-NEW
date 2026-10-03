@extends('client.layout.master')

@section('content-area')
    <x-ui.page-header :title="'Voucher ' . $batch->voucher_number" :current="$batch->voucher_number"
        :crumbs="[['label' => 'Payments', 'url' => route('expense.payments.index')], ['label' => 'Vouchers', 'url' => route('expense.vouchers.index')]]"
        :back="route('expense.vouchers.index')">
        <x-slot:actions>
            <a href="{{ route('expense.vouchers.pdf', $batch->id) }}" class="btn btn-light-brand btn-sm"><i class="feather-file-text me-2"></i>PDF</a>
            <a href="{{ route('expense.vouchers.csv', $batch->id) }}" class="btn btn-light-brand btn-sm"><i class="feather-download me-2"></i>Bank CSV</a>
            @if ($batch->payment_mode === 'payroll')
                <span class="badge bg-info align-self-center" title="Reverse it by reopening the payslip">Paid through payroll</span>
            @elseunless ($batch->isVoided())
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                    data-bs-target="#voidModal"><i class="feather-slash me-2"></i>Void voucher</button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="main-content" style="padding: 20px !important;">
        @if ($batch->isVoided())
            <div class="alert alert-danger">
                <b>This voucher was voided</b> on {{ $batch->voided_at?->format('d M Y H:i') }} by
                {{ $batch->voider?->name ?? 'unknown' }}. Reason: {{ $batch->void_reason }}
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-muted small">Payment date</div>
                                <div class="fw-semibold">{{ $batch->payment_date?->format('d M Y') }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Mode</div>
                                <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', $batch->payment_mode)) }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Reference / UTR</div>
                                <div class="fw-semibold">{{ $batch->reference_number ?: '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Bank</div>
                                <div class="fw-semibold">{{ $batch->bank_name ?: '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Posted by</div>
                                <div class="fw-semibold">{{ $batch->creator?->name ?? '-' }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Posted at</div>
                                <div class="fw-semibold">{{ $batch->created_at?->format('d M Y H:i') }}</div>
                            </div>
                            @if ($batch->remarks)
                                <div class="col-12">
                                    <div class="text-muted small">Remarks</div>
                                    <div>{{ $batch->remarks }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100"
                    style="background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color:#fff; border:0;">
                    <div class="card-body">
                        <div class="small opacity-75">Voucher total</div>
                        <div class="fs-2 fw-bold">₹{{ number_format((float) $batch->total_amount, 2) }}</div>
                        <div class="small opacity-75">{{ $batch->line_count }} payment(s)</div>
                        @if ($batch->isVoided())
                            <div class="mt-2 small">Still counted as paid: ₹{{ number_format($postedTotal, 2) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Expense #</th>
                            <th>Type</th>
                            <th>Bank account</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $l)
                            <tr @class(['text-decoration-line-through text-muted' => $l->status === 'voided'])>
                                <td>{{ $l->employee_name }} <span class="small text-muted">{{ $l->employee_code }}</span>
                                </td>
                                <td>{{ $l->expense_number }}</td>
                                <td>{{ ucfirst($l->requirement_type) }}</td>
                                <td class="small">
                                    @if ($l->account_number)
                                        {{ $l->bank_name }} · {{ $l->account_number }} · {{ $l->ifsc }}
                                    @else
                                        <span class="text-warning">No bank details on file</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">₹{{ number_format((float) $l->amount, 2) }}</td>
                                <td>
                                    @if ($l->status === 'voided')
                                        <span class="badge bg-danger" title="{{ $l->void_reason }}">Voided</span>
                                    @else
                                        <span class="badge bg-success">Posted</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('create-modal')
    @unless ($batch->isVoided())
        <div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Void voucher {{ $batch->voucher_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2">Every payment in this voucher is reversed together (or none, if an
                            employee has already spent an advance from it). The voucher and its lines stay on record as
                            voided.</p>
                        <textarea id="voidReason" class="form-control" rows="3" maxlength="500"
                            placeholder="Reason (required, min 3 characters)"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmVoid">Void voucher</button>
                    </div>
                </div>
            </div>
        </div>
    @endunless
@endsection

@section('script-area')
    @unless ($batch->isVoided())
        <script>
            toastr.options = {
                closeButton: true,
                progressBar: true,
                timeOut: 5000
            };

            $('#confirmVoid').on('click', function() {
                const reason = ($('#voidReason').val() || '').trim();
                if (reason.length < 3) {
                    toastr.error('Please give a reason (at least 3 characters).');
                    return;
                }
                const btn = $(this).prop('disabled', true);

                $.ajax({
                    url: @json(route('expense.vouchers.void', $batch->id)),
                    type: 'POST',
                    data: {
                        reason: reason
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                }).done(function(res) {
                    toastr.success(res.message);
                    setTimeout(() => location.reload(), 1200);
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Could not void the voucher.');
                    btn.prop('disabled', false);
                });
            });
        </script>
    @endunless
@endsection
