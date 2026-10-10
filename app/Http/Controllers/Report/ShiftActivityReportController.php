<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\ShiftChangeLog;
use App\Models\ShiftRequest;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shift traceability reports (shift-requests plan Phase 7):
 *  - Shift Change Log — every shift_change_logs row (who changed whose shift
 *    on which day, from → to, how and why);
 *  - Shift Requests Register — one row per swap / change request (or direct
 *    swap / change) with its status, decision and turnaround time.
 * Both have a CSV export of exactly what the filters show.
 *
 * `role:admin,hr,manager` (the whole Reports area) + `feature:custom_shift`;
 * a manager only sees their own reportees, same as the roster.
 */
class ShiftActivityReportController extends Controller
{
    use SanitizesCsv;
    use \App\Http\Controllers\Concerns\FiltersReportEmployees;

    private const PER_PAGE = 50;
    private const MAX_DAYS = 366;

    public const CHANGE_TYPE_LABELS = [
        'assign' => 'Assigned', 'change' => 'Changed', 'remove' => 'Removed', 'swap' => 'Swapped',
        'request_change' => 'Change request', 'rotation' => 'Rotation', 'revert' => 'Reverted',
    ];

    // ------------------------------------------------------------------
    // Shift Change Log
    // ------------------------------------------------------------------

    public function changeLog(Request $request)
    {
        try {
            $f = $this->filters($request);
            $query = $this->changeLogQuery($f, $request);

            $stats = DB::query()->fromSub($query, 's')->selectRaw(
                "COUNT(*) as total, COUNT(DISTINCT user_id) as employees,
                 SUM(change_type = 'swap') as swaps, SUM(shift_request_id IS NOT NULL) as from_requests"
            )->first();

            $rows = (clone $query)->orderByDesc('l.created_at')->orderByDesc('l.id')
                ->paginate(self::PER_PAGE)->withQueryString();

            return view('client.report.attendance.shift-change-log', [
                'rows' => $rows,
                'stats' => $stats,
                'filters' => $f,
                'sources' => ShiftChangeLog::SOURCE_LABELS,
                'changeTypes' => self::CHANGE_TYPE_LABELS,
            ] + $this->options($f['tenant_id']));
        } catch (Exception $e) {
            Log::error('Shift change log report error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to load the shift change log.');
        }
    }

    public function changeLogExport(Request $request)
    {
        try {
            $f = $this->filters($request);

            return $this->csv('shift-change-log-' . $f['start'] . '-to-' . $f['end'] . '.csv', [
                'Changed On', 'Shift Date', 'Employee ID', 'Employee', 'Department', 'Branch', 'From Shift', 'To Shift',
                'Additional Shift', 'Change', 'How', 'Request No.', 'Changed By', 'Role', 'Channel', 'Reason',
            ], function ($write) use ($f, $request) {
                foreach ($this->changeLogQuery($f, $request)->orderBy('l.created_at')->orderBy('l.id')->cursor() as $r) {
                    $write([
                        Carbon::parse($r->created_at)->format('Y-m-d H:i'), substr((string) $r->date, 0, 10), $r->employee_code, $r->employee_name,
                        $r->department_name, $r->branch_name, $r->from_shift_name ?? '—', $r->to_shift_name ?? '—',
                        $r->is_additional ? 'Yes' : 'No', self::CHANGE_TYPE_LABELS[$r->change_type] ?? $r->change_type,
                        ShiftChangeLog::SOURCE_LABELS[$r->source] ?? $r->source, $r->request_no, $r->actor_name ?? 'System',
                        $r->actor_role, $r->channel, $r->reason,
                    ]);
                }
            });
        } catch (Exception $e) {
            Log::error('Shift change log export error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to export the shift change log.');
        }
    }

    private function changeLogQuery(array $f, Request $request): Builder
    {
        $col = $f['date_by'] === 'shift' ? 'l.date' : DB::raw('DATE(l.created_at)');

        $q = DB::table('shift_change_logs as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('user_job_details as uj', 'uj.user_id', '=', 'l.user_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'uj.department')
            ->leftJoin('company_branches as cb', 'cb.id', '=', 'uj.branch_id')
            ->leftJoin('shifts as fs', 'fs.id', '=', 'l.from_shift_id')
            ->leftJoin('shifts as ts', 'ts.id', '=', 'l.to_shift_id')
            ->leftJoin('users as act', 'act.id', '=', 'l.actor_id')
            ->leftJoin('shift_requests as sr', 'sr.id', '=', 'l.shift_request_id')
            ->where('l.tenant_id', $f['tenant_id'])
            ->whereBetween($col, [$f['start'], $f['end']])
            ->select([
                'l.*', 'u.name as employee_name', 'u.employee_id as employee_code', 'u.email as employee_email',
                'dp.name as department_name', 'cb.name as branch_name',
                'fs.name as from_shift_name', 'fs.color_code as from_color', 'ts.name as to_shift_name', 'ts.color_code as to_color',
                'act.name as actor_name', 'sr.request_no',
            ]);

        $this->scopeEmployees($q, $f, ['l.user_id']);
        $this->applyReportEmployeeFilters($q, $request);
        if ($f['source'] !== null) {
            $q->where('l.source', $f['source']);
        }
        if ($f['change_type'] !== null) {
            $q->where('l.change_type', $f['change_type']);
        }
        if ($f['actor_id'] !== null) {
            $q->where('l.actor_id', $f['actor_id']);
        }

        return $q;
    }

    // ------------------------------------------------------------------
    // Shift Requests Register
    // ------------------------------------------------------------------

    public function requests(Request $request)
    {
        try {
            $f = $this->filters($request);
            $query = $this->requestsQuery($f, $request);

            $stats = DB::query()->fromSub($query, 's')->selectRaw(
                "COUNT(*) as total,
                 SUM(status IN ('pending_peer','pending_approval')) as pending,
                 SUM(status = 'approved') as approved,
                 SUM(status IN ('rejected','peer_declined')) as rejected,
                 AVG(CASE WHEN decided_at IS NOT NULL AND mode = 'request' THEN TIMESTAMPDIFF(MINUTE, created_at, decided_at) END) as avg_minutes"
            )->first();

            $rows = (clone $query)->orderByDesc('r.created_at')->orderByDesc('r.id')
                ->paginate(self::PER_PAGE)->withQueryString();

            return view('client.report.attendance.shift-requests-register', [
                'rows' => $rows,
                'stats' => $stats,
                'filters' => $f,
                'statuses' => ShiftRequest::STATUS_LABELS,
                'shifts' => $this->itemSummaries($rows->getCollection()->pluck('id')->all()),
            ] + $this->options($f['tenant_id']));
        } catch (Exception $e) {
            Log::error('Shift requests register error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to load the shift requests register.');
        }
    }

    public function requestsExport(Request $request)
    {
        try {
            $f = $this->filters($request);

            return $this->csv('shift-requests-' . $f['start'] . '-to-' . $f['end'] . '.csv', [
                'Request No.', 'Raised On', 'Type', 'Mode', 'Requested By', 'Employee ID', 'Department', 'Branch', 'Colleague',
                'Shift Dates', 'Shifts', 'Status', 'Decided By', 'Decided On', 'Turnaround', 'Reason', 'Colleague Remarks', 'Approver Remarks', 'Channel',
            ], function ($write) use ($f, $request) {
                foreach ($this->requestsQuery($f, $request)->orderBy('r.created_at')->orderBy('r.id')->get()->chunk(200) as $chunk) {
                    $items = $this->itemSummaries($chunk->pluck('id')->all());
                    foreach ($chunk as $r) {
                        $write([
                            $r->request_no, Carbon::parse($r->created_at)->format('Y-m-d H:i'), $r->type === 'swap' ? 'Shift swap' : 'Shift change',
                            $r->mode === 'direct' ? 'Direct (admin)' : 'Request', $r->requester_name, $r->requester_code, $r->department_name,
                            $r->branch_name, $r->counterpart_name, $items[$r->id]['dates'] ?? '', $items[$r->id]['shifts'] ?? '',
                            ShiftRequest::STATUS_LABELS[$r->status] ?? $r->status, $r->decider_name,
                            $r->decided_at ? Carbon::parse($r->decided_at)->format('Y-m-d H:i') : '', self::turnaround($r),
                            $r->reason, $r->peer_remarks, $r->approver_remarks, $r->channel,
                        ]);
                    }
                }
            });
        } catch (Exception $e) {
            Log::error('Shift requests register export error: ' . $e->getMessage());

            return redirect()->route('report.attendance.index')->with('error', 'Failed to export the shift requests register.');
        }
    }

    /** "3 h 20 m" from raised to decided; '' while undecided or for a direct (saved-approved) change. */
    public static function turnaround(object $r): string
    {
        if (! $r->decided_at || $r->mode === 'direct') {
            return '';
        }
        $minutes = max(0, (int) Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->decided_at)));
        if ($minutes >= 1440) {
            return intdiv($minutes, 1440) . ' d ' . intdiv($minutes % 1440, 60) . ' h';
        }

        return ($minutes >= 60 ? intdiv($minutes, 60) . ' h ' : '') . ($minutes % 60) . ' m';
    }

    private function requestsQuery(array $f, Request $request): Builder
    {
        $col = $f['date_by'] === 'shift' ? null : DB::raw('DATE(r.created_at)');

        $q = DB::table('shift_requests as r')
            ->join('users as u', 'u.id', '=', 'r.requester_id')
            ->leftJoin('user_job_details as uj', 'uj.user_id', '=', 'r.requester_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'uj.department')
            ->leftJoin('company_branches as cb', 'cb.id', '=', 'uj.branch_id')
            ->leftJoin('users as cp', 'cp.id', '=', 'r.counterpart_id')
            ->leftJoin('users as dc', 'dc.id', '=', 'r.decided_by')
            ->where('r.tenant_id', $f['tenant_id'])
            ->select([
                'r.*', 'u.name as requester_name', 'u.employee_id as requester_code', 'u.email as requester_email',
                'dp.name as department_name', 'cb.name as branch_name', 'cp.name as counterpart_name', 'dc.name as decider_name',
            ]);

        // Date range on the day raised, or on any shift date in the request.
        if ($col) {
            $q->whereBetween($col, [$f['start'], $f['end']]);
        } else {
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('shift_request_items as i')
                ->whereColumn('i.shift_request_id', 'r.id')->whereBetween('i.date', [$f['start'], $f['end']]));
        }

        $this->scopeEmployees($q, $f, ['r.requester_id', 'r.counterpart_id']);
        $this->applyReportEmployeeFilters($q, $request);
        if ($f['status'] !== null) {
            $f['status'] === 'pending' ? $q->whereIn('r.status', ShiftRequest::PENDING) : $q->where('r.status', $f['status']);
        }
        if ($f['type'] !== null) {
            $q->where('r.type', $f['type']);
        }
        if ($f['actor_id'] !== null) {
            $q->where('r.decided_by', $f['actor_id']);
        }

        return $q;
    }

    /** request id => ['dates' => '12 Oct, 13 Oct', 'shifts' => 'Asha: Morning → Night; …'] */
    private function itemSummaries(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        $items = DB::table('shift_request_items as i')
            ->join('users as u', 'u.id', '=', 'i.user_id')
            ->leftJoin('shifts as fs', 'fs.id', '=', 'i.from_shift_id')
            ->leftJoin('shifts as ts', 'ts.id', '=', 'i.to_shift_id')
            ->whereIn('i.shift_request_id', $requestIds)
            ->orderBy('i.date')->orderBy('i.id')
            ->get(['i.shift_request_id', 'i.date', 'u.name', 'fs.name as from_name', 'ts.name as to_name'])
            ->groupBy('shift_request_id');

        return $items->map(fn ($rows) => [
            'dates' => $rows->pluck('date')->unique()->map(fn ($d) => Carbon::parse($d)->format('d M Y'))->implode(', '),
            'shifts' => $rows->map(fn ($i) => $i->name . ' ' . Carbon::parse($i->date)->format('d M') . ': ' . ($i->from_name ?? 'None') . ' → ' . ($i->to_name ?? 'None'))->implode('; '),
            'lines' => $rows->all(),
        ])->all();
    }

    // ------------------------------------------------------------------
    // Shared
    // ------------------------------------------------------------------

    /** Validated filters; dates default to the current month. */
    private function filters(Request $request): array
    {
        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        $start = $date($request->query('start_date')) ?? now()->startOfMonth()->toDateString();
        $end = $date($request->query('end_date')) ?? now()->endOfMonth()->toDateString();
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > self::MAX_DAYS) {
            $end = Carbon::parse($start)->addDays(self::MAX_DAYS)->toDateString();
        }

        $int = fn ($k) => $request->filled($k) ? (int) $request->query($k) : null;
        $status = (string) $request->query('status');

        return [
            'tenant_id' => (int) Auth::user()->tenant_id,
            'start' => $start,
            'end' => $end,
            'date_by' => $request->query('date_by') === 'shift' ? 'shift' : 'changed',
            'search' => trim((string) $request->query('search', '')),
            'user_id' => $int('user_id'),
            'actor_id' => $int('actor_id'),
            'source' => array_key_exists((string) $request->query('source'), ShiftChangeLog::SOURCE_LABELS) ? $request->query('source') : null,
            'change_type' => array_key_exists((string) $request->query('change_type'), self::CHANGE_TYPE_LABELS) ? $request->query('change_type') : null,
            'status' => $status === 'pending' || array_key_exists($status, ShiftRequest::STATUS_LABELS) ? $status : null,
            'type' => in_array($request->query('type'), ['swap', 'change'], true) ? $request->query('type') : null,
        ];
    }

    /** Manager → only reportees; employee picker + name/ID search on the given user columns. */
    private function scopeEmployees(Builder $q, array $f, array $userCols): void
    {
        $auth = Auth::user();
        if ($auth->role === 'manager') {
            $team = User::where('tenant_id', $f['tenant_id'])->managedBy($auth->id)->pluck('id')->all();
            $q->where(fn ($w) => collect($userCols)->each(fn ($c) => $w->orWhereIn($c, $team)));
        }
        if ($f['user_id'] !== null) {
            $q->where(fn ($w) => collect($userCols)->each(fn ($c) => $w->orWhere($c, $f['user_id'])));
        }
        if ($f['search'] !== '') {
            $s = '%' . addcslashes($f['search'], '%_\\') . '%';
            $q->where(fn ($w) => $w->where('u.name', 'LIKE', $s)->orWhere('u.employee_id', 'LIKE', $s));
        }
    }

    private function options(int $tenantId): array
    {
        $employees = User::where('tenant_id', $tenantId)->where('role', '!=', 'superadmin');
        if (Auth::user()->role === 'manager') {
            $employees->managedBy(Auth::id());
        }

        return [
            'employees' => $employees->orderBy('name')->get(['id', 'name', 'employee_id']),
            // Who can change shifts / decide requests.
            'actors' => User::where('tenant_id', $tenantId)->whereIn('role', ['admin', 'hr', 'manager'])->where('status', 1)
                ->orderBy('name')->get(['id', 'name', 'role']),
        ];
    }

    private function csv(string $filename, array $header, callable $fill)
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeCsvRow($handle, $header);
        $fill(fn (array $row) => $this->writeCsvRow($handle, $row));
        rewind($handle);
        $body = stream_get_contents($handle);
        fclose($handle);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
