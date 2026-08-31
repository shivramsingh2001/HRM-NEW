<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $mainDomain = 'shurttech.com';

       

        // Handle API requests differently
        if ($request->is('api/*')) {
            return $this->handleApiRequest($request, $next);
        }

        // Local development: no *.shurttech.com host available.
        if ($this->isLocalHost($host)) {
            return $this->handleLocalRequest($request, $next);
        }

        // Handle web requests with subdomain
        return $this->handleWebRequest($request, $next, $host, $mainDomain);
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
            ?? env('LOCAL_TENANT_ID');

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
        if ($this->isTenantSelectableRoute($request)) {
            $identifier = $request->header('X-Tenant')
                ?? $request->header('X-Tenant-ID')
                ?? $request->input('tenant_id');

            if ($identifier) {
                $tenant = Tenant::where('status', 'active')
                    ->where(function ($q) use ($identifier) {
                        $q->where('subdomain', $identifier)
                          ->orWhere('id', $identifier);
                    })
                    ->first();

                if ($tenant) {
                    $this->setTenantContext($tenant, $request);
                }
            }
        }

        return $next($request);
    }

    /**
     * Is this one of the pre-auth routes allowed to select a tenant by header/body?
     */
    private function isTenantSelectableRoute($request): bool
    {
        return $request->is(...self::TENANT_SELECTABLE_ROUTES);
    }

    /**
     * Handle web requests with subdomain
     */
    private function handleWebRequest($request, $next, $host, $mainDomain)
    {
        

        // If accessing main domain (no subdomain)
        if ($host === $mainDomain || $host === "www.$mainDomain") {
            Log::info('🏢 Main domain access - no tenant');
            return $next($request);
        }

        // Extract subdomain safely
        if (!str_ends_with($host, $mainDomain)) {
            Log::warning('🏢 Invalid domain', ['host' => $host]);
            abort(404, 'Invalid domain');
        }

        $subdomain = str_replace('.' . $mainDomain, '', $host);

        // Skip special subdomains
        if (in_array($subdomain, ['crm', 'api', 'www', 'mail', 'admin'])) {
            Log::info('🏢 Special subdomain - no tenant', ['subdomain' => $subdomain]);
            return $next($request);
        }

        // Find tenant by subdomain
        $tenant = Tenant::where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();

        Log::info('🏢 Tenant lookup result', [
            'subdomain' => $subdomain,
            'found' => $tenant ? 'YES' : 'NO',
            'tenant_id' => $tenant->id ?? null,
            'tenant_name' => $tenant->company_name ?? null
        ]);

        if (!$tenant) {
            Log::warning('🏢 Tenant not found or inactive', ['subdomain' => $subdomain]);
            abort(404, 'Company not found or inactive');
        }

        // Store tenant globally
        $this->setTenantContext($tenant, $request);

        return $next($request);
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

    }
}