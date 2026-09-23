<?php

namespace App\Services\User;

use App\Models\Tenant;

/**
 * Single generator for auto-assigned employee IDs, used by both the web
 * "Add Employee" wizard (UserController) and recruitment onboarding
 * (EmployeeProvisioningService) so the prefix stays consistent across both
 * creation paths.
 */
class EmployeeIdService
{
    public static function generate(int $tenantId, int $userId): string
    {
        $padding = (int) config('employee_id.padding_length', 6);

        return static::prefixFor($tenantId) . str_pad((string) $userId, $padding, '0', STR_PAD_LEFT);
    }

    public static function prefixFor(int $tenantId): string
    {
        $prefix = strtoupper(trim((string) Tenant::whereKey($tenantId)->value('employee_id_prefix')));

        return $prefix !== '' ? $prefix : strtoupper((string) config('employee_id.default_prefix', 'SH'));
    }
}
