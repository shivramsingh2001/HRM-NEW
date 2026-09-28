<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SBXPC biometric ingest — key auth + scope, dedupe, enroll mapping, in/out
 * flow through the AttendanceEntryService funnel.
 */
class BiometricIngestTest extends TestCase
{
    private int $tenantId;
    private User $employee;
    private BiometricDevice $device;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        $this->employee = User::where('tenant_id', $this->tenantId)->where('status', 1)
            ->where('role', '!=', 'admin')->firstOrFail();

        $issued = ApiClient::issue($this->tenantId, 'phpunit biometric ' . uniqid(),
            ['biometric:write', 'biometric:read']);
        $this->secret = $issued['secret'];

        $this->device = BiometricDevice::create([
            'tenant_id' => $this->tenantId,
            'serial_number' => 'PHPUNIT-' . uniqid(),
            'name' => 'phpunit gate',
            'direction_mode' => 'auto',
            'is_active' => true,
        ]);
        BiometricEnrollment::create([
            'tenant_id' => $this->tenantId,
            'biometric_device_id' => $this->device->id,
            'enroll_no' => '1001',
            'user_id' => $this->employee->id,
        ]);
    }

    protected function tearDown(): void
    {
        BiometricPunch::where('biometric_device_id', $this->device->id)->delete();
        BiometricEnrollment::where('biometric_device_id', $this->device->id)->delete();
        $this->device->delete();
        DB::table('webhook_deliveries')->where('event', 'like', 'biometric%')->delete();
        parent::tearDown();
    }

    private function sendPunches(array $punches, string $idem)
    {
        return $this->withHeaders([
            'Authorization' => "Bearer {$this->secret}",
            'Idempotency-Key' => $idem,
        ])->postJson('/api/v1/biometric/punches', [
            'device_serial' => $this->device->serial_number,
            'punches' => $punches,
        ]);
    }

    public function test_in_punch_creates_attendance_via_the_funnel(): void
    {
        $date = now()->subDay()->toDateString();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('date', $date)->delete();

        $res = $this->sendPunches([[
            'enroll_no' => '1001',
            'punched_at' => $date . 'T09:05:00+05:30',
            'raw_verify_mode' => 1,
            'direction' => 'in',
        ]], 'PHPU-1');

        $res->assertOk()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.duplicates', 0)
            ->assertJsonPath('data.skipped_unmapped', 0);

        $punch = BiometricPunch::where('biometric_device_id', $this->device->id)->latest('id')->first();
        $this->assertSame('processed', $punch->status);
        $this->assertNotNull($punch->attendance_id);

        $att = Attendance::withoutGlobalScopes()->find($punch->attendance_id);
        $this->assertNotNull($att->clock_in);
        $this->assertSame('fingerprint', $att->attendance_type);

        // audit row from the funnel
        $this->assertDatabaseHas('attendance_logs', [
            'attendance_id' => $att->id,
            'source' => 'biometric',
        ]);

        Attendance::withoutGlobalScopes()->where('id', $att->id)->delete();
        \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
    }

    public function test_resent_batch_is_all_duplicates(): void
    {
        $date = now()->subDay()->toDateString();
        $payload = [[
            'enroll_no' => '1001', 'punched_at' => $date . 'T09:10:00+05:30',
            'raw_verify_mode' => 1, 'direction' => 'in',
        ]];

        $this->sendPunches($payload, 'PHPU-2a')->assertOk();
        $again = $this->sendPunches($payload, 'PHPU-2b');
        $again->assertOk()->assertJsonPath('data.accepted', 0)->assertJsonPath('data.duplicates', 1);

        $att = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->first();
        if ($att) {
            \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
            $att->delete();
        }
    }

    public function test_unmapped_enroll_is_skipped_and_reported(): void
    {
        $res = $this->sendPunches([[
            'enroll_no' => '9999',
            'punched_at' => now()->subDay()->toDateString() . 'T08:00:00+05:30',
            'raw_verify_mode' => 1,
        ]], 'PHPU-3');

        $res->assertOk()
            ->assertJsonPath('data.skipped_unmapped', 1)
            ->assertJsonPath('data.unknown_enrolls', ['9999']);
    }

    public function test_in_then_out_fills_one_attendance_row(): void
    {
        $date = now()->subDay()->toDateString();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->sendPunches([['enroll_no' => '1001', 'punched_at' => $date . 'T09:00:00+05:30', 'raw_verify_mode' => 1, 'direction' => 'in']], 'PHPU-4a')->assertOk();
        $this->sendPunches([['enroll_no' => '1001', 'punched_at' => $date . 'T18:15:00+05:30', 'raw_verify_mode' => 1, 'direction' => 'out']], 'PHPU-4b')->assertOk();

        $att = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->first();
        $this->assertNotNull($att->clock_in);
        $this->assertNotNull($att->clock_out);
        $this->assertGreaterThan(0, (float) $att->worked_hours);

        \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
        $att->delete();
    }

    public function test_read_only_key_cannot_write(): void
    {
        $ro = ApiClient::issue($this->tenantId, 'phpunit ro ' . uniqid(), ['biometric:read']);
        $this->withHeaders(['Authorization' => "Bearer {$ro['secret']}"])
            ->postJson('/api/v1/biometric/punches', ['device_serial' => $this->device->serial_number, 'punches' => [['enroll_no' => '1001', 'punched_at' => now()->toIso8601String()]]])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'insufficient_scope');
    }
}
