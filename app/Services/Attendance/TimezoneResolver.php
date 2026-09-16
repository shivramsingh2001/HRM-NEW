<?php

namespace App\Services\Attendance;

use Illuminate\Support\Facades\DB;

/**
 * Tier 1 / W4 — the zone an employee's punches should be recorded / rendered in.
 *
 * Resolution: users.timezone -> tenants.timezone -> config('app.timezone').
 * Cheap and memoised; safe to call from jobs and console commands.
 */
class TimezoneResolver
{
    /** @var array<string,string> */
    private array $memo = [];

    public function forUser(int $userId, ?int $tenantId = null): string
    {
        $key = $userId . '|' . ($tenantId ?? '');
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $userTz = DB::table('users')->where('id', $userId)->value('timezone');
        if ($userTz && $this->valid($userTz)) {
            return $this->memo[$key] = $userTz;
        }

        if (! $tenantId) {
            $tenantId = (int) DB::table('users')->where('id', $userId)->value('tenant_id');
        }

        return $this->memo[$key] = $this->forTenant($tenantId);
    }

    public function forTenant(?int $tenantId): string
    {
        $fallback = config('app.timezone') ?: 'UTC';
        if (! $tenantId) {
            return $fallback;
        }

        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone');

        return ($tz && $this->valid($tz)) ? $tz : $fallback;
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function valid(string $tz): bool
    {
        return in_array($tz, timezone_identifiers_list(), true);
    }
}
