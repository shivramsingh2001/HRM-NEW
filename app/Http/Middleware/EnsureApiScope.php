<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Tier 2 / T2-B — `scope:attendance:write` etc. Checks the authenticated
 * ApiClient carries the scope; 403 with a stable code otherwise.
 */
class EnsureApiScope
{
    public function handle(Request $request, Closure $next, string $scope)
    {
        $client = app()->bound('current_api_client') ? app('current_api_client') : null;

        if (! $client || ! $client->hasScope($scope)) {
            return ApiResponse::fail('insufficient_scope', "This API key lacks the '{$scope}' scope.", [], 403);
        }

        return $next($request);
    }
}
