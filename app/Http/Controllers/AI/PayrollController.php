<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/payroll — monthly payslips: attendance days, earnings, deductions
 * (incl. loan EMI / salary advance / late-early), overtime, net pay and payment
 * status, with every component line. Visibility follows payroll:view
 * (own / team / company). Someone seeing only their own payslips sees only
 * processed / paid ones — never a draft still being edited.
 *
 * Query: month=YYYY-MM, from_month / to_month=YYYY-MM, payment_status, user_id.
 * Default: the last 6 months.
 */
class PayrollController extends Controller
{
    use AiScope;

    private const MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'payroll');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            foreach (['month', 'from_month', 'to_month'] as $k) {
                if ($request->filled($k) && ! preg_match(self::MONTH, (string) $request->{$k})) {
                    return response()->json(['success' => false, 'message' => "{$k} must be YYYY-MM."], 422);
                }
            }
            $fromMonth = $request->month ?: ($request->from_month ?: now()->subMonths(5)->format('Y-m'));
            $toMonth = $request->month ?: ($request->to_month ?: now()->format('Y-m'));

            $query = DB::table('monthly_payrolls as p')
                ->join('users as u', 'u.id', '=', 'p.user_id')
                ->where('p.tenant_id', $tenantId)
                ->whereBetween('p.payroll_month', [$fromMonth, $toMonth])
                ->select(['p.*', 'u.name as employee_name', 'u.employee_id as employee_code']);
            if ($ids !== null) {
                $query->whereIn('p.user_id', $ids ?: [0]);
            }
            if ($scope === 'own') {
                $query->whereIn('p.payment_status', ['processed', 'paid']);
            }
            if ($request->filled('payment_status')) {
                $query->where('p.payment_status', $request->payment_status);
            }

            $slips = $query->orderByDesc('p.payroll_month')->orderBy('u.name')->get();

            $components = DB::table('payroll_components')->where('tenant_id', $tenantId)
                ->whereIn('monthly_payroll_id', $slips->pluck('id')->all() ?: [0])
                ->get(['monthly_payroll_id', 'component_name', 'component_type', 'amount', 'is_taxable'])
                ->groupBy('monthly_payroll_id');

            $f = fn ($v) => round((float) ($v ?? 0), 2);

            $data = $slips->map(function ($p) use ($components, $f) {
                $lines = $components->get($p->id, collect());

                return [
                    'id' => $p->id,
                    'employee' => ['id' => $p->user_id, 'name' => $p->employee_name, 'employee_id' => $p->employee_code],
                    'month' => $p->payroll_month,
                    'engine' => $p->engine_version ?: 'legacy',
                    'payment' => [
                        'status' => $p->payment_status, // pending (draft) | processed | paid | cancelled
                        'date' => $p->payment_date,
                        'mode' => $p->payment_mode,
                        'reference' => $p->transaction_reference,
                    ],
                    'days' => [
                        'working_days' => $p->total_working_days,
                        'payable_days' => $p->payable_days !== null ? (float) $p->payable_days : null,
                        'present' => $p->present_days,
                        'half_days' => $p->half_days !== null ? (float) $p->half_days : null,
                        'absent' => $p->absent_days,
                        'paid_leave' => $p->paid_leaves !== null ? (float) $p->paid_leaves : null,
                        'unpaid_leave' => $p->unpaid_leaves !== null ? (float) $p->unpaid_leaves : null,
                        'holidays' => $p->holidays,
                        'week_offs' => $p->week_offs,
                    ],
                    'overtime' => [
                        'hours' => $f($p->overtime_hours),
                        'rate_per_hour' => $f($p->overtime_rate),
                        'amount' => $f($p->overtime_amount),
                    ],
                    'earnings' => [
                        'basic' => $f($p->basic_salary), 'hra' => $f($p->hra), 'conveyance' => $f($p->conveyence),
                        'medical' => $f($p->medical_allowance), 'children' => $f($p->children_allowance),
                        'post' => $f($p->post_allowance), 'lta' => $f($p->leave_travel_allowance),
                        'incentive' => $f($p->monthly_incentive), 'special' => $f($p->special_allowance),
                        'overtime' => $f($p->overtime_amount),
                    ],
                    'deductions' => [
                        'pf' => $f($p->provident_fund), 'esi' => $f($p->esi), 'professional_tax' => $f($p->professional_tax),
                        'tds' => $f($p->tds), 'loan_emi' => $f($p->loan_deduction),
                        'salary_advance' => $f($p->salary_advance_deduction),
                        'late' => $f($p->late_deduction), 'early_leaving' => $f($p->early_deduction),
                        'other' => $f($p->other_deductions),
                    ],
                    'employer_contributions' => ['pf' => $f($p->employer_provident_fund), 'esi' => $f($p->employer_esi)],
                    'gross_earnings' => $f($p->gross_earnings),
                    'total_deductions' => $f($p->total_deductions),
                    'net_pay' => $f($p->net_payable),
                    // Every line on the payslip (dynamic components, bonuses, arrears, reimbursements …).
                    'lines' => $lines->map(fn ($c) => [
                        'name' => $c->component_name,
                        'type' => $c->component_type,
                        'amount' => $f($c->amount),
                        'taxable' => (bool) $c->is_taxable,
                    ])->values(),
                    'remarks' => $p->remarks,
                    'processed_at' => $p->processing_date,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Payroll data fetched successfully',
                'data' => $data,
                'summary' => [
                    'period' => ['from_month' => $fromMonth, 'to_month' => $toMonth],
                    'payslips' => $data->count(),
                    'employees' => $data->pluck('employee.id')->unique()->count(),
                    'by_payment_status' => $data->countBy(fn ($r) => $r['payment']['status']),
                    'total_gross' => round($data->sum('gross_earnings'), 2),
                    'total_deductions' => round($data->sum('total_deductions'), 2),
                    'total_net_pay' => round($data->sum('net_pay'), 2),
                    'total_overtime_amount' => round($data->sum(fn ($r) => $r['overtime']['amount']), 2),
                    'total_loan_and_advance_recovered' => round($data->sum(fn ($r) => $r['deductions']['loan_emi'] + $r['deductions']['salary_advance']), 2),
                    'by_month' => $data->groupBy('month')->map(fn ($g) => [
                        'payslips' => $g->count(),
                        'gross' => round($g->sum('gross_earnings'), 2),
                        'net_pay' => round($g->sum('net_pay'), 2),
                    ]),
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('payroll', $e);
        }
    }
}
