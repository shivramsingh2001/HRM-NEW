<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Site\SiteException;
use App\Services\Site\SiteService;
use App\Services\Site\SiteVisitReportService;
use App\Services\Site\SiteVisitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Site visit reports phase 5 (2026-11-02). Runs against the shared dev DB
 * (no RefreshDatabase) and removes every row it creates in tearDown.
 */
class SiteVisitReportTest extends TestCase
{
    /** @var int[] */
    private array $siteIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('site_visits')) {
            $this->markTestSkipped('site_visits table not migrated on this DB.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->siteIds) {
            SiteVisit::withoutGlobalScope('tenant')->whereIn('site_id', $this->siteIds)->delete();
            Site::withoutGlobalScope('tenant')->withTrashed()->whereIn('id', $this->siteIds)->forceDelete();
        }
        parent::tearDown();
    }

    private function site(User $admin): Site
    {
        $site = app(SiteService::class)->create((int) $admin->tenant_id, [
            'site_name' => 'Report Test '.uniqid(), 'state_code' => (int) DB::table('states')->value('state_code'), 'address' => 'X',
        ], $admin);
        $this->siteIds[] = $site->id;

        return $site;
    }

    private function visit(User $user, Site $site): SiteVisit
    {
        return app(SiteVisitService::class)->create($user, ['site_id' => $site->id, 'remarks' => 'Report test']);
    }

    public function test_monthly_summary_counts_visits_sites_and_days(): void
    {
        $admin = User::whereNotNull('tenant_id')->where('role', 'admin')->firstOrFail();
        [$a, $b] = [$this->site($admin), $this->site($admin)];
        $this->visit($admin, $a);
        $this->visit($admin, $a);
        $this->visit($admin, $b);

        $row = app(SiteVisitReportService::class)->monthlySummary($admin, now()->format('Y-m'), (int) $admin->id)
            ->firstWhere('user_id', (int) $admin->id);

        // Other visits by the same admin this month may exist on the dev DB, so compare at least.
        $this->assertGreaterThanOrEqual(3, $row['visits']);
        $this->assertGreaterThanOrEqual(2, $row['sites']);
        $this->assertGreaterThanOrEqual(1, $row['days']);
    }

    public function test_manager_only_sees_own_team(): void
    {
        $manager = User::whereNotNull('tenant_id')->where('role', 'manager')->first();
        if (! $manager) {
            $this->markTestSkipped('No manager on this DB.');
        }
        $outsider = User::where('tenant_id', $manager->tenant_id)->where('id', '!=', $manager->id)
            ->whereNotIn('id', User::managedBy($manager->id)->pluck('id'))->first();
        if (! $outsider) {
            $this->markTestSkipped('No employee outside the manager\'s team.');
        }

        $ids = app(SiteVisitReportService::class)->scopedUserIds($manager);
        $this->assertContains((int) $manager->id, $ids);
        $this->assertNotContains((int) $outsider->id, $ids);

        $this->expectException(SiteException::class);
        app(SiteVisitReportService::class)->employeeMonth($manager, (int) $outsider->id, now()->format('Y-m'));
    }
}
