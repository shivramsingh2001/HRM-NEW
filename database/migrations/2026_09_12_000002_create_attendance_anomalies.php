<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 / T2-F — flagged attendance anomalies (buddy-punch, impossible travel,
 * pattern break, chronic missing clock-out).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_anomalies')) {
            return;
        }

        Schema::create('attendance_anomalies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->date('date')->nullable();
            $table->string('type', 30);        // buddy_punch|impossible_travel|pattern_break|chronic_open
            $table->string('severity', 10)->default('medium'); // low|medium|high
            $table->json('detail')->nullable();
            $table->string('status', 12)->default('open');     // open|ack|dismissed
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('fingerprint', 64)->nullable();     // de-dupe key
            $table->timestamp('created_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unique(['tenant_id', 'fingerprint'], 'anom_tenant_fp_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_anomalies');
    }
};
