<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GPS breadcrumbs belonging to an attendance_tracking_sessions row. Bulk-
 * inserted (chunked insertOrIgnore) by App\Services\FieldTracking\
 * TrackingPointIngestService — atp_session_point_uq is the retry-safe dedup
 * key (client-generated point_id, scoped to the session it was captured in).
 * Immutable once written — no updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_tracking_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('session_id');

            $table->string('point_id', 64);

            $table->dateTime('track_time');
            $table->decimal('lat', 10, 7);
            $table->decimal('long', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedTinyInteger('battery_per')->nullable();

            // Reserved for future route/geofence replay — not populated yet.
            $table->decimal('speed_mps', 6, 2)->nullable();
            $table->decimal('bearing', 6, 2)->nullable();
            $table->boolean('is_mock_location')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'session_id', 'point_id'], 'atp_session_point_uq');
            $table->index(['tenant_id', 'session_id', 'track_time'], 'atp_session_time_idx');
            $table->index(['tenant_id', 'user_id', 'track_time'], 'atp_tenant_user_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_tracking_points');
    }
};
