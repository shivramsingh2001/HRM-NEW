<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\AI\Concerns\AiScope;
use App\Http\Controllers\Controller;
use App\Services\Attendance\PolicyResolver;
use App\Services\EmployeePolicyService;
use App\Services\FeatureService;
use App\Services\Performance\PerformancePolicyResolver;
use App\Services\RequestLimitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/ai/policy — the rules one employee follows today: attendance (grace, late / early
 * actions, deductions), leave types, overtime, performance weights, WFH / regularization /
 * expense limits, week-offs, shifts and notice period. Company values with the employee's own
 * values (Employee 360 → Policies) on top; `customised` lists what differs for this employee.
 * Default = the caller; user_id = another employee within the caller's employee:view scope.
 */
class PolicyController extends Controller
{
    use AiScope;

    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $tenantId = (int) $authUser->tenant_id;
            $userId = (int) $authUser->id;

            if ($request->filled('user_id') && (int) $request->user_id !== $userId) {
                $scope = $this->scopeOf($authUser, 'employee');
                $ids = $scope ? $this->narrowToUser($this->visibleUserIds($authUser, $scope), $request->user_id) : [];
                $exists = DB::table('users')->where('tenant_id', $tenantId)->where('id', (int) $request->user_id)->exists();
                if ($ids === [] || ! $exists) {
                    return $this->forbidden();
                }
                $userId = (int) $request->user_id;
            }

            $today = Carbon::today()->toDateString();
            $features = app(FeatureService::class);
            $custom = app(EmployeePolicyService::class)->all($tenantId, $userId);
            $employeePolicy = app(EmployeePolicyService::class);
            $tenant = DB::table('tenants')->where('id', $tenantId)
                ->first(['notice_period', 'week_start', 'default_weekoff_days', 'custom_shifts_enabled', 'default_shift_id', 'leave_carry_forward_enabled']);
            $leaveYear = app(\App\Services\LeaveYearService::class);
            $employee = DB::table('users')->where('id', $userId)->first(['id', 'name', 'employee_id']);

            // Attendance
            $attendance = get_object_vars(app(PolicyResolver::class)->forUserDate($tenantId, $userId, $today));
            unset($attendance['tenantId']);

            // Leave types as this employee sees them
            $leaveTypes = DB::table('leave_types')->where('tenant_id', $tenantId)->where('status', 1)->orderBy('name')->get()
                ->map(function ($t) use ($tenantId, $userId, $employeePolicy) {
                    $rule = $employeePolicy->leaveRule($tenantId, $userId, $t);

                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'code' => $t->code,
                        'available_to_employee' => $employeePolicy->leaveAllowed($tenantId, $userId, (int) $t->id),
                        'credit_type' => $t->credit_type,
                        'credit_value' => (float) $rule->credit_value,
                        'is_unpaid' => (bool) $t->is_unpaid,
                        'min_notice_days' => (int) $rule->min_notice_days,
                        'max_consecutive_days' => (int) $rule->max_consecutive_days,
                        'requires_document_after_days' => $t->requires_document_after_days !== null ? (int) $t->requires_document_after_days : null,
                        'max_carry_forward' => $t->max_carry_forward !== null ? (float) $t->max_carry_forward : null,
                        'carry_forward_period' => (new \App\Models\LeaveType(['credit_type' => $t->credit_type]))->carryForwardPeriod(),
                        'carry_forward_expiry_months' => $t->carry_forward_expiry_months !== null ? (int) $t->carry_forward_expiry_months : null,
                        'is_encashable' => (bool) $t->is_encashable,
                        'description' => $t->description,
                        'customised' => $employeePolicy->hasLeaveOverride($tenantId, $userId, (int) $t->id),
                    ];
                })->values();

            // Overtime
            $overtime = null;
            if ($features->enabled($tenantId, 'overtime')) {
                $otPolicy = app(\App\Services\Attendance\OvertimePolicyService::class);
                $companyOt = $otPolicy->company($tenantId);
                $o = $otPolicy->forEmployee($tenantId, $userId);
                $overtime = [
                    'enabled' => $otPolicy->enabled($tenantId),
                    'mode' => $companyOt->mode ?? 'request',
                    'auto_start_basis' => $companyOt->auto_start_basis ?? 'grace',
                    'auto_start_after_minutes' => (int) ($companyOt->auto_start_after_minutes ?? 0),
                    'eligible' => $employeePolicy->overtimeEligible($tenantId, $userId),
                    'min_hours' => isset($o->min_hours) ? (float) $o->min_hours : null,
                    'rate_type' => $o->rate_type ?? 'multiplier',
                    'fixed_rate_per_hour' => isset($o->fixed_rate_per_hour) ? (float) $o->fixed_rate_per_hour : null,
                    'rate_multiplier' => isset($o->rate_multiplier) ? (float) $o->rate_multiplier : null,
                    'max_hours_per_day' => isset($o->max_hours_per_day) ? (float) $o->max_hours_per_day : null,
                    'max_hours_per_month' => isset($o->max_hours_per_month) ? (float) $o->max_hours_per_month : null,
                    'require_approval' => isset($o->require_approval) ? (bool) $o->require_approval : true,
                    'auto_approve_limit' => isset($o->auto_approve_limit) ? (float) $o->auto_approve_limit : null,
                    'overtime_after_hours' => $attendance['overtimeAfterHours'] ?? null,
                ];
            }

            // Performance
            $performance = $features->enabled($tenantId, 'kpi_performance')
                ? app(PerformancePolicyResolver::class)->forUserDate($tenantId, $userId, $today)->toArray()
                : null;

            // Request / expense limits (0 = no limit)
            $limits = app(RequestLimitService::class)->forEmployee($tenantId, $userId);
            $limits['expense_monthly_limit'] = (float) ($custom['expense']['monthly_limit'] ?? 0);

            // Week-offs and shifts
            $weekoffs = DB::table('user_weekoffs')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)
                ->where(fn ($q) => $q->where('off_type', 'day_based')->orWhere('end_date', '>=', $today)->orWhere('start_date', '>=', $today))
                ->orderBy('off_type')->orderBy('start_date')
                ->get(['off_type', 'day_name', 'start_date', 'end_date', 'description']);
            $shifts = DB::table('shifts')->where('tenant_id', $tenantId)->where('status', 1)->orderBy('start_time')
                ->get(['id', 'name', 'start_time', 'end_time', 'is_overnight', 'total_hours', 'grace_minutes', 'break_time'])
                ->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'start_time' => $s->start_time, 'end_time' => $s->end_time,
                    'is_overnight' => (bool) $s->is_overnight, 'total_hours' => $s->total_hours,
                    'grace_minutes' => $s->grace_minutes, 'break_time' => $s->break_time,
                    'is_company_default' => (int) $s->id === (int) ($tenant->default_shift_id ?? 0),
                ])->values();

            return response()->json([
                'success' => true,
                'message' => 'Policy data fetched successfully',
                'data' => [
                    'employee' => $employee,
                    'effective_on' => $today,
                    'attendance' => $attendance,
                    'leave_types' => $leaveTypes,
                    'leave_year' => [
                        'current_start' => $leaveYear->startFor($tenantId)->toDateString(),
                        'next_start' => $leaveYear->startFor($tenantId)->addYear()->toDateString(),
                        // Off = all unused leave carries over; on = each type's max_carry_forward /
                        // carry_forward_expiry_months apply at the leave-year start.
                        'carry_forward_enabled' => (bool) ($tenant->leave_carry_forward_enabled ?? false),
                    ],
                    'overtime' => $overtime,
                    'performance' => $performance,
                    'limits' => $limits,
                    'week_offs' => [
                        'employee' => $weekoffs,
                        'company_default_days' => json_decode($tenant->default_weekoff_days ?? '[]', true) ?: [],
                        'week_start' => $tenant->week_start ?? null,
                    ],
                    'shifts' => [
                        'mode' => ($tenant->custom_shifts_enabled ?? 0) ? 'custom (per-employee roster)' : 'fixed (company default shift)',
                        'list' => $shifts,
                    ],
                    'notice_period_days' => (int) ($tenant->notice_period ?? 30),
                    'customised' => array_map('array_keys', $custom),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return $this->failed('policies', $e);
        }
    }
}
