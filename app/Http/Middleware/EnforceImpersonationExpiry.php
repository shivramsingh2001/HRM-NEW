<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard-expires an impersonation session after its expires_at, regardless of
 * activity (Super Admin Panel Phase 6.3). Runs on every authenticated request.
 */
class EnforceImpersonationExpiry
{
    public function handle(Request $request, Closure $next): Response
    {
        $imp = $request->session()->get('impersonation');

        $stale = false;
        if ($imp) {
            // Session copy of the hard cap (fast path).
            if (isset($imp['expires_at']) && Carbon::parse($imp['expires_at'])->isPast()) {
                $stale = true;
            } else {
                // Authoritative check — catches a panel force-end and is the real
                // 60-minute cap. One query, only while impersonating.
                $row = DB::table('impersonation_sessions')->where('id', $imp['session_id'] ?? 0)
                    ->first(['ended_at', 'expires_at']);
                if (! $row || $row->ended_at !== null
                    || ($row->expires_at && Carbon::parse($row->expires_at, 'UTC')->isPast())) {
                    $stale = true;
                }
            }
        }

        if ($stale) {
            DB::table('impersonation_sessions')
                ->where('id', $imp['session_id'] ?? 0)
                ->whereNull('ended_at')
                ->update(['ended_at' => now(), 'end_reason' => 'expired']);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $panel = rtrim(config('services.superadmin.panel_url', ''), '/');
            $to = $panel ? $panel . '/impersonation' : route('login');

            return $request->expectsJson()
                ? response()->json(['error' => 'impersonation_expired'], 440)
                : redirect()->away($to);
        }

        return $next($request);
    }
}
