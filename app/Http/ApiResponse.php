<?php

namespace App\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Tier 2 / T2-D — the one response shape for the public API (`/api/v1/*`).
 *
 *   success: { "data": <payload>, "meta": { "request_id": "...", ... } }
 *   failure: { "error": { "code": "...", "message": "...", "details": [...] },
 *              "meta": { "request_id": "..." } }
 *
 * NEVER used for the legacy mobile API under /api/user/* — those bodies are
 * frozen. ApiV1EnvelopeTest guards that boundary.
 */
class ApiResponse
{
    public static function ok(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(['request_id' => self::requestId()], $meta),
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $p, ?callable $map = null, array $meta = []): JsonResponse
    {
        $items = $map ? collect($p->items())->map($map)->all() : $p->items();

        return self::ok($items, array_merge($meta, [
            'pagination' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
            ],
        ]));
    }

    /**
     * @param  array<int|string,mixed>  $details  field errors or extra context
     */
    public static function fail(string $code, string $message, array $details = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => ['request_id' => self::requestId()],
        ], $status);
    }

    public static function requestId(): string
    {
        $req = request();
        $existing = $req->attributes->get('request_id') ?: $req->headers->get('X-Request-Id');
        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();
        $req->attributes->set('request_id', $id);

        return $id;
    }
}
