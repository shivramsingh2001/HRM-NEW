<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/daily-report — the day-wise work reports employees file against an approved
 * WFH / travel request (work done, challenges, next-day plan, hours), plus the request days that
 * still have no submitted report. Visibility follows requests:view (own / team / company).
 * Default range: 1st of the current month → today.
 */
class DailyReportController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'requests');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            $start = $this->ymd($request->start_date) ?? Carbon::now()->startOfMonth()->toDateString();
            $end = $this->ymd($request->end_date) ?? Carbon::today()->toDateString();
            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            $reports = DB::table('daily_reports as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->leftJoin('requests as q', 'q.id', '=', 'r.request_id')
                ->leftJoin('request_types as t', 't.id', '=', 'q.request_type_id')
                ->where('r.tenant_id', $tenantId)
                ->whereBetween('r.report_date', [$start, $end])
                ->when($ids !== null, fn ($q) => $q->whereIn('r.user_id', $ids ?: [0]))
                ->when($request->filled('status'), fn ($q) => $q->where('r.status', strtoupper($request->status)))
                ->orderByDesc('r.report_date')
                ->get([
                    'r.id', 'r.report_date', 'r.user_id', 'u.name as employee_name', 'u.employee_id as employee_code',
                    'r.request_id', 't.type_name as request_type', 'q.start_date as request_start', 'q.end_date as request_end',
                    'r.work_done', 'r.challenges_faced', 'r.next_day_plan', 'r.start_time', 'r.end_time', 'r.total_hours',
                    'r.status', 'r.manager_comments', 'r.created_at',
                ]);

            $data = $reports->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->report_date,
                'employee' => ['id' => $r->user_id, 'name' => $r->employee_name, 'employee_id' => $r->employee_code],
                'request' => $r->request_id ? ['id' => $r->request_id, 'type' => $r->request_type,
                    'start_date' => $r->request_start, 'end_date' => $r->request_end] : null,
                'work_done' => $r->work_done,
                'challenges_faced' => $r->challenges_faced,
                'next_day_plan' => $r->next_day_plan,
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'total_hours' => $r->total_hours !== null ? (float) $r->total_hours : null,
                'status' => $r->status,
                'manager_comments' => $r->manager_comments,
                'submitted_at' => $r->created_at,
            ])->values();

            // Approved request days up to today with no SUBMITTED report.
            $submitted = DB::table('daily_reports')->where('tenant_id', $tenantId)->where('status', 'SUBMITTED')
                ->whereBetween('report_date', [$start, $end])
                ->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids ?: [0]))
                ->get(['user_id', 'report_date'])
                ->map(fn ($r) => $r->user_id . '|' . substr($r->report_date, 0, 10))->flip();
            $lastDay = min($end, Carbon::today()->toDateString());
            $missing = DB::table('requests as q')->join('users as u', 'u.id', '=', 'q.user_id')
                ->leftJoin('request_types as t', 't.id', '=', 'q.request_type_id')
                ->where('q.tenant_id', $tenantId)->where('q.status', 'APPROVED')
                ->where('q.start_date', '<=', $lastDay)->where('q.end_date', '>=', $start)
                ->when($ids !== null, fn ($q) => $q->whereIn('q.user_id', $ids ?: [0]))
                ->get(['q.id', 'q.user_id', 'u.name', 'u.employee_id', 't.type_name as type', 'q.start_date', 'q.end_date'])
                ->flatMap(function ($q) use ($start, $lastDay, $submitted) {
                    $from = max($start, substr($q->start_date, 0, 10));
                    $to = min($lastDay, substr($q->end_date, 0, 10));
                    if ($from > $to) {
                        return [];
                    }

                    return collect(CarbonPeriod::create($from, $to))
                        ->map(fn ($d) => $d->toDateString())
                        ->reject(fn ($d) => isset($submitted[$q->user_id . '|' . $d]))
                        ->map(fn ($d) => ['date' => $d, 'employee' => ['id' => $q->user_id, 'name' => $q->name, 'employee_id' => $q->employee_id],
                            'request' => ['id' => $q->id, 'type' => $q->type]]);
                })->sortBy('date')->values();

            return response()->json([
                'success' => true,
                'message' => 'Daily report data fetched successfully',
                'data' => $data,
                'missing_reports' => $missing,
                'summary' => [
                    'total_reports' => $data->count(),
                    'submitted' => $data->where('status', 'SUBMITTED')->count(),
                    'drafts' => $data->where('status', 'DRAFT')->count(),
                    'total_hours' => round($data->sum('total_hours'), 2),
                    'missing_reports' => $missing->count(),
                    'employees_with_missing_reports' => $missing->pluck('employee.id')->unique()->count(),
                    'date_range' => ['start' => $start, 'end' => $end],
                ],
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('daily reports', $e);
        }
    }
}
