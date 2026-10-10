<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Services\Payroll\PayslipContext;
use App\Traits\AuthorizesByScope;
use App\Traits\ResolvesCurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payslip PDFs — the admin payslip (monthly-payrolls.payslip) and the
 * employee's own salary slips (my-salary-slips, download / view). Moved out
 * of MonthlyPayrollController unchanged (code-quality plan, Phase 1); route
 * names are the same.
 */
class PayslipController extends Controller
{
    use AuthorizesByScope, ResolvesCurrentTenant;

    public function __construct(private PayslipContext $context) {}

    // =========================================================================

    public function generatePayslip($id)
    {
        try {
            $tenantId = $this->currentTenantId();
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'payrollMaster',
            ])->findOrFail($id);

            $bankDetails = $this->context->bankDetails((int) $monthlyPayroll->user_id, $tenantId);

            $company = $this->context->company($tenantId);

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'payslip_'
                .$monthlyPayroll->user->employee_id
                .'_'.$monthlyPayroll->payroll_month.'.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Generate payslip error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to generate payslip: '.$e->getMessage());
        }
    }

    // =========================================================================

    public function mySalarySlips(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = MonthlyPayroll::with(['processor'])
                ->where('user_id', $authUser->id)
                ->whereIn('payment_status', ['paid', 'processed']);

            if ($request->filled('year')) {
                $query->whereRaw('LEFT(payroll_month, 4) = ?', [$request->year]);
            }
            if ($request->filled('month')) {
                $query->whereRaw('RIGHT(payroll_month, 2) = ?', [str_pad($request->month, 2, '0', STR_PAD_LEFT)]);
            }

            $salarySlips = $query->orderBy('payroll_month', 'desc')->paginate(20);

            $years = MonthlyPayroll::where('user_id', $authUser->id)
                ->whereIn(DB::raw('LOWER(payment_status)'), ['paid', 'processed'])
                ->selectRaw('DISTINCT LEFT(payroll_month, 4) as year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();

            $months = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December',
            ];

            $summary = [
                'total_earned' => $salarySlips->sum('net_payable'),
                'total_slips' => $salarySlips->total(),
                'average_salary' => $salarySlips->count() > 0
                    ? $salarySlips->sum('net_payable') / $salarySlips->count()
                    : 0,
                'last_slip_date' => $salarySlips->isNotEmpty()
                    ? $salarySlips->first()->payroll_month
                    : null,
            ];

            return view(
                'client.payroll.monthly-payroll.employee-salary-slips',
                compact('salarySlips', 'years', 'months', 'summary')
            );
        } catch (\Exception $e) {
            Log::error('My salary slips error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error loading salary slips: '.$e->getMessage());
        }
    }

    // PDF -- SALARY SLIPS (employee self-service)

    public function downloadSalarySlip($id)
    {
        try {
            $authUser = Auth::user();
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'processor',
            ])->findOrFail($id);

            if (! $this->scopeCoversOwner($authUser, 'payroll', 'view', $monthlyPayroll->user_id)) {
                abort(403, 'Unauthorized access.');
            }

            if (! in_array($monthlyPayroll->payment_status, ['paid', 'processed'])) {
                return redirect()->back()
                    ->with('error', 'Salary slip is only available for paid or processed payrolls.');
            }

            $bankDetails = $this->context->bankDetails((int) $monthlyPayroll->user_id, $this->currentTenantId());

            $company = $this->context->company($this->currentTenantId());

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company,
                'isEmployeeView' => true,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'salary_slip_'
                .$monthlyPayroll->user->employee_id
                .'_'.$monthlyPayroll->payroll_month.'.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Download salary slip error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to download salary slip: '.$e->getMessage());
        }
    }

    // =========================================================================

    public function viewSalarySlip($id)
    {
        try {
            $authUser = Auth::user();
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'user.jobDetails.department',
                'user.jobDetails.designation',
                'components',
                'processor',
            ])->findOrFail($id);

            if (! $this->scopeCoversOwner($authUser, 'payroll', 'view', $monthlyPayroll->user_id)) {
                abort(403, 'Unauthorized access.');
            }

            if (! in_array($monthlyPayroll->payment_status, ['paid', 'processed'])) {
                return redirect()->back()
                    ->with('error', 'Salary slip is only available for paid or processed payrolls.');
            }

            $bankDetails = $this->context->bankDetails((int) $monthlyPayroll->user_id, $this->currentTenantId());

            $company = $this->context->company($this->currentTenantId());

            $pdf = Pdf::loadView('client.payroll.monthly-payroll.pdf', [
                'monthlyPayroll' => $monthlyPayroll,
                'bankDetails' => $bankDetails,
                'company' => $company,
                'isEmployeeView' => true,
            ]);

            $pdf->setPaper('A4', 'portrait');

            return $pdf->stream('salary_slip.pdf');
        } catch (\Exception $e) {
            Log::error('View salary slip error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to view salary slip: '.$e->getMessage());
        }
    }
}
