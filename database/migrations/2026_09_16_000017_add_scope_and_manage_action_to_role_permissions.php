<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC granularity: adds a `scope` column (own/team/company) so a grant can
 * express "approve leave for your team", not just "can approve leave", and
 * adds `manage` as a 7th action (verified safe on a scratch table before
 * writing this: appending a new enum value via MODIFY does not renumber or
 * reinterpret existing rows — that landmine only applies when altering or
 * reordering EXISTING enum values, not when appending a new one at the end).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('role_permissions')) {
            return;
        }

        if (!Schema::hasColumn('role_permissions', 'scope')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->enum('scope', ['own', 'team', 'company'])->default('company')->after('action');
            });
        }

        $type = DB::selectOne(
            "SELECT COLUMN_TYPE as col_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_permissions' AND COLUMN_NAME = 'action'"
        );
        if ($type && !str_contains($type->col_type, "'manage'")) {
            DB::statement("ALTER TABLE role_permissions MODIFY action ENUM('view','create','edit','delete','approve','export','manage') NOT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('role_permissions')) {
            return;
        }

        if (Schema::hasColumn('role_permissions', 'scope')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->dropColumn('scope');
            });
        }

        // Not reverting the action enum — removing 'manage' would destroy
        // any rows that use it, which down() must never do silently.
    }
};
