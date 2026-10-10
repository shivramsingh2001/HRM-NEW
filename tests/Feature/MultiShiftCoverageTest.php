<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Shift\ShiftController as ApiShiftController;
use App\Models\Attendance;
use App\Models\AttendancePunch;
use App\Models\AttendanceRegularization;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Biometric\BiometricAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Multi-shift Phase 5 — gaps not covered by the per-phase tests: biometric
 * punches for a multi-shift employee on a single-punch tenant, the mobile
 * clock-in/out `shift` field, the mobile regularization store (per shift +
 * overnight), and the mobile shift-plan `additional_shifts`. Shared dev DB —
 * throwaway employee/shifts/device removed in tearDown; tenant flags restored.
 */
class MultiShiftCoverageTest extends TestCase
{
    private string $tag;
    private User $admin;
    private User $employee;
    private int $tenantId;
    private array $originalFlags;
    private ?BiometricDevice $device = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit MC ' . uniqid();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalFlags = (array) DB::table('tenants')->where('id', $this->tenantId)->first(['custom_shifts_enabled', 'allow_multiple_punches']);
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1, 'allow_multiple_punches' => 0]);

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'mc.' . uniqid() . '@phpunit.test',
            'employee_id' => 'MC' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        $uid = $this->employee->id;
        if ($this->device) {
            BiometricPunch::where('biometric_device_id', $this->device->id)->delete();
            $this->device->delete();
        }
        AttendancePunch::withoutGlobalScopes()->where('user_id', $uid)->delete();
        DB::table('attendance_tracking_sessions')->where('user_id', $uid)->delete();
        DB::table('attendance_logs')->where('user_id', $uid)->delete();
        Attendance::withoutGlobalScopes()->where('user_id', $uid)->delete();
        DB::table('attendance_summaries')->where('user_id', $uid)->delete();
        AttendanceRegularization::withoutGlobalScopes()->where('user_id', $uid)->delete();
        UserShift::withoutGlobalScopes()->where('user_id', $uid)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $uid)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update($this->originalFlags);
        Auth::logout();
        parent::tearDown();
    }

    private function shift(string $name, string $start, string $end): Shift
    {
        return Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId, 'name' => $this->tag . ' ' . $name,
            'start_time' => $start, 'end_time' => $end, 'is_overnight' => $end <= $start,
            'total_hours' => 8, 'grace_minutes' => 0, 'break_time' => 0,
            'status' => 1, 'created_by' => $this->admin->id,
        ]);
    }

    private function onDay(Shift $shift, string $date, bool $additional = false): UserShift
    {
        return UserShift::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'shift_id' => $shift->id,
            'is_additional' => $additional, 'date' => $date, 'status' => 'upcoming', 'created_by' => $this->admin->id,
        ]);
    }

    public function test_biometric_punches_of_a_multi_shift_employee_open_a_second_session(): void
    {
        $d = Carbon::today()->subDays(2)->toDateString();
        $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $evening = $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);

        $this->device = BiometricDevice::create([
            'tenant_id' => $this->tenantId, 'serial_number' => 'PHPUNIT-MC-' . uniqid(),
            'name' => 'phpunit gate', 'direction_mode' => 'auto', 'is_active' => true,
        ]);
        $service = app(BiometricAttendanceService::class);
        foreach (['06:00', '14:00', '15:00', '23:00'] as $time) {
            $punch = BiometricPunch::create([
                'tenant_id' => $this->tenantId, 'biometric_device_id' => $this->device->id,
                'serial_number' => $this->device->serial_number, 'enroll_no' => '9001',
                'user_id' => $this->employee->id, 'punched_at' => "$d $time:00",
                'direction' => 'auto', 'status' => 'pending',
            ]);
            $service->apply($punch);
            $this->assertSame('processed', $punch->fresh()->status, "punch at $time: " . $punch->fresh()->error);
        }

        $a = Attendance::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $d)->firstOrFail();
        $this->assertSame(2, (int) $a->session_count);
        $this->assertSame(480, (int) $a->extra_shift_minutes);
        $this->assertSame(2, AttendancePunch::withoutGlobalScopes()->where('user_shift_id', $evening->id)->count());
    }

    public function test_mobile_clock_in_and_out_return_the_matched_shift(): void
    {
        $now = Carbon::now()->startOfMinute();
        $this->onDay($this->shift('Current', $now->copy()->subHour()->format('H:i'), $now->copy()->addHours(7)->format('H:i')), $now->toDateString());

        Auth::login($this->employee);
        $controller = app(\App\Http\Controllers\Api\Attendance\ClockController::class);
        $body = ['lat' => 28.6, 'long' => 77.2, 'address' => 'Test field site address'];

        $in = $controller->clockIn(Request::create('/x', 'POST', $body))->getData(true);
        $this->assertTrue($in['status'], $in['message'] ?? '');
        $this->assertSame($this->tag . ' Current', $in['shift']['name']);
        $this->assertFalse($in['shift']['is_additional']);

        $this->travel(2)->minutes();
        $out = $controller->clockOut(Request::create('/x', 'POST', $body))->getData(true);
        $this->assertTrue($out['status'], $out['message'] ?? '');
        $this->assertSame($this->tag . ' Current', $out['data']['shift']['name']);
    }

    public function test_mobile_regularization_store_handles_shift_and_overnight(): void
    {
        $d = Carbon::today()->subDays(3)->toDateString();
        $this->onDay($this->shift('Day', '09:00', '18:00'), $d);
        $night = $this->onDay($this->shift('Night', '22:00', '06:00'), $d, true);
        Auth::login($this->employee);
        $controller = app(\App\Http\Controllers\Api\Attendance\MobileRegularizationController::class);
        $base = ['date' => $d, 'request_type' => 'both', 'in_time' => '22:00', 'out_time' => '06:00', 'reason' => 'Forgot to punch'];

        // Primary (day) shift: out before in is a typo.
        $r = $controller->regularizationStore(Request::create('/x', 'POST', $base))->getData(true);
        $this->assertFalse($r['success']);
        $this->assertStringStartsWith('Out time must be after in time', $r['message']);

        // A shift that is not the employee's on that date.
        $r = $controller->regularizationStore(Request::create('/x', 'POST', $base + ['user_shift_id' => 999999999]))->getData(true);
        $this->assertFalse($r['success']);

        // The night shift: out on the next day is fine, stored against that shift.
        $r = $controller->regularizationStore(Request::create('/x', 'POST', $base + ['user_shift_id' => $night->id]))->getData(true);
        $this->assertTrue($r['success'], $r['message'] ?? '');
        $this->assertSame($night->id, (int) AttendanceRegularization::withoutGlobalScopes()->where('user_id', $this->employee->id)->value('user_shift_id'));
    }

    public function test_mobile_shift_plan_lists_additional_shifts(): void
    {
        $d = Carbon::today()->addDays(3)->toDateString();
        $this->onDay($this->shift('Morning', '06:00', '14:00'), $d);
        $this->onDay($this->shift('Evening', '15:00', '23:00'), $d, true);

        Auth::login($this->employee);
        $res = app(ApiShiftController::class)->myShiftPlan(Request::create('/x', 'GET', ['start_date' => $d, 'end_date' => $d]))->getData(true);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $day = collect($res['data']['shifts'])->firstWhere('date', $d);
        $this->assertSame($this->tag . ' Morning', $day['shift_name']);
        $this->assertCount(1, $day['additional_shifts']);
        $this->assertSame($this->tag . ' Evening', $day['additional_shifts'][0]['shift_name']);
    }
}
