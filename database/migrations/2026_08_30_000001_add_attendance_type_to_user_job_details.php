<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The application already reads and writes user_job_details.attendance_type
 * (UserController::updateAttendanceType, AttendanceController::getAttendance,
 * UserController@index) but the column was never added by a migration, so every
 * one of those paths 500s with "Unknown column 'attendance_type'".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_job_details', 'attendance_type')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->string('attendance_type', 30)
                    ->default('manual_attendance')
                    ->after('face_register')
                    ->comment('manual_attendance | face_verification');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_job_details', 'attendance_type')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->dropColumn('attendance_type');
            });
        }
    }
};
