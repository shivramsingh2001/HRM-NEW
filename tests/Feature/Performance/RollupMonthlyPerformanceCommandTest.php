<?php

namespace Tests\Feature\Performance;

use App\Models\EmployeeDailyPerformance;
use App\Models\EmployeeKpiScore;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Performance\PerformanceRollupService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * rollupMonth() clamps its date range to [max(month start, employee's
 * joining_date), min(month end, today)] — so the target month must be a
 * real, closed month within the picked employee's employment, not an
 * arbitrary fixed date far in the past (which would produce an inverted/empty
 * range for a recently-hired real employee). The month is chosen as
 * "the month after they joined" and any pre-existing employee_kpi_scores row
 * for it is snapshotted and restored in tearDown(), exactly like a genuine
 * nightly rollup would only ever add/replace that one row.
 */
class RollupMonthlyPerformanceCommandTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private string $month;
    private ?array $originalKpiRow = null;

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

        $existing = \DB::table('employee_kpi_scores')
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('reporting_month', $this->month . '-01')
            ->first();
        $this->originalKpiRow = $existing ? (array) $existing : null;

        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', 'like', $this->month . '%')
            ->delete();

        \DB::table('employee_kpi_scores')
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('reporting_month', $this->month . '-01')
            ->delete();
        if ($this->originalKpiRow !== null) {
            \DB::table('employee_kpi_scores')->insert($this->originalKpiRow);
        }

        parent::tearDown();
    }

    private function seedDailyRow(int $day, ?float $score, string $status = 'calculated'): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'performance_date' => sprintf('%s-%02d', $this->month, $day),
            'day_type' => 'working',
            'attendance_status' => 'present',
            'attendance_score' => $score,
            'overall_daily_score' => $score,
            'calculation_status' => $status,
            'calculated_at' => now(),
        ]);
    }

    public function test_rollup_averages_calculated_days_and_ignores_excluded_ones(): void
    {
        $this->seedDailyRow(1, 80.0);
        $this->seedDailyRow(2, 90.0);
        $this->seedDailyRow(3, null, 'excluded'); // holiday — must not drag the average down

        $user = User::withoutGlobalScopes()->with('jobDetails')->find($this->userId);
        $kpiScore = app(PerformanceRollupService::class)->rollupMonth($user, $this->month);

        $this->assertSame(85.0, (float) $kpiScore->overall_score); // (80+90)/2, excluded day ignored
        $this->assertSame(2, $kpiScore->days_calculated);
        $this->assertNotNull($kpiScore->grade);
    }

    public function test_rollup_with_zero_calculated_days_yields_a_null_score_not_zero(): void
    {
        $this->seedDailyRow(1, null, 'excluded');

        $user = User::withoutGlobalScopes()->with('jobDetails')->find($this->userId);
        $kpiScore = app(PerformanceRollupService::class)->rollupMonth($user, $this->month);

        $this->assertNull($kpiScore->overall_score);
        $this->assertSame(0, $kpiScore->days_calculated);
    }

    /**
     * --notify is opt-in (only the scheduled cron entry passes it) so manual
     * backfills over historical months don't spam employees — see
     * routes/console.php's `performance:rollup-monthly --notify` schedule
     * entry vs. this command's other, unflagged manual-use invocations.
     */
    public function test_notify_flag_sends_a_database_notification_to_each_rolled_up_employee(): void
    {
        $this->seedDailyRow(1, 80.0);

        \DB::table('notifications')
            ->where('notifiable_id', $this->userId)->where('notifiable_type', User::class)
            ->where('type', 'App\\Notifications\\PerformanceNotification')
            ->delete();

        $this->artisan('performance:rollup-monthly', [
            '--month' => $this->month,
            '--user' => $this->userId,
            '--force' => true,
            '--notify' => true,
        ])->assertExitCode(0);

        $notified = \DB::table('notifications')
            ->where('notifiable_id', $this->userId)->where('notifiable_type', User::class)
            ->where('type', 'App\\Notifications\\PerformanceNotification')
            ->exists();

        $this->assertTrue($notified);

        \DB::table('notifications')
            ->where('notifiable_id', $this->userId)->where('notifiable_type', User::class)
            ->where('type', 'App\\Notifications\\PerformanceNotification')
            ->delete();
    }
}
