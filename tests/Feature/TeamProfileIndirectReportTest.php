<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/user/team-profile/{id}: a manager opens a team member who is also
 * a manager (Sakshi), then one of HER reports (Pragya). Pragya reports to
 * Sakshi, not to the head directly — the head may still open her profile.
 * Someone outside the head's reporting chain stays blocked.
 */
class TeamProfileIndirectReportTest extends TestCase
{
    /** @var array<int, User> */
    private array $made = [];
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->value('tenant_id');
    }

    protected function tearDown(): void
    {
        $ids = array_map(fn ($u) => $u->id, $this->made);
        DB::table('user_reporting_heads')->whereIn('user_id', $ids)->delete();
        User::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        auth('api')->logout();
        parent::tearDown();
    }

    private function user(string $name, string $role, ?User $head = null): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit TP ' . $name, 'email' => 'tp.' . uniqid() . '@phpunit.test', 'employee_id' => 'TP' . random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => $role, 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        if ($head) {
            DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'reporting_head_id' => $head->id, 'is_primary' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->made[] = $u;
    }

    private function profile(User $as, User $target)
    {
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($as), 'Accept' => 'application/json'];
        $as->refresh();
        if ($as->last_login_token !== null) {
            $headers['Device-Token'] = $as->last_login_token;
        }

        return $this->withHeaders($headers)->getJson('/api/user/team-profile/' . $target->id)->assertOk();
    }

    public function test_manager_can_open_direct_and_indirect_reports_but_not_outsiders(): void
    {
        $head = $this->user('Head', 'manager');
        $sakshi = $this->user('Sakshi', 'manager', $head);
        $pragya = $this->user('Pragya', 'employee', $sakshi);
        $outsider = $this->user('Outsider', 'employee', $this->user('Other Manager', 'manager'));

        $this->profile($head, $sakshi)->assertJsonPath('success', true);
        $this->profile($head, $pragya)->assertJsonPath('success', true);
        $this->profile($sakshi, $pragya)->assertJsonPath('success', true);
        $this->profile($head, $outsider)->assertJsonPath('success', false);
        $this->profile($sakshi, $head)->assertJsonPath('success', false);   // not upward
    }

    public function test_web_member_detail_opens_an_indirect_report(): void
    {
        $head = $this->user('Head', 'manager');
        $sakshi = $this->user('Sakshi', 'manager', $head);
        $pragya = $this->user('Pragya', 'employee', $sakshi);
        $outsider = $this->user('Outsider', 'employee', $this->user('Other Manager', 'manager'));
        $this->actingAs($head)->withSession(['tenant_id' => $this->tenantId]);

        if (app(\App\Services\RbacService::class)->scopeFor($head, 'team', 'view') !== 'team') {
            $this->markTestSkipped('Managers of this dev tenant have no team:view = team permission.');
        }

        $this->get(route('team.member-detail', encrypt($sakshi->id)))->assertOk();
        $this->get(route('team.member-detail', encrypt($pragya->id)))->assertOk();          // was: redirect back to Sakshi
        $this->from(route('team.member-detail', encrypt($sakshi->id)))
            ->get(route('team.member-detail', encrypt($outsider->id)))->assertRedirect();   // still blocked
    }

    public function test_reports_up_to_handles_chains_and_loops(): void
    {
        $a = $this->user('A', 'manager');
        $b = $this->user('B', 'manager', $a);
        $c = $this->user('C', 'employee', $b);
        DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $a->id, 'reporting_head_id' => $c->id, 'is_primary' => 0, 'created_at' => now(), 'updated_at' => now()]); // loop

        $this->assertTrue(User::reportsUpTo($c->id, $a->id));
        $this->assertTrue(User::reportsUpTo($b->id, $a->id));
        $this->assertFalse(User::reportsUpTo($c->id, 999999999));
    }
}
