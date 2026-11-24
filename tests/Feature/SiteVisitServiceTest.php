<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Site\SiteException;
use App\Services\Site\SiteService;
use App\Services\Site\SiteVisitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Site visits phase 4 (2026-11-02). Runs against the shared dev DB (no
 * RefreshDatabase) and removes every row it creates in tearDown.
 */
class SiteVisitServiceTest extends TestCase
{
    private ?Site $site = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('site_visits')) {
            $this->markTestSkipped('site_visits table not migrated on this DB.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->site) {
            SiteVisit::withoutGlobalScope('tenant')->where('site_id', $this->site->id)->delete();
            Site::withoutGlobalScope('tenant')->withTrashed()->where('id', $this->site->id)->forceDelete();
        }
        parent::tearDown();
    }

    /** @return array{0:User,1:User} two users of one company */
    private function users(): array
    {
        $tenantId = User::whereNotNull('tenant_id')->select('tenant_id')->groupBy('tenant_id')
            ->havingRaw('count(*) >= 2')->value('tenant_id');
        $users = User::where('tenant_id', $tenantId)->orderBy('id')->limit(2)->get();

        $this->site = app(SiteService::class)->create((int) $tenantId, [
            'site_name' => 'Visit Test '.uniqid(), 'state_code' => (int) DB::table('states')->value('state_code'), 'address' => 'X',
        ], $users[0]);

        return [$users[0], $users[1]];
    }

    private function visit(User $user, array $extra = []): SiteVisit
    {
        return app(SiteVisitService::class)->create($user, $extra + ['site_id' => $this->site->id, 'remarks' => 'Met the supervisor']);
    }

    public function test_visit_date_is_always_today(): void
    {
        [$user] = $this->users();
        $visit = $this->visit($user, ['visit_date' => '2020-01-01']);

        $this->assertSame(now()->toDateString(), $visit->visit_date->toDateString());
        $this->assertSame((int) $user->id, (int) $visit->user_id);
    }

    public function test_inactive_site_cannot_be_chosen(): void
    {
        [$user] = $this->users();
        app(SiteService::class)->setStatus($this->site, 'inactive', $user);

        $this->expectException(SiteException::class);
        $this->visit($user);
    }

    public function test_only_the_visitor_can_edit_and_site_stays(): void
    {
        [$owner, $other] = $this->users();
        $visit = $this->visit($owner);

        $updated = app(SiteVisitService::class)->update($visit, $owner, ['remarks' => 'Changed', 'site_id' => 999999]);
        $this->assertSame('Changed', $updated->remarks);
        $this->assertSame($this->site->id, (int) $updated->site_id);
        $this->assertNotNull($updated->edited_at);

        $this->expectException(SiteException::class);
        app(SiteVisitService::class)->update($visit, $other, ['remarks' => 'Not mine']);
    }

    public function test_yesterdays_visit_is_locked(): void
    {
        [$owner] = $this->users();
        $visit = $this->visit($owner);
        $visit->forceFill(['visit_date' => now()->subDay()->toDateString()])->save();

        $this->expectException(SiteException::class);
        app(SiteVisitService::class)->update($visit->fresh(), $owner, ['remarks' => 'Too late']);
    }
    public function test_stock_is_saved_shown_and_editable(): void
    {
        [$owner] = $this->users();
        $visit = $this->visit($owner, ['current_stock' => '120.50', 'date_of_stock_received' => now()->subDays(3)->toDateString()]);

        $api = app(SiteVisitService::class)->toApi($visit->fresh());
        $this->assertSame(120.5, $api['current_stock']);
        $this->assertSame(now()->subDays(3)->toDateString(), $api['date_of_stock_received']);
        $this->assertSame('120.5', $visit->fresh()->stockText());

        // Edit without the stock fields keeps them; sending null clears them.
        $kept = app(SiteVisitService::class)->update($visit->fresh(), $owner, ['remarks' => 'Recount later']);
        $this->assertSame('120.50', (string) $kept->current_stock);
        $cleared = app(SiteVisitService::class)->update($kept, $owner, ['remarks' => 'x', 'current_stock' => null, 'date_of_stock_received' => null]);
        $this->assertNull($cleared->current_stock);
        $this->assertSame('—', $cleared->stockText());
    }

    public function test_stock_rules(): void
    {
        $v = fn (array $in) => validator($in + ['site_id' => 1, 'remarks' => 'x'], \App\Http\Requests\Site\SiteVisitRules::rules(false),
            \App\Http\Requests\Site\SiteVisitRules::messages(), \App\Http\Requests\Site\SiteVisitRules::attributes());

        $this->assertTrue($v([])->passes());                                            // both optional
        $this->assertTrue($v(['current_stock' => 0, 'date_of_stock_received' => now()->toDateString()])->passes());
        $this->assertTrue($v(['current_stock' => -1])->fails());
        $this->assertTrue($v(['current_stock' => 'ten'])->fails());
        $this->assertTrue($v(['date_of_stock_received' => now()->addDay()->toDateString()])->fails());
        $this->assertTrue($v(['date_of_stock_received' => '24-11-2026'])->fails());
    }
}
