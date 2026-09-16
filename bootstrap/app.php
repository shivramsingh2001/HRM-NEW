<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Tier 2 / T2-D — public API, separate from the mobile /api/user/* surface.
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function ($middleware) {
        // Machine-to-machine endpoints authenticated by a shared-secret header.
        $middleware->validateCsrfTokens(except: [
            'internal/superadmin/*',
        ]);

        // Hard-expire impersonation sessions on every web request.
        $middleware->web(append: [
            \App\Http\Middleware\EnforceImpersonationExpiry::class,
        ]);
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'singleLogin' => \App\Http\Middleware\CheckSingleDeviceLogin::class,
             'role' => \App\Http\Middleware\RoleMiddleware::class,
            'redirect.role' => \App\Http\Middleware\RedirectBasedOnRole::class,
            'shifts.custom' => \App\Http\Middleware\EnsureCustomShiftsEnabled::class,
            'field.tracking' => \App\Http\Middleware\EnsureFieldTrackingEnabled::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            // Tier 2 / public API
            'apiv1' => \App\Http\Middleware\ApiV1::class,
            'apikey' => \App\Http\Middleware\ResolveApiClient::class,
            'idempotency' => \App\Http\Middleware\Idempotency::class,
            'scope' => \App\Http\Middleware\EnsureApiScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tier 2 / T2-D — public API error taxonomy (only for api/v1/*).
        $exceptions->render(function (\Throwable $e, $request) {
            if (\App\Http\ApiExceptionRenderer::handles($request)) {
                return \App\Http\ApiExceptionRenderer::render($e, $request);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect('/')->withErrors(['You must be logged in to access this page.']);
        });

        // Map JWT token errors (missing / invalid / expired / blacklisted) to the
        // same 401 payload Sanctum used to return, so API responses stay identical.
        $exceptions->render(function (\PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect('/')->withErrors(['You must be logged in to access this page.']);
        });
    })->create();
