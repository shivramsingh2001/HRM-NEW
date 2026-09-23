<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per employee per calendar day — the daily performance "fact"
 * table. App\Services\Performance\PerformanceRollupService aggregates these
 * into the existing monthly `employee_kpi_scores` table; weekly figures are
 * always computed on demand from this table (no separate weekly table,
 * matching the attendances/attendance_summaries precedent).
 *
 * Component score columns are NULLABLE on purpose: null means "genuinely
 * inapplicable that day" (e.g. no tasks assigned, a holiday, no
 * regularization request) and must never be confused with an earned 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_daily_performance')) {
            return;
        }

        Schema::create('employee_daily_performance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->date('performance_date');

            // Day classification.
            $table->string('day_type', 20); // working|weekoff|holiday|full_leave_paid|full_leave_unpaid
            $table->string('attendance_status', 20)->nullable(); // present|half_day|absent|paid_leave|unpaid_leave|first_half_leave|second_half_leave|holiday|week_off|checked_in_only|upcoming

            // Attendance raw inputs.
            $table->decimal('worked_hours', 5, 2)->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_departure_minutes')->default(0);
            $table->boolean('is_late')->default(false);
            $table->boolean('is_early_departure')->default(false);
            $table->boolean('is_unauthorized_absent')->default(false);

            // Regularization (that date only).
            $table->unsignedBigInteger('regularization_id')->nullable();
            $table->string('regularization_status', 12)->nullable(); // approved|rejected|pending

            // Task metrics (tasks.task_date = performance_date, task_assigns.assigned_to = user_id).
            $table->unsignedSmallInteger('assigned_tasks_count')->default(0);
            $table->unsignedSmallInteger('completed_tasks_count')->default(0);
            $table->unsignedSmallInteger('on_time_completed_tasks_count')->default(0);
            $table->unsignedSmallInteger('late_completed_tasks_count')->default(0);
            $table->unsignedSmallInteger('overdue_tasks_count')->default(0);

            // Project-scoped task metrics (same tasks, filtered to project_id + active project_assigns membership).
            $table->unsignedSmallInteger('project_assigned_tasks_count')->default(0);
            $table->unsignedSmallInteger('project_completed_tasks_count')->default(0);
            $table->unsignedSmallInteger('project_on_time_tasks_count')->default(0);

            // Component scores — nullable, see class docblock.
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->decimal('task_completion_score', 5, 2)->nullable();
            $table->decimal('task_ontime_score', 5, 2)->nullable();
            $table->decimal('project_participation_score', 5, 2)->nullable();
            $table->decimal('regularization_score', 5, 2)->nullable();
            $table->decimal('overall_daily_score', 5, 2)->nullable();

            $table->string('calculation_status', 12)->default('pending'); // pending|calculated|excluded|failed
            $table->date('policy_effective_from')->nullable();
            $table->json('components_included')->nullable();
            $table->json('calculation_audit')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'performance_date'], 'daily_perf_tenant_user_date_uq');
            $table->index(['tenant_id', 'performance_date'], 'daily_perf_tenant_date_idx');
            $table->index(['tenant_id', 'calculation_status'], 'daily_perf_tenant_status_idx');
            $table->index(['user_id', 'performance_date'], 'daily_perf_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_daily_performance');
    }
};
