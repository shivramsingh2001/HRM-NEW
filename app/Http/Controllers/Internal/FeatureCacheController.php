<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\FeatureService;
use App\Services\RbacService;
use Illuminate\Http\Request;

/**
 * Machine-to-machine: the Super Admin Panel calls this after any feature
 * override / subscription change so the HRM's feature cache is fresh
 * immediately instead of after its TTL. Auth = shared secret header.
 */
class FeatureCacheController extends Controller
{
    public function bust(Request $request, FeatureService $features, RbacService $rbac)
    {
        $expected = config('services.superadmin.internal_token');
        if (! $expected || ! hash_equals($expected, (string) $request->header('X-Internal-Token'))) {
            abort(403);
        }

        $tenantId = (int) $request->input('tenant_id');
        if ($tenantId > 0) {
            $features->bust($tenantId);
        }

        $roleId = (int) $request->input('role_id');
        if ($roleId > 0) {
            $rbac->forgetRole($roleId);
        }

        if ($request->boolean('maintenance')) {
            app(\App\Services\MaintenanceModeService::class)->forget();
        }

        return response()->json(['ok' => true, 'tenant_id' => $tenantId, 'role_id' => $roleId]);
    }
}
