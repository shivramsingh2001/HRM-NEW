<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\User;
use App\Notifications\TaskStatusUpdateNotification;
use App\Services\TaskNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Task status notifications say what actually happened: an In Progress /
 * Hold / Completed update is never labelled "rejected", approvals reach every
 * assignee, the stored (bell) notification matches the push wording, and
 * nobody is notified about their own action. Shared dev DB — throwaway rows.
 */
class TaskNotificationTest extends TestCase
{
    private int $tenantId;
    private array $users = [];
    private array $taskIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::table('task_assigns')->whereIn('task_id', $this->taskIds)->delete();
        DB::table('tasks')->whereIn('id', $this->taskIds)->delete();
        User::withoutGlobalScopes()->whereIn('id', array_map(fn ($u) => $u->id, $this->users))->forceDelete();
        parent::tearDown();
    }

    private function user(string $name): User
    {
        return $this->users[] = User::withoutGlobalScopes()->forceCreate([
            'name' => $name, 'email' => 'tasknotif.' . uniqid() . '@phpunit.test',
            'password' => bcrypt('x'), 'role' => 'employee', 'status' => 1, 'tenant_id' => $this->tenantId,
        ]);
    }

    /** @param array<int,array{0:User,1:User}> $pairs [assigned_by, assigned_to] in insert order */
    private function task(array $pairs, string $mode = 'individual', string $status = 'pending'): Task
    {
        $task = Task::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenantId, 'task_code' => 'PU' . random_int(100000, 999999), 'title' => 'Phpunit task',
            'description' => 'x', 'task_date' => now()->toDateString(), 'deadline_date' => now()->addDays(3)->toDateString(),
            'priority' => 'medium', 'status' => $status, 'task_mode' => $mode,
        ]);
        $this->taskIds[] = $task->id;
        foreach ($pairs as [$by, $to]) {
            TaskAssign::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenantId, 'task_id' => $task->id,
                'assigned_by' => $by->id, 'assigned_to' => $to->id, 'status' => 'assigned', 'individual_status' => 'pending',
            ]);
        }

        return $task;
    }

    private function titleSentTo(User $user): ?string
    {
        $sent = Notification::sent($user, TaskStatusUpdateNotification::class);

        return $sent->isEmpty() ? null : $sent->first()->toArray($user)['title'];
    }

    public function test_in_progress_update_is_never_labelled_rejected(): void
    {
        $manager = $this->user('Manager');
        $emp = $this->user('Employee');
        $other = $this->user('Other');
        // The actor is both an assigner (first row) and an assignee — the old
        // guess picked the first row and sent "Task Rejected" to $other.
        $task = $this->task([[$emp, $other], [$manager, $emp]], 'group');

        app(TaskNotificationService::class)->notifyTaskStatusUpdate($task, $emp, 'pending', 'in_progress', 'started');

        $this->assertSame('▶️ Task In Progress', $this->titleSentTo($manager));
        $this->assertNull($this->titleSentTo($other));
        Notification::assertNotSentTo($emp, TaskStatusUpdateNotification::class);
    }

    public function test_each_status_has_its_own_wording_in_push_and_bell(): void
    {
        foreach (['in_progress' => '▶️ Task In Progress', 'hold' => '⏸️ Task On Hold', 'completed' => '☑️ Task Completed',
            'cancelled' => '🚫 Task Cancelled', 'pending' => '🔄 Task Status Updated'] as $status => $title) {
            [$pushTitle, $body] = TaskNotificationService::statusMessage('T', 'Asha', $status);
            $this->assertSame($title, $pushTitle, $status);
            $this->assertStringNotContainsString('reject', strtolower($body), $status);
            $this->assertStringNotContainsString('_', $body, $status);
        }

        $manager = $this->user('Manager');
        $emp = $this->user('Employee');
        $task = $this->task([[$manager, $emp]]);
        app(TaskNotificationService::class)->notifyTaskStatusUpdate($task, $emp, 'in_progress', 'hold', null, $manager);

        $data = Notification::sent($manager, TaskStatusUpdateNotification::class)->first()->toArray($manager);
        $this->assertSame('⏸️ Task On Hold', $data['title']);
        $this->assertSame('Employee put task "Phpunit task" On Hold.', $data['message']);
    }

    public function test_approval_without_receiver_reaches_every_group_member(): void
    {
        $manager = $this->user('Manager');
        $a = $this->user('Member A');
        $b = $this->user('Member B');
        $task = $this->task([[$manager, $a], [$manager, $b]], 'group', 'completed');

        app(TaskNotificationService::class)->notifyTaskStatusUpdate($task, $manager, 'completed', 'approved', 'good');

        $this->assertSame('✅ Task Approved', $this->titleSentTo($a));
        $this->assertSame('✅ Task Approved', $this->titleSentTo($b));
        Notification::assertNotSentTo($manager, TaskStatusUpdateNotification::class);
    }

    public function test_group_member_update_reports_their_part_and_group_status(): void
    {
        [$title, $body] = TaskNotificationService::statusMessage('Launch', 'Ravi', 'completed', null, 'in_progress');

        $this->assertSame('👥 Group Task Update', $title);
        $this->assertSame('Ravi marked their part of "Launch" as Completed. Group status: In Progress.', $body);

        [$title] = TaskNotificationService::statusMessage('Launch', 'Ravi', 'completed', null, 'completed');
        $this->assertSame('☑️ Group Task Completed', $title);
    }

    public function test_no_notification_about_your_own_action(): void
    {
        $emp = $this->user('Solo');
        $task = $this->task([[$emp, $emp]]);

        $this->assertFalse(app(TaskNotificationService::class)->notifyTaskStatusUpdate($task, $emp, 'pending', 'completed'));
        $this->assertFalse(app(TaskNotificationService::class)->notifyTaskAssigned($task, $emp, $emp));
        Notification::assertNothingSent();
    }
}
