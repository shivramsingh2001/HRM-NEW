<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * My Leaves (leave.view): Apply / Edit leave is a side drawer on the list page.
 * The old /leave/apply and /leave/update/{id} URLs only open it, and a refused
 * submit comes back with the drawer's mode so it reopens. Runs on the dev DB —
 * every test is wrapped in a transaction that is rolled back.
 */
class LeaveDrawerTest extends TestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $id = DB::table('leaves')->join('users', 'users.id', '=', 'leaves.user_id')
            ->where('leaves.status', 'pending')->where('users.status', 1)->whereNotNull('users.tenant_id')
            ->orderBy('leaves.user_id')->value('leaves.user_id');
        if (! $id) {
            $this->markTestSkipped('Needs an employee with a pending leave in the dev DB.');
        }
        $this->employee = User::withoutGlobalScopes()->findOrFail($id);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_old_apply_and_edit_urls_open_the_drawer_on_my_leaves(): void
    {
        $this->actingAs($this->employee);

        $this->get(route('leave.apply', ['date' => '2026-10-23']))
            ->assertRedirect(route('leave.view', ['apply' => 1, 'date' => '2026-10-23']));

        $leaveId = DB::table('leaves')->where('user_id', $this->employee->id)->where('status', 'pending')->value('id');
        $this->get(route('leave.update', ['id' => encrypt($leaveId)]))
            ->assertRedirect(route('leave.view', ['edit' => $leaveId]));

        // Someone else's leave is not opened.
        $other = DB::table('leaves')->where('user_id', '!=', $this->employee->id)->value('id');
        if ($other) {
            $this->get(route('leave.update', ['id' => encrypt($other)]))
                ->assertRedirect(route('leave.view'))->assertSessionHas('error');
        }
    }

    public function test_list_page_has_the_drawer_with_balances_and_edit_data(): void
    {
        $leaveId = DB::table('leaves')->where('user_id', $this->employee->id)->where('status', 'pending')->value('id');

        $this->actingAs($this->employee)->get(route('leave.view', ['apply' => 1, 'date' => '2026-10-23']))
            ->assertOk()
            ->assertSee('id="leaveDrawer"', false)
            ->assertSee('data-balance=', false)
            ->assertSee('data-id="'.$leaveId.'"', false)
            ->assertSee('value="2026-10-23"', false);
    }

    public function test_a_refused_submit_comes_back_with_the_drawer_mode(): void
    {
        $typeId = DB::table('leave_types')->where('tenant_id', $this->employee->tenant_id)->where('status', 1)->value('id');

        $this->actingAs($this->employee)->from(route('leave.view'))->post(route('leave.apply-store'), [
            '_leave_mode' => 'apply', 'leave_type' => $typeId,
            'start_date' => '2026-12-10', 'start_session' => 'fullday',
            'end_date' => '2026-12-09', 'end_session' => 'fullday', 'reason' => 'drawer test',
        ])->assertRedirect(route('leave.view'))
            ->assertSessionHas('error', 'End date cannot be before start date.')
            ->assertSessionHasInput('_leave_mode', 'apply');

        $this->assertFalse(DB::table('leaves')->where('reason', 'drawer test')->exists());
    }
}
