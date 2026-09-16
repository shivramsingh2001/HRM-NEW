<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\FieldTracking\FieldTrackingService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Field GPS tracking — hard seat cap, seat assign/remove, and the
 * location_tracking block the mobile API hands the app.
 *
 * Runs against the shared dev DB (no RefreshDatabase); each test restores the
 * pilot tenant + job-detail rows it touched.
 */
class FieldTrackingTest extends TestCase
{
    private int $tenantId;
    private array $userIds;
    private array $originalTenant = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->value('tenant_id');
        $this->userIds = User::where('tenant_id', $this->tenantId)
            ->where('status', 1)->where('role', '!=', 'admin')
            ->limit(3)->pluck('id')->all();

        $t = Tenant::find($this->tenantId);
        $this->originalTenant = $t->only(['field_tracking_enabled', 'field_tracking_seats', 'field_tracking_ping_seconds']);
        $t->update(['field_tracking_enabled' => true, 'field_tracking_seats' => 2, 'field_tracking_ping_seconds' => 120]);

        UserJobDetail::whereIn('user_id', $this->userIds)->update(['location_tracking_enabled' => 0]);
    }

    protected function tearDown(): void
    {
        UserJobDetail::whereIn('user_id', $this->userIds)->update(['location_tracking_enabled' => 0]);
        Tenant::where('id', $this->tenantId)->update($this->originalTenant);
        DB::table('webhook_deliveries')->where('event', 'like', 'field_tracking%')->delete();

        parent::tearDown();
    }

    public function test_hard_seat_cap_blocks_the_third_enable(): void
    {
        $svc = app(FieldTrackingService::class);

        $this->assertTrue($svc->canEnable($this->tenantId)['ok']);
        $svc->assign(User::find($this->userIds[0]));
        $svc->assign(User::find($this->userIds[1]));

        $gate = $svc->canEnable($this->tenantId);
        $this->assertFalse($gate['ok']);
        $this->assertStringContainsString('seats are in use', $gate['reason']);
        $this->assertSame(2, $svc->seatsUsed($this->tenantId));
    }

    public function test_assign_and_remove_flip_the_flag_and_count(): void
    {
        $svc = app(FieldTrackingService::class);
        $user = User::find($this->userIds[0]);

        $svc->assign($user);
        $this->assertEquals(1, UserJobDetail::where('user_id', $user->id)->value('location_tracking_enabled'));
        $this->assertSame(1, $svc->seatsUsed($this->tenantId));

        $svc->remove($user);
        $this->assertEquals(0, UserJobDetail::where('user_id', $user->id)->value('location_tracking_enabled'));
        $this->assertSame(0, $svc->seatsUsed($this->tenantId));
    }

    public function test_resolve_for_user_reflects_enablement_and_cadence(): void
    {
        $svc = app(FieldTrackingService::class);

        $on = User::find($this->userIds[0]);
        $off = User::find($this->userIds[1]);
        $svc->assign($on);

        $a = $svc->resolveForUser($on->id);
        $this->assertTrue($a['enabled']);
        $this->assertSame(120, $a['ping_seconds']);
        $this->assertSame('single', $a['mode']);

        $b = $svc->resolveForUser($off->id);
        $this->assertFalse($b['enabled']);
    }

    public function test_ping_seconds_is_clamped_to_the_config_floor(): void
    {
        Tenant::where('id', $this->tenantId)->update(['field_tracking_ping_seconds' => 5]);
        $svc = app(FieldTrackingService::class);

        $this->assertSame(
            (int) config('location.min_ping_seconds', 30),
            $svc->pingSeconds(Tenant::find($this->tenantId))
        );
    }

    public function test_toggle_endpoint_respects_the_cap(): void
    {
        $admin = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first()
            ?? User::where('tenant_id', $this->tenantId)->where('role', 'hr')->first();
        if (! $admin) {
            $this->markTestSkipped('no admin/hr user in tenant');
        }

        $svc = app(FieldTrackingService::class);
        $svc->assign(User::find($this->userIds[0]));
        $svc->assign(User::find($this->userIds[1])); // seats full (2/2)

        $res = $this->actingAs($admin)->postJson(route('employee.toggle-location-tracking'), [
            'id' => $this->userIds[2],
        ]);

        $res->assertOk()->assertJsonPath('success', false);
        $this->assertStringContainsString('seats are in use', $res->json('message'));
    }
}
