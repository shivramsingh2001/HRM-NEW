<?php

namespace App\Support;

use App\Models\User;
use App\Services\FeatureService;
use App\Services\RbacService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

/**
 * The reports that can be emailed on a schedule (Reports → Scheduled Reports).
 *
 * Every entry points at the report's existing Export route, so a scheduled file
 * is exactly what the Export button downloads. `period` says which date ranges
 * make sense for the report:
 *   day   — one date (Today / Yesterday)
 *   month — one month (This month / Last month)
 *   range — a date range (Yesterday / Last 7 days / This month / Last month)
 *   none  — no date (a "now" snapshot, e.g. Leave Balance, Asset Register)
 *
 * The runner sends every period parameter name the report controllers use
 * (date, report_date, month, start_date/end_date, date_from/date_to,
 * from_date/to_date) — each report reads only the ones it knows.
 *
 * Who may see an entry is read from the route's own `feature:` / `permission:`
 * / `role:` middleware, so it never drifts from what the Reports page allows.
 */
class ReportCatalog
{
    public const PERIODS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 days',
        'this_month' => 'This month (to date)',
        'last_month' => 'Last month',
    ];

    public const PERIODS_BY_TYPE = [
        'day' => ['yesterday', 'today'],
        'month' => ['last_month', 'this_month'],
        'range' => ['yesterday', 'last_7_days', 'this_month', 'last_month'],
        'none' => [],
    ];

    /**
     * key => [group, label, route, route params, extra query, period type].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: array<string, string>, 4: array<string, string>, 5: string}>
     */
    private const REPORTS = [
        'attendance.overall' => ['Attendance', 'Overall Attendance', 'report.attendance.overall.export', [], [], 'month'],
        'attendance.day' => ['Attendance', 'Daywise Attendance', 'report.attendance.day.export', [], [], 'day'],
        'attendance.punches' => ['Attendance', 'Clock In/Out Log', 'report.attendance.punches.export', [], [], 'range'],
        'attendance.hourly' => ['Attendance', 'Working Hours', 'report.attendance.hourly.export', [], [], 'month'],
        'attendance.detail' => ['Attendance', 'Detailed Report', 'report.attendance.detail.export', [], [], 'month'],
        'attendance.summary' => ['Attendance', 'Monthly Summary', 'report.attendance.summary.export', [], [], 'month'],
        'attendance.shift-monthly' => ['Attendance', 'Shift Report', 'report.attendance.shift-monthly.export', [], [], 'month'],
        'attendance.shift-changes' => ['Attendance', 'Shift Change Log', 'report.attendance.shift-changes.export', [], [], 'range'],
        'attendance.shift-requests' => ['Attendance', 'Shift Requests Register', 'report.attendance.shift-requests.export', [], [], 'range'],

        'leave.balance' => ['Leave', 'Leave Balance Report', 'report.leave.show', ['report' => 'balance'], ['export' => 'csv'], 'none'],
        'leave.register' => ['Leave', 'Leave Report', 'report.leave.show', ['report' => 'register'], ['export' => 'csv'], 'range'],
        'leave.summary' => ['Leave', 'Leave Summary', 'report.leave.show', ['report' => 'summary'], ['export' => 'csv'], 'range'],
        'leave.regularization' => ['Leave', 'Regularization Report', 'report.leave.show', ['report' => 'regularization'], ['export' => 'csv'], 'range'],
        'leave.wfh-travel' => ['Leave', 'WFH & Travel Report', 'report.leave.show', ['report' => 'wfh-travel'], ['export' => 'csv'], 'range'],

        'payroll.monthly' => ['Payroll', 'Monthly Payroll Report', 'report.payroll.show', ['report' => 'monthly'], ['export' => 'csv'], 'month'],
        'payroll.structure' => ['Payroll', 'Employee Payroll Structure', 'report.payroll.show', ['report' => 'structure'], ['export' => 'csv'], 'none'],
        'payroll.loan' => ['Payroll', 'Loan Report', 'report.payroll.show', ['report' => 'loan'], ['export' => 'csv'], 'none'],
        'payroll.summary' => ['Payroll', 'Payroll Summary', 'report.payroll.show', ['report' => 'summary'], ['export' => 'csv'], 'month'],

        'expense.register' => ['Expense', 'Payment Register', 'expense.reports.show', ['report' => 'register'], ['export' => 'csv'], 'range'],
        'expense.summary' => ['Expense', 'Expense Summary', 'expense.reports.show', ['report' => 'summary'], ['export' => 'csv'], 'range'],
        'expense.ageing' => ['Expense', 'Outstanding Advances', 'expense.reports.show', ['report' => 'ageing'], ['export' => 'csv'], 'none'],

        'task.day-wise' => ['Task', 'Day-wise Task', 'report.task.day-wise.export', [], [], 'range'],
        'task.employee-monthly' => ['Task', 'Employee Monthly Task', 'report.task.employee-monthly.export', [], [], 'month'],
        'task.employee-date-wise' => ['Task', 'Employee Date-wise Task', 'report.task.employee-date-wise.export', [], [], 'day'],
        'task.monthly-task-detail' => ['Task', 'Monthly Task Detail', 'report.task.monthly-task-detail.export', [], [], 'month'],

        'project.summary' => ['Project', 'Project Summary', 'report.project.summary.export', [], [], 'none'],
        'project.progress' => ['Project', 'Project Progress', 'report.project.progress.export', [], [], 'none'],
        'project.task-performance' => ['Project', 'Project Task & Employee Performance', 'report.project.task-performance.export', [], [], 'none'],
        'project.timeline' => ['Project', 'Project Timeline / Overdue', 'report.project.timeline.export', [], [], 'none'],

        'asset.register' => ['Asset', 'Asset Register', 'report.asset.register.export', [], [], 'none'],

        'site-visit.day-wise' => ['Site Visits', 'Day-wise Visits', 'report.site-visit.day-wise.export', [], [], 'day'],
        'site-visit.monthly' => ['Site Visits', 'Monthly Visits', 'report.site-visit.monthly.export', [], [], 'month'],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::REPORTS[$key]);
    }

    /** @return array{key: string, group: string, label: string, route: string, params: array<string, string>, query: array<string, string>, period_type: string}|null */
    public static function get(string $key): ?array
    {
        $r = self::REPORTS[$key] ?? null;

        return $r === null ? null : [
            'key' => $key, 'group' => $r[0], 'label' => $r[1], 'route' => $r[2],
            'params' => $r[3], 'query' => $r[4], 'period_type' => $r[5],
        ];
    }

    public static function label(string $key): string
    {
        return self::REPORTS[$key][1] ?? $key;
    }

    /** @return array<int, string> period keys allowed for the report ([] = no date) */
    public static function periodsFor(string $key): array
    {
        return self::PERIODS_BY_TYPE[self::REPORTS[$key][5] ?? 'none'];
    }

    /**
     * Reports this user may schedule right now, grouped for a <select>.
     *
     * @return array<string, array<string, array{label: string, periods: array<string, string>}>>
     */
    public static function availableFor(User $user): array
    {
        $out = [];
        foreach (array_keys(self::REPORTS) as $key) {
            if (self::denialReason($key, $user) !== null) {
                continue;
            }
            $periods = [];
            foreach (self::periodsFor($key) as $p) {
                $periods[$p] = self::PERIODS[$p];
            }
            $out[self::REPORTS[$key][0]][$key] = ['label' => self::REPORTS[$key][1], 'periods' => $periods];
        }

        return $out;
    }

    /**
     * Why this user can't get the report (null = allowed). Checks the export
     * route's own role / feature / permission middleware for the current tenant.
     */
    public static function denialReason(string $key, User $user): ?string
    {
        $entry = self::get($key);
        $route = $entry ? Route::getRoutes()->getByName($entry['route']) : null;
        if (! $route) {
            return 'This report is no longer available.';
        }
        foreach ($route->gatherMiddleware() as $mw) {
            if (! is_string($mw) || ! str_contains($mw, ':')) {
                continue;
            }
            [$name, $args] = explode(':', $mw, 2);
            $args = array_values(array_filter(array_map('trim', explode(',', $args))));
            if ($name === 'role' && ! in_array($user->role, $args, true)) {
                return 'Your role can no longer open this report.';
            }
            if ($name === 'feature') {
                $features = app(FeatureService::class);
                if (! collect($args)->contains(fn ($k) => $features->enabledForCurrentTenant($k))) {
                    return 'This report\'s module is not in the company\'s plan any more.';
                }
            }
            if ($name === 'permission' && ! app(RbacService::class)->can($user, $args[0], $args[1] ?? 'view')) {
                return 'You no longer have permission to open this report.';
            }
        }
        // Payroll reports are company-wide only (same rule as the Reports page / PayrollReportController).
        if (str_starts_with($key, 'payroll.') && app(RbacService::class)->scopeFor($user, 'payroll', 'view') !== 'company') {
            return 'Payroll reports need company-wide payroll access.';
        }

        return null;
    }

    /**
     * Query parameters for the period, resolved against $now (the run time).
     *
     * @return array{query: array<string, string>, label: string}
     */
    public static function periodQuery(?string $period, CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        [$start, $end, $label] = match ($period) {
            'today' => [$today, $today, $today->format('d M Y')],
            'yesterday' => [$today->subDay(), $today->subDay(), $today->subDay()->format('d M Y')],
            'last_7_days' => [$today->subDays(7), $today->subDay(), $today->subDays(7)->format('d M').' – '.$today->subDay()->format('d M Y')],
            'this_month' => [$today->startOfMonth(), $today, $today->format('F Y').' (to '.$today->format('d M').')'],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth(), $today->subMonthNoOverflow()->format('F Y')],
            default => [null, null, 'As on '.$today->format('d M Y')],
        };
        if ($start === null) {
            return ['query' => [], 'label' => $label];
        }

        return ['label' => $label, 'query' => self::datesQuery($start, $end)];
    }

    /**
     * Query parameters for a date the caller picked (mobile admin API) instead of a
     * preset: a day (`date`), a month (`month` = Y-m) or a range (`from` / `to`),
     * by the report's period type. Null when the input does not fit the type.
     *
     * @param  array{date?: ?string, month?: ?string, from?: ?string, to?: ?string}  $input
     * @return array{query: array<string, string>, label: string}|null
     */
    public static function pickedQuery(string $key, array $input, CarbonImmutable $now): ?array
    {
        $today = $now->startOfDay();
        $parse = function (?string $value, string $format) {
            $date = $value ? CarbonImmutable::createFromFormat('!'.$format, $value) : false;

            return $date && $date->format($format) === $value ? $date : null;
        };

        switch (self::REPORTS[$key][5] ?? 'none') {
            case 'day':
                $day = $parse($input['date'] ?? null, 'Y-m-d');

                return $day ? ['label' => $day->format('d M Y'), 'query' => self::datesQuery($day, $day)] : null;
            case 'month':
                $month = $parse($input['month'] ?? null, 'Y-m');
                if (! $month) {
                    return null;
                }
                $end = $month->endOfMonth()->startOfDay();
                $end = $end->greaterThan($today) ? $today : $end;

                return ['label' => $month->format('F Y'), 'query' => self::datesQuery($month, $end)];
            case 'range':
                $from = $parse($input['from'] ?? null, 'Y-m-d');
                $to = $parse($input['to'] ?? ($input['from'] ?? null), 'Y-m-d');
                if (! $from || ! $to || $to->lessThan($from)) {
                    return null;
                }

                return ['label' => $from->equalTo($to) ? $from->format('d M Y') : $from->format('d M').' – '.$to->format('d M Y'),
                    'query' => self::datesQuery($from, $to)];
            default:
                return ['query' => [], 'label' => 'As on '.$today->format('d M Y')];
        }
    }

    /**
     * The same dates under every parameter name the report controllers read
     * (each report reads its own: date, report_date, month, start/end_date, date_from/to, from/to_date).
     *
     * @return array<string, string>
     */
    private static function datesQuery(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $s = $start->toDateString();
        $e = $end->toDateString();

        return [
            'date' => $e, 'report_date' => $e, 'month' => $start->format('Y-m'),
            'start_date' => $s, 'end_date' => $e,
            'date_from' => $s, 'date_to' => $e,
            'from_date' => $s, 'to_date' => $e,
        ];
    }

    /**
     * Optional employee filters (branch / department) as the report controllers read them.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array<string, string>
     */
    public static function filterQuery(?array $filters): array
    {
        $q = [];
        if (! empty($filters['branch_id'])) {
            $q['branch_id'] = (string) (int) $filters['branch_id'];
        }
        if (! empty($filters['department_id'])) {
            $q['department'] = $q['department_id'] = (string) (int) $filters['department_id'];
        }

        return $q;
    }
}
