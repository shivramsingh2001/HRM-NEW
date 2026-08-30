<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant monthly late-arrival allowance.
 *
 *   late_halfday_enabled = 0  -> feature off, late days stay 'late'
 *   monthly_late_allowance = NULL -> unlimited, never auto-convert
 *   monthly_late_allowance = 0    -> every late day becomes a half day
 *   monthly_late_allowance = N    -> once the running late count in a calendar
 *                                    month exceeds N, that day and every later
 *                                    late day are recorded as half day
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'late_halfday_enabled')) {
                $table->boolean('late_halfday_enabled')->default(false)->after('notice_period');
            }
            if (!Schema::hasColumn('tenants', 'monthly_late_allowance')) {
                $table->unsignedTinyInteger('monthly_late_allowance')->nullable()->after('late_halfday_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach (['monthly_late_allowance', 'late_halfday_enabled'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
