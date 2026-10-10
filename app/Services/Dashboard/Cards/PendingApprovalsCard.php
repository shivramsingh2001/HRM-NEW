<?php

namespace App\Services\Dashboard\Cards;

use App\Models\AttendanceRegularization;
use App\Models\Expense;
use App\Models\Leave;
use App\Models\Shift;
use App\Services\RbacService;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard — "Needs your action": pending items per module the plan includes and the viewer may open.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class PendingApprovalsCard
{
    /**
     * "Needs your action": pending items per module, only for modules the plan
     * includes and the viewer may open (same permission as the list page).
     */
    public function build(callable $on): array
    {
        $user = Auth::user();
        $rbac = app(RbacService::class);
        $can = fn (string $module, string $action) => $rbac->can($user, $module, $action);

        $items = [
            ['leave', 'Leave requests', 'calendar', $on('leave_management') && $can('leave', 'view'),
                fn () => Leave::where('status', 'pending')->count(), 'leave.view-all'],
            ['regularization', 'Regularizations', 'edit-3', $on('regularization') && $can('attendance', 'approve'),
                fn () => AttendanceRegularization::where('status', 'pending')->count(), 'attendance-regularization.manage'],
            ['wfh', 'WFH & Travel', 'map', $on('wfh_travel') && $can('requests', 'view'),
                fn () => \App\Models\Request::where('status', \App\Models\Request::STATUS_PENDING)->count(), 'requests.index'],
            ['overtime', 'Overtime', 'clock', $on('overtime') && $can('overtime', 'view'),
                fn () => \App\Models\OvertimeRequest::where('status', 'pending')->count(), 'overtime.view-all'],
            ['expense', 'Expenses', 'credit-card', $on('expense_management') && $can('expenses', 'view'),
                fn () => Expense::where('status', 'pending')->count(), 'expense.view-all'],
            ['offboarding', 'Offboarding', 'log-out', $on('offboarding') && $can('offboarding', 'view'),
                fn () => \App\Models\OffboardingRequest::where('status', \App\Models\OffboardingRequest::STATUS_PENDING_APPROVAL)->count(), 'offboarding.index'],
            // Shift swaps / changes waiting for this approver (no RBAC module for shifts — role-gated like its routes).
            ['shift_request', 'Shift requests', 'shuffle', $on('custom_shift') && in_array($user->role, ['admin', 'hr', 'manager'], true),
                fn () => app(\App\Services\Shift\ShiftRequestPresenter::class)->approvalQuery($user)->count(), 'shift.requests.index'],
        ];

        $out = [];
        foreach ($items as [$key, $label, $icon, $visible, $count, $route]) {
            if (! $visible) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'count' => (int) $count(),
                'url' => route($route, match ($key) {
                    'expense' => ['status' => 'pending'], 'shift_request' => ['tab' => 'approvals'], default => []
                }),
            ];
        }

        return $out;
    }
}
