<?php

namespace Tests\Feature;

use App\Models\ApprovalAction;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\AttendanceRegularization;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use Tests\TestCase;

/**
 * Tier 2 / T2-A — two-level chain: level 1 advances, level 2 finalises;
 * a non-approver is rejected; no workflow => engine is a no-op.
 */
class ApprovalWorkflowTest extends TestCase
{
    private function tenantId(): int
    {
        return (int) (\DB::table('users')->value('tenant_id') ?: 1);
    }

    private function users(int $n): array
    {
        return User::where('tenant_id', $this->tenantId())->limit($n)->pluck('id')->all();
    }

    /**
     * This suite runs against the shared dev DB (no RefreshDatabase), so purge
     * any workflow rows for the tenant+type before each scenario — otherwise a
     * leftover from a failed run makes open() pick the wrong workflow.
     */
    private function clearWorkflows(string $type): void
    {
        $ids = \DB::table('approval_workflows')
            ->where('tenant_id', $this->tenantId())->where('request_type', $type)->pluck('id');
        $reqIds = \DB::table('approval_requests')->whereIn('workflow_id', $ids)->pluck('id');
        \DB::table('approval_actions')->whereIn('approval_request_id', $reqIds)->delete();
        \DB::table('approval_requests')->whereIn('id', $reqIds)->delete();
        \DB::table('approval_workflow_steps')->whereIn('workflow_id', $ids)->delete();
        \DB::table('approval_workflows')->whereIn('id', $ids)->delete();
    }

    public function test_no_workflow_returns_null(): void
    {
        $svc = app(ApprovalService::class);
        $reg = new AttendanceRegularization(['tenant_id' => $this->tenantId(), 'user_id' => $this->users(1)[0]]);
        $actor = User::find($this->users(1)[0]);

        $this->clearWorkflows('leave');

        // 'leave' has no configured workflow -> engine opts out.
        $this->assertNull($svc->open('leave', $reg, $actor));
    }

    public function test_two_level_chain_advances_then_finalises(): void
    {
        [$subjectUser, $lvl1, $lvl2] = array_pad($this->users(3), 3, $this->users(1)[0]);
        $tenantId = $this->tenantId();
        $this->clearWorkflows('overtime');

        $wf = ApprovalWorkflow::create([
            'tenant_id' => $tenantId, 'request_type' => 'overtime',
            'name' => 'phpunit ' . uniqid(), 'is_active' => true,
        ]);
        ApprovalWorkflowStep::create(['workflow_id' => $wf->id, 'level' => 1, 'approver_type' => 'user', 'approver_ref' => $lvl1, 'quorum' => 'any', 'on_breach' => 'notify']);
        ApprovalWorkflowStep::create(['workflow_id' => $wf->id, 'level' => 2, 'approver_type' => 'user', 'approver_ref' => $lvl2, 'quorum' => 'any', 'on_breach' => 'notify']);

        // Fake subject: reuse the regularization model as a generic carrier.
        $reg = AttendanceRegularization::create([
            'tenant_id' => $tenantId, 'user_id' => $subjectUser,
            'date' => now()->subDay()->toDateString(), 'request_type' => 'full_day',
            'reason' => 'phpunit', 'status' => 'pending',
        ]);

        $svc = app(ApprovalService::class);
        $request = $svc->open('overtime', $reg, User::find($subjectUser));
        $this->assertNotNull($request);
        $this->assertSame(1, $request->current_level);

        // A stranger cannot act.
        $stranger = User::where('tenant_id', $tenantId)->whereNotIn('id', [$lvl1, $lvl2])->first();
        if ($stranger) {
            try {
                $svc->act($request, $stranger, 'approved');
                $this->fail('stranger should not be able to approve');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsStringIgnoringCase('not an approver', $e->getMessage());
            }
        }

        $svc->act($request->fresh(), User::find($lvl1), 'approved');
        $this->assertSame(2, $request->fresh()->current_level);
        $this->assertSame('pending', $request->fresh()->status);

        $svc->act($request->fresh(), User::find($lvl2), 'approved');
        $this->assertSame('approved', $request->fresh()->status);

        // cleanup
        $reg->forceDelete();
        \DB::table('approval_actions')->where('approval_request_id', $request->id)->delete();
        $request->delete();
        $wf->steps()->delete();
        $wf->delete();
    }

    public function test_overtime_handler_finalises_the_request(): void
    {
        $tenantId = $this->tenantId();
        [$subjectUser, $approver] = array_pad($this->users(2), 2, $this->users(1)[0]);

        $wf = ApprovalWorkflow::create([
            'tenant_id' => $tenantId, 'request_type' => 'overtime',
            'name' => 'phpunit ot ' . uniqid(), 'is_active' => true,
        ]);
        ApprovalWorkflowStep::create(['workflow_id' => $wf->id, 'level' => 1, 'approver_type' => 'user', 'approver_ref' => $approver, 'quorum' => 'any', 'on_breach' => 'notify']);

        $ot = OvertimeRequest::create([
            'tenant_id' => $tenantId, 'user_id' => $subjectUser,
            'date' => now()->subDay()->toDateString(), 'overtime_hours' => 3.0,
            'reason' => 'phpunit', 'status' => 'pending',
        ]);

        $svc = app(ApprovalService::class);
        $ar = $svc->decide('overtime', $ot, User::find($approver), 'approved', 'ok');

        $this->assertNotNull($ar);
        $this->assertSame('approved', $ar->status);

        $ot->refresh();
        $this->assertSame('approved', $ot->status);
        $this->assertEquals(3.0, (float) $ot->approved_hours);
        $this->assertSame($approver, $ot->approved_by);

        // cleanup
        ApprovalAction::where('approval_request_id', $ar->id)->delete();
        $ar->delete();
        $ot->forceDelete();
        $wf->steps()->delete();
        $wf->delete();
    }

    public function test_sla_tick_auto_approves_a_stale_level(): void
    {
        $tenantId = $this->tenantId();
        [$subjectUser, $approver] = array_pad($this->users(2), 2, $this->users(1)[0]);

        $wf = ApprovalWorkflow::create([
            'tenant_id' => $tenantId, 'request_type' => 'overtime',
            'name' => 'phpunit sla ' . uniqid(), 'is_active' => true,
        ]);
        ApprovalWorkflowStep::create(['workflow_id' => $wf->id, 'level' => 1, 'approver_type' => 'user', 'approver_ref' => $approver, 'quorum' => 'any', 'sla_hours' => 1, 'on_breach' => 'auto_approve']);

        $ot = OvertimeRequest::create([
            'tenant_id' => $tenantId, 'user_id' => $subjectUser,
            'date' => now()->subDay()->toDateString(), 'overtime_hours' => 2.0,
            'reason' => 'phpunit', 'status' => 'pending',
        ]);

        $svc = app(ApprovalService::class);
        $ar = $svc->open('overtime', $ot, User::find($subjectUser));
        // backdate so the SLA is breached (raw update — bypass auto timestamps)
        \DB::table('approval_requests')->where('id', $ar->id)->update(['updated_at' => now()->subHours(5)]);

        $processed = $svc->tick();
        $this->assertGreaterThanOrEqual(1, $processed);
        $this->assertContains($ar->fresh()->status, ['approved', 'auto_approved']);
        $this->assertSame('approved', $ot->fresh()->status);

        // cleanup
        ApprovalAction::where('approval_request_id', $ar->id)->delete();
        $ar->delete();
        $ot->forceDelete();
        $wf->steps()->delete();
        $wf->delete();
    }
}
