<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'singleLogin' => \App\Http\Middleware\CheckSingleDeviceLogin::class,
             'role' => \App\Http\Middleware\RoleMiddleware::class,
            'redirect.role' => \App\Http\Middleware\RedirectBasedOnRole::class,
            'verify.fingerprint' => \App\Http\Middleware\VerifyFingerprintAuthToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
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
