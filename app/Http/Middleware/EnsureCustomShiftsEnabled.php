<?php

namespace App\Http\Middleware;

use App\Services\FeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the Shift-management + Assign-Shift routes for tenants that run on a
 * single fixed company shift (custom_shifts_enabled = 0), or whose plan no
 * longer includes the custom_shift feature even if that column is still set
 * from before. The sidebar already hides the menu; this stops direct requests
 * too.
 */
class EnsureCustomShiftsEnabled
{
    public function __construct(private FeatureService $features)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (!$tenant || !$tenant->custom_shifts_enabled) {
            if ($request->expectsJson()) {
                abort(403, 'Custom shifts are disabled for your company.');
            }

            return redirect()
                ->route('shift-settings.index')
                ->with('error', 'Custom shifts are disabled for your company. Enable them here first.');
        }

        if (! $this->features->enabledForCurrentTenant('custom_shift')) {
            if ($request->expectsJson()) {
                abort(403, 'Custom shifts are not included in your plan.');
            }

            return redirect()
                ->route('shift-settings.index')
                ->with('error', 'Custom shifts are not included in your current plan. Contact support to enable it.');
        }

        return $next($request);
    }
}
