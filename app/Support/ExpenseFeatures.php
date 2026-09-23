<?php

namespace App\Support;

use App\Services\FeatureService;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place that answers "may this company route expense reimbursements through payroll?".
 * Used by the UI, the controllers, ExpenseReimbursementPayrollService and the payroll engine hook —
 * never scatter config()/feature checks for this.
 *
 * All four must hold (dependency rule):
 *   - `expense_payroll_link`  the per-company switch the Super Admin controls
 *   - `expense_management`    the expense module itself
 *   - `payroll`               the payroll module
 *   - the tenant runs the DYNAMIC payroll engine (`tenants.payroll_dynamic_ui_enabled`) — only that
 *     engine can add a reimbursement line to a payslip.
 */
class ExpenseFeatures
{
    public static function payrollRouteEnabled(?int $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        $features = app(FeatureService::class);

        return $features->enabled($tenantId, 'expense_payroll_link')
            && $features->enabled($tenantId, 'expense_management')
            && $features->enabled($tenantId, 'payroll')
            && (bool) DB::table('tenants')->where('id', $tenantId)->value('payroll_dynamic_ui_enabled');
    }

    /** Why the route is unavailable, for an error message; null when it is available. */
    public static function payrollRouteBlockedReason(?int $tenantId): ?string
    {
        if (self::payrollRouteEnabled($tenantId)) {
            return null;
        }

        return 'Paying reimbursements through payroll is not enabled for your company.';
    }
}
