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

        // Handle web requests with subdomain
        return $this->handleWebRequest($request, $next, $host, $mainDomain);
    }

    /**
     * Handle API requests - tenant comes from authenticated user or request header
     */
    private function handleApiRequest($request, $next)
    {
        // Method 1: From authenticated user (after login)
        if ($request->user()) {
            $tenantId = $request->user()->tenant_id;
            
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                
                if ($tenant && $tenant->status == 'active') {
                    $this->setTenantContext($tenant, $request);
                 
                    
                    return $next($request);
                }
            }
        }

        // Method 2: From request header (for login/register before authentication)
        if ($request->hasHeader('X-Tenant') || $request->hasHeader('X-Tenant-ID')) {
            $tenantIdentifier = $request->header('X-Tenant') ?? $request->header('X-Tenant-ID');
            
            // Try to find by subdomain or ID
            $tenant = Tenant::where('subdomain', $tenantIdentifier)
                ->orWhere('id', $tenantIdentifier)
                ->where('status', 'active')
                ->first();
            
            if ($tenant) {
                $this->setTenantContext($tenant, $request);
                
               
                return $next($request);
            }
        }

        // Method 3: From request payload (for login/register)
        if ($request->has('tenant_id')) {
            $tenant = Tenant::where('id', $request->tenant_id)
                ->where('status', 'active')
                ->first();
            
            if ($tenant) {
                $this->setTenantContext($tenant, $request);
              
                
                return $next($request);
            }
        }

       
        
        return $next($request);
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