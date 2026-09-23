<?php

namespace Tests\Feature\Performance;

use App\Models\EmployeeDailyPerformance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CalculateDailyPerformanceCommandTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private string $date;

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
        // Raw DB queries — deliberately not Eloquent — so these aren't
        // affected by the TenantTrait global scope once current_tenant is
        // bound below.
        $this->userId = (int) \DB::table('users')
            ->where('tenant_id', $this->tenantId)->where('status', '1')
            ->value('id');

        // The command refuses to score a date before the employee's
        // joining_date (by design — no synthetic pre-hire rows). These tests
        // only assert row-existence/idempotency behaviour, not specific
        // scores, so any real date after hire is fine to use.
        $joiningDate = \DB::table('user_job_details')->where('user_id', $this->userId)->value('joining_date');
        $candidate = \Carbon\Carbon::parse($joiningDate ?: '2020-01-01')->addDays(2);
        $yesterday = now()->subDay()->startOfDay();
        $this->date = $candidate->gt($yesterday) ? $yesterday->format('Y-m-d') : $candidate->format('Y-m-d');

        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->date)
            ->delete();

        parent::tearDown();
    }

    public function test_dry_run_computes_but_writes_nothing(): void
    {
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId, '--dry-run' => true]);

        $exists = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->date)
            ->exists();

        $this->assertFalse($exists);
    }

    public function test_creates_a_row_for_the_target_date_and_user(): void
    {
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId]);

        $row = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->date)
            ->first();

        $this->assertNotNull($row);
        $this->assertContains($row->calculation_status, ['calculated', 'excluded']);
    }

    public function test_second_run_without_force_skips_existing_row(): void
    {
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId]);
        $first = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->date)->first();

        // Re-run without --force: output should report it as skipped, and
        // the row's calculated_at must be unchanged.
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId]);
        $second = $first->fresh();

        $this->assertEquals($first->calculated_at, $second->calculated_at);
    }

    public function test_force_recalculates_an_existing_row(): void
    {
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId]);
        $first = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->userId)
            ->where('performance_date', $this->date)->first();

        sleep(1);
        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId, '--force' => true]);
        $second = $first->fresh();

        $this->assertTrue($second->calculated_at->gt($first->calculated_at));
    }

    public function test_does_not_touch_other_tenants_or_users(): void
    {
        $otherUserId = (int) \DB::table('users')->where('tenant_id', '!=', $this->tenantId)->value('id');
        $this->assertNotSame(0, $otherUserId, 'need at least one user on a different tenant for this test');

        Artisan::call('performance:calculate-daily', ['--date' => $this->date, '--user' => $this->userId]);

        $otherUserHasRow = EmployeeDailyPerformance::withoutGlobalScopes()
            ->where('user_id', $otherUserId)->where('performance_date', $this->date)
            ->exists();

        $this->assertFalse($otherUserHasRow);
    }
}
