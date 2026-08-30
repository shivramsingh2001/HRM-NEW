<?php

namespace App\Helpers;

use App\Models\Tenant;

class TenantHelper
{
    public static function getTenantDetails($tenantId = null)
    {
        if (!$tenantId && auth()->check()) {
            $tenantId = auth()->user()->tenant_id ?? 1;
        }
        
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            return (object)[
                'company_name' => config('app.name', 'Our Company'),
                'email' => 'info@company.com',
                'phone' => '+91 0000000000',
                'address' => 'Company Address',
                'website' => config('app.url'),
                'logo' => null,
            ];
        }
        
        return $tenant;
    }
}