<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SelfApproval;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nobody approves their own request (LV-000652: a manager approved her own
 * leave through GET /api/update-team-leave-status/{id}). Her reporting head
 * still can; an admin may decide on their own.
 */
class SelfApprovalTest extends TestCase
{
    /** @var array<int, User> */
    private array $made = [];
    private array $leaveIds = [];
    private array $otIds = [];
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->value('tenant_id');
    }

    protected function tearDown(): void
    {
        $ids = array_map(fn ($u) => $u->id, $this->made);
        DB::table('leave_transactions')->whereIn('leave_id', $this->leaveIds)->delete();
        DB::table('leaves')->whereIn('id', $this->leaveIds)->delete();
        DB::table('overtime_requests')->whereIn('id', $this->otIds)->delete();
        DB::table('user_reporting_heads')->whereIn('user_id', $ids)->delete();
        DB::table('user_job_details')->whereIn('user_id', $ids)->delete();
        User::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        auth('api')->logout();
        parent::tearDown();
    }

    private function user(string $role, ?User $head = null): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit SA ' . $role, 'email' => 'sa.' . uniqid() . '@phpunit.test', 'employee_id' => 'SA' . random_int(100000, 999999),
            'password' => bcrypt('secret'), 'role' => $role, 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        DB::table('user_job_details')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'reporting_head' => $head?->id, 'created_at' => now(), 'updated_at' => now()]);
        if ($head) {
            DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'reporting_head_id' => $head->id, 'is_primary' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->made[] = $u;
    }

    private function pendingLeave(User $owner): int
    {
        $id = DB::table('leaves')->insertGetId([
            'tenant_id' => $this->tenantId, 'leave_id' => 'LV-T' . random_int(10000, 99999), 'user_id' => $owner->id,
            'leave_type' => (int) DB::table('leave_types')->where('tenant_id', $this->tenantId)->value('id'),
            'start_date' => '2026-12-01', 'start_session' => 'fullday', 'end_date' => '2026-12-01', 'end_session' => 'fullday',
            'leave_count' => 1, 'reason' => 'phpunit', 'status' => 'pending', 'source' => 'self', 'deduct_balance' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->leaveIds[] = $id;
    }

    private function api(User $as)
    {
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($as), 'Accept' => 'application/json'];
        $as->refresh();
        if ($as->last_login_token !== null) {
            $headers['Device-Token'] = $as->last_login_token;
        }

        return $this->withHeaders($headers);
    }

    public function test_manager_cannot_approve_own_leave_but_their_head_is_not_blocked(): void
    {
        $head = $this->user('manager');
        $manager = $this->user('manager', $head);
        $leave = $this->pendingLeave($manager);

        $this->api($manager)->getJson("/api/update-team-leave-status/{$leave}?status=approved")
            ->assertStatus(403)->assertJsonPath('message', SelfApproval::MESSAGE);
        $this->assertSame('pending', DB::table('leaves')->where('id', $leave)->value('status'));

        $r = $this->api($head)->getJson("/api/update-team-leave-status/{$leave}?status=approved");
        $this->assertNotSame(SelfApproval::MESSAGE, $r->json('message'));
    }

    public function test_manager_cannot_approve_own_overtime_by_api(): void
    {
        $manager = $this->user('manager', $this->user('manager'));
        $ot = DB::table('overtime_requests')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $manager->id, 'date' => '2026-12-01', 'overtime_hours' => 1,
            'reason' => 'phpunit', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->otIds[] = $ot;

        $this->api($manager)->postJson("/api/overtime/approve/{$ot}")
            ->assertStatus(403)->assertJsonPath('message', SelfApproval::MESSAGE);
        $this->assertSame('pending', DB::table('overtime_requests')->where('id', $ot)->value('status'));
    }

    public function test_rule(): void
    {
        $manager = $this->user('manager');
        $admin = $this->user('admin');

        $this->assertTrue(SelfApproval::blocks($manager, $manager->id));
        $this->assertFalse(SelfApproval::blocks($manager, $admin->id));
        $this->assertFalse(SelfApproval::blocks($admin, $admin->id)); // company owner, nobody above
        $this->assertFalse(SelfApproval::blocks($manager, null));
    }
}
