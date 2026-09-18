<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `radius` already existed on the pre-rename `branches` table and is
 * actively read by geofencing (Api/Attendance/AttendanceController), but
 * was missing a default and never exposed in validation/UI. `geofence_enabled`
 * is new — defaults true so every existing location behaves exactly as
 * today unless an admin explicitly opts a specific location out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_locations', 'radius')) {
                $table->integer('radius')->nullable()->default(50)->after('longitude');
            }
            if (!Schema::hasColumn('attendance_locations', 'geofence_enabled')) {
                $table->boolean('geofence_enabled')->default(true)->after('radius');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_locations', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_locations', 'geofence_enabled')) {
                $table->dropColumn('geofence_enabled');
            }
        });
    }
};
