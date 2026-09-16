<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Models\ApiClient;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * Tier 2 / T2-B — authenticates a public-API request from
 * `Authorization: Bearer <key_id>.<secret>`, binds `current_tenant` and
 * `current_api_client`, and logs the call.
 *
 * Registered as the `auth:apikey` behaviour via a viaRequest guard would also
 * work, but doing it as middleware lets us bind the tenant container singleton
 * (which the TenantTrait global scope reads) before controllers run.
 */
class ResolveApiClient
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        if (! $bearer || ! str_contains($bearer, '.')) {
            return ApiResponse::fail('unauthenticated', 'Missing or malformed API key.', [], 401);
        }

        [$keyId, $secret] = explode('.', $bearer, 2);

        $client = ApiClient::where('key_id', $keyId)->first();
        if (! $client || ! $client->secretMatches($secret) || ! $client->isUsable()) {
            return ApiResponse::fail('unauthenticated', 'Invalid or inactive API key.', [], 401);
        }

        $tenant = Tenant::find($client->tenant_id);
        if (! $tenant) {
            return ApiResponse::fail('unauthenticated', 'API key is not attached to a tenant.', [], 401);
        }

        app()->instance('current_tenant', $tenant);
        app()->instance('current_api_client', $client);
        $request->setUserResolver(fn () => $client);

        $startedAt = microtime(true);
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        \App\Models\ApiRequestLog::create([
            'tenant_id' => $client->tenant_id,
            'api_client_id' => $client->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'idempotency_key' => $request->header('Idempotency-Key'),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);

        return $response;
    }
}
