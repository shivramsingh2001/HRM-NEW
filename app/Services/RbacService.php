<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tenant RBAC resolver (Super Admin Panel Phase 5.4). Reads roles /
 * role_permissions from the shared DB — the same rows the Super Admin Panel
 * edits. Cache is invalidated cross-app via
 * Internal\FeatureCacheController::bust() -> forgetRole(), called by the
 * Super Admin Panel's PlatformClient::bustRole() whenever a role's
 * permissions are saved.
 *
 * `admin` keeps a hardcoded god-mode bypass — deliberate, not a gap: an
 * account holder who can already manage tenant settings/billing/users
 * shouldn't be lockable out of any screen by a misconfigured permission row.
 */
class RbacService
{
    /** Actions where being granted `manage` also satisfies the check. */
    private const MANAGE_IMPLIES = ['view', 'create', 'edit', 'delete'];

    public function can(User $user, string $module, string $action, ?string $requiredScope = null): bool
    {
        if (($user->role ?? null) === 'admin') {
            return true; // god-mode retained — see class docblock
        }

        $grantedScope = $this->scopeFor($user, $module, $action);
        if ($grantedScope === null) {
            return false;
        }

        if ($requiredScope === null) {
            return true;
        }

        // A broader grant satisfies a narrower requirement: company covers
        // team and own; team covers own. Never the other way around.
        $rank = ['own' => 1, 'team' => 2, 'company' => 3];

        return ($rank[$grantedScope] ?? 0) >= ($rank[$requiredScope] ?? 0);
    }

    /**
     * The broadest scope the user's role grants for module:action, checking
     * `manage` as a fallback when the specific action isn't directly
     * granted (manage implies view/create/edit/delete — not approve/export,
     * which are deliberately separate grants even under "full control").
     * Returns null if the user has no grant for this module:action at all.
     */
    public function scopeFor(User $user, string $module, string $action): ?string
    {
        if (($user->role ?? null) === 'admin') {
            return 'company';
        }

        $matrix = $this->permissionMatrix($user);
        $moduleGrants = $matrix[$module] ?? [];

        if (isset($moduleGrants[$action])) {
            return $moduleGrants[$action];
        }

        if (in_array($action, self::MANAGE_IMPLIES, true) && isset($moduleGrants['manage'])) {
            return $moduleGrants['manage'];
        }

        return null;
    }

    /**
     * @return array<string,string[]> flat "module:action" list — kept for
     * any existing caller expecting the old shape (e.g. debugging/admin
     * views listing "everything this role can do").
     */
    public function permissions(User $user): array
    {
        $matrix = $this->permissionMatrix($user);
        $flat = [];
        foreach ($matrix as $module => $actions) {
            foreach (array_keys($actions) as $action) {
                $flat[] = "{$module}:{$action}";
            }
        }

        return $flat;
    }

    /**
     * Full effective matrix for a user: every config('rbac.modules') module and
     * every action, resolved through scopeFor() (so admin god-mode and
     * manage-implies-view/create/edit/delete apply). null = not allowed.
     * Used by the mobile app to hide buttons the user can't use.
     *
     * @return array<string,array<string,?string>> module => [action => own|team|company|null]
     */
    public function effectiveMatrix(User $user): array
    {
        $out = [];
        foreach ((array) config('rbac.modules', []) as $module) {
            foreach ((array) config('rbac.actions', []) as $action) {
                $out[$module][$action] = $this->scopeFor($user, $module, $action);
            }
        }

        return $out;
    }

    /**
     * @return array<string,array<string,string>> module => [action => scope]
     */
    private function permissionMatrix(User $user): array
    {
        $roleId = $this->resolveRoleId($user);
        if (! $roleId) {
            return [];
        }

        return Cache::remember("rbac:role:{$roleId}", 600, function () use ($roleId) {
            $matrix = [];
            foreach (DB::table('role_permissions')->where('role_id', $roleId)->get() as $p) {
                $matrix[$p->module][$p->action] = $p->scope ?? 'company';
            }

            return $matrix;
        });
    }

    /**
     * Resolves the user's role_id, defensively re-checked against their own
     * tenant_id — role_id is normally kept correct by UserRoleObserver, but
     * this closes the gap explicitly rather than trusting that chain alone
     * (a role_id that somehow pointed at another tenant's role would
     * otherwise silently grant that tenant's permission matrix).
     */
    private function resolveRoleId(User $user): ?int
    {
        if (! $user->tenant_id) {
            return null;
        }

        if ($user->role_id) {
            $belongsToTenant = DB::table('roles')
                ->where('id', $user->role_id)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            return $belongsToTenant ? $user->role_id : null;
        }

        if ($user->role) {
            return DB::table('roles')
                ->where('tenant_id', $user->tenant_id)
                ->where('slug', $user->role)
                ->value('id');
        }

        return null;
    }

    public function forgetRole(int $roleId): void
    {
        Cache::forget("rbac:role:{$roleId}");
    }

    /** Resolve for the currently authenticated user (view helper / directive). */
    public function currentCan(string $module, string $action, ?string $requiredScope = null): bool
    {
        $user = auth()->user();

        return $user ? $this->can($user, $module, $action, $requiredScope) : false;
    }

    /** Scope helper for the currently authenticated user. */
    public function currentScopeFor(string $module, string $action): ?string
    {
        $user = auth()->user();

        return $user ? $this->scopeFor($user, $module, $action) : null;
    }
}
