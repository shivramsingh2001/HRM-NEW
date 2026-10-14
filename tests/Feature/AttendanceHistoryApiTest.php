<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/user/attendance/history — week-offs and leaves on the right days.
 *
 * Weekly offs saved from the Employee 360 page have no start/end date (they
 * apply every week); the history SQL used to need a date range, so those
 * Saturdays/Sundays came back "Absent". A multi-day leave used to show only
 * on its first day.
 */
class AttendanceHistoryApiTest extends TestCase
{
    private User $employee;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $admin->tenant_id;

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'PHPUnit History ' . uniqid(),
            'email' => 'hist.' . uniqid() . '@phpunit.test',
            'employee_id' => 'HS' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('user_weekoffs')->where('user_id', $this->employee->id)->delete();
        DB::table('leaves')->where('user_id', $this->employee->id)->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        Auth::logout();
        parent::tearDown();
    }

    public function test_week_offs_without_dates_and_multi_day_leaves_show_on_every_day(): void
    {
        $monday = Carbon::today()->subWeeks(2)->startOfWeek();
        $sunday = $monday->copy()->addDays(6);
        $uid = $this->employee->id;

        // Weekly offs exactly as the Employee 360 page saves them: no dates.
        foreach (['Saturday', 'Sunday'] as $day) {
            DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $uid, 'off_type' => 'day_based',
                'day_name' => $day, 'start_date' => null, 'end_date' => null, 'created_by' => $uid, 'status' => 1, 'created_at' => now()]);
        }
        // A one-off date-based week-off on Thursday.
        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $uid, 'off_type' => 'date_based',
            'start_date' => $monday->copy()->addDays(3)->toDateString(), 'end_date' => $monday->copy()->addDays(3)->toDateString(),
            'created_by' => $uid, 'status' => 1, 'created_at' => now()]);
        // Monday–Wednesday full-day leave.
        DB::table('leaves')->insert(['tenant_id' => $this->tenantId, 'leave_id' => 'PHPU-' . random_int(1000, 9999), 'user_id' => $uid,
            'leave_type' => 1, 'start_date' => $monday->toDateString(), 'start_session' => 'fullday',
            'end_date' => $monday->copy()->addDays(2)->toDateString(), 'end_session' => 'fullday', 'status' => 'approved', 'created_at' => now()]);

        Auth::login($this->employee);
        $history = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', [
            'start_date' => $monday->toDateString(), 'end_date' => $sunday->toDateString(),
        ]))->getData(true)['data'])->keyBy('date');

        $expected = ['Full Day Leave', 'Full Day Leave', 'Full Day Leave', 'Week Off', 'Absent', 'Week Off', 'Week Off'];
        foreach ($expected as $offset => $status) {
            $date = $monday->copy()->addDays($offset)->toDateString();
            if ($history[$date]['holiday_name'] !== null) {
                continue; // a real tenant holiday on the dev DB wins over everything
            }
            $this->assertSame($status, $history[$date]['day_status'], $date);
        }
    }

    /**
     * The three ways a weekly off is stored:
     *   Employee 360 page              → no start / no end
     *   Shift Assignment "Fixed"       → start date, no end (open-ended)
     *   Shift Assignment "Flexible"    → start + end date
     * and a Flexible one that already ended must NOT apply.
     */
    public function test_weekly_offs_from_fixed_and_flexible_assignments(): void
    {
        $monday = Carbon::today()->subWeeks(2)->startOfWeek();
        $saturday = $monday->copy()->addDays(5)->toDateString();
        $shapes = [
            'employee 360' => [null, null, 'Week Off'],
            'fixed' => [$monday->copy()->subMonth()->toDateString(), null, 'Week Off'],
            'flexible' => [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString(), 'Week Off'],
            'flexible ended' => [$monday->copy()->subMonth()->toDateString(), $monday->copy()->subDay()->toDateString(), 'Absent'],
            'fixed starts later' => [$monday->copy()->addWeek()->toDateString(), null, 'Absent'],
        ];

        Auth::login($this->employee);
        foreach ($shapes as $label => [$start, $end, $expected]) {
            DB::table('user_weekoffs')->where('user_id', $this->employee->id)->delete();
            DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'off_type' => 'day_based',
                'day_name' => 'Saturday', 'start_date' => $start, 'end_date' => $end, 'created_by' => $this->employee->id, 'status' => 1, 'created_at' => now()]);

            $day = collect(app(AttendanceController::class)->history(Request::create('/', 'GET', [
                'start_date' => $saturday, 'end_date' => $saturday,
            ]))->getData(true)['data'])->first();
            if ($day['holiday_name'] !== null) {
                $this->markTestSkipped("$saturday is a holiday on this DB");
            }
            $this->assertSame($expected, $day['day_status'], "history: $label");

            $profileDay = collect(app(\App\Http\Controllers\Api\User\UserController::class)->getUserProfile($this->employee->id)
                ->getData(true)['data']['attendance_details']['attendances'])->firstWhere('date', $saturday);
            $this->assertSame($expected === 'Week Off' ? 'Weekend' : 'Absent', $profileDay['day_status'], "team-profile: $label");
        }
    }

    public function test_team_profile_calendar_shows_week_offs_without_dates(): void
    {
        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'off_type' => 'day_based',
            'day_name' => 'Saturday', 'start_date' => null, 'end_date' => null, 'created_by' => $this->employee->id, 'status' => 1, 'created_at' => now()]);

        Auth::login($this->employee);
        $data = app(\App\Http\Controllers\Api\User\UserController::class)->getUserProfile($this->employee->id)->getData(true)['data'];
        $saturdays = collect($data['attendance_details']['attendances'])
            ->filter(fn ($d) => Carbon::parse($d['date'])->isSaturday() && $d['holiday_name'] === null)->pluck('day_status')->unique()->values()->all();

        $this->assertSame(['Weekend'], $saturdays);
        $this->assertGreaterThan(0, $data['attendance_summary']['counts']['weekend']);
    }
}
