<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginAttemptService;
use App\Services\AuthAuditService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;// <-- ADD THIS LINE

class AuthController extends Controller
{
    public function __construct(
        protected LoginAttemptService $loginAttempts,
        protected AuthAuditService $audit
    ) {
    }

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

            // Platform-level gate: a tenant suspended / pending-deletion from the
            // Super Admin Panel cannot log any user in.
            $tenantRow = \App\Models\Tenant::find($tenantId);
            if ($tenantRow && !in_array($tenantRow->status, ['active', 'trial'], true)) {
                return back()
                    ->withErrors(['error' => 'This workspace is currently unavailable. Please contact support.'])
                    ->withInput();
            }

            // Real-time subscription-expiry gate — independent of the daily
            // auto-suspend cron (which waits out a grace period before flipping
            // tenant.status). New logins are blocked the instant the current
            // subscription's end date (or trial) has passed, even during that
            // grace window; already-open sessions are unaffected here.
            $activeSub = DB::table('tenant_subscriptions')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'trial'])
                ->orderByDesc('start_date')->orderByDesc('id')
                ->first();
            $expiredAt = $activeSub?->end_date ?? ($activeSub?->status === 'trial' ? $activeSub?->trial_ends_at : null);
            if ($expiredAt && \Illuminate\Support\Carbon::parse($expiredAt)->isPast()) {
                return back()
                    ->withErrors(['error' => 'Your subscription has ended. Please contact your admin to renew before logging in again.'])
                    ->withInput();
            }

            // Consecutive-failure lockout — scoped per tenant+employee_id
            // since employee_id alone isn't unique across tenants.
            $lockoutKey = 'web:' . $tenantId . ':' . $request->employee_id;
            if ($this->loginAttempts->isLockedOut($lockoutKey)) {
                return back()
                    ->withErrors(['error' => $this->loginAttempts->lockoutMessage($lockoutKey)])
                    ->withInput();
            }

            // Check if user exists first (optional but good for debugging)
            $user = User::withoutGlobalScope('tenant')
            ->where('employee_id', $request->employee_id)
            ->where('tenant_id', $tenantId)
            ->first();

            if (!$user) {
                $this->loginAttempts->recordFailure($lockoutKey);
                $this->audit->logLoginFailed($tenantId, $request->employee_id);

                return back()
                    ->withErrors(['error' => 'Employee ID does not exist in this company.'])
                    ->withInput();
            }

            if (!Hash::check($request->password, $user->password)) {
                $this->loginAttempts->recordFailure($lockoutKey);
                $this->audit->logLoginFailed($tenantId, $request->employee_id);

                return back()
                    ->withErrors(['error' => 'Password not matched.'])
                    ->withInput();
            }

            if ($user->status != 1) {
                return back()
                    ->withErrors(['error' => 'Your account is inactive. Please contact administrator.'])
                    ->withInput();
            }

            $this->loginAttempts->clear($lockoutKey);
            $this->audit->logLoginSuccess($user->id, $user->tenant_id);

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