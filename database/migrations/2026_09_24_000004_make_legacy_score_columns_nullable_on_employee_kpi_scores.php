<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 4 legacy score columns (attendance/task_completion/deadline_met/
 * regularization) were NOT NULL DEFAULT 0.00 — forcing "no data this month"
 * to be stored identically to "scored a genuine zero". That's exactly the
 * missing-data-treated-as-zero bug class this redesign fixes everywhere
 * else (see PerformanceScoreCalculator::blend()); leaving these 4 columns
 * unable to express null would silently reintroduce it at the monthly
 * rollup layer. Relaxing a NOT NULL constraint only — no type/rename change.
 * Raw SQL (not ->change()) since doctrine/dbal isn't installed in this app.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY attendance_score DECIMAL(5,2) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY task_completion_score DECIMAL(5,2) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY deadline_met_score DECIMAL(5,2) NULL DEFAULT NULL');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY regularization_score DECIMAL(5,2) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE employee_kpi_scores SET attendance_score = 0.00 WHERE attendance_score IS NULL");
        DB::statement("UPDATE employee_kpi_scores SET task_completion_score = 0.00 WHERE task_completion_score IS NULL");
        DB::statement("UPDATE employee_kpi_scores SET deadline_met_score = 0.00 WHERE deadline_met_score IS NULL");
        DB::statement("UPDATE employee_kpi_scores SET regularization_score = 0.00 WHERE regularization_score IS NULL");

        DB::statement('ALTER TABLE employee_kpi_scores MODIFY attendance_score DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY task_completion_score DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY deadline_met_score DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE employee_kpi_scores MODIFY regularization_score DECIMAL(5,2) NOT NULL DEFAULT 0.00');
    }
};
