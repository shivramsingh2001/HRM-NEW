<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The `branches` table has only ever been used for attendance geofencing
 * (name + lat/long + radius, read by mobile check-in/check-out) — never an
 * organizational structure. Renaming it to reflect what it actually is,
 * freeing up "branch" for a new, separate organizational entity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && !Schema::hasTable('attendance_locations')) {
            Schema::rename('branches', 'attendance_locations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_locations') && !Schema::hasTable('branches')) {
            Schema::rename('attendance_locations', 'branches');
        }
    }
};
