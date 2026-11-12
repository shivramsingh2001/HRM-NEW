<?php

namespace App\Services\Dashboard;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The web admin dashboard (AdminDashboard::build()) as plain JSON for the
 * mobile app: grouped into sections, dates as strings, models / collections
 * as arrays, a `*_url` next to every stored photo path. A card the company's
 * plan (or the viewer's permissions) hides is null, exactly as on the web.
 */
class AdminDashboardPayload
{
    private const TASK_KEYS = [
        'total_tasks', 'pending_tasks', 'in_progress_tasks', 'completed_tasks', 'approved_tasks',
        'rejected_tasks', 'overdue_tasks', 'monthly_tasks', 'least_tasks_employees',
    ];

    private const PROJECT_KEYS = [
        'total_projects', 'pending_projects', 'ongoing_projects', 'completed_projects',
        'on_hold_projects', 'cancelled_projects', 'projects_by_status',
    ];

    /** Keys of build() placed explicitly below (or dropped as web-only). */
    private const USED_KEYS = [
        'page_title', 'colorPalette', 'show', 'range', 'total_employees', 'active_employees', 'inactive_employees',
        'monthly_joinings', 'today', 'present_today', 'absent_today', 'on_leave_today', 'pending_approvals',
        'payroll_snapshot', 'loan_snapshot', 'overtime_snapshot', 'asset_snapshot', 'meeting_snapshot',
        'department_distribution', 'recent_announcements', 'recent_joinings', 'upcoming_holidays',
        'today_celebrations', 'upcoming_birthdays', 'upcoming_anniversaries', 'recent_leaves',
        'attendance_chart_data', 'top_performers', 'performance_month_options', 'selected_performance_month',
        'company_overview_trend', 'calendar_holidays', 'most_regularization_requests',
    ];

    /** Stored photo/file path keys → the file-storage module their URL is built for. */
    private const FILE_KEYS = [
        'profile_image' => 'profile_photo',
        'photo' => 'profile_photo',
        'image' => null,
    ];

    public function __construct(private AdminDashboard $dashboard) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $d = $this->dashboard->build();
        $show = $d['show'];

        $attendance = $d['today'];
        unset($attendance['chart']);

        $range = $d['range'];
        unset($range['start'], $range['end']);

        $tasks = $show['tasks'] ? array_intersect_key($d, array_flip(self::TASK_KEYS)) : null;
        $projects = $show['projects'] ? array_intersect_key($d, array_flip(self::PROJECT_KEYS)) : null;
        // ExpenseCard::statistics() is merged flat into the web data — whatever is left is it.
        $expenses = $show['expenses']
            ? array_diff_key($d, array_flip(array_merge(self::USED_KEYS, self::TASK_KEYS, self::PROJECT_KEYS)))
            : null;

        return $this->normalize([
            'range' => $range,
            'show' => $show,
            'employees' => [
                'total' => $d['total_employees'],
                'active' => $d['active_employees'],
                'inactive' => $d['inactive_employees'],
                'joinings' => $d['monthly_joinings'],
            ],
            'attendance' => $show['attendance'] ? $attendance : null,
            'attendance_chart' => $d['attendance_chart_data'],
            'pending_approvals' => $d['pending_approvals'],
            'payroll' => $d['payroll_snapshot'],
            'loans' => $d['loan_snapshot'],
            'overtime' => $d['overtime_snapshot'],
            'assets' => $d['asset_snapshot'],
            'meetings' => $d['meeting_snapshot'],
            'tasks' => $tasks,
            'projects' => $projects,
            'expenses' => $expenses,
            'regularization' => $show['regularization'] ? ['most_requests' => $d['most_regularization_requests'] ?? []] : null,
            'top_performers' => $show['attendance'] ? [
                'month' => $d['selected_performance_month'],
                'month_options' => $d['performance_month_options'],
                'employees' => $d['top_performers'],
            ] : null,
            'department_distribution' => $d['department_distribution'],
            'recent_joinings' => $d['recent_joinings'],
            'recent_leaves' => $show['leave'] ? $d['recent_leaves'] : null,
            'recent_announcements' => $show['announcements'] ? $d['recent_announcements'] : null,
            'upcoming_holidays' => $show['holiday'] ? $d['upcoming_holidays'] : null,
            'calendar_holidays' => $show['holiday'] ? $d['calendar_holidays'] : null,
            'celebrations' => [
                'today' => $d['today_celebrations'],
                'upcoming_birthdays' => $d['upcoming_birthdays'],
                'upcoming_anniversaries' => $d['upcoming_anniversaries'],
            ],
            'company_trend' => $d['company_overview_trend'],
        ]);
    }

    /** Dates → strings, models / collections / objects → arrays, photo paths → + URL. */
    private function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $formatted = $value->format('Y-m-d H:i:s');

            return str_ends_with($formatted, ' 00:00:00') ? substr($formatted, 0, 10) : $formatted;
        }
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $this->normalize($item);
            if (is_string($key) && array_key_exists($key, self::FILE_KEYS) && is_string($item) && $item !== ''
                && ! array_key_exists($key.'_url', $value)) {
                $out[$key.'_url'] = str_starts_with($item, 'http') ? $item : file_url($item, self::FILE_KEYS[$key]);
            }
        }

        return $out;
    }
}
