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
        <div style="border: 1px solid black; padding: 16px 32px;">
            <table style="width: 100%;">
                <tbody>
                    <tr>
                        @php
                            $logoPath = null;
                            if(!empty($company->logo)) {
                                $fullPath = public_path($company->logo);
                                if(file_exists($fullPath)) {
                                    $logoPath = $company->logo;
                                }
                            }
                           
                        @endphp
                        <td style="padding: 16px; padding-bottom: 0px; text-align: center; width: 10%;">
                            @if($logoPath)
                                <img src="{{ $company->logo }}" alt="{{ $company->company_name ?? 'Logo' }}" style="width: 60px; height: 60px; object-fit: contain;">
                            @else
                                <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Default Logo" style="width: 60px; height: 60px; object-fit: contain;">
                            @endif
                        </td>
                        <td style="padding: 8px; width: 80%;">
                            <h3 style="text-align: center; padding-right: 93px;">{{ $company->company_name ?? 'Company Name' }}</h3>
                            <h5 style="text-align: center; padding-right: 93px;">{{ $company->address ?? 'Company Address' }}</h5>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top: 8px; padding-bottom: 32px; width: 90%;">
                            <h4 style="text-align: center;">Pay Slip For The Month of {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('M-Y') }}</h4>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Employee No. :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $monthlyPayroll->user->employee_id ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Name :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->name ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Designation :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $monthlyPayroll->user->jobDetails->designationRel->name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">DOJ :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->jobDetails && $monthlyPayroll->user->jobDetails->joining_date ? \Carbon\Carbon::parse($monthlyPayroll->user->jobDetails->joining_date)->format('d-m-Y') : 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Location :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $company->address ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">PAN :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->basicDetails->pan_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Bank Name :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $bankDetails->bank_name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">PF. No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->basicDetails->pf_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Account No. :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $bankDetails->account_number ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">UAN No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->basicDetails->uan_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">IFSC No. :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $bankDetails->ifsc ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">ESI No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ $monthlyPayroll->user->basicDetails->esi_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Working Days :</td>
                                        <td style="width: 50%; font-size: 14px;">{{ $monthlyPayroll->total_working_days ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Payable Days :</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">{{ ($monthlyPayroll->payable_days ?? 0)}}</td>
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
                                        <td style="width: 30%; font-weight: 700; font-size: 14px;">Payroll Period :</td>
                                        <td style="width: 70%; font-size: 14px;">{{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->startOfMonth()->format('M jS, Y') }} to {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->endOfMonth()->format('M jS, Y') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>   
                </tbody>
            </table>
            <table style="width: 100%; border: 1px solid #000000; border-spacing: 0; border-collapse: collapse; margin-top: 20px;">
                <tbody style=" border-spacing: 0; margin: 0px; padding: 0px;">
                    <tr style=" border-spacing: 0;">
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; font-size: 14px;">Earnings</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; font-size: 14px;">Amount (Rs.)</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; font-size: 14px;">Deductions</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; font-size: 14px;">Amount (Rs.)</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Basic</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->basic_salary ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Provident Fund</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->provident_fund ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">HRA</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->hra ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Employer Provident Fund</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->employer_provident_fund ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Conveyence</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->conveyence ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">ESI</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->esi ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Medical Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->medical_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Employer ESI</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->employer_esi ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Children Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->children_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">PT</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->professional_tax ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Post Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->post_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">TDS</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->tds ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Leave Travel Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->leave_travel_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Loan Deduction</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->loan_deduction ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Monthly Incentive</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->monthly_incentive ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Other Deductions</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->other_deductions ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Gross Earnings (A)</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->gross_earnings ?? 0, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Total Deduction (B)</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->total_deductions ?? 0, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table style="width: 100%; margin-top: 20px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #000000;">
                <tbody>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            Net Pay (A-B)
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            {{ number_format($monthlyPayroll->net_payable ?? 0, 2) }}
                        </th>
                    </tr>
                    {{-- <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            In Words
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            {{ $monthlyPayroll->net_payable > 0 ? 'Rupees ' . App\Helpers\NumberToWords::transformINR($monthlyPayroll->net_payable) : 'Zero' }}
                        </th>
                    </tr> --}}
                </tbody>
            </table>

            
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #000000;">
                <tbody>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            Employer Contribution
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            Amount (Rs.)
                        </th>
                    </tr>
                    <tr>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            Employer PF
                        </td>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            {{ number_format($monthlyPayroll->employer_provident_fund ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            Employer ESI
                        </td>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            {{ number_format($monthlyPayroll->employer_esi ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; width: 50%;">
                            Cost To Company (CTC)
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; width: 50%;">
                            {{ number_format(($monthlyPayroll->gross_earnings ?? 0) + ($monthlyPayroll->employer_provident_fund ?? 0) + ($monthlyPayroll->employer_esi ?? 0), 2) }}
                        </th>
                    </tr>
                </tbody>
            </table>

            <p style="text-align: center; margin-top: 20px; margin-bottom: 0px;">Note : This is a computer generated document, hence no signature is required.</p>
        </div>
    </div>
</body>

</html>