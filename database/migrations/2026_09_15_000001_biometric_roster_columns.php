<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roster auto-provisioning: HRM becomes the source of truth for who exists on a
 * terminal. The bridge creates/updates/removes device users from
 * biometric_enrollments instead of an admin hand-mapping enroll numbers.
 *
 * Existing rows default to source='manual', sync_state='synced' so current
 * hand-made mappings keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'auto_provision')) {
                $table->boolean('auto_provision')->default(true)->after('direction_mode');
            }
            if (! Schema::hasColumn('biometric_devices', 'provision_scope')) {
                // tenant = every active employee | branch = only those on this device's branch_id
                $table->string('provision_scope', 12)->default('tenant')->after('auto_provision');
            }
            if (! Schema::hasColumn('biometric_devices', 'default_privilege')) {
                $table->unsignedTinyInteger('default_privilege')->default(0)->after('provision_scope');
            }
        });

        Schema::table('biometric_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_enrollments', 'device_user_id')) {
                // The integer actually written to the terminal (= users.id).
                $table->unsignedBigInteger('device_user_id')->nullable()->after('enroll_no');
            }
            if (! Schema::hasColumn('biometric_enrollments', 'sync_state')) {
                // pending = needs create/update | synced | failed | removing = needs delete
                $table->string('sync_state', 12)->default('synced')->after('user_id');
            }
            if (! Schema::hasColumn('biometric_enrollments', 'name_pushed')) {
                $table->string('name_pushed', 64)->nullable()->after('name_on_device');
            }
            if (! Schema::hasColumn('biometric_enrollments', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('name_pushed');
            }
            if (! Schema::hasColumn('biometric_enrollments', 'last_error')) {
                $table->string('last_error', 300)->nullable()->after('synced_at');
            }
            if (! Schema::hasColumn('biometric_enrollments', 'source')) {
                // auto = roster-managed (safe to delete) | manual = legacy hand-map (never auto-removed)
                $table->string('source', 12)->default('manual')->after('last_error');
            }
        });

        // Index for the bridge roster query (pending/removing per device).
        Schema::table('biometric_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('biometric_enrollments', 'sync_state')) {
                $table->index(['biometric_device_id', 'sync_state'], 'bio_enr_device_sync_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_enrollments', function (Blueprint $table) {
            $table->dropIndex('bio_enr_device_sync_idx');
            $table->dropColumn(['device_user_id', 'sync_state', 'name_pushed', 'synced_at', 'last_error', 'source']);
        });
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn(['auto_provision', 'provision_scope', 'default_privilege']);
        });
    }
};
