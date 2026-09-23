<?php

namespace Tests\Feature\Report;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Regression test for the Branch-column/Branch-filter/Search rollout across Attendance, Task,
 * Project, Asset and Expense reports (see docs/modules.md — Reports module). Hits every touched
 * report's index and CSV-export routes with a `branch_id` + `search` query string and asserts a
 * plain 200, to catch runtime errors static analysis (php -l / Blade::compileString) can't see
 * (undefined relations, wrong column names, colspan mismatches, etc).
 */
class BranchSearchSmokeTest extends TestCase
{
    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->whereNotNull('tenant_id')->select('tenant_id')
            ->groupBy('tenant_id')->havingRaw('count(*) >= 5')
            ->orderByRaw('count(*) desc')->value('tenant_id');

        if (! $this->tenantId) {
            $this->markTestSkipped('no tenant with enough users');
        }

        $admin = User::where('tenant_id', $this->tenantId)->where('role', 'admin')->first();
        Auth::login($admin);
        Session::put('tenant_id', $this->tenantId);
        app()->instance('current_tenant', Tenant::find($this->tenantId));
    }

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    /** @dataProvider routes */
    public function test_route_renders_ok(string $name, array $params = []): void
    {
        $params += ['branch_id' => 1, 'search' => 'a'];
        $this->get(route($name, $params))->assertOk();
    }

    public static function routes(): array
    {
        return [
            'task employee-monthly' => ['report.task.employee-monthly'],
            'task employee-date-wise' => ['report.task.employee-date-wise'],
            'task day-wise' => ['report.task.day-wise'],
            'task monthly-task-detail' => ['report.task.monthly-task-detail'],
            'project summary' => ['report.project.summary.index'],
            'project progress' => ['report.project.progress.index'],
            'project timeline' => ['report.project.timeline.index'],
            'project task-performance' => ['report.project.task-performance.index'],
            'asset register' => ['report.asset.register.index'],
            'asset employee-wise' => ['report.asset.employee-wise.index'],
            'asset summary' => ['report.asset.summary.index'],
            'asset warranty-expiry' => ['report.asset.warranty-expiry.index'],
            'expense register' => ['expense.reports.show', ['report' => 'register']],
            'expense summary' => ['expense.reports.show', ['report' => 'summary']],
            'expense ageing' => ['expense.reports.show', ['report' => 'ageing']],
            'attendance detail' => ['report.attendance.detail.index'],
            'attendance day' => ['report.attendance.day.index'],
            'attendance hourly' => ['report.attendance.hourly.index'],
            'attendance overall' => ['report.attendance.overall.index'],
            'attendance employee-wise' => ['report.attendance.detailed.index'],
            'attendance monthly summary' => ['report.attendance.monthly.summary.index'],
            'attendance overtime monthly' => ['report.overtime.monthly.index'],
            'attendance shift-monthly' => ['report.attendance.shift-monthly.index'],
            // CSV export siblings — separate query paths, same branch_id/search filters.
            'task employee-monthly export' => ['report.task.employee-monthly.export'],
            'task employee-date-wise export' => ['report.task.employee-date-wise.export'],
            'task day-wise export' => ['report.task.day-wise.export'],
            'task monthly-task-detail export' => ['report.task.monthly-task-detail.export'],
            'project summary export' => ['report.project.summary.export'],
            'project progress export' => ['report.project.progress.export'],
            'project timeline export' => ['report.project.timeline.export'],
            'project task-performance export' => ['report.project.task-performance.export'],
            'asset register export' => ['report.asset.register.export'],
            'attendance detail export' => ['report.attendance.detail.export'],
            'attendance day export' => ['report.attendance.day.export'],
            'attendance hourly export' => ['report.attendance.hourly.export'],
            'attendance overall export' => ['report.attendance.overall.export'],
            'attendance shift-monthly export' => ['report.attendance.shift-monthly.export'],
        ];
    }
}
