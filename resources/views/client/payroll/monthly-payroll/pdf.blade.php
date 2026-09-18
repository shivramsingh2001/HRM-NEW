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
                        <td style="padding: 8px; padding-bottom: 0px; text-align: center; width: 10%;">
                            @if($logoPath)
                                <img src="{{ $company->logo }}" alt="{{ $company->company_name ?? 'Logo' }}" style="width: 48px; height: 48px; object-fit: contain;">
                            @else
                                <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Default Logo" style="width: 48px; height: 48px; object-fit: contain;">
                            @endif
                        </td>
                        <td style="padding: 8px; width: 80%;">
                            <h3 style="text-align: center; padding-right: 80px; font-size: 16px; color: #1e3a8a; margin-bottom: 2px;">{{ $company->company_name ?? 'Company Name' }}</h3>
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
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->basicDetails->pan_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $bankDetails->account_number ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">UAN No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->basicDetails->uan_no ?? 'N/A' }}</td>
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
                                        <td style="width: 50%; font-size: 12px; color: #111827;">{{ $bankDetails->ifsc ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 40%; padding-top: 3px; padding-bottom: 3px; padding-left: 20px;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 500; font-size: 11px; color: #64748b;">ESI No. :</td>
                                        <td style="width: 50%; text-align: right; font-size: 12px; color: #111827;">{{ $monthlyPayroll->user->basicDetails->esi_no ?? 'N/A' }}</td>
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
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Basic</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->basic_salary ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Provident Fund</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->provident_fund ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">HRA</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->hra ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Employer Provident Fund</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->employer_provident_fund ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Conveyence</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->conveyence ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">ESI</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->esi ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Medical Allowance</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->medical_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Employer ESI</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->employer_esi ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Children Allowance</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->children_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">PT</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->professional_tax ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Post Allowance</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->post_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">TDS</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->tds ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Leave Travel Allowance</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->leave_travel_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Loan Deduction</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->loan_deduction ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Monthly Incentive</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->monthly_incentive ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Other Deductions</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->other_deductions ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Special Allowance</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->special_allowance ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;"></td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">Overtime</td>
                                    <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($monthlyPayroll->overtime_amount ?? 0, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;"></td>
                    </tr>
                    {{--
                        Any component that only exists as a payroll_components row -- custom
                        bonuses, arrears, or anything the dynamic engine produced with no
                        dedicated fixed column above -- rendered here so the downloadable PDF
                        can't diverge from what show.blade.php already displays on screen.
                    --}}
                    @php
                        $pdfFixedEarningNames = [
                            'Basic Salary', 'HRA', 'Conveyance Allowance', 'Medical Allowance',
                            'Children Allowance', 'Post Allowance', 'Leave Travel Allowance',
                            'Monthly Incentive', 'Special Allowance', 'Overtime',
                        ];
                        $pdfFixedDeductionNames = [
                            'Provident Fund', 'ESI', 'Professional Tax', 'TDS',
                            'Loan Deduction', 'Other Deductions',
                        ];
                        $pdfExtraEarnings = $monthlyPayroll->components
                            ? $monthlyPayroll->components->where('component_type', 'earning')
                                ->whereNotIn('component_name', $pdfFixedEarningNames)
                            : collect();
                        $pdfExtraDeductions = $monthlyPayroll->components
                            ? $monthlyPayroll->components->where('component_type', 'deduction')
                                ->whereNotIn('component_name', $pdfFixedDeductionNames)
                            : collect();
                    @endphp
                    @foreach ($pdfExtraEarnings as $extraEarning)
                        <tr>
                            <td style="width: 50%;">
                                <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                    <tbody>
                                        <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">{{ $extraEarning->component_name }}</td>
                                        <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($extraEarning->amount, 2) }}</td>
                                    </tbody>
                                </table>
                            </td>
                            <td style="width: 50%;"></td>
                        </tr>
                    @endforeach
                    @foreach ($pdfExtraDeductions as $extraDeduction)
                        <tr>
                            <td style="width: 50%;"></td>
                            <td style="width: 50%;">
                                <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                    <tbody>
                                        <td style="width: 60%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px;">{{ $extraDeduction->component_name }}</td>
                                        <td style="width: 40%; border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px;">{{ number_format($extraDeduction->amount, 2) }}</td>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endforeach
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
                    <tr>
                        <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px; width: 50%;">
                            Employer PF
                        </td>
                        <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px; width: 50%;">
                            {{ number_format($monthlyPayroll->employer_provident_fund ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px; width: 50%;">
                            Employer ESI
                        </td>
                        <td style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px; width: 50%;">
                            {{ number_format($monthlyPayroll->employer_esi ?? 0, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 12px; width: 50%;">
                            Cost To Company (CTC)
                        </th>
                        <th style="border: 1px solid #e5e7eb; padding: 5px 8px; text-align: right; font-size: 12px; width: 50%;">
                            {{ number_format(($monthlyPayroll->gross_earnings ?? 0) + ($monthlyPayroll->employer_provident_fund ?? 0) + ($monthlyPayroll->employer_esi ?? 0), 2) }}
                        </th>
                    </tr>
                </tbody>
            </table>

            <p style="text-align: center; margin-top: 16px; margin-bottom: 0px; font-size: 10px; color: #94a3b8;">Note : This is a computer generated document, hence no signature is required.</p>
        </div>
    </div>
</body>

</html>