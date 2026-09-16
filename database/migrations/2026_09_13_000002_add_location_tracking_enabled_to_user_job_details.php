<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee continuous GPS tracking switch. Independent of user_job_details.type
 * (office/field), which only governs clock-in geofencing. Each enabled row consumes
 * one of the tenant's field-tracking seats.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_job_details', 'location_tracking_enabled')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->boolean('location_tracking_enabled')
                    ->default(false)
                    ->after('attendance_type')
                    ->comment('per-employee continuous GPS tracking; consumes a tenant field-tracking seat');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_job_details', 'location_tracking_enabled')) {
            Schema::table('user_job_details', function (Blueprint $table) {
                $table->dropColumn('location_tracking_enabled');
            });
        }
    }
};
