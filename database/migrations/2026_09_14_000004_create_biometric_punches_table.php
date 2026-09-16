<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw punch records received from the bridge. Deduped on
 * (serial_number, enroll_no, punched_at, raw_verify_mode) so re-sent batches are
 * harmless. ProcessBiometricPunch turns each into an attendances write via the
 * AttendanceEntryService funnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('biometric_punches')) {
            return;
        }

        Schema::create('biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('biometric_device_id')->index();
            $table->string('serial_number');
            $table->string('enroll_no');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('punched_at');
            $table->timestamp('punched_at_utc')->nullable();
            $table->string('direction', 8)->default('auto');   // in|out|auto
            $table->string('method', 20)->nullable();          // fingerprint|face|card|password|mixed|unknown
            $table->unsignedInteger('raw_verify_mode')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedBigInteger('device_pos')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 12)->default('pending');  // pending|processed|skipped|error
            $table->string('error', 500)->nullable();
            $table->unsignedBigInteger('attendance_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['serial_number', 'enroll_no', 'punched_at', 'raw_verify_mode'],
                'bio_punch_dedupe_uq'
            );
            $table->index(['tenant_id', 'status']);
            $table->index(['biometric_device_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punches');
    }
};
