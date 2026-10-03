{{-- Employee 360 — payroll history (EmployeeProfileController::tabPayroll). Current salary breakup is on the "Payroll" tab. --}}
@php $money = fn ($v) => '₹ ' . number_format((float) $v, 2); @endphp

@if ($canPayroll)
    @if ($dynamic)
        <h5 class="section-title"><i class="feather-layers"></i> Salary structure</h5>
        @if ($structure)
            <div class="p360-kpis">
                <div class="p360-kpi"><div class="v">{{ $money($structure->ctc) }}</div><div class="l">Annual CTC</div></div>
                <div class="p360-kpi"><div class="v">{{ \Carbon\Carbon::parse($structure->effective_from)->format('d M Y') }}</div><div class="l">Effective from</div></div>
                <div class="p360-kpi"><div class="v">{{ ucfirst(str_replace('_', ' ', (string) $structure->revision_type)) ?: '—' }}</div><div class="l">Last revision</div></div>
            </div>
        @else
            <p class="p360-note">No salary structure assigned yet.</p>
        @endif

        @if ($revisions->count() > 1)
            <div class="p360-sub">Revisions</div>
            <table class="p360-table">
                <thead><tr><th>From</th><th>To</th><th>CTC</th><th>Type</th><th>Reason</th></tr></thead>
                <tbody>
                    @foreach ($revisions as $rv)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($rv->effective_from)->format('d M Y') }}</td>
                            <td>{{ $rv->effective_to ? \Carbon\Carbon::parse($rv->effective_to)->format('d M Y') : 'Current' }}</td>
                            <td>{{ $money($rv->ctc) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', (string) $rv->revision_type)) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($rv->revision_reason, 40) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <div class="p360-sub">Payslips</div>
    @if ($payslips->isEmpty())
        <p class="p360-note">No payroll has been run for this employee yet.</p>
    @else
        <div class="table-responsive">
            <table class="p360-table">
                <thead><tr><th>Month</th><th>Payable days</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($payslips as $p)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($p->payroll_month . (strlen($p->payroll_month) === 7 ? '-01' : ''))->format('M Y') }}</td>
                            <td>{{ $p->payable_days }}</td>
                            <td>{{ $money($p->gross_earnings) }}</td>
                            <td>{{ $money($p->total_deductions) }}</td>
                            <td><strong>{{ $money($p->net_payable) }}</strong></td>
                            <td><x-ui.status-badge :status="$p->payment_status ?: 'pending'" /></td>
                            <td><a href="{{ route('monthly-payrolls.payslip', $p->id) }}" target="_blank" class="btn btn-sm btn-light-brand" title="Payslip"><i class="feather-file-text"></i></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($bonuses->isNotEmpty())
        <div class="p360-sub">Bonuses</div>
        <table class="p360-table">
            <thead><tr><th>Name</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($bonuses as $b)
                    <tr>
                        <td>{{ $b->name }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', (string) $b->bonus_type)) }}</td>
                        <td>{{ $money($b->amount) }}</td>
                        <td><x-ui.status-badge :status="$b->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if ($canLoans)
    <div class="p360-sub">Loans</div>
    @if ($loans->isEmpty())
        <p class="p360-note">No loans.</p>
    @else
        <table class="p360-table">
            <thead><tr><th>Loan</th><th>Date</th><th>Amount</th><th>EMI</th><th>Remaining</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($loans as $ln)
                    <tr>
                        <td>{{ $ln->loan_number }}</td>
                        <td>{{ $ln->loan_date ? \Carbon\Carbon::parse($ln->loan_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $money($ln->amount) }}</td>
                        <td>{{ $ln->emi_amount ? $money($ln->emi_amount) : '—' }}</td>
                        <td>{{ $money($ln->remaining_amount) }}</td>
                        <td><x-ui.status-badge :status="$ln->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
