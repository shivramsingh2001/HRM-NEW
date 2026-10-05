<?php

namespace App\Http\Controllers\User;

use App\Models\Asset;
use App\Models\AttendanceLocation;
use App\Models\CompanyBranch;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDocument;
use App\Models\Language;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Enums\AttendanceStatus;
use App\Services\Attendance\TenantShiftResolver;
use App\Services\AuditLogger;
use App\Services\EmployeePolicyService;
use App\Services\LeaveNotificationService;
use App\Services\LeaveService;
use App\Services\Shift\ShiftMaterializer;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Employee 360 page — Phase 2: the actions on every tab.
 *
 * Each action is a small form partial (`client/user/profile/forms/{form}`)
 * loaded into one shared modal and posted by AJAX to the module's EXISTING
 * endpoint (employee.update.step, shift.assign, team.mark-attendance,
 * leave-credit.manual.*, leave.update-status, expense.update-status,
 * assets.assign …) — so every rule, permission and notification stays in one
 * place. Only three actions had no endpoint and live here: reset password,
 * apply leave on behalf, and set weekly offs.
 * Admin + HR only (route middleware).
 */
class EmployeeProfileActionController extends EmployeeProfileController
{
    /** form => the tab whose plan features it needs */
    public const FORMS = [
        'login' => 'overview',
        'personal' => 'overview',
        'job' => 'overview',
        'address' => 'overview',
        'bank' => 'overview',
        'document' => 'overview',
        'password' => 'overview',
        'status' => 'overview',
        'shift-assign' => 'shift',
        'shift-end' => 'shift',
        'weekoffs' => 'shift',
        'attendance-mark' => 'attendance',
        'regularization' => 'attendance',
        'leave-credit' => 'leave',
        'leave-debit' => 'leave',
        'leave-apply' => 'leave',
        'leave-decide' => 'leave',
        'expense-decide' => 'expenses',
        'asset-assign' => 'assets',
        'asset-return' => 'assets',
        // Phase 3 — company policy values customised for this employee.
        'policy-attendance' => 'attendance',
        'policy-overtime' => 'policies',
        'policy-performance' => 'performance',
        'policy-leave' => 'leave',
        // Phase 4 — request / expense limits customised for this employee.
        'policy-requests' => 'policies',
        'policy-expense' => 'expenses',
    ];

    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function form(Request $request, string $id, string $form)
    {
        abort_unless(array_key_exists($form, self::FORMS), 404);
        abort_unless($this->tabEnabled(self::FORMS[$form]), 403, 'This module is not included in your company\'s plan.');

        $user = $this->employee($id);
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $this->{'form' . Str::studly($form)}($user, $tenantId, $request);

        // The three "one value per setting" policy sections share one form.
        $view = isset($data['section']) ? 'policy' : $form;

        return view('client.user.profile.forms.' . $view, $data + [
            'user' => $user,
            'encId' => $id,
            'can' => $this->abilities(),
        ]);
    }

    // ----------------------------------------------------------------- forms

    private function formLogin(User $user): array
    {
        return [];
    }

    private function formPersonal(User $user): array
    {
        $basic = $user->basicDetails;

        return [
            'basic' => $basic,
            'languages' => Language::where('status', 1)->orderBy('name')->get(),
            'selectedLanguages' => array_map('intval', (array) json_decode($basic->language ?? '[]', true)),
        ];
    }

    private function formJob(User $user): array
    {
        $job = $user->jobDetails;

        return [
            'job' => $job,
            'departments' => Department::where('status', 1)->orderBy('name')->get(),
            'designations' => Designation::where('status', 1)->orderBy('name')->get(),
            'heads' => User::where('role', '!=', 'employee')->where('id', '!=', $user->id)->where('status', 1)->orderBy('name')->get(['id', 'name']),
            // Primary first — the first selected option is saved as the primary head.
            'selectedHeads' => $user->reportingHeads()->orderByDesc('is_primary')->pluck('users.id')->all(),
            'locations' => AttendanceLocation::where('status', 1)->orderBy('name')->get(),
            'companyBranches' => CompanyBranch::where('status', 1)->orderBy('name')->get(),
            'leaveTypes' => LeaveType::where('status', 1)->orderBy('name')->get(),
            'selectedLeaveTypes' => array_map('intval', (array) json_decode($job->leave_assigned ?? '[]', true)),
        ];
    }

    private function formAddress(User $user): array
    {
        $location = $user->location;

        return [
            'location' => $location,
            'countries' => Country::where('status', 1)->orderBy('name')->get(),
            'states' => $location && $location->country
                ? DB::table('states')->where('country_code', $location->country)->where('status', 1)->orderBy('name')->get(['state_code', 'name'])
                : collect(),
            'cities' => $location && $location->state
                ? DB::table('cities')->where('state_code', $location->state)->where('status', 1)->orderBy('name')->get(['city_code', 'name'])
                : collect(),
        ];
    }

    private function formBank(User $user): array
    {
        return ['bank' => $user->bankDetails];
    }

    private function formDocument(User $user, int $tenantId, Request $request): array
    {
        $documents = EmployeeDocument::where('user_id', $user->id)->latest()->get();
        $remove = $request->query('remove') ? $documents->firstWhere('id', (int) $request->query('remove')) : null;
        abort_if($request->query('remove') && !$remove, 404);

        return [
            'documents' => $documents,
            'remove' => $remove,
            'types' => EmployeeDocument::$documentTypes,
        ];
    }

    private function formPassword(User $user): array
    {
        return [];
    }

    private function formStatus(User $user): array
    {
        return [];
    }

    private function formShiftAssign(User $user, int $tenantId): array
    {
        abort_unless(app(TenantShiftResolver::class)->isCustomShifts($tenantId), 403, 'Your company uses one fixed shift for everyone.');

        return [
            'shifts' => Shift::where('status', 1)->orderBy('name')->get(),
            'activePermanent' => DB::table('shift_assignments as sa')->leftJoin('shifts as s', 's.id', '=', 'sa.shift_id')
                ->where('sa.tenant_id', $tenantId)->where('sa.user_id', $user->id)
                ->where('sa.type', 'permanent')->where('sa.status', 'active')->where('sa.is_additional', 0)
                ->first(['sa.id', 'sa.start_date', 's.name as shift_name']),
        ];
    }

    private function formShiftEnd(User $user, int $tenantId, Request $request): array
    {
        $assignment = DB::table('shift_assignments as sa')->leftJoin('shifts as s', 's.id', '=', 'sa.shift_id')
            ->where('sa.tenant_id', $tenantId)->where('sa.user_id', $user->id)
            ->where('sa.id', (int) $request->query('assignment'))
            ->where('sa.type', 'permanent')->where('sa.status', 'active')
            ->first(['sa.id', 'sa.start_date', 's.name as shift_name']);
        abort_unless($assignment, 404);

        return ['assignment' => $assignment];
    }

    private function formWeekoffs(User $user, int $tenantId): array
    {
        abort_unless(app(TenantShiftResolver::class)->isCustomShifts($tenantId), 403, 'Week-offs are set for the whole company in Shift Settings.');

        return [
            'weekdays' => self::WEEKDAYS,
            'selected' => DB::table('user_weekoffs')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->where('off_type', 'day_based')->where('status', 1)->pluck('day_name')->unique()->values()->all(),
        ];
    }

    private function formAttendanceMark(User $user, int $tenantId, Request $request): array
    {
        $date = (string) $request->query('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= now()->toDateString() ? $date : now()->toDateString();

        return [
            'date' => $date,
            'statuses' => AttendanceStatus::markable(),
            'leaveTypes' => LeaveType::where('status', 1)->orderBy('name')->get(),
        ];
    }

    private function formRegularization(User $user, int $tenantId, Request $request): array
    {
        $regularization = DB::table('attendance_regularizations')
            ->where('tenant_id', $tenantId)->where('user_id', $user->id)
            ->where('id', (int) $request->query('reg'))->where('status', 'pending')
            ->first();
        abort_unless($regularization, 404);

        return [
            'regularization' => $regularization,
            'decision' => $request->query('decision') === 'rejected' ? 'rejected' : 'approved',
        ];
    }

    private function formLeaveCredit(User $user, int $tenantId): array
    {
        return $this->leaveFormData($user, $tenantId);
    }

    private function formLeaveDebit(User $user, int $tenantId): array
    {
        return $this->leaveFormData($user, $tenantId);
    }

    private function formLeaveApply(User $user, int $tenantId): array
    {
        return $this->leaveFormData($user, $tenantId);
    }

    private function leaveFormData(User $user, int $tenantId): array
    {
        return [
            'leaveTypes' => LeaveType::where('status', 1)->orderBy('name')->get(),
            'balances' => DB::table('leave_balances')->where('tenant_id', $tenantId)->where('user_id', $user->id)
                ->pluck('balance', 'leave_type_id'),
        ];
    }

    private function formLeaveDecide(User $user, int $tenantId, Request $request): array
    {
        $decision = $request->query('decision') === 'cancelled' ? 'cancelled' : 'approved';

        $leave = DB::table('leaves as l')->leftJoin('leave_types as t', 't.id', '=', 'l.leave_type')
            ->where('l.tenant_id', $tenantId)->where('l.user_id', $user->id)
            ->where('l.id', (int) $request->query('leave'))
            // pending: approve or reject; approved: only a revoke.
            ->whereIn('l.status', $decision === 'cancelled' ? ['pending', 'approved'] : ['pending'])
            ->first(['l.*', 't.name as type_name']);
        abort_unless($leave, 404);

        return ['leave' => $leave, 'decision' => $decision];
    }

    private function formExpenseDecide(User $user, int $tenantId, Request $request): array
    {
        $expense = DB::table('expenses')
            ->where('tenant_id', $tenantId)->where('user_id', $user->id)->whereNull('deleted_at')
            ->where('id', (int) $request->query('expense'))->where('status', 'pending')
            ->first();
        abort_unless($expense, 404);

        return [
            'expense' => $expense,
            'decision' => $request->query('decision') === 'cancelled' ? 'cancelled' : 'approved',
        ];
    }

    private function formAssetAssign(User $user): array
    {
        return ['assets' => Asset::where('status', 'available')->orderBy('name')->get(['id', 'asset_code', 'name', 'serial_number'])];
    }

    private function formAssetReturn(User $user, int $tenantId, Request $request): array
    {
        $assignment = DB::table('asset_assignments as aa')->join('assets as a', 'a.id', '=', 'aa.asset_id')
            ->where('aa.tenant_id', $tenantId)->where('aa.user_id', $user->id)
            ->where('aa.id', (int) $request->query('assignment'))
            ->whereIn('aa.status', ['accepted', 'pending_acceptance'])
            ->first(['aa.id', 'a.asset_code', 'a.name as asset_name']);
        abort_unless($assignment, 404);

        return ['assignment' => $assignment];
    }

    private function formPolicyAttendance(User $user, int $tenantId): array
    {
        return $this->policyFormData('attendance', $user, $tenantId);
    }

    private function formPolicyOvertime(User $user, int $tenantId): array
    {
        return $this->policyFormData('overtime', $user, $tenantId);
    }

    private function formPolicyPerformance(User $user, int $tenantId): array
    {
        return $this->policyFormData('performance', $user, $tenantId);
    }

    private function formPolicyRequests(User $user, int $tenantId): array
    {
        return $this->policyFormData('requests', $user, $tenantId);
    }

    private function formPolicyExpense(User $user, int $tenantId): array
    {
        return $this->policyFormData('expense', $user, $tenantId);
    }

    private function policyFormData(string $section, User $user, int $tenantId): array
    {
        abort_unless(in_array($section, $this->policySections(), true), 403, 'This module is not included in your company\'s plan.');

        return [
            'section' => $section,
            'fields' => EmployeePolicyService::fields($section),
            'company' => $this->companyPolicy($section, $tenantId),
            'custom' => app(EmployeePolicyService::class)->section($tenantId, $user->id, $section),
        ];
    }

    private function formPolicyLeave(User $user, int $tenantId): array
    {
        return [
            'leaveTypes' => LeaveType::where('status', 1)->orderBy('name')->get(),
            'fields' => EmployeePolicyService::LEAVE_FIELDS,
            'custom' => app(EmployeePolicyService::class)->section($tenantId, $user->id, 'leave'),
        ];
    }

    // ------------------------------------------------ per-employee policies

    /**
     * Save (or, with `reset`, clear) this employee's custom values for one
     * policy section. The form posts `custom[key] = 1` for each setting that
     * is customised and `value[key]` for its value; for leave both are nested
     * under the leave type id. Anything not ticked goes back to the company
     * value.
     */
    public function savePolicy(Request $request, string $id, string $section, EmployeePolicyService $policy)
    {
        abort_unless(in_array($section, EmployeePolicyService::SECTIONS, true), 404);
        abort_unless(
            $section === 'leave' ? $this->tabEnabled('leave') : in_array($section, $this->policySections(), true),
            403,
            'This module is not included in your company\'s plan.'
        );

        $user = $this->employee($id);
        $tenantId = (int) Auth::user()->tenant_id;
        $values = [];

        if ($request->boolean('reset')) {
            // nothing kept — every setting follows the company again
        } elseif ($section === 'leave') {
            $types = LeaveType::where('status', 1)->pluck('name', 'id');
            $attributes = [];
            foreach ($types as $typeId => $name) {
                foreach (EmployeePolicyService::LEAVE_FIELDS as $key => $field) {
                    $attributes["value.$typeId.$key"] = "$name — " . strtolower($field['label']);
                }
            }
            $data = $request->validate($policy->leaveRules($types->keys()->all()), [], $attributes);

            foreach ($types->keys() as $typeId) {
                $row = [];
                foreach (EmployeePolicyService::LEAVE_FIELDS as $key => $field) {
                    if (! empty($data['custom'][$typeId][$key])) {
                        $row[$key] = $policy->cast($field, $data['value'][$typeId][$key]);
                    }
                }
                if ($row) {
                    $values['type:' . $typeId] = $row;
                }
            }
        } else {
            $fields = EmployeePolicyService::fields($section);
            $attributes = [];
            foreach ($fields as $key => $field) {
                $attributes["value.$key"] = strtolower($field['label']);
            }
            $data = $request->validate($policy->rules($section), [], $attributes);

            foreach ($fields as $key => $field) {
                if (! empty($data['custom'][$key])) {
                    $values[$key] = $policy->cast($field, $data['value'][$key]);
                }
            }

            // The same cross-field rules the company settings pages apply, on the merged result.
            $merged = $values + $this->companyPolicy($section, $tenantId);
            if ($section === 'attendance' && (float) $merged['half_day_ratio'] > (float) $merged['present_ratio']) {
                return $this->fail('"Half day from" cannot be higher than "Present from".');
            }
            if ($section === 'performance') {
                $sum = array_sum(array_map(fn ($k) => (float) $merged[$k], [
                    'weight_attendance', 'weight_task_completion', 'weight_task_ontime',
                    'weight_project_participation', 'weight_regularization', 'weight_manager_rating',
                ]));
                if (abs($sum - 100.0) > 0.5) {
                    return $this->fail("The six weights must add up to 100 (now {$sum}, counting the company values you did not change).");
                }
            }
        }

        $policy->save($tenantId, $user->id, $section, $values, Auth::id());

        // Attendance rules changed: re-grade this month so the tab shows the effect now.
        if ($section === 'attendance') {
            try {
                $month = now()->format('Y-m');
                app(\App\Services\Attendance\LatePolicyService::class)->recalculateMonth($user->id, $tenantId, $month);
                app(\App\Services\AttendanceSummaryService::class)->updateMonthlySummary($user->id, $month, $tenantId);
            } catch (\Throwable $e) {
                Log::warning('Employee 360 policy save: month re-grade failed: ' . $e->getMessage());
            }
        }

        $count = $section === 'leave' ? array_sum(array_map('count', $values)) : count($values);

        return response()->json([
            'success' => true,
            'message' => $count
                ? "{$count} custom " . ($count === 1 ? 'value' : 'values') . " saved for {$user->name}."
                : "{$user->name} now follows the company policy.",
        ]);
    }

    // --------------------------------------------- actions with no old endpoint

    /** Admin / HR sets a new login password for the employee. */
    public function resetPassword(Request $request, string $id)
    {
        $user = $this->employee($id);

        // Same length rule as the employee wizard.
        $request->validate(['password' => 'required|string|min:6|max:10|confirmed']);

        $user->password = Hash::make($request->input('password'));
        $user->save();

        app(AuditLogger::class)->record('tenant_user', Auth::id(), (int) $user->tenant_id, 'employee.password_reset', 'users', $user->id);

        return response()->json(['success' => true, 'message' => 'Password changed for ' . $user->name . '.']);
    }

    /**
     * Apply a leave for the employee. Admin / HR is the approver, so the leave
     * is created already approved and the balance is deducted right away
     * (LeaveService::createApproved — the same writer manual attendance uses).
     * Same checks as the employee's own form: working days, balance, and the
     * leave type's maximum consecutive days. Notice period is not enforced.
     */
    public function applyLeave(Request $request, string $id, LeaveService $leaves)
    {
        abort_unless($this->tabEnabled('leave'), 403, 'This module is not included in your company\'s plan.');
        abort_unless($this->abilities()['leave_manage'], 403, 'You do not have permission to apply leave for an employee.');

        $user = $this->employee($id);
        $tenantId = (int) Auth::user()->tenant_id;

        $data = $request->validate([
            'leave_type' => ['required', Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
            'start_date' => 'required|date',
            'start_session' => 'required|in:session1,session2,fullday',
            'end_date' => 'required|date|after_or_equal:start_date',
            'end_session' => 'required|in:session1,session2,fullday',
            'reason' => 'required|string|max:500',
        ]);

        // Shared with Leave → All Leaves → "Apply leave for employee" (checks, approval, log, notification).
        try {
            $result = $leaves->applyOnBehalf(Auth::user(), $user, (int) $data['leave_type'], $data['start_date'],
                $data['start_session'], $data['end_date'], $data['end_session'], $data['reason']);
        } catch (\DomainException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Employee 360 apply leave failed: ' . $e->getMessage());

            return $this->fail('Could not apply the leave. Please try again.', 500);
        }

        return response()->json(['success' => true, 'message' => "{$result['type']->name} applied for {$result['days']} day(s) and approved."]);
    }

    /**
     * Replace the employee's weekly off days (the recurring "every Sunday"
     * kind; one-off week-off dates are left alone), then rebuild the shift
     * plan from tomorrow so the new off days are free of shifts. Custom-shift
     * companies only — a fixed-shift company sets week-offs for everyone in
     * Shift Settings.
     */
    public function setWeekoffs(Request $request, string $id)
    {
        abort_unless($this->tabEnabled('shift'), 403, 'This module is not included in your company\'s plan.');

        $user = $this->employee($id);
        $tenantId = (int) Auth::user()->tenant_id;
        abort_unless(app(TenantShiftResolver::class)->isCustomShifts($tenantId), 403, 'Week-offs are set for the whole company in Shift Settings.');

        $data = $request->validate([
            'days' => 'nullable|array',
            'days.*' => ['distinct', Rule::in(self::WEEKDAYS)],
        ]);
        $days = array_values($data['days'] ?? []);

        $base = DB::table('user_weekoffs')->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('off_type', 'day_based');
        $before = (clone $base)->where('status', 1)->pluck('day_name')->unique()->values()->all();

        DB::transaction(function () use ($base, $days, $tenantId, $user) {
            (clone $base)->delete();

            $now = now();
            DB::table('user_weekoffs')->insert(array_map(fn ($day) => [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'off_type' => 'day_based',
                'day_name' => $day,
                'start_date' => null,
                'end_date' => null,
                'status' => 1,
                'created_by' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ], $days));

            app(ShiftMaterializer::class)->regenerateFrom($tenantId, $user->id, Carbon::tomorrow());
        });

        app(AuditLogger::class)->record('tenant_user', Auth::id(), $tenantId, 'employee.weekoffs_updated', 'users', $user->id, ['days' => $before], ['days' => $days]);

        return response()->json([
            'success' => true,
            'message' => $days ? 'Weekly off set to ' . implode(', ', $days) . '.' : 'Weekly offs removed.',
        ]);
    }

    private function fail(string $message, int $status = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
