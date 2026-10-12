<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Team Members multi-select → mark the same status for several employees
 * (team.mark-attendance-bulk → BulkAttendanceMarker → AttendanceEntryService::markStatus).
 *
 * Shared dev DB (no RefreshDatabase): creates its own employees / shift and
 * removes everything it wrote in tearDown.
 */
class BulkMarkAttendanceTest extends TestCase
{
    private User $admin;

    private int $tenantId;

    private string $date;

    /** @var list<User> */
    private array $employees = [];

    private ?int $shiftId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->date = Carbon::today()->subDays(3)->toDateString();
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));

        for ($i = 0; $i < 3; $i++) {
            $this->employees[] = User::withoutGlobalScopes()->forceCreate([
                'name' => 'PHPUnit Bulk '.$i.' '.uniqid(), 'email' => 'bulk'.$i.'.'.uniqid().'@phpunit.test',
                'employee_id' => 'BK'.random_int(100000, 999999), 'password' => bcrypt('secret'),
                'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $ids = array_map(fn ($u) => $u->id, $this->employees);
        DB::table('attendance_punches')->whereIn('user_id', $ids)->delete();
        DB::table('attendance_logs')->whereIn('user_id', $ids)->delete();
        DB::table('attendances')->whereIn('user_id', $ids)->delete();
        DB::table('attendance_summaries')->whereIn('user_id', $ids)->delete();
        DB::table('user_shifts')->whereIn('user_id', $ids)->delete();
        if ($this->shiftId) {
            DB::table('shifts')->where('id', $this->shiftId)->delete();
        }
        User::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        parent::tearDown();
    }

    private function row(User $u, ?string $date = null)
    {
        return DB::table('attendances')->where('user_id', $u->id)->where('date', $date ?? $this->date)->first();
    }

    private function ids(array $users): array
    {
        return array_map(fn ($u) => $u->id, $users);
    }

    public function test_absent_for_several_employees(): void
    {
        $res = $this->actingAs($this->admin)->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => $this->ids($this->employees),
            'date' => $this->date,
            'status' => 'absent',
            'remarks' => 'Bulk test',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertCount(3, $res->json('marked'));
        $this->assertSame([], $res->json('failed'));
        foreach ($this->employees as $u) {
            $row = $this->row($u);
            $this->assertSame('absent', $row->attendance_status);
            $this->assertSame('manual', $row->attendance_type);
            $this->assertEquals($this->admin->id, $row->marked_by);
        }
    }

    public function test_present_with_fixed_times_over_a_date_range(): void
    {
        $end = Carbon::parse($this->date)->addDay()->toDateString();

        $this->actingAs($this->admin)->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => $this->ids(array_slice($this->employees, 0, 2)),
            'date' => $this->date,
            'end_date' => $end,
            'status' => 'present',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
        ])->assertOk()->assertJsonPath('marked.0.days', 2);

        foreach (array_slice($this->employees, 0, 2) as $u) {
            foreach ([$this->date, $end] as $d) {
                $row = $this->row($u, $d);
                $this->assertSame($d.' 09:00:00', $row->clock_in);
                $this->assertSame($d.' 18:00:00', $row->clock_out);
                $this->assertSame(9.0, (float) $row->worked_hours);
            }
        }
        $this->assertNull($this->row($this->employees[2]), 'an employee that was not selected is untouched');
    }

    public function test_shift_timings_per_employee_and_a_clear_reason_when_there_is_no_shift(): void
    {
        $this->shiftId = DB::table('shifts')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'PHPUnit bulk shift '.uniqid(),
            'start_time' => '10:00:00', 'end_time' => '19:00:00', 'total_hours' => '09:00',
            'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        [$withShift, $noShift] = $this->employees;
        DB::table('user_shifts')->insert([
            'tenant_id' => $this->tenantId, 'user_id' => $withShift->id, 'shift_id' => $this->shiftId,
            'date' => $this->date, 'is_additional' => 0, 'status' => 'upcoming', 'created_by' => $this->admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->admin)->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => [$withShift->id, $noShift->id],
            'date' => $this->date,
            'status' => 'present',
            'use_shift_times' => 1,
        ])->assertOk();

        $this->assertSame([$withShift->id], array_column($res->json('marked'), 'user_id'));
        $this->assertSame($noShift->id, $res->json('failed.0.user_id'));
        $this->assertStringContainsString('No shift assigned', $res->json('failed.0.reason'));

        $row = $this->row($withShift);
        $this->assertSame($this->date.' 10:00:00', $row->clock_in);
        $this->assertSame($this->date.' 19:00:00', $row->clock_out);
        $this->assertNull($this->row($noShift));
    }

    public function test_team_members_page_shows_the_multi_select(): void
    {
        $this->actingAs($this->admin)->get(route('team.index', ['date' => $this->date]))
            ->assertOk()
            ->assertSee('id="selectAll"', false)
            ->assertSee('class="row-sel"', false)
            ->assertSee('id="bulkMarkDrawer"', false)
            ->assertSee(route('team.mark-attendance-bulk'), false);
    }

    public function test_validation_and_access(): void
    {
        $this->actingAs($this->admin)->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => [], 'date' => $this->date, 'status' => 'absent',
        ])->assertStatus(422)->assertJsonValidationErrors('user_ids');

        $this->actingAs($this->admin)->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => $this->ids($this->employees), 'date' => $this->date, 'status' => 'half_day',
        ])->assertStatus(422)->assertJsonValidationErrors(['clock_in', 'clock_out']);

        // A plain employee may not mark anyone.
        $this->actingAs($this->employees[0])->postJson(route('team.mark-attendance-bulk'), [
            'user_ids' => [$this->employees[1]->id], 'date' => $this->date, 'status' => 'absent',
        ])->assertForbidden();
        $this->assertNull($this->row($this->employees[1]));
    }
}
