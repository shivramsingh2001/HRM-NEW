<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomShiftsEnabled;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\TenantShiftResolver;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Multi-shift Phase 2: a 2nd+ shift on the same day ("Add as additional
 * shift"), the overlap rule, the one-primary-per-day DB key, and the screens
 * that list several shifts per day. Shared dev DB — a throwaway employee and
 * throwaway shifts, all removed in tearDown; custom_shifts_enabled restored.
 */
class MultiShiftTest extends TestCase
{
    private string $tag;
    private User $admin;
    private User $employee;
    private int $tenantId;
    private int $originalCustom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit MS ' . uniqid();
        $this->withoutMiddleware([EnsureCustomShiftsEnabled::class, EnsureFeatureEnabled::class]);

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'ms.' . uniqid() . '@phpunit.test',
            'employee_id' => 'MS' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        DB::table('user_weekoffs')->where('user_id', $this->employee->id)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => $this->originalCustom]);
        parent::tearDown();
    }

    private function shift(string $name, string $start, string $end): Shift
    {
        return Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'name' => $this->tag . ' ' . $name,
            'start_time' => $start,
            'end_time' => $end,
            'is_overnight' => $end <= $start,
            'total_hours' => 8,
            'status' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    private function assign(Shift $shift, string $start, ?string $end = null, array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson(route('shift.assign'), $extra + [
            'type' => 'flexible',
            'assign_type' => 'user',
            'user_ids' => [$this->employee->id],
            'shift_id' => $shift->id,
            'start_date' => $start,
            'end_date' => $end ?? $start,
        ]);
    }

    private function rowsOn(string $date)
    {
        return UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $date)->orderBy('is_additional')->get();
    }

    public function test_additional_shift_keeps_the_primary_and_the_resolver_returns_both(): void
    {
        $d1 = Carbon::today()->addDays(2)->toDateString();
        $d2 = Carbon::today()->addDays(3)->toDateString();
        $morning = $this->shift('Morning', '06:00', '14:00');
        $evening = $this->shift('Evening', '14:00', '22:00');

        $this->assign($morning, $d1, $d2)->assertOk();
        $this->assign($evening, $d1, $d2, ['is_additional' => 1])->assertOk()->assertJsonPath('data.assigned', 2);

        $rows = $this->rowsOn($d1);
        $this->assertCount(2, $rows);
        $this->assertSame([$morning->id, $evening->id], $rows->pluck('shift_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([false, true], $rows->pluck('is_additional')->all());

        $resolver = app(TenantShiftResolver::class);
        $this->assertSame($morning->id, $resolver->forUserDate($this->employee->id, $this->tenantId, $d1)->id);
        $this->assertSame((int) $rows[0]->id, (int) $resolver->detailsForUserDate($this->employee->id, $this->tenantId, $d1)['user_shift_id']);

        $instances = $resolver->instancesForUserDate($this->employee->id, $this->tenantId, $d1);
        $this->assertSame([$morning->id, $evening->id], $instances->map(fn ($i) => $i['shift']->id)->all());
        $this->assertSame($d1 . ' 22:00', $instances[1]['end']->format('Y-m-d H:i'));

        $assignment = ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('shift_id', $evening->id)->firstOrFail();
        $this->assertTrue($assignment->is_additional);
    }

    public function test_overlapping_additional_shift_is_rejected_and_nothing_is_written(): void
    {
        $d1 = Carbon::today()->addDays(2)->toDateString();
        $d2 = Carbon::today()->addDays(3)->toDateString();
        $night = $this->shift('Night', '22:00', '06:00');
        $early = $this->shift('Early', '05:00', '13:00');
        $late = $this->shift('Late', '14:00', '22:00');

        $this->assign($night, $d1)->assertOk();

        // The night shift of $d1 runs until 06:00 on $d2 — an 05:00 start on $d2 overlaps it.
        $this->assign($early, $d2, null, ['is_additional' => 1])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Night on ' . $d2));
        $this->assertCount(0, $this->rowsOn($d2));
        $this->assertFalse(ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('shift_id', $early->id)->exists());

        // Back to back is fine: Late ends exactly when Night starts.
        $this->assign($late, $d1, null, ['is_additional' => 1])->assertOk();
        $this->assertCount(2, $this->rowsOn($d1));
    }

    public function test_only_one_primary_row_per_day_but_many_additional_rows(): void
    {
        $date = Carbon::today()->addDays(5)->toDateString();
        $a = $this->shift('A', '06:00', '10:00');
        $b = $this->shift('B', '11:00', '15:00');
        $c = $this->shift('C', '16:00', '20:00');
        $base = ['tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => $date, 'status' => 'upcoming', 'created_by' => $this->admin->id];

        UserShift::create($base + ['shift_id' => $a->id]);
        UserShift::create($base + ['shift_id' => $b->id, 'is_additional' => 1]);
        UserShift::create($base + ['shift_id' => $c->id, 'is_additional' => 1]);

        $this->expectException(QueryException::class);
        UserShift::create($base + ['shift_id' => $b->id]);
    }

    public function test_replacing_the_primary_shift_leaves_additional_shifts_alone(): void
    {
        $date = Carbon::today()->addDays(2)->toDateString();
        $morning = $this->shift('Morning', '06:00', '14:00');
        $general = $this->shift('General', '07:00', '13:00');
        $evening = $this->shift('Evening', '15:00', '23:00');

        $this->assign($morning, $date)->assertOk();
        $this->assign($evening, $date, null, ['is_additional' => 1])->assertOk();
        $this->assign($general, $date, null, ['override_existing' => 1])->assertOk();

        $rows = $this->rowsOn($date);
        $this->assertSame([$general->id, $evening->id], $rows->pluck('shift_id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_permanent_additional_shift_can_be_ended_without_touching_the_primary(): void
    {
        $start = Carbon::today()->addDay()->toDateString();
        $morning = $this->shift('Morning', '06:00', '14:00');
        $evening = $this->shift('Evening', '15:00', '23:00');

        $this->assign($morning, $start, null, ['type' => 'permanent'])->assertOk();
        $this->assign($evening, $start, null, ['type' => 'permanent', 'is_additional' => 1])->assertOk();

        $extra = ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('is_additional', 1)->firstOrFail();
        $this->assertSame('permanent', $extra->type);
        $primaryCount = UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('is_additional', 0)->count();
        $this->assertGreaterThan(100, UserShift::withoutGlobalScopes()->where('shift_assignment_id', $extra->id)->count());

        $endOn = Carbon::today()->addDays(10)->toDateString();
        $this->actingAs($this->admin)->postJson(route('shift.assignments.end-permanent', $extra->id), ['end_date' => $endOn])->assertOk();

        $this->assertSame(0, UserShift::withoutGlobalScopes()->where('shift_assignment_id', $extra->id)->where('date', '>', $endOn)->count());
        $this->assertSame($primaryCount, UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('is_additional', 0)->count());
        $this->assertSame('active', ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('is_additional', 0)->value('status'));
    }

    public function test_editing_a_day_into_an_overlapping_shift_is_rejected(): void
    {
        $date = Carbon::today()->addDays(2)->toDateString();
        $morning = $this->shift('Morning', '06:00', '14:00');
        $evening = $this->shift('Evening', '15:00', '23:00');
        $long = $this->shift('Long', '13:00', '21:00');

        $this->assign($morning, $date)->assertOk();
        $this->assign($evening, $date, null, ['is_additional' => 1])->assertOk();
        $primary = $this->rowsOn($date)->first();

        $this->actingAs($this->admin)->postJson(route('shift.update-user-shift'), ['user_shift_id' => $primary->id, 'shift_id' => $long->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Evening'));
        $this->assertSame($morning->id, (int) $primary->fresh()->shift_id);
    }

    public function test_roster_report_and_export_show_every_shift_of_the_day(): void
    {
        $date = Carbon::today()->addDays(2);
        $morning = $this->shift('Morning', '06:00', '14:00');
        $evening = $this->shift('Evening', '15:00', '23:00');
        $this->assign($morning, $date->toDateString())->assertOk();
        $this->assign($evening, $date->toDateString(), null, ['is_additional' => 1])->assertOk();

        $this->actingAs($this->admin)
            ->get(route('shift.roster', ['view' => 'week', 'date' => $date->toDateString(), 'search' => $this->employee->employee_id]))
            ->assertOk()->assertSee($this->tag . ' Morning')->assertSee('+ ' . $this->tag . ' Evening');

        $this->actingAs($this->admin)
            ->get(route('report.attendance.shift-monthly.index', ['month' => $date->format('Y-m'), 'search' => $this->employee->employee_id]))
            ->assertOk()->assertSee('Additional: ' . $this->tag . ' Evening');

        $csv = $this->actingAs($this->admin)
            ->get(route('shift.user-shifts.export', ['from_date' => $date->toDateString(), 'to_date' => $date->toDateString(), 'user_id' => $this->employee->id]))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Additional Shift', $csv);
        $this->assertStringContainsString($this->tag . ' Evening', $csv);
        $this->assertStringContainsString(',Yes', $csv);
    }

    public function test_bulk_assign_groups_consecutive_dates_into_one_history_row(): void
    {
        $shift = $this->shift('Bulk', '09:00', '17:00');
        $dates = [Carbon::today()->addDays(2), Carbon::today()->addDays(3), Carbon::today()->addDays(4)];

        $this->actingAs($this->admin)->postJson(route('shift.user-shifts.assign-bulk'), [
            'user_ids' => [$this->employee->id],
            'shift_id' => $shift->id,
            'dates' => array_map(fn ($d) => $d->toDateString(), $dates),
        ])->assertOk();

        $history = ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame($dates[2]->toDateString(), $history[0]->end_date->toDateString());
    }
}
