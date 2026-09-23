<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per clock-in -> clock-out punch pair (attendance_punches.session_seq),
 * not per calendar day — a tenant with allow_multiple_punches=1 can have several
 * of these in one day, each with its own separated GPS trail. Replaces
 * attendance_tracks/attendance_id as the breadcrumb anchor; see
 * App\Services\FieldTracking\TrackingSessionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->date('date');

            $table->unsignedBigInteger('punch_in_id');
            $table->unsignedBigInteger('punch_out_id')->nullable();
            $table->unsignedTinyInteger('session_seq');
            $table->unsignedBigInteger('attendance_id')->nullable();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->string('status', 12)->default('open');
            $table->string('close_reason', 20)->nullable();

            $table->unsignedInteger('point_count')->default(0);
            $table->timestamp('last_point_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'punch_in_id'], 'ats_punch_in_uq');
            $table->index(['tenant_id', 'user_id', 'started_at'], 'ats_tenant_user_started_idx');
            $table->index(['tenant_id', 'status'], 'ats_tenant_status_idx');
            $table->index(['attendance_id'], 'ats_attendance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_tracking_sessions');
    }
};
