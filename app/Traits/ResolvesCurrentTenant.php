<?php

namespace App\Traits;

use App\Models\User;

/**
 * Payroll Audit Phase 4 — M10. Single source of truth for "what tenant is
 * this request scoped to," replacing two separately-maintained
 * implementations: MonthlyPayrollController's currentTenantId() (web,
 * subdomain-bound `current_tenant` container binding) and
 * Api\Payroll\PayrollController's inline `$user->tenant_id ?? session(...)`
 * (JWT-guarded mobile API, no web session/subdomain resolution).
 *
 * Precedence: the container-bound tenant (set by web tenant-resolution
 * middleware) wins when present; otherwise the authenticated user's own
 * tenant_id (the only signal a JWT API request carries); session('tenant_id')
 * is the last-resort fallback for the rare context with neither.
 */
trait ResolvesCurrentTenant
{
    private function currentTenantId(?User $user = null): ?int
    {
        if (app()->bound('current_tenant') && app('current_tenant')) {
            return (int) app('current_tenant')->id;
        }

        $user ??= auth()->user();
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }

        return session('tenant_id') ? (int) session('tenant_id') : null;
    }
}
