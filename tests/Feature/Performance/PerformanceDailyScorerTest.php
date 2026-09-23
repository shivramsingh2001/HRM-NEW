<?php

namespace Tests\Feature\Performance;

use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Performance\PerformanceDailyScorer;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Exercises PerformanceDailyScorer against real tenant/user rows (no
 * RefreshDatabase, matching this codebase's established test pattern) but a
 * fixed synthetic date far from real usage, so this can never collide with
 * genuine attendance/task/regularization data. Every row this test creates
 * is deleted in tearDown() regardless of pass/fail.
 */
class PerformanceDailyScorerTest extends TestCase
{
    private int $tenantId;
    private int $userId;
    private string $date = '2019-05-06'; // a Monday, far from any real data

    private array $createdAttendanceIds = [];
    private array $createdTaskIds = [];
    private array $createdRegIds = [];
    private array $createdHolidayIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
        $this->userId = (int) User::where('tenant_id', $this->tenantId)->value('id');

        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        Attendance::withoutGlobalScopes()->whereIn('id', $this->createdAttendanceIds)->delete();
        Task::withoutGlobalScopes()->whereIn('id', $this->createdTaskIds)->delete();
        \DB::table('task_assigns')->whereIn('task_id', $this->createdTaskIds)->delete();
        AttendanceRegularization::withoutGlobalScopes()->whereIn('id', $this->createdRegIds)->delete();
        Holiday::withoutGlobalScopes()->whereIn('id', $this->createdHolidayIds)->delete();

        parent::tearDown();
    }

    private function scorer(): PerformanceDailyScorer
    {
        return app(PerformanceDailyScorer::class);
    }

    public function test_a_day_with_no_attendance_and_no_activity_scores_as_unauthorized_absent(): void
    {
        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $this->date);

        $this->assertSame('working', $result['day_type']);
        $this->assertSame('absent', $result['attendance_status']);
        $this->assertSame(0.0, $result['attendance_score']);
        $this->assertTrue($result['is_unauthorized_absent']);
        // No tasks/regularization that day either -> both excluded, not zeroed.
        $this->assertNull($result['task_completion_score']);
        $this->assertNull($result['regularization_score']);
        $this->assertSame('calculated', $result['calculation_status']); // attendance alone still yields a score
    }

    public function test_a_holiday_excludes_attendance_entirely(): void
    {
        $holiday = Holiday::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'start_date' => $this->date,
            'end_date' => $this->date,
            'name' => 'Test Holiday',
            'status' => 1,
        ]);
        $this->createdHolidayIds[] = $holiday->id;

        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $this->date);

        $this->assertSame('holiday', $result['day_type']);
        $this->assertNull($result['attendance_score']);
        // No other activity either -> the whole day is excluded from rollups.
        $this->assertSame('excluded', $result['calculation_status']);
        $this->assertNull($result['overall_daily_score']);
    }

    public function test_a_future_date_stays_pending_and_is_never_scored(): void
    {
        $future = now()->addYears(2)->format('Y-m-d');

        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $future);

        $this->assertSame('pending', $result['calculation_status']);
        $this->assertArrayNotHasKey('overall_daily_score', $result);
    }

    public function test_zero_assigned_tasks_excludes_task_completion_not_zeros_it(): void
    {
        // No tasks created for this date at all.
        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $this->date);

        $this->assertSame(0, $result['assigned_tasks_count']);
        $this->assertNull($result['task_completion_score']);
    }

    public function test_completed_tasks_produce_a_completion_and_ontime_score(): void
    {
        $task = Task::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'task_code' => 'TST01',
            'task_mode' => 'individual',
            'title' => 'Test task',
            'task_date' => $this->date,
            'deadline_date' => $this->date,
            'priority' => 'medium',
            'status' => 'completed',
        ]);
        $this->createdTaskIds[] = $task->id;

        \DB::table('task_assigns')->insert([
            'tenant_id' => $this->tenantId,
            'task_id' => $task->id,
            'assigned_by' => $this->userId,
            'assigned_to' => $this->userId,
            'individual_status' => 'completed',
            'completed_at' => $this->date . ' 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $this->date);

        $this->assertSame(1, $result['assigned_tasks_count']);
        $this->assertSame(1, $result['completed_tasks_count']);
        $this->assertSame(1, $result['on_time_completed_tasks_count']);
        $this->assertSame(100.0, $result['task_completion_score']);
        $this->assertSame(100.0, $result['task_ontime_score']);
    }

    public function test_regularization_penalty_differs_by_outcome(): void
    {
        $reg = AttendanceRegularization::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'date' => $this->date,
            'request_type' => 'in_time',
            'reason' => 'Test',
            'status' => 'rejected',
        ]);
        $this->createdRegIds[] = $reg->id;

        $result = $this->scorer()->scoreDay($this->userId, $this->tenantId, $this->date);

        $this->assertSame('rejected', $result['regularization_status']);
        // Default policy: rejected penalty 15 -> score 85.
        $this->assertSame(85.0, $result['regularization_score']);
    }
}
