<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days that end on the next calendar day (e.g. clock in 06:30, clock out
 * 09:00 the next morning):
 *  - attendance_regularizations.out_next_day — the requested out time is on
 *    the day after the attendance date (time-only inputs could not say so);
 *  - tenants.auto_clockout_hours — how long a clock-in may stay open before
 *    attendance:auto-clockout closes it (was a fixed 15 h for everyone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_regularizations', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_regularizations', 'out_next_day')) {
                $table->boolean('out_next_day')->default(false)->after('out_time');
            }
        });
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'auto_clockout_hours')) {
                $table->unsignedTinyInteger('auto_clockout_hours')->default(15)->after('allow_multiple_punches');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_regularizations', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_regularizations', 'out_next_day')) {
                $table->dropColumn('out_next_day');
            }
        });
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'auto_clockout_hours')) {
                $table->dropColumn('auto_clockout_hours');
            }
        });
    }
};
