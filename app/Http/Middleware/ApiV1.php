<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Tier 2 / T2-D — request/response conventions for the public API.
 *
 *  - forces JSON error handling (so validation etc. never returns HTML)
 *  - assigns / echoes an X-Request-Id for support correlation
 */
class ApiV1
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $requestId = ApiResponse::requestId();

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
