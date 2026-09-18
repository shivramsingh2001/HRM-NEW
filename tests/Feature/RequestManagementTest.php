<?php

namespace Tests\Feature;

use App\Http\Controllers\Attendance\RequestController;
use App\Http\Controllers\Api\Attendance\RequestController as ApiRequestController;
use App\Models\Request;
use App\Models\RequestAttachment;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Travel & WFH "Requests" module improvements (2026-09-17): RBAC scope
 * enforcement (web now matches mobile), soft-cancel semantics, audit
 * logging, attachment bug fixes, and the new mobile update()/cancel()
 * endpoints. Runs against the shared dev DB (no RefreshDatabase) — mirrors
 * ProjectManagementTest's convention.
 */
class RequestManagementTest extends TestCase
{
    private function tenantId(): int
    {
        return (int) DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
    }

    private function loginTenantUser(User $user, int $tenantId): void
    {
        Auth::login($user);
        Session::put('tenant_id', $tenantId);
        app()->instance('current_tenant', \App\Models\Tenant::find($tenantId));
    }

    private function requestTypeId(): int
    {
        return (int) (RequestType::where('type_name', 'WFH')->value('id') ?? RequestType::query()->value('id'));
    }

    /**
     * Create a Request row directly (bypassing controller validation) for
     * setup. tenant_id is NOT fillable on the Request model (only
     * auto-filled by TenantTrait when current_tenant is already bound at
     * creation time), so it's set explicitly here to stay correct
     * regardless of whether the acting user has "logged in" yet.
     */
    private function makeRequest(int $tenantId, int $userId, string $status = 'PENDING', int $startOffsetDays = 10, int $endOffsetDays = 12): Request
    {
        $request = new Request([
            'request_type_id' => $this->requestTypeId(),
            'user_id' => $userId,
            'start_date' => now()->addDays($startOffsetDays)->toDateString(),
            'end_date' => now()->addDays($endOffsetDays)->toDateString(),
            'reason' => 'Test request ' . uniqid(),
            'status' => $status,
            'applied_date' => now(),
        ]);
        $request->tenant_id = $tenantId;
        $request->save();

        return $request;
    }

    private function cleanupRequest(Request $request): void
    {
        DB::table('request_histories')->where('request_id', $request->id)->delete();
        DB::table('audit_logs')->where('entity_type', 'Request')->where('entity_id', $request->id)->delete();
        DB::table('notifications')->where('data', 'like', '%"request_id":' . $request->id . '%')->delete();
        $request->attachments()->delete();
        $request->delete();
    }

    public function test_store_rejects_overlapping_request(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $this->loginTenantUser($employee, $tenantId);
        $controller = app(RequestController::class);

        $req1 = HttpRequest::create('/x', 'POST', [
            'request_type_id' => $this->requestTypeId(),
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'reason' => 'First request ' . uniqid(),
        ]);
        $resp1 = $controller->store($req1);
        $data1 = json_decode($resp1->getContent(), true);
        $this->assertTrue($data1['success'], json_encode($data1));

        $req2 = HttpRequest::create('/x', 'POST', [
            'request_type_id' => $this->requestTypeId(),
            'start_date' => now()->addDays(22)->toDateString(),
            'end_date' => now()->addDays(28)->toDateString(),
            'reason' => 'Overlapping request ' . uniqid(),
        ]);
        $resp2 = $controller->store($req2);
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertFalse($data2['success']);
        $this->assertSame(422, $resp2->getStatusCode());

        $created = Request::where('tenant_id', $tenantId)->where('user_id', $employee->id)->orderByDesc('id')->first();
        $this->cleanupRequest($created);
    }

    public function test_employee_cannot_approve_own_or_others_request(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $target = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->where('id', '!=', $employee->id)->first();

        $request = $this->makeRequest($tenantId, $target->id);

        $this->loginTenantUser($employee, $tenantId);
        $controller = app(RequestController::class);

        $resp = $controller->approve(HttpRequest::create('/x', 'POST', ['comments' => 'nope']), $request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame(403, $resp->getStatusCode());

        $this->cleanupRequest($request);
    }

    public function test_manager_can_only_approve_team_requests(): void
    {
        $tenantId = $this->tenantId();
        $managers = User::where('tenant_id', $tenantId)->where('role', 'manager')->where('status', 1)->get();
        $this->assertGreaterThanOrEqual(2, $managers->count(), 'Need at least 2 managers in this tenant to run this test.');

        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $existingHeadIds = DB::table('user_reporting_heads')->where('user_id', $employee->id)->pluck('reporting_head_id')->toArray();

        $createdPivot = false;
        if (!empty($existingHeadIds)) {
            // Employee already has a real reporting head among these managers — reuse it, don't mutate data.
            $manager1 = $managers->firstWhere('id', $existingHeadIds[0]) ?? User::find($existingHeadIds[0]);
            $manager2 = $managers->first(fn ($m) => !in_array($m->id, $existingHeadIds));
            $this->assertNotNull($manager2, 'Need a manager not already linked to this employee.');
        } else {
            [$manager1, $manager2] = [$managers[0], $managers[1]];
            DB::table('user_reporting_heads')->insert([
                'tenant_id' => $tenantId, 'user_id' => $employee->id, 'reporting_head_id' => $manager1->id,
                'is_primary' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $createdPivot = true;
        }

        $request = $this->makeRequest($tenantId, $employee->id);
        $controller = app(RequestController::class);

        // manager2 (not the reporting head) should be rejected.
        $this->loginTenantUser($manager2, $tenantId);
        $resp = $controller->approve(HttpRequest::create('/x', 'POST', []), $request->id);
        $this->assertSame(403, $resp->getStatusCode());
        $this->assertSame('PENDING', $request->fresh()->status);

        // manager1 (the actual reporting head) should succeed.
        $this->loginTenantUser($manager1, $tenantId);
        $resp = $controller->approve(HttpRequest::create('/x', 'POST', ['comments' => 'Approved for team member']), $request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('APPROVED', $request->fresh()->status);

        if ($createdPivot) {
            DB::table('user_reporting_heads')->where('user_id', $employee->id)->where('reporting_head_id', $manager1->id)->delete();
        }
        $this->cleanupRequest($request);
    }

    public function test_admin_has_company_wide_approve_scope(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();

        $request = $this->makeRequest($tenantId, $employee->id);

        $this->loginTenantUser($admin, $tenantId);
        $controller = app(RequestController::class);

        $resp = $controller->approve(HttpRequest::create('/x', 'POST', ['comments' => 'Admin approve']), $request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('APPROVED', $request->fresh()->status);

        $this->cleanupRequest($request);
    }

    public function test_approve_writes_history_and_audit_row(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $request = $this->makeRequest($tenantId, $employee->id);

        $this->loginTenantUser($admin, $tenantId);
        $controller = app(RequestController::class);
        $controller->approve(HttpRequest::create('/x', 'POST', ['comments' => 'ok']), $request->id);

        $this->assertTrue(
            DB::table('request_histories')->where('request_id', $request->id)->where('action', 'APPROVED')->exists()
        );
        $this->assertTrue(
            DB::table('audit_logs')->where('entity_type', 'Request')->where('entity_id', $request->id)->where('action', 'requests.approved')->exists()
        );

        $this->cleanupRequest($request);
    }

    public function test_reject_requires_reason_and_notifies(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $request = $this->makeRequest($tenantId, $employee->id);

        $this->loginTenantUser($admin, $tenantId);
        $controller = app(RequestController::class);

        // Missing reason -> validation error.
        $resp = $controller->reject(HttpRequest::create('/x', 'POST', []), $request->id);
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame('PENDING', $request->fresh()->status);

        // With reason -> success + notification row + audit row.
        $resp = $controller->reject(HttpRequest::create('/x', 'POST', ['comments' => 'Not enough coverage']), $request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('REJECTED', $request->fresh()->status);

        $this->assertTrue(
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $employee->id)
                ->where('data', 'like', '%"request_id":' . $request->id . '%')
                ->exists(),
            'Employee should receive a database notification for the rejection.'
        );
        $this->assertTrue(
            DB::table('audit_logs')->where('entity_type', 'Request')->where('entity_id', $request->id)->where('action', 'requests.rejected')->exists()
        );

        $this->cleanupRequest($request);
    }

    public function test_cancel_transitions_status_not_hard_delete(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $this->loginTenantUser($employee, $tenantId);
        $controller = app(RequestController::class);

        // PENDING -> CANCELLED
        $pending = $this->makeRequest($tenantId, $employee->id, 'PENDING');
        $resp = $controller->destroy($pending->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertNotNull(Request::find($pending->id), 'Cancelling must not hard-delete the row.');
        $this->assertSame('CANCELLED', $pending->fresh()->status);
        $this->assertTrue(DB::table('request_histories')->where('request_id', $pending->id)->where('action', 'CANCELLED')->exists());

        // APPROVED -> CANCELLED (previously impossible)
        $approved = $this->makeRequest($tenantId, $employee->id, 'APPROVED', 40, 42);
        $resp = $controller->destroy($approved->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('CANCELLED', $approved->fresh()->status);

        $this->cleanupRequest($pending);
        $this->cleanupRequest($approved);
    }

    public function test_delete_attachment_succeeds_for_owner(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $request = $this->makeRequest($tenantId, $employee->id);

        $attachment = new RequestAttachment([
            'request_id' => $request->id,
            'file_name' => 'test.txt',
            'file_path' => 'uploads/requests/attachments/does-not-exist.txt',
            'file_type' => 'text/plain',
            'file_size' => 10,
            'uploaded_by' => $employee->id,
        ]);
        $attachment->tenant_id = $tenantId;
        $attachment->save();

        $this->loginTenantUser($employee, $tenantId);
        $controller = app(RequestController::class);

        $resp = $controller->deleteAttachment($attachment->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertNull(RequestAttachment::find($attachment->id));

        $this->cleanupRequest($request);
    }

    public function test_get_stats_no_sql_error(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $this->loginTenantUser($employee, $tenantId);
        $controller = app(RequestController::class);

        $resp = $controller->getStats();
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertArrayHasKey('pending', $data['data']);
    }

    public function test_bulk_approve_sends_notifications_and_audit_rows(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $employees = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $employees->count());

        $r1 = $this->makeRequest($tenantId, $employees[0]->id);
        $r2 = $this->makeRequest($tenantId, $employees[1]->id);

        $this->loginTenantUser($admin, $tenantId);
        $controller = app(RequestController::class);

        $resp = $controller->bulkApprove(HttpRequest::create('/x', 'POST', [
            'request_ids' => [$r1->id, $r2->id],
        ]));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame(2, $data['data']['success_count']);

        foreach ([$r1, $r2] as $r) {
            $this->assertSame('APPROVED', $r->fresh()->status);
            $this->assertTrue(DB::table('audit_logs')->where('entity_type', 'Request')->where('entity_id', $r->id)->where('action', 'requests.approved')->exists());
            $this->assertTrue(
                DB::table('notifications')->where('data', 'like', '%"request_id":' . $r->id . '%')->exists(),
                'Each bulk-approved request should notify its owner.'
            );
        }

        $this->cleanupRequest($r1);
        $this->cleanupRequest($r2);
    }

    public function test_mobile_update_and_cancel_endpoints(): void
    {
        $tenantId = $this->tenantId();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $this->loginTenantUser($employee, $tenantId);
        $controller = app(ApiRequestController::class);

        $request = $this->makeRequest($tenantId, $employee->id, 'PENDING', 60, 62);

        $updateReq = HttpRequest::create('/x', 'POST', [
            'request_type_id' => $this->requestTypeId(),
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addDays(63)->toDateString(),
            'reason' => 'Updated via mobile API',
        ]);
        $resp = $controller->update($updateReq, $request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('Updated via mobile API', $request->fresh()->reason);

        $resp = $controller->cancel($request->id);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('CANCELLED', $request->fresh()->status);
        $this->assertNotNull(Request::find($request->id));

        $this->cleanupRequest($request);
    }

    public function test_mobile_reject_requires_comments(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $employee = User::where('tenant_id', $tenantId)->where('role', 'employee')->where('status', 1)->first();
        $request = $this->makeRequest($tenantId, $employee->id);

        $this->loginTenantUser($admin, $tenantId);
        $controller = app(ApiRequestController::class);

        // status=REJECTED with no comments should fail validation (comments required for reject).
        $req = HttpRequest::create('/x', 'POST', ['id' => $request->id, 'status' => 'REJECTED']);
        $resp = $controller->updateStatus($req);
        $data = json_decode($resp->getContent(), true);
        $this->assertFalse($data['success'], 'Rejecting via mobile API without comments should fail validation.');
        $this->assertSame('PENDING', $request->fresh()->status);

        // With comments -> succeeds.
        $req2 = HttpRequest::create('/x', 'POST', ['id' => $request->id, 'status' => 'REJECTED', 'comments' => 'No coverage']);
        $resp2 = $controller->updateStatus($req2);
        $data2 = json_decode($resp2->getContent(), true);
        $this->assertTrue($data2['success'], json_encode($data2));
        $this->assertSame('REJECTED', $request->fresh()->status);

        $this->cleanupRequest($request);
    }
}
