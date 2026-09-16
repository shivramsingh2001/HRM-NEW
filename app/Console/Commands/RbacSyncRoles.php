<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Makes RBAC data consistent for every tenant:
 *   1. ensures the 4 system roles exist in `roles` (+ their default permission
 *      matrix in `role_permissions`, including scope),
 *   2. sets each user's `role_id` from their varchar `role`.
 * Idempotent — safe to re-run. Re-running also UPDATES the scope of any
 * already-seeded row whose config default has since changed (e.g. adding
 * scope granularity after rows already existed as scope=company) — inserts
 * alone would never fix rows created before scope existed.
 * Pass --revoke-removed to additionally DELETE role_permissions rows for a
 * module:action a role's config entry no longer grants at all (e.g. a
 * module:action that used to be part of a role's spec and was removed) —
 * off by default since it's the one destructive mode; without it, stale
 * grants from an earlier, broader config just sit unused rather than being
 * cleaned up automatically on every sync.
 *
 * config('rbac.system_roles')[$slug][2] (the permissions map) accepts, per
 * module:
 *   'all'                                 — every configured action, scope=company
 *   ['view', 'edit', ...]                 — those actions, scope=company
 *   ['view' => 'own', 'approve' => 'team'] — per-action scope
 */
class RbacSyncRoles extends Command
{
    protected $signature = 'rbac:sync-roles {--tenant= : limit to one tenant_id} {--revoke-removed : also delete role_permissions rows no longer granted by config}';

    protected $description = 'Seed system roles per tenant and backfill users.role_id.';

    public function handle(): int
    {
        $tenantIds = DB::table('users')
            ->when($this->option('tenant'), fn ($q, $v) => $q->where('tenant_id', $v))
            ->whereNotNull('tenant_id')->distinct()->pluck('tenant_id');

        $actions = config('rbac.actions');
        $revokeRemoved = (bool) $this->option('revoke-removed');
        $rolesSeeded = 0;
        $usersLinked = 0;
        $permissionsInserted = 0;
        $permissionsUpdated = 0;
        $permissionsRevoked = 0;

        foreach ($tenantIds as $tid) {
            foreach (config('rbac.system_roles') as $slug => [$name, $isSystem, $perms]) {
                $roleId = DB::table('roles')->where('tenant_id', $tid)->where('slug', $slug)->value('id');
                if (! $roleId) {
                    $roleId = DB::table('roles')->insertGetId([
                        'tenant_id' => $tid, 'name' => $name, 'slug' => $slug,
                        'description' => "{$name} role", 'is_system' => $isSystem ? 1 : 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $rolesSeeded++;
                }

                foreach ($perms as $module => $spec) {
                    $configuredScopes = $this->resolveActionScopes($spec, $actions);

                    foreach ($configuredScopes as $action => $scope) {
                        $existing = DB::table('role_permissions')
                            ->where('role_id', $roleId)->where('module', $module)->where('action', $action)
                            ->first();

                        if (! $existing) {
                            DB::table('role_permissions')->insert([
                                'role_id' => $roleId, 'module' => $module, 'action' => $action, 'scope' => $scope,
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $permissionsInserted++;
                        } elseif ($existing->scope !== $scope) {
                            DB::table('role_permissions')->where('id', $existing->id)->update([
                                'scope' => $scope, 'updated_at' => now(),
                            ]);
                            $permissionsUpdated++;
                        }
                    }

                    if ($revokeRemoved && ! empty($configuredScopes)) {
                        $removed = DB::table('role_permissions')
                            ->where('role_id', $roleId)->where('module', $module)
                            ->whereNotIn('action', array_keys($configuredScopes))
                            ->delete();
                        $permissionsRevoked += $removed;
                    }
                }
            }

            // Backfill role_id from the varchar role.
            $roleMap = DB::table('roles')->where('tenant_id', $tid)->pluck('id', 'slug');
            foreach (DB::table('users')->where('tenant_id', $tid)->whereNull('role_id')->get(['id', 'role']) as $u) {
                $rid = $roleMap[$u->role] ?? null;
                if ($rid) {
                    DB::table('users')->where('id', $u->id)->update(['role_id' => $rid]);
                    $usersLinked++;
                }
            }
        }

        $this->info("Tenants: {$tenantIds->count()} · system roles seeded: {$rolesSeeded} · "
            . "permissions inserted: {$permissionsInserted} · permissions scope-updated: {$permissionsUpdated} · "
            . "permissions revoked: {$permissionsRevoked} · users linked: {$usersLinked}");

        return self::SUCCESS;
    }

    /**
     * @return array<string,string> action => scope
     */
    private function resolveActionScopes($spec, array $actions): array
    {
        if ($spec === 'all') {
            return array_fill_keys($actions, 'company');
        }

        $result = [];
        foreach ((array) $spec as $key => $value) {
            if (is_int($key)) {
                // Plain list entry, e.g. 'view' — scope defaults to company.
                if (in_array($value, $actions, true)) {
                    $result[$value] = 'company';
                }
            } else {
                // Associative entry, e.g. 'view' => 'own'.
                if (in_array($key, $actions, true) && in_array($value, config('rbac.scopes', ['own', 'team', 'company']), true)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
