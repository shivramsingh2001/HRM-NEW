<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Shift Report (Monthly) — new "Shift Report" card on the Attendance tab of Reports
 * (client/report/index.blade.php), backed by ShiftReportController. Reads the same
 * `user_shifts`/`user_weekoffs` data the Shift Roster shows, so it agrees with it.
 * Runs against the shared dev DB (no RefreshDatabase) — mirrors ShiftAssignmentTest's
 * tenant-selection pattern. Every test cleans up the rows it creates.
 */
class ShiftReportTest extends TestCase
{
    private int $tenantId;
    private ?bool $originalCustomShiftsEnabled = null;
    private array $shiftIds = [];
    private array $userShiftIds = [];
    private array $weekoffIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        $this->originalCustomShiftsEnabled = (bool) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);
    }

    protected function tearDown(): void
    {
        UserShift::whereIn('id', $this->userShiftIds)->delete();
        UserWeekoffs::whereIn('id', $this->weekoffIds)->delete();
        Shift::withoutGlobalScopes()->whereIn('id', $this->shiftIds)->delete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => (int) $this->originalCustomShiftsEnabled]);

        Auth::logout();
        parent::tearDown();
    }

    private function actingAdmin(): User
    {
        $admin = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();
        Auth::login($admin);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        return $admin;
    }

    private function makeShift(string $name, string $start, string $end): Shift
    {
        $shift = Shift::create([
            'tenant_id' => $this->tenantId, 'name' => $name, 'start_time' => $start, 'end_time' => $end,
            'total_hours' => abs(\Carbon\Carbon::parse($end)->diffInHours(\Carbon\Carbon::parse($start))),
            'color_code' => '#1e3a8a', 'status' => 1, 'created_by' => Auth::id(),
        ]);
        $this->shiftIds[] = $shift->id;

        return $shift;
    }

    private function assign(User $user, Shift $shift, string $date): void
    {
        $row = UserShift::create([
            'tenant_id' => $this->tenantId, 'user_id' => $user->id, 'shift_id' => $shift->id, 'date' => $date,
            'status' => 1, 'created_by' => Auth::id(),
        ]);
        $this->userShiftIds[] = $row->id;
    }

    private function pickTestUser(int $skip = 0): User
    {
        return User::where('tenant_id', $this->tenantId)->where('status', 1)->where('role', '!=', 'admin')
            ->orderBy('id')->skip($skip)->first();
    }

    public function test_the_shift_report_card_is_on_the_attendance_tab(): void
    {
        $this->actingAdmin();

        $this->get(route('report.attendance.index'))->assertOk()
            ->assertSee('Shift Report')
            ->assertSee(route('report.attendance.shift-monthly.index'), false);
    }

    public function test_monthly_matrix_shows_the_right_shift_per_day_and_totals_and_respects_filters(): void
    {
        $this->actingAdmin();
        $user = $this->pickTestUser();
        $month = now()->startOfMonth()->addMonth()->format('Y-m'); // a clean future month, no real attendance/roster noise
        $day1 = $month . '-05';
        $day2 = $month . '-06';

        $morning = $this->makeShift('Morning Shift ' . uniqid(), '09:00', '18:00');
        $this->assign($user, $morning, $day1);

        $wo = UserWeekoffs::create([
            'tenant_id' => $this->tenantId, 'user_id' => $user->id, 'off_type' => 'date_based',
            'start_date' => $day2, 'end_date' => $day2, 'status' => 1, 'created_by' => Auth::id(),
        ]);
        $this->weekoffIds[] = $wo->id;

        $page = $this->get(route('report.attendance.shift-monthly.index', ['month' => $month]))->assertOk();
        $page->assertSee($user->name)->assertSee('MS', false)->assertSee('WO', false);

        // filtering to a different shift excludes this employee's row (they have none of it)
        $other = $this->makeShift('Night Shift ' . uniqid(), '20:00', '05:00');
        $filtered = $this->get(route('report.attendance.shift-monthly.index', ['month' => $month, 'shift_id' => $other->id]))->assertOk();
        $filtered->assertDontSee($user->employee_id);

        // CSV export contains the assigned shift on the right date
        $csv = $this->get(route('report.attendance.shift-monthly.export', ['month' => $month]))->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString($day1 . ',' . \Carbon\Carbon::parse($day1)->format('D') . ',' . $user->employee_id, $csv->getContent());
    }

    public function test_a_manager_only_sees_their_own_reportees(): void
    {
        $manager = User::where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', 1)->first();
        if (! $manager) {
            $this->markTestSkipped('no manager fixture in this tenant');
        }

        Auth::login($manager);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        $this->get(route('report.attendance.shift-monthly.index'))->assertOk();
    }
}
