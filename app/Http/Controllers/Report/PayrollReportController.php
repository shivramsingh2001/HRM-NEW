<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Loan;
use App\Models\MonthlyPayroll;
use App\Models\PayrollEmployeeStructure;
use App\Models\PayrollStructure;
use App\Models\User;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Payroll Reports — Monthly Payroll, Employee Payroll Structure, Loan and a department-wise
 * Payroll Summary. Money-sensitive, so unlike Expense/Asset reports this whole area (the tab
 * itself, gated in `client/report/index.blade.php`, and every method here) requires the caller's
 * `payroll,view` RBAC scope to resolve to 'company' — a manager's `payroll.view` scope is 'own'
 * (config/rbac.php), so they are not offered a cross-employee payroll report; they keep the
 * existing self-service `my-payroll/*` screens. The Loan Report is the one exception: it is
 * scoped by `loans,view` (company for admin/hr, team for a manager), matching the existing
 * `loan.reports.*` endpoints, so a manager keeps seeing their team's loans here too.
 */
class PayrollReportController extends Controller
{
    use SanitizesCsv;

    public const REPORTS = [
        'monthly' => 'Monthly Payroll',
        'structure' => 'Employee Payroll Structure',
        'loan' => 'Loan Report',
        'summary' => 'Payroll Summary',
    ];

    public function show(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $user = $request->user();
        $this->authorizeReport($user, $report);

        $data = match ($report) {
            'monthly' => $this->monthly($request, $user),
            'structure' => $this->structure($request, $user),
            'loan' => $this->loan($request, $user),
            'summary' => $this->summary($request, $user),
        };

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $data);
        }

        return view('client.report.payroll.reports', $data + [
            'report' => $report,
            'reports' => self::REPORTS,
            'filters' => $request->query(),
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ==================== reports ====================

    private function monthly(Request $request, User $user): array
    {
        $month = $this->month($request);

        $query = MonthlyPayroll::query()
            ->join('users', 'users.id', '=', 'monthly_payrolls.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->where('monthly_payrolls.payroll_month', $month);

        $this->scopeByUser($query, 'monthly_payrolls.user_id', $user, 'payroll');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if (in_array($request->query('payment_status'), ['pending', 'processing', 'paid'], true)) {
            $query->where('monthly_payrolls.payment_status', $request->query('payment_status'));
        }

        $rows = $query->orderBy('users.name')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'monthly_payrolls.gross_earnings',
                'monthly_payrolls.total_deductions', 'monthly_payrolls.net_payable', 'monthly_payrolls.payment_status',
                'monthly_payrolls.payment_date',
            ]);

        return [
            'title' => 'Monthly Payroll — ' . Carbon::createFromFormat('Y-m', $month)->format('F Y'),
            'month' => $month,
            'headers' => ['Employee ID', 'Employee', 'Department', 'Gross', 'Deductions', 'Net Payable', 'Status', 'Paid On'],
            'rows' => $rows->map(fn ($r) => [
                $r->employee_id, $r->name, $r->department ?? '—', $this->m($r->gross_earnings), $this->m($r->total_deductions),
                $this->m($r->net_payable), ucfirst($r->payment_status), $r->payment_date ? Carbon::parse($r->payment_date)->format('d M Y') : '—',
            ])->all(),
            'summary' => [
                'Employees' => $rows->count(),
                'Total gross' => '₹' . number_format((float) $rows->sum('gross_earnings'), 2),
                'Total deductions' => '₹' . number_format((float) $rows->sum('total_deductions'), 2),
                'Total net payable' => '₹' . number_format((float) $rows->sum('net_payable'), 2),
            ],
        ];
    }

    private function structure(Request $request, User $user): array
    {
        $query = PayrollEmployeeStructure::query()
            ->join('users', 'users.id', '=', 'payroll_employee_structures.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->leftJoin('payroll_structures as ps', 'ps.id', '=', 'payroll_employee_structures.payroll_structure_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'payroll_employee_structures.approved_by')
            ->where('payroll_employee_structures.is_current', true)
            ->whereNull('payroll_employee_structures.deleted_at');

        $this->scopeByUser($query, 'payroll_employee_structures.user_id', $user, 'payroll');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if ($request->filled('payroll_structure_id')) {
            $query->where('payroll_employee_structures.payroll_structure_id', (int) $request->query('payroll_structure_id'));
        }

        $rows = $query->orderBy('users.name')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'ps.name as structure_name',
                'payroll_employee_structures.ctc', 'payroll_employee_structures.effective_from',
                'payroll_employee_structures.status', 'approver.name as approver_name',
            ]);

        return [
            'title' => 'Employee Payroll Structure',
            'headers' => ['Employee ID', 'Employee', 'Department', 'Structure', 'CTC (annual)', 'Effective From', 'Status', 'Approved By'],
            'rows' => $rows->map(fn ($r) => [
                $r->employee_id, $r->name, $r->department ?? '—', $r->structure_name ?? '—', $this->m($r->ctc),
                $r->effective_from ? Carbon::parse($r->effective_from)->format('d M Y') : '—',
                ucfirst($r->status ?? 'approved'), $r->approver_name ?? '—',
            ])->all(),
            'summary' => [
                'Employees' => $rows->count(),
                'Total CTC (annual)' => '₹' . number_format((float) $rows->sum('ctc'), 2),
                'Structures in use' => $rows->pluck('structure_name')->filter()->unique()->count(),
            ],
            'structures' => PayrollStructure::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function loan(Request $request, User $user): array
    {
        $query = Loan::query()
            ->join('users', 'users.id', '=', 'loans.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->leftJoin('loan_categories as lc', 'lc.id', '=', 'loans.loan_type_id');

        $this->scopeByUser($query, 'loans.user_id', $user, 'loans');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if (in_array($request->query('status'), array_keys(Loan::getStatuses()), true)) {
            $query->where('loans.status', $request->query('status'));
        }

        $rows = $query->orderByDesc('loans.loan_date')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'lc.name as category',
                'loans.loan_number', 'loans.amount', 'loans.remaining_amount', 'loans.status', 'loans.loan_date',
            ]);

        return [
            'title' => 'Loan Report',
            'headers' => ['Loan No.', 'Employee ID', 'Employee', 'Department', 'Category', 'Amount', 'Outstanding', 'Status', 'Loan Date'],
            'rows' => $rows->map(fn ($r) => [
                $r->loan_number, $r->employee_id, $r->name, $r->department ?? '—', $r->category ?? '—',
                $this->m($r->amount), $this->m($r->remaining_amount), Loan::getStatuses()[$r->status] ?? ucfirst($r->status),
                $r->loan_date ? Carbon::parse($r->loan_date)->format('d M Y') : '—',
            ])->all(),
            'summary' => [
                'Loans' => $rows->count(),
                'Total disbursed' => '₹' . number_format((float) $rows->sum('amount'), 2),
                'Outstanding' => '₹' . number_format((float) $rows->sum('remaining_amount'), 2),
                'Active' => $rows->where('status', Loan::STATUS_ACTIVE)->count(),
            ],
            'loanStatuses' => Loan::getStatuses(),
        ];
    }

    private function summary(Request $request, User $user): array
    {
        $month = $this->month($request);

        $query = MonthlyPayroll::query()
            ->join('users', 'users.id', '=', 'monthly_payrolls.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->where('monthly_payrolls.payroll_month', $month);

        $this->scopeByUser($query, 'monthly_payrolls.user_id', $user, 'payroll');

        $rows = $query->groupBy('d.id', 'd.name')
            ->orderBy('d.name')
            ->selectRaw("COALESCE(d.name, 'No department') AS department, COUNT(*) AS employees,
                COALESCE(SUM(monthly_payrolls.gross_earnings), 0) AS gross,
                COALESCE(SUM(monthly_payrolls.total_deductions), 0) AS deductions,
                COALESCE(SUM(monthly_payrolls.net_payable), 0) AS net,
                SUM(monthly_payrolls.payment_status = 'paid') AS paid_count")
            ->get();

        return [
            'title' => 'Payroll Summary — ' . Carbon::createFromFormat('Y-m', $month)->format('F Y'),
            'month' => $month,
            'headers' => ['Department', 'Employees', 'Gross', 'Deductions', 'Net Payable', 'Paid'],
            'rows' => $rows->map(fn ($r) => [
                $r->department, (int) $r->employees, $this->m($r->gross), $this->m($r->deductions), $this->m($r->net),
                (int) $r->paid_count . ' / ' . (int) $r->employees,
            ])->all(),
            'totals_row' => ['TOTAL', (int) $rows->sum('employees'), $this->m($rows->sum('gross')), $this->m($rows->sum('deductions')), $this->m($rows->sum('net')), (int) $rows->sum('paid_count') . ' / ' . (int) $rows->sum('employees')],
            'summary' => [
                'Departments' => $rows->count(),
                'Employees' => (int) $rows->sum('employees'),
                'Total net payable' => '₹' . number_format((float) $rows->sum('net'), 2),
            ],
        ];
    }

    // ==================== helpers ====================

    /** The Payroll Reports tab is only offered when `payroll,view` resolves to company scope (admin/hr). */
    private function authorizeReport(User $user, string $report): void
    {
        $module = $report === 'loan' ? 'loans' : 'payroll';
        $scope = app(RbacService::class)->scopeFor($user, $module, 'view');

        if ($report === 'loan') {
            abort_if($scope === null, 403, 'You do not have permission to view loan reports.');

            return;
        }

        abort_unless($scope === 'company', 403, 'You do not have permission to view payroll reports.');
    }

    /** null scope (no permission) already aborted by authorizeReport(); 'company' = no filter, else scoped to the user's own/team ids. */
    private function scopeByUser($query, string $column, User $user, string $module): void
    {
        $scope = app(RbacService::class)->scopeFor($user, $module, 'view');

        if ($scope === 'company') {
            return;
        }

        $ids = $scope === 'own'
            ? [$user->id]
            : User::managedBy($user->id)->pluck('id')->push($user->id)->unique()->values()->all();

        $query->whereIn($column, $ids);
    }

    private function month(Request $request): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : now()->format('Y-m');
    }

    private function csv(string $report, array $data)
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeCsvRow($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            $this->writeCsvRow($handle, $row);
        }
        if (! empty($data['totals_row'])) {
            $this->writeCsvRow($handle, $data['totals_row']);
        }
        rewind($handle);
        $body = stream_get_contents($handle);
        fclose($handle);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="payroll-' . $report . '-' . now()->format('Ymd') . '.csv"',
        ]);
    }

    private function m(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
