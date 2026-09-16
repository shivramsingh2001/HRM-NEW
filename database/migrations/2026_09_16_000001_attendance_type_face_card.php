<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Face + card attendance. The biometric pipeline already ingests face/card
 * punches; this lets them be labelled correctly and gives the bridge an HRM
 * card number to push.
 *
 *  - attendances.attendance_type gains 'face' and 'card' (enum widening; existing
 *    rows untouched).
 *  - users.card_number   : optional RFID/card value, pushed to the device.
 *  - biometric_enrollments.card_pushed : last card value written to a device
 *    (change / removal detection, mirrors name_pushed).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY attendance_type "
            . "ENUM('manual','fingerprint','face','card','app') NULL DEFAULT 'app'");

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'card_number')) {
                $table->string('card_number', 32)->nullable()->after('employee_id');
            }
        });

        Schema::table('biometric_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_enrollments', 'card_pushed')) {
                $table->string('card_pushed', 32)->nullable()->after('name_pushed');
            }
        });
    }

    public function down(): void
    {
        // Only safe to narrow the enum if nothing uses the new values.
        $inUse = DB::table('attendances')->whereIn('attendance_type', ['face', 'card'])->exists();
        if (! $inUse) {
            DB::statement("ALTER TABLE attendances MODIFY attendance_type "
                . "ENUM('manual','fingerprint','app') NULL DEFAULT 'app'");
        }

        Schema::table('biometric_enrollments', function (Blueprint $table) {
            $table->dropColumn('card_pushed');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
