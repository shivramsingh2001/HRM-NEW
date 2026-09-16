<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll Audit Phase 4 — L2. run_number is computed in app code as
     * PayrollRun::where('payroll_period_id', $id)->count() + 1 with no
     * database-level guard -- a race between two concurrent "Process
     * Payroll" submissions for the same period could produce two runs with
     * the same run_number. Confirmed no existing duplicates in live data.
     */
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->unique(['payroll_period_id', 'run_number'], 'payroll_runs_period_run_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropUnique('payroll_runs_period_run_unique');
        });
    }
};
