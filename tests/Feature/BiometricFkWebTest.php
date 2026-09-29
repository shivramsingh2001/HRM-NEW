<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FkWeb direct push (terminal -> /api/v1/biometric/fkweb/{token}) — payloads
 * shaped exactly like the ones captured from firmware M61BH v3.16.1842.
 */
class BiometricFkWebTest extends TestCase
{
    private int $tenantId;
    private User $employee;
    private BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        // A throwaway employee — never punch or wipe attendance for a real one.
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'FkWeb Temp',
            'email' => 'fkweb.' . uniqid() . '@phpunit.test',
            'employee_id' => 'FK' . random_int(100000, 999999),
            'password' => bcrypt('x'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);

        $this->device = BiometricDevice::create([
            'tenant_id' => $this->tenantId,
            'serial_number' => 'PHPUNIT-FK-' . uniqid(),
            'name' => 'phpunit fkweb gate',
            'direction_mode' => 'auto',
            'is_active' => true,
            'push_token' => Str::random(40),
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
        AttendancePunch::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_logs')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        parent::tearDown();
    }

    private function body(array $json, string $bin = ''): string
    {
        $j = json_encode($json);

        return pack('V', strlen($j)) . $j . $bin;
    }

    private function push(string $body, array $headers = [], ?string $token = null)
    {
        $server = ['CONTENT_TYPE' => 'application/octet-stream'];
        foreach ($headers as $k => $v) {
            $server['HTTP_' . strtoupper($k)] = $v;
        }

        return $this->call('POST', '/api/v1/biometric/fkweb/' . ($token ?? $this->device->push_token),
            [], [], [], $server, $body);
    }

    private function glog(string $userId, string $ioTime): string
    {
        return $this->body([
            'fk_bin_data_lib' => 'M50', 'user_id' => $userId,
            'verify_mode' => 2, 'io_mode' => 1, 'io_time' => $ioTime,
        ]);
    }

    public function test_receive_cmd_is_a_heartbeat_with_no_command(): void
    {
        $res = $this->push($this->body([
            'fk_name' => 'M61BH', 'fk_time' => '20260928174357',
            'fk_info' => ['firmware' => 'M61BH v3.16.1842'],
        ]), ['request_code' => 'receive_cmd', 'dev_id' => $this->device->serial_number, 'trans_id' => '7']);

        $res->assertOk()
            ->assertHeader('response_code', 'ERROR_NO_CMD')
            ->assertHeader('trans_id', '7')
            ->assertHeader('Content-Length', '0'); // firmware stalls without it
        $this->device->refresh();
        $this->assertNotNull($this->device->last_seen_at);
        $this->assertSame('M61BH', $this->device->model);
    }

    public function test_realtime_glog_creates_attendance_and_is_idempotent(): void
    {
        $date = now()->subDay();
        Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('date', $date->toDateString())->delete();

        // Zero-padded device id == users.id -> self-maps.
        $userId = str_pad((string) $this->employee->id, 8, '0', STR_PAD_LEFT);
        $body = $this->glog($userId, $date->format('Ymd') . '090500');
        $headers = ['request_code' => 'realtime_glog', 'dev_id' => $this->device->serial_number];

        $this->push($body, $headers)->assertOk()->assertHeader('response_code', 'OK');
        $this->push($body, $headers)->assertOk()->assertHeader('response_code', 'OK'); // resend

        $punches = BiometricPunch::where('biometric_device_id', $this->device->id)->get();
        $this->assertCount(1, $punches);
        $this->assertSame((string) $this->employee->id, $punches[0]->enroll_no);
        $this->assertSame($date->format('Y-m-d') . ' 09:05:00', $punches[0]->punched_at->format('Y-m-d H:i:s'));

        $att = Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('date', $date->toDateString())->first();
        $this->assertNotNull($att);
        $this->assertNotEmpty($att->clock_in);
    }

    public function test_enabling_multiple_punches_mid_day_adopts_the_single_punch_row(): void
    {
        $date = now()->subDays(3);
        $day = $date->toDateString();
        $clean = function () use ($day) {
            AttendancePunch::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->employee->id)->where('date', $day)->delete();
            Attendance::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->employee->id)->where('date', $day)->delete();
        };
        $clean();
        $flag = DB::table('tenants')->where('id', $this->tenantId)->value('allow_multiple_punches');
        $userId = str_pad((string) $this->employee->id, 8, '0', STR_PAD_LEFT);
        $headers = ['request_code' => 'realtime_glog', 'dev_id' => $this->device->serial_number];

        try {
            // Morning: single-punch mode writes clock_in/clock_out, no punch rows.
            DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => 0]);
            $this->push($this->glog($userId, $date->format('Ymd') . '090000'), $headers)->assertOk();
            $this->push($this->glog($userId, $date->format('Ymd') . '130000'), $headers)->assertOk();

            // Admin switches multiple punches on; the next punch opens session 2.
            DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => 1]);
            $this->push($this->glog($userId, $date->format('Ymd') . '140000'), $headers)->assertOk();

            $last = BiometricPunch::where('biometric_device_id', $this->device->id)
                ->orderByDesc('punched_at')->first();
            $this->assertSame('processed', $last->status, (string) $last->error);
            $this->assertSame('in', $last->direction);

            $punches = AttendancePunch::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->employee->id)->where('date', $day)->where('status', 'active')
                ->orderBy('punched_at')->get();
            $this->assertSame(['in', 'out', 'in'], $punches->pluck('direction')->all());
            $this->assertSame([1, 1, 2], $punches->pluck('session_seq')->map(fn ($s) => (int) $s)->all());
            $this->assertStringContainsString('phpunit fkweb gate', (string) $punches[2]->address);
        } finally {
            DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => $flag]);
            $clean();
        }
    }

    public function test_headers_stripped_by_proxy_still_work(): void
    {
        $userId = str_pad((string) $this->employee->id, 8, '0', STR_PAD_LEFT);
        $this->push($this->glog($userId, now()->subDays(2)->format('Ymd') . '100000'))
            ->assertOk()->assertHeader('response_code', 'OK');

        $this->assertSame(1, BiometricPunch::where('biometric_device_id', $this->device->id)->count());

        // A raw template continuation block with no headers is just acked.
        $this->push(random_bytes(1024))->assertOk()->assertHeader('response_code', 'OK');
    }

    public function test_enroll_data_links_enrollment_and_discards_templates(): void
    {
        $userId = str_pad((string) $this->employee->id, 8, '0', STR_PAD_LEFT);
        $block1 = $this->body([
            'user_id' => $userId, 'user_name' => 'Test Person (E1',
            'user_privilege' => 0, 'user_enabled' => 1,
            'enroll_data_array' => [['backup_number' => 0, 'enroll_data' => 'BIN_1']],
        ], random_bytes(700));

        $this->push($block1, ['request_code' => 'realtime_enroll_data', 'blk_no' => '1'])
            ->assertHeader('response_code', 'OK');
        $this->push(random_bytes(1024), ['request_code' => 'realtime_enroll_data', 'blk_no' => '2'])
            ->assertHeader('response_code', 'OK');

        $row = BiometricEnrollment::where('biometric_device_id', $this->device->id)->sole();
        $this->assertSame((string) $this->employee->id, $row->enroll_no);
        $this->assertSame($this->employee->id, (int) $row->user_id);
        $this->assertSame('Test Person (E1', $row->name_on_device);
    }

    public function test_direct_onboarding_creates_the_employee_from_a_device_enrollment(): void
    {
        $this->device->update(['allow_direct_onboarding' => true]);
        $deviceUserId = '0000' . random_int(90000, 99999);   // matches no HRM user
        $name = 'Walkup ' . Str::random(6);
        $enroll = $this->body([
            'user_id' => $deviceUserId, 'user_name' => $name,
            'user_privilege' => 0, 'user_enabled' => 1,
        ], random_bytes(300));

        try {
            $this->push($enroll, ['request_code' => 'realtime_enroll_data', 'blk_no' => '1'])
                ->assertOk()->assertHeader('response_code', 'OK');
            $this->push($enroll, ['request_code' => 'realtime_enroll_data', 'blk_no' => '1'])
                ->assertOk(); // re-enroll (new finger) resends it

            $created = User::withoutGlobalScopes()->where('name', $name)->get();
            $this->assertCount(1, $created);
            $this->assertSame($this->tenantId, (int) $created[0]->tenant_id);
            $this->assertNotEmpty($created[0]->employee_id);

            $row = BiometricEnrollment::where('biometric_device_id', $this->device->id)->sole();
            $this->assertSame(ltrim($deviceUserId, '0'), $row->enroll_no);
            $this->assertSame($created[0]->id, (int) $row->user_id);

            // Their punches now resolve to the new employee.
            $this->push($this->glog($deviceUserId, now()->subDays(4)->format('Ymd') . '093000'),
                ['request_code' => 'realtime_glog'])->assertHeader('response_code', 'OK');
            $punch = BiometricPunch::where('biometric_device_id', $this->device->id)->sole();
            $this->assertSame($created[0]->id, (int) $punch->user_id);
        } finally {
            foreach (User::withoutGlobalScopes()->where('name', $name)->pluck('id') as $id) {
                AttendancePunch::withoutGlobalScopes()->where('user_id', $id)->delete();
                Attendance::withoutGlobalScopes()->where('user_id', $id)->delete();
                DB::table('attendance_logs')->where('user_id', $id)->delete();
                BiometricEnrollment::where('user_id', $id)->delete();
                DB::table('user_job_details')->where('user_id', $id)->delete();
                User::withoutGlobalScopes()->where('id', $id)->forceDelete();
            }
        }
    }

    public function test_bad_token_and_wrong_dev_id_are_rejected(): void
    {
        $body = $this->glog('00000001', '20260101090000');

        $this->push($body, [], Str::random(40))->assertStatus(404)->assertHeader('response_code', 'ERROR');
        $this->push($body, ['dev_id' => 'SOMEONE-ELSE'])->assertStatus(403);

        $this->device->update(['is_active' => false]);
        $this->push($body)->assertStatus(404);

        $this->assertSame(0, BiometricPunch::where('biometric_device_id', $this->device->id)->count());
    }
}
