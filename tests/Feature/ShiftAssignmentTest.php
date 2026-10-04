<?php

namespace Tests\Feature;

use App\Http\Controllers\Shift\ShiftController;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Attendance\TenantShiftResolver;
use App\Services\Shift\ShiftMaterializer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Permanent / Flexible shift assignment types. Runs against the shared dev DB
 * (no RefreshDatabase) — mirrors BranchManagementTest's tenant-selection and
 * direct-controller-call pattern.
 *
 * Isolation (same pattern as MultiShiftAssignTest): each test works on a
 * throwaway employee created in setUp, every shift it creates is tagged, and
 * tearDown() — which runs even when an assertion fails — deletes all of it and
 * restores the tenant's custom_shifts_enabled + default_shift_id. Previously
 * the cleanup sat at the end of each test, so one failed assertion left rows
 * behind on a real employee and the next run failed on that leftover state.
 */
class ShiftAssignmentTest extends TestCase
{
    private ?bool $originalCustomShiftsEnabled = null;
    private $originalDefaultShiftId = null;
    private int $tenantId;
    private string $tag;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'PHPUnit SA ' . uniqid();

        $this->tenantId = (int) DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');

        $tenant = DB::table('tenants')->where('id', $this->tenantId)->first(['custom_shifts_enabled', 'default_shift_id']);
        $this->originalCustomShiftsEnabled = (bool) $tenant->custom_shifts_enabled;
        $this->originalDefaultShiftId = $tenant->default_shift_id;

        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);

        // A fresh employee with no shift history, so earlier data can't affect the assertions.
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' Employee',
            'email' => 'sa.' . uniqid() . '@phpunit.test',
            'employee_id' => 'SA' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => 'employee',
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
    }

    protected function tearDown(): void
    {
        $assignmentIds = ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->employee->id)->pluck('id');
        UserShift::withoutGlobalScopes()->where('user_id', $this->employee->id)->delete();
        ShiftAssignment::withoutGlobalScopes()->whereIn('id', $assignmentIds)->delete();
        DB::table('user_weekoffs')->where('user_id', $this->employee->id)->delete();
        Shift::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->where('id', $this->employee->id)->forceDelete();

        DB::table('tenants')->where('id', $this->tenantId)->update([
            'custom_shifts_enabled' => (int) $this->originalCustomShiftsEnabled,
            'default_shift_id' => $this->originalDefaultShiftId,
        ]);

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

    private function makeTestShift(string $name, string $start, string $end): Shift
    {
        return Shift::create([
            'tenant_id' => $this->tenantId,
            'name' => $this->tag . ' ' . $name, // tagged so tearDown() can always remove it
            'start_time' => $start,
            'end_time' => $end,
            'total_hours' => abs(Carbon::parse($end)->diffInHours(Carbon::parse($start))),
            'status' => 1,
            'created_by' => Auth::id() ?: $this->actingAdmin()->id,
        ]);
    }

    private function pickTestUser(): User
    {
        return $this->employee;
    }

    /** Request::create() sends no Accept header, so the controller's ajax()/wantsJson() check would otherwise pick the redirect branch. */
    private function jsonRequest(string $uri, array $data): Request
    {
        $request = Request::create($uri, 'POST', $data);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    public function test_assigning_permanent_shift_supersedes_previous_permanent(): void
    {
        $admin = $this->actingAdmin();
        $user = $this->pickTestUser();
        $shiftA = $this->makeTestShift('Test Perm A ' . uniqid(), '06:00', '14:00');
        $shiftB = $this->makeTestShift('Test Perm B ' . uniqid(), '14:00', '22:00');

        $controller = app(ShiftController::class);

        $start1 = Carbon::today();
        $resp1 = $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'permanent',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $shiftA->id,
            'start_date' => $start1->toDateString(),
        ]));
        $this->assertTrue(json_decode($resp1->getContent(), true)['status']);

        $first = ShiftAssignment::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('type', 'permanent')->orderByDesc('id')->first();
        $this->assertSame('active', $first->status);

        $start2 = Carbon::today()->addDays(5);
        $resp2 = $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'permanent',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $shiftB->id,
            'start_date' => $start2->toDateString(),
        ]));
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertTrue($data2['status']);
        $this->assertSame(1, $data2['data']['superseded']);

        $first->refresh();
        $this->assertSame('superseded', $first->status);
        $this->assertSame($start2->copy()->subDay()->toDateString(), $first->end_date->toDateString());

        $second = ShiftAssignment::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('type', 'permanent')->where('status', 'active')->first();
        $this->assertSame($shiftB->id, $second->shift_id);
        $this->assertSame($second->id, $first->superseded_by_id);

        // Before the switch date, cached days still point at the old shift.
        $beforeSwitch = UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('date', $start2->copy()->subDay()->toDateString())->first();
        $this->assertNotNull($beforeSwitch);
        $this->assertSame($shiftA->id, $beforeSwitch->shift_id);

        // From the switch date forward, cached days point at the new shift.
        $afterSwitch = UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('date', $start2->toDateString())->first();
        $this->assertNotNull($afterSwitch);
        $this->assertSame($shiftB->id, $afterSwitch->shift_id);

        // Cleanup.
        UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->whereIn('shift_assignment_id', [$first->id, $second->id])->delete();
        ShiftAssignment::whereIn('id', [$first->id, $second->id])->delete();
        $shiftA->delete();
        $shiftB->delete();
    }

    public function test_flexible_assignment_overrides_permanent_for_its_dates_then_reverts(): void
    {
        $admin = $this->actingAdmin();
        $user = $this->pickTestUser();
        $permanentShift = $this->makeTestShift('Test Perm ' . uniqid(), '06:00', '14:00');
        $flexShift = $this->makeTestShift('Test Flex ' . uniqid(), '14:00', '22:00');

        $controller = app(ShiftController::class);

        $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'permanent',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $permanentShift->id,
            'start_date' => Carbon::today()->toDateString(),
        ]));

        $overrideStart = Carbon::today()->addDays(2);
        $overrideEnd = Carbon::today()->addDays(4);
        $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'flexible',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $flexShift->id,
            'start_date' => $overrideStart->toDateString(),
            'end_date' => $overrideEnd->toDateString(),
        ]));

        $resolver = app(TenantShiftResolver::class);

        // Inside the override window: Flexible wins.
        $resolved = $resolver->forUserDate($user->id, $this->tenantId, $overrideStart->copy()->addDay()->toDateString());
        $this->assertSame($flexShift->id, $resolved->id);

        // Outside the override window, before it: Permanent still wins.
        $before = $resolver->forUserDate($user->id, $this->tenantId, Carbon::today()->toDateString());
        $this->assertSame($permanentShift->id, $before->id);

        // Outside the override window, after it: automatically reverts to Permanent.
        $after = $resolver->forUserDate($user->id, $this->tenantId, $overrideEnd->copy()->addDay()->toDateString());
        $this->assertSame($permanentShift->id, $after->id);

        // Cleanup.
        $assignments = ShiftAssignment::where('tenant_id', $this->tenantId)->where('user_id', $user->id)->get();
        UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->whereIn('shift_assignment_id', $assignments->pluck('id'))->delete();
        ShiftAssignment::whereIn('id', $assignments->pluck('id'))->delete();
        $permanentShift->delete();
        $flexShift->delete();
    }

    public function test_custom_shifts_disabled_path_is_unaffected(): void
    {
        $defaultShift = $this->makeTestShift('Test Default ' . uniqid(), '09:00', '18:00');
        DB::table('tenants')->where('id', $this->tenantId)->update([
            'custom_shifts_enabled' => 0,
            'default_shift_id' => $defaultShift->id,
        ]);

        $resolver = app(TenantShiftResolver::class);
        $user = $this->pickTestUser();

        $resolved = $resolver->forUserDate($user->id, $this->tenantId, Carbon::today()->toDateString());
        $this->assertNotNull($resolved);
        $this->assertSame($defaultShift->id, $resolved->id);

        $defaultShift->delete();
    }

    public function test_roll_horizon_extends_permanent_cache_and_is_idempotent(): void
    {
        $admin = $this->actingAdmin();
        $user = $this->pickTestUser();
        $shift = $this->makeTestShift('Test Horizon ' . uniqid(), '06:00', '14:00');

        $assignment = ShiftAssignment::create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'type' => 'permanent',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => null,
            'status' => 'active',
            'source' => 'manual',
            'created_by' => $admin->id,
        ]);

        $materializer = app(ShiftMaterializer::class);
        // Materialize only a short window, as if the horizon hadn't rolled yet.
        $materializer->materializePermanentHorizon($assignment, Carbon::today(), Carbon::today()->addDays(10));

        $countBefore = UserShift::where('tenant_id', $this->tenantId)->where('shift_assignment_id', $assignment->id)->count();
        $this->assertGreaterThan(0, $countBefore);
        $this->assertLessThan(ShiftMaterializer::PERMANENT_HORIZON_DAYS, $countBefore + 5);

        \Artisan::call('shift:roll-permanent-horizon');

        $farRow = UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('date', Carbon::today()->addDays(ShiftMaterializer::PERMANENT_HORIZON_DAYS - 5)->toDateString())
            ->first();
        $this->assertNotNull($farRow, 'Horizon roll should have extended the cache close to the full window');
        $this->assertSame($shift->id, $farRow->shift_id);

        $countAfterFirstRoll = UserShift::where('tenant_id', $this->tenantId)->where('shift_assignment_id', $assignment->id)->count();
        \Artisan::call('shift:roll-permanent-horizon');
        $countAfterSecondRoll = UserShift::where('tenant_id', $this->tenantId)->where('shift_assignment_id', $assignment->id)->count();
        $this->assertSame($countAfterFirstRoll, $countAfterSecondRoll, 'Rolling the horizon twice should be a no-op the second time');

        // Cleanup.
        UserShift::where('tenant_id', $this->tenantId)->where('shift_assignment_id', $assignment->id)->delete();
        $assignment->delete();
        $shift->delete();
    }

    public function test_overlapping_flexible_requires_override_existing(): void
    {
        $admin = $this->actingAdmin();
        $user = $this->pickTestUser();
        $shiftA = $this->makeTestShift('Test Flex A ' . uniqid(), '06:00', '14:00');
        $shiftB = $this->makeTestShift('Test Flex B ' . uniqid(), '14:00', '22:00');

        $controller = app(ShiftController::class);
        $start = Carbon::today()->addDays(20);
        $end = Carbon::today()->addDays(22);

        $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'flexible',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $shiftA->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));

        // Without override_existing: existing days are left alone.
        $respNoOverride = $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'flexible',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $shiftB->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]));
        $dataNoOverride = json_decode($respNoOverride->getContent(), true);
        $this->assertGreaterThan(0, $dataNoOverride['data']['already_assigned']);

        $stillShiftA = UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('date', $start->toDateString())->first();
        $this->assertSame($shiftA->id, $stillShiftA->shift_id);

        // With override_existing: takes over.
        $controller->assignShift($this->jsonRequest('/x', [
            'type' => 'flexible',
            'assign_type' => 'user',
            'user_ids' => [$user->id],
            'shift_id' => $shiftB->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'override_existing' => true,
        ]));

        $nowShiftB = UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('date', $start->toDateString())->first();
        $this->assertSame($shiftB->id, $nowShiftB->shift_id);

        // Cleanup.
        $assignments = ShiftAssignment::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->where('start_date', '>=', $start->toDateString())->get();
        UserShift::where('tenant_id', $this->tenantId)->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->delete();
        ShiftAssignment::whereIn('id', $assignments->pluck('id'))->delete();
        $shiftA->delete();
        $shiftB->delete();
    }
}
