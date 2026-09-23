<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ $batch->voucher_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 2px;
            color: #1e3a8a;
        }

        .muted {
            color: #6b7280;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 3px 6px 3px 0;
            vertical-align: top;
        }

        .lines th {
            background: #1e3a8a;
            color: #fff;
            text-align: left;
            padding: 6px;
            font-size: 10px;
        }

        .lines td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px;
        }

        .r {
            text-align: right;
        }

        .total td {
            font-weight: bold;
            border-top: 2px solid #1e3a8a;
            padding: 8px 6px;
        }

        .void {
            color: #b91c1c;
            border: 2px solid #b91c1c;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
            margin: 6px 0;
        }

        .struck {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .sign td {
            padding-top: 42px;
            width: 33%;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }

        .sign span {
            border-top: 1px solid #9ca3af;
            display: block;
            margin: 0 12px;
            padding-top: 4px;
        }
    </style>
</head>

<body>
    <h1>Payment Voucher</h1>
    <div class="muted">{{ $company->name ?? ($company->company_name ?? '') }}</div>

    @if ($batch->isVoided())
        <div class="void">VOIDED — {{ $batch->voided_at?->format('d M Y') }}: {{ $batch->void_reason }}</div>
    @endif

    <table class="meta" style="margin: 10px 0 14px;">
        <tr>
            <td class="muted" style="width:18%">Voucher no.</td>
            <td style="width:32%"><b>{{ $batch->voucher_number }}</b></td>
            <td class="muted" style="width:18%">Payment date</td>
            <td>{{ $batch->payment_date?->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="muted">Mode</td>
            <td>{{ ucwords(str_replace('_', ' ', $batch->payment_mode)) }}</td>
            <td class="muted">Reference / UTR</td>
            <td>{{ $batch->reference_number ?: '-' }}</td>
        </tr>
        <tr>
            <td class="muted">Bank</td>
            <td>{{ $batch->bank_name ?: '-' }}</td>
            <td class="muted">Prepared by</td>
            <td>{{ $batch->creator?->name ?? '-' }} ({{ $batch->created_at?->format('d M Y H:i') }})</td>
        </tr>
        @if ($batch->remarks)
            <tr>
                <td class="muted">Remarks</td>
                <td colspan="3">{{ $batch->remarks }}</td>
            </tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th>Employee</th>
                <th>Expense no.</th>
                <th>Type</th>
                <th>Bank account</th>
                <th class="r">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr @class(['struck' => $l->status === 'voided'])>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->employee_name }}<br><span class="muted">{{ $l->employee_code }}</span></td>
                    <td>{{ $l->expense_number }}</td>
                    <td>{{ ucfirst($l->requirement_type) }}</td>
                    <td>
                        @if ($l->account_number)
                            {{ $l->bank_name }}<br>{{ $l->account_number }} · {{ $l->ifsc }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="r">{{ number_format((float) $l->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="5" class="r">Total ({{ $batch->line_count }} payment(s))</td>
                <td class="r">{{ number_format((float) $batch->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td><span>Prepared by</span></td>
            <td><span>Checked by</span></td>
            <td><span>Approved by</span></td>
        </tr>
    </table>
</body>

</html>
