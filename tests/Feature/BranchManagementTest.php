<?php

namespace Tests\Feature;

use App\Http\Controllers\Branch\BranchController;
use App\Models\CompanyBranch;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Branch (organizational, company_branches — 2026-09-20). Runs against the
 * shared dev DB (no RefreshDatabase) — mirrors EmployeeMultiReportingHeadTest's
 * tenant-selection pattern.
 */
class BranchManagementTest extends TestCase
{
    private function tenantId(): int
    {
        return (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
    }

    private function actingAdmin(): User
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        Auth::login($admin);
        Session::put('tenant_id', $tenantId);
        app()->instance('current_tenant', \App\Models\Tenant::find($tenantId));

        return $admin;
    }

    public function test_store_creates_branch_with_profile_fields(): void
    {
        $this->actingAdmin();
        $controller = app(BranchController::class);

        $req = Request::create('/x', 'POST', [
            'name' => 'Test Branch ' . uniqid(),
            'city' => 'Metropolis',
            'state' => 'NY',
            'email' => 'branch@example.com',
            'status' => 1,
        ]);

        $resp = $controller->store($req);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success']);

        $branch = CompanyBranch::where('tenant_id', $this->tenantId())->orderByDesc('id')->first();
        $this->assertSame('Metropolis', $branch->city);
        $this->assertSame('branch@example.com', $branch->email);

        $branch->delete();
    }

    public function test_assign_employees_bulk_updates_branch_id_not_office_branch(): void
    {
        $this->actingAdmin();
        $controller = app(BranchController::class);
        $tenantId = $this->tenantId();

        $branch = CompanyBranch::create([
            'tenant_id' => $tenantId,
            'name' => 'Assign Test Branch ' . uniqid(),
            'status' => 1,
        ]);

        $employee = User::where('tenant_id', $tenantId)->where('status', 1)->first();
        $jobDetail = UserJobDetail::where('user_id', $employee->id)->first();
        $originalBranchId = $jobDetail->branch_id;
        $originalOfficeBranch = $jobDetail->office_branch;

        $req = Request::create('/x', 'POST', [
            'assign_type' => 'user',
            'user_ids' => [$employee->id],
        ]);

        $resp = $controller->assignEmployees($req, encrypt($branch->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['assigned_count']);

        $jobDetail->refresh();
        $this->assertSame($branch->id, $jobDetail->branch_id);
        // Confirms this is a genuinely separate column from office_branch
        // (attendance geofencing) — assigning to a Branch must not touch it.
        $this->assertSame($originalOfficeBranch, $jobDetail->office_branch);

        // cleanup
        $jobDetail->branch_id = $originalBranchId;
        $jobDetail->save();
        $branch->delete();
    }

    public function test_destroy_blocked_while_employee_assigned_then_succeeds_after_unassign(): void
    {
        $this->actingAdmin();
        $controller = app(BranchController::class);
        $tenantId = $this->tenantId();

        $branch = CompanyBranch::create([
            'tenant_id' => $tenantId,
            'name' => 'Guard Test Branch ' . uniqid(),
            'status' => 1,
        ]);

        $employee = User::where('tenant_id', $tenantId)->where('status', 1)->first();
        $jobDetail = UserJobDetail::where('user_id', $employee->id)->first();
        $original = $jobDetail->branch_id;

        $jobDetail->branch_id = $branch->id;
        $jobDetail->save();

        $blocked = $controller->destroy(new Request(), encrypt($branch->id));
        $this->assertSame(422, $blocked->getStatusCode());
        $this->assertNotNull(CompanyBranch::find($branch->id));

        $jobDetail->branch_id = $original;
        $jobDetail->save();

        $allowed = $controller->destroy(new Request(), encrypt($branch->id));
        $data = json_decode($allowed->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull(CompanyBranch::find($branch->id));
    }

    public function test_detail_returns_branch_and_its_members(): void
    {
        $this->actingAdmin();
        $controller = app(BranchController::class);
        $tenantId = $this->tenantId();

        $branch = CompanyBranch::create([
            'tenant_id' => $tenantId,
            'name' => 'Detail Test Branch ' . uniqid(),
            'status' => 1,
        ]);

        $employee = User::where('tenant_id', $tenantId)->where('status', 1)->first();
        $jobDetail = UserJobDetail::where('user_id', $employee->id)->first();
        $original = $jobDetail->branch_id;
        $jobDetail->branch_id = $branch->id;
        $jobDetail->save();

        $view = $controller->detail(new Request(), encrypt($branch->id));
        $viewData = $view->getData();
        $this->assertSame($branch->id, $viewData['branch']->id);
        $this->assertTrue($viewData['users']->contains('id', $employee->id));

        $jobDetail->branch_id = $original;
        $jobDetail->save();
        $branch->delete();
    }
}
