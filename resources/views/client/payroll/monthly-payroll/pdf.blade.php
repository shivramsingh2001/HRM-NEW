<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Slip - {{ $monthlyPayroll->user->name ?? 'Employee' }}</title>
    <style>
        * {
            margin: 0px;
            padding: 0px;  
        }
    </style>
</head>
<body style="min-height: 100vh; width: 100vw; margin: 0px;">
    <div style="padding: 8px 8px;">
        <div style="border: 1px solid #e5e7eb; padding: 14px 28px;">
            @php
                // PAN / Account No. / IFSC / UAN / ESI No. are sensitive
                // identifiers — the slip only ever shows the last 4
                // characters, matching how bank/card numbers are
                // conventionally partially masked. A closure (not a named
                // function) since this view can render more than once per
                // request (bulk payslip export loops) and a named function
                // would fatal on the second render with "cannot redeclare".
                $maskId = function ($value, $visible = 4) {
                    if (empty($value)) {
                        return 'N/A';
                    }
                    $value = (string) $value;
                    $len = strlen($value);
                    return $len <= $visible ? $value : str_repeat('X', $len - $visible) . substr($value, -$visible);
                };
            @endphp
            <table style="width: 100%;">
                <tbody>
                    <tr>
                        @php
                            // dompdf can't fetch signed cloud URLs: embed the logo bytes instead.
                            $logoPath = !empty($company->logo) ? file_storage()->dataUri($company->logo, 'tenant_logo') : null;

                        @endphp
                        <td style="padding: 8px; padding-bottom: 0px; text-align: center; width: 10%;">
                            @if($logoPath)
                                <img src="{{ $logoPath }}" alt="{{ $company->company_name ?? 'Logo' }}" style="width: 48px; height: 48px; object-fit: contain;">
                            @else
                                <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Default Logo" style="width: 48px; height: 48px; object-fit: contain;">
                            @endif
                        </td>
                        <td style="padding: 8px; width: 80%;">
                            <h3 style="text-align: center; padding-right: 80px; font-size: 30px; color: #1e3a8a; margin-bottom: 2px;">{{ $company->company_name ?? 'Company Name' }}</h3>
                            <h5 style="text-align: center; padding-right: 80px; font-size: 11px; font-weight: 400; color: #475569;">{{ $company->address ?? 'Company Address' }}</h5>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top: 6px; padding-bottom: 18px; width: 90%;">
                            <h4 style="text-align: center; font-size: 13px; font-weight: 600; color: #1e3a8a;">Pay Slip For The Month of {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('M-Y') }}</h4>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table style="width: 100%;">
                <tbody style="width: 100%;">
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Employee No. :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->employee_id ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Name :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Designation :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->jobDetails->designationRel->name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">DOJ :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->jobDetails && $monthlyPayroll->user->jobDetails->joining_date ? \Carbon\Carbon::parse($monthlyPayroll->user->jobDetails->joining_date)->format('d-m-Y') : 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Location :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $company->address ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">PAN :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $maskId($monthlyPayroll->user->basicDetails->pan_no ?? null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Bank Name :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $bankDetails->bank_name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">PF. No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->basicDetails->pf_no ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Account No. :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $maskId($bankDetails->account_number ?? null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">UAN No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $maskId($monthlyPayroll->user->basicDetails->uan_no ?? null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">IFSC No. :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $maskId($bankDetails->ifsc ?? null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">ESI No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $maskId($monthlyPayroll->user->basicDetails->esi_no ?? null) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 60%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Working Days :</td>
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $monthlyPayroll->total_working_days ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">Payable Days :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ ($monthlyPayroll->payable_days ?? 0)}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr> 
                    <tr>
                        <td colspan="2" style="width: 100%; padding-top: 3px; padding-bottom: 3px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 30%; font-weight: 500; font-size: 11px; color: #64748b;">Payroll Period :</td>
                                        <td style="width: 70%; font-size: 12px; color: #111827;">{{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->startOfMonth()->format('M jS, Y') }} to {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->endOfMonth()->format('M jS, Y') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>   
                </tbody>
            </table>
            {{--
                Fully dynamic Earnings/Deductions: built from
                PayrollCalculationEngine::resolveEmployeeComponents() for a
                dynamic-engine payroll (only the employee's actual assigned
                structure components, e.g. a Basic-only employee's slip shows
                only Basic), or from the legacy flat fields for a legacy-engine
                payroll — either way, every row is zero-suppressed and the two
                columns are independent lists (not positionally paired, which
                is what previously let an Employer Contribution row leak into
                the visual "Deductions" column below).
            --}}
            @php
                $isDynamicSlip = ($monthlyPayroll->engine_version ?? null) === 'dynamic_v1';

                if ($isDynamicSlip) {
                    $slipResolved = app(\App\Services\Payroll\PayrollCalculationEngine::class)
                        ->resolveEmployeeComponents($monthlyPayroll->user, $monthlyPayroll->tenant_id, $monthlyPayroll->payroll_month);

                    $slipEarnings = collect($slipResolved['earnings'])
                        ->merge($slipResolved['reimbursements'])
                        ->filter(fn ($c) => $c['amount'] > 0)
                        ->map(fn ($c) => ['name' => $c['name'], 'amount' => $c['amount']])
                        ->values();
                    $slipDeductions = collect($slipResolved['deductions'])
                        ->filter(fn ($c) => $c['amount'] > 0)
                        ->map(fn ($c) => ['name' => $c['name'], 'amount' => $c['amount']])
                        ->values();
                } else {
                    $legacyEarningFields = [
                        'basic_salary' => 'Basic', 'hra' => 'HRA', 'conveyence' => 'Conveyence',
                        'medical_allowance' => 'Medical Allowance', 'children_allowance' => 'Children Allowance',
                        'post_allowance' => 'Post Allowance', 'leave_travel_allowance' => 'Leave Travel Allowance',
                        'monthly_incentive' => 'Monthly Incentive', 'special_allowance' => 'Special Allowance',
                        'overtime_amount' => 'Overtime',
                    ];
                    $legacyDeductionFields = [
                        'provident_fund' => 'Provident Fund', 'esi' => 'ESI', 'professional_tax' => 'PT',
                        'tds' => 'TDS', 'loan_deduction' => 'Loan Deduction', 'other_deductions' => 'Other Deductions',
                    ];

                    $slipEarnings = collect($legacyEarningFields)
                        ->map(fn ($label, $field) => ['name' => $label, 'amount' => (float) ($monthlyPayroll->{$field} ?? 0)])
                        ->filter(fn ($c) => $c['amount'] > 0)
                        ->values();
                    $slipDeductions = collect($legacyDeductionFields)
                        ->map(fn ($label, $field) => ['name' => $label, 'amount' => (float) ($monthlyPayroll->{$field} ?? 0)])
                        ->filter(fn ($c) => $c['amount'] > 0)
                        ->values();

                    // Any component that only exists as a payroll_components row --
                    // custom bonuses, arrears, or anything the dynamic engine
                    // produced with no dedicated fixed column above.
                    if ($monthlyPayroll->components) {
                        $slipEarnings = $slipEarnings->merge(
                            $monthlyPayroll->components->where('component_type', 'earning')
                                ->whereNotIn('component_name', array_values($legacyEarningFields))
                                ->filter(fn ($c) => (float) $c->amount > 0)
                                ->map(fn ($c) => ['name' => $c->component_name, 'amount' => (float) $c->amount])
                        )->values();
                        $slipDeductions = $slipDeductions->merge(
                            $monthlyPayroll->components->where('component_type', 'deduction')
                                ->whereNotIn('component_name', array_values($legacyDeductionFields))
                                ->filter(fn ($c) => (float) $c->amount > 0)
                                ->map(fn ($c) => ['name' => $c->component_name, 'amount' => (float) $c->amount])
                        )->values();
                    }
                }

                $slipRowCount = max($slipEarnings->count(), $slipDeductions->count());
            @endphp
            <table style="width: 100%; border: 1px solid #e5e7eb; border-spacing: 0; border-collapse: collapse; margin-top: 16px;">
                <tbody style=" border-spacing: 0; margin: 0px; padding: 0px;">
                    <tr style=" border-spacing: 0;">
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; font-size: 11px; color: #1e3a8a; text-align: left;">Earnings</th>
                                    <th style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; font-size: 11px; color: #1e3a8a; text-align: right;">Amount (Rs.)</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; font-size: 11px; color: #1e3a8a; text-align: left;">Deductions</th>
                                    <th style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; font-size: 11px; color: #1e3a8a; text-align: right;">Amount (Rs.)</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @for ($i = 0; $i < $slipRowCount; $i++)
                        <tr>
                            <td style="width: 50%;">
                                @if ($slipEarnings->has($i))
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">{{ $slipEarnings[$i]['name'] }}</td>
                                            <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($slipEarnings[$i]['amount'], 2) }}</td>
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                            <td style="width: 50%;">
                                @if ($slipDeductions->has($i))
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">{{ $slipDeductions[$i]['name'] }}</td>
                                            <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($slipDeductions[$i]['amount'], 2) }}</td>
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endfor
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Gross Earnings (A)</th>
                                    <th style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->gross_earnings ?? 0, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Total Deduction (B)</th>
                                    <th style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->total_deductions ?? 0, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width: 100%; margin-top: 14px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #1e3a8a;">
                <tbody>
                    <tr>
                        <th style="border: 1px solid #1e3a8a; padding: 7px 8px; text-align: left; font-size: 13px; color: #1e3a8a; width: 50%;">
                            Net Pay (A-B)
                        </th>
                        <th style="border: 1px solid #1e3a8a; padding: 7px 8px; text-align: right; font-size: 14px; color: #1e3a8a; width: 50%;">
                            {{ number_format($monthlyPayroll->net_payable ?? 0, 2) }}
                        </th>
                    </tr>
                    {{-- <tr>
                        <th style="border: 1px solid #1e3a8a; padding: 7px 8px; text-align: left; font-size: 12px; width: 50%;">
                            In Words
                        </th>
                        <th style="border: 1px solid #1e3a8a; padding: 7px 8px; text-align: right; font-size: 12px; width: 50%;">
                            {{ $monthlyPayroll->net_payable > 0 ? 'Rupees ' . App\Helpers\NumberToWords::transformINR($monthlyPayroll->net_payable) : 'Zero' }}
                        </th>
                    </tr> --}}
                </tbody>
            </table>


            {{-- Employer Contribution / CTC breakdown is intentionally never
                 shown on the salary slip, for anyone (admin or employee) —
                 was previously employee-view-only via $isEmployeeView; now
                 unconditionally hidden here instead. --}}
            @if (false)
                @php
                    $slipEmployerContributions = $isDynamicSlip
                        ? collect($slipResolved['employer_contributions'])->filter(fn ($c) => $c['amount'] > 0)->values()
                        : collect([
                            ['name' => 'Employer PF', 'amount' => (float) ($monthlyPayroll->employer_provident_fund ?? 0)],
                            ['name' => 'Employer ESI', 'amount' => (float) ($monthlyPayroll->employer_esi ?? 0)],
                        ])->filter(fn ($c) => $c['amount'] > 0)->values();
                    $slipEmployerContributionsTotal = $slipEmployerContributions->sum('amount');
                @endphp
                <table style="width: 100%; margin-top: 14px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #e5e7eb;">
                    <tbody>
                        <tr>
                            <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 11px; color: #1e3a8a; width: 50%;">
                                Employer Contribution
                            </th>
                            <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 11px; color: #1e3a8a; width: 50%;">
                                Amount (Rs.)
                            </th>
                        </tr>
                        @forelse ($slipEmployerContributions as $contribution)
                            <tr>
                                <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px; width: 50%;">
                                    {{ $contribution['name'] }}
                                </td>
                                <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px; width: 50%;">
                                    {{ number_format($contribution['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: center; font-size: 11px; color: #94a3b8;">
                                    No employer contribution components
                                </td>
                            </tr>
                        @endforelse
                        <tr>
                            <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px; width: 50%;">
                                Cost To Company (CTC)
                            </th>
                            <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px; width: 50%;">
                                {{ number_format(($monthlyPayroll->gross_earnings ?? 0) + $slipEmployerContributionsTotal, 2) }}
                            </th>
                        </tr>
                    </tbody>
                </table>
            @endif

            <p style="text-align: center; margin-top: 16px; margin-bottom: 0px; font-size: 10px; color: #94a3b8;">Note : This is a computer generated document, hence no signature is required.</p>
        </div>
    </div>
</body>

</html>