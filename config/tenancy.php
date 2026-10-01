<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local-dev tenant fallback
    |--------------------------------------------------------------------------
    |
    | On localhost/*.test (no real *.shurttech.com subdomain available),
    | TenantMiddleware falls back to this tenant id/subdomain when no other
    | signal (authenticated user, ?tenant=, X-Tenant header, session) resolves
    | one. Irrelevant outside local development.
    |
    */
    'local_tenant_id' => env('LOCAL_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Single login domain
    |--------------------------------------------------------------------------
    |
    | Every company signs in on the same host (the host of APP_URL); the tenant
    | is identified from the logged-in user, not from a subdomain. Old company
    | subdomains (<code>.base_domain) are 301-redirected to that host.
    |
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'shurttech.com'),

    'app_host' => parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: null,

    'redirect_company_subdomains' => (bool) env('TENANCY_REDIRECT_SUBDOMAINS', true),

    // Subdomains of base_domain that are not companies (never redirected).
    'reserved_subdomains' => ['crm', 'api', 'www', 'mail', 'admin'],

];
