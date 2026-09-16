<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tier 2 / T2-B — replay-safe writes.
 *
 * For unsafe methods carrying an `Idempotency-Key` header:
 *   - a stored result for the same key + identical request body is replayed
 *   - the same key with a different body is a 409
 *   - otherwise the request runs and its response is stored (24h)
 *
 * Requires `current_api_client` (ResolveApiClient runs first).
 */
class Idempotency
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (! $key) {
            return $next($request);
        }
        if (strlen($key) > 80) {
            return ApiResponse::fail('invalid_idempotency_key', 'Idempotency-Key must be <= 80 chars.', [], 400);
        }

        $client = app('current_api_client');
        $hash = hash('sha256', $request->method() . '|' . $request->path() . '|' . $request->getContent());

        $existing = IdempotencyKey::where('tenant_id', $client->tenant_id)
            ->where('api_client_id', $client->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                return ApiResponse::fail('idempotency_key_reused', 'This Idempotency-Key was used with a different request.', [], 409);
            }
            if ($existing->response_status) {
                return response($existing->response_body, $existing->response_status)
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotent-Replayed', 'true');
            }
            // In-flight duplicate (row created, response not yet stored).
            return ApiResponse::fail('idempotency_in_progress', 'A request with this Idempotency-Key is still processing.', [], 409);
        }

        try {
            IdempotencyKey::create([
                'tenant_id' => $client->tenant_id,
                'api_client_id' => $client->id,
                'key' => $key,
                'request_hash' => $hash,
                'created_at' => now(),
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 1062) {
                return ApiResponse::fail('idempotency_in_progress', 'A request with this Idempotency-Key is still processing.', [], 409);
            }
            throw $e;
        }

        $response = $next($request);

        IdempotencyKey::where('tenant_id', $client->tenant_id)
            ->where('api_client_id', $client->id)
            ->where('key', $key)
            ->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);

        return $response->header('Idempotent-Replayed', 'false');
    }
}
