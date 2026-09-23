<?php
// app/Http/Controllers/Api/Performance/PerformanceController.php

namespace App\Http\Controllers\Api\Performance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDailyPerformance;
use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\User;
use App\Services\Performance\PerformanceRollupService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Employee self-service performance API for the Flutter mobile app
 * (routes/api.php, /user/performance/*) — read-only, always scoped to the
 * authenticated employee's own data (no team/manager endpoints here; those
 * stay web-only). Mirrors PerformanceController (web)'s data shapes and
 * Api\Attendance\AttendanceController's response envelope
 * (`{status, message?, data}`, try/catch -> generic 500, Log::error on
 * failure).
 */
class PerformanceController extends Controller
{
    /**
     * Monthly summary: overall score, grade, and the component breakdown —
     * the mobile equivalent of the web my-dashboard's top KPI cards.
     */
    public function summary(Request $request, PerformanceRollupService $rollup)
    {
        try {
            $user = Auth::user();
            $month = $this->safeMonth($request->input('month'));

            $kpiScore = EmployeeKpiScore::where('user_id', $user->id)
                ->where('reporting_month', $month . '-01')
                ->first();

            if (!$kpiScore) {
                $kpiScore = Cache::lock("perf-rollup-{$user->id}-{$month}", 10)
                    ->block(5, fn () => $rollup->rollupMonth($user, $month));
            }

            return response()->json([
                'status' => true,
                'data' => $this->formatKpiScore($kpiScore, $month),
            ], 200);
        } catch (Exception $e) {
            Log::error('Performance summary (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * Last N months of monthly scores (default 6) — mobile trend chart data.
     */
    public function history(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = min(24, max(1, (int) $request->input('limit', 6)));

            $history = EmployeeKpiScore::where('user_id', $user->id)
                ->orderBy('reporting_month', 'desc')
                ->take($limit)
                ->get()
                ->map(fn ($k) => $this->formatKpiScore($k, Carbon::parse($k->reporting_month)->format('Y-m')))
                ->values();

            return response()->json(['status' => true, 'data' => $history], 200);
        } catch (Exception $e) {
            Log::error('Performance history (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * Daily performance rows for one month — mobile daily trend view.
     */
    public function daily(Request $request)
    {
        try {
            $user = Auth::user();
            $month = $this->safeMonth($request->input('month'));
            $monthStart = Carbon::parse($month . '-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $rangeEnd = $monthEnd->lt(Carbon::today()) ? $monthEnd : Carbon::today();

            // Map to plain arrays with an explicit Y-m-d string — the
            // model's date cast re-serializes performance_date as a UTC ISO
            // string on every read (Model::serializeDate() always calls
            // Carbon::toJSON()), which is the wrong calendar day for an IST
            // tenant; re-assigning the cast attribute does not avoid this.
            $rows = EmployeeDailyPerformance::where('user_id', $user->id)
                ->where('calculation_status', 'calculated')
                ->whereBetween('performance_date', [$monthStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
                ->orderBy('performance_date')
                ->get([
                    'performance_date', 'overall_daily_score', 'attendance_score', 'task_completion_score',
                    'task_ontime_score', 'project_participation_score', 'regularization_score',
                    'day_type', 'attendance_status',
                ])
                ->map(fn ($row) => [
                    'performance_date' => $row->performance_date->format('Y-m-d'),
                    'overall_daily_score' => $row->overall_daily_score,
                    'attendance_score' => $row->attendance_score,
                    'task_completion_score' => $row->task_completion_score,
                    'task_ontime_score' => $row->task_ontime_score,
                    'project_participation_score' => $row->project_participation_score,
                    'regularization_score' => $row->regularization_score,
                    'day_type' => $row->day_type,
                    'attendance_status' => $row->attendance_status,
                ]);

            return response()->json(['status' => true, 'data' => $rows], 200);
        } catch (Exception $e) {
            Log::error('Performance daily (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * On-demand weekly buckets for one month (7-day chunks from the 1st,
     * same boundaries as the web dashboard's toggle) — never persisted.
     */
    public function weekly(Request $request, PerformanceRollupService $rollup)
    {
        try {
            $user = Auth::user();
            $tenantId = (int) ($user->tenant_id ?: DB::table('users')->where('id', $user->id)->value('tenant_id'));
            $month = $this->safeMonth($request->input('month'));
            $monthStart = Carbon::parse($month . '-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $rangeEnd = $monthEnd->lt(Carbon::today()) ? $monthEnd : Carbon::today();

            $weeks = [];
            $cursor = $monthStart->copy();
            $weekNo = 1;
            while ($cursor->lte($rangeEnd)) {
                $weeks[] = array_merge(
                    $rollup->weekly($user->id, $tenantId, $cursor->format('Y-m-d')),
                    ['label' => 'Week ' . $weekNo]
                );
                $cursor->addDays(7);
                $weekNo++;
            }

            return response()->json(['status' => true, 'data' => $weeks], 200);
        } catch (Exception $e) {
            Log::error('Performance weekly (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * One day's full breakdown — mobile equivalent of the web day-detail drawer.
     */
    public function dailyDetail(Request $request)
    {
        try {
            $user = Auth::user();
            $date = $this->safeDate($request->input('date'), now()->subDay()->format('Y-m-d'));

            $row = EmployeeDailyPerformance::where('user_id', $user->id)
                ->where('performance_date', $date)
                ->first();

            if (!$row) {
                return response()->json(['status' => false, 'message' => 'No data calculated for this date yet.'], 200);
            }

            // See daily()'s comment — the date cast re-serializes as UTC ISO
            // on every read, so fix the one field up after toArray().
            $data = $row->toArray();
            $data['performance_date'] = $row->performance_date->format('Y-m-d');

            return response()->json(['status' => true, 'data' => $data], 200);
        } catch (Exception $e) {
            Log::error('Performance daily-detail (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * The manager's review for one month, if submitted (or acknowledged).
     */
    public function review(Request $request)
    {
        try {
            $user = Auth::user();
            $month = $this->safeMonth($request->input('month'));

            $review = ManagerPerformanceReview::where('user_id', $user->id)
                ->where('review_month', $month . '-01')
                ->whereIn('status', ['submitted', 'acknowledged'])
                ->with('reviewer:id,name')
                ->first();

            if (!$review) {
                return response()->json(['status' => true, 'data' => null], 200);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $review->id,
                    'review_month' => $review->review_month->format('Y-m-d'),
                    'reviewer_name' => $review->reviewer?->name,
                    'overall_rating' => $review->overall_rating,
                    'strengths' => $review->strengths,
                    'areas_for_improvement' => $review->areas_for_improvement,
                    'achievements' => $review->achievements,
                    'goals_next_month' => $review->goals_next_month,
                    'additional_feedback' => $review->additional_feedback,
                    'status' => $review->status,
                    'submitted_at' => $review->submitted_at?->format('Y-m-d H:i:s'),
                    'employee_acknowledged_at' => $review->employee_acknowledged_at?->format('Y-m-d H:i:s'),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Performance review (API) failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /**
     * Employee acknowledges their submitted review from the app.
     */
    public function acknowledgeReview($id)
    {
        try {
            $review = ManagerPerformanceReview::findOrFail($id);

            if (Auth::id() != $review->user_id) {
                return response()->json(['status' => false, 'message' => 'Only the employee can acknowledge the review.'], 403);
            }

            if ($review->status != 'submitted') {
                return response()->json(['status' => false, 'message' => 'Only submitted reviews can be acknowledged.'], 400);
            }

            $review->update(['status' => 'acknowledged', 'employee_acknowledged_at' => now()]);

            return response()->json(['status' => true, 'message' => 'Review acknowledged successfully.'], 200);
        } catch (Exception $e) {
            Log::error('Performance review acknowledge (API) failed', ['user_id' => Auth::id(), 'review_id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again later.'], 500);
        }
    }

    /** @return array<string,mixed> */
    private function formatKpiScore(EmployeeKpiScore $k, string $month): array
    {
        return [
            'month' => $month,
            'overall_score' => $k->overall_score,
            'grade' => $k->grade,
            'attendance_score' => $k->attendance_score,
            'task_completion_score' => $k->task_completion_score,
            'task_ontime_score' => $k->deadline_met_score,
            'project_participation_score' => $k->project_participation_score,
            'regularization_score' => $k->regularization_score,
            'manager_rating_score' => $k->manager_rating_score,
            'manager_rating_raw' => $k->manager_rating_raw,
            'manager_rating_included' => (bool) $k->manager_rating_included,
            'present_days' => $k->present_days,
            'absent_days' => $k->absent_days,
            'half_days' => $k->half_days,
            'late_days' => $k->late_days,
            'early_departure_days' => $k->early_departure_days,
            'assigned_tasks' => $k->assigned_tasks,
            'completed_tasks' => $k->completed_tasks,
            'overdue_tasks' => $k->overdue_tasks,
            'regularization_count' => $k->regularization_count,
            'days_calculated' => $k->days_calculated,
            'days_expected' => $k->days_expected,
        ];
    }

    private function safeMonth(?string $month): string
    {
        if ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }

        return now()->subMonth()->format('Y-m');
    }

    private function safeDate(?string $date, string $default): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return $default;
    }
}
