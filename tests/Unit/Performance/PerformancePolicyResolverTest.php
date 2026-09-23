<?php

namespace Tests\Unit\Performance;

use App\Models\PerformancePolicy;
use App\Services\Performance\PerformancePolicyResolver;
use Tests\TestCase;

/**
 * Uses the real DB (policy resolution needs the performance_policies table)
 * but only ever touches a synthetic tenant_id that cannot collide with real
 * tenants, and cleans up everything it creates.
 */
class PerformancePolicyResolverTest extends TestCase
{
    private const FAKE_TENANT_ID = 999999;

    protected function tearDown(): void
    {
        PerformancePolicy::withoutGlobalScopes()->where('tenant_id', self::FAKE_TENANT_ID)->forceDelete();
        parent::tearDown();
    }

    public function test_falls_back_to_global_default_when_tenant_has_no_row(): void
    {
        $resolver = new PerformancePolicyResolver();
        $policy = $resolver->forTenantDate(self::FAKE_TENANT_ID, '2026-01-01');

        $this->assertSame(30.00, $policy->weightAttendance);
        $this->assertNull($policy->tenantId); // resolved the global default row (tenant_id IS NULL), not a per-tenant one
    }

    public function test_tenant_specific_row_beats_global_default(): void
    {
        PerformancePolicy::withoutGlobalScopes()->create([
            'tenant_id' => self::FAKE_TENANT_ID,
            'effective_from' => '2000-01-01',
            'weight_attendance' => 50.00,
            'weight_task_completion' => 15.00,
            'weight_task_ontime' => 10.00,
            'weight_project_participation' => 10.00,
            'weight_regularization' => 5.00,
            'weight_manager_rating' => 10.00,
        ]);

        $resolver = new PerformancePolicyResolver();
        $policy = $resolver->forTenantDate(self::FAKE_TENANT_ID, '2026-01-01');

        $this->assertSame(50.00, $policy->weightAttendance);
    }

    public function test_versioning_picks_the_newest_row_not_in_the_future(): void
    {
        PerformancePolicy::withoutGlobalScopes()->create([
            'tenant_id' => self::FAKE_TENANT_ID,
            'effective_from' => '2000-01-01',
            'weight_attendance' => 40.00,
        ]);
        PerformancePolicy::withoutGlobalScopes()->create([
            'tenant_id' => self::FAKE_TENANT_ID,
            'effective_from' => '2026-06-01',
            'weight_attendance' => 60.00,
        ]);

        $resolver = new PerformancePolicyResolver();

        // Before the new version takes effect -> the older row.
        $this->assertSame(40.00, $resolver->forTenantDate(self::FAKE_TENANT_ID, '2026-05-01')->weightAttendance);
        // On/after the new version's effective_from -> the newer row.
        $this->assertSame(60.00, $resolver->forTenantDate(self::FAKE_TENANT_ID, '2026-06-01')->weightAttendance);
    }

    public function test_safe_with_no_bound_current_tenant(): void
    {
        // Simulates console-context safety: tenant is passed explicitly, no
        // app('current_tenant') binding exists in this test at all.
        $this->assertFalse(app()->bound('current_tenant'));

        $resolver = new PerformancePolicyResolver();
        $policy = $resolver->forTenantDate(self::FAKE_TENANT_ID, '2026-01-01');

        $this->assertInstanceOf(\App\Services\Performance\PerformancePolicySnapshot::class, $policy);
    }
}
