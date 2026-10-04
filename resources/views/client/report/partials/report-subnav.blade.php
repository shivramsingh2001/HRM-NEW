{{--
    Sub-tabs to switch between the reports of one Reports-hub group without going
    back to the hub — same pills as the Leave / Payroll report pages.
    Usage: @include('client.report.partials.report-subnav', ['group' => 'attendance'])
    Groups: attendance, project, task, expense, asset. Each tab respects the same
    feature gates as the matching card on client/report/index.blade.php.
--}}
@php
    $subnavFeatures = app(\App\Services\FeatureService::class);
    $expenseReport = request()->route('report');

    $subnavGroups = [
        'attendance' => [
            ['Overall Attendance', 'report.attendance.overall.index', ['report.attendance.overall.*']],
            ['Daywise Attendance', 'report.attendance.day.index', ['report.attendance.day.*']],
            ['Clock In/Out Log', 'report.attendance.punches.index', ['report.attendance.punches.*']],
            ['Working Hours', 'report.attendance.hourly.index', ['report.attendance.hourly.*']],
            ['Detailed Report', 'report.attendance.detail.index', ['report.attendance.detail.*']],
            ['Monthly Summary', 'report.attendance.summary.index', ['report.attendance.summary.*']],
            $subnavFeatures->enabledForCurrentTenant('overtime')
                ? ['Overtime Hours', 'report.overtime.monthly.index', ['report.overtime.monthly.*']] : null,
            ['Attendance Location Wise', 'report.attendance.branch-wise', ['report.attendance.branch-wise*']],
            ($subnavFeatures->enabledForCurrentTenant('fixed_shift') || $subnavFeatures->enabledForCurrentTenant('custom_shift'))
                ? ['Shift Report', 'report.attendance.shift-monthly.index', ['report.attendance.shift-monthly.*']] : null,
        ],
        'project' => [
            ['Project Summary', 'report.project.summary.index', ['report.project.summary.*']],
            ['Project Progress', 'report.project.progress.index', ['report.project.progress.*']],
            ['Task & Employee Performance', 'report.project.task-performance.index', ['report.project.task-performance.*']],
            ['Timeline / Overdue', 'report.project.timeline.index', ['report.project.timeline.*']],
        ],
        'task' => [
            ['Task & Project Overview', 'report.task-project.index', ['report.task-project.*']],
            ['Day-wise Task', 'report.task.day-wise', ['report.task.day-wise*']],
            ['Employee Monthly Task', 'report.task.employee-monthly', ['report.task.employee-monthly*']],
            ['Employee Date-wise Task', 'report.task.employee-date-wise', ['report.task.employee-date-wise*']],
            ['Monthly Task Detail', 'report.task.monthly-task-detail', ['report.task.monthly-task-detail*']],
        ],
        'expense' => [
            ['Payment Register', ['expense.reports.show', 'register'], $expenseReport === 'register'],
            ['Expense Summary', ['expense.reports.show', 'summary'], $expenseReport === 'summary'],
            ['Outstanding Advances', ['expense.reports.show', 'ageing'], $expenseReport === 'ageing'],
        ],
        'asset' => [
            ['Asset Register', 'report.asset.register.index', ['report.asset.register.*']],
            ['Employee-wise Assets', 'report.asset.employee-wise.index', ['report.asset.employee-wise.*']],
            ['Asset Summary', 'report.asset.summary.index', ['report.asset.summary.*']],
            ['Warranty Expiry', 'report.asset.warranty-expiry.index', ['report.asset.warranty-expiry.*']],
        ],
    ];
@endphp

@once
    <style>
        .rpt-subnav { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .rpt-subnav a {
            font-size: 11px; font-weight: 600; padding: 6px 14px; border-radius: 20px; text-decoration: none;
            color: #475569; background: #f4f6fb; border: 1px solid #dfe5f0;
        }
        .rpt-subnav a.active { background: #0D6EFD; color: #fff; border-color: #0D6EFD; }
        .rpt-subnav a:hover:not(.active) { background: #EFF6FF; color: #0D6EFD; }
    </style>
@endonce

<div class="rpt-subnav">
    @foreach (array_filter($subnavGroups[$group] ?? []) as [$label, $target, $active])
        @php
            $href = is_array($target) ? route($target[0], $target[1]) : route($target);
            $isActive = is_bool($active) ? $active : request()->routeIs(...$active);
        @endphp
        <a href="{{ $href }}" class="{{ $isActive ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>
