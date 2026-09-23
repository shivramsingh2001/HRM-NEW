<?php

namespace App\Http\Middleware;

use App\Services\FeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group to a platform feature key, e.g. `feature:payroll`.
 * Accepts a comma-separated list of keys (`feature:task_single,task_group`) and
 * passes if ANY of them is enabled — for modules with more than one sub-feature
 * where having either is enough to reach the shared route group.
 * No tenant context (should not happen on tenant routes) = allow.
 */
class EnsureFeatureEnabled
{
    public function __construct(private FeatureService $features)
    {
    }

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $keys = array_map('trim', explode(',', $key));
        $allowed = collect($keys)->contains(fn ($k) => $this->features->enabledForCurrentTenant($k));

        if (! $allowed) {
            if ($request->expectsJson()) {
                abort(403, "The {$key} module is not enabled for your plan.");
            }

            return redirect()->route('dashboard')
                ->withErrors(['error' => 'That module is not included in your current plan. Contact support to enable it.']);
        }

        return $next($request);
    }
}
