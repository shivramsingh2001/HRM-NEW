<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/performance — monthly KPI score per employee (overall, grade, component scores,
 * attendance / task counts), the manager's review, a 6-month trend and the current month so far
 * (average of the daily scores). Visibility follows performance:view (own / team / company).
 */
class PerformanceController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;

            $scope = $this->scopeOf($authUser, 'performance');
            if ($scope === null) {
                return $this->forbidden();
            }
            $ids = $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id);

            if ($request->filled('month') && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->month)) {
                return response()->json(['success' => false, 'message' => 'Invalid month. Use the format YYYY-MM.'], 422);
            }

            $kpi = fn () => DB::table('employee_kpi_scores as k')->where('k.tenant_id', $tenantId)
                ->when($ids !== null, fn ($q) => $q->whereIn('k.user_id', $ids ?: [0]));

            // Default month = the latest month that has scores for the visible employees.
            $month = $request->filled('month')
                ? $request->month . '-01'
                : ($kpi()->max('k.reporting_month') ?? Carbon::now()->startOfMonth()->subMonth()->toDateString());
            $month = Carbon::parse($month)->startOfMonth();

            $rows = $kpi()->join('users as u', 'u.id', '=', 'k.user_id')
                ->where('k.reporting_month', $month->toDateString())
                ->get(['k.*', 'u.name as employee_name', 'u.employee_id as employee_code']);

            $userIds = $rows->pluck('user_id')->all();

            // 6-month trend (ending at $month) — one query.
            $trend = $kpi()->whereIn('k.user_id', $userIds ?: [0])
                ->whereBetween('k.reporting_month', [$month->copy()->subMonths(5)->toDateString(), $month->toDateString()])
                ->orderBy('k.reporting_month')->get(['k.user_id', 'k.reporting_month', 'k.overall_score', 'k.grade'])
                ->groupBy('user_id');

            // Manager reviews for the month: visible once submitted, or to the reviewer.
            $reviews = DB::table('manager_performance_reviews')->where('tenant_id', $tenantId)
                ->whereIn('user_id', $userIds ?: [0])->where('review_month', $month->toDateString())
                ->where(fn ($q) => $q->whereIn('status', ['submitted', 'acknowledged'])->orWhere('reviewer_id', $authUser->id))
                ->get()->keyBy('user_id');
            $reviewers = $this->people($tenantId, $reviews->pluck('reviewer_id'));

            // Current month so far: average daily score over calculated working days.
            $mtdQuery = DB::table('employee_daily_performance')->where('tenant_id', $tenantId)
                ->whereBetween('performance_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::today()->toDateString()])
                ->where('calculation_status', 'calculated')->where('day_type', 'working')
                ->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids ?: [0]))
                ->groupBy('user_id')
                ->select('user_id', DB::raw('ROUND(AVG(overall_daily_score), 2) as avg_score'), DB::raw('COUNT(*) as days'),
                    DB::raw('SUM(is_late) as late_days'), DB::raw("SUM(attendance_status = 'absent') as absent_days"));
            $mtd = $mtdQuery->get()->keyBy('user_id');

            $num = fn ($v) => $v === null ? null : (float) $v;

            $data = $rows->map(function ($k) use ($trend, $reviews, $reviewers, $mtd, $num) {
                $review = $reviews->get($k->user_id);
                $sofar = $mtd->get($k->user_id);

                return [
                    'employee' => ['id' => $k->user_id, 'name' => $k->employee_name, 'employee_id' => $k->employee_code],
                    'month' => substr($k->reporting_month, 0, 7),
                    'overall_score' => $num($k->overall_score),
                    'grade' => $k->grade,
                    'status' => $k->status,
                    'component_scores' => [
                        'attendance' => $num($k->attendance_score),
                        'task_completion' => $num($k->task_completion_score),
                        'deadline_met' => $num($k->deadline_met_score),
                        'regularization' => $num($k->regularization_score),
                        'project_participation' => $num($k->project_participation_score),
                        'manager_rating' => $k->manager_rating_included ? $num($k->manager_rating_score) : null,
                    ],
                    'attendance' => [
                        'present_days' => $num($k->present_days),
                        'absent_days' => $num($k->absent_days),
                        'half_days' => $num($k->half_days),
                        'late_days' => $num($k->late_days),
                        'early_departure_days' => $num($k->early_departure_days),
                        'paid_leaves' => $num($k->paid_leaves),
                        'unpaid_leaves' => $num($k->unpaid_leaves),
                        'working_days' => $num($k->working_days_in_period),
                        'total_late_minutes' => $num($k->total_late_minutes),
                        'overtime_hours' => $num($k->overtime_hours),
                    ],
                    'tasks' => [
                        'assigned' => $num($k->assigned_tasks),
                        'completed' => $num($k->completed_tasks),
                        'on_time' => $num($k->on_time_completed_tasks),
                        'late' => $num($k->late_completed_tasks),
                        'overdue' => $num($k->overdue_tasks),
                    ],
                    'regularizations' => [
                        'total' => $num($k->regularization_count),
                        'approved' => $num($k->approved_regularization_count),
                        'rejected' => $num($k->rejected_regularization_count),
                        'pending' => $num($k->pending_regularization_count),
                    ],
                    'manager_feedback' => $k->manager_feedback,
                    'manager_review' => $review ? [
                        'reviewer' => $reviewers[$review->reviewer_id] ?? null,
                        'overall_rating' => $num($review->overall_rating),
                        'strengths' => $review->strengths,
                        'areas_for_improvement' => $review->areas_for_improvement,
                        'achievements' => $review->achievements,
                        'goals_next_month' => $review->goals_next_month,
                        'additional_feedback' => $review->additional_feedback,
                        'status' => $review->status,
                        'submitted_at' => $review->submitted_at,
                        'acknowledged_at' => $review->employee_acknowledged_at,
                    ] : null,
                    'trend' => $trend->get($k->user_id, collect())->map(fn ($t) => [
                        'month' => substr($t->reporting_month, 0, 7),
                        'overall_score' => $num($t->overall_score),
                        'grade' => $t->grade,
                    ])->values(),
                    'current_month_so_far' => $sofar ? [
                        'average_daily_score' => (float) $sofar->avg_score,
                        'days_scored' => (int) $sofar->days,
                        'late_days' => (int) $sofar->late_days,
                        'absent_days' => (int) $sofar->absent_days,
                    ] : null,
                ];
            })->sortByDesc(fn ($r) => $r['overall_score'] ?? -1)->values();

            $scored = $data->whereNotNull('overall_score');
            $brief = fn ($r) => ['employee' => $r['employee'], 'overall_score' => $r['overall_score'], 'grade' => $r['grade']];

            $summary = [
                'month' => $month->format('Y-m'),
                'employees' => $data->count(),
                'average_score' => $scored->count() ? round($scored->avg('overall_score'), 2) : null,
                'by_grade' => $data->countBy(fn ($r) => $r['grade'] ?? 'Not graded'),
            ];
            if ($scope !== 'own') {
                $summary['top_5'] = $scored->take(5)->map($brief)->values();
                $summary['bottom_5'] = $scored->reverse()->take(5)->map($brief)->values();
            }

            return response()->json([
                'success' => true,
                'message' => 'Performance data fetched successfully',
                'data' => $data,
                'summary' => $summary,
                'scope' => $scope,
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('performance', $e);
        }
    }
}
