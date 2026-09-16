<?php

namespace App\Http\Middleware;

use App\Services\FeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group to a platform feature key, e.g. `feature:payroll`.
 * No tenant context (should not happen on tenant routes) = allow.
 */
class EnsureFeatureEnabled
{
    public function __construct(private FeatureService $features)
    {
    }

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $this->features->enabledForCurrentTenant($key)) {
            if ($request->expectsJson()) {
                abort(403, "The {$key} module is not enabled for your plan.");
            }

            return redirect()->route('dashboard')
                ->withErrors(['error' => 'That module is not included in your current plan. Contact support to enable it.']);
        }

        return $next($request);
    }
}
