<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogFingerprintCallback
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isJson() && !is_array($request->json()->all())) {
            Log::warning('Fingerprint callback: non-JSON body received', ['ip' => $request->ip()]);
        }

        if (!$request->has('RealTime')) {
            Log::warning('Fingerprint callback: missing RealTime key', [
                'ip'   => $request->ip(),
                'body' => $request->all(),
            ]);
            // Still let it through — controller returns {"status":"done"} regardless,
            // per Cams doc ("never block the Protocol Engine").
        }

        return $next($request);
    }
}