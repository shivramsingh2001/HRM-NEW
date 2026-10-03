<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Company policy values changed for ONE employee (Employee 360 → Policies).
 *
 * `employee_policy_overrides` holds one row per (employee, section, key); when
 * there is no row the company value applies, so a company with no overrides
 * behaves exactly as before. Each policy's own reader layers the overrides on
 * top of the company value:
 *   attendance  → PolicyResolver::forUserDate() / forUserMonth()
 *   performance → PerformancePolicyResolver::forUserDate() / forUserMonth()
 *   overtime    → overtime() / overtimeEligible() here
 *   leave       → leaveRule() / leaveAllowed() here
 *
 * An override has no start date: it applies to whatever is calculated after it
 * is saved (including a re-run of an older month).
 */
class EmployeePolicyService
{
    /**
     * What can be customised, per section. Keys of attendance / performance /
     * overtime are the company table's own column names.
     * type: bool | int | decimal | select (with `options`).
     */
    public const FIELDS = [
        'attendance' => [
            'day_classification_enabled' => ['label' => 'Grade the day by hours worked', 'type' => 'bool', 'help' => 'Off = any work counts as a present day'],
            'present_ratio' => ['label' => 'Present from (share of shift hours)', 'type' => 'decimal', 'min' => 0, 'max' => 1, 'step' => 0.01],
            'half_day_ratio' => ['label' => 'Half day from (share of shift hours)', 'type' => 'decimal', 'min' => 0, 'max' => 1, 'step' => 0.01],
            'fallback_present_hours' => ['label' => 'Present from, when no shift (hours)', 'type' => 'decimal', 'min' => 0, 'max' => 24, 'step' => 0.25],
            'fallback_half_hours' => ['label' => 'Half day from, when no shift (hours)', 'type' => 'decimal', 'min' => 0, 'max' => 24, 'step' => 0.25],
            'grace_mode' => ['label' => 'Grace minutes come from', 'type' => 'select', 'options' => ['fixed' => 'One fixed value', 'shift' => 'The shift']],
            'fixed_grace_minutes' => ['label' => 'Grace minutes (late / early)', 'type' => 'int', 'min' => 0, 'max' => 240],
            'monthly_late_allowance' => ['label' => 'Late days allowed per month', 'type' => 'int', 'min' => 0, 'max' => 31],
            'late_attendance_action' => ['label' => 'After the late allowance', 'type' => 'select', 'options' => ['none' => 'No action', 'half_day' => 'Half day', 'absent' => 'Absent']],
            'late_deduction_enabled' => ['label' => 'Salary deduction for extra late days', 'type' => 'bool'],
            'late_deduction_mode' => ['label' => 'Deduction per extra late day', 'type' => 'select', 'options' => ['fixed_amount' => 'Fixed amount per day', 'half_day' => 'Half a day\'s pay', 'full_day' => 'A full day\'s pay', 'custom_multiplier' => 'Day\'s pay × multiplier']],
            'late_deduction_amount' => ['label' => 'Fixed deduction amount (₹)', 'type' => 'decimal', 'min' => 0, 'max' => 1000000, 'step' => 1],
            'late_deduction_multiplier' => ['label' => 'Deduction multiplier', 'type' => 'decimal', 'min' => 0, 'max' => 5, 'step' => 0.05],
            'monthly_early_allowance' => ['label' => 'Early-leaving days allowed per month', 'type' => 'int', 'min' => 0, 'max' => 31],
            'early_attendance_action' => ['label' => 'After the early-leaving allowance', 'type' => 'select', 'options' => ['none' => 'No action', 'half_day' => 'Half day', 'absent' => 'Absent']],
            'early_deduction_enabled' => ['label' => 'Salary deduction for extra early-leaving days', 'type' => 'bool'],
            'early_deduction_mode' => ['label' => 'Deduction per extra early-leaving day', 'type' => 'select', 'options' => ['fixed_amount' => 'Fixed amount per day', 'half_day' => 'Half a day\'s pay', 'full_day' => 'A full day\'s pay', 'custom_multiplier' => 'Day\'s pay × multiplier']],
            'early_deduction_amount' => ['label' => 'Fixed deduction amount (₹)', 'type' => 'decimal', 'min' => 0, 'max' => 1000000, 'step' => 1],
            'early_deduction_multiplier' => ['label' => 'Deduction multiplier', 'type' => 'decimal', 'min' => 0, 'max' => 5, 'step' => 0.05],
            'overtime_after_hours' => ['label' => 'Overtime counted after (hours)', 'type' => 'decimal', 'min' => 0, 'max' => 24, 'step' => 0.25],
            'sandwich_leave' => ['label' => 'Sandwich leave rule', 'type' => 'bool'],
        ],
        'overtime' => [
            'eligible' => ['label' => 'Eligible for overtime', 'type' => 'bool', 'help' => 'No = cannot request overtime and is not paid for it'],
            'rate_multiplier' => ['label' => 'Overtime rate (× hourly rate)', 'type' => 'decimal', 'min' => 0, 'max' => 9.99, 'step' => 0.05],
            'max_hours_per_day' => ['label' => 'Maximum overtime hours per day', 'type' => 'decimal', 'min' => 0, 'max' => 24, 'step' => 0.25, 'help' => '0 = no limit'],
            'max_hours_per_month' => ['label' => 'Maximum overtime hours per month', 'type' => 'decimal', 'min' => 0, 'max' => 744, 'step' => 0.5, 'help' => '0 = no limit'],
            'require_approval' => ['label' => 'Overtime needs approval', 'type' => 'bool'],
            'auto_approve_limit' => ['label' => 'Auto-approve up to (hours)', 'type' => 'decimal', 'min' => 0, 'max' => 24, 'step' => 0.25, 'help' => '0 = never'],
        ],
        'performance' => [
            'weight_attendance' => ['label' => 'Weight — attendance', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'weight_task_completion' => ['label' => 'Weight — task completion', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'weight_task_ontime' => ['label' => 'Weight — tasks on time', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'weight_project_participation' => ['label' => 'Weight — project participation', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'weight_regularization' => ['label' => 'Weight — regularization', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'weight_manager_rating' => ['label' => 'Weight — manager rating', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'late_grace_minutes' => ['label' => 'Late grace (minutes)', 'type' => 'int', 'min' => 0, 'max' => 240],
            'late_penalty_per_incident' => ['label' => 'Late penalty per day', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'late_penalty_cap' => ['label' => 'Late penalty cap', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'early_departure_grace_minutes' => ['label' => 'Early-leaving grace (minutes)', 'type' => 'int', 'min' => 0, 'max' => 240],
            'early_departure_penalty_per_incident' => ['label' => 'Early-leaving penalty per day', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'early_departure_penalty_cap' => ['label' => 'Early-leaving penalty cap', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'regularization_penalty_approved' => ['label' => 'Regularization penalty — approved', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'regularization_penalty_rejected' => ['label' => 'Regularization penalty — rejected', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'regularization_penalty_pending' => ['label' => 'Regularization penalty — pending', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'regularization_penalty_cap' => ['label' => 'Regularization penalty cap', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'task_overdue_penalty_per_task' => ['label' => 'Overdue-task penalty per task', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
            'task_overdue_penalty_cap' => ['label' => 'Overdue-task penalty cap', 'type' => 'decimal', 'min' => 0, 'max' => 100, 'step' => 0.5],
        ],
    ];

    /**
     * Phase 4 sections. "requests" keys are the `tenants` columns of the
     * company limits; "expense" has no company value (0 = no limit). Read
     * through App\Services\RequestLimitService.
     */
    public const LIMIT_FIELDS = [
        'requests' => [
            'wfh_max_days_per_month' => ['label' => 'Work-from-home days allowed per month', 'type' => 'int', 'min' => 0, 'max' => 31, 'help' => '0 = no limit'],
            'wfh_min_notice_days' => ['label' => 'Work-from-home notice needed (days)', 'type' => 'int', 'min' => 0, 'max' => 90, 'help' => '0 = none'],
            'regularization_max_per_month' => ['label' => 'Regularization requests allowed per month', 'type' => 'int', 'min' => 0, 'max' => 31, 'help' => '0 = no limit'],
            'regularization_max_days_back' => ['label' => 'Regularize at most this many days back', 'type' => 'int', 'min' => 0, 'max' => 365, 'help' => '0 = no limit'],
        ],
        'expense' => [
            'monthly_limit' => ['label' => 'Expense claims allowed per month (₹)', 'type' => 'decimal', 'min' => 0, 'max' => 100000000, 'step' => 1, 'help' => 'Reimbursements + settlements; 0 = no limit'],
        ],
    ];

    /** Per leave type (section "leave", key "type:{id}"). */
    public const LEAVE_FIELDS = [
        'allowed' => ['label' => 'Can use this leave type', 'type' => 'bool'],
        'credit_value' => ['label' => 'Days credited each cycle', 'type' => 'decimal', 'min' => 0, 'max' => 366, 'step' => 0.5],
        'min_notice_days' => ['label' => 'Notice needed (days)', 'type' => 'int', 'min' => 0, 'max' => 365, 'help' => '0 = none'],
        'max_consecutive_days' => ['label' => 'Longest single leave (days)', 'type' => 'int', 'min' => 0, 'max' => 365, 'help' => '0 = no limit'],
    ];

    public const SECTIONS = ['attendance', 'overtime', 'performance', 'leave', 'requests', 'expense'];

    /** The "one value per setting" sections (everything but leave), with their fields. */
    public static function fields(string $section): array
    {
        return self::FIELDS[$section] ?? self::LIMIT_FIELDS[$section] ?? [];
    }

    /** @var array<string,array<string,array<string,mixed>>> "tenant|user" => section => key => value */
    private array $memo = [];

    private ?bool $ready = null;

    public function __construct(private AuditLogger $audit)
    {
    }

    /** Every override of one employee: section => key => value. */
    public function all(int $tenantId, int $userId): array
    {
        $memoKey = $tenantId . '|' . $userId;
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $out = [];
        if ($this->tableReady()) {
            $rows = DB::table('employee_policy_overrides')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->get(['section', 'key', 'value']);
            foreach ($rows as $row) {
                $out[$row->section][$row->key] = json_decode($row->value, true);
            }
        }

        return $this->memo[$memoKey] = $out;
    }

    /** One section's overrides of one employee: key => value ([] = none). */
    public function section(int $tenantId, int $userId, string $section): array
    {
        return $this->all($tenantId, $userId)[$section] ?? [];
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * Replace one section's overrides. $values is key => value; a key that is
     * missing (or null) goes back to the company value.
     */
    public function save(int $tenantId, int $userId, string $section, array $values, ?int $actorId): void
    {
        $before = $this->section($tenantId, $userId, $section);
        $values = array_filter($values, fn ($v) => $v !== null);

        DB::transaction(function () use ($tenantId, $userId, $section, $values, $actorId) {
            DB::table('employee_policy_overrides')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)->where('section', $section)
                ->delete();

            $now = now();
            DB::table('employee_policy_overrides')->insert(array_map(fn ($key) => [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'section' => $section,
                'key' => (string) $key,
                'value' => json_encode($values[$key]),
                'set_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys($values)));
        });

        $this->forget();
        $this->audit->record('tenant_user', $actorId, $tenantId, 'employee.policy_override_saved', 'users', $userId,
            ['section' => $section, 'values' => $before], ['section' => $section, 'values' => $values]);
    }

    /**
     * Validation rules for a section's form: `custom[key]` = 1 marks a field
     * as customised, `value[key]` carries it.
     */
    public function rules(string $section): array
    {
        $rules = ['custom' => 'nullable|array', 'value' => 'nullable|array'];
        foreach (self::fields($section) as $key => $field) {
            $rules["value.$key"] = array_merge(["required_with:custom.$key", 'nullable'], $this->typeRules($field));
        }

        return $rules;
    }

    /** Rules for the leave form: `custom[typeId][field]`, `value[typeId][field]`. */
    public function leaveRules(array $leaveTypeIds): array
    {
        $rules = ['custom' => 'nullable|array', 'value' => 'nullable|array'];
        foreach ($leaveTypeIds as $id) {
            foreach (self::LEAVE_FIELDS as $key => $field) {
                $rules["value.$id.$key"] = array_merge(["required_with:custom.$id.$key", 'nullable'], $this->typeRules($field));
            }
        }

        return $rules;
    }

    private function typeRules(array $field): array
    {
        return match ($field['type']) {
            'bool' => ['boolean'],
            'int' => ['integer', 'min:' . ($field['min'] ?? 0), 'max:' . ($field['max'] ?? 100000)],
            'decimal' => ['numeric', 'min:' . ($field['min'] ?? 0), 'max:' . ($field['max'] ?? 100000)],
            'select' => [Rule::in(array_keys($field['options']))],
        };
    }

    /** A value as shown on screen: Yes / No, the option's label, or the number without trailing zeros. */
    public static function display(array $field, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field['type']) {
            'bool' => $value ? 'Yes' : 'No',
            'select' => (string) ($field['options'][$value] ?? $value),
            default => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'),
        };
    }

    /** Cast a posted value to what the field stores. */
    public function cast(array $field, $value)
    {
        return match ($field['type']) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'decimal' => (float) $value,
            default => (string) $value,
        };
    }

    // -------------------------------------------------------------- overtime

    /**
     * The overtime settings this employee follows: the company row ($company,
     * as the caller already resolved it — may be null) with the employee's
     * custom values on top. Returns $company untouched when there are none.
     */
    public function overtime(int $tenantId, int $userId, ?object $company): ?object
    {
        $custom = array_diff_key($this->section($tenantId, $userId, 'overtime'), ['eligible' => true]);
        if (! $custom) {
            return $company;
        }

        $settings = $company ? clone $company : new \stdClass();
        foreach (['rate_multiplier', 'max_hours_per_day', 'max_hours_per_month', 'require_approval', 'auto_approve_limit'] as $column) {
            if (array_key_exists($column, $custom)) {
                $settings->{$column} = $custom[$column];
            } elseif (! isset($settings->{$column})) {
                // No company row at all: the code's own fallbacks (1.5×, approval needed) stay in charge.
                $settings->{$column} = $column === 'require_approval' ? true : null;
            }
        }

        return $settings;
    }

    public function overtimeEligible(int $tenantId, int $userId): bool
    {
        return (bool) ($this->section($tenantId, $userId, 'overtime')['eligible'] ?? true);
    }

    /** The employee's own monthly overtime cap, or null when they follow the company. */
    public function overtimeMonthlyCap(int $tenantId, int $userId): ?float
    {
        $cap = $this->section($tenantId, $userId, 'overtime')['max_hours_per_month'] ?? null;

        return $cap !== null && (float) $cap > 0 ? (float) $cap : null; // 0 = no limit
    }

    /**
     * Why this employee cannot book $hours of overtime on $date, or null when
     * they can. Only rules the employee has a CUSTOM value for are checked
     * here (eligibility, monthly cap) — the company-wide checks stay where
     * they were. $ignoreRequestId = the request being edited.
     */
    public function overtimeRefusal(int $tenantId, int $userId, string $date, float $hours, ?int $ignoreRequestId = null): ?string
    {
        if (! $this->overtimeEligible($tenantId, $userId)) {
            return 'This employee is not eligible for overtime.';
        }

        $cap = $this->overtimeMonthlyCap($tenantId, $userId);
        if ($cap !== null) {
            $month = \Carbon\Carbon::parse($date);
            $booked = (float) DB::table('overtime_requests')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->when($ignoreRequestId, fn ($q) => $q->where('id', '!=', $ignoreRequestId))
                ->sum(DB::raw('COALESCE(approved_hours, overtime_hours)'));
            if ($booked + $hours > $cap) {
                return "Overtime cannot exceed {$cap} hours in a month for this employee ({$booked} already booked).";
            }
        }

        return null;
    }

    // ----------------------------------------------------------------- leave

    /** This employee's custom rules for one leave type ([] = none). */
    public function leaveOverride(int $tenantId, int $userId, int $leaveTypeId): array
    {
        return (array) ($this->section($tenantId, $userId, 'leave')['type:' . $leaveTypeId] ?? []);
    }

    public function hasLeaveOverride(int $tenantId, int $userId, int $leaveTypeId): bool
    {
        return $this->leaveOverride($tenantId, $userId, $leaveTypeId) !== [];
    }

    /** False only when the leave type was switched off for this employee. */
    public function leaveAllowed(int $tenantId, int $userId, int $leaveTypeId): bool
    {
        return (bool) ($this->leaveOverride($tenantId, $userId, $leaveTypeId)['allowed'] ?? true);
    }

    /**
     * The leave type as this employee sees it: a copy with credit_value /
     * min_notice_days / max_consecutive_days replaced by their custom values.
     * Returns $leaveType itself when there are none.
     */
    public function leaveRule(int $tenantId, int $userId, object $leaveType): object
    {
        $custom = array_intersect_key(
            $this->leaveOverride($tenantId, $userId, (int) $leaveType->id),
            array_flip(['credit_value', 'min_notice_days', 'max_consecutive_days'])
        );
        if (! $custom) {
            return $leaveType;
        }

        $rule = $leaveType instanceof \Illuminate\Database\Eloquent\Model
            ? (object) $leaveType->getAttributes()
            : clone $leaveType;
        foreach ($custom as $column => $value) {
            $rule->{$column} = $value;
        }

        return $rule;
    }

    /**
     * Why this employee cannot take $leaveType from $start to $end, or null
     * when they can: the type is switched off for them, too little notice, or
     * too many consecutive days — each against their custom value when they
     * have one, else the leave type's own.
     *
     * $customOnly: check notice / length only where the employee has a custom
     * value (for entry points that never enforced the company rule).
     * $notice = false skips the notice check (admin / HR applying on behalf).
     */
    public function leaveRefusal(int $tenantId, int $userId, object $leaveType, $start, $end, bool $customOnly = false, bool $notice = true): ?string
    {
        $custom = $this->leaveOverride($tenantId, $userId, (int) $leaveType->id);
        if (! ($custom['allowed'] ?? true)) {
            return "{$leaveType->name} is not available for this employee.";
        }

        $rule = $this->leaveRule($tenantId, $userId, $leaveType);
        $applies = fn (string $column) => ! $customOnly || array_key_exists($column, $custom);

        if ($notice && $applies('min_notice_days') && ! empty($rule->min_notice_days)) {
            $noticeGiven = now()->startOfDay()->diffInDays($start->copy()->startOfDay(), false);
            if ($noticeGiven < $rule->min_notice_days) {
                return "{$leaveType->name} requires at least {$rule->min_notice_days} day(s) notice.";
            }
        }

        if ($applies('max_consecutive_days') && ! empty($rule->max_consecutive_days)) {
            $consecutiveDays = abs($start->diffInDays($end)) + 1;
            if ($consecutiveDays > $rule->max_consecutive_days) {
                return "{$leaveType->name} cannot be taken for more than {$rule->max_consecutive_days} consecutive day(s).";
            }
        }

        return null;
    }

    private function tableReady(): bool
    {
        if ($this->ready === null) {
            try {
                $this->ready = DB::getSchemaBuilder()->hasTable('employee_policy_overrides');
            } catch (\Throwable $e) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }
}
