<?php

namespace App\Services\Reports;

use App\Exceptions\ReportScheduleException;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reports for the mobile admin API: the list of reports the user may open, and
 * one report for a period as a table (columns + rows) or as its export file.
 * Every report is produced by replaying its web Export route as the user
 * (ReportFileGenerator::generateFor), so access rules and numbers are the web's.
 */
class MobileReportService
{
    /** Longest date range a picked from / to may cover. */
    public const MAX_RANGE_DAYS = 93;

    public function __construct(private ReportFileGenerator $generator, private ReportTableReader $reader) {}

    /** @return array{groups: array<int, array{name: string, reports: array<int, array<string, mixed>>}>, branches: mixed, departments: mixed} */
    public function catalog(User $user): array
    {
        $groups = [];
        foreach (ReportCatalog::availableFor($user) as $group => $reports) {
            $items = [];
            foreach ($reports as $key => $report) {
                $items[] = [
                    'key' => $key,
                    'label' => $report['label'],
                    'period_type' => ReportCatalog::get($key)['period_type'],
                    'presets' => collect($report['periods'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                ];
            }
            $groups[] = ['name' => $group, 'reports' => $items];
        }

        $options = fn (string $table) => DB::table($table)->where('tenant_id', $user->tenant_id)->orderBy('name')->get(['id', 'name']);

        return ['groups' => $groups, 'branches' => $options('company_branches'), 'departments' => $options('departments')];
    }

    /**
     * @param  array<string, mixed>  $input  period | date | month | from, to; branch_id, department_id
     * @return array{content: string, filename: string, mime: string, period_label: string}
     *
     * @throws ReportScheduleException message safe to show; ->pause = access refused
     */
    public function file(User $user, Tenant $tenant, string $key, array $input): array
    {
        if ($why = ReportCatalog::denialReason($key, $user)) {
            throw new ReportScheduleException($why, true);
        }

        return $this->generator->generateFor($key, $this->period($key, $input), ReportCatalog::filterQuery($input), $user, $tenant);
    }

    /**
     * @param  array<string, mixed>  $input  as file() + search, page, per_page
     * @return array<string, mixed>
     */
    public function table(User $user, Tenant $tenant, string $key, array $input): array
    {
        $file = $this->file($user, $tenant, $key, $input);
        $table = $this->reader->read($file['content'], $file['mime']);

        $rows = $table['rows'];
        $search = trim((string) ($input['search'] ?? ''));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, fn (array $r) => mb_stripos(implode(' ', $r), $search) !== false));
        }

        $perPage = (int) ($input['per_page'] ?? 50);
        $page = (int) ($input['page'] ?? 1);
        $total = count($rows);

        return [
            'report' => ['key' => $key, 'label' => ReportCatalog::label($key), 'group' => ReportCatalog::get($key)['group']],
            'period_label' => $file['period_label'],
            'title_lines' => $table['title_lines'],
            'columns' => $table['columns'],
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ];
    }

    /**
     * A preset (`period`) or a picked date / month / range; default = the report's first preset.
     *
     * @return array{query: array<string, string>, label: string}
     */
    private function period(string $key, array $input): array
    {
        $now = CarbonImmutable::now();
        $picked = array_filter(array_intersect_key($input, array_flip(['date', 'month', 'from', 'to'])));
        if ($picked !== []) {
            if (isset($picked['from'], $picked['to'])
                && CarbonImmutable::parse($picked['from'])->diffInDays(CarbonImmutable::parse($picked['to'])) > self::MAX_RANGE_DAYS) {
                throw new ReportScheduleException('Please pick a range of '.self::MAX_RANGE_DAYS.' days or less.');
            }
            $resolved = ReportCatalog::pickedQuery($key, $picked, $now);
            if ($resolved === null) {
                throw new ReportScheduleException(match (ReportCatalog::get($key)['period_type']) {
                    'day' => 'This report needs a date (date=YYYY-MM-DD).',
                    'month' => 'This report needs a month (month=YYYY-MM).',
                    default => 'This report needs a date range (from / to = YYYY-MM-DD).',
                });
            }

            return $resolved;
        }

        $allowed = ReportCatalog::periodsFor($key);
        $period = in_array($input['period'] ?? null, $allowed, true) ? $input['period'] : ($allowed[0] ?? null);

        return ReportCatalog::periodQuery($period, $now);
    }
}
