<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 / T2-E — freeze a tenant's attendance for a month once payroll has run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_period_locks')) {
            return;
        }

        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('year_month', 7);
            $table->string('status', 12)->default('open'); // open | locked | reopened
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year_month'], 'attn_lock_tenant_ym_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_period_locks');
    }
};
