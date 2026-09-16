<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed account lockout, layered underneath the route-level
 * `throttle:login`/`throttle:otp-verify` rate limiters. The rate limiters
 * cap request *volume* per identifier+ip; this tracks *consecutive
 * failures* per identifier alone, so an attacker can't dodge a lockout by
 * spreading guesses across many IPs (which a purely ip-keyed rate limit
 * would allow). Deliberately cache-based — no new table, self-expiring,
 * cheap to check on every request.
 */
class LoginAttemptService
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_MINUTES = 15;

    private function key(string $identifier): string
    {
        return 'login-lockout:' . strtolower($identifier);
    }

    /** True if this identifier has exceeded the failure threshold and is currently locked out. */
    public function isLockedOut(string $identifier): bool
    {
        return (int) Cache::get($this->key($identifier), 0) >= self::MAX_ATTEMPTS;
    }

    /** Minutes remaining until the lockout clears (0 if not locked out). */
    public function lockoutMinutesRemaining(string $identifier): int
    {
        $ttl = Cache::get($this->key($identifier) . ':ttl');
        if (!$ttl) {
            return 0;
        }

        return max(0, (int) ceil((strtotime($ttl) - time()) / 60));
    }

    public function recordFailure(string $identifier): void
    {
        $key = $this->key($identifier);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::DECAY_MINUTES));

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put($key . ':ttl', now()->addMinutes(self::DECAY_MINUTES)->toDateTimeString(), now()->addMinutes(self::DECAY_MINUTES));
        }
    }

    public function clear(string $identifier): void
    {
        $key = $this->key($identifier);
        Cache::forget($key);
        Cache::forget($key . ':ttl');
    }

    public function lockoutMessage(string $identifier): string
    {
        $minutes = $this->lockoutMinutesRemaining($identifier);

        return "Too many failed login attempts. Please try again in {$minutes} minute(s).";
    }
}
