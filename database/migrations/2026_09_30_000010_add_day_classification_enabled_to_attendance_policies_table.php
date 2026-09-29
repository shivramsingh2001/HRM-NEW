<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On/Off switch for the Day Classification card (Company Policies). Off =
 * no hour-based present/half-day/absent scoring — any day with work counts
 * as present. Defaults to 1 so every existing policy row keeps today's
 * behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_policies', 'day_classification_enabled')) {
                $table->boolean('day_classification_enabled')->default(true)->after('fallback_half_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_policies', 'day_classification_enabled')) {
                $table->dropColumn('day_classification_enabled');
            }
        });
    }
};
