<?php

namespace Tests\Feature;

use App\Models\MaintenanceMode;
use App\Models\User;
use App\Services\MaintenanceModeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Platform maintenance mode (row managed by the Super Admin Panel).
 * GET /api/maintenance has a frozen response shape — older mobile app
 * versions read it. Dev DB, rolled back.
 */
class MaintenanceModeTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        DB::beginTransaction();
        app(MaintenanceModeService::class)->forget();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        app(MaintenanceModeService::class)->forget();
        parent::tearDown();
    }

    private function set(array $attrs): MaintenanceMode
    {
        $m = app(MaintenanceModeService::class)->row();
        $m->update($attrs + ['allowed_ips' => [], 'allowed_users' => []]);
        app(MaintenanceModeService::class)->forget();

        return $m;
    }

    public function test_public_api_keeps_the_frozen_response_shape(): void
    {
        $this->set(['is_enabled' => false, 'start_time' => null, 'end_time' => null]);

        $res = $this->getJson('/api/maintenance')->assertOk();
        $this->assertSame(['success', 'data', 'is_active'], array_keys($res->json()));
        $this->assertSame(
            ['id', 'is_enabled', 'title', 'message', 'start_time', 'end_time', 'allowed_ips', 'allowed_users', 'enabled_by', 'created_at', 'updated_at'],
            array_keys($res->json('data'))
        );
        $this->assertTrue($res->json('success'));
        $this->assertFalse($res->json('data.is_enabled'));
        $this->assertFalse($res->json('is_active'));
        $this->assertSame([], $res->json('data.allowed_ips'));
        $this->assertSame([], $res->json('data.allowed_users'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.000000Z$/', $res->json('data.created_at'));
    }

    public function test_active_maintenance_blocks_api_and_web_but_not_the_status_api(): void
    {
        $this->set(['is_enabled' => true, 'title' => 'Upgrade', 'message' => 'Back soon', 'start_time' => null, 'end_time' => now()->addHour()]);

        $this->getJson('/api/maintenance')->assertOk()->assertJson(['is_active' => true, 'data' => ['is_enabled' => true]]);

        $this->getJson('/api/user/profile')->assertStatus(503)
            ->assertJson(['success' => false, 'maintenance' => true, 'title' => 'Upgrade', 'message' => 'Back soon'])
            ->assertHeader('Retry-After');

        $this->actingAs($this->admin)->get('/dashboard')->assertStatus(503)->assertSee('Back soon');

        // Login page stays reachable so allowed users can sign in.
        $this->get('/')->assertStatus(200);
    }

    public function test_allowed_users_and_ips_bypass(): void
    {
        $this->set(['is_enabled' => true, 'allowed_users' => [$this->admin->id]]);
        $this->assertNotSame(503, $this->actingAs($this->admin)->get('/dashboard')->status());

        $this->set(['is_enabled' => true, 'allowed_ips' => ['10.9.0.0/16']]);
        $this->assertNotSame(503, $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '10.9.4.2'])->get('/dashboard')->status());
        $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '10.8.4.2'])->get('/dashboard')->assertStatus(503);
    }

    public function test_schedule_window(): void
    {
        $this->set(['is_enabled' => true, 'start_time' => now()->addHour(), 'end_time' => null]);
        $this->getJson('/api/maintenance')->assertJson(['is_active' => false]);
        $this->assertNotSame(503, $this->actingAs($this->admin)->get('/dashboard')->status());

        $this->set(['is_enabled' => true, 'start_time' => now()->subHours(2), 'end_time' => now()->subHour()]);
        $this->getJson('/api/maintenance')->assertJson(['is_active' => false]);
    }
}
