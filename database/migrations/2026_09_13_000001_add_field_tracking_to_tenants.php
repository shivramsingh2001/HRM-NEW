<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field GPS tracking add-on — per-tenant entitlement.
 *
 *   field_tracking_enabled        master switch (vendor-set, like custom_shifts_enabled)
 *   field_tracking_seats          paid seats; vendor-set, read-only in the tenant UI
 *   field_tracking_ping_seconds   desired continuous-tracking cadence (tenant-editable)
 *   field_tracking_retention_days attendance_tracks retention window (tenant-editable)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'field_tracking_enabled')) {
                $table->boolean('field_tracking_enabled')->default(false)->after('default_weekoff_days');
            }
            if (! Schema::hasColumn('tenants', 'field_tracking_seats')) {
                $table->unsignedInteger('field_tracking_seats')->default(0)->after('field_tracking_enabled');
            }
            if (! Schema::hasColumn('tenants', 'field_tracking_ping_seconds')) {
                $table->unsignedSmallInteger('field_tracking_ping_seconds')->default(60)->after('field_tracking_seats');
            }
            if (! Schema::hasColumn('tenants', 'field_tracking_retention_days')) {
                $table->unsignedSmallInteger('field_tracking_retention_days')->default(90)->after('field_tracking_ping_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            foreach ([
                'field_tracking_retention_days',
                'field_tracking_ping_seconds',
                'field_tracking_seats',
                'field_tracking_enabled',
            ] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
