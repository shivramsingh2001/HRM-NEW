<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the Shift-management + Assign-Shift routes for tenants that run on a
 * single fixed company shift (custom_shifts_enabled = 0). The sidebar already
 * hides the menu; this stops direct requests too.
 */
class EnsureCustomShiftsEnabled
{
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

        return $next($request);
    }
}
