<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Attendance\PolicyResolver;
use App\Services\Attendance\TenantShiftResolver;
use App\Services\EmployeePolicyService;
use App\Services\FeatureService;
use App\Services\Performance\PerformancePolicyResolver;
use App\Services\RbacService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Employee 360 page — the lazy tabs on the employee detail page
 * (`employee.show`, UserController::show). Each tab is one AJAX partial with
 * only this employee's data, gated by the company's plan features.
 * Admin + HR only (route middleware). The actions on each tab are in
 * EmployeeProfileActionController.
 */
class EmployeeProfileController extends Controller
{
    /** tab => plan features (any one enables it; empty = always on) */
    public const TABS = [
        'overview' => [],
        'shift' => ['fixed_shift', 'custom_shift'],
        'attendance' => ['attendance'],
        'leave' => ['leave_management'],
        'holidays' => ['holiday'],
        'payroll' => ['payroll', 'loan_management'],
        'expenses' => ['expense_management'],
        'tasks' => ['task_single', 'task_group', 'project_management'],
        'assets' => ['asset_management'],
        'performance' => ['kpi_performance'],
        'policies' => [],
        'activity' => [],
    ];

    public function __construct(private FeatureService $features)
    {
    }

    /** Lazy tabs this company's plan includes. */
    public function enabledTabs(): array
    {
        return array_values(array_filter(array_keys(self::TABS), fn ($tab) => $this->tabEnabled($tab)));
    }

    public function tab(Request $request, string $id, string $tab)
    {
        abort_unless(array_key_exists($tab, self::TABS), 404);
        abort_unless($this->tabEnabled($tab), 403, 'This module is not included in your company\'s plan.');

        $user = $this->employee($id);
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $this->{'tab' . ucfirst($tab)}($user, $tenantId, $request);

        return view('client.user.profile.' . $tab, $data + ['user' => $user, 'encId' => $id, 'can' => $this->abilities()]);
    }

    /**
     * What the signed-in admin / HR user may do on this page — decides which
     * action buttons show. The endpoints enforce the same rules themselves.
     */
    public function abilities(): array
    {
        $rbac = app(RbacService::class);
        $actor = Auth::user();
        $can = fn (string $module, string $action) => $rbac->can($actor, $module, $action);

        return [
            'leave_manage' => $can('leave', 'manage'),
            'leave_approve' => $can('leave', 'approve'),
            'attendance_approve' => $can('attendance', 'approve') && $this->features->enabledForCurrentTenant('regularization'),
            'expenses_approve' => $can('expenses', 'approve'),
            'assets_manage' => $can('assets', 'manage'),
            'payroll_view' => $can('payroll', 'view'),
            'payroll_edit' => $can('payroll', 'edit') && $this->features->enabledForCurrentTenant('payroll'),
            'custom_shifts' => app(TenantShiftResolver::class)->isCustomShifts((int) $actor->tenant_id),
        ];
    }

    protected function tabEnabled(string $tab): bool
    {
        $keys = self::TABS[$tab] ?? [];
        if ($keys === []) {
            return true;
        }
        foreach ($keys as $key) {
            if ($this->features->enabledForCurrentTenant($key)) {
                return true;
            }
        }

        return false;
    }

    /** The employee, in the caller's company; same rule as the detail page (no admins). */
    protected function employee(string $encryptedId): User
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Throwable $e) {
            report($e);
            abort(404);
        }

        return User::withoutGlobalScopes()
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('role', '!=', 'admin')
            ->firstOrFail();
    }

    // ------------------------------------------------------------------ tabs

    private function tabOverview(User $user, int $tenantId): array
    {
        $ym = now()->format('Y-m');
        $summary = DB::table('attendance_summaries')
            ->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('year_month', $ym)
            ->whereNull('deleted_at')
            ->first();

        return [
            'month' => now()->format('F Y'),
            'presentDays' => $summary->present_days ?? DB::table('attendances')
                ->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->where('date', 'like', $ym . '%')->whereNotNull('clock_in')->count(),
            'lateDays' => (int) ($summary->late_days ?? 0),
            'leaveBalance' => (float) DB::table('leave_balances')->where('tenant_id', $tenantId)->where('user_id', $user->id)->sum('balance'),
            'pending' => [
                'leaves' => DB::table('leaves')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', 'pending')->count(),
                'regularizations' => DB::table('attendance_regularizations')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('status', 'pending')->count(),
                'expenses' => DB::table('expenses')->where('tenant_id', $tenantId)->where('user_id', $user->id)->whereNull('deleted_at')->where('status', 'pending')->count(),
            ],
            'openTasks' => DB::table('task_assigns')->join('tasks', 'tasks.id', '=', 'task_assigns.task_id')
                ->where('task_assigns.tenant_id', $tenantId)->where('task_assigns.assigned_to', $user->id)
                ->whereNotIn('tasks.status', ['completed', 'cancelled'])->count(),
            'assets' => DB::table('asset_assignments')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->whereIn('status', ['accepted', 'pending_acceptance'])->count(),
        ];
    }

    private function tabShift(User $user, int $tenantId): array
    {
        $resolver = app(TenantShiftResolver::class);
        $days = [];
        for ($d = Carbon::today(); $d->lte(Carbon::today()->addDays(13)); $d->addDay()) {
            $days[] = [
                'date' => $d->copy(),
                'shifts' => $resolver->instancesForUserDate($user->id, $tenantId, $d->toDateString()),
            ];
        }

        return [
            'customShifts' => $resolver->isCustomShifts($tenantId),
            'defaultShift' => $resolver->defaultShift($tenantId),
            'days' => $days,
            'assignments' => DB::table('shift_assignments as sa')
                ->leftJoin('shifts as s', 's.id', '=', 'sa.shift_id')
                ->where('sa.tenant_id', $tenantId)->where('sa.user_id', $user->id)
                ->orderByRaw("sa.status = 'active' desc")->orderByDesc('sa.start_date')->limit(15)
                ->get(['sa.*', 's.name as shift_name', 's.start_time', 's.end_time', 's.is_overnight']),
            'weekoffs' => DB::table('user_weekoffs')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->where('status', 1)->orderBy('off_type')->orderBy('start_date')->get(),
            // Swap / change requests this employee raised or is the colleague in.
            'shiftRequests' => \App\Models\ShiftRequest::where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('counterpart_id', $user->id))
                ->with(['requester:id,name', 'counterpart:id,name', 'decider:id,name', 'items.user:id,name', 'items.fromShift:id,name', 'items.toShift:id,name'])
                ->orderByDesc('id')->limit(15)->get(),
            // Every change to this employee's shifts (shift_change_logs).
            'changeLog' => DB::table('shift_change_logs as l')
                ->leftJoin('shifts as fs', 'fs.id', '=', 'l.from_shift_id')
                ->leftJoin('shifts as ts', 'ts.id', '=', 'l.to_shift_id')
                ->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
                ->leftJoin('shift_requests as sr', 'sr.id', '=', 'l.shift_request_id')
                ->where('l.tenant_id', $tenantId)->where('l.user_id', $user->id)
                ->orderByDesc('l.id')->limit(25)
                ->get(['l.*', 'fs.name as from_name', 'ts.name as to_name', 'a.name as actor_name', 'sr.request_no']),
        ];
    }

    private function tabAttendance(User $user, int $tenantId, Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::parse($month . '-01');

        $rows = DB::table('attendances')
            ->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get(['id', 'date', 'clock_in', 'clock_out', 'worked_hours', 'attendance_status', 'effective_status',
                'late_minutes', 'early_departure_minutes', 'shift_count', 'extra_shift_minutes', 'is_regularized', 'attendance_type'])
            ->keyBy(fn ($r) => substr((string) $r->date, 0, 10));

        return [
            'month' => $month,
            'monthStart' => $start,
            'rows' => $rows,
            'summary' => DB::table('attendance_summaries')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->where('year_month', $month)->whereNull('deleted_at')->first(),
            'regularizations' => DB::table('attendance_regularizations')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->orderByDesc('date')->limit(10)->get(),
        ];
    }

    private function tabLeave(User $user, int $tenantId): array
    {
        return [
            'balances' => DB::table('leave_balances as b')->join('leave_types as t', 't.id', '=', 'b.leave_type_id')
                ->where('b.tenant_id', $tenantId)->where('b.user_id', $user->id)
                ->orderBy('t.name')->get(['t.name', 't.code', 't.is_unpaid', 'b.balance']),
            'leaves' => DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type')
                ->where('l.tenant_id', $tenantId)->where('l.user_id', $user->id)
                ->orderByDesc('l.start_date')->limit(20)
                ->get(['l.*', 't.name as type_name']),
            'transactions' => DB::table('leave_transactions as x')->leftJoin('leave_types as t', 't.id', '=', 'x.leave_type')
                ->where('x.tenant_id', $tenantId)->where('x.user_id', $user->id)
                ->orderByDesc('x.id')->limit(10)
                ->get(['x.*', 't.name as type_name']),
        ];
    }

    private function tabHolidays(User $user, int $tenantId, Request $request): array
    {
        $year = (int) ($request->query('year') ?: now()->year);

        return [
            'year' => $year,
            'holidays' => DB::table('holidays')->where('tenant_id', $tenantId)
                ->where(fn ($q) => $q->whereYear('start_date', $year)->orWhereYear('end_date', $year))
                ->orderBy('start_date')->get(),
        ];
    }

    private function tabPayroll(User $user, int $tenantId): array
    {
        return [
            'dynamic' => (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled'),
            'structure' => DB::table('payroll_employee_structures')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->where('is_current', 1)->whereNull('deleted_at')->first(),
            'revisions' => DB::table('payroll_employee_structures')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->whereNull('deleted_at')->orderByDesc('effective_from')->limit(5)->get(),
            'payslips' => DB::table('monthly_payrolls')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->orderByDesc('payroll_month')->limit(12)
                ->get(['id', 'payroll_month', 'payable_days', 'gross_earnings', 'total_deductions', 'net_payable', 'payment_status']),
            'loans' => DB::table('loans')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->whereNull('deleted_at')->orderByDesc('loan_date')->limit(10)->get(),
            'bonuses' => DB::table('payroll_bonuses')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->orderByDesc('id')->limit(10)->get(),
            'canPayroll' => $this->features->enabledForCurrentTenant('payroll'),
            'canLoans' => $this->features->enabledForCurrentTenant('loan_management'),
        ];
    }

    private function tabExpenses(User $user, int $tenantId): array
    {
        $base = DB::table('expenses')->where('tenant_id', $tenantId)->where('user_id', $user->id)->whereNull('deleted_at');

        return [
            'expenses' => (clone $base)->orderByDesc('date')->orderByDesc('id')->limit(20)->get(),
            'byStatus' => (clone $base)->groupBy('status')->select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(amount) total'))->get(),
            'balance' => DB::table('user_expense_balances')->where('tenant_id', $tenantId)->where('user_id', $user->id)->first(),
        ];
    }

    private function tabTasks(User $user, int $tenantId): array
    {
        return [
            'tasks' => DB::table('task_assigns as a')->join('tasks as t', 't.id', '=', 'a.task_id')
                ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
                ->where('a.tenant_id', $tenantId)->where('a.assigned_to', $user->id)
                ->orderByRaw("t.status in ('completed','cancelled')")->orderBy('t.deadline_date')->limit(25)
                ->get(['t.id', 't.task_code', 't.title', 't.priority', 't.status', 't.deadline_date', 'a.progress_percentage', 'p.name as project_name']),
            'projects' => DB::table('project_assigns as pa')->join('projects as p', 'p.id', '=', 'pa.project_id')
                ->where('pa.tenant_id', $tenantId)->where('pa.user_id', $user->id)
                ->orderByDesc('p.start_date')->limit(15)
                ->get(['p.id', 'p.project_code', 'p.name', 'p.status', 'p.deadline_date', 'p.progress_percentage', 'pa.is_head']),
            'canProjects' => $this->features->enabledForCurrentTenant('project_management'),
        ];
    }

    private function tabAssets(User $user, int $tenantId): array
    {
        return [
            'assignments' => DB::table('asset_assignments as aa')->join('assets as a', 'a.id', '=', 'aa.asset_id')
                ->where('aa.tenant_id', $tenantId)->where('aa.user_id', $user->id)
                ->orderByRaw("aa.status in ('accepted','pending_acceptance') desc")->orderByDesc('aa.assigned_at')->limit(20)
                ->get(['aa.*', 'a.asset_code', 'a.name as asset_name', 'a.serial_number', 'a.condition']),
        ];
    }

    private function tabPerformance(User $user, int $tenantId): array
    {
        return [
            'scores' => DB::table('employee_kpi_scores')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->orderByDesc('reporting_month')->limit(6)->get(),
            'reviews' => DB::table('manager_performance_reviews as r')->leftJoin('users as u', 'u.id', '=', 'r.reviewer_id')
                ->where('r.tenant_id', $tenantId)->where('r.user_id', $user->id)
                ->orderByDesc('r.review_month')->limit(5)->get(['r.*', 'u.name as reviewer_name']),
        ];
    }

    private function tabPolicies(User $user, int $tenantId): array
    {
        $resolver = app(TenantShiftResolver::class);
        $job = DB::table('user_job_details')->where('user_id', $user->id)->first();

        $policy = app(EmployeePolicyService::class);

        // Company value vs this employee's custom value, per policy section.
        $sections = [];
        foreach ($this->policySections() as $section) {
            $sections[$section] = [
                'fields' => EmployeePolicyService::fields($section),
                'company' => $this->companyPolicy($section, $tenantId),
                'custom' => $policy->section($tenantId, $user->id, $section),
            ];
        }

        return [
            'sections' => $sections,
            'leaveEnabled' => $this->tabEnabled('leave'),
            'leaveCustom' => $policy->section($tenantId, $user->id, 'leave'),
            'leaveTypes' => DB::table('leave_types')->where('tenant_id', $tenantId)->where('status', 1)->orderBy('name')->get(),
            'shiftMode' => $resolver->isCustomShifts($tenantId) ? 'Custom per-employee shifts' : 'Fixed company shift',
            'defaultShift' => $resolver->defaultShift($tenantId),
            'attendanceType' => $job->attendance_type ?? null,
            'workType' => $job->type ?? null,
            'fieldTracking' => (bool) ($job->location_tracking_enabled ?? false),
        ];
    }

    /** The attendance / overtime / performance policy sections this company's plan includes. */
    protected function policySections(): array
    {
        $features = $this->features;

        return array_values(array_filter([
            $this->tabEnabled('attendance') ? 'attendance' : null,
            $features->enabledForCurrentTenant('overtime') ? 'overtime' : null,
            $this->tabEnabled('performance') ? 'performance' : null,
            ($features->enabledForCurrentTenant('wfh_travel') || $features->enabledForCurrentTenant('regularization')) ? 'requests' : null,
            $this->tabEnabled('expenses') ? 'expense' : null,
        ]));
    }

    /**
     * The company's own values for one policy section, keyed like
     * EmployeePolicyService::FIELDS — what an employee follows when they have
     * no custom value.
     */
    protected function companyPolicy(string $section, int $tenantId): array
    {
        if ($section === 'attendance') {
            return app(PolicyResolver::class)->forTenantDate($tenantId, now()->toDateString())->toPersistableArray();
        }
        if ($section === 'performance') {
            return app(PerformancePolicyResolver::class)->forTenantDate($tenantId, now()->toDateString())->toArray();
        }
        if ($section === 'requests') {
            return app(\App\Services\RequestLimitService::class)->company($tenantId);
        }
        if ($section === 'expense') {
            return ['monthly_limit' => 0]; // no company-wide limit — department budgets are separate
        }

        // Overtime: Company Policies → Overtime (or the code's defaults).
        $row = app(\App\Services\Attendance\OvertimePolicyService::class)->company($tenantId);

        return [
            'eligible' => true,
            'rate_type' => $row->rate_type ?? 'multiplier',
            'rate_multiplier' => $row->rate_multiplier ?? 1.5,
            'fixed_rate_per_hour' => $row->fixed_rate_per_hour ?? 0,
            'min_hours' => $row->min_hours ?? 0,
            'max_hours_per_day' => $row->max_hours_per_day ?? 0,
            'max_hours_per_month' => $row->max_hours_per_month ?? 0,
            'require_approval' => $row ? (bool) $row->require_approval : true,
            'auto_approve_limit' => $row->auto_approve_limit ?? 0,
        ];
    }

    private function tabActivity(User $user, int $tenantId): array
    {
        $audit = DB::table('audit_logs as l')->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.entity_type', 'users')->where('l.entity_id', $user->id)
            ->orderByDesc('l.id')->limit(25)
            ->get(['l.created_at', 'l.action', 'a.name as actor_name'])
            ->map(fn ($r) => ['at' => $r->created_at, 'what' => str_replace('_', ' ', (string) $r->action), 'by' => $r->actor_name, 'kind' => 'profile']);

        $attendance = DB::table('attendance_logs as l')->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
            ->where('l.tenant_id', $tenantId)->where('l.user_id', $user->id)
            ->orderByDesc('l.id')->limit(25)
            ->get(['l.created_at', 'l.event_type', 'l.source', 'l.reason', 'a.name as actor_name'])
            ->map(fn ($r) => [
                'at' => $r->created_at,
                'what' => trim(str_replace('_', ' ', (string) $r->event_type) . ' · ' . str_replace('_', ' ', (string) $r->source) . ($r->reason ? ' — ' . $r->reason : '')),
                'by' => $r->actor_name,
                'kind' => 'attendance',
            ]);

        // Requests admin / HR raised for this employee (loan, overtime, expense, leave, regularization).
        $onBehalf = DB::table('audit_logs as l')->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.action', 'like', '%on_behalf')
            ->where(fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.on_behalf_of')) = ?", [(string) $user->id])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.target_user_id')) = ?", [(string) $user->id]))
            ->orderByDesc('l.id')->limit(25)
            ->get(['l.created_at', 'l.action', 'l.entity_type', 'l.new_values', 'a.name as actor_name'])
            ->map(function ($r) {
                $v = json_decode((string) $r->new_values, true) ?: [];
                $detail = collect([
                    $v['loan_number'] ?? null,
                    isset($v['amount']) ? '₹' . $v['amount'] : null,
                    $v['leave_type'] ?? null,
                    isset($v['days']) ? $v['days'] . ' day(s)' : null,
                    isset($v['hours']) ? $v['hours'] . ' h' : null,
                    $v['date'] ?? ($v['start_date'] ?? null),
                ])->filter()->implode(' · ');

                return [
                    'at' => $r->created_at,
                    'what' => str_replace(['_', '.'], [' ', ' · '], (string) $r->action) . ($detail ? ' — ' . $detail : '') . ' (approved)',
                    'by' => $r->actor_name,
                    'kind' => 'request',
                ];
            });

        // Shift changes (roster edits, swaps, approved requests, rotations).
        $shifts = DB::table('shift_change_logs as l')
            ->leftJoin('shifts as fs', 'fs.id', '=', 'l.from_shift_id')
            ->leftJoin('shifts as ts', 'ts.id', '=', 'l.to_shift_id')
            ->leftJoin('users as a', 'a.id', '=', 'l.actor_id')
            ->where('l.tenant_id', $tenantId)->where('l.user_id', $user->id)
            ->orderByDesc('l.id')->limit(25)
            ->get(['l.created_at', 'l.date', 'l.source', 'l.reason', 'fs.name as from_name', 'ts.name as to_name', 'a.name as actor_name'])
            ->map(fn ($r) => [
                'at' => $r->created_at,
                'what' => (\App\Models\ShiftChangeLog::SOURCE_LABELS[$r->source] ?? str_replace('_', ' ', (string) $r->source))
                    . ' · ' . Carbon::parse($r->date)->format('d M') . ': ' . ($r->from_name ?? 'none') . ' → ' . ($r->to_name ?? 'none')
                    . ($r->reason ? ' — ' . $r->reason : ''),
                'by' => $r->actor_name,
                'kind' => 'shift',
            ]);

        return [
            'events' => $audit->concat($attendance)->concat($onBehalf)->concat($shifts)->sortByDesc('at')->take(40)->values(),
        ];
    }

    /** "06:00 AM – 02:00 PM (+1 day)" for a shift-like row. */
    public static function shiftLabel($shift): string
    {
        if (!$shift || empty($shift->start_time)) {
            return '—';
        }

        return Carbon::parse($shift->start_time)->format('h:i A') . ' – ' . Carbon::parse($shift->end_time)->format('h:i A')
            . (ShiftWindow::isOvernight($shift) ? ' (+1 day)' : '');
    }
}
