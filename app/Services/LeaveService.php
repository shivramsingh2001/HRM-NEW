<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;

/**
 * Shared leave-creation logic. Extracted from LeaveController::approveLeave so
 * that other flows (e.g. marking a day as leave from the attendance screen —
 * Feature A) apply the same balance + leave_transactions side effects.
 *
 * Also the single source of truth for "is this leave type LWP" and for
 * approving/cancelling an existing pending Leave — both the web and mobile
 * controllers call these instead of keeping their own copies (three
 * independent, drifting hardcoded-id implementations previously existed).
 */
class LeaveService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * A leave type is unpaid when its tenant-scoped row has is_unpaid=true —
     * the single authoritative paid/unpaid signal (also read by
     * MonthlyPayrollController and PerformanceCalculationService). Replaces
     * the old code==='lwp' check: is_unpaid lets a tenant flag ANY leave
     * type as unpaid, not just the one system LWP row, and LeaveTypeController
     * keeps code='lwp' => is_unpaid=true in sync on write.
     */
    public function isLwp(LeaveType $type): bool
    {
        return (bool) $type->is_unpaid;
    }

    public function isLwpId(?int $leaveTypeId): bool
    {
        if (!$leaveTypeId) {
            return false;
        }

        $type = LeaveType::find($leaveTypeId);

        return $type ? $this->isLwp($type) : false;
    }

    /**
     * Approve an existing pending Leave: deducts balance (unless LWP) and
     * writes the LeaveTransaction ledger row. Caller is expected to run this
     * inside its own DB transaction and rollback when success=false.
     *
     * @return array{success:bool, message:string}
     */
    public function approvePendingLeave(Leave $leave, $approver, ?string $remarks): array
    {
        $leaveDays = (float) $leave->leave_count;
        $tenantId = $leave->tenant_id;
        $leaveTypeId = (int) $leave->leave_type;
        $userId = $leave->user_id;
        $isLwp = $this->isLwpId($leaveTypeId);

        // Two requests for the same day/session may already be pending (applied
        // before the apply-time check existed): approving the second one must
        // not deduct the balance twice.
        $refusal = $this->applyRefusal((int) $userId, Carbon::parse($leave->start_date), Carbon::parse($leave->end_date),
            $this->normalizeSession($leave->start_session), $this->normalizeSession($leave->end_session), (int) $leave->id, ['approved']);
        if ($refusal) {
            return ['success' => false, 'message' => $refusal];
        }

        $balanceRecord = null;
        $currentBalance = 0.0;

        if (!$isLwp) {
            $balanceRecord = LeaveBalance::where('user_id', $userId)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
            $currentBalance = $balanceRecord ? (float) $balanceRecord->balance : 0.0;

            if ($currentBalance < $leaveDays) {
                return [
                    'success' => false,
                    'message' => "Insufficient leave balance. You have {$currentBalance} days available but requested {$leaveDays} days.",
                ];
            }
        }

        Leave::where('id', $leave->id)->update([
            'status' => 'approved',
            'status_update_by' => $approver->id,
            'status_update_remarks' => $remarks,
        ]);

        if ($isLwp) {
            LeaveTransaction::create([
                'tenant_id' => $tenantId,
                'leave_id' => $leave->id,
                'user_id' => $userId,
                'leave_type' => $leaveTypeId,
                'transaction_type' => 'sub',
                'total_leaves' => $leaveDays,
                'leaves_count' => 0,
                'leave_detail' => 'unpaid',
                'before_leaves' => 0,
                'after_leaves' => 0,
                'transaction_date' => now(),
                'status' => 1,
                'remarks' => "LWP Leave approved: {$remarks}",
            ]);

            $this->audit->record('tenant_user', $approver->id, (int) $tenantId, 'leave.approved', 'Leave', $leave->id, [], ['status' => 'approved', 'leave_detail' => 'unpaid', 'remarks' => $remarks]);

            return ['success' => true, 'message' => 'Leave approved successfully'];
        }

        $paidDays = min($currentBalance, $leaveDays);
        $newBalance = $currentBalance - $paidDays;
        $leaveDetail = $paidDays >= $leaveDays ? 'paid' : ($paidDays <= 0 ? 'unpaid' : 'mixed');

        if ($paidDays > 0 && $balanceRecord) {
            $balanceRecord->update(['balance' => $newBalance]);
        }

        LeaveTransaction::create([
            'tenant_id' => $tenantId,
            'leave_id' => $leave->id,
            'user_id' => $userId,
            'leave_type' => $leaveTypeId,
            'transaction_type' => 'sub',
            'total_leaves' => $leaveDays,
            'leaves_count' => $paidDays,
            'leave_detail' => $leaveDetail,
            'before_leaves' => $currentBalance,
            'after_leaves' => $newBalance,
            'transaction_date' => now(),
            'status' => 1,
            'remarks' => "Leave approved: {$remarks}",
        ]);

        $this->audit->record('tenant_user', $approver->id, (int) $tenantId, 'leave.approved', 'Leave', $leave->id, [], ['status' => 'approved', 'leave_detail' => $leaveDetail, 'paid_days' => $paidDays, 'remarks' => $remarks]);

        return ['success' => true, 'message' => 'Leave approved successfully'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function cancelPendingLeave(Leave $leave, $approver, ?string $remarks): array
    {
        Leave::where('id', $leave->id)->update([
            'status' => 'cancelled',
            'status_update_by' => $approver->id,
            'status_update_remarks' => $remarks,
        ]);

        $this->audit->record('tenant_user', $approver->id, (int) $leave->tenant_id, 'leave.rejected', 'Leave', $leave->id, [], ['status' => 'cancelled', 'remarks' => $remarks]);

        return ['success' => true, 'message' => 'Leave rejected successfully'];
    }

    /**
     * Revoke an already-*approved* leave (employee returns early, HR needs to
     * correct a mistake, etc.) and restore whatever balance was deducted at
     * approval time. Distinct from cancelPendingLeave(), which flips a still
     * -pending leave with no balance to restore. Restores exactly the
     * `leaves_count` recorded on the original approval's ledger row(s) — not
     * `leave_count` — so a leave that was only partially covered by balance
     * at approval (a 'mixed' ledger entry) is reversed by the same partial
     * amount, never over-crediting.
     *
     * @return array{success:bool, message:string}
     */
    public function cancelApprovedLeave(Leave $leave, $approver, ?string $remarks): array
    {
        if ($leave->status !== 'approved') {
            return ['success' => false, 'message' => 'Only an approved leave can be revoked this way.'];
        }

        $tenantId = $leave->tenant_id;
        $leaveTypeId = (int) $leave->leave_type;
        $userId = $leave->user_id;

        $paidDaysToRestore = (float) LeaveTransaction::where('leave_id', $leave->id)
            ->where('transaction_type', 'sub')
            ->sum('leaves_count');

        Leave::where('id', $leave->id)->update([
            'status' => 'cancelled',
            'status_update_by' => $approver->id,
            'status_update_remarks' => $remarks,
        ]);

        if ($paidDaysToRestore > 0) {
            $balanceRecord = LeaveBalance::where('user_id', $userId)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
            $currentBalance = $balanceRecord ? (float) $balanceRecord->balance : 0.0;
            $newBalance = $currentBalance + $paidDaysToRestore;

            if ($balanceRecord) {
                $balanceRecord->update(['balance' => $newBalance]);
            } else {
                LeaveBalance::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'leave_type_id' => $leaveTypeId,
                    'balance' => $newBalance,
                ]);
            }

            LeaveTransaction::create([
                'tenant_id' => $tenantId,
                'leave_id' => $leave->id,
                'user_id' => $userId,
                'leave_type' => $leaveTypeId,
                'transaction_type' => 'add',
                'total_leaves' => $paidDaysToRestore,
                'leaves_count' => $paidDaysToRestore,
                'leave_detail' => 'paid',
                'before_leaves' => $currentBalance,
                'after_leaves' => $newBalance,
                'transaction_date' => now(),
                'status' => 1,
                'remarks' => "Approved leave revoked, balance restored: {$remarks}",
            ]);
        }

        $this->audit->record(
            'tenant_user', $approver->id, (int) $tenantId, 'leave.cancelled_after_approval',
            'Leave', $leave->id, ['status' => 'approved'], ['status' => 'cancelled', 'balance_restored' => $paidDaysToRestore, 'remarks' => $remarks]
        );

        return ['success' => true, 'message' => 'Approved leave revoked and balance restored successfully'];
    }

    /**
     * Total deductible days for a leave application: walks every calendar
     * day from $start to $end inclusive, skipping weekends and any tenant
     * holiday, and applying half-day session counting on the first/last day.
     * The Leave row itself still stores the full requested start/end date —
     * only the deducted/reported day count excludes non-working days.
     */
    public function computeLeaveDays(
        // Any Carbon date: the web controllers pass Illuminate\Support\Carbon,
        // the mobile API passes Carbon\Carbon (a strict type here broke /api/apply-leave).
        \Carbon\CarbonInterface $start,
        \Carbon\CarbonInterface $end,
        string $startSession,
        string $endSession,
        ?int $tenantId = null
    ): float {
        // Work on mutable copies (the day loop below advances $current in place).
        $start = Carbon::instance($start);
        $end = Carbon::instance($end);

        $holidays = $tenantId
            ? Holiday::where('tenant_id', $tenantId)->where('status', 1)->get(['start_date', 'end_date'])
            : collect();

        $isNonWorkingDay = function (Carbon $date) use ($holidays) {
            if ($date->isSaturday() || $date->isSunday()) {
                return true;
            }

            foreach ($holidays as $holiday) {
                $holidayStart = Carbon::parse($holiday->start_date)->startOfDay();
                $holidayEnd = Carbon::parse($holiday->end_date ?? $holiday->start_date)->endOfDay();

                if ($date->between($holidayStart, $holidayEnd)) {
                    return true;
                }
            }

            return false;
        };

        $total = 0.0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (!$isNonWorkingDay($current)) {
                [$firstHalf, $secondHalf] = $this->halvesOn($current, $start, $end, $startSession, $endSession);
                $total += ($firstHalf ? 0.5 : 0) + ($secondHalf ? 0.5 : 0);
            }

            $current->addDay();
        }

        return $total;
    }

    /**
     * Which halves of $day a leave covers, as [firstHalf, secondHalf]. The start
     * session says where the leave begins (Session 2 = from the second half of
     * the start day), the end session where it ends (Session 1 = up to the first
     * half of the end day); every day in between is whole. So 23 Oct Session 1
     * → 24 Oct Session 1 is 1.5 days, and one day Session 1 → Session 2 is a full day.
     *
     * @return array{0:bool, 1:bool}
     */
    public function halvesOn(\Carbon\CarbonInterface $day, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, ?string $startSession, ?string $endSession): array
    {
        return [
            !$day->isSameDay($start) || $this->normalizeSession($startSession) !== 'session2',
            !$day->isSameDay($end) || $this->normalizeSession($endSession) !== 'session1',
        ];
    }

    /** Older rows store sessions as 1 / 2; empty means a full day. */
    private function normalizeSession(?string $session): string
    {
        return match ((string) $session) {
            'session1', '1' => 'session1',
            'session2', '2' => 'session2',
            default => 'fullday',
        };
    }

    /**
     * Why a leave for these dates/sessions cannot be applied, or null when it can:
     * a one-day leave starting in the second half and ending in the first half,
     * or a half of any day already taken by another leave of the employee in one
     * of $blockingStatuses. A rejected / cancelled leave never blocks — the
     * employee can apply for that day again. $ignoreLeaveId skips the leave being
     * edited or approved.
     *
     * Callers that create a leave call it again inside their transaction after
     * locking the employee (recheckUnderLock), so a double tap cannot get through twice.
     */
    public function applyRefusal(int $userId, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, string $startSession, string $endSession, ?int $ignoreLeaveId = null, array $blockingStatuses = ['pending', 'approved']): ?string
    {
        if ($start->isSameDay($end) && $this->normalizeSession($startSession) === 'session2' && $this->normalizeSession($endSession) === 'session1') {
            return 'A one-day leave cannot start in Session 2 (second half) and end in Session 1 (first half).';
        }

        $existing = Leave::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->whereIn('status', $blockingStatuses)
            ->when($ignoreLeaveId, fn ($q) => $q->where('id', '!=', $ignoreLeaveId))
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get(['id', 'leave_id', 'status', 'start_date', 'start_session', 'end_date', 'end_session']);

        foreach ($existing as $leave) {
            $leaveStart = Carbon::parse($leave->start_date)->startOfDay();
            $leaveEnd = Carbon::parse($leave->end_date)->startOfDay();
            $day = Carbon::instance($start)->startOfDay()->max($leaveStart)->copy();
            $lastDay = Carbon::instance($end)->startOfDay()->min($leaveEnd)->copy();

            for (; $day->lte($lastDay); $day->addDay()) {
                [$newFirst, $newSecond] = $this->halvesOn($day, $start, $end, $startSession, $endSession);
                [$oldFirst, $oldSecond] = $this->halvesOn($day, $leaveStart, $leaveEnd, $leave->start_session, $leave->end_session);
                $clash = ($newFirst && $oldFirst) ? ($newSecond && $oldSecond ? 'full day' : 'first half') : (($newSecond && $oldSecond) ? 'second half' : null);

                if ($clash) {
                    $ref = $leave->leave_id ?: "#{$leave->id}";

                    return "There is already {$this->article($leave->status)} {$leave->status} leave ({$ref}) on {$day->format('d M Y')} ({$clash}). A day can be applied again only after that leave is rejected or cancelled.";
                }
            }
        }

        return null;
    }

    private function article(string $word): string
    {
        return in_array(strtolower($word[0] ?? ''), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';
    }

    /**
     * Checks shared by the employee's own apply / edit forms (web and mobile):
     * same day + session already taken, working days in the range, balance
     * (skipped for LWP). Returns the deductible day count.
     *
     * @throws \DomainException with a message for the user when a check fails
     */
    public function checkApplication(int $userId, int $tenantId, int $leaveTypeId, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, string $startSession, string $endSession, ?int $ignoreLeaveId = null): float
    {
        if ($refusal = $this->applyRefusal($userId, $start, $end, $startSession, $endSession, $ignoreLeaveId)) {
            throw new \DomainException($refusal);
        }

        // One request, one row: total_days excludes weekends/holidays,
        // but start_date/end_date still store the full requested range.
        $days = $this->computeLeaveDays($start, $end, $startSession, $endSession, $tenantId);
        if ($days <= 0) {
            throw new \DomainException('The selected date range has no working days to apply leave for.');
        }

        if (!$this->isLwpId($leaveTypeId)) {
            $available = (float) LeaveBalance::where('user_id', $userId)->where('leave_type_id', $leaveTypeId)->value('balance');
            if ($available < $days) {
                throw new \DomainException("Insufficient leave balance. You have {$available} days available but requested {$days} days.");
            }
        }

        return $days;
    }

    /**
     * Save a new pending leave. The overlap check runs again under a lock on the
     * employee, so a double-tapped submit cannot create the same leave twice.
     *
     * @throws \DomainException when the day / session was taken meanwhile
     */
    public function createPending(array $attributes): Leave
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($attributes) {
            $this->recheckUnderLock((int) $attributes['user_id'], Carbon::parse($attributes['start_date']), Carbon::parse($attributes['end_date']),
                $attributes['start_session'], $attributes['end_session']);

            return Leave::create(['status' => 'pending'] + $attributes);
        });
    }

    /**
     * Lock the employee's user row until the surrounding transaction ends (so
     * concurrent submissions for one employee run one at a time), then repeat
     * the overlap check.
     *
     * @throws \DomainException
     */
    public function recheckUnderLock(int $userId, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, string $startSession, string $endSession, ?int $ignoreLeaveId = null): void
    {
        \App\Models\User::withoutGlobalScopes()->whereKey($userId)->lockForUpdate()->value('id');

        if ($refusal = $this->applyRefusal($userId, $start, $end, $startSession, $endSession, $ignoreLeaveId)) {
            throw new \DomainException($refusal);
        }
    }

    /**
     * Admin / HR applies a leave for an employee (Employee 360 → Leave, and
     * Leave → All Leaves → "Apply leave for employee"). The caller is the
     * approver, so it is created APPROVED and the balance deducted right away
     * (createApproved). Same checks as the employee's own form — the type's rules
     * or the employee's custom ones, working days, overlap, balance — except the
     * notice period. Logged as `leave.applied_on_behalf`; the employee is notified.
     *
     * @throws \DomainException with a message for the user when a check fails
     */
    public function applyOnBehalf(\App\Models\User $actor, \App\Models\User $employee, int $leaveTypeId, string $startDate, string $startSession, string $endDate, string $endSession, string $reason): array
    {
        $tenantId = (int) $employee->tenant_id;
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $leaveType = LeaveType::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($leaveTypeId);

        if ($refusal = app(EmployeePolicyService::class)->leaveRefusal($tenantId, (int) $employee->id, $leaveType, $start, $end, notice: false)) {
            throw new \DomainException($refusal);
        }

        if ($refusal = $this->applyRefusal((int) $employee->id, $start, $end, $startSession, $endSession)) {
            throw new \DomainException($refusal);
        }

        $days = $this->computeLeaveDays($start->copy(), $end->copy(), $startSession, $endSession, $tenantId);
        if ($days <= 0) {
            throw new \DomainException('The selected date range has no working days to apply leave for.');
        }

        if (! $this->isLwpId((int) $leaveType->id)) {
            $available = (float) LeaveBalance::withoutGlobalScopes()->where('user_id', $employee->id)->where('leave_type_id', $leaveType->id)->value('balance');
            if ($available < $days) {
                throw new \DomainException("Insufficient leave balance. {$available} day(s) available but {$days} day(s) requested. Credit the balance first, or use an unpaid leave type.");
            }
        }

        $leave = \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $employee, $tenantId, $leaveType, $start, $end, $startSession, $endSession, $reason, $days) {
            $this->recheckUnderLock((int) $employee->id, $start, $end, $startSession, $endSession);

            $leave = $this->createApproved([
                'tenant_id' => $tenantId,
                'user_id' => $employee->id,
                'leave_type_id' => (int) $leaveType->id,
                'start_date' => $start->toDateString(),
                'start_session' => $startSession,
                'end_date' => $end->toDateString(),
                'end_session' => $endSession,
                'leave_count' => $days,
                'reason' => $reason,
                'applied_by' => $actor->id,
            ]);
            // createApproved stamps its attendance-marking origin; this one was raised for the employee.
            $leave->update(['source' => 'on_behalf', 'total_days' => $days]);

            return $leave;
        });

        $this->audit->record('tenant_user', $actor->id, $tenantId, 'leave.applied_on_behalf', 'Leave', $leave->id, [], [
            'target_user_id' => $employee->id, 'on_behalf_of' => $employee->id, 'employee_name' => $employee->name,
            'raised_by' => $actor->name, 'raised_by_role' => $actor->role,
            'leave_type_id' => (int) $leaveType->id, 'leave_type' => $leaveType->name, 'days' => $days,
            'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
        ]);

        try {
            app(LeaveNotificationService::class)->notifyLeaveApproved($leave->fresh('user'), $reason);
        } catch (\Throwable $e) {
            // never block on notification
        }

        return ['leave' => $leave, 'days' => $days, 'type' => $leaveType];
    }

    /**
     * Create a pre-approved leave and apply the balance / ledger side effects,
     * with a loss-of-pay fallback when the balance is short.
     *
     * @param array{
     *   tenant_id:int, user_id:int, leave_type_id:int,
     *   start_date:string, end_date:string,
     *   start_session:string, end_session:string,
     *   leave_count:float, reason:?string, applied_by:int, deduct_balance?:bool
     * } $data
     */
    public function createApproved(array $data): Leave
    {
        $deduct = $data['deduct_balance'] ?? true;

        $leave = Leave::create([
            'tenant_id' => $data['tenant_id'],
            'user_id' => $data['user_id'],
            'leave_type' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'start_session' => $data['start_session'],
            'end_date' => $data['end_date'],
            'end_session' => $data['end_session'],
            'leave_count' => $data['leave_count'],
            'reason' => $data['reason'] ?? 'Marked from attendance',
            'status' => 'approved',
            'status_update_by' => $data['applied_by'],
            'source' => 'manual_attendance',
            'applied_by' => $data['applied_by'],
            'deduct_balance' => $deduct,
        ]);

        $this->applyLedger($leave, $deduct);

        // leave_id (LV-xxxxxx) is set by a BEFORE INSERT trigger — reload it.
        return $leave->refresh();
    }

    private function applyLedger(Leave $leave, bool $deduct): void
    {
        $leaveDays = (float) $leave->leave_count;
        $tenantId = $leave->tenant_id;
        $leaveTypeId = (int) $leave->leave_type;
        $userId = $leave->user_id;

        // LWP type, or the caller opted out of a balance deduction.
        if ($this->isLwpId($leaveTypeId) || !$deduct) {
            LeaveTransaction::create([
                'tenant_id' => $tenantId,
                'leave_id' => $leave->id,
                'user_id' => $userId,
                'leave_type' => $leaveTypeId,
                'transaction_type' => 'sub',
                'total_leaves' => $leaveDays,
                'leaves_count' => 0,
                'leave_detail' => 'unpaid',
                'before_leaves' => 0,
                'after_leaves' => 0,
                'transaction_date' => now(),
                'status' => 1,
                'remarks' => 'Leave marked from attendance (loss of pay)',
            ]);
            return;
        }

        $balanceRecord = LeaveBalance::where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        $currentBalance = $balanceRecord ? (float) $balanceRecord->balance : 0.0;
        $paidDays = min($currentBalance, $leaveDays);
        $newBalance = $currentBalance - $paidDays;

        $leaveDetail = $paidDays >= $leaveDays
            ? 'paid'
            : ($paidDays <= 0 ? 'unpaid' : 'mixed');

        if ($paidDays > 0 && $balanceRecord) {
            $balanceRecord->update(['balance' => $newBalance]);
        }

        LeaveTransaction::create([
            'tenant_id' => $tenantId,
            'leave_id' => $leave->id,
            'user_id' => $userId,
            'leave_type' => $leaveTypeId,
            'transaction_type' => 'sub',
            'total_leaves' => $leaveDays,
            'leaves_count' => $paidDays,
            'leave_detail' => $leaveDetail,
            'before_leaves' => $currentBalance,
            'after_leaves' => $newBalance,
            'transaction_date' => now(),
            'status' => 1,
            'remarks' => 'Leave marked from attendance',
        ]);
    }
}
