<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant "custom shifts" toggle.
 *
 *   custom_shifts_enabled = 0 (default) -> the whole company runs on ONE fixed
 *                                          shift (the shifts row pointed at by
 *                                          default_shift_id); per-employee /
 *                                          per-date assignment is ignored.
 *   custom_shifts_enabled = 1           -> the existing shifts + user_shifts
 *                                          assign-shift feature is used.
 *
 *   default_shift_id     -> shifts.id used when custom_shifts_enabled = 0
 *   default_weekoff_days -> JSON array of English weekday names, e.g. ["Sunday"],
 *                           the company-wide weekly offs for a fixed-shift tenant
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'custom_shifts_enabled')) {
                $table->boolean('custom_shifts_enabled')->default(false)->after('monthly_late_allowance');
            }
            if (!Schema::hasColumn('tenants', 'default_shift_id')) {
                $table->unsignedBigInteger('default_shift_id')->nullable()->after('custom_shifts_enabled');
            }
            if (!Schema::hasColumn('tenants', 'default_weekoff_days')) {
                $table->json('default_weekoff_days')->nullable()->after('default_shift_id');
            }
        });

        // Any tenant that already has shift rows is clearly using the assign-shift
        // feature — keep it on so production behaviour does not change under them.
        if (Schema::hasTable('shifts')) {
            DB::statement(
                'UPDATE tenants SET custom_shifts_enabled = 1
                 WHERE id IN (SELECT DISTINCT tenant_id FROM shifts WHERE tenant_id IS NOT NULL)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['default_weekoff_days', 'default_shift_id', 'custom_shifts_enabled'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
