<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dual-write for the RBAC transition: keep users.role_id aligned with the
 * varchar `role` whenever it changes. Best-effort — never blocks a user save.
 */
class UserRoleObserver
{
    public function saving(User $user): void
    {
        try {
            if (! $user->isDirty('role') && $user->role_id) {
                return;
            }
            if (! $user->tenant_id || ! $user->role) {
                return;
            }
            $roleId = DB::table('roles')
                ->where('tenant_id', $user->tenant_id)
                ->where('slug', $user->role)
                ->value('id');
            if ($roleId) {
                $user->role_id = $roleId;
            }
        } catch (\Throwable $e) {
            Log::warning('UserRoleObserver: ' . $e->getMessage());
        }
    }
}
