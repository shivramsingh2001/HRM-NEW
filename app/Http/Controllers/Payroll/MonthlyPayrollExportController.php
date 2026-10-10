<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CSV export of selected payslips (monthly-payrolls.export). Moved out of
 * MonthlyPayrollController unchanged (code-quality plan, Phase 1).
 */
class MonthlyPayrollExportController extends Controller
{
    // =========================================================================

    public function export(Request $request)
    {
        try {
            $request->validate(['ids' => 'required|json']);

            $ids = json_decode($request->ids, true);
            $payrolls = MonthlyPayroll::with(['user', 'processor'])
                ->whereIn('id', $ids)
                ->get();

            if ($payrolls->isEmpty()) {
                return redirect()->back()->with('error', 'No records found to export.');
            }

            return $this->buildCsvResponse($payrolls, 'payroll_export_'.date('Y-m-d_His').'.csv', true);
        } catch (\Exception $e) {
            Log::error('Export error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to export: '.$e->getMessage());
        }
    }

    /**
     * Build a CSV download response.
     * $detailed = true  -> full columns (used by bulk export with selected IDs)
     * $detailed = false -> summary columns
     */
    private function buildCsvResponse($payrolls, string $filename, bool $detailed)
    {
        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

        $detailed ? $this->writeDetailed($handle, $payrolls) : $this->writeSummary($handle, $payrolls);

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    /** Full columns (bulk export of the selected payslips). */
    private function writeDetailed($handle, $payrolls): void
    {
        fputcsv($handle, [
            'Employee ID',
            'Employee Name',
            'Month',
            'Present Days',
            'Half Days',
            'Working Days',
            'Paid Leaves',
            'Overtime Hours',
            'Basic Salary',
            'HRA',
            'Conveyance',
            'Medical',
            'Gross Earnings',
            'PF Deduction',
            'ESI Deduction',
            'Professional Tax',
            'Salary Advance Deduction',
            'Loan Deduction',
            'Total Deductions',
            'Net Payable',
            'Status',
            'Payment Date',
            'Payment Mode',
        ]);
        foreach ($payrolls as $p) {
            fputcsv($handle, [
                $p->user->employee_id ?? 'N/A',
                $p->user->name ?? 'N/A',
                Carbon::createFromFormat('Y-m', $p->payroll_month)->format('F Y'),
                $p->present_days,
                $p->half_days ?? 0,
                $p->total_working_days,
                $p->paid_leaves,
                $p->overtime_hours,
                $p->basic_salary,
                $p->hra,
                $p->conveyence,
                $p->medical_allowance,
                $p->gross_earnings,
                $p->provident_fund,
                $p->esi,
                $p->professional_tax,
                $p->salary_advance_deduction ?? 0,
                $p->loan_deduction,
                $p->total_deductions,
                $p->net_payable,
                ucfirst($p->payment_status),
                $p->payment_date,
                $p->payment_mode ?? 'N/A',
            ]);
        }
    }

    /** Summary columns. */
    private function writeSummary($handle, $payrolls): void
    {
        fputcsv($handle, [
            'Employee Name',
            'Employee ID',
            'Email',
            'Month',
            'Total Working Days',
            'Payable Days',
            'Net Payable (INR)',
        ]);
        foreach ($payrolls as $p) {
            fputcsv($handle, [
                $p->user->name ?? 'N/A',
                $p->user->employee_id ?? 'N/A',
                $p->user->email ?? 'N/A',
                Carbon::createFromFormat('Y-m', $p->payroll_month)->format('F Y'),
                $p->total_working_days,
                $p->payable_days,
                number_format($p->net_payable, 2),
            ]);
        }
    }
}
