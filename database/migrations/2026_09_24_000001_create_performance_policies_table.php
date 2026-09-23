<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, versioned performance-scoring policy — weights and penalty
 * rules for the daily performance engine (App\Services\Performance\*).
 * Mirrors attendance_policies' shape exactly: a NULL-tenant row is the
 * global default; a tenant row applies to every day >= effective_from.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_policies')) {
            Schema::create('performance_policies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->date('effective_from')->default('2000-01-01');

                // Top-level weights (sum to 100). Daily grain uses only the
                // first 5, renormalized among themselves — manager rating has
                // no daily meaning.
                $table->decimal('weight_attendance', 5, 2)->default(30.00);
                $table->decimal('weight_task_completion', 5, 2)->default(20.00);
                $table->decimal('weight_task_ontime', 5, 2)->default(15.00);
                $table->decimal('weight_project_participation', 5, 2)->default(10.00);
                $table->decimal('weight_regularization', 5, 2)->default(10.00);
                $table->decimal('weight_manager_rating', 5, 2)->default(15.00);

                // Attendance thresholds/penalties.
                $table->smallInteger('late_grace_minutes')->default(10);
                $table->decimal('late_penalty_per_incident', 5, 2)->default(2.00);
                $table->decimal('late_penalty_cap', 5, 2)->default(20.00);
                $table->smallInteger('early_departure_grace_minutes')->default(10);
                $table->decimal('early_departure_penalty_per_incident', 5, 2)->default(2.00);
                $table->decimal('early_departure_penalty_cap', 5, 2)->default(20.00);

                // Regularization penalties — distinguish outcome (fixes the
                // old service's "same penalty regardless of approved/rejected" bug).
                $table->decimal('regularization_penalty_approved', 5, 2)->default(2.00);
                $table->decimal('regularization_penalty_rejected', 5, 2)->default(15.00);
                $table->decimal('regularization_penalty_pending', 5, 2)->default(5.00);
                $table->decimal('regularization_penalty_cap', 5, 2)->default(30.00);

                // Task thresholds.
                $table->decimal('task_overdue_penalty_per_task', 5, 2)->default(5.00);
                $table->decimal('task_overdue_penalty_cap', 5, 2)->default(20.00);
                $table->unsignedSmallInteger('min_tasks_for_task_score')->default(1);

                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'effective_from'], 'perf_policy_tenant_effective_uq');
            });
        }

        $exists = DB::table('performance_policies')->whereNull('tenant_id')->exists();
        if (! $exists) {
            DB::table('performance_policies')->insert([
                'tenant_id' => null,
                'effective_from' => '2000-01-01',
                'weight_attendance' => 30.00,
                'weight_task_completion' => 20.00,
                'weight_task_ontime' => 15.00,
                'weight_project_participation' => 10.00,
                'weight_regularization' => 10.00,
                'weight_manager_rating' => 15.00,
                'late_grace_minutes' => 10,
                'late_penalty_per_incident' => 2.00,
                'late_penalty_cap' => 20.00,
                'early_departure_grace_minutes' => 10,
                'early_departure_penalty_per_incident' => 2.00,
                'early_departure_penalty_cap' => 20.00,
                'regularization_penalty_approved' => 2.00,
                'regularization_penalty_rejected' => 15.00,
                'regularization_penalty_pending' => 5.00,
                'regularization_penalty_cap' => 30.00,
                'task_overdue_penalty_per_task' => 5.00,
                'task_overdue_penalty_cap' => 20.00,
                'min_tasks_for_task_score' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_policies');
    }
};
