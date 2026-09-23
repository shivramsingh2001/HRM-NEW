<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only columns on the existing monthly `employee_kpi_scores` table
 * to support: the new project-participation criterion, an overdue-task
 * indicator, data-completeness auditing (this table is now a rollup of
 * `employee_daily_performance`, which may not have a full month of rows
 * yet), and transparency on which performance_policies version + weights
 * graded a given month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_kpi_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_kpi_scores', 'project_participation_score')) {
                $table->decimal('project_participation_score', 5, 2)->nullable()->after('regularization_score');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'project_assigned_tasks')) {
                $table->unsignedSmallInteger('project_assigned_tasks')->default(0)->after('project_participation_score');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'project_completed_tasks')) {
                $table->unsignedSmallInteger('project_completed_tasks')->default(0)->after('project_assigned_tasks');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'project_on_time_tasks')) {
                $table->unsignedSmallInteger('project_on_time_tasks')->default(0)->after('project_completed_tasks');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'overdue_tasks')) {
                $table->unsignedSmallInteger('overdue_tasks')->default(0)->after('late_completed_tasks');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'working_days_in_period')) {
                $table->unsignedSmallInteger('working_days_in_period')->nullable()->after('overtime_hours');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'days_calculated')) {
                $table->unsignedSmallInteger('days_calculated')->nullable()->after('working_days_in_period');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'days_expected')) {
                $table->unsignedSmallInteger('days_expected')->nullable()->after('days_calculated');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'policy_effective_from')) {
                $table->date('policy_effective_from')->nullable()->after('days_expected');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'manager_rating_included')) {
                $table->boolean('manager_rating_included')->default(false)->after('manager_rating_score');
            }
            if (! Schema::hasColumn('employee_kpi_scores', 'weights_snapshot')) {
                $table->json('weights_snapshot')->nullable()->after('calculation_audit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_kpi_scores', function (Blueprint $table) {
            foreach ([
                'project_participation_score', 'project_assigned_tasks', 'project_completed_tasks',
                'project_on_time_tasks', 'overdue_tasks', 'working_days_in_period', 'days_calculated',
                'days_expected', 'policy_effective_from', 'manager_rating_included', 'weights_snapshot',
            ] as $col) {
                if (Schema::hasColumn('employee_kpi_scores', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
