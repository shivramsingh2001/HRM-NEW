<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves whether a feature module is enabled for a tenant. Mirrors the Super
 * Admin Panel's App\Services\FeatureService (kept as a copy — see
 * hrm-superadmin/docs Phase 4.4) and reads the same shared tables:
 *   tenant override  ->  active subscription snapshot  ->  config default  ->  false
 * Deprecated registry keys resolve disabled, following replaced_by once.
 *
 * The panel POSTs /internal/superadmin/feature-cache/bust after any override or
 * subscription change so this stays fresh; a 5-minute TTL is the safety net.
 */
class FeatureService
{
    private const TTL = 300;

    public function enabled(int $tenantId, string $key): bool
    {
        return (bool) Cache::remember(
            "feat:{$tenantId}:{$key}",
            self::TTL,
            fn () => $this->resolve($tenantId, $key, 0)
        );
    }

    /** Feature for the tenant bound by TenantMiddleware (app('current_tenant')). */
    public function enabledForCurrentTenant(string $key): bool
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $tenant) {
            return true; // no tenant context (console, public routes) — don't gate
        }

        return $this->enabled((int) $tenant->id, $key);
    }

    /**
     * Every feature key with its on/off state for a tenant — what the mobile app
     * reads after login to show only the modules the company's plan includes.
     *
     * @return array<string,bool>
     */
    public function allForTenant(int $tenantId): array
    {
        $out = [];
        foreach ($this->allKeys() as $key) {
            $out[$key] = $this->enabled($tenantId, $key);
        }

        return $out;
    }

    public function bust(int $tenantId): void
    {
        foreach ($this->allKeys() as $key) {
            Cache::forget("feat:{$tenantId}:{$key}");
        }
    }

    // ------------------------------------------------------------------

    private function resolve(int $tenantId, string $key, int $depth): bool
    {
        if ($depth > 3) {
            return false;
        }

        $reg = DB::table('feature_registry')->where('key', $key)->first();
        if ($reg && ($reg->deprecated_at !== null || ! $reg->is_active)) {
            return $reg->replaced_by ? $this->resolve($tenantId, $reg->replaced_by, $depth + 1) : false;
        }

        $override = DB::table('tenant_feature_overrides')
            ->where('tenant_id', $tenantId)->where('feature_key', $key)->first();
        if ($override) {
            return (bool) $override->is_enabled;
        }

        $sub = DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trial'])
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
            ->orderByDesc('start_date')->orderByDesc('id')
            ->first();
        if ($sub) {
            $snapshot = json_decode($sub->features_snapshot ?? '[]', true);
            if (is_string($snapshot)) {
                $snapshot = json_decode($snapshot, true);
            }
            if (is_array($snapshot) && array_key_exists($key, $snapshot)) {
                return (bool) $snapshot[$key];
            }
        }

        return (bool) config("features.{$key}.default", false);
    }

    /** @return string[] */
    private function allKeys(): array
    {
        $cfg = array_keys((array) config('features', []));
        if ($cfg) {
            return $cfg;
        }

        return DB::table('feature_registry')->pluck('key')->all();
    }
}
