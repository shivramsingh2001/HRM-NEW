<?php

namespace Tests\Feature\Performance;

use App\Models\EmployeeDailyPerformance;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The Flutter mobile app's employee self-service Performance API
 * (routes/api.php, /user/performance/*) — Phase 3 of the Performance
 * redesign. Exercised via the real JWT ('api') guard, matching how the app
 * actually authenticates, not $this->actingAs()'s session-based default.
 */
class PerformanceApiTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private User $user;
    private string $token;
    private string $syntheticDate;
    private ?array $syntheticRow = null;

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

        $this->user = User::withoutGlobalScopes()->find($this->userId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));
        $this->token = auth('api')->login($this->user);

        // A synthetic day far in the past so it never collides with real data.
        $this->syntheticDate = '2019-05-06';
        $existing = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->syntheticDate)->first();
        $this->syntheticRow = $existing ? $existing->toArray() : null;

        EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->syntheticDate)->delete();

        EmployeeDailyPerformance::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'performance_date' => $this->syntheticDate,
            'day_type' => 'working',
            'attendance_status' => 'present',
            'attendance_score' => 85.5,
            'overall_daily_score' => 85.5,
            'calculation_status' => 'calculated',
            'calculated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->syntheticDate)->delete();

        if ($this->syntheticRow !== null) {
            EmployeeDailyPerformance::withoutGlobalScopes()->create($this->syntheticRow);
        }

        parent::tearDown();
    }

    /**
     * CheckSingleDeviceLogin compares the Device-Token header against
     * users.last_login_token — mirror whatever that user's real value
     * already is so this test doesn't depend on it happening to be null.
     */
    private function authHeaders(): array
    {
        $headers = ['Authorization' => "Bearer {$this->token}"];

        if ($this->user->last_login_token !== null) {
            $headers['Device-Token'] = $this->user->last_login_token;
        }

        return $headers;
    }

    public function test_summary_endpoint_returns_current_employees_own_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/user/performance/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data' => ['month', 'overall_score', 'grade', 'attendance_score']]);
        $this->assertTrue($response->json('status'));
    }

    public function test_history_endpoint_respects_limit_param(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/user/performance/history?limit=2');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(2, count($response->json('data')));
    }

    /**
     * Regression test for the UTC-serialization bug found during
     * verification: performance_date must round-trip as the exact same
     * Y-m-d string that was requested, not shift a day for an IST tenant.
     */
    public function test_daily_endpoint_returns_the_exact_requested_calendar_date(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/user/performance/daily?month=' . Carbon::parse($this->syntheticDate)->format('Y-m'));

        $response->assertStatus(200);
        $dates = collect($response->json('data'))->pluck('performance_date');
        $this->assertContains($this->syntheticDate, $dates);
    }

    public function test_daily_detail_endpoint_round_trips_the_exact_date(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/user/performance/daily-detail?date=' . $this->syntheticDate);

        $response->assertStatus(200);
        $this->assertTrue($response->json('status'));
        $this->assertSame($this->syntheticDate, $response->json('data.performance_date'));
        $this->assertSame(85.5, $response->json('data.overall_daily_score'));
    }

    public function test_weekly_endpoint_returns_an_array_of_week_buckets(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/user/performance/weekly?month=' . Carbon::parse($this->syntheticDate)->format('Y-m'));

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('week_start', $response->json('data.0'));
    }

    public function test_review_endpoint_returns_null_when_no_submitted_review_exists(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/user/performance/review?month=1999-01');

        $response->assertStatus(200);
        $this->assertNull($response->json('data'));
    }
}
