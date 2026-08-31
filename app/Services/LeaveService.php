<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;

/**
 * Shared leave-creation logic. Extracted from LeaveController::approveLeave so
 * that other flows (e.g. marking a day as leave from the attendance screen —
 * Feature A) apply the same balance + leave_transactions side effects.
 */
class LeaveService
{
    /** Loss-of-pay leave type id (matches the check in LeaveController). */
    private const LWP_LEAVE_TYPE_ID = 3;

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
        if ($leaveTypeId === self::LWP_LEAVE_TYPE_ID || !$deduct) {
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
