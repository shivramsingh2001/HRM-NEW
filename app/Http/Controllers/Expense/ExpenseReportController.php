<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Expense\ExpenseAgeingService;
use App\Services\RbacService;
use App\Support\Money;
use App\Traits\AuthorizesByScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Expense reports, each with a CSV export (`?export=csv`):
 *   register — every payment (voucher, date, employee, mode, reference, status) in a date range
 *   summary  — claims by employee / category / project for a period
 *   ageing   — how old each employee's UNSPENT advance is (0-30 / 31-60 / 61-90 / 90+ days)
 *
 * Behind permission:expenses,export. Rows are limited to the employees the caller's `export` scope
 * covers (own / team / company) — the same RBAC scoping as every other Expense screen.
 */
class ExpenseReportController extends Controller
{
    use AuthorizesByScope, SanitizesCsv;

    public const REPORTS = ['register' => 'Payment register', 'summary' => 'Expense summary', 'ageing' => 'Outstanding advances'];

    public function __construct(private ExpenseAgeingService $ageing)
    {
    }

    public function hub()
    {
        return redirect()->route('expense.reports.show', 'register');
    }

    public function show(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $user = Auth::user();
        $data = match ($report) {
            'register' => $this->register($request, $user),
            'summary' => $this->summary($request, $user),
            'ageing' => $this->ageingReport($request, $user),
        };

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $data, $user);
        }

        return view('client.expense.expense.reports', $data + [
            'report' => $report,
            'reports' => self::REPORTS,
            'filters' => $request->query(),
        ]);
    }

    // ==================== reports ====================

    private function register(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);

        $query = ExpensePayment::query()
            ->join('expenses', 'expenses.id', '=', 'expense_payments.expense_id')
            ->join('users', 'users.id', '=', 'expenses.user_id')
            ->leftJoin('expense_payment_batches as pb', 'pb.id', '=', 'expense_payments.batch_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('company_branches as cb', 'cb.id', '=', 'ujd.branch_id')
            ->whereBetween('expense_payments.payment_date', [$from, $to]);

        $this->applyScope($query, 'expenses.user_id', $user, 'expenses', 'export');

        if ($request->filled('user_id')) {
            $query->where('expenses.user_id', (int) $request->user_id);
        }
        if ($request->filled('payment_mode')) {
            $query->where('expense_payments.payment_mode', $request->payment_mode);
        }
        if (in_array($request->status, ['posted', 'voided'], true)) {
            $query->where('expense_payments.status', $request->status);
        }
        if ($request->filled('branch_id')) {
            $query->where('ujd.branch_id', (int) $request->branch_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.employee_id', 'like', "%{$search}%")
                    ->orWhere('expenses.expense_number', 'like', "%{$search}%")
                    ->orWhere('pb.voucher_number', 'like', "%{$search}%")
                    ->orWhere('expense_payments.reference_number', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('expense_payments.payment_date')->orderBy('expense_payments.id')
            ->get([
                'expense_payments.payment_date', 'expense_payments.amount', 'expense_payments.payment_mode', 'expense_payments.reference_number',
                'expense_payments.status', 'expense_payments.void_reason', 'pb.voucher_number',
                'expenses.expense_number', 'expenses.requirement_type', 'users.name as employee_name', 'users.employee_id as employee_code',
                'cb.name as branch_name',
            ]);

        $posted = $rows->where('status', 'posted');

        return [
            'title' => 'Payment register',
            'from' => $from, 'to' => $to,
            'headers' => ['Date', 'Voucher', 'Employee ID', 'Employee', 'Branch', 'Expense No', 'Type', 'Mode', 'Reference', 'Amount', 'Status'],
            'rows' => $rows->map(fn ($r) => [
                Carbon::parse($r->payment_date)->format('Y-m-d'), $r->voucher_number, $r->employee_code, $r->employee_name, $r->branch_name ?? '—', $r->expense_number,
                $r->requirement_type, $r->payment_mode, $r->reference_number, number_format((float) $r->amount, 2, '.', ''),
                $r->status === 'voided' ? 'voided: ' . $r->void_reason : 'posted',
            ])->all(),
            'summary' => ['Payments (posted)' => $posted->count(), 'Total paid' => '₹' . number_format(Money::fromCents($posted->sum(fn ($r) => Money::toCents($r->amount))), 2)],
            'employees' => $this->employeeOptions($user),
            'branches' => $this->branchOptions($user),
        ];
    }

    private function summary(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);
        $group = in_array($request->group, ['employee', 'category', 'project'], true) ? $request->group : 'employee';
        $type = in_array($request->requirement_type, ['spend', 'advance', 'settlement', 'reimbursement', 'all'], true) ? $request->requirement_type : 'spend';

        // Shortfall reimbursements are part of their settlement's amount — don't count them twice.
        $query = Expense::query()->join('users', 'users.id', '=', 'expenses.user_id')
            ->whereNull('expenses.parent_expense_id')
            ->whereBetween('expenses.date', [$from, $to]);

        $this->applyScope($query, 'expenses.user_id', $user, 'expenses', 'export');

        if ($type === 'spend') {
            $query->whereIn('expenses.requirement_type', ['settlement', 'reimbursement']);
        } elseif ($type !== 'all') {
            $query->where('expenses.requirement_type', $type);
        }
        if ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;
            $query->whereExists(function ($sub) use ($branchId) {
                $sub->select(DB::raw(1))->from('user_job_details')
                    ->whereColumn('user_job_details.user_id', 'expenses.user_id')
                    ->where('user_job_details.branch_id', $branchId);
            });
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.employee_id', 'like', "%{$search}%");
            });
        }

        [$labelSql, $groupBy] = match ($group) {
            'category' => ["COALESCE(et.name, '—')", ['et.id', 'et.name']],
            'project' => ["COALESCE(pr.name, 'No project')", ['pr.id', 'pr.name']],
            default => ["CONCAT(users.name, ' (', COALESCE(users.employee_id, ''), ')')", ['users.id', 'users.name', 'users.employee_id']],
        };

        if ($group === 'category') {
            $query->leftJoin('expense_types as et', 'et.id', '=', 'expenses.expense_type');
        } elseif ($group === 'project') {
            $query->leftJoin('projects as pr', 'pr.id', '=', 'expenses.project_id');
        }

        $rows = $query->groupBy($groupBy)->orderBy(DB::raw($labelSql))
            ->selectRaw("{$labelSql} AS label, COUNT(*) AS claims,
                COALESCE(SUM(CASE WHEN expenses.status <> 'cancelled' THEN expenses.amount END), 0) AS submitted,
                COALESCE(SUM(CASE WHEN expenses.status IN ('approved','complete') THEN expenses.amount END), 0) AS approved,
                COALESCE(SUM(CASE WHEN expenses.status = 'pending' THEN expenses.amount END), 0) AS pending,
                COALESCE(SUM(CASE WHEN expenses.status = 'cancelled' THEN expenses.amount END), 0) AS cancelled")
            ->get();

        $t = fn (string $col) => Money::fromCents($rows->sum(fn ($r) => Money::toCents($r->{$col})));

        return [
            'title' => 'Expense summary by ' . $group,
            'from' => $from, 'to' => $to, 'group' => $group, 'requirement_type' => $type,
            'headers' => [ucfirst($group), 'Claims', 'Submitted', 'Approved', 'Pending', 'Rejected / withdrawn'],
            'rows' => $rows->map(fn ($r) => [$r->label, (int) $r->claims, $this->m($r->submitted), $this->m($r->approved), $this->m($r->pending), $this->m($r->cancelled)])->all(),
            'summary' => ['Claims' => (int) $rows->sum('claims'), 'Submitted' => '₹' . number_format($t('submitted'), 2), 'Approved' => '₹' . number_format($t('approved'), 2)],
            'branches' => $this->branchOptions($user),
        ];
    }

    private function ageingReport(Request $request, User $user): array
    {
        $userIds = $this->scopedUserIds($user);

        if ($request->filled('branch_id')) {
            $branchUserIds = DB::table('user_job_details')->where('branch_id', (int) $request->branch_id)->pluck('user_id')->all();
            $userIds = $userIds === null ? $branchUserIds : array_values(array_intersect($userIds, $branchUserIds));
        }

        $snapshot = $this->ageing->snapshot((int) $user->tenant_id, $userIds);

        $branchByUser = DB::table('user_job_details')
            ->join('company_branches', 'company_branches.id', '=', 'user_job_details.branch_id')
            ->whereIn('user_job_details.user_id', array_column($snapshot['rows'], 'user_id'))
            ->pluck('company_branches.name', 'user_job_details.user_id');

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $snapshot['rows'] = array_values(array_filter($snapshot['rows'], fn ($r) =>
                stripos($r['name'], $search) !== false || stripos((string) $r['employee_id'], $search) !== false
            ));
        }

        return [
            'title' => 'Outstanding advances',
            'as_of' => $snapshot['as_of'],
            'headers' => ['Employee ID', 'Employee', 'Branch', 'Unspent balance', ...array_keys(ExpenseAgeingService::BUCKETS), 'No ledger', 'Oldest (days)'],
            'rows' => array_map(fn ($r) => [
                $r['employee_id'], $r['name'], $branchByUser[$r['user_id']] ?? '—', $this->m($r['balance']),
                ...array_map(fn ($b) => $this->m($b), array_values($r['buckets'])),
                $this->m($r['no_ledger']), $r['oldest_days'] ?? '—',
            ], $snapshot['rows']),
            'totals_row' => ['', 'TOTAL', '', $this->m($snapshot['totals']['balance']),
                ...array_map(fn ($k) => $this->m($snapshot['totals'][$k]), array_keys(ExpenseAgeingService::BUCKETS)),
                $this->m($snapshot['totals']['no_ledger']), ''],
            'summary' => ['Employees holding an advance' => count($snapshot['rows']), 'Total unspent' => '₹' . number_format($snapshot['totals']['balance'], 2)],
            'note' => 'Age = how long the OLDEST unspent part of the advance has been held (settlements consume the oldest advance first). '
                . '"No ledger" is balance the transaction history cannot account for — run expense:reconcile-balances.',
            'branches' => $this->branchOptions($user),
        ];
    }

    // ==================== helpers ====================

    private function csv(string $report, array $data, User $user)
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

        app(AuditLogger::class)->record('tenant_user', $user->id, (int) $user->tenant_id, 'expenses.report_exported', 'ExpenseReport', null, [], ['report' => $report, 'rows' => count($data['rows'])]);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="expense-' . $report . '-' . now()->format('Ymd') . '.csv"',
        ]);
    }

    /** @return array{0: string, 1: string} inclusive Y-m-d range; defaults to the current month */
    private function period(Request $request): array
    {
        $from = $request->filled('from_date') ? Carbon::parse($request->from_date)->toDateString() : now()->startOfMonth()->toDateString();
        $to = $request->filled('to_date') ? Carbon::parse($request->to_date)->toDateString() : now()->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /** null = every employee in the tenant (company scope); otherwise the ids the `export` scope covers. */
    private function scopedUserIds(User $user): ?array
    {
        $scope = app(RbacService::class)->scopeFor($user, 'expenses', 'export');
        abort_if($scope === null, 403, 'You do not have permission to export expense reports.');

        return match ($scope) {
            'company' => null,
            'own' => [$user->id],
            default => User::managedBy($user->id)->pluck('id')->push($user->id)->unique()->values()->all(),
        };
    }

    private function employeeOptions(User $user)
    {
        $ids = $this->scopedUserIds($user);

        return User::where('status', 1)->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')->get(['id', 'name', 'employee_id']);
    }

    /** Branch dropdown for the report filter bar (tenant-scoped, active only). */
    private function branchOptions(User $user)
    {
        return DB::table('company_branches')
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function m(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
