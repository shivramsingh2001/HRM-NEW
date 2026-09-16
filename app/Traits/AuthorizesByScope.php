<?php

namespace App\Traits;

use App\Models\User;
use App\Models\UserJobDetail;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Extracts the "own / team / all-company" query-filtering pattern that was
 * previously hand-duplicated per controller (e.g. a manager branch doing
 * ->where('reporting_head', $authUser->id) right next to its role check) so
 * every module applies it the same way, driven by the user's actual
 * role_permissions.scope grant instead of a hardcoded role check.
 *
 * Usage in a controller:
 *   $query = Leave::query();
 *   $query = $this->applyScope($query, $query->getModel()->getTable().'.user_id', $authUser, 'leave', 'view');
 *   // null scope (no permission at all) -> throws 403 via abort()
 */
trait AuthorizesByScope
{
    /**
     * @param  Builder  $query
     * @param  string  $ownerColumn  fully-qualified or bare column holding the record owner's user_id
     * @param  User  $user
     * @param  string  $module
     * @param  string  $action
     * @param  string|null  $teamOwnerColumn  defaults to $ownerColumn — pass a
     *         different column when the "team" scope should filter by a
     *         different relation than the "own" scope does (rare).
     */
    protected function applyScope(
        Builder $query,
        string $ownerColumn,
        User $user,
        string $module,
        string $action,
        ?string $teamOwnerColumn = null
    ): Builder {
        $rbac = app(RbacService::class);
        $scope = $rbac->scopeFor($user, $module, $action);

        if ($scope === null) {
            abort(403, 'You do not have permission for this action.');
        }

        if ($scope === 'company') {
            return $query;
        }

        if ($scope === 'own') {
            return $query->where($ownerColumn, $user->id);
        }

        // scope === 'team': the user's own record plus everyone whose
        // reporting_head is this user.
        $teamUserIds = UserJobDetail::where('reporting_head', $user->id)
            ->pluck('user_id')
            ->push($user->id);

        return $query->whereIn($teamOwnerColumn ?? $ownerColumn, $teamUserIds);
    }

    /**
     * Non-query variant: for a single already-loaded record, checks whether
     * the user's scope for module:action covers that record's owner.
     */
    protected function scopeCoversOwner(User $user, string $module, string $action, int $ownerUserId): bool
    {
        $rbac = app(RbacService::class);
        $scope = $rbac->scopeFor($user, $module, $action);

        if ($scope === null) {
            return false;
        }
        if ($scope === 'company') {
            return true;
        }
        if ($scope === 'own') {
            return $ownerUserId === $user->id;
        }

        // team
        if ($ownerUserId === $user->id) {
            return true;
        }

        return UserJobDetail::where('user_id', $ownerUserId)
            ->where('reporting_head', $user->id)
            ->exists();
    }
}
