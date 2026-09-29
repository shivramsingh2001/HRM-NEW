<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use App\Services\Biometric\BiometricRosterService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Roster auto-provisioning: HRM drives the terminal's user list (keyed by
 * users.id), the bridge pulls GET /roster and reports POST /roster/ack, and
 * punches self-map with no admin action.
 */
class BiometricRosterTest extends TestCase
{
    private int $tenantId;
    private User $emp;
    private BiometricDevice $device;
    private string $secret;
    private $multiPunchFlag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');

        // These assertions cover the single-punch path; don't depend on the dev tenant's setting.
        $this->multiPunchFlag = DB::table('tenants')->where('id', $this->tenantId)->value('allow_multiple_punches');
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => 0]);

        // tenant_id is guarded on User; forceCreate so it persists.
        $this->emp = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Roster Temp',
            'email' => 'roster.' . uniqid() . '@phpunit.test',
            'employee_id' => 'RT' . random_int(100000, 999999),
            'password' => bcrypt('x'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);

        $issued = ApiClient::issue($this->tenantId, 'phpunit roster ' . uniqid(),
            ['biometric:write', 'biometric:read']);
        $this->secret = $issued['secret'];

        $this->device = BiometricDevice::create([
            'tenant_id' => $this->tenantId,
            'serial_number' => 'PHPUNIT-ROS-' . uniqid(),
            'name' => 'phpunit roster gate',
            'direction_mode' => 'auto',
            'is_active' => true,
            'auto_provision' => true,
            'provision_scope' => 'tenant',
            'default_privilege' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        BiometricPunch::where('biometric_device_id', $this->device->id)->delete();
        BiometricEnrollment::where('biometric_device_id', $this->device->id)->delete();
        BiometricEnrollment::where('user_id', $this->emp->id)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $this->emp->id)->delete();
        DB::table('attendance_punches')->where('user_id', $this->emp->id)->delete();
        DB::table('attendance_logs')->where('user_id', $this->emp->id)->delete();
        $this->device->delete();
        User::withoutGlobalScopes()->where('id', $this->emp->id)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => $this->multiPunchFlag]);
        parent::tearDown();
    }

    private function row(): ?BiometricEnrollment
    {
        return BiometricEnrollment::where('biometric_device_id', $this->device->id)
            ->where('user_id', $this->emp->id)->first();
    }

    public function test_rebuild_flags_active_employee_as_pending(): void
    {
        app(BiometricRosterService::class)->rebuild($this->device);

        $row = $this->row();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->sync_state);
        $this->assertSame('auto', $row->source);
        $this->assertSame((string) $this->emp->id, $row->enroll_no);
        $this->assertSame($this->emp->id, (int) $row->device_user_id);
        $this->assertNull($row->name_pushed);
    }

    public function test_deactivated_employee_is_flagged_removing(): void
    {
        $svc = app(BiometricRosterService::class);
        $svc->rebuild($this->device);

        $this->emp->update(['status' => 0]);
        $svc->rebuild($this->device);

        $this->assertSame('removing', $this->row()->sync_state);
    }

    public function test_rename_reflags_pending_with_new_name(): void
    {
        $svc = app(BiometricRosterService::class);
        $svc->rebuild($this->device);

        // pretend the bridge synced it
        $this->row()->update(['sync_state' => 'synced', 'name_pushed' => $svc->nameFor($this->emp->fresh())]);

        $this->emp->update(['name' => 'Renamed Person']);
        $svc->rebuild($this->device);

        $row = $this->row();
        $this->assertSame('pending', $row->sync_state);
        $this->assertStringContainsString('Renamed Person', $svc->nameFor($this->emp->fresh()));
    }

    public function test_bridge_roster_feed_and_ack(): void
    {
        app(BiometricRosterService::class)->rebuild($this->device);

        $feed = $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->getJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster")
            ->assertOk()->json('data');

        $ids = array_column($feed['upserts'], 'id');
        $this->assertContains($this->emp->id, $ids);

        $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->postJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster/ack", [
                'applied' => [['id' => $this->emp->id, 'action' => 'upsert', 'ok' => true]],
            ])->assertOk()->assertJsonPath('data.synced', 1);

        $row = $this->row();
        $this->assertSame('synced', $row->sync_state);
        $this->assertNotNull($row->name_pushed);
        $this->assertNotNull($row->synced_at);
    }

    public function test_ack_delete_removes_the_row(): void
    {
        app(BiometricRosterService::class)->rebuild($this->device);
        $this->emp->update(['status' => 0]);
        app(BiometricRosterService::class)->rebuild($this->device);

        $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->postJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster/ack", [
                'applied' => [['id' => $this->emp->id, 'action' => 'delete', 'ok' => true]],
            ])->assertOk()->assertJsonPath('data.removed', 1);

        $this->assertNull($this->row());
    }

    public function test_punch_with_unmapped_user_id_self_maps_and_processes(): void
    {
        $date = now()->subDay()->toDateString();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->emp->id)->where('date', $date)->delete();

        // no enrollment row exists yet — enroll_no == users.id
        $this->assertNull($this->row());

        $res = $this->withHeaders([
            'Authorization' => "Bearer {$this->secret}",
            'Idempotency-Key' => 'ROS-PUNCH-1',
        ])->postJson('/api/v1/biometric/punches', [
            'device_serial' => $this->device->serial_number,
            'punches' => [[
                'enroll_no' => (string) $this->emp->id,
                'punched_at' => $date . 'T09:05:00+05:30',
                'raw_verify_mode' => 1,
                'direction' => 'in',
            ]],
        ]);

        $res->assertOk()->assertJsonPath('data.accepted', 1)->assertJsonPath('data.skipped_unmapped', 0);

        $row = $this->row();
        $this->assertNotNull($row);
        $this->assertSame('auto', $row->source);

        $punch = BiometricPunch::where('biometric_device_id', $this->device->id)->latest('id')->first();
        $this->assertSame('processed', $punch->status);
        $att = Attendance::withoutGlobalScopes()->find($punch->attendance_id);
        $this->assertNotNull($att->clock_in);

        DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
    }

    private function sendPunch(string $method, string $idem): array
    {
        $date = now()->subDay()->toDateString();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->emp->id)->where('date', $date)->delete();

        $this->withHeaders(['Authorization' => "Bearer {$this->secret}", 'Idempotency-Key' => $idem])
            ->postJson('/api/v1/biometric/punches', [
                'device_serial' => $this->device->serial_number,
                'punches' => [[
                    'enroll_no' => (string) $this->emp->id,
                    'punched_at' => $date . 'T09:05:00+05:30',
                    'method' => $method,
                    'direction' => 'in',
                ]],
            ])->assertOk();

        $punch = BiometricPunch::where('biometric_device_id', $this->device->id)->latest('id')->first();
        $att = Attendance::withoutGlobalScopes()->find($punch->attendance_id);
        DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();

        return [$punch, $att];
    }

    public function test_face_punch_is_labelled_face(): void
    {
        [$punch, $att] = $this->sendPunch('face', 'ROS-FACE-1');
        $this->assertSame('processed', $punch->status);
        $this->assertSame('face', $att->attendance_type);
        $this->assertStringStartsWith('Face · ', $att->clock_in_address);
    }

    public function test_card_punch_is_labelled_card(): void
    {
        [, $att] = $this->sendPunch('card', 'ROS-CARD-1');
        $this->assertSame('card', $att->attendance_type);
    }

    public function test_fingerprint_punch_stays_fingerprint(): void
    {
        [, $att] = $this->sendPunch('fingerprint', 'ROS-FP-1');
        $this->assertSame('fingerprint', $att->attendance_type);
    }

    public function test_card_number_flows_through_roster_and_ack(): void
    {
        $this->emp->update(['card_number' => '6833482']);
        app(BiometricRosterService::class)->rebuild($this->device);

        $feed = $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->getJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster")
            ->assertOk()->json('data.upserts');
        $mine = collect($feed)->firstWhere('id', $this->emp->id);
        $this->assertSame('6833482', $mine['card']);

        $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->postJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster/ack", [
                'applied' => [['id' => $this->emp->id, 'action' => 'upsert', 'ok' => true]],
            ])->assertOk();

        $this->assertSame('6833482', $this->row()->card_pushed);

        // clearing it re-flags the row and drops card from the feed
        $this->emp->update(['card_number' => null]);
        app(BiometricRosterService::class)->rebuild($this->device);
        $this->assertSame('pending', $this->row()->sync_state);

        $feed2 = $this->withHeaders(['Authorization' => "Bearer {$this->secret}"])
            ->getJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster")
            ->assertOk()->json('data.upserts');
        $this->assertNull(collect($feed2)->firstWhere('id', $this->emp->id)['card']);
    }

    public function test_read_only_key_cannot_ack(): void
    {
        $ro = ApiClient::issue($this->tenantId, 'phpunit roster ro ' . uniqid(), ['biometric:read']);
        $this->withHeaders(['Authorization' => "Bearer {$ro['secret']}"])
            ->postJson("/api/v1/biometric/devices/{$this->device->serial_number}/roster/ack", ['applied' => [['id' => 1, 'action' => 'upsert', 'ok' => true]]])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'insufficient_scope');
    }
}
