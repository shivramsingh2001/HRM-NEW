<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled Reports (2026-11-10). `report_schedules` = one row per "email this
 * report at this time" rule an admin/HR user creates on Reports → Scheduled
 * Reports; `report_key` points into App\Support\ReportCatalog. The report runs
 * as `created_by` (same data scope as on screen). `report_schedule_runs` is
 * the delivery log (last 50 kept per schedule). New tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_schedules')) {
            Schema::create('report_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('created_by');
                $table->string('name', 150);
                $table->string('report_key', 60);
                $table->string('period', 20)->nullable();
                $table->json('filters')->nullable();
                $table->enum('frequency', ['daily', 'weekly', 'monthly']);
                $table->json('weekdays')->nullable();            // 0 = Sunday … 6 = Saturday (daily / weekly)
                $table->unsignedTinyInteger('day_of_month')->nullable(); // 1–28, 0 = last day (monthly)
                $table->time('send_time');
                $table->json('recipients');
                $table->string('subject', 191)->nullable();
                $table->text('message')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('paused_reason', 255)->nullable();
                $table->dateTime('next_run_at')->nullable();
                $table->dateTime('run_requested_at')->nullable(); // "Send now" — picked up by the next minute's dispatcher
                $table->dateTime('last_run_at')->nullable();
                $table->string('last_status', 20)->nullable();
                $table->string('last_error', 500)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'created_at'], 'rpt_sched_tenant_idx');
                $table->index(['is_active', 'next_run_at'], 'rpt_sched_due_idx');
                $table->index('run_requested_at', 'rpt_sched_requested_idx');
            });
        }

        if (! Schema::hasTable('report_schedule_runs')) {
            Schema::create('report_schedule_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreignId('report_schedule_id')->constrained('report_schedules')->cascadeOnDelete();
                $table->enum('trigger', ['schedule', 'manual']);
                $table->enum('status', ['sent', 'failed']);
                $table->string('period_label', 100)->nullable();
                $table->string('file_name', 191)->nullable();
                $table->unsignedInteger('file_size')->nullable();
                $table->json('recipients')->nullable();
                $table->text('error')->nullable();
                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();
                $table->timestamps();
                $table->index(['report_schedule_id', 'id'], 'rpt_sched_runs_sched_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedule_runs');
        Schema::dropIfExists('report_schedules');
    }
};
