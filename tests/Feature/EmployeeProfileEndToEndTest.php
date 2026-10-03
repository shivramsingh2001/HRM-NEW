<?php

namespace Tests\Feature;

use App\Http\Controllers\User\EmployeeProfileActionController;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Employee 360 page (Phase 5): the remaining end-to-end paths — a manager is
 * kept out of every part of the page, and each tab action not covered by
 * EmployeeProfileActionsTest goes through its form's real endpoint and changes
 * only this employee. Runs on the dev DB inside a rolled-back transaction;
 * uploads go to a fake disk.
 */
class EmployeeProfileEndToEndTest extends TestCase
{
    private User $admin;
    private User $employee;
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $id = DB::table('attendances')->join('users', 'users.id', '=', 'attendances.user_id')
            ->where('attendances.tenant_id', $this->tenantId)->where('users.role', '!=', 'admin')
            ->groupBy('attendances.user_id')->orderByRaw('COUNT(*) DESC')->value('attendances.user_id');
        if (!$id) {
            $this->markTestSkipped('Needs an employee with attendance in the dev DB.');
        }
        $this->employee = User::withoutGlobalScopes()->findOrFail($id);

        Storage::fake('uploads');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    private function enc(): string
    {
        return encrypt($this->employee->id);
    }

    private function tabOn(string $tab): bool
    {
        return in_array($tab, app(EmployeeProfileActionController::class)->enabledTabs(), true);
    }

    private function asJson(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_a_manager_cannot_open_any_part_of_the_page(): void
    {
        $manager = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', 1)
            ->where('id', '!=', $this->employee->id)->first();
        if (!$manager) {
            $this->markTestSkipped('Needs a manager.');
        }

        $this->actingAs($manager);
        $this->get(route('employee.show', $this->enc()))->assertForbidden();
        $this->get(route('employee.profile.tab', ['id' => $this->enc(), 'tab' => 'attendance']))->assertForbidden();
        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'bank']))->assertForbidden();
        $this->postJson(route('employee.profile.policy', ['id' => $this->enc(), 'section' => 'attendance']), [])->assertForbidden();
        $this->postJson(route('employee.profile.apply-leave', $this->enc()), [])->assertForbidden();
        $this->postJson(route('employee.profile.weekoffs', $this->enc()), [])->assertForbidden();
    }

    public function test_personal_job_and_address_forms_save_through_the_wizard_steps(): void
    {
        $this->actingAs($this->admin);
        $url = route('employee.update.step', $this->enc());

        $this->post($url, ['step' => 2, 'father_name' => 'P360 Father', 'gender' => 'm', 'nationality' => 'Indian', 'language' => []], $this->asJson())
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('P360 Father', DB::table('user_basic_details')->where('user_id', $this->employee->id)->value('father_name'));

        // Job: the form always sends the full step (incl. the leave types), so nothing is cleared by accident.
        $job = DB::table('user_job_details')->where('user_id', $this->employee->id)->first();
        $leaveTypes = array_map('intval', (array) json_decode($job->leave_assigned ?? '[]', true));
        $this->post($url, array_filter([
            'step' => 3, 'department' => $job->department ?? null, 'designation' => $job->designation ?? null,
            'employment_type' => 'contract', 'type' => $job->type ?: 'office', 'joining_date' => $job->joining_date ?? null,
            'leave_type_assigned' => $leaveTypes ?: null,
        ], fn ($v) => $v !== null), $this->asJson())->assertOk();
        $after = DB::table('user_job_details')->where('user_id', $this->employee->id)->first();
        $this->assertSame('contract', $after->employment_type);
        $this->assertSame($leaveTypes, array_map('intval', (array) json_decode($after->leave_assigned ?? '[]', true)));

        $this->post($url, ['step' => 4, 'current_address' => 'P360 Street 1', 'pin_code' => '110001'], $this->asJson())->assertOk();
        $this->assertSame('P360 Street 1', DB::table('user_locations')->where('user_id', $this->employee->id)->value('address'));
    }

    public function test_upload_and_remove_a_document(): void
    {
        $this->actingAs($this->admin);
        $url = route('employee.update.step', $this->enc());
        $existing = DB::table('employee_documents')->where('user_id', $this->employee->id)->whereNull('deleted_at')->get();

        // What the "Upload" form sends: every kept document as hidden rows, plus the new one.
        $rows = $existing->values()->map(fn ($d) => [
            'id' => $d->id, 'document_type' => $d->document_type, 'document_type_other' => $d->document_type_other, 'document_name' => $d->document_name,
        ])->all();
        $rows[] = ['document_type' => 'other', 'document_type_other' => 'P360 certificate', 'document_name' => 'P360-1',
            'file' => UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')];
        $this->post($url, ['step' => 7, 'documents' => $rows], $this->asJson())->assertOk()->assertJson(['success' => true]);

        $new = DB::table('employee_documents')->where('user_id', $this->employee->id)->whereNull('deleted_at')->where('document_name', 'P360-1')->first();
        $this->assertNotNull($new);
        $this->assertSame($existing->count() + 1, DB::table('employee_documents')->where('user_id', $this->employee->id)->whereNull('deleted_at')->count());

        // The "Remove" form for it: every other document, not this one.
        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'document', 'remove' => $new->id]))->assertOk()->assertSee('Remove document');
        $this->post($url, ['step' => 7, 'documents' => array_slice($rows, 0, -1)], $this->asJson())->assertOk();
        $this->assertNull(DB::table('employee_documents')->where('id', $new->id)->whereNull('deleted_at')->first());
        $this->assertSame($existing->count(), DB::table('employee_documents')->where('user_id', $this->employee->id)->whereNull('deleted_at')->count());
    }

    public function test_deactivate_and_activate(): void
    {
        $this->actingAs($this->admin);
        $before = (int) $this->employee->status;

        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'status']))->assertOk();
        $this->post(route('employee.toggle-status'), ['id' => $this->employee->id, 'status' => $before ? 0 : 1], $this->asJson())->assertOk()->assertJson(['success' => true]);
        $this->assertSame($before ? 0 : 1, (int) DB::table('users')->where('id', $this->employee->id)->value('status'));
    }

    public function test_leave_decisions(): void
    {
        if (!$this->tabOn('leave')) {
            $this->markTestSkipped('Leave is not in this company\'s plan.');
        }
        $this->actingAs($this->admin);
        $type = DB::table('leave_types')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        $leave = fn () => DB::table('leaves')->insertGetId([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'leave_type' => $type,
            'start_date' => '2031-03-03', 'end_date' => '2031-03-03', 'start_session' => 'fullday', 'end_session' => 'fullday',
            'leave_count' => 1, 'total_days' => 1, 'reason' => 'p360 e2e', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $rejected = $leave();
        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'leave-decide', 'leave' => $rejected, 'decision' => 'cancelled']))->assertOk();
        $this->postJson(route('leave.update-status', $rejected), ['status' => 'cancelled', 'remarks' => 'p360 e2e'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('cancelled', DB::table('leaves')->where('id', $rejected)->value('status'));
    }

    public function test_regularization_and_expense_decisions(): void
    {
        $this->actingAs($this->admin);
        $features = app(\App\Services\FeatureService::class);

        if ($features->enabled($this->tenantId, 'regularization') && $this->tabOn('attendance')) {
            $reg = DB::table('attendance_regularizations')->insertGetId([
                'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'date' => now()->subDays(2)->toDateString(),
                'request_type' => 'full_day', 'reason' => 'p360 e2e', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'regularization', 'reg' => $reg, 'decision' => 'rejected']))->assertOk();
            $this->postJson(route('attendance-regularization.approval'), ['id' => $reg, 'status' => 'rejected', 'remarks' => 'p360 e2e'])
                ->assertOk()->assertJson(['success' => true]);
            $this->assertSame('rejected', DB::table('attendance_regularizations')->where('id', $reg)->value('status'));
        }

        $category = DB::table('expense_types')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        if ($this->tabOn('expenses') && $category) {
            $expense = DB::table('expenses')->insertGetId([
                'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'expense_number' => 'P360-' . uniqid(), 'expense_type' => $category,
                'requirement_type' => 'reimbursement', 'amount' => 10, 'paid_amount' => 0, 'date' => now()->toDateString(),
                'status' => 'pending', 'description' => 'p360 e2e', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'expense-decide', 'expense' => $expense, 'decision' => 'cancelled']))->assertOk();
            $this->postJson(route('expense.update-status', $expense), ['status' => 'cancelled', 'remarks' => 'p360 e2e'])->assertOk()->assertJson(['success' => true]);
            $this->assertSame('cancelled', DB::table('expenses')->where('id', $expense)->value('status'));
        }
    }

    public function test_assign_and_return_an_asset(): void
    {
        $asset = DB::table('assets')->where('tenant_id', $this->tenantId)->where('status', 'available')->whereNull('deleted_at')->first();
        if (!$this->tabOn('assets') || !$asset) {
            $this->markTestSkipped('Needs an available asset.');
        }
        $this->actingAs($this->admin);

        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'asset-assign']))->assertOk()->assertSee($asset->asset_code);
        // The form puts the chosen asset's encrypted id into the URL (__ID__).
        $this->postJson(route('assets.assign', encrypt($asset->id)), ['user_id' => $this->employee->id, 'remarks' => 'p360 e2e'])
            ->assertOk()->assertJson(['success' => true]);
        $assignment = DB::table('asset_assignments')->where('asset_id', $asset->id)->where('user_id', $this->employee->id)
            ->where('status', 'pending_acceptance')->orderByDesc('id')->first();
        $this->assertNotNull($assignment);

        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'asset-return', 'assignment' => $assignment->id]))->assertOk();
        $this->postJson(route('assets.assignments.return', encrypt($assignment->id)), ['return_condition' => 'good', 'remarks' => 'p360 e2e'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('returned', DB::table('asset_assignments')->where('id', $assignment->id)->value('status'));
    }

    public function test_end_a_permanent_shift(): void
    {
        if (!$this->tabOn('shift') || !app(\App\Services\Attendance\TenantShiftResolver::class)->isCustomShifts($this->tenantId)) {
            $this->markTestSkipped('Needs a custom-shift company.');
        }
        $shift = DB::table('shifts')->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');
        $this->actingAs($this->admin);

        $this->post(route('shift.assign'), [
            'assign_type' => 'user', 'user_ids' => [$this->employee->id], 'shift_id' => $shift, 'type' => 'permanent', 'start_date' => now()->toDateString(),
        ], $this->asJson())->assertOk()->assertJson(['status' => true]);
        $assignment = DB::table('shift_assignments')->where('tenant_id', $this->tenantId)->where('user_id', $this->employee->id)
            ->where('type', 'permanent')->where('status', 'active')->where('is_additional', 0)->orderByDesc('id')->first();
        $this->assertNotNull($assignment);

        $this->get(route('employee.profile.form', ['id' => $this->enc(), 'form' => 'shift-end', 'assignment' => $assignment->id]))->assertOk();
        $this->postJson(route('shift.assignments.end-permanent', $assignment->id), ['end_date' => now()->toDateString(), 'reason' => 'p360 e2e'])
            ->assertOk()->assertJson(['status' => true]);
        $this->assertNotSame('active', DB::table('shift_assignments')->where('id', $assignment->id)->value('status'));
    }
}
