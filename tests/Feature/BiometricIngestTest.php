<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use Carbon\Carbon;
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
    private $multiPunchFlag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        // These assertions cover the single-punch path; don't depend on the dev tenant's setting.
        $this->multiPunchFlag = DB::table('tenants')->where('id', $this->tenantId)->value('allow_multiple_punches');
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => 0]);
        // A throwaway employee — never punch or wipe attendance for a real one.
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Ingest Temp',
            'email' => 'ingest.' . uniqid() . '@phpunit.test',
            'employee_id' => 'IN' . random_int(100000, 999999),
            'password' => bcrypt('x'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);

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
        // The roster observer also queues the temp employee onto real auto_provision devices.
        BiometricEnrollment::where('user_id', $this->employee->id)->delete();
        DB::table('attendance_punches')->where('user_id', $this->employee->id)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_logs')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => $this->multiPunchFlag]);
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

    private function dayRow(string $date): ?Attendance
    {
        return Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->first();
    }

    private function punchAt(string $date, string $time, string $idem)
    {
        return $this->sendPunches([['enroll_no' => '1001', 'punched_at' => $date . 'T' . $time . '+05:30', 'raw_verify_mode' => 1]], $idem)->assertOk();
    }

    public function test_first_punch_in_last_punch_out_middle_ignored(): void
    {
        $this->device->update(['direction_mode' => 'first_last']);
        $date = now()->subDays(2)->toDateString();
        Attendance::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->punchAt($date, '09:00:00', 'PHPU-6a');
        $this->punchAt($date, '13:00:00', 'PHPU-6b');   // lunch: clock-out for now
        $this->assertSame('13:00:00', Carbon::parse($this->dayRow($date)->clock_out)->format('H:i:s'));

        $this->punchAt($date, '19:00:00', 'PHPU-6c');   // later punch moves the clock-out
        $this->punchAt($date, '14:00:00', 'PHPU-6d');   // late upload, in between: ignored

        $att = $this->dayRow($date);
        $this->assertSame('09:00:00', Carbon::parse($att->clock_in)->format('H:i:s'));
        $this->assertSame('19:00:00', Carbon::parse($att->clock_out)->format('H:i:s'));
        $this->assertEqualsWithDelta(10.0, (float) $att->worked_hours, 0.02);
        $this->assertSame('between clock-in and clock-out',
            BiometricPunch::where('biometric_device_id', $this->device->id)->latest('id')->value('error'));

        \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
        $att->delete();
    }

    public function test_late_uploaded_earlier_punch_becomes_clock_in(): void
    {
        $this->device->update(['direction_mode' => 'first_last']);
        $date = now()->subDays(3)->toDateString();
        Attendance::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->punchAt($date, '18:00:00', 'PHPU-7a');   // machine sends the evening punch first
        $this->punchAt($date, '09:30:00', 'PHPU-7b');   // morning punch arrives later

        $att = $this->dayRow($date);
        $this->assertSame('09:30:00', Carbon::parse($att->clock_in)->format('H:i:s'));
        $this->assertSame('18:00:00', Carbon::parse($att->clock_out)->format('H:i:s'));
        $this->assertEqualsWithDelta(8.5, (float) $att->worked_hours, 0.02);

        \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
        $att->delete();
    }

    public function test_hand_set_day_keeps_its_clock_out(): void
    {
        $this->device->update(['direction_mode' => 'first_last']);
        $date = now()->subDays(4)->toDateString();
        Attendance::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->punchAt($date, '09:00:00', 'PHPU-8a');
        $this->punchAt($date, '17:00:00', 'PHPU-8b');
        $att = $this->dayRow($date);
        $att->forceFill(['attendance_type' => 'manual'])->save();

        $this->punchAt($date, '20:00:00', 'PHPU-8c');

        $this->assertSame('17:00:00', Carbon::parse($this->dayRow($date)->clock_out)->format('H:i:s'));

        \DB::table('attendance_logs')->where('attendance_id', $att->id)->delete();
        $att->delete();
    }

    public function test_auto_device_keeps_classic_rule(): void
    {
        $this->device->update(['direction_mode' => 'auto']);
        $date = now()->subDays(5)->toDateString();
        Attendance::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $date)->delete();

        $this->punchAt($date, '09:00:00', 'PHPU-9a');
        $this->punchAt($date, '13:00:00', 'PHPU-9b');
        $this->punchAt($date, '19:00:00', 'PHPU-9c');   // option off: ignored

        $att = $this->dayRow($date);
        $this->assertSame('13:00:00', Carbon::parse($att->clock_out)->format('H:i:s'));
        $this->assertSame('already clocked out',
            BiometricPunch::where('biometric_device_id', $this->device->id)->latest('id')->value('error'));

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
