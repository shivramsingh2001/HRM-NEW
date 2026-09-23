<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a discrete deduction "type" (Fixed Amount / Half Day / Full Day /
 * Custom Multiplier) on top of the existing late_deduction_enabled toggle and
 * late_deduction_multiplier field, per rule (late, early).
 *
 * Backward compatible by construction: every existing row keeps
 * late_deduction_multiplier at whatever it already was, and the new mode
 * column defaults to 'custom_multiplier' — so a tenant that already enabled
 * deduction with a multiplier computes the exact same amount as before,
 * with zero migration-time data rewrite needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_policies', 'late_deduction_mode')) {
                $table->enum('late_deduction_mode', ['fixed_amount', 'half_day', 'full_day', 'custom_multiplier'])
                    ->default('custom_multiplier')
                    ->after('late_deduction_enabled');
            }
            if (! Schema::hasColumn('attendance_policies', 'late_deduction_amount')) {
                $table->decimal('late_deduction_amount', 10, 2)->nullable()->after('late_deduction_mode');
            }
            if (! Schema::hasColumn('attendance_policies', 'early_deduction_mode')) {
                $table->enum('early_deduction_mode', ['fixed_amount', 'half_day', 'full_day', 'custom_multiplier'])
                    ->default('custom_multiplier')
                    ->after('early_deduction_enabled');
            }
            if (! Schema::hasColumn('attendance_policies', 'early_deduction_amount')) {
                $table->decimal('early_deduction_amount', 10, 2)->nullable()->after('early_deduction_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            foreach (['late_deduction_mode', 'late_deduction_amount', 'early_deduction_mode', 'early_deduction_amount'] as $column) {
                if (Schema::hasColumn('attendance_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
