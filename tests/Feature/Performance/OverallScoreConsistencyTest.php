<?php

namespace Tests\Feature\Performance;

use App\Models\EmployeeDailyPerformance;
use App\Models\EmployeeKpiScore;
use App\Models\ManagerPerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Performance\PerformanceRollupService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Regression test for the core reported bug: the old module computed
 * "overall score" four different, mutually-inconsistent ways
 * (PerformanceCalculationService, EmployeeKpiScore::getOverallScoreAttribute(),
 * PerformanceController::calculateOverallScore(),
 * ManagerPerformanceReviewController::recalculateOverallScore()) — the same
 * stored score could even earn a different letter grade depending which
 * code path last touched the row. Every write path now goes through
 * PerformanceRollupService, which is itself backed by the single
 * PerformanceScoreCalculator::blend()/grade(). This test proves the monthly
 * rollup path and the manager-review-submission path agree exactly.
 */
class OverallScoreConsistencyTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private string $month;
    private ?array $originalKpiRow = null;
    private ?array $originalReviewRow = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
        $this->userId = (int) \DB::table('users')
            ->where('tenant_id', $this->tenantId)->where('status', '1')
            ->value('id');

        $joiningDate = \DB::table('user_job_details')->where('user_id', $this->userId)->value('joining_date');
        $candidate = Carbon::parse($joiningDate ?: now()->subYear())->addMonthNoOverflow()->startOfMonth();
        $lastClosedMonth = now()->subMonthNoOverflow()->startOfMonth();
        $this->month = ($candidate->gt($lastClosedMonth) ? $lastClosedMonth : $candidate)->format('Y-m');

        $existingKpi = \DB::table('employee_kpi_scores')
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('reporting_month', $this->month . '-01')->first();
        $this->originalKpiRow = $existingKpi ? (array) $existingKpi : null;

        $existingReview = \DB::table('manager_performance_reviews')
            ->where('user_id', $this->userId)->where('review_month', $this->month . '-01')->first();
        $this->originalReviewRow = $existingReview ? (array) $existingReview : null;

        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', 'like', $this->month . '%')
            ->delete();

        \DB::table('manager_performance_reviews')
            ->where('user_id', $this->userId)->where('review_month', $this->month . '-01')->delete();
        if ($this->originalReviewRow !== null) {
            \DB::table('manager_performance_reviews')->insert($this->originalReviewRow);
        }

        \DB::table('employee_kpi_scores')
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('reporting_month', $this->month . '-01')->delete();
        if ($this->originalKpiRow !== null) {
            \DB::table('employee_kpi_scores')->insert($this->originalKpiRow);
        }

        parent::tearDown();
    }

    public function test_rollup_and_review_submission_paths_agree_on_the_same_score_and_grade(): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->userId,
            'performance_date' => $this->month . '-01',
            'day_type' => 'working', 'attendance_status' => 'present',
            'attendance_score' => 90.0, 'overall_daily_score' => 90.0,
            'calculation_status' => 'calculated', 'calculated_at' => now(),
        ]);
        EmployeeDailyPerformance::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->userId,
            'performance_date' => $this->month . '-02',
            'day_type' => 'working', 'attendance_status' => 'present',
            'attendance_score' => 70.0, 'overall_daily_score' => 70.0,
            'calculation_status' => 'calculated', 'calculated_at' => now(),
        ]);

        $rollup = app(PerformanceRollupService::class);
        $user = User::withoutGlobalScopes()->with('jobDetails')->find($this->userId);

        // Path A: the monthly rollup command, before any manager review exists.
        $kpiScore = $rollup->rollupMonth($user, $this->month);
        $objectiveOnly = (float) $kpiScore->overall_score;
        // No manager review yet -> 100% objective weight -> exactly the daily average.
        $this->assertSame(80.0, $objectiveOnly); // (90+70)/2
        $this->assertFalse((bool) $kpiScore->manager_rating_included);

        // Path B: a manager submits a review — mirrors
        // ManagerPerformanceReviewController::store()'s call.
        ManagerPerformanceReview::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'reviewer_id' => $this->userId,
            'kpi_score_id' => $kpiScore->id,
            'review_month' => $this->month . '-01',
            'overall_rating' => 5,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $rollup->recalculateMonthlyOverall($kpiScore->fresh());
        $afterReview = $kpiScore->fresh();

        $this->assertTrue((bool) $afterReview->manager_rating_included);
        $this->assertNotSame($objectiveOnly, (float) $afterReview->overall_score);

        // Path C: re-running the monthly rollup from scratch (e.g. a
        // --force re-run) must land on the EXACT same blended score as path
        // B's incremental recalculation — proving there is only one formula.
        $kpiScoreAgain = $rollup->rollupMonth($user->fresh(), $this->month);

        $this->assertSame((float) $afterReview->overall_score, (float) $kpiScoreAgain->overall_score);
        $this->assertSame($afterReview->grade, $kpiScoreAgain->grade);
    }
}
