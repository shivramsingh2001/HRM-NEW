<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Employees raise their own overtime and travel/WFH requests, but the seeded
 * Employee role only had `view` on those modules — so the mobile app, which
 * shows "Apply" from the permissions matrix (GET /api/user/features), would
 * hide both. Adds `create` scope `own` to every system Employee role that
 * lacks it. Insert-only: never touches a grant a company has customised
 * (unlike `rbac:sync-roles`, which rewrites scopes to the config defaults).
 */
return new class extends Migration
{
    private const GRANTS = [['overtime', 'create'], ['requests', 'create']];

    public function up(): void
    {
        $roleIds = DB::table('roles')->where('slug', 'employee')->where('is_system', 1)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach (self::GRANTS as [$module, $action]) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)->where('module', $module)->where('action', $action)->exists();
                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId, 'module' => $module, 'action' => $action, 'scope' => 'own',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            Cache::forget("rbac:role:{$roleId}"); // RbacService caches each role's matrix for 10 min
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')->where('slug', 'employee')->where('is_system', 1)->pluck('id');

        foreach (self::GRANTS as [$module, $action]) {
            DB::table('role_permissions')->whereIn('role_id', $roleIds)
                ->where('module', $module)->where('action', $action)->where('scope', 'own')->delete();
        }
        foreach ($roleIds as $roleId) {
            Cache::forget("rbac:role:{$roleId}");
        }
    }
};
