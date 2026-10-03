<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomShiftsEnabled;
use App\Http\Requests\UpdateShiftSettingsRequest;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * "Overnight shift (ends next day)" checkbox on Shifts create/edit
 * (ShiftController + ShiftWindow::timesError) and per-company unique shift
 * names. Shared dev DB — every shift created here is removed in tearDown.
 */
class ShiftOvernightTest extends TestCase
{
    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit ' . uniqid();
        // Plan/toggle gating is covered elsewhere; this test is about the shift form.
        $this->withoutMiddleware(EnsureCustomShiftsEnabled::class);
    }

    protected function tearDown(): void
    {
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        parent::tearDown();
    }

    private function admins(int $count): array
    {
        $admins = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')
            ->orderBy('id')->get()->unique('tenant_id')->take($count)->values()->all();
        if (count($admins) < $count) {
            $this->markTestSkipped("Needs {$count} tenants with an admin in the dev DB.");
        }

        return $admins;
    }

    private function storeShift(User $admin, array $data)
    {
        return $this->actingAs($admin)->postJson(route('shift.store'), $data + [
            'name' => $this->tag . ' Night',
            'grace_minutes' => 10,
            'break_time' => 30,
        ]);
    }

    public function test_overnight_checkbox_is_saved_and_hours_cross_midnight(): void
    {
        [$admin] = $this->admins(1);

        $this->storeShift($admin, ['start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => 1])
            ->assertCreated();

        $shift = Shift::withoutGlobalScopes()->where('name', $this->tag . ' Night')->firstOrFail();
        $this->assertTrue($shift->is_overnight);
        $this->assertSame(7.5, (float) $shift->total_hours);
        $this->assertSame((int) $admin->tenant_id, (int) $shift->tenant_id);
    }

    public function test_day_shift_without_checkbox_is_saved_as_not_overnight(): void
    {
        [$admin] = $this->admins(1);

        $this->storeShift($admin, ['start_time' => '09:00', 'end_time' => '18:00'])->assertCreated();

        $shift = Shift::withoutGlobalScopes()->where('name', $this->tag . ' Night')->firstOrFail();
        $this->assertFalse($shift->is_overnight);
        $this->assertSame(8.5, (float) $shift->total_hours);
    }

    public function test_times_that_disagree_with_the_checkbox_are_rejected(): void
    {
        [$admin] = $this->admins(1);

        $this->storeShift($admin, ['start_time' => '22:00', 'end_time' => '06:00'])
            ->assertStatus(422)->assertJsonPath('errors.end_time.0', fn ($m) => str_contains($m, 'Overnight shift'));
        $this->storeShift($admin, ['start_time' => '09:00', 'end_time' => '18:00', 'is_overnight' => 1])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['end_time']]);

        $this->assertFalse(Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->exists());
    }

    public function test_edit_can_turn_a_shift_into_an_overnight_one(): void
    {
        [$admin] = $this->admins(1);
        $this->storeShift($admin, ['start_time' => '09:00', 'end_time' => '18:00'])->assertCreated();
        $shift = Shift::withoutGlobalScopes()->where('name', $this->tag . ' Night')->firstOrFail();

        $this->actingAs($admin)->postJson(route('shift.update', $shift->id), [
            'name' => $shift->name, 'start_time' => '20:00', 'end_time' => '08:00', 'is_overnight' => 1,
        ])->assertOk();

        $shift->refresh();
        $this->assertTrue($shift->is_overnight);
        $this->assertSame(12.0, (float) $shift->total_hours);
    }

    public function test_shift_name_is_unique_per_company_not_globally(): void
    {
        [$a, $b] = $this->admins(2);
        $day = ['start_time' => '09:00', 'end_time' => '18:00'];

        $this->storeShift($a, $day)->assertCreated();
        $this->storeShift($b, $day)->assertCreated();
        $this->storeShift($a, $day)->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);

        $this->assertSame(2, Shift::withoutGlobalScopes()->where('name', $this->tag . ' Night')->count());
    }

    public function test_shift_screens_show_the_overnight_checkbox(): void
    {
        [$admin] = $this->admins(1);
        $this->storeShift($admin, ['start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => 1])->assertCreated();

        $this->actingAs($admin)->get(route('shift.index'))->assertOk()
            ->assertSee('Overnight shift (ends next day)')
            ->assertSee('data-is_overnight="1"', false);

        $this->actingAs($admin)->get(route('shift-settings.index'))->assertOk()
            ->assertSee('name="is_overnight"', false);
    }

    public function test_fixed_company_shift_settings_apply_the_same_rule(): void
    {
        [$admin] = $this->admins(1);

        $validate = function (array $data) use ($admin) {
            $request = UpdateShiftSettingsRequest::create('/shift-settings', 'PUT', $data + ['custom_shifts_enabled' => 0]);
            $request->setContainer($this->app)->setRedirector($this->app['redirect']);
            $request->setUserResolver(fn () => $admin);
            $request->validateResolved();
        };

        $validate(['start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => 1]);
        $validate(['start_time' => '09:00', 'end_time' => '18:00']);

        try {
            $validate(['start_time' => '22:00', 'end_time' => '06:00']);
            $this->fail('Overnight times without the checkbox must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('end_time', $e->errors());
        }
    }
}
