<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use App\Services\Biometric\BiometricDayRebuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Re-applying a device's Direction rule to past days (BiometricDayRebuilder),
 * and biometric:reprocess --remap no longer re-applying rule-skipped punches.
 * Shared dev DB — throwaway employee + device, cleaned in tearDown.
 */
class BiometricDayRebuildTest extends TestCase
{
    private int $tenantId;

    private User $employee;

    private BiometricDevice $device;

    private string $secret;

    private $multiPunchFlag;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        $this->multiPunchFlag = DB::table('tenants')->where('id', $this->tenantId)->value('allow_multiple_punches');
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => 0]);
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Rebuild Temp', 'email' => 'rebuild.'.uniqid().'@phpunit.test',
            'employee_id' => 'RB'.random_int(100000, 999999), 'password' => bcrypt('x'),
            'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
        $this->secret = ApiClient::issue($this->tenantId, 'phpunit rebuild '.uniqid(), ['biometric:write', 'biometric:read'])['secret'];
        $this->device = BiometricDevice::create([
            'tenant_id' => $this->tenantId, 'serial_number' => 'PHPUNIT-RB-'.uniqid(),
            'name' => 'phpunit rebuild gate', 'direction_mode' => 'auto', 'is_active' => true,
        ]);
        BiometricEnrollment::create([
            'tenant_id' => $this->tenantId, 'biometric_device_id' => $this->device->id,
            'enroll_no' => 'RB2001', 'user_id' => $this->employee->id,
        ]);
        $this->date = now()->subDays(2)->toDateString();
    }

    protected function tearDown(): void
    {
        BiometricPunch::where('biometric_device_id', $this->device->id)->delete();
        BiometricEnrollment::where('user_id', $this->employee->id)->delete();
        $this->device->delete();
        DB::table('webhook_deliveries')->where('event', 'like', 'biometric%')->delete();
        DB::table('attendance_punches')->where('user_id', $this->employee->id)->delete();
        DB::table('attendance_logs')->where('user_id', $this->employee->id)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['allow_multiple_punches' => $this->multiPunchFlag]);
        parent::tearDown();
    }

    private function punches(array $times): void
    {
        foreach ($times as $i => $t) {
            $this->withHeaders(['Authorization' => "Bearer {$this->secret}", 'Idempotency-Key' => 'RB-'.uniqid().$i])
                ->postJson('/api/v1/biometric/punches', [
                    'device_serial' => $this->device->serial_number,
                    'punches' => [['enroll_no' => 'RB2001', 'punched_at' => "{$this->date}T{$t}+05:30", 'raw_verify_mode' => 1]],
                ])->assertOk();
        }
    }

    private function day(): Attendance
    {
        return Attendance::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)->where('date', $this->date)->firstOrFail();
    }

    private function times(): array
    {
        $d = $this->day();

        return [Carbon::parse($d->clock_in)->format('H:i:s'), $d->clock_out ? Carbon::parse($d->clock_out)->format('H:i:s') : null];
    }

    public function test_mode_change_alone_leaves_past_days_and_rebuild_applies_it(): void
    {
        $this->punches(['09:00:00', '13:00:00', '19:00:00']);
        $this->assertSame(['09:00:00', '13:00:00'], $this->times());      // auto: 2nd punch out, 3rd ignored

        $this->device->update(['direction_mode' => 'first_last']);
        Artisan::call('biometric:reprocess', ['--remap' => true, '--device' => $this->device->id]);
        $this->assertSame(['09:00:00', '13:00:00'], $this->times());      // "from now on": past day untouched

        $s = app(BiometricDayRebuilder::class)->rebuild($this->device, $this->date, $this->date);
        $this->assertSame(1, $s['days_rebuilt']);
        $this->assertSame(['09:00:00', '19:00:00'], $this->times());      // rebuilt with first in, last out
        $this->assertEqualsWithDelta(10.0, (float) $this->day()->worked_hours, 0.02);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->punches(['09:00:00', '13:00:00', '19:00:00']);
        $this->device->update(['direction_mode' => 'first_last']);

        $s = app(BiometricDayRebuilder::class)->rebuild($this->device, $this->date, $this->date, true);

        $this->assertTrue($s['dry_run']);
        $this->assertSame(1, $s['days_rebuilt']);
        $this->assertSame(['09:00:00', '13:00:00'], $this->times());
        $this->assertSame('already clocked out', BiometricPunch::where('biometric_device_id', $this->device->id)
            ->where('punched_at', "{$this->date} 19:00:00")->value('error'));
    }

    public function test_hand_set_day_is_skipped(): void
    {
        $this->punches(['09:00:00', '13:00:00', '19:00:00']);
        $this->day()->forceFill(['attendance_type' => 'manual'])->save();
        $this->device->update(['direction_mode' => 'first_last']);

        $s = app(BiometricDayRebuilder::class)->rebuild($this->device, $this->date, $this->date);

        $this->assertSame(1, $s['skipped']['hand_set']);
        $this->assertSame(['09:00:00', '13:00:00'], $this->times());
    }

    public function test_remap_still_processes_a_punch_once_its_employee_is_mapped(): void
    {
        BiometricEnrollment::where('biometric_device_id', $this->device->id)->update(['user_id' => null]);
        $this->punches(['09:00:00']);
        $punch = BiometricPunch::where('biometric_device_id', $this->device->id)->firstOrFail();
        $this->assertSame('skipped', $punch->status);

        BiometricEnrollment::where('biometric_device_id', $this->device->id)->update(['user_id' => $this->employee->id]);
        Artisan::call('biometric:reprocess', ['--remap' => true, '--device' => $this->device->id]);

        $this->assertSame('processed', $punch->fresh()->status);
        $this->assertSame('09:00:00', $this->times()[0]);
    }

    public function test_edit_device_screen_offers_now_only_or_past_days(): void
    {
        $this->punches(['09:00:00', '13:00:00', '19:00:00']);
        $admin = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'admin')->firstOrFail();
        $form = ['name' => 'phpunit rebuild gate', 'direction_mode' => 'first_last', 'is_active' => 1, 'auto_provision' => 0];

        // "From now on only" — past day untouched.
        $this->actingAs($admin)->putJson("/settings/biometric/devices/{$this->device->id}", $form + ['apply_past' => 0])
            ->assertOk()->assertJsonPath('rebuilt', false);
        $this->assertSame(['09:00:00', '13:00:00'], $this->times());

        // Back to auto, then first_last again with "Also past days from".
        $this->device->refresh()->update(['direction_mode' => 'auto']);
        $res = $this->actingAs($admin)->putJson("/settings/biometric/devices/{$this->device->id}",
            $form + ['apply_past' => 1, 'apply_from' => $this->date]);
        $res->assertOk()->assertJsonPath('rebuilt', true);
        $this->assertStringContainsString('1 day(s) rebuilt', $res->json('message'));
        $this->assertSame(['09:00:00', '19:00:00'], $this->times());

        // A date older than the limit is refused.
        $this->device->refresh()->update(['direction_mode' => 'auto']);
        $this->actingAs($admin)->putJson("/settings/biometric/devices/{$this->device->id}",
            $form + ['apply_past' => 1, 'apply_from' => now()->subDays(200)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('apply_from');
    }

    public function test_range_is_capped(): void
    {
        [$from, $to] = app(BiometricDayRebuilder::class)->range('2020-01-01', now()->addDays(5)->toDateString());

        $this->assertSame(now()->toDateString(), $to);
        $this->assertSame(now()->subDays(BiometricDayRebuilder::MAX_DAYS)->toDateString(), $from);
    }
}
