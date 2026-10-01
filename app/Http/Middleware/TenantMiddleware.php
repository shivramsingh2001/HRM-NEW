<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // Handle API requests differently
        if ($request->is('api/*')) {
            return $this->handleApiRequest($request, $next);
        }

        // Public careers pages name their company in the URL (careers/{company}).
        if ($request->route('company') !== null) {
            return $this->handleCareersRequest($request, $next);
        }

        // Local development: no *.shurttech.com host available.
        if ($this->isLocalHost($host)) {
            return $this->handleLocalRequest($request, $next);
        }

        // Single login domain for every company
        return $this->handleWebRequest($request, $next, $host);
    }

    /**
     * careers/{company}/... — pre-login, company-specific. {company} is the
     * tenant's code (tenants.subdomain). The parameter is removed from the
     * route afterwards so controller signatures stay unchanged.
     */
    private function handleCareersRequest(Request $request, Closure $next)
    {
        $code = (string) $request->route('company');

        $tenant = Tenant::where('subdomain', $code)->where('status', 'active')->first();
        if (!$tenant) {
            abort(404, 'Company not found or inactive');
        }

        $this->setTenantContext($tenant, $request);
        $request->route()->forgetParameter('company');

        return $next($request);
    }

    /**
     * localhost / 127.0.0.1 / *.localhost / *.test / bare IPs — i.e. any host
     * that can't carry a real tenant subdomain.
     */
    private function isLocalHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test')
            || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Resolve a tenant for a local-dev request. Tries, in order: the
     * authenticated user, ?tenant=, the X-Tenant header, the session, the
     * LOCAL_TENANT_ID env var, then the first active tenant. Never 404s.
     */
    private function handleLocalRequest($request, $next)
    {
        $identifier = $request->query('tenant')
            ?? $request->header('X-Tenant')
            ?? $request->session()->get('tenant_id')
            ?? config('tenancy.local_tenant_id');

        $tenant = null;

        if ($request->user()) {
            $tenant = Tenant::find($request->user()->tenant_id);
        }

        if (!$tenant && $identifier) {
            $tenant = Tenant::where('status', 'active')
                ->where(function ($q) use ($identifier) {
                    $q->where('id', $identifier)->orWhere('subdomain', $identifier);
                })
                ->first();
        }

        if (!$tenant) {
            $tenant = Tenant::where('status', 'active')->orderBy('id')->first();
        }

        if ($tenant) {
            $this->setTenantContext($tenant, $request);
        }

        return $next($request);
    }

    /**
     * Routes that are hit BEFORE authentication and legitimately need the caller
     * to name their tenant (login / password reset / OTP must disambiguate
     * employee_id / email / contact, which are only unique per tenant).
     */
    private const TENANT_SELECTABLE_ROUTES = [
        'api/login',
        'api/forgot-password',
        'api/reset-password',
        'api/send-otp',
        'api/login-otp',
    ];

    /**
     * Handle API requests.
     *
     * For authenticated requests the tenant is taken *only* from the JWT user —
     * never from a client-supplied header or body param. JWT is stateless so the
     * `api` guard can resolve the user here even though this middleware runs
     * before the `auth:api` middleware.
     */
    private function handleApiRequest($request, $next)
    {
        $user = null;

        try {
            $user = auth('api')->user();
        } catch (\Throwable $e) {
            // Malformed / expired / blacklisted token — leave $user null and let
            // the downstream auth:api middleware return the 401.
            $user = null;
        }

        if ($user) {
            $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

            if ($tenant && $tenant->status === 'active') {
                $this->setTenantContext($tenant, $request);
            } else {
                Log::warning('🏢 Authenticated API user has no active tenant', [
                    'user_id' => $user->id ?? null,
                    'tenant_id' => $user->tenant_id ?? null,
                ]);
            }

            return $next($request);
        }

        // Unauthenticated: only the pre-auth allowlist may name its tenant.
        // Tenant resolution is REQUIRED here, not best-effort — these routes
        // look users up by employee_id/email/contact, none of which are
        // guaranteed unique across tenants. Previously, a request with no
        // (or an unresolvable) tenant identifier fell through to $next()
        // anyway, leaving the downstream User::where(...) lookup completely
        // unscoped by TenantTrait's global scope (which only filters when
        // app()->bound('current_tenant') — never bound here) — i.e. able to
        // match a same-employee_id/email/contact row in ANY tenant. Failing
        // closed here removes that gap at its single root cause instead of
        // relying on every controller to remember to check it individually.
        if ($this->isTenantSelectableRoute($request)) {
            $identifier = $request->header('X-Tenant')
                ?? $request->header('X-Tenant-ID')
                ?? $request->input('tenant_id');

            // Common app for every company: when no company is named, take it
            // from the account itself. employee_id, contact and email each have
            // a global UNIQUE key on users, so the match is unambiguous.
            // An unknown identifier falls through untenanted so the controller
            // returns its normal "not found" reply (no extra enumeration signal).
            if (!$identifier && ($lookup = $this->accountLookupFor($request))) {
                [$field, $column] = $lookup;
                $account = User::withoutGlobalScope('tenant')
                    ->where($column, $request->input($field))
                    ->first(['id', 'tenant_id']);

                if (!$account) {
                    return $next($request);
                }

                $identifier = $account->tenant_id; // null → rejected below as before
            }

            if (!$identifier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing tenant identifier. Include an X-Tenant header with your request.',
                ], 400);
            }

            $tenant = Tenant::where('status', 'active')
                ->where(function ($q) use ($identifier) {
                    $q->where('subdomain', $identifier)
                      ->orWhere('id', $identifier);
                })
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found or inactive.',
                ], 400);
            }

            $this->setTenantContext($tenant, $request);
        }

        return $next($request);
    }

    /**
     * Pre-auth route => [request field, users column] used to find the
     * caller's company when the app sends no X-Tenant.
     */
    private const ACCOUNT_LOOKUP = [
        'api/login' => ['employee_id', 'employee_id'],
        'api/send-otp' => ['mobile_no', 'contact'],
        'api/login-otp' => ['mobile_no', 'contact'],
        'api/forgot-password' => ['email', 'email'],
        'api/reset-password' => ['email', 'email'],
    ];

    /** @return array{0:string,1:string}|null */
    private function accountLookupFor($request): ?array
    {
        foreach (self::ACCOUNT_LOOKUP as $route => [$field, $column]) {
            if ($request->is($route) && $request->filled($field)) {
                return [$field, $column];
            }
        }

        return null;
    }

    /**
     * Is this one of the pre-auth routes allowed to select a tenant by header/body?
     */
    private function isTenantSelectableRoute($request): bool
    {
        return $request->is(...self::TENANT_SELECTABLE_ROUTES);
    }

    /**
     * Web requests: every company uses the same host (APP_URL). The tenant is
     * the logged-in user's own tenant; guests (login, forgot/reset password,
     * impersonation handoff) get no tenant. Old company subdomains are
     * redirected to the single host.
     */
    private function handleWebRequest($request, $next, $host)
    {
        if ($redirect = $this->redirectCompanySubdomain($request, $host)) {
            return $redirect;
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

        if (!$tenant || !in_array($tenant->status, ['active', 'trial'], true)) {
            Log::warning('🏢 Logged-in user has no active tenant', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['error' => 'This workspace is currently unavailable. Please contact support.']);
        }

        $this->setTenantContext($tenant, $request);

        return $next($request);
    }

    /**
     * <code>.base_domain (e.g. demo.shurttech.com) → same path on the single
     * login host. Null when the host isn't an old company subdomain.
     */
    private function redirectCompanySubdomain($request, string $host)
    {
        $host = strtolower($host);
        $base = strtolower((string) config('tenancy.base_domain'));
        $appHost = strtolower((string) config('tenancy.app_host'));

        if (!config('tenancy.redirect_company_subdomains') || !$base || !$appHost || $host === $appHost) {
            return null;
        }

        if (!str_ends_with($host, '.' . $base)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.' . $base));
        if (in_array($subdomain, config('tenancy.reserved_subdomains', []), true)) {
            return null;
        }

        return redirect()->away(rtrim((string) config('app.url'), '/') . $request->getRequestUri(), 301);
    }

    /**
     * Set tenant context in various places
     */
    private function setTenantContext($tenant, $request)
    {
        // Store in session
        session([
            'tenant_id' => $tenant->id,
            'tenant' => $tenant
        ]);

        // Share with views
        view()->share('currentTenant', $tenant);

        // Store in app container
        app()->instance('current_tenant', $tenant);
        
        // Also store in request for easy access
        $request->attributes->set('tenant', $tenant);
        $request->merge(['tenant_id' => $tenant->id]);

        // route('public.jobs.*') fills careers/{company} from the bound tenant
        if ($tenant->subdomain) {
            URL::defaults(['company' => $tenant->subdomain]);
        }

    }
}