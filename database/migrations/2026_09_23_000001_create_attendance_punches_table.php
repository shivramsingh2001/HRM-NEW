<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw Clock In/Out event log — one row per punch, from every source (mobile
 * GPS, web, manual/admin, biometric, future kiosk). `attendances` remains the
 * one-row-per-day rollup every report/payroll consumer already reads; this
 * table is what feeds it (mirrors the existing `biometric_punches` ->
 * `attendances` funnel pattern, generalised to all sources).
 *
 * No hard uniqueness on (user, punched_at, direction): unlike biometric device
 * replay, GPS/mobile punches have no stable natural key, so duplicate
 * suppression is an application-level debounce window (see
 * AttendancePunchService), not a DB constraint. `client_ref` covers mobile
 * retry-after-timeout idempotency instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_punches')) {
            Schema::create('attendance_punches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->date('date'); // the session's logical day (see cross-midnight rule in AttendancePunchService)
                $table->string('direction', 8); // in | out

                $table->dateTime('punched_at');
                $table->timestamp('punched_at_utc')->nullable();
                $table->string('timezone', 64)->nullable();

                // Source / provenance
                $table->string('source', 20); // mobile_app | web | manual | biometric | kiosk | api | backfill
                $table->string('method', 20)->nullable(); // gps | fingerprint | face | card | password | manual_entry
                $table->unsignedBigInteger('biometric_device_id')->nullable();

                // GPS / location
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('long', 10, 7)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('location_verification', 20)->nullable(); // verified | unverified | skipped
                $table->unsignedBigInteger('attendance_location_id')->nullable();
                $table->decimal('distance_meters', 8, 2)->nullable(); // distance from the matched attendance_location
                $table->decimal('accuracy_meters', 8, 2)->nullable();

                // Device / network telemetry
                $table->string('device_id', 191)->nullable();
                $table->string('network_type', 20)->nullable();
                $table->string('wifi_ssid', 191)->nullable();
                $table->unsignedTinyInteger('battery_percent')->nullable();

                // Manual/admin attribution
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_role', 20)->nullable();
                $table->string('reason', 500)->nullable();

                // Lifecycle — soft-cancel only, never deleted (audit history).
                $table->string('status', 12)->default('active'); // active | void
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 500)->nullable();

                // Session linkage, populated by the aggregation pass.
                $table->unsignedTinyInteger('session_seq')->nullable();
                $table->unsignedBigInteger('paired_punch_id')->nullable();

                // Regularization (Phase 2 — columns reserved now, unused in Phase 1).
                $table->unsignedBigInteger('regularization_id')->nullable();
                $table->boolean('is_regularized')->default(false);
                $table->unsignedBigInteger('original_punch_id')->nullable();

                // Idempotency for offline/retry sync from mobile.
                $table->string('client_ref', 64)->nullable();

                $table->unsignedBigInteger('attendance_id')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'date', 'punched_at'], 'punch_tenant_user_date_idx');
                $table->index(['tenant_id', 'status'], 'punch_tenant_status_idx');
                $table->index(['attendance_id'], 'punch_attendance_idx');
                $table->index(['biometric_device_id', 'punched_at'], 'punch_device_time_idx');
                $table->unique(['tenant_id', 'user_id', 'client_ref'], 'punch_client_ref_uq');
            });
        }

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_logs', 'attendance_punch_id')) {
                $table->unsignedBigInteger('attendance_punch_id')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'attendance_punch_id')) {
                $table->dropColumn('attendance_punch_id');
            }
        });

        Schema::dropIfExists('attendance_punches');
    }
};
