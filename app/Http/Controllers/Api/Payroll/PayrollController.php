<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Models\Tenant;
use App\Traits\ResolvesCurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Get list of all payslips for the authenticated user
     */
    public function getMyPayslips(Request $request)
    {
        try {
            $user = $request->user();

            $payslips = MonthlyPayroll::where('user_id', $user->id)
                ->whereIn('payment_status', ['processed', 'paid'])
                ->orderBy('payroll_month', 'desc')
                ->get()
                ->map(function ($payslip) {
                    return [
                        'id' => $payslip->id,
                        'month' => Carbon::createFromFormat('Y-m', $payslip->payroll_month)->format('F Y'),
                        'month_code' => $payslip->payroll_month,
                        'net_salary' => $payslip->net_payable,
                        'status' => $payslip->payment_status,
                        'is_available' => true,
                        'download_url' => route('employee.payslip.download', $payslip->id),
                        'view_url' => route('employee.payslip.view', $payslip->id),
                        'base64_url' => route('employee.payslip.base64', $payslip->id),
                        'generated_on' => $payslip->created_at->format('d M Y'),
                        'payment_date' => $payslip->payment_date ? Carbon::parse($payslip->payment_date)->format('d M Y') : null,
                    ];
                });

            $pendingPayrolls = MonthlyPayroll::where('user_id', $user->id)
                ->where('payment_status', 'pending')
                ->orderBy('payroll_month', 'desc')
                ->get()
                ->map(function ($payslip) {
                    return [
                        'id' => $payslip->id,
                        'month' => Carbon::createFromFormat('Y-m', $payslip->payroll_month)->format('F Y'),
                        'month_code' => $payslip->payroll_month,
                        'net_salary' => null,
                        'status' => 'pending',
                        'is_available' => false,
                        'message' => 'Payroll is being processed',
                        'expected_availability' => Carbon::createFromFormat('Y-m', $payslip->payroll_month)->endOfMonth()->addDays(5)->format('d M Y'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' =>  $payslips,
                    
            ]);
        } catch (\Exception $e) {
            Log::error('Get my payslips error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payslips: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a specific payslip is available
     */
    public function checkPayslipAvailability($id)
    {
        try {
            $user = request()->user();

            $payslip = MonthlyPayroll::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$payslip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not found'
                ], 404);
            }

            $isAvailable = in_array($payslip->payment_status, ['processed', 'paid']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payslip->id,
                    'month' => Carbon::createFromFormat('Y-m', $payslip->payroll_month)->format('F Y'),
                    'month_code' => $payslip->payroll_month,
                    'status' => $payslip->payment_status,
                    'is_available' => $isAvailable,
                    'can_download' => $isAvailable,
                    'can_view' => $isAvailable,
                    'message' => $isAvailable ? 'Payslip ready' : 'Payslip not yet available',
                    'expected_date' => !$isAvailable && $payslip->payment_status == 'pending'
                        ? Carbon::createFromFormat('Y-m', $payslip->payroll_month)->endOfMonth()->addDays(5)->format('d M Y')
                        : null,
                    'urls' => $isAvailable ? [
                        'view' => route('api.employee.payslip.view', $payslip->id),
                        'download' => route('api.employee.payslip.download', $payslip->id),
                        'base64' => route('api.employee.payslip.base64', $payslip->id),
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Check payslip availability error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View payslip in browser or get PDF stream
     */
    public function viewPayslip($id)
    {
        try {
            $user = request()->user();

            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components'
            ])->where('id', $id)
              ->where('user_id', $user->id)
              ->firstOrFail();

            if (!in_array($monthlyPayroll->payment_status, ['processed', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not yet available'
                ], 403);
            }

            // Get user bank details
            $bankDetails = DB::table('user_bank_details')
                ->where('user_id', $monthlyPayroll->user_id)
                ->first();

            // Get tenant/company details
            $tenant_id = $this->currentTenantId($user);
            $company = DB::table('tenants')->where('id', $tenant_id)->first();

            // Generate PDF
            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company
            ]);

            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'payslip_' . $monthlyPayroll->user->employee_id . '_' . $monthlyPayroll->payroll_month . '.pdf';

            // For API requests, return JSON with base64
            if (request()->wantsJson() || request()->has('api')) {
                $pdfContent = $pdf->output();
                $base64Pdf = base64_encode($pdfContent);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pdf_base64' => $base64Pdf,
                        'filename' => $filename,
                        'content_type' => 'application/pdf',
                        'size' => strlen($pdfContent),
                        'month' => Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('F Y'),
                        'employee_name' => $monthlyPayroll->user->name,
                        'net_payable' => $monthlyPayroll->net_payable
                    ]
                ]);
            }

            // For web browsers, stream the PDF
            return $pdf->stream($filename);

        } catch (\Exception $e) {
            Log::error('View payslip error: ' . $e->getMessage());
            
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to view payslip: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to view payslip: ' . $e->getMessage());
        }
    }

    /**
     * Download payslip as PDF
     */
    public function downloadPayslip($id)
    {
        try {
            $user = request()->user();

            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components'
            ])->where('id', $id)
              ->where('user_id', $user->id)
              ->firstOrFail();

            if (!in_array($monthlyPayroll->payment_status, ['processed', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not yet available'
                ], 403);
            }

            // Get user bank details
            $bankDetails = DB::table('user_bank_details')
                ->where('user_id', $monthlyPayroll->user_id)
                ->first();

            // Get tenant/company details
            $tenant_id = $this->currentTenantId($user);
            $company = DB::table('tenants')->where('id', $tenant_id)->first();

            // Generate PDF
            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'payslip_' . $monthlyPayroll->user->employee_id . '_' . $monthlyPayroll->payroll_month . '.pdf';

            // Return PDF as download
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Download payslip error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payslip as base64 encoded string (for mobile apps)
     */
    public function getPayslipBase64($id)
    {
        try {
            $user = request()->user();

            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components'
            ])->where('id', $id)
              ->where('user_id', $user->id)
              ->firstOrFail();

            if (!in_array($monthlyPayroll->payment_status, ['processed', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not yet available'
                ], 403);
            }

            // Get user bank details
            $bankDetails = DB::table('user_bank_details')
                ->where('user_id', $monthlyPayroll->user_id)
                ->first();

            // Get tenant/company details
            $tenant_id = $this->currentTenantId($user);
            $company = DB::table('tenants')->where('id', $tenant_id)->first();

            // Generate PDF
            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company
            ]);

            $pdf->setPaper('A4', 'portrait');

            // Convert to base64
            $pdfContent = $pdf->output();
            $base64Pdf = base64_encode($pdfContent);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $monthlyPayroll->id,
                    'pdf_base64' => $base64Pdf,
                    'filename' => 'payslip_' . $monthlyPayroll->user->employee_id . '_' . $monthlyPayroll->payroll_month . '.pdf',
                    'content_type' => 'application/pdf',
                    'size' => strlen($pdfContent),
                    'month' => Carbon::createFromFormat('Y-m', $monthlyPayroll->payroll_month)->format('F Y'),
                    'month_code' => $monthlyPayroll->payroll_month,
                    'employee_name' => $monthlyPayroll->user->name,
                    'employee_id' => $monthlyPayroll->user->employee_id,
                    'net_payable' => $monthlyPayroll->net_payable,
                    'status' => $monthlyPayroll->payment_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get payslip base64 error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payslip: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payslip summary for a specific month
     */
    public function getPayslipSummary($id)
    {
        try {
            $user = request()->user();

            $payslip = MonthlyPayroll::with([
                'user',
                'components' => function($q) {
                    $q->where('component_type', 'earning');
                }
            ])->where('id', $id)
              ->where('user_id', $user->id)
              ->firstOrFail();

            if (!in_array($payslip->payment_status, ['processed', 'paid'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payslip not yet available'
                ], 403);
            }

            // Get earnings breakdown
            $earnings = [
                ['name' => 'Basic Salary', 'amount' => $payslip->basic_salary],
                ['name' => 'HRA', 'amount' => $payslip->hra],
                ['name' => 'Conveyance', 'amount' => $payslip->conveyence],
                ['name' => 'Medical Allowance', 'amount' => $payslip->medical_allowance],
                ['name' => 'Children Allowance', 'amount' => $payslip->children_allowance ?? 0],
                ['name' => 'Post Allowance', 'amount' => $payslip->post_allowance ?? 0],
                ['name' => 'LTA', 'amount' => $payslip->leave_travel_allowance ?? 0],
                ['name' => 'Monthly Incentive', 'amount' => $payslip->monthly_incentive ?? 0],
                ['name' => 'Special Allowance', 'amount' => $payslip->special_allowance ?? 0],
                ['name' => 'Overtime', 'amount' => $payslip->overtime_amount ?? 0],
            ];

            // Get deductions breakdown
            $deductions = [
                ['name' => 'Provident Fund', 'amount' => $payslip->provident_fund],
                ['name' => 'ESI', 'amount' => $payslip->esi],
                ['name' => 'Professional Tax', 'amount' => $payslip->professional_tax],
                ['name' => 'TDS', 'amount' => $payslip->tds ?? 0],
                ['name' => 'Loan Deduction', 'amount' => $payslip->loan_deduction ?? 0],
                ['name' => 'Other Deductions', 'amount' => $payslip->other_deductions ?? 0],
            ];

            // Filter out zero amounts
            $earnings = array_filter($earnings, function($item) {
                return $item['amount'] > 0;
            });

            $deductions = array_filter($deductions, function($item) {
                return $item['amount'] > 0;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payslip->id,
                    'month' => Carbon::createFromFormat('Y-m', $payslip->payroll_month)->format('F Y'),
                    'month_code' => $payslip->payroll_month,
                    'employee' => [
                        'name' => $payslip->user->name,
                        'employee_id' => $payslip->user->employee_id,
                        'designation' => $payslip->user->jobDetails->designation_name ?? 'N/A',
                        'department' => $payslip->user->jobDetails->department_name ?? 'N/A',
                    ],
                    'attendance' => [
                        'working_days' => $payslip->total_working_days,
                        'present_days' => $payslip->present_days,
                        'absent_days' => $payslip->absent_days,
                        'paid_leaves' => $payslip->paid_leaves,
                        'overtime_hours' => $payslip->overtime_hours,
                    ],
                    'earnings' => array_values($earnings),
                    'deductions' => array_values($deductions),
                    'totals' => [
                        'gross_earnings' => $payslip->gross_earnings,
                        'total_deductions' => $payslip->total_deductions,
                        'net_payable' => $payslip->net_payable,
                    ],
                    'status' => $payslip->payment_status,
                    'payment_date' => $payslip->payment_date ? Carbon::parse($payslip->payment_date)->format('d M Y') : null,
                    'urls' => [
                        'view' => route('api.employee.payslip.view', $payslip->id),
                        'download' => route('api.employee.payslip.download', $payslip->id),
                        'base64' => route('api.employee.payslip.base64', $payslip->id),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get payslip summary error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payslip summary: ' . $e->getMessage()
            ], 500);
        }
    }
}