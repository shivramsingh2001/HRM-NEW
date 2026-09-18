<?php

namespace Tests\Feature;

use App\Http\Controllers\Report\ProjectReportController;
use App\Models\Project;
use App\Models\ProjectAssign;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * The 4 new Project Reports (Summary, Progress, Task & Employee Performance,
 * Timeline/Overdue), added alongside the Project Management overhaul
 * (2026-09-21). Runs against the shared dev DB (no RefreshDatabase) —
 * mirrors ProjectManagementTest/BranchManagementTest's pattern.
 */
class ProjectReportsTest extends TestCase
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

    /** An overdue project (deadline yesterday, still "ongoing") with one completed + one pending task, a pending milestone, and one team member. */
    private function makeOverdueProjectWithTasks(int $tenantId, int $headId): Project
    {
        $project = Project::create([
            'tenant_id' => $tenantId,
            'name' => 'Report Test Project ' . uniqid(),
            'project_code' => 'RT' . rand(10000, 99999),
            'start_date' => now()->subMonth()->toDateString(),
            'deadline_date' => now()->subDay()->toDateString(),
            'project_head' => $headId,
            'status' => 'ongoing',
            'priority' => 'high',
            'budget' => 1000,
        ]);

        ProjectAssign::create([
            'tenant_id' => $tenantId, 'project_id' => $project->id, 'user_id' => $headId, 'is_head' => 1, 'status' => 1,
        ]);

        Task::create([
            'tenant_id' => $tenantId, 'title' => 'Rpt Task Done ' . uniqid(),
            'deadline_date' => now()->subWeek()->toDateString(), 'project_id' => $project->id, 'status' => 'completed',
        ]);
        Task::create([
            'tenant_id' => $tenantId, 'title' => 'Rpt Task Pending ' . uniqid(),
            'deadline_date' => now()->addWeek()->toDateString(), 'project_id' => $project->id, 'status' => 'pending',
        ]);

        ProjectMilestone::create([
            'tenant_id' => $tenantId, 'project_id' => $project->id,
            'title' => 'Upcoming Milestone', 'due_date' => now()->addWeek()->toDateString(), 'status' => 'pending',
        ]);

        return $project;
    }

    public function test_summary_report_index_and_export_include_the_test_project(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeOverdueProjectWithTasks($tenantId, $admin->id);
        $controller = app(ProjectReportController::class);

        $view = $controller->summary(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $rows = $view->getData()['projects'];
        $this->assertTrue(collect($rows)->contains(fn ($r) => $r->id === $project->id));
        $row = collect($rows)->firstWhere('id', $project->id);
        $this->assertSame(2, $row->tasks_total);
        $this->assertSame(1, $row->tasks_completed);
        $this->assertSame(1, $row->team_size);
        $this->assertSame('1000.00', (string) $row->budget);

        $export = $controller->summaryExport(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));
        $this->assertStringContainsString($project->project_code, $export->getContent());

        $project->assigns()->delete();
        $project->tasks()->delete();
        $project->milestones()->delete();
        $project->delete();
    }

    public function test_progress_report_shows_task_breakdown_and_latest_update(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeOverdueProjectWithTasks($tenantId, $admin->id);

        \DB::table('project_updates')->insert([
            'tenant_id' => $tenantId, 'project_id' => $project->id, 'user_id' => $admin->id,
            'completed_work' => 'Finished the initial setup.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ProjectReportController::class);
        $view = $controller->progress(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $row = collect($view->getData()['projects'])->firstWhere('id', $project->id);

        $this->assertSame(1, $row->tasks_completed);
        $this->assertSame(1, $row->tasks_pending);
        $this->assertStringContainsString('overdue', $row->days_label);
        $this->assertStringContainsString('Finished the initial setup', $row->latest_update_summary);

        $export = $controller->progressExport(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));

        \DB::table('project_updates')->where('project_id', $project->id)->delete();
        $project->assigns()->delete();
        $project->tasks()->delete();
        $project->milestones()->delete();
        $project->delete();
    }

    public function test_task_performance_report_computes_completion_counts_per_employee(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeOverdueProjectWithTasks($tenantId, $admin->id);

        $task = $project->tasks()->where('status', 'completed')->first();
        \DB::table('task_assigns')->insert([
            'tenant_id' => $tenantId, 'task_id' => $task->id, 'assigned_to' => $admin->id, 'assigned_by' => $admin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $controller = app(ProjectReportController::class);
        $view = $controller->taskPerformance(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $row = collect($view->getData()['rows'])->firstWhere('user_id', $admin->id);
        $this->assertNotNull($row);
        $this->assertGreaterThanOrEqual(1, $row->completed);

        $export = $controller->taskPerformanceExport(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));

        \DB::table('task_assigns')->where('task_id', $task->id)->delete();
        $project->assigns()->delete();
        $project->tasks()->delete();
        $project->milestones()->delete();
        $project->delete();
    }

    public function test_timeline_report_flags_overdue_and_shows_next_milestone(): void
    {
        $admin = $this->actingAdmin();
        $tenantId = $this->tenantId();
        $project = $this->makeOverdueProjectWithTasks($tenantId, $admin->id);

        // Independently computed via the model scope, per the plan's Testing section.
        $expectedOverdue = Project::overdue()->where('id', $project->id)->exists();
        $this->assertTrue($expectedOverdue, 'Test fixture project should match Project::scopeOverdue().');

        $controller = app(ProjectReportController::class);
        $view = $controller->timeline(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $row = collect($view->getData()['projects'])->firstWhere('id', $project->id);

        $this->assertTrue((bool) $row->is_overdue);
        $this->assertStringContainsString('Upcoming Milestone', $row->next_milestone);

        $export = $controller->timelineExport(Request::create('/x', 'GET', ['project_id' => $project->id]));
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));
        $this->assertStringContainsString('Yes', $export->getContent());

        $project->assigns()->delete();
        $project->tasks()->delete();
        $project->milestones()->delete();
        $project->delete();
    }
}
