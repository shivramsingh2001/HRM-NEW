<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the number of employee seats a tenant purchased. Modeled directly
 * on FieldTrackingService's seat-cap shape. tenant_subscriptions has no
 * Eloquent model in this app (that table is otherwise only managed from the
 * separate hrm-superadmin app) — read via a raw query, mirroring exactly how
 * hrm-superadmin's own TenantSubscription::scopeActive() defines "active".
 *
 * Not yet wired into the manual Add-Employee flow — only the biometric
 * direct-onboarding path (BiometricEmployeeProvisioningService) uses this so
 * far. See docs/modules.md for the flagged follow-up.
 */
class EmployeeCapService
{
    private array $tenantCache = [];

    public function employeeCountUsed(int $tenantId): int
    {
        return (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->count();
    }

    public function employeeCountPurchased(Tenant $tenant): int
    {
        $override = DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('max_employees_override')
            ->orderByDesc('id')
            ->value('max_employees_override');

        return (int) ($override ?? $tenant->max_employees ?? 100);
    }

    /** @return array{ok:bool, reason:?string} */
    public function canCreateEmployee(int $tenantId): array
    {
        $tenant = $this->tenant($tenantId);

        if (! $tenant) {
            return ['ok' => false, 'reason' => 'Tenant not found.'];
        }

        $purchased = $this->employeeCountPurchased($tenant);
        if ($this->employeeCountUsed($tenantId) >= $purchased) {
            return [
                'ok' => false,
                'reason' => "All {$purchased} purchased employee seats are in use. Contact us to add more seats.",
            ];
        }

        return ['ok' => true, 'reason' => null];
    }

    private function tenant(int $tenantId): ?Tenant
    {
        return $this->tenantCache[$tenantId] ??= Tenant::find($tenantId);
    }
}
