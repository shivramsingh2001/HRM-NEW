<?php

namespace Tests\Feature;

use App\Http\Controllers\User\EmployeeProfileController;
use App\Models\User;
use App\Services\FeatureService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employee 360 page (Phase 1, read-only): `employee.show` + the lazy tabs
 * `employee.profile.tab`. Admin + HR only; only the employee's own company;
 * tabs follow the plan's features. Uses existing dev-DB data (read-only).
 */
class EmployeeProfileTest extends TestCase
{
    private User $admin;
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();

        // The admin's employee with the most attendance rows — the richest data to render.
        $id = DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')
            ->where('attendances.tenant_id', $this->admin->tenant_id)->where('users.role', '!=', 'admin')
            ->groupBy('attendances.user_id')->orderByRaw('COUNT(*) DESC')->value('attendances.user_id');
        if (!$id) {
            $this->markTestSkipped('Needs an employee with attendance in the dev DB.');
        }
        $this->employee = User::withoutGlobalScopes()->findOrFail($id);
    }

    private function tabUrl(string $tab, array $query = [], ?int $userId = null): string
    {
        return route('employee.profile.tab', ['id' => encrypt($userId ?? $this->employee->id), 'tab' => $tab] + $query);
    }

    public function test_the_page_lists_every_enabled_tab(): void
    {
        $res = $this->actingAs($this->admin)->get(route('employee.show', encrypt($this->employee->id)))->assertOk();

        $enabled = app(EmployeeProfileController::class)->enabledTabs();
        $this->assertContains('overview', $enabled);
        foreach (array_diff($enabled, ['overview']) as $tab) {
            $res->assertSee('data-bs-target="#p360-' . $tab . '"', false);
        }
        $res->assertSee('/tab/overview', false); // the id is re-encrypted on every render
    }

    public function test_every_enabled_tab_renders_for_the_employee(): void
    {
        $this->actingAs($this->admin);
        foreach (app(EmployeeProfileController::class)->enabledTabs() as $tab) {
            $res = $this->get($this->tabUrl($tab));
            $this->assertSame(200, $res->getStatusCode(), "tab {$tab}: " . substr(strip_tags((string) $res->getContent()), 0, 300));
        }
    }

    public function test_month_and_year_pickers(): void
    {
        $this->actingAs($this->admin);
        $month = DB::table('attendances')->where('user_id', $this->employee->id)->orderByDesc('date')->value('date');
        $ym = substr((string) $month, 0, 7);

        if (in_array('attendance', app(EmployeeProfileController::class)->enabledTabs(), true)) {
            $this->get($this->tabUrl('attendance', ['month' => $ym]))->assertOk()
                ->assertSee(\Carbon\Carbon::parse($ym . '-01')->format('F Y'));
            $this->get($this->tabUrl('attendance', ['month' => 'bad']))->assertOk(); // falls back to this month
        }
        if (in_array('holidays', app(EmployeeProfileController::class)->enabledTabs(), true)) {
            $this->get($this->tabUrl('holidays', ['year' => 2025]))->assertOk()->assertSee('Holidays 2025');
        }
    }

    public function test_only_admin_and_hr_can_open_it(): void
    {
        $plain = User::withoutGlobalScopes()->where('tenant_id', $this->admin->tenant_id)->where('role', 'employee')->where('status', 1)
            ->where('id', '!=', $this->employee->id)->first();
        if (!$plain) {
            $this->markTestSkipped('Needs a second employee.');
        }

        $this->actingAs($plain)->get(route('employee.show', encrypt($this->employee->id)))->assertForbidden();
        $this->actingAs($plain)->get($this->tabUrl('overview'))->assertForbidden();
    }

    public function test_another_companys_employee_and_unknown_tabs_are_not_found(): void
    {
        $other = User::withoutGlobalScopes()->where('tenant_id', '!=', $this->admin->tenant_id)->whereNotNull('tenant_id')
            ->where('role', '!=', 'admin')->value('id');

        $this->actingAs($this->admin);
        if ($other) {
            $this->get($this->tabUrl('overview', [], (int) $other))->assertNotFound();
        }
        $this->get($this->tabUrl('nonsense'))->assertNotFound();
        $this->get(route('employee.profile.tab', ['id' => 'not-encrypted', 'tab' => 'overview']))->assertNotFound();
    }

    public function test_a_tab_outside_the_plan_is_refused(): void
    {
        $features = $this->mock(FeatureService::class);
        $features->shouldReceive('enabledForCurrentTenant')->andReturnUsing(fn ($key) => $key !== 'leave_management');
        $features->shouldIgnoreMissing(true);

        $this->actingAs($this->admin)->get($this->tabUrl('leave'))->assertForbidden();
        $this->assertNotContains('leave', app(EmployeeProfileController::class)->enabledTabs());
    }
}
