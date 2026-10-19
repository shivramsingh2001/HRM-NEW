<?php

namespace App\Services\Leave;

use App\Models\CompOffCredit;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserWeekoffs;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\PolicyResolver;
use App\Services\AuditLogger;
use App\Support\WeekOffPredicate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Comp-off (plan B, 2026-10-17). Working one of your week-offs or a company
 * holiday earns a day off to take later (Company Policies → Comp-off, per
 * employee on Employee 360 → Policies).
 *
 *  - Earning: ≥ full-day hours → 1 day, ≥ half-day hours → ½ day (hours worked
 *    that day). Request mode: the employee asks, a manager / HR / admin
 *    approves (an `approval_workflows` "comp_off" workflow when configured —
 *    CompOffApprovalHandler — else the single approver; never your own). Auto
 *    mode: `comp-off:auto-credit` credits them nightly.
 *  - A day taken as comp-off cannot also be paid as overtime: a day with
 *    pending / approved overtime cannot be claimed, and approving comp-off
 *    rejects that day's pending overtime. A week-off worked under a week-off
 *    swap already gave another day off, so it cannot be claimed either.
 *  - An approved credit adds its days to the balance of the system "Comp Off"
 *    leave type (code comp_off, created on first use); the employee spends it
 *    through the normal leave application. Unused days lapse after the expiry
 *    (`comp-off:expire`, oldest credit first).
 *  - Optional cap on days earned per calendar month.
 */
class CompOffService
{
    public const CODE = 'comp_off';

    public function __construct(private PolicyResolver $policies) {}

    /** The company's "Comp Off" leave type, created on first use. */
    public function leaveType(int $tenantId): LeaveType
    {
        return LeaveType::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => self::CODE],
            ['name' => 'Comp Off', 'credit_type' => 'no', 'credit_value' => 0, 'status' => 1, 'is_unpaid' => false,
                'description' => 'Earned by working a week-off or holiday (Comp-off page). Taken like any other leave.']
        );
    }

    public function balance(int $tenantId, int $userId): float
    {
        $type = LeaveType::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', self::CODE)->first();

        return $type ? (float) (LeaveBalance::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->where('leave_type_id', $type->id)->value('balance') ?? 0) : 0.0;
    }

    /**
     * Can $date be claimed? Returns day_type / hours / days, and `reason` when it cannot.
     *
     * @return array{date:string, eligible:bool, reason:?string, day_type:?string, hours:float, days:float}
     */
    public function dayInfo(int $tenantId, int $userId, string $date, ?int $ignoreCreditId = null): array
    {
        $out = ['date' => $date, 'eligible' => false, 'reason' => null, 'day_type' => null, 'hours' => 0.0, 'days' => 0.0];
        $policy = $this->policies->forUserDate($tenantId, $userId, $date);
        $row = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->first(['clock_in', 'clock_out', 'worked_hours']);
        $out['hours'] = round((float) ($row->worked_hours ?? 0), 2);
        $out['day_type'] = $this->offDayType($tenantId, $userId, $date);
        $out['days'] = $out['hours'] >= $policy->compOffFullDayHours ? 1.0 : ($out['hours'] >= $policy->compOffHalfDayHours ? 0.5 : 0.0);

        $out['reason'] = match (true) {
            ! $policy->compOffEnabled => 'Comp-off is not on for this employee',
            $date >= now()->toDateString() => 'Only a day already worked can be claimed',
            ! $row || empty($row->clock_in) || empty($row->clock_out) => 'No completed clock-in / clock-out that day',
            $out['day_type'] === null => 'Not a week-off or holiday',
            $out['days'] <= 0 => "Worked {$out['hours']} h — less than the {$policy->compOffHalfDayHours} h needed for half a day",
            $this->claimed($tenantId, $userId, $date, $ignoreCreditId) => 'Already claimed as comp-off',
            $this->overtimeTaken($tenantId, $userId, $date) => 'Already claimed as overtime',
            $this->swapped($tenantId, $userId, $date) => 'A week-off swap already gave another day off for it',
            default => $this->capReason($tenantId, $userId, $date, $out['days'], $policy->compOffMonthlyCap, $ignoreCreditId),
        };
        $out['eligible'] = $out['reason'] === null;

        return $out;
    }

    /**
     * Worked week-offs / holidays in [$from, $to] that can still be claimed (for the request form).
     *
     * @return array<int, array{date:string, eligible:bool, reason:?string, day_type:?string, hours:float, days:float}>
     */
    public function claimableDays(int $tenantId, int $userId, string $from, string $to): array
    {
        $dates = DB::table('attendances')->where('tenant_id', $tenantId)->where('user_id', $userId)->whereBetween('date', [$from, $to])
            ->whereNotNull('clock_in')->whereNotNull('clock_out')->orderByDesc('date')->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10));

        return $dates->map(fn ($d) => $this->dayInfo($tenantId, $userId, $d))->filter(fn ($i) => $i['eligible'])->values()->all();
    }

    /** The employee asks for comp-off for a worked off-day. */
    public function request(User $user, string $date, ?string $reason): CompOffCredit
    {
        $tenantId = (int) $user->tenant_id;
        $info = $this->dayInfo($tenantId, (int) $user->id, $date);
        if (! $info['eligible']) {
            throw new RuntimeException($info['reason']);
        }
        $credit = CompOffCredit::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'user_id' => $user->id, 'worked_date' => $date, 'day_type' => $info['day_type'],
            'worked_hours' => $info['hours'], 'days' => $info['days'], 'status' => 'pending', 'source' => 'request', 'reason' => $reason,
        ]);
        app(AuditLogger::class)->record('tenant_user', $user->id, $tenantId, 'comp_off.requested', 'comp_off_credits', $credit->id, [], $info);

        return $credit;
    }

    /**
     * Approve / reject a pending request. Goes through the company's "comp_off"
     * approval workflow when one is configured (a level at a time), else is final.
     * The caller has already checked the actor may decide for this employee.
     */
    public function decide(CompOffCredit $credit, User $actor, string $action, ?string $remarks = null): CompOffCredit
    {
        if ($credit->status !== 'pending') {
            throw new RuntimeException("This comp-off is already {$credit->status}.");
        }
        if (\App\Support\SelfApproval::blocks($actor, (int) $credit->user_id)) {
            throw new RuntimeException(\App\Support\SelfApproval::MESSAGE);
        }
        if ($action === 'approved') {
            // Re-check: overtime / a swap / the cap may have changed since the request.
            $info = $this->dayInfo((int) $credit->tenant_id, (int) $credit->user_id, $credit->worked_date->toDateString(), (int) $credit->id);
            if (! $info['eligible']) {
                throw new RuntimeException($info['reason']);
            }
        }

        $flow = app(ApprovalService::class)->decide('comp_off', $credit, $actor, $action, $remarks);
        if ($flow === null) {
            $action === 'approved' ? $this->applyApproved($credit, (int) $actor->id, $remarks) : $this->applyRejected($credit, (int) $actor->id, $remarks);
        }

        return $credit->fresh();
    }

    /** Final approval: add the days to the Comp Off balance (also from CompOffApprovalHandler and auto mode). */
    public function applyApproved(CompOffCredit $credit, ?int $actorId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($credit, $actorId, $remarks) {
            $tenantId = (int) $credit->tenant_id;
            $type = $this->leaveType($tenantId);
            $policy = $this->policies->forUserDate($tenantId, (int) $credit->user_id, $credit->worked_date->toDateString());

            $balance = LeaveBalance::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $credit->user_id, 'leave_type_id' => $type->id], ['balance' => 0]
            );
            $before = (float) $balance->getAttribute('balance');
            $balance->update(['balance' => $before + $credit->days]);
            LeaveTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'user_id' => $credit->user_id, 'leave_type' => $type->id, 'transaction_type' => 'add',
                'total_leaves' => $credit->days, 'leaves_count' => $credit->days, 'before_leaves' => $before, 'after_leaves' => $before + $credit->days,
                'transaction_date' => now(), 'status' => 1,
                'remarks' => 'Comp-off earned: worked '.$credit->worked_date->format('d M Y').($credit->day_type === 'holiday' ? ' (holiday)' : ' (week-off)'),
            ]);

            $credit->update([
                'status' => 'approved', 'decided_by' => $actorId, 'decided_at' => now(), 'decision_remarks' => $remarks, 'leave_type_id' => $type->id,
                'expires_on' => $policy->compOffExpiryDays > 0 ? $credit->worked_date->copy()->addDays($policy->compOffExpiryDays)->toDateString() : null,
            ]);

            // The day is paid as comp-off now, not overtime.
            DB::table('overtime_requests')->where('tenant_id', $tenantId)->where('user_id', $credit->user_id)
                ->where('date', $credit->worked_date->toDateString())->where('status', 'pending')
                ->update(['status' => 'rejected', 'rejection_reason' => 'Taken as comp-off instead', 'approved_by' => $actorId, 'approved_at' => now(), 'updated_at' => now()]);
        });
        app(AuditLogger::class)->record('tenant_user', $actorId, (int) $credit->tenant_id, 'comp_off.approved', 'comp_off_credits', $credit->id, [], ['days' => $credit->days]);
    }

    public function applyRejected(CompOffCredit $credit, ?int $actorId, ?string $remarks = null): void
    {
        $credit->update(['status' => 'rejected', 'decided_by' => $actorId, 'decided_at' => now(), 'decision_remarks' => $remarks]);
        app(AuditLogger::class)->record('tenant_user', $actorId, (int) $credit->tenant_id, 'comp_off.rejected', 'comp_off_credits', $credit->id, [], ['remarks' => $remarks]);
    }

    /** The employee withdraws their own pending request. */
    public function cancel(CompOffCredit $credit, User $actor): void
    {
        if ($credit->status !== 'pending' || (int) $credit->user_id !== (int) $actor->id) {
            throw new RuntimeException('Only your own pending request can be cancelled.');
        }
        $credit->update(['status' => 'cancelled']);
    }

    /**
     * Auto mode: credit every claimable worked off-day in [$from, $to] for one employee.
     *
     * @return string[] dates credited
     */
    public function autoCredit(int $tenantId, int $userId, string $from, string $to): array
    {
        $done = [];
        foreach (array_reverse($this->claimableDays($tenantId, $userId, $from, $to)) as $info) {
            if ($this->policies->forUserDate($tenantId, $userId, $info['date'])->compOffMode !== 'auto') {
                continue;
            }
            $credit = CompOffCredit::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId, 'user_id' => $userId, 'worked_date' => $info['date'], 'day_type' => $info['day_type'],
                'worked_hours' => $info['hours'], 'days' => $info['days'], 'status' => 'pending', 'source' => 'auto',
            ]);
            $this->applyApproved($credit, null, 'Credited automatically');
            $done[] = $info['date'];
        }

        return $done;
    }

    /**
     * Lapse the unused part of credits whose expiry date has passed. Leave taken
     * spends the oldest credit first, so what is left of an expiring credit is
     * the balance minus the newer credits still valid (never below 0, never above
     * the credit).
     *
     * @return int credits expired
     */
    public function expire(?string $today = null): int
    {
        $today ??= now()->toDateString();
        // Every company's due credits; each is then loaded within its own tenant.
        $due = DB::table('comp_off_credits')->where('status', 'approved')->whereNotNull('expires_on')
            ->where('expires_on', '<', $today)->orderBy('worked_date')->get(['id', 'tenant_id']);
        foreach ($due as $row) {
            $credit = CompOffCredit::withoutGlobalScopes()->where('tenant_id', $row->tenant_id)->findOrFail($row->id);
            DB::transaction(function () use ($credit) {
                $tenantId = (int) $credit->tenant_id;
                $balance = LeaveBalance::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $credit->user_id)
                    ->where('leave_type_id', $credit->leave_type_id)->lockForUpdate()->first();
                $current = (float) ($balance?->getAttribute('balance') ?? 0);
                $newer = (float) CompOffCredit::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $credit->user_id)
                    ->where('status', 'approved')->where('id', '!=', $credit->id)->where('worked_date', '>', $credit->worked_date->toDateString())->sum('days');
                $lapse = max(0.0, min((float) $credit->days, $current - $newer));

                if ($lapse > 0 && $balance) {
                    $balance->update(['balance' => $current - $lapse]);
                    LeaveTransaction::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId, 'user_id' => $credit->user_id, 'leave_type' => $credit->leave_type_id, 'transaction_type' => 'sub',
                        'total_leaves' => $lapse, 'leaves_count' => $lapse, 'before_leaves' => $current, 'after_leaves' => $current - $lapse,
                        'transaction_date' => now(), 'status' => 1,
                        'remarks' => 'Comp-off expired: worked '.$credit->worked_date->format('d M Y').", {$lapse} day(s) unused",
                    ]);
                }
                $credit->update(['status' => 'expired', 'lapsed_days' => $lapse]);
            });
        }

        return $due->count();
    }

    private function offDayType(int $tenantId, int $userId, string $date): ?string
    {
        $holiday = DB::table('holidays')->where('tenant_id', $tenantId)->where('status', 1)->where('start_date', '<=', $date)
            ->whereRaw('COALESCE(NULLIF(end_date, \'\'), start_date) >= ?', [$date])->exists();
        if ($holiday) {
            return 'holiday';
        }
        $weekoffs = UserWeekoffs::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 1)->get();

        return WeekOffPredicate::isWeekOff($weekoffs, Carbon::parse($date)) ? 'week_off' : null;
    }

    private function claimed(int $tenantId, int $userId, string $date, ?int $ignoreCreditId = null): bool
    {
        return CompOffCredit::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('worked_date', $date)
            ->whereIn('status', ['pending', 'approved', 'expired'])->when($ignoreCreditId, fn ($q) => $q->where('id', '!=', $ignoreCreditId))->exists();
    }

    private function overtimeTaken(int $tenantId, int $userId, string $date): bool
    {
        return DB::table('overtime_requests')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('date', $date)
            ->where('status', 'approved')->exists();
    }

    private function swapped(int $tenantId, int $userId, string $date): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('weekoff_swap_employees')
            && DB::table('weekoff_swap_employees')->where('tenant_id', $tenantId)->where('user_id', $userId)
                ->where('worked_date', $date)->where('status', 'active')->exists();
    }

    private function capReason(int $tenantId, int $userId, string $date, float $days, int $cap, ?int $ignoreCreditId = null): ?string
    {
        if ($cap <= 0) {
            return null;
        }
        $month = Carbon::parse($date);
        $earned = (float) CompOffCredit::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'expired'])->when($ignoreCreditId, fn ($q) => $q->where('id', '!=', $ignoreCreditId))
            ->whereBetween('worked_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->sum('days');

        return $earned + $days > $cap ? "Only {$cap} comp-off day(s) can be earned per month ({$earned} already)" : null;
    }
}
