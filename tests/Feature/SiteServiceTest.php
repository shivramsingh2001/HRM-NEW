<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Site\SiteException;
use App\Services\Site\SiteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sites phase 1 (2026-11-02). Runs against the shared dev DB (no
 * RefreshDatabase) and removes every row it creates in tearDown.
 */
class SiteServiceTest extends TestCase
{
    /** @var int[] */
    private array $siteIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('sites')) {
            $this->markTestSkipped('sites table not migrated on this DB.');
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

    private function admin(): User
    {
        return User::whereNotNull('tenant_id')->where('role', 'admin')->firstOrFail();
    }

    private function make(User $admin, array $extra = []): Site
    {
        $site = app(SiteService::class)->create((int) $admin->tenant_id, $extra + [
            'site_name' => 'Test Site ' . uniqid(),
            'state_code' => (string) DB::table('states')->value('state_code'),
            'address' => '1 Test Road',
        ], $admin);
        $this->siteIds[] = $site->id;

        return $site;
    }

    public function test_format_code_pads_the_running_number(): void
    {
        $this->assertSame('SITE-0007', SiteService::formatCode(7));
        $this->assertSame('SITE-12345', SiteService::formatCode(12345));
    }

    public function test_create_hands_out_consecutive_codes_per_company(): void
    {
        $admin = $this->admin();
        $first = $this->make($admin);
        $second = $this->make($admin);

        $this->assertSame($first->site_no + 1, $second->site_no);
        $this->assertSame(SiteService::formatCode($second->site_no), $second->site_code);
        $this->assertSame('active', $second->status);
    }

    public function test_update_never_changes_the_code(): void
    {
        $admin = $this->admin();
        $site = $this->make($admin);
        $code = $site->site_code;

        app(SiteService::class)->update($site, ['site_code' => 'HACKED', 'site_no' => 999999, 'district' => 'Noida'], $admin);

        $site->refresh();
        $this->assertSame($code, $site->site_code);
        $this->assertSame('Noida', $site->district);
    }

    public function test_deleted_site_number_is_not_reused(): void
    {
        $admin = $this->admin();
        $site = $this->make($admin);
        app(SiteService::class)->delete($site, $admin);

        $next = $this->make($admin);
        $this->assertSame($site->site_no + 1, $next->site_no);
    }

    public function test_list_filters_by_search_and_status(): void
    {
        $admin = $this->admin();
        $tag = 'Filter'.uniqid();
        $active = $this->make($admin, ['site_name' => "{$tag} A"]);
        $inactive = $this->make($admin, ['site_name' => "{$tag} B", 'status' => 'inactive']);
        $tenantId = (int) $admin->tenant_id;

        $all = app(SiteService::class)->paginate($tenantId, ['search' => $tag])->pluck('id')->all();
        $onlyInactive = app(SiteService::class)->paginate($tenantId, ['search' => $tag, 'status' => 'inactive'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$active->id, $inactive->id], $all);
        $this->assertSame([$inactive->id], $onlyInactive);
    }

    public function test_site_with_visits_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $site = $this->make($admin);
        SiteVisit::create([
            'tenant_id' => $admin->tenant_id, 'user_id' => $admin->id, 'site_id' => $site->id,
            'visit_date' => now()->toDateString(), 'visited_at' => now(), 'remarks' => 'test',
        ]);

        $this->expectException(SiteException::class);
        app(SiteService::class)->delete($site, $admin);
    }
}
