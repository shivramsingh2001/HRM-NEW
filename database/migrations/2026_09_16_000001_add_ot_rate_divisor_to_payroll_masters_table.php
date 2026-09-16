<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll Audit Phase 2 — M8: the day-based overtime rate previously
     * always divided basic salary by the number of calendar days in the
     * month. Some tenants expect a fixed working-days convention (commonly
     * 26) instead, which yields a materially different (usually higher)
     * hourly rate. Makes the divisor explicit and tenant-configurable
     * instead of a fixed assumption baked into the formula — default
     * preserves the exact current behavior for every existing tenant.
     */
    public function up(): void
    {
        Schema::table('payroll_masters', function (Blueprint $table) {
            $table->enum('ot_rate_divisor_mode', ['calendar_days', 'fixed_working_days'])
                ->default('calendar_days')
                ->after('working_hours_per_day');
            $table->unsignedSmallInteger('ot_fixed_working_days')
                ->default(26)
                ->after('ot_rate_divisor_mode');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_masters', function (Blueprint $table) {
            $table->dropColumn(['ot_rate_divisor_mode', 'ot_fixed_working_days']);
        });
    }
};
