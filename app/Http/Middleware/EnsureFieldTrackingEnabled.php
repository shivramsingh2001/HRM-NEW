<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the Field-Tracking settings + assignment routes for tenants that have
 * not purchased the add-on (field_tracking_enabled = 0). The sidebar already
 * hides the menu; this stops direct requests too.
 */
class EnsureFieldTrackingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (!$tenant || !$tenant->field_tracking_enabled) {
            if ($request->expectsJson()) {
                abort(403, 'Field tracking is disabled for your company.');
            }

            return redirect()
                ->route('settings.field-tracking.index')
                ->with('error', 'Field tracking is not enabled for your company. Contact us to add it.');
        }

        return $next($request);
    }
}
