<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attendance_punches` already carries device_id/network_type/wifi_ssid;
 * ip_address was missed on the original create migration — needed so
 * AttendanceRollupService can propagate it onto `attendances.ip_address`
 * exactly as the mobile clock-in controller always has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_punches', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('wifi_ssid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_punches', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
