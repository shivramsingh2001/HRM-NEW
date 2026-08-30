<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        
        // If trying to access login page while already logged in
        if ($request->routeIs('login')) {
            return $this->redirectToDashboard($user);
        }

        return $next($request);
    }

    /**
     * Redirect user to their role-specific dashboard
     */
    private function redirectToDashboard($user)
    {
        switch ($user->role) {
            case 'admin':
                return redirect()->route('dashboard.admin');
            case 'hr':
                return redirect()->route('dashboard.hr');
            case 'manager':
                return redirect()->route('dashboard.manager');
            default:
                return redirect()->route('dashboard.employee');
        }
    }
}