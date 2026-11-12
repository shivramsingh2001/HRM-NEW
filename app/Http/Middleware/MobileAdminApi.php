<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Front door of the mobile admin API (/api/admin/*):
 *  - treats the request as wanting JSON, so a failed FormRequest answers 422 JSON
 *    and abort() answers JSON, whatever Accept header the app sends;
 *  - refuses the call when TenantMiddleware bound no company (inactive / missing
 *    tenant) — without `current_tenant` the tenant global scope is off and company
 *    -wide admin queries would not be limited to the caller's company.
 */
class MobileAdminApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $user = $request->user();
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $user || ! $tenant || (int) $tenant->id !== (int) $user->tenant_id) {
            return response()->json(['success' => false, 'message' => 'Your company account is not active.'], 403);
        }
        // Same rule as the `role:admin,hr` that follows on the route group, answered in the app's usual shape.
        if (! in_array($user->role, ['admin', 'hr'], true)) {
            return response()->json(['success' => false, 'message' => 'Only Admin and HR can use this.'], 403);
        }

        return $next($request);
    }
}
