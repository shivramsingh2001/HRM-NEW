<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Limits on how much an employee may request (Employee 360 Phase 4):
 *   - WFH: days per month, advance notice            (company + per employee)
 *   - Regularization: requests per month, days back  (company + per employee)
 *   - Expense: claimed amount per month              (per employee only)
 *
 * Company values live on `tenants` (Company Policies → Request limits); an
 * employee's own value (Employee 360 → Policies) wins over it. 0 / empty =
 * no limit, so a company that never sets one behaves exactly as before.
 * Each *Refusal() returns the message to show, or null when the request is
 * within the limits. Used by the web and the mobile submit endpoints alike.
 */
class RequestLimitService
{
    public const REQUEST_KEYS = ['wfh_max_days_per_month', 'wfh_min_notice_days', 'regularization_max_per_month', 'regularization_max_days_back'];

    public function __construct(private EmployeePolicyService $employeePolicy)
    {
    }

    /** The company's values (0 = no limit). */
    public function company(int $tenantId): array
    {
        $row = DB::table('tenants')->where('id', $tenantId)->first(self::REQUEST_KEYS);

        return array_map(fn ($key) => (int) ($row->{$key} ?? 0), array_combine(self::REQUEST_KEYS, self::REQUEST_KEYS));
    }

    /** What applies to this employee: their own value, else the company's (0 = no limit). */
    public function forEmployee(int $tenantId, int $userId): array
    {
        $custom = $this->employeePolicy->section($tenantId, $userId, 'requests');
        $limits = $this->company($tenantId);
        foreach (self::REQUEST_KEYS as $key) {
            if (array_key_exists($key, $custom)) {
                $limits[$key] = (int) $custom[$key];
            }
        }

        return $limits;
    }

    /**
     * A regularization for $date: not further back than allowed, and not more
     * requests in that month than allowed (pending + approved; rejected ones
     * don't count). $ignoreId = the request being edited.
     */
    public function regularizationRefusal(int $tenantId, int $userId, string $date, ?int $ignoreId = null): ?string
    {
        $limits = $this->forEmployee($tenantId, $userId);
        $day = Carbon::parse($date)->startOfDay();

        if ($limits['regularization_max_days_back'] > 0
            && $day->lt(Carbon::today()->subDays($limits['regularization_max_days_back']))) {
            return "Regularization can only be requested for the last {$limits['regularization_max_days_back']} day(s).";
        }

        if ($limits['regularization_max_per_month'] > 0) {
            $count = DB::table('attendance_regularizations')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereBetween('date', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->count();
            if ($count >= $limits['regularization_max_per_month']) {
                return "At most {$limits['regularization_max_per_month']} regularization request(s) can be raised for " . $day->format('F Y') . ' — that many are already pending or approved.';
            }
        }

        return null;
    }

    /**
     * A WFH request from $start to $end (other request types are not limited):
     * enough notice, and not more WFH days in any month it touches than
     * allowed (calendar days of pending + approved WFH requests).
     */
    public function wfhRefusal(int $tenantId, int $userId, int $requestTypeId, string $start, string $end, ?int $ignoreId = null): ?string
    {
        if (! $this->isWfhType($requestTypeId)) {
            return null;
        }

        $limits = $this->forEmployee($tenantId, $userId);
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();

        if ($limits['wfh_min_notice_days'] > 0 && Carbon::today()->diffInDays($from, false) < $limits['wfh_min_notice_days']) {
            return "Work from home must be requested at least {$limits['wfh_min_notice_days']} day(s) in advance.";
        }

        if ($limits['wfh_max_days_per_month'] > 0) {
            $wfhTypeIds = DB::table('request_types')->where('tenant_id', $tenantId)->get(['id', 'type_name'])
                ->filter(fn ($t) => $this->looksLikeWfh($t->type_name))->pluck('id');
            $existing = DB::table('requests')
                ->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->whereIn('request_type_id', $wfhTypeIds)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where('start_date', '<=', $to->copy()->endOfMonth()->toDateString())
                ->where('end_date', '>=', $from->copy()->startOfMonth()->toDateString())
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->get(['start_date', 'end_date']);

            for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
                $monthStart = $month->copy();
                $monthEnd = $month->copy()->endOfMonth();
                $days = $this->daysInside($from, $to, $monthStart, $monthEnd);
                foreach ($existing as $r) {
                    $days += $this->daysInside(Carbon::parse($r->start_date), Carbon::parse($r->end_date), $monthStart, $monthEnd);
                }
                if ($days > $limits['wfh_max_days_per_month']) {
                    return "Work from home is limited to {$limits['wfh_max_days_per_month']} day(s) a month — this would make {$days} day(s) in " . $monthStart->format('F Y') . '.';
                }
            }
        }

        return null;
    }

    /**
     * An expense claim of $amount dated $date: the employee's own monthly
     * limit on claimed spend (reimbursements + settlements of the month, not
     * cancelled / withdrawn). Advances are not spend and are not limited.
     */
    public function expenseRefusal(int $tenantId, int $userId, string $date, float $amount, string $requirementType, ?int $ignoreId = null): ?string
    {
        $limit = (float) ($this->employeePolicy->section($tenantId, $userId, 'expense')['monthly_limit'] ?? 0);
        if ($limit <= 0 || $requirementType === 'advance') {
            return null;
        }

        $day = Carbon::parse($date);
        $claimed = (float) DB::table('expenses')
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->whereIn('requirement_type', ['reimbursement', 'settlement'])
            ->whereBetween('date', [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->sum('amount');

        if ($claimed + $amount > $limit) {
            $fmt = fn (float $v) => '₹' . number_format($v, 2);

            return "Expense claims are limited to {$fmt($limit)} a month for this employee. {$fmt($claimed)} is already claimed for "
                . $day->format('F Y') . ', so at most ' . $fmt(max(0, $limit - $claimed)) . ' more can be claimed.';
        }

        return null;
    }

    private function isWfhType(int $requestTypeId): bool
    {
        return $this->looksLikeWfh((string) DB::table('request_types')->where('id', $requestTypeId)->value('type_name'));
    }

    /** "WFH", "Work From Home", "work-from-home" … — travel requests are not limited. */
    private function looksLikeWfh(string $name): bool
    {
        $n = strtolower(preg_replace('/[^a-z]/i', '', $name));

        return $n === 'wfh' || str_contains($n, 'workfromhome');
    }

    private function daysInside(Carbon $from, Carbon $to, Carbon $windowStart, Carbon $windowEnd): int
    {
        $start = $from->gt($windowStart) ? $from : $windowStart;
        $end = $to->lt($windowEnd) ? $to : $windowEnd;

        return $start->lte($end) ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1 : 0;
    }
}
