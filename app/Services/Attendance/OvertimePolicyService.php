<?php

namespace App\Services\Attendance;

use App\Models\OvertimeRequest;
use App\Models\OvertimeSetting;
use App\Services\AuditLogger;
use App\Services\EmployeePolicyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Company Policies → Overtime: the one place that knows the company's overtime
 * switch, mode (request & approval / automatic) and limits, with the employee's
 * Employee 360 overrides on top. Used by web + mobile requests, on-behalf
 * entries, approvals, AutoOvertimeService and payroll (OvertimePayService).
 */
class OvertimePolicyService
{
    /** Values used when a company has never saved its overtime settings. */
    public const DEFAULTS = [
        'enabled' => true,
        'mode' => OvertimeSetting::MODE_REQUEST,
        'auto_start_basis' => 'grace',
        'auto_start_after_minutes' => 0,
        'min_hours' => null,
        'rate_type' => 'multiplier',
        'rate_multiplier' => 1.5,
        'fixed_rate_per_hour' => null,
        'max_hours_per_day' => null,
        'max_hours_per_month' => null,
        'require_approval' => true,
        'auto_approve_limit' => null,
    ];

    /** @var array<int,OvertimeSetting> */
    private array $company = [];

    public function __construct(private EmployeePolicyService $employeePolicy)
    {
    }

    public function company(int $tenantId): OvertimeSetting
    {
        return $this->company[$tenantId] ??= OvertimeSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
            ?? new OvertimeSetting(['tenant_id' => $tenantId] + self::DEFAULTS);
    }

    public function forget(): void
    {
        $this->company = [];
    }

    public function enabled(int $tenantId): bool
    {
        return (bool) ($this->company($tenantId)->enabled ?? true);
    }

    public function isAuto(int $tenantId): bool
    {
        return $this->enabled($tenantId) && $this->company($tenantId)->mode === OvertimeSetting::MODE_AUTO;
    }

    /** The settings this employee follows: company row + Employee 360 overrides, as a plain object. */
    public function forEmployee(int $tenantId, int $userId): object
    {
        $company = (object) array_merge(self::DEFAULTS, array_filter(
            $this->company($tenantId)->only(array_keys(self::DEFAULTS)),
            fn ($v) => $v !== null
        ));

        $settings = $this->employeePolicy->overtime($tenantId, $userId, $company);
        $settings->eligible = $this->employeePolicy->overtimeEligible($tenantId, $userId);

        return $settings;
    }

    /** Monthly cap in hours for this employee (Employee 360 value, else the company's); null = none. */
    public function monthlyCap(int $tenantId, int $userId): ?float
    {
        $cap = (float) ($this->forEmployee($tenantId, $userId)->max_hours_per_month ?? 0);

        return $cap > 0 ? $cap : null;
    }

    /** Hours already booked (pending + approved, or $statuses) in $date's month. */
    public function bookedHours(int $tenantId, int $userId, string $date, ?int $ignoreId = null, array $statuses = ['pending', 'approved']): float
    {
        $month = Carbon::parse($date);

        return round((float) DB::table('overtime_requests')
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereIn('status', $statuses)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->sum(DB::raw('COALESCE(approved_hours, overtime_hours)')), 2);
    }

    /**
     * Why $hours of overtime on $date cannot be booked, or null when it can.
     * $byEmployee = the employee raising it themselves (blocked in automatic
     * mode); false for HR entries and approvals.
     */
    public function refusal(int $tenantId, int $userId, string $date, float $hours, ?int $ignoreId = null, bool $byEmployee = true): ?string
    {
        if (! $this->enabled($tenantId)) {
            return 'Overtime is turned off for your company.';
        }
        if ($byEmployee && $this->isAuto($tenantId)) {
            return 'Overtime is calculated automatically from your attendance — no request is needed.';
        }

        $s = $this->forEmployee($tenantId, $userId);
        if (! $s->eligible) {
            return 'This employee is not eligible for overtime.';
        }

        $min = (float) ($s->min_hours ?? 0);
        if ($min > 0 && $hours < $min) {
            return 'Overtime must be at least ' . $this->hours($min) . ' per day.';
        }

        $maxDay = (float) ($s->max_hours_per_day ?? 0);
        if ($maxDay > 0 && $hours > $maxDay) {
            return "Overtime hours cannot exceed {$this->hours($maxDay)} per day.";
        }

        $cap = $this->monthlyCap($tenantId, $userId);
        if ($cap !== null) {
            $booked = $this->bookedHours($tenantId, $userId, $date, $ignoreId);
            if ($booked + $hours > $cap + 0.001) {
                return "Overtime cannot exceed {$this->hours($cap)} in a month ({$this->hours($booked)} already booked).";
            }
        }

        return null;
    }

    /** pending / approved for a new employee request, per require_approval + auto_approve_limit. */
    public function initialStatus(int $tenantId, int $userId, float $hours): string
    {
        $s = $this->forEmployee($tenantId, $userId);
        if (! $s->require_approval) {
            return 'approved';
        }
        $limit = (float) ($s->auto_approve_limit ?? 0);

        return $limit > 0 && $hours <= $limit ? 'approved' : 'pending';
    }

    /**
     * The existing row for this employee and date (one per date — DB unique),
     * or null. A REJECTED row can be raised again: callers reuse it through
     * saveRequest().
     */
    public function existing(int $userId, string $date): ?OvertimeRequest
    {
        return OvertimeRequest::withoutGlobalScopes()->where('user_id', $userId)->whereDate('date', $date)->first();
    }

    /**
     * Create the request, or reopen this date's rejected one (the old decision
     * is kept in the audit log). Returns null when a pending/approved row exists.
     */
    public function saveRequest(int $tenantId, int $userId, string $date, array $attributes): ?OvertimeRequest
    {
        $existing = $this->existing($userId, $date);
        if ($existing && $existing->status !== 'rejected') {
            return null;
        }

        $attributes += ['source' => OvertimeRequest::SOURCE_REQUEST];
        if (! $existing) {
            return OvertimeRequest::create(['tenant_id' => $tenantId, 'user_id' => $userId, 'date' => $date] + $attributes);
        }

        $old = $existing->only(['status', 'overtime_hours', 'approved_hours', 'rejection_reason', 'approved_by', 'approved_at', 'source']);
        $existing->fill($attributes + [
            'approved_by' => null, 'approved_hours' => null, 'approved_at' => null, 'rejection_reason' => null,
            'attendance_id' => null, 'auto_minutes' => null, 'manually_adjusted_at' => null,
        ])->save();
        app(AuditLogger::class)->record('user', auth()->id(), $tenantId, 'overtime.resubmitted', 'OvertimeRequest', (int) $existing->id,
            $old, $existing->only(['status', 'overtime_hours', 'approved_hours']));

        return $existing;
    }

    public function hours(float $h): string
    {
        $n = rtrim(rtrim(number_format($h, 2, '.', ''), '0'), '.');

        return $n . ' hour' . ($n === '1' ? '' : 's');
    }
}
