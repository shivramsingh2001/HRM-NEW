<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\User\UserController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mobile Team API: an employee who clocks in on an approved leave day worked
 * that day — attendance comes before leave (as the web calendars), so
 * /api/user/team-view and /api/user/team-profile/{id} must not say "On Leave".
 * The same for a holiday. A leave day without a clock-in still shows as leave,
 * and a half-day leave as "First / Second Half Leave" (sessions are session1 / session2).
 */
class LeaveDayAttendanceApiTest extends TestCase
{
    private User $employee;

    private User $manager;

    private int $tenantId;

    private ?int $holidayId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $admin->tenant_id;

        $make = fn (string $role, string $prefix) => User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit '.$prefix.' '.uniqid(),
            'email' => strtolower($prefix).'.'.uniqid().'@phpunit.test',
            'employee_id' => $prefix.random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => $role,
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
        $this->employee = $make('employee', 'LD');
        $this->manager = $make('manager', 'LM');
        DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id,
            'reporting_head_id' => $this->manager->id, 'is_primary' => 1, 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        DB::table('holidays')->where('id', $this->holidayId)->delete();
        DB::table('attendances')->where('user_id', $this->employee->id)->delete();
        DB::table('leaves')->where('user_id', $this->employee->id)->delete();
        DB::table('user_reporting_heads')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->whereIn('id', [$this->employee->id, $this->manager->id])->forceDelete();
        Auth::logout();
        parent::tearDown();
    }

    public function test_clock_in_on_a_leave_or_holiday_shows_as_worked_and_half_day_leave_is_labelled(): void
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $uid = $this->employee->id;

        // Today: approved full-day leave AND a company holiday — the employee came in anyway.
        // Yesterday: approved first-half leave, no clock-in.
        DB::table('leaves')->insert([
            ['tenant_id' => $this->tenantId, 'leave_id' => 'PHPU-'.random_int(1000, 9999), 'user_id' => $uid, 'leave_type' => 1,
                'start_date' => $today->toDateString(), 'start_session' => 'fullday', 'end_date' => $today->toDateString(), 'end_session' => 'fullday',
                'status' => 'approved', 'created_at' => now()],
            ['tenant_id' => $this->tenantId, 'leave_id' => 'PHPU-'.random_int(1000, 9999), 'user_id' => $uid, 'leave_type' => 1,
                'start_date' => $yesterday->toDateString(), 'start_session' => 'session1', 'end_date' => $yesterday->toDateString(), 'end_session' => 'session1',
                'status' => 'approved', 'created_at' => now()],
        ]);
        $this->holidayId = DB::table('holidays')->insertGetId(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit Holiday',
            'start_date' => $today->toDateString(), 'end_date' => $today->toDateString(), 'status' => 1, 'created_at' => now()]);
        DB::table('attendances')->insert(['tenant_id' => $this->tenantId, 'user_id' => $uid, 'date' => $today->toDateString(),
            'clock_in' => $today->copy()->setTime(0, 5), 'attendance_status' => 'present', 'created_at' => now()]);

        // Team list (manager) — today: worked, not "Holiday" / "On Leave".
        Auth::login($this->manager);
        $team = app(UserController::class)->team(Request::create('/', 'GET'))->getData(true);
        $row = collect($team['data']['teams'] ?? [])->firstWhere('id', $uid);
        $this->assertNotNull($row, 'employee is in the manager\'s team list');
        $this->assertSame('Present (Not Checked Out)', $row['status']);

        // Member calendar — today worked; yesterday (no clock-in) is a first-half leave, not a full day.
        $data = app(UserController::class)->getUserProfile($uid)->getData(true)['data'];
        $days = collect($data['attendance_details']['attendances'])->keyBy('date');
        $this->assertSame('Checked In Only', $days[$today->toDateString()]['day_status']);
        $y = $days[$yesterday->toDateString()] ?? null; // not in this month's calendar on the 1st
        if ($y && $y['holiday_name'] === null && $y['day_status'] !== 'Weekend') {
            $this->assertSame('First Half Leave', $y['day_status']);
        }
    }
}
