<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform maintenance gate, appended to the `web` and `api` groups. While
 * maintenance is active every request gets a 503 (maintenance page for the
 * browser, JSON for the API) except:
 *  - allowed IPs / allowed users (set in the Super Admin Panel),
 *  - super admins impersonating a tenant user,
 *  - the paths below: the status API itself, login (so allowed users can sign
 *    in), the panel's machine-to-machine calls and biometric punch ingestion
 *    (terminals must not lose punches).
 */
class CheckMaintenanceMode
{
    private const EXEMPT_PATHS = [
        'api/maintenance',
        'api/login', 'api/send-otp', 'api/login-otp',
        'api/v1/biometric/*',
        '/', 'login', 'logout', 'impersonate/consume',
        'internal/superadmin/*',
    ];

    public function __construct(private MaintenanceModeService $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $m = $this->maintenance->cached();
        if ($m === null || ! $m->isActive() || $request->is(...self::EXEMPT_PATHS)) {
            return $next($request);
        }

        $isApi = $request->is('api/*');
        if (! $isApi && $request->hasSession() && $request->session()->has('impersonation')) {
            return $next($request);
        }
        if ($this->maintenance->bypasses($m, $request, $isApi ? 'api' : 'web')) {
            return $next($request);
        }

        $title = $m->title ?: \App\Models\MaintenanceMode::DEFAULT_TITLE;
        $message = $m->message ?: \App\Models\MaintenanceMode::DEFAULT_MESSAGE;
        $headers = $m->end_time ? ['Retry-After' => (string) max(60, (int) now()->diffInSeconds($m->end_time, false))] : [];

        if ($isApi || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'maintenance' => true,
                'title' => $title,
                'message' => $message,
                'end_time' => $m->end_time?->toJSON(),
            ], 503, $headers);
        }

        return response()->view('errors.maintenance', [
            'title' => $title,
            'message' => $message,
            'endTime' => $m->end_time,
        ], 503, $headers);
    }
}
