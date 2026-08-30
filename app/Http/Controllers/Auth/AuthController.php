<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;// <-- ADD THIS LINE

class AuthController extends Controller
{
    public function index(Request $request)
    {
        return view('client.auth.login');
    }

    public function login(Request $request)
    {
        
        try {
            $request->validate([
                'employee_id' => 'required|string',
                'password' => 'required|string',
            ]);
    
            // Get tenant_id from session
            $tenantId = session('tenant_id');

            if (!$tenantId) {
                return back()
                    ->withErrors(['error' => 'Company not identified. Please use company subdomain.'])
                    ->withInput();
            }
    
            // Check if user exists first (optional but good for debugging)
            $user = User::withoutGlobalScope('tenant')
            ->where('employee_id', $request->employee_id)
            ->where('tenant_id', $tenantId)
            ->first();
            
            if (!$user) {
                return back()
                    ->withErrors(['error' => 'Employee ID does not exist in this company.'])
                    ->withInput();
            }
   
            if (!Hash::check($request->password, $user->password)) {
                return back()
                    ->withErrors(['error' => 'Password not matched.'])
                    ->withInput();
            }
  
            if ($user->status != 1) {
                return back()
                    ->withErrors(['error' => 'Your account is inactive. Please contact administrator.'])
                    ->withInput();
            }
  
            // All checks passed, log the user in manually
            Auth::login($user, $request->boolean('remember'));
            
            // Regenerate session to prevent session fixation
            $request->session()->regenerate();
             
            // Ensure tenant data is in session
            session(['tenant_id' => $user->tenant_id]);
            
            if ($user->tenant) {
                session(['tenant' => $user->tenant]);
            }
            
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Login Successfully.');
    
        } catch (Exception $e) {
           
            if (config('app.debug')) {
                throw $e;
            }
            
            return back()
                ->withErrors(['error' => 'An error occurred. Please try after some time.'])
                ->withInput();
        }
    }

    protected function credentials(Request $request)
    {
        return [
            'employee_id' => $request->employee_id,
            'password' => $request->password,
            'tenant_id' => session('tenant_id')
        ];
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        $tenantId = session('tenant_id');
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login')
            ->with('success', 'Logout successfully!');
    }
    
    public function dashboard(Request $request)
    {
        return view('client.dashboard.dashboard');
    }
    
    public function forgot(Request $request)
    {
        return view('client.auth.forgot-password');
    }
}