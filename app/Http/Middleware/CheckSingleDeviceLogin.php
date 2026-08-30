<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSingleDeviceLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Token sent from mobile must match the one stored in DB
        if ($request->header('Device-Token') !== $user->last_login_token) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is logged in on another device. Please login again.'
            ], 401);
        }
        return $next($request);
    }
}
