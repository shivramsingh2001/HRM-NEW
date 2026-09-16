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
    public function __construct(private BiometricRosterService $roster)
    {
    }

    public function created(User $user): void
    {
        $this->sync($user);
    }

    public function updated(User $user): void
    {
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
