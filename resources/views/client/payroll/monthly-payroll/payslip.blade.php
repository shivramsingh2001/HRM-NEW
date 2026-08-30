{{-- resources/views/client/payroll/monthly-payroll/payslip.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Slip - {{ $monthlyPayroll->user->name ?? 'Employee' }} - {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('M Y') }}</title>
    <style>
        * {
            margin: 0px;
            padding: 0px;  
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.5;
            color: #333;
        }
        .payslip-container {
            padding: 8px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .payslip-border {
            border: 1px solid black;
            padding: 16px 32px;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .fw-700 { font-weight: 700; }
        .fs-14 { font-size: 14px; }
        .mt-20 { margin-top: 20px; }
        .mb-0 { margin-bottom: 0; }
        .w-100 { width: 100%; }
        .w-50 { width: 50%; }
        .w-60 { width: 60%; }
        .w-40 { width: 40%; }
        .w-25 { width: 25%; }
        .w-75 { width: 75%; }
        .w-20 { width: 20%; }
        .w-80 { width: 80%; }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        .border-table, .border-table th, .border-table td {
            border: 1px solid #000000;
        }
        .border-bottom {
            border-bottom: 1px solid #000000;
        }
        .p-8 { padding: 8px; }
        .p-16 { padding: 16px; }
        .p-32 { padding: 32px; }
        .pb-0 { padding-bottom: 0; }
        .pt-8 { padding-top: 8px; }
        .pb-32 { padding-bottom: 32px; }
    </style>
</head>
<body style="min-height: 100vh; width: 100vw; margin: 0px; background: #f5f5f5;">
    <div class="payslip-container">
        <div class="payslip-border" style="background: white;">
            <!-- Header with Logo and Company Info -->
            <table style="width: 100%;">
                <tbody>
                    <tr>
                        <td style="padding: 16px; padding-bottom: 0px; text-align: center; width: 20%;">
                            @php
                                $companyLogo = $company['logo'] ?? asset('assets/images/logo.png');
                            @endphp
                            <img src="{{ $companyLogo }}" alt="Company Logo" style="max-width: 150px; max-height: 100px; object-fit: contain;">
                        </td>
                        <td style="padding: 8px; width: 80%;">
                            <h3 style="text-align: center; margin-bottom: 5px;">{{ $company['name'] ?? 'Shurt Tech Private Limited' }}</h3>
                            <h5 style="text-align: center; font-weight: normal; margin-top: 0;">
                                {{ $company['address'] ?? 'C-101, Sector 2, Noida, Gautam Buddh Nagar, Uttar Pradesh, India - 201301' }}
                            </h5>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="padding-top: 8px; padding-bottom: 32px; width: 80%;">
                            <h4 style="text-align: center; margin: 0;">
                                Pay Slip For The Month of {{ \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('M-Y') }}
                            </h4>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Employee Details Section -->
            <table style="width: 100%;">
                <tbody>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Employee No.</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $monthlyPayroll->user->employee_id ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Name</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Designation</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $monthlyPayroll->user->jobDetails->designation_name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">DOJ</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->jobDetails && $monthlyPayroll->user->jobDetails->joining_date ? \Carbon\Carbon::parse($monthlyPayroll->user->jobDetails->joining_date)->format('d-m-Y') : 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Location</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $company['location'] ?? 'Sector 2, Noida' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">PAN</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->basicDetails->pan_no ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Bank Name</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $bankDetails->bank_name ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">UAN No.</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->basicDetails->uan_no ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Account No.</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $bankDetails->account_number ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">PF No.</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->basicDetails->pf_no ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">IFSC No.</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $bankDetails->ifsc ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">ESI No.</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->user->basicDetails->esi_no ?? 'N/A' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Working Days</td>
                                        <td style="width: 50%; font-size: 14px;">: {{ $monthlyPayroll->total_working_days ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 50%; font-weight: 700; font-size: 14px;">Payable Days</td>
                                        <td style="width: 50%; text-align: right; font-size: 14px;">: {{ $monthlyPayroll->payable_days ?? 0}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr> 
                    <tr>
                        <td colspan="2" style="width: 100%;">
                            <table style="width: 100%;">
                                <tbody>
                                    <tr>
                                        <td style="width: 25%; font-weight: 700; font-size: 14px;">Payroll Period</td>
                                        <td style="width: 75%; font-size: 14px;">: 
                                            @php
                                                $startDate = \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->startOfMonth()->format('M jS, Y');
                                                $endDate = \Carbon\Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->endOfMonth()->format('M jS, Y');
                                            @endphp
                                            {{ $startDate }} to {{ $endDate }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>   
                </tbody>
            </table>

            <!-- Earnings & Deductions Table -->
            <table style="width: 100%; border: 1px solid #000000; border-spacing: 0; border-collapse: collapse; margin-top: 20px;">
                <tbody>
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; font-size: 14px; background: #f0f0f0;">Earnings</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; font-size: 14px; background: #f0f0f0;">Amount (₹)</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; font-size: 14px; background: #f0f0f0;">Deductions</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; font-size: 14px; background: #f0f0f0;">Amount (₹)</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Basic Salary -->
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Basic</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->basic_salary, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Provident Fund</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->provident_fund > 0 ? number_format($monthlyPayroll->provident_fund, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <!-- HRA -->
                    @if($monthlyPayroll->hra > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">HRA</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->hra, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Employer PF</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->employer_provident_fund > 0 ? number_format($monthlyPayroll->employer_provident_fund, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Conveyance -->
                    @if($monthlyPayroll->conveyence > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Conveyance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->conveyence, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">ESI</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->esi > 0 ? number_format($monthlyPayroll->esi, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Medical Allowance -->
                    @if($monthlyPayroll->medical_allowance > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Medical Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->medical_allowance, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Employer ESI</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->employer_esi > 0 ? number_format($monthlyPayroll->employer_esi, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Children Allowance -->
                    @if(($monthlyPayroll->children_allowance ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Children Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->children_allowance, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Professional Tax</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->professional_tax > 0 ? number_format($monthlyPayroll->professional_tax, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Post Allowance -->
                    @if(($monthlyPayroll->post_allowance ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Post Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->post_allowance, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">TDS</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->tds > 0 ? number_format($monthlyPayroll->tds, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- LTA -->
                    @if(($monthlyPayroll->leave_travel_allowance ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Leave Travel Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->leave_travel_allowance, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Loan Deduction</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->loan_deduction > 0 ? number_format($monthlyPayroll->loan_deduction, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Monthly Incentive -->
                    @if(($monthlyPayroll->monthly_incentive ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Monthly Incentive</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->monthly_incentive, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Other Deductions</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ $monthlyPayroll->other_deductions > 0 ? number_format($monthlyPayroll->other_deductions, 2) : '-' }}</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Special Allowance -->
                    @if(($monthlyPayroll->special_allowance ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Special Allowance</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->special_allowance, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">-</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">-</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Overtime -->
                    @if(($monthlyPayroll->overtime_amount ?? 0) > 0)
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">Overtime</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($monthlyPayroll->overtime_amount, 2) }}</td>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">-</td>
                                    <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">-</td>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Additional Components from payroll_components table -->
                    @if($monthlyPayroll->components)
                        @foreach($monthlyPayroll->components->where('component_type', 'earning') as $component)
                            @if(!in_array($component->component_name, ['Basic Salary', 'HRA', 'Conveyance Allowance', 'Medical Allowance', 'Children Allowance', 'Post Allowance', 'Leave Travel Allowance', 'Monthly Incentive', 'Special Allowance', 'Overtime']))
                            <tr>
                                <td style="width: 50%;">
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">{{ $component->component_name }}</td>
                                            <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($component->amount, 2) }}</td>
                                        </tbody>
                                    </table>
                                </td>
                                <td style="width: 50%;">
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">-</td>
                                            <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">-</td>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        
                        @foreach($monthlyPayroll->components->where('component_type', 'deduction') as $component)
                            @if(!in_array($component->component_name, ['Provident Fund', 'Employer PF', 'ESI', 'Employer ESI', 'Professional Tax', 'TDS', 'Loan Deduction', 'Other Deductions']))
                            <tr>
                                <td style="width: 50%;">
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">-</td>
                                            <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">-</td>
                                        </tbody>
                                    </table>
                                </td>
                                <td style="width: 50%;">
                                    <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                        <tbody>
                                            <td style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px;">{{ $component->component_name }}</td>
                                            <td style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px;">{{ number_format($component->amount, 2) }}</td>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <!-- Gross Earnings & Total Deductions -->
                    <tr>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px; background: #f0f0f0;">Gross Earnings (A)</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px; background: #f0f0f0;">{{ number_format($monthlyPayroll->gross_earnings, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table style="width: 100%; border-spacing: 0; border-collapse: collapse;">
                                <tbody>
                                    <th style="width: 60%; border: 1px solid #000000; padding: 8px; text-align: left; font-size: 14px; background: #f0f0f0;">Total Deduction (B)</th>
                                    <th style="width: 40%; border: 1px solid #000000; padding: 8px; text-align: right; font-size: 14px; background: #f0f0f0;">{{ number_format($monthlyPayroll->total_deductions, 2) }}</th>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Net Pay Table -->
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #000000;">
                <tbody>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; background: #f0f0f0;">
                            Net Pay (A-B)
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; background: #f0f0f0;">
                            ₹ {{ number_format($monthlyPayroll->net_payable, 2) }}
                        </th>
                    </tr>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px;">
                            In Words
                        </th>
                        {{-- <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px;">
                            {{ $monthlyPayroll->net_payable > 0 ? 'Rupees ' . \NumberToWords::transformINR($monthlyPayroll->net_payable) : 'Zero' }}
                        </th> --}}
                    </tr>
                </tbody>
            </table>

            <!-- Employer Contribution Table -->
            <table style="width: 100%; margin-top: 20px; border-collapse: collapse; border-spacing: 0px; border: 1px solid #000000;">
                <tbody>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; background: #f0f0f0;">
                            Employer Contribution
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; background: #f0f0f0;">
                            Amount (₹)
                        </th>
                    </tr>
                    <tr>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px;">
                            Employer PF
                        </td>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px;">
                            {{ $monthlyPayroll->employer_provident_fund > 0 ? number_format($monthlyPayroll->employer_provident_fund, 2) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px;">
                            Employer ESI
                        </td>
                        <td style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px;">
                            {{ $monthlyPayroll->employer_esi > 0 ? number_format($monthlyPayroll->employer_esi, 2) : '-' }}
                        </td>
                    </tr>
                    <tr>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: left; font-size: 14px; background: #f0f0f0;">
                            Cost To Company (CTC)
                        </th>
                        <th style="border: 2px solid #000000; padding: 8px; text-align: right; font-size: 14px; background: #f0f0f0;">
                            {{ number_format($monthlyPayroll->gross_earnings + $monthlyPayroll->employer_provident_fund + $monthlyPayroll->employer_esi, 2) }}
                        </th>
                    </tr>
                </tbody>
            </table>

            <p style="text-align: center; margin-top: 20px; margin-bottom: 0px; font-size: 12px; color: #666;">
                Note : This is a computer generated document, hence no signature is required.
            </p>
        </div>
    </div>
</body>
</html>