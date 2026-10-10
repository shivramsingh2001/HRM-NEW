<?php

namespace App\Services;

use App\Models\MaintenanceMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Reads the platform maintenance row (written by the Super Admin Panel) and
 * decides whether a request may pass. The middleware reads through a short
 * cache; the panel busts it via /internal/superadmin/feature-cache/bust
 * (payload `maintenance: true`), so changes show up at once when the shared
 * token is set and within CACHE_SECONDS otherwise.
 */
class MaintenanceModeService
{
    public const CACHE_KEY = 'platform:maintenance_mode';

    public const CACHE_SECONDS = 30;

    /** The single row, created with the default text if it is missing. */
    public function row(): MaintenanceMode
    {
        return MaintenanceMode::query()->orderBy('id')->first()
            ?? MaintenanceMode::create([
                'is_enabled' => false,
                'title' => MaintenanceMode::DEFAULT_TITLE,
                'message' => MaintenanceMode::DEFAULT_MESSAGE,
                'allowed_ips' => [],
                'allowed_users' => [],
            ]);
    }

    /** Cached copy for the per-request middleware check (null = no row). */
    public function cached(): ?MaintenanceMode
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS,
                fn () => MaintenanceMode::query()->orderBy('id')->first());
        } catch (\Throwable $e) {
            // Table missing / DB hiccup must never take the whole app down.
            report($e);

            return null;
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Body of GET /api/maintenance. The shape is fixed — the mobile app's
     * older versions read it — so keys are listed explicitly.
     *
     * @return array{success: bool, data: array<string, mixed>, is_active: bool}
     */
    public function publicPayload(): array
    {
        $m = $this->row();

        return [
            'success' => true,
            'data' => [
                'id' => $m->id,
                'is_enabled' => (bool) $m->is_enabled,
                'title' => $m->title,
                'message' => $m->message,
                'start_time' => $m->start_time?->toJSON(),
                'end_time' => $m->end_time?->toJSON(),
                'allowed_ips' => array_values($m->allowed_ips ?? []),
                'allowed_users' => array_values($m->allowed_users ?? []),
                'enabled_by' => $m->enabled_by,
                'created_at' => $m->created_at?->toJSON(),
                'updated_at' => $m->updated_at?->toJSON(),
            ],
            'is_active' => $m->isActive(),
        ];
    }

    /** Allowed IPs (exact or CIDR) and allowed user ids bypass maintenance. */
    public function bypasses(MaintenanceMode $m, Request $request, string $guard): bool
    {
        $ips = array_values(array_filter(array_map('strval', $m->allowed_ips ?? [])));
        if ($ips && IpUtils::checkIp((string) $request->ip(), $ips)) {
            return true;
        }

        $users = array_map('intval', $m->allowed_users ?? []);
        if (! $users) {
            return false;
        }

        try {
            $id = Auth::guard($guard)->id();
        } catch (\Throwable $e) {
            // A bad / expired token just means "not an allowed user".
            return false;
        }

        return $id !== null && in_array((int) $id, $users, true);
    }
}
