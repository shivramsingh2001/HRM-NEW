<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use App\Services\LeaveYearService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/leave-history — the leave ledger: every credit and debit
 * (yearly/monthly/weekly credits, leave taken, revocations, carry-forward
 * lapses and expiries) plus the carry-forward results per period. Visibility
 * follows leave:view (own / team / company).
 *
 * Query: from / to = YYYY-MM-DD (default: the current leave year), leave_type_id, user_id.
 */
class LeaveHistoryController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'leave');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            $yearStart = app(LeaveYearService::class)->startFor($tenantId, now());
            $from = $this->ymd($request->from) ?? $yearStart->toDateString();
            $to = $this->ymd($request->to) ?? now()->toDateString();

            $tx = DB::table('leave_transactions as t')
                ->join('users as u', 'u.id', '=', 't.user_id')
                ->leftJoin('leave_types as lt', 'lt.id', '=', 't.leave_type')
                ->where('t.tenant_id', $tenantId)
                ->whereBetween('t.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->select(['t.id', 't.user_id', 'u.name as employee_name', 'u.employee_id as employee_code', 't.leave_type as leave_type_id',
                    'lt.name as leave_type', 't.transaction_type', 't.leaves_count', 't.before_leaves', 't.after_leaves',
                    't.transaction_date', 't.leave_id', 't.leave_detail', 't.remarks', 't.created_at']);
            if ($ids !== null) {
                $tx->whereIn('t.user_id', $ids ?: [0]);
            }
            if ($request->filled('leave_type_id')) {
                $tx->where('t.leave_type', (int) $request->leave_type_id);
            }
            $rows = $tx->orderByDesc('t.created_at')->limit(2000)->get();

            $kind = function ($r): string {
                $remarks = strtolower((string) $r->remarks);

                return match (true) {
                    str_starts_with($remarks, 'carry forward:') => 'carry_forward_lapse',
                    str_starts_with($remarks, 'carried leave expired') => 'carry_forward_expiry',
                    $r->leave_id !== null && $r->transaction_type === 'sub' => 'leave_taken',
                    $r->leave_id !== null && $r->transaction_type === 'add' => 'leave_revoked',
                    $r->transaction_type === 'add' => 'credit',
                    default => 'adjustment',
                };
            };

            $data = $rows->map(fn ($r) => [
                'id' => $r->id,
                'employee' => ['id' => $r->user_id, 'name' => $r->employee_name, 'employee_id' => $r->employee_code],
                'leave_type' => ['id' => $r->leave_type_id, 'name' => $r->leave_type],
                'kind' => $kind($r), // credit | leave_taken | leave_revoked | carry_forward_lapse | carry_forward_expiry | adjustment
                'direction' => $r->transaction_type, // add | sub
                'days' => (float) $r->leaves_count,
                'balance_before' => $r->before_leaves !== null ? (float) $r->before_leaves : null,
                'balance_after' => $r->after_leaves !== null ? (float) $r->after_leaves : null,
                'date' => $r->transaction_date ?: Carbon::parse($r->created_at)->toDateString(),
                'leave_id' => $r->leave_id,
                'paid' => $r->leave_detail,
                'remarks' => $r->remarks,
            ])->values();

            $cf = DB::table('leave_carry_forwards as c')
                ->join('users as u', 'u.id', '=', 'c.user_id')
                ->leftJoin('leave_types as lt', 'lt.id', '=', 'c.leave_type_id')
                ->where('c.tenant_id', $tenantId)
                ->whereBetween('c.leave_year_start', [$from, $to])
                ->select(['c.*', 'u.name as employee_name', 'u.employee_id as employee_code', 'lt.name as leave_type', 'lt.credit_type']);
            if ($ids !== null) {
                $cf->whereIn('c.user_id', $ids ?: [0]);
            }
            if ($request->filled('leave_type_id')) {
                $cf->where('c.leave_type_id', (int) $request->leave_type_id);
            }
            $carry = $cf->orderByDesc('c.leave_year_start')->get()->map(fn ($c) => [
                'employee' => ['id' => $c->user_id, 'name' => $c->employee_name, 'employee_id' => $c->employee_code],
                'leave_type' => ['id' => $c->leave_type_id, 'name' => $c->leave_type],
                'period_start' => $c->leave_year_start, // 1st of month / Monday / leave-year start, per the type's credit type
                'period' => match ($c->credit_type) { 'monthly' => 'month', 'weekly' => 'week', default => 'leave_year' },
                'closing_balance' => (float) $c->closing_balance,
                'limit' => $c->carry_limit !== null ? (float) $c->carry_limit : null,
                'carried' => (float) $c->carried,
                'lapsed' => (float) $c->lapsed,
                'expires_on' => $c->expires_on,
                'expired' => (float) $c->expired,
            ])->values();

            return response()->json([
                'success' => true,
                'message' => 'Leave history fetched successfully',
                'data' => $data,
                'carry_forward' => $carry,
                'summary' => [
                    'period' => ['from' => $from, 'to' => $to],
                    'entries' => $data->count(),
                    'by_kind' => $data->groupBy('kind')->map(fn ($g) => ['entries' => $g->count(), 'days' => round($g->sum('days'), 2)]),
                    'credited_days' => round($data->where('kind', 'credit')->sum('days'), 2),
                    'taken_days' => round($data->where('kind', 'leave_taken')->sum('days') - $data->where('kind', 'leave_revoked')->sum('days'), 2),
                    'lapsed_days' => round($data->whereIn('kind', ['carry_forward_lapse', 'carry_forward_expiry'])->sum('days'), 2),
                    'carry_forward_enabled' => (bool) DB::table('tenants')->where('id', $tenantId)->value('leave_carry_forward_enabled'),
                    'truncated' => $rows->count() >= 2000,
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('leave history', $e);
        }
    }
}
