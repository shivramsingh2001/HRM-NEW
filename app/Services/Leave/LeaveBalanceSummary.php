<?php

namespace App\Services\Leave;

use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;

/**
 * One employee's leave balance in numbers for the My Leaves page cards:
 * available (leave_balances — what the apply check uses), credited / used
 * (leave_transactions, read the same way as Leave Credit → My Transactions)
 * and the available balance per leave type.
 */
class LeaveBalanceSummary
{
    /**
     * @return array{available: float, credited: float, used: float, by_type: \Illuminate\Support\Collection<int, array{id: int, name: string, balance: float}>}
     */
    public function forUser(int $userId): array
    {
        $byType = LeaveBalance::with('leaveType:id,name')->where('user_id', $userId)->get()
            ->filter(fn ($b) => $b->leaveType !== null)
            ->map(fn ($b) => ['id' => (int) $b->leave_type_id, 'name' => (string) $b->leaveType->name, 'balance' => (float) $b->balance])
            ->sortBy('name')
            ->values();

        $totals = LeaveTransaction::where('user_id', $userId)
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'add' THEN total_leaves END), 0) AS credited")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'sub' THEN leaves_count END), 0) AS used")
            ->first();

        return [
            'available' => (float) $byType->sum('balance'),
            'credited' => (float) ($totals->credited ?? 0),
            'used' => (float) ($totals->used ?? 0),
            'by_type' => $byType,
        ];
    }
}
