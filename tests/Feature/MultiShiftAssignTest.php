<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomShiftsEnabled;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\UserShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Assign Shift with several shifts at once (shift_ids[]): the earliest-starting
 * shift becomes the main shift, the rest additional; overlapping picks are
 * refused with nothing saved; one pick / the old shift_id work as before.
 */
class MultiShiftAssignTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;
    private string $tag;
    private int $originalCustom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit MSA ' . uniqid();
        $this->withoutMiddleware([EnsureCustomShiftsEnabled::class, EnsureFeatureEnabled::class]);

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);

        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'msa.' . uniqid() . '@phpunit.test',
            'employee_id' => 'MA' . random_int(100000, 999999),
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

    private function assign(array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('shift.assign'), $extra + [
            'assign_type' => 'user',
            'user_ids' => [$this->employee->id],
            'type' => 'flexible',
            'start_date' => $this->day(),
            'end_date' => $this->day(),
        ]);
    }

    private function day(): string
    {
        return Carbon::today()->addDays(3)->toDateString();
    }

    private function rows()
    {
        return UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->where('date', $this->day())->get();
    }

    public function test_two_shifts_earliest_becomes_main_the_other_additional(): void
    {
        $morning = $this->shift('Morning', '06:00', '14:00');
        $night = $this->shift('Night', '22:00', '06:00');

        // picked in "wrong" order on purpose — the earliest start still wins
        $this->assign(['shift_ids' => [$night->id, $morning->id]])
            ->assertOk()->assertJson(['status' => true, 'data' => ['main_shift_id' => $morning->id, 'additional_shift_ids' => [$night->id]]]);

        $rows = $this->rows()->keyBy('shift_id');
        $this->assertCount(2, $rows);
        $this->assertSame(0, (int) $rows[$morning->id]->is_additional);
        $this->assertSame(1, (int) $rows[$night->id]->is_additional);
    }

    public function test_overlapping_picks_are_refused_and_nothing_is_saved(): void
    {
        $day = $this->shift('Day', '09:00', '18:00');
        $late = $this->shift('Late', '14:00', '22:00');

        $res = $this->assign(['shift_ids' => [$day->id, $late->id]])->assertStatus(422)->assertJson(['status' => false]);
        $this->assertStringContainsString('overlap', $res->json('message'));
        $this->assertCount(0, $this->rows());
        $this->assertSame(0, ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->count());
    }

    public function test_back_to_back_shifts_are_allowed(): void
    {
        $first = $this->shift('First', '06:00', '14:00');
        $second = $this->shift('Second', '14:00', '22:00');

        $this->assign(['shift_ids' => [$first->id, $second->id]])->assertOk();
        $this->assertCount(2, $this->rows());
    }

    public function test_with_additional_ticked_every_pick_is_additional(): void
    {
        $main = $this->shift('Main', '09:00', '17:00');
        $this->assign(['shift_id' => $main->id])->assertOk();

        $early = $this->shift('Early', '05:00', '08:00');
        $evening = $this->shift('Evening', '18:00', '21:00');
        $this->assign(['shift_ids' => [$early->id, $evening->id], 'is_additional' => 1])
            ->assertOk()->assertJson(['data' => ['main_shift_id' => null]]);

        $rows = $this->rows()->keyBy('shift_id');
        $this->assertSame(0, (int) $rows[$main->id]->is_additional);     // main shift untouched
        $this->assertSame(1, (int) $rows[$early->id]->is_additional);
        $this->assertSame(1, (int) $rows[$evening->id]->is_additional);
    }

    public function test_an_additional_pick_that_overlaps_an_existing_shift_saves_nothing(): void
    {
        $existing = $this->shift('Existing', '18:00', '23:00');
        $this->assign(['shift_id' => $existing->id])->assertOk();
        $other = $this->shift('Other', '07:00', '12:00');
        $this->assign(['shift_id' => $other->id, 'is_additional' => 1])->assertOk();

        // a new main shift (earliest start, replacing the old one) + an extra that clashes with
        // the employee's existing additional shift (07–12)
        $newMain = $this->shift('NewMain', '04:00', '06:30');
        $clashing = $this->shift('Clashing', '09:00', '11:00');
        $this->assign(['shift_ids' => [$clashing->id, $newMain->id], 'override_existing' => 1])
            ->assertStatus(422)->assertJson(['status' => false]);

        // rolled back: the main shift was not replaced either
        $rows = $this->rows();
        $this->assertSame($existing->id, (int) $rows->where('is_additional', 0)->first()->shift_id);
        $this->assertNotContains($newMain->id, $rows->pluck('shift_id')->all());
        $this->assertNotContains($clashing->id, $rows->pluck('shift_id')->all());
    }

    public function test_one_pick_and_the_old_shift_id_still_work(): void
    {
        $a = $this->shift('Solo', '09:00', '17:00');
        $this->assign(['shift_ids' => [$a->id]])->assertOk()->assertJson(['status' => true]);
        $this->assertSame([$a->id], $this->rows()->pluck('shift_id')->all());

        $b = $this->shift('Solo2', '10:00', '18:00');
        $this->assign(['shift_id' => $b->id, 'override_existing' => 1])->assertOk();
        $this->assertSame([$b->id], $this->rows()->where('is_additional', 0)->pluck('shift_id')->values()->all());
    }
}
