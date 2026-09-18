<?php

namespace Tests\Feature;

use App\Http\Controllers\AttendanceLocation\AttendanceLocationController;
use App\Models\AttendanceLocation;
use App\Models\User;
use App\Models\UserJobDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Attendance Locations (renamed from Branch, 2026-09-20). Runs against the
 * shared dev DB (no RefreshDatabase) — mirrors EmployeeMultiReportingHeadTest's
 * tenant-selection pattern.
 */
class AttendanceLocationTest extends TestCase
{
    private function tenantId(): int
    {
        return (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 2')
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

    public function test_store_validates_radius_and_geofence_enabled(): void
    {
        $this->actingAdmin();
        $controller = app(AttendanceLocationController::class);

        $req = Request::create('/x', 'POST', [
            'name' => 'Test Location ' . uniqid(),
            'latitude' => 28.6,
            'longitude' => 77.2,
            'radius' => 80,
            'geofence_enabled' => '0',
            'status' => 1,
        ]);

        $resp = $controller->store($req);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success']);

        $location = AttendanceLocation::where('id', $data['data']['id'])->first();
        $this->assertNotNull($location);
        $this->assertSame(80, $location->radius);
        $this->assertFalse($location->geofence_enabled);

        $location->delete();
    }

    public function test_store_rejects_out_of_range_radius(): void
    {
        $this->actingAdmin();
        $controller = app(AttendanceLocationController::class);

        $req = Request::create('/x', 'POST', [
            'name' => 'Bad Radius ' . uniqid(),
            'radius' => 99999,
            'status' => 1,
        ]);

        $resp = $controller->store($req);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_destroy_blocked_while_employee_assigned_then_succeeds_after_unassign(): void
    {
        $this->actingAdmin();
        $controller = app(AttendanceLocationController::class);

        $tenantId = $this->tenantId();
        $location = AttendanceLocation::create([
            'tenant_id' => $tenantId,
            'name' => 'Guard Test Location ' . uniqid(),
            'status' => 1,
            'geofence_enabled' => true,
        ]);

        $employee = User::where('tenant_id', $tenantId)->where('status', 1)->first();
        $jobDetail = UserJobDetail::where('user_id', $employee->id)->first();
        $original = $jobDetail->office_branch;

        $jobDetail->office_branch = $location->id;
        $jobDetail->save();

        $blocked = $controller->destroy($location->id);
        $this->assertSame(422, $blocked->getStatusCode());
        $this->assertNotNull(AttendanceLocation::find($location->id));

        $jobDetail->office_branch = $original;
        $jobDetail->save();

        $allowed = $controller->destroy($location->id);
        $data = json_decode($allowed->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull(AttendanceLocation::find($location->id));
    }
}
