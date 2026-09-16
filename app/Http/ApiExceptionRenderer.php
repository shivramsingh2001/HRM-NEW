<?php

namespace App\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Tier 2 / T2-D — maps exceptions thrown under /api/v1/* to the ApiResponse
 * error taxonomy. Wired in bootstrap/app.php; only fires when the request path
 * is api/v1/*, so legacy endpoints keep their own error shapes.
 */
class ApiExceptionRenderer
{
    public static function handles($request): bool
    {
        return $request->is('api/v1', 'api/v1/*');
    }

    public static function render(\Throwable $e, $request): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::fail(
                'validation_failed',
                'The given data was invalid.',
                $e->errors(),
                422,
            ),
            $e instanceof AuthenticationException => ApiResponse::fail(
                'unauthenticated', 'Authentication is required.', [], 401,
            ),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::fail(
                'forbidden', 'You do not have access to this resource.', [], 403,
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::fail(
                'not_found', 'Resource not found.', [], 404,
            ),
            $e instanceof TooManyRequestsHttpException => ApiResponse::fail(
                'rate_limited', 'Too many requests.', [], 429,
            ),
            $e instanceof \App\Exceptions\ApiException => ApiResponse::fail(
                $e->errorCode, $e->getMessage(), $e->details, $e->status,
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::fail(
                'http_error', $e->getMessage() ?: 'Request failed.', [], $e->getStatusCode(),
            ),
            default => ApiResponse::fail(
                'server_error',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                [],
                500,
            ),
        };
    }
}
