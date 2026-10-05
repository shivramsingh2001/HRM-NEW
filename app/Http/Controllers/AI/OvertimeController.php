<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use App\Services\Attendance\OvertimePolicyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/overtime — overtime entries (requested and automatic) for a month
 * or date range, with per-employee totals against the monthly limit and the
 * company's overtime mode. Visibility follows overtime:view (own / team / company).
 *
 * Query: month=YYYY-MM (default current) or from/to=YYYY-MM-DD, status, source (request|auto), user_id.
 */
class OvertimeController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'overtime');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            if ($request->filled('month') && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->month)) {
                return response()->json(['success' => false, 'message' => 'month must be YYYY-MM.'], 422);
            }
            $from = $this->ymd($request->from);
            $to = $this->ymd($request->to);
            if (! $from || ! $to) {
                $m = Carbon::createFromFormat('Y-m-d', ($request->month ?: now()->format('Y-m')) . '-01');
                [$from, $to] = [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()];
            }

            $query = DB::table('overtime_requests as o')
                ->join('users as u', 'u.id', '=', 'o.user_id')
                ->leftJoin('users as a', 'a.id', '=', 'o.approved_by')
                ->where('o.tenant_id', $tenantId)
                ->whereBetween('o.date', [$from, $to])
                ->select(['o.id', 'o.user_id', 'u.name as employee_name', 'u.employee_id', 'o.date', 'o.overtime_hours',
                    'o.approved_hours', 'o.status', 'o.source', 'o.reason', 'o.rejection_reason', 'o.auto_minutes',
                    'o.manually_adjusted_at', 'o.created_by', 'a.name as approver_name', 'o.approved_at', 'o.created_at']);
            if ($ids !== null) {
                $query->whereIn('o.user_id', $ids ?: [0]);
            }
            if ($request->filled('status')) {
                $query->where('o.status', $request->status);
            }
            if ($request->filled('source')) {
                $query->where('o.source', $request->source);
            }

            $rows = $query->orderByDesc('o.date')->get();

            $data = $rows->map(fn ($o) => [
                'id' => $o->id,
                'employee' => ['id' => $o->user_id, 'name' => $o->employee_name, 'employee_id' => $o->employee_id],
                'date' => $o->date,
                'source' => $o->source ?? 'request', // auto = calculated from attendance
                'status' => $o->status,
                'requested_hours' => (float) $o->overtime_hours,
                'approved_hours' => $o->status === 'approved' ? (float) ($o->approved_hours ?? $o->overtime_hours) : null,
                'auto_minutes' => $o->auto_minutes !== null ? (int) $o->auto_minutes : null,
                'adjusted_by_hr' => $o->manually_adjusted_at !== null,
                'raised_by_admin' => $o->created_by !== null && (int) $o->created_by !== (int) $o->user_id,
                'reason' => $o->reason,
                'rejection_reason' => $o->rejection_reason,
                'approved_by' => $o->approver_name,
                'approved_at' => $o->approved_at,
                'created_at' => $o->created_at,
            ])->values();

            // Per-employee totals for the period (+ monthly limit when the period is one month).
            $otPolicy = app(OvertimePolicyService::class);
            $oneMonth = substr($from, 0, 7) === substr($to, 0, 7);
            $perEmployee = $data->groupBy(fn ($r) => $r['employee']['id'])->map(function ($items) use ($otPolicy, $tenantId, $oneMonth) {
                $emp = $items->first()['employee'];
                $cap = $oneMonth ? $otPolicy->monthlyCap($tenantId, (int) $emp['id']) : null;
                $approved = round($items->where('status', 'approved')->sum('approved_hours'), 2);

                return [
                    'employee' => $emp,
                    'approved_hours' => $approved,
                    'pending_hours' => round($items->where('status', 'pending')->sum('requested_hours'), 2),
                    'auto_hours' => round($items->where('status', 'approved')->where('source', 'auto')->sum('approved_hours'), 2),
                    'monthly_limit' => $cap,
                    'remaining_this_month' => $cap !== null ? max(0, round($cap - $approved - $items->where('status', 'pending')->sum('requested_hours'), 2)) : null,
                ];
            })->values();

            $company = $otPolicy->company($tenantId);

            return response()->json([
                'success' => true,
                'message' => 'Overtime data fetched successfully',
                'data' => $data,
                'per_employee' => $perEmployee,
                'summary' => [
                    'period' => ['from' => $from, 'to' => $to],
                    'total_entries' => $data->count(),
                    'by_status' => $data->countBy('status'),
                    'by_source' => $data->countBy('source'),
                    'approved_hours' => round($data->where('status', 'approved')->sum('approved_hours'), 2),
                    'pending_hours' => round($data->where('status', 'pending')->sum('requested_hours'), 2),
                    'rejected_hours' => round($data->where('status', 'rejected')->sum('requested_hours'), 2),
                ],
                'settings' => [
                    'enabled' => $otPolicy->enabled($tenantId),
                    'mode' => $company->mode ?? 'request', // request = employees raise requests; auto = calculated from attendance
                    'auto_start_basis' => $company->auto_start_basis ?? 'grace',
                    'auto_start_after_minutes' => (int) ($company->auto_start_after_minutes ?? 0),
                    'min_hours' => $company->min_hours !== null ? (float) $company->min_hours : null,
                    'max_hours_per_day' => $company->max_hours_per_day !== null ? (float) $company->max_hours_per_day : null,
                    'max_hours_per_month' => $company->max_hours_per_month !== null ? (float) $company->max_hours_per_month : null,
                    'rate_type' => $company->rate_type ?? 'multiplier',
                    'rate_multiplier' => (float) ($company->rate_multiplier ?? 1.5),
                    'fixed_rate_per_hour' => $company->fixed_rate_per_hour !== null ? (float) $company->fixed_rate_per_hour : null,
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('overtime', $e);
        }
    }
}
