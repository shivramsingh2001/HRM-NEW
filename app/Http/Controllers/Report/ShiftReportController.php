<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\CompanyBranch;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Models\UserWeekoffs;
use App\Services\Attendance\TenantShiftResolver;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Shift Report (Monthly) — which employee is on which shift on which date.
 *
 * Reads what the Shift Roster already shows (`user_shifts` per-day cache + `user_weekoffs`),
 * so it always agrees with the roster. A company with custom shifts OFF runs on its single fixed
 * shift (`tenants.default_shift_id`), exactly as TenantShiftResolver treats it. Read-only; the
 * CSV export is one row per employee per day.
 *
 * `role:admin,hr,manager` (the whole Reports area); a manager only sees their own reportees,
 * same as the roster.
 */
class ShiftReportController extends Controller
{
    use SanitizesCsv;

    private const PER_PAGE = 20;

    public function monthly(Request $request)
    {
        try {
            $data = $this->build($request);

            $page = LengthAwarePaginator::resolveCurrentPage();
            $rows = new LengthAwarePaginator(
                $data['rows']->forPage($page, self::PER_PAGE)->values(),
                $data['rows']->count(),
                self::PER_PAGE,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('client.report.attendance.shift-monthly', $data + ['pagedRows' => $rows]);
        } catch (Exception $e) {
            Log::error('Shift monthly report error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to load the shift report.');
        }
    }

    public function monthlyExport(Request $request)
    {
        try {
            $data = $this->build($request);

            $handle = fopen('php://temp', 'w+');
            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeCsvRow($handle, ['Date', 'Day', 'Employee ID', 'Employee', 'Department', 'Branch', 'Shift', 'Start', 'End', 'Status']);

            foreach ($data['rows'] as $row) {
                foreach ($data['dates'] as $d) {
                    $ds = $d->format('Y-m-d');
                    $cell = $row['cells'][$ds];
                    $this->writeCsvRow($handle, [
                        $ds, $d->format('D'), $row['employee_id'], $row['name'], $row['department'], $row['branch'],
                        $cell['type'] === 'shift' ? $cell['name'] : '',
                        $cell['type'] === 'shift' ? $cell['start'] : '',
                        $cell['type'] === 'shift' ? $cell['end'] : '',
                        match ($cell['type']) {
                            'shift' => 'Shift',
                            'weekoff' => 'Week off',
                            default => 'Unassigned',
                        },
                    ]);
                    // Additional (2nd+) shifts that day get their own line.
                    foreach ($cell['extra'] ?? [] as $x) {
                        $this->writeCsvRow($handle, [
                            $ds, $d->format('D'), $row['employee_id'], $row['name'], $row['department'], $row['branch'],
                            $x['name'], $x['start'], $x['end'], 'Additional shift',
                        ]);
                    }
                }
            }

            rewind($handle);
            $body = stream_get_contents($handle);
            fclose($handle);

            return response($body, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="shift-report-' . $data['month'] . '.csv"',
            ]);
        } catch (Exception $e) {
            Log::error('Shift monthly report export error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to export the shift report.');
        }
    }

    /**
     * Everything both the page and the export need: the month's dates, one row per matching
     * employee (with a cell for every date), the shifts in use and headline numbers.
     */
    private function build(Request $request): array
    {
        $auth = Auth::user();
        $tenantId = (int) $auth->tenant_id;

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $dates = collect(range(0, $start->daysInMonth - 1))->map(fn ($i) => $start->copy()->addDays($i));
        $dateStrings = $dates->map(fn ($d) => $d->format('Y-m-d'))->all();

        // Employees — same scope as the Shift Roster.
        $userQuery = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where('role', '!=', 'admin')
            ->with(['jobDetails.department', 'jobDetails.branch']);

        if ($auth->role === 'manager') {
            $userQuery->managedBy($auth->id);
        }
        if ($request->filled('search')) {
            $s = (string) $request->query('search');
            $userQuery->where(fn ($q) => $q->where('name', 'LIKE', "%{$s}%")->orWhere('employee_id', 'LIKE', "%{$s}%"));
        }
        if ($request->filled('department_id')) {
            $deptId = (int) $request->query('department_id');
            $userQuery->whereHas('jobDetails', fn ($q) => $q->where('department', $deptId));
        }
        if ($request->filled('branch_id')) {
            $branchIdFilter = (int) $request->query('branch_id');
            $userQuery->whereHas('jobDetails', fn ($q) => $q->where('branch_id', $branchIdFilter));
        }

        $users = $userQuery->orderBy('name')->get();
        $userIds = $users->pluck('id')->all();

        $resolver = app(TenantShiftResolver::class);
        $customShifts = $resolver->isCustomShifts($tenantId);
        $defaultShift = $customShifts ? null : $resolver->defaultShift($tenantId);

        $shifts = Shift::where('tenant_id', $tenantId)->get()->keyBy('id');

        $assignments = $userIds === [] ? collect() : UserShift::where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$dateStrings[0], end($dateStrings)])
            ->orderBy('is_additional')
            ->get(['user_id', 'shift_id', 'date', 'is_additional'])
            ->groupBy(fn ($r) => $r->user_id . '|' . Carbon::parse($r->date)->format('Y-m-d'));

        $weekoffs = $userIds === [] ? collect() : UserWeekoffs::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $shiftFilter = $request->filled('shift_id') ? (int) $request->query('shift_id') : null;

        $rows = collect();
        foreach ($users as $user) {
            $cells = [];
            $counts = ['shift' => 0, 'weekoff' => 0, 'unassigned' => 0];
            $hasFilteredShift = $shiftFilter === null;

            foreach ($dates as $d) {
                $ds = $d->format('Y-m-d');
                $isWeekOff = ($w = $weekoffs->get($user->id)) ? WeekOffPredicate::isWeekOff($w, $d) : false;

                // Primary shift first, then any additional (2nd+) shifts that day.
                $dayShifts = $customShifts
                    ? $assignments->get($user->id . '|' . $ds, collect())->map(fn ($a) => $shifts->get($a->shift_id))->filter()->values()
                    : collect($isWeekOff || !$defaultShift ? [] : [$defaultShift]);
                $shift = $dayShifts->first();

                if ($shift) {
                    $cells[$ds] = $this->shiftCell($shift) + [
                        'extra' => $dayShifts->slice(1)->map(fn ($x) => $this->shiftCell($x))->values()->all(),
                    ];
                    $counts['shift']++;
                    if ($shiftFilter !== null && $dayShifts->contains(fn ($x) => (int) $x->id === $shiftFilter)) {
                        $hasFilteredShift = true;
                    }
                } elseif ($isWeekOff) {
                    $cells[$ds] = ['type' => 'weekoff'];
                    $counts['weekoff']++;
                } else {
                    $cells[$ds] = ['type' => 'unassigned'];
                    $counts['unassigned']++;
                }
            }

            if (! $hasFilteredShift) {
                continue;
            }

            $rows->push([
                'user_id' => $user->id,
                'name' => $user->name,
                'employee_id' => $user->employee_id,
                'department' => $user->jobDetails?->department?->name ?? '',
                'branch' => $user->jobDetails?->branch?->name ?? '',
                'cells' => $cells,
                'shift_days' => $counts['shift'],
                'weekoffs' => $counts['weekoff'],
                'unassigned' => $counts['unassigned'],
            ]);
        }

        // Legend: only the shifts that actually appear this month.
        $usedIds = $rows->flatMap(fn ($r) => collect($r['cells'])->where('type', 'shift')
            ->flatMap(fn ($c) => array_merge([$c['shift_id']], array_column($c['extra'] ?? [], 'shift_id'))))->unique()->values();
        $legend = $usedIds->map(fn ($id) => $shifts->get($id))->filter()->map(fn ($s) => $this->shiftCell($s))->sortBy('name')->values();

        return [
            'month' => $month,
            'monthLabel' => $start->format('F Y'),
            'dates' => $dates,
            'rows' => $rows,
            'legend' => $legend,
            'customShifts' => $customShifts,
            'defaultShift' => $defaultShift,
            'stats' => [
                'employees' => $rows->count(),
                'shift_days' => (int) $rows->sum('shift_days'),
                'weekoffs' => (int) $rows->sum('weekoffs'),
                'unassigned' => (int) $rows->sum('unassigned'),
                'shifts_in_use' => $legend->count(),
            ],
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'branches' => CompanyBranch::where('tenant_id', $tenantId)->active()->orderBy('name')->get(['id', 'name']),
            'shiftOptions' => $shifts->where('status', 1)->sortBy('name')->values(),
            'filters' => [
                'search' => $request->query('search'),
                'department_id' => $request->query('department_id'),
                'branch_id' => $request->query('branch_id'),
                'shift_id' => $request->query('shift_id'),
            ],
        ];
    }

    private function shiftCell(Shift $shift): array
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $shift->color_code) ? $shift->color_code : '#0D6EFD';

        return [
            'type' => 'shift',
            'shift_id' => $shift->id,
            'name' => $shift->name,
            'short' => $this->shortLabel((string) $shift->name),
            'color' => $color,
            'start' => $this->hhmm($shift->start_time),
            'end' => $this->hhmm($shift->end_time),
        ];
    }

    /** "Morning Shift" → "MS", "Night" → "NIG" — a compact label for the matrix cell. */
    private function shortLabel(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(count($words) > 1
            ? implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 3)))
            : mb_substr($name, 0, 3));
    }

    private function hhmm(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }
}
