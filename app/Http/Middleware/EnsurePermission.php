<?php

namespace App\Http\Middleware;

use App\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant RBAC route gate — `permission:payroll,approve`.
 *
 * Applied across 45+ routes (payroll, leave, expense, task, team, overtime,
 * etc.) as the screen-by-screen transition from `role:` continues (Phase
 * 5.4) — `role:` still gates some routes directly, both are live
 * concurrently. `admin` bypasses via RbacService::can().
 */
class EnsurePermission
{
    public function __construct(private RbacService $rbac)
    {
    }

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();

        // Mobile API permission checks are switched off for now
        // (config rbac.api_permission_checks) — the user must still be signed in.
        if ($user && ! config('rbac.api_permission_checks') && $request->is('api/*') && ! $request->is('api/v1/*')) {
            return $next($request);
        }

        if (! $user || ! $this->rbac->can($user, $module, $action)) {
            if ($request->expectsJson()) {
                abort(403, 'You do not have permission for this action.');
            }

            return redirect()->route('dashboard')->withErrors(['error' => 'You do not have permission to access that.']);
        }

        return $next($request);
    }
}
