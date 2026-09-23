<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Concerns\SanitizesCsv;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\Department;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Request as WorkRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Leave Reports — Leave Balance, Leave (applications) Report, a by-type/department Leave Summary,
 * Regularization Report and WFH & Travel Report, all in one tab. Rows are scoped by the caller's
 * RBAC view scope for the owning module (`leave`/`attendance`/`requests` — company for admin/hr,
 * team for a manager, matching what those modules already grant on their own screens); the tab
 * itself needs no extra gate since the whole Reports page is already `role:admin,hr,manager`.
 */
class LeaveReportController extends Controller
{
    use SanitizesCsv;

    public const REPORTS = [
        'balance' => 'Leave Balance',
        'register' => 'Leave Report',
        'summary' => 'Leave Summary',
        'regularization' => 'Regularization Report',
        'wfh-travel' => 'WFH & Travel Report',
    ];

    public function show(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $user = $request->user();

        $data = match ($report) {
            'balance' => $this->balance($request, $user),
            'register' => $this->register($request, $user),
            'summary' => $this->summary($request, $user),
            'regularization' => $this->regularization($request, $user),
            'wfh-travel' => $this->wfhTravel($request, $user),
        };

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $data);
        }

        return view('client.report.leave.reports', $data + [
            'report' => $report,
            'reports' => self::REPORTS,
            'filters' => $request->query(),
            'departments' => Department::where('status', 1)->orderBy('name')->get(['id', 'name']),
            'leaveTypes' => LeaveType::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ==================== reports ====================

    private function balance(Request $request, User $user): array
    {
        $query = LeaveBalance::query()
            ->join('users', 'users.id', '=', 'leave_balances.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->join('leave_types as lt', 'lt.id', '=', 'leave_balances.leave_type_id');

        $this->scopeByUser($query, 'leave_balances.user_id', $user, 'leave');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_balances.leave_type_id', (int) $request->query('leave_type_id'));
        }

        $rows = $query->orderBy('users.name')->orderBy('lt.name')
            ->get(['users.name', 'users.employee_id', 'd.name as department', 'lt.name as leave_type', 'leave_balances.balance']);

        return [
            'title' => 'Leave Balance',
            'headers' => ['Employee ID', 'Employee', 'Department', 'Leave Type', 'Balance'],
            'rows' => $rows->map(fn ($r) => [$r->employee_id, $r->name, $r->department ?? '—', $r->leave_type, number_format((float) $r->balance, 1)])->all(),
            'summary' => [
                'Employees' => $rows->pluck('name')->unique()->count(),
                'Rows' => $rows->count(),
                'Total balance days' => number_format((float) $rows->sum('balance'), 1),
            ],
        ];
    }

    private function register(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);

        $query = Leave::query()
            ->join('users', 'users.id', '=', 'leaves.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->leftJoin('leave_types as lt', 'lt.id', '=', 'leaves.leave_type')
            ->whereBetween('leaves.start_date', [$from, $to]);

        $this->scopeByUser($query, 'leaves.user_id', $user, 'leave');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leaves.leave_type', (int) $request->query('leave_type_id'));
        }
        if (in_array($request->query('status'), ['pending', 'approved', 'cancelled'], true)) {
            $query->where('leaves.status', $request->query('status'));
        }

        $rows = $query->orderByDesc('leaves.start_date')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'lt.name as leave_type',
                'leaves.start_date', 'leaves.end_date', 'leaves.total_days', 'leaves.status',
            ]);

        return [
            'title' => 'Leave Report',
            'from' => $from, 'to' => $to,
            'headers' => ['Employee ID', 'Employee', 'Department', 'Leave Type', 'From', 'To', 'Days', 'Status'],
            'rows' => $rows->map(fn ($r) => [
                $r->employee_id, $r->name, $r->department ?? '—', $r->leave_type ?? '—',
                Carbon::parse($r->start_date)->format('d M Y'), Carbon::parse($r->end_date)->format('d M Y'),
                number_format((float) $r->total_days, 1), ucfirst($r->status),
            ])->all(),
            'summary' => [
                'Applications' => $rows->count(),
                'Approved' => $rows->where('status', 'approved')->count(),
                'Pending' => $rows->where('status', 'pending')->count(),
                'Total days' => number_format((float) $rows->sum('total_days'), 1),
            ],
        ];
    }

    private function summary(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);

        $query = Leave::query()
            ->join('leave_types as lt', 'lt.id', '=', 'leaves.leave_type')
            ->whereBetween('leaves.start_date', [$from, $to]);

        $this->scopeByUser($query, 'leaves.user_id', $user, 'leave');

        $rows = $query->groupBy('lt.id', 'lt.name')->orderBy('lt.name')
            ->selectRaw("lt.name AS leave_type, COUNT(*) AS applications,
                COALESCE(SUM(CASE WHEN leaves.status = 'approved' THEN leaves.total_days END), 0) AS approved_days,
                COALESCE(SUM(CASE WHEN leaves.status = 'pending' THEN leaves.total_days END), 0) AS pending_days,
                SUM(leaves.status = 'approved') AS approved_count,
                SUM(leaves.status = 'pending') AS pending_count,
                SUM(leaves.status = 'cancelled') AS cancelled_count")
            ->get();

        return [
            'title' => 'Leave Summary by Type',
            'from' => $from, 'to' => $to,
            'headers' => ['Leave Type', 'Applications', 'Approved (days)', 'Pending (days)', 'Approved', 'Pending', 'Cancelled'],
            'rows' => $rows->map(fn ($r) => [
                $r->leave_type, (int) $r->applications, number_format((float) $r->approved_days, 1), number_format((float) $r->pending_days, 1),
                (int) $r->approved_count, (int) $r->pending_count, (int) $r->cancelled_count,
            ])->all(),
            'summary' => [
                'Leave types' => $rows->count(),
                'Applications' => (int) $rows->sum('applications'),
                'Approved days' => number_format((float) $rows->sum('approved_days'), 1),
            ],
        ];
    }

    private function regularization(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);

        $query = AttendanceRegularization::query()
            ->join('users', 'users.id', '=', 'attendance_regularizations.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->whereBetween('attendance_regularizations.date', [$from, $to]);

        $this->scopeByUser($query, 'attendance_regularizations.user_id', $user, 'attendance');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if (in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)) {
            $query->where('attendance_regularizations.status', $request->query('status'));
        }

        $rows = $query->orderByDesc('attendance_regularizations.date')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'attendance_regularizations.date',
                'attendance_regularizations.request_type', 'attendance_regularizations.in_time', 'attendance_regularizations.out_time',
                'attendance_regularizations.reason', 'attendance_regularizations.status',
            ]);

        return [
            'title' => 'Regularization Report',
            'from' => $from, 'to' => $to,
            'headers' => ['Employee ID', 'Employee', 'Department', 'Date', 'Type', 'In', 'Out', 'Reason', 'Status'],
            'rows' => $rows->map(fn ($r) => [
                $r->employee_id, $r->name, $r->department ?? '—', Carbon::parse($r->date)->format('d M Y'), ucfirst(str_replace('_', ' ', $r->request_type ?? '—')),
                $r->in_time ?? '—', $r->out_time ?? '—', \Illuminate\Support\Str::limit($r->reason ?? '', 60), ucfirst($r->status),
            ])->all(),
            'summary' => [
                'Requests' => $rows->count(),
                'Approved' => $rows->where('status', 'approved')->count(),
                'Pending' => $rows->where('status', 'pending')->count(),
                'Rejected' => $rows->where('status', 'rejected')->count(),
            ],
        ];
    }

    private function wfhTravel(Request $request, User $user): array
    {
        [$from, $to] = $this->period($request);

        $query = WorkRequest::query()
            ->join('users', 'users.id', '=', 'requests.user_id')
            ->leftJoin('user_job_details as ujd', 'ujd.user_id', '=', 'users.id')
            ->leftJoin('departments as d', 'd.id', '=', 'ujd.department')
            ->join('request_types as rt', 'rt.id', '=', 'requests.request_type_id')
            ->whereBetween('requests.start_date', [$from, $to]);

        $this->scopeByUser($query, 'requests.user_id', $user, 'requests');

        if ($request->filled('department_id')) {
            $query->where('ujd.department', (int) $request->query('department_id'));
        }
        if ($request->filled('request_type_id')) {
            $query->where('requests.request_type_id', (int) $request->query('request_type_id'));
        }
        if (in_array($request->query('status'), ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'], true)) {
            $query->where('requests.status', $request->query('status'));
        }

        $rows = $query->orderByDesc('requests.start_date')
            ->get([
                'users.name', 'users.employee_id', 'd.name as department', 'rt.type_name',
                'requests.start_date', 'requests.end_date', 'requests.reason', 'requests.status',
            ]);

        return [
            'title' => 'WFH & Travel Report',
            'from' => $from, 'to' => $to,
            'headers' => ['Employee ID', 'Employee', 'Department', 'Type', 'From', 'To', 'Reason', 'Status'],
            'rows' => $rows->map(fn ($r) => [
                $r->employee_id, $r->name, $r->department ?? '—', $r->type_name,
                Carbon::parse($r->start_date)->format('d M Y'), Carbon::parse($r->end_date)->format('d M Y'),
                \Illuminate\Support\Str::limit($r->reason ?? '', 60), ucfirst(strtolower($r->status)),
            ])->all(),
            'summary' => [
                'Requests' => $rows->count(),
                'Approved' => $rows->where('status', 'APPROVED')->count(),
                'Pending' => $rows->where('status', 'PENDING')->count(),
            ],
            'requestTypes' => RequestType::where('is_active', 1)->orderBy('type_name')->get(['id', 'type_name']),
        ];
    }

    // ==================== helpers ====================

    /** null scope (no permission) aborts 403; 'company' = no filter, else scoped to the user's own/team ids. */
    private function scopeByUser($query, string $column, User $user, string $module): void
    {
        $scope = app(RbacService::class)->scopeFor($user, $module, 'view');
        abort_if($scope === null, 403, 'You do not have permission to view this report.');

        if ($scope === 'company') {
            return;
        }

        $ids = $scope === 'own'
            ? [$user->id]
            : User::managedBy($user->id)->pluck('id')->push($user->id)->unique()->values()->all();

        $query->whereIn($column, $ids);
    }

    /** @return array{0: string, 1: string} inclusive Y-m-d range; defaults to the current month */
    private function period(Request $request): array
    {
        $from = $request->filled('from_date') ? Carbon::parse($request->query('from_date'))->toDateString() : now()->startOfMonth()->toDateString();
        $to = $request->filled('to_date') ? Carbon::parse($request->query('to_date'))->toDateString() : now()->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function csv(string $report, array $data)
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeCsvRow($handle, $data['headers']);
        foreach ($data['rows'] as $row) {
            $this->writeCsvRow($handle, $row);
        }
        rewind($handle);
        $body = stream_get_contents($handle);
        fclose($handle);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="leave-' . $report . '-' . now()->format('Ymd') . '.csv"',
        ]);
    }
}
