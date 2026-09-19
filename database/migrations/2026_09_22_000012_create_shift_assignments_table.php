<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source-of-truth, append-only record of shift assignments. `user_shifts`
 * stays as-is and becomes a per-day cache materialized FROM this table
 * (see App\Services\Shift\ShiftMaterializer) — every existing consumer of
 * `user_shifts` keeps working unmodified.
 *
 *   type = 'permanent' -> open-ended (end_date null) until changed/ended;
 *                         assigning a new permanent to a user auto-supersedes
 *                         their existing active permanent (history kept).
 *   type = 'flexible'  -> today's existing per-date/range behaviour, unchanged;
 *                         always wins over an active permanent for the dates
 *                         it covers (the override case).
 *
 * No FK constraints, matching the existing `shifts`/`user_shifts`/
 * `user_weekoffs` legacy tables (app-level relationships only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_id');
            $table->enum('type', ['permanent', 'flexible']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'superseded', 'ended', 'cancelled'])->default('active');
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->enum('source', ['manual', 'migration_backfill'])->default('manual');
            $table->enum('week_off_type', ['day_based', 'date_based'])->nullable();
            $table->json('week_off_days')->nullable();
            $table->json('week_off_dates')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'type', 'status'], 'shift_assign_tenant_user_type_status_idx');
            $table->index(['tenant_id', 'user_id', 'start_date', 'end_date'], 'shift_assign_tenant_user_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
