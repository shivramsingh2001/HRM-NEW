<?php

namespace Tests\Feature;

use App\Http\Controllers\Project\ProjectController;
use App\Models\Project;
use App\Models\ProjectAssign;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectComment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Project Management overhaul (2026-09-21): priority/budget, permission
 * enforcement, progress manual-override, and the new updates/comments/
 * milestones/risks sub-resources. Runs against the shared dev DB (no
 * RefreshDatabase) — mirrors BranchManagementTest's tenant-selection pattern.
 */
class ProjectManagementTest extends TestCase
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

    private function loginTenantUser(User $user, int $tenantId): void
    {
        Auth::login($user);
        Session::put('tenant_id', $tenantId);
        app()->instance('current_tenant', \App\Models\Tenant::find($tenantId));
    }

    private function actingAdmin(): User
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $this->loginTenantUser($admin, $tenantId);

        return $admin;
    }

    private function makeProject(int $tenantId, array $memberIds, int $headId): Project
    {
        $project = Project::create([
            'tenant_id' => $tenantId,
            'name' => 'Test Project ' . uniqid(),
            'project_code' => 'PT' . rand(10000, 99999),
            'start_date' => now()->toDateString(),
            'deadline_date' => now()->addMonth()->toDateString(),
            'project_head' => $headId,
            'status' => 'ongoing',
            'priority' => 'medium',
        ]);

        foreach (array_unique(array_merge($memberIds, [$headId])) as $userId) {
            ProjectAssign::create([
                'tenant_id' => $tenantId,
                'project_id' => $project->id,
                'user_id' => $userId,
                'is_head' => $userId == $headId ? 1 : 0,
                'status' => 1,
            ]);
        }

        return $project;
    }

    public function test_store_creates_project_with_priority_and_budget(): void
    {
        $this->actingAdmin();
        $tenantId = $this->tenantId();
        $controller = app(ProjectController::class);

        $members = User::where('tenant_id', $tenantId)->where('status', 1)->limit(2)->pluck('id')->toArray();
        $head = $members[0];

        $req = Request::create('/x', 'POST', [
            'name' => 'Store Test Project ' . uniqid(),
            'start_date' => now()->toDateString(),
            'deadline_date' => now()->addMonth()->toDateString(),
            'project_head' => $head,
            'priority' => 'high',
            'budget' => 5000,
            'member' => $members,
        ]);

        $resp = $controller->store($req);
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));

        $project = Project::where('tenant_id', $tenantId)->orderByDesc('id')->first();
        $this->assertSame('high', $project->priority);
        $this->assertSame('5000.00', (string) $project->budget);
        $this->assertSame('ongoing', $project->status);

        $project->assigns()->delete();
        $project->delete();
    }

    public function test_update_rejected_for_non_member_but_allowed_for_team_member(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $this->loginTenantUser($admin, $tenantId);

        // Only admin/hr/manager have an 'edit' grant on projects at all
        // (config/rbac.php — employee is 'view' => 'own' only), so both
        // users here must be managers for this to exercise the record-level
        // (team-membership) check rather than the module-level gate.
        $managers = User::where('tenant_id', $tenantId)->where('role', 'manager')->where('status', 1)->limit(2)->get();
        $this->assertGreaterThanOrEqual(2, $managers->count(), 'Need at least 2 managers in this tenant to run this test.');
        [$member, $outsider] = [$managers[0], $managers[1]];

        $project = $this->makeProject($tenantId, [$member->id], $member->id);
        $controller = app(ProjectController::class);

        // Outsider (not head, not a team member) — 'own' scope should reject.
        $this->loginTenantUser($outsider, $tenantId);
        $req = Request::create('/x', 'POST', [
            'name' => $project->name,
            'start_date' => $project->start_date->toDateString(),
            'deadline_date' => $project->deadline_date->toDateString(),
            'project_head' => $member->id,
            'status' => 'hold',
            'priority' => 'high',
            'member' => [$member->id],
        ]);
        $resp = $controller->update($req, encrypt($project->id));
        $this->assertSame(403, $resp->getStatusCode());
        $this->assertSame('ongoing', $project->fresh()->status);

        // The project head (a team member) — should succeed and write an audit row.
        $this->loginTenantUser($member, $tenantId);
        $auditCountBefore = \DB::table('audit_logs')->where('entity_type', 'Project')->where('entity_id', $project->id)->count();

        $resp = $controller->update($req, encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $this->assertSame('hold', $project->fresh()->status);

        $auditCountAfter = \DB::table('audit_logs')->where('entity_type', 'Project')->where('entity_id', $project->id)->count();
        $this->assertGreaterThan($auditCountBefore, $auditCountAfter);

        $project->assigns()->delete();
        $project->delete();
    }

    public function test_manual_progress_override_survives_task_save_then_reset_resyncs(): void
    {
        $tenantId = $this->tenantId();
        $admin = User::where('tenant_id', $tenantId)->where('role', 'admin')->first();
        $this->loginTenantUser($admin, $tenantId);

        $project = $this->makeProject($tenantId, [$admin->id], $admin->id);
        $controller = app(ProjectController::class);

        // A completed task should normally push progress toward 100 via the observer.
        $task = Task::create([
            'tenant_id' => $tenantId,
            'title' => 'Obs Test Task ' . uniqid(),
            'deadline_date' => now()->addWeek()->toDateString(),
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        $this->assertSame(100, $project->fresh()->progress_percentage);

        // PM manually reports a lower percentage via storeUpdate.
        $req = Request::create('/x', 'POST', ['reported_progress_percentage' => 40, 'notes' => 'Manual check-in']);
        $resp = $controller->storeUpdate($req, encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));

        $project->refresh();
        $this->assertSame(40, $project->progress_percentage);
        $this->assertTrue((bool) $project->progress_manual_override);

        // Another task save must NOT overwrite the manual value (observer guard).
        $task2 = Task::create([
            'tenant_id' => $tenantId,
            'title' => 'Obs Test Task 2 ' . uniqid(),
            'deadline_date' => now()->addWeek()->toDateString(),
            'project_id' => $project->id,
            'status' => 'pending',
        ]);
        $this->assertSame(40, $project->fresh()->progress_percentage, 'Observer must skip recalculation while progress_manual_override is true.');

        // resetProgress() clears the flag and immediately re-syncs from tasks.
        $resp = $controller->resetProgress(encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $project->refresh();
        $this->assertFalse((bool) $project->progress_manual_override);
        $this->assertSame(50, $project->progress_percentage); // 1 of 2 tasks completed

        $task->delete();
        $task2->delete();
        $project->assigns()->delete();
        $project->delete();
    }

    public function test_milestone_and_risk_crud_round_trip(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeProject($tenantId, [$admin->id], $admin->id);
        $controller = app(ProjectController::class);

        // Milestone: store -> complete -> destroy.
        $resp = $controller->storeMilestone(Request::create('/x', 'POST', [
            'title' => 'Kickoff', 'due_date' => now()->addWeek()->toDateString(),
        ]), encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $milestoneId = $data['data']['id'];

        $resp = $controller->updateMilestone(Request::create('/x', 'POST', ['status' => 'completed']), $milestoneId);
        $this->assertTrue(json_decode($resp->getContent(), true)['success']);
        $this->assertSame('completed', ProjectMilestone::find($milestoneId)->status);
        $this->assertNotNull(ProjectMilestone::find($milestoneId)->completed_at);

        $resp = $controller->destroyMilestone($milestoneId);
        $this->assertTrue(json_decode($resp->getContent(), true)['success']);
        $this->assertNull(ProjectMilestone::find($milestoneId));

        // Risk: store -> resolve -> destroy.
        $resp = $controller->storeRisk(Request::create('/x', 'POST', [
            'type' => 'blocker', 'title' => 'Vendor delay', 'severity' => 'high',
        ]), encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $riskId = $data['data']['id'];

        $resp = $controller->updateRisk(Request::create('/x', 'POST', ['status' => 'resolved']), $riskId);
        $this->assertTrue(json_decode($resp->getContent(), true)['success']);
        $risk = ProjectRisk::find($riskId);
        $this->assertSame('resolved', $risk->status);
        $this->assertNotNull($risk->resolved_at);

        $resp = $controller->destroyRisk($riskId);
        $this->assertTrue(json_decode($resp->getContent(), true)['success']);
        $this->assertNull(ProjectRisk::find($riskId));

        $project->assigns()->delete();
        $project->delete();
    }

    public function test_comment_store_and_destroy_round_trip(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeProject($tenantId, [$admin->id], $admin->id);
        $controller = app(ProjectController::class);

        $resp = $controller->storeComment(Request::create('/x', 'POST', ['comment' => 'Looks good so far.']), encrypt($project->id));
        $data = json_decode($resp->getContent(), true);
        $this->assertTrue($data['success'], json_encode($data));
        $commentId = $data['data']['id'];
        $this->assertNotNull(ProjectComment::find($commentId));

        $resp = $controller->destroyComment($commentId);
        $this->assertTrue(json_decode($resp->getContent(), true)['success']);
        $this->assertNull(ProjectComment::find($commentId));

        $project->assigns()->delete();
        $project->delete();
    }
}
