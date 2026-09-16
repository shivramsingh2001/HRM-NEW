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

];
