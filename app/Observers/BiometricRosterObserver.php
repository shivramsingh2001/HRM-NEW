<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Biometric\BiometricRosterService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps biometric terminals in step with employee lifecycle changes. All work is
 * best-effort: the scheduled `biometric:sync-roster` reconciles anything missed,
 * so a failure here must never block an employee create/update/delete.
 */
class BiometricRosterObserver
{
    private static bool $suppressed = false;

    public function __construct(private BiometricRosterService $roster)
    {
    }

    /**
     * Suspends this observer for the duration of $callback. Used by
     * BiometricEmployeeProvisioningService::createFromDeviceEnrollment(),
     * which creates the User before the triggering BiometricEnrollment row
     * has been linked to it — without this, the created() event below would
     * fire syncUser() while that link doesn't exist yet, and create a
     * second, biometrics-less enrollment row for the same person on the
     * same device. The caller links the row itself, then calls syncUser()
     * explicitly afterward so other devices in the tenant still pick the
     * new employee up normally.
     */
    public static function suppressed(\Closure $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;
        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    public function created(User $user): void
    {
        if (self::$suppressed) {
            return;
        }
        $this->sync($user);
    }

    public function updated(User $user): void
    {
        if (self::$suppressed) {
            return;
        }
        if ($user->wasChanged(['name', 'employee_id', 'status', 'tenant_id'])) {
            $this->sync($user);
        }
    }

    public function deleted(User $user): void
    {
        $this->sync($user);
    }

    private function sync(User $user): void
    {
        try {
            $this->roster->syncUser($user);
        } catch (\Throwable $e) {
            Log::warning('BiometricRosterObserver: sync failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }
}
