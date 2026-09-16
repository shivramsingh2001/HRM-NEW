<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One row per "process payroll" batch execution for a period. Carries
     * engine_version, the load-bearing field for the whole legacy/dynamic
     * cutover strategy.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('payroll_period_id');
                $table->unsignedInteger('run_number')->default(1);
                $table->enum('status', ['draft', 'calculating', 'calculated', 'approved', 'paid', 'cancelled'])->default('draft');
                $table->string('engine_version', 20)->default('legacy_fixed');
                $table->unsignedBigInteger('triggered_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('employees_included')->default(0);
                $table->decimal('total_gross', 18, 2)->default(0);
                $table->decimal('total_net', 18, 2)->default(0);
                $table->decimal('total_deductions', 18, 2)->default(0);
                $table->unsignedBigInteger('approval_request_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'payroll_period_id']);

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('payroll_period_id')->references('id')->on('payroll_periods')->cascadeOnDelete();
                $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // monthly_payrolls needs to know which run produced it, so existing
        // rows (engine_version='legacy_fixed', added in an earlier migration)
        // stay nullable/untouched while new dynamic runs populate this.
        if (! Schema::hasColumn('monthly_payrolls', 'payroll_run_id')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->unsignedBigInteger('payroll_run_id')->nullable()->after('employee_payroll_id');
                $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('monthly_payrolls', 'payroll_run_id')) {
            Schema::table('monthly_payrolls', function (Blueprint $table) {
                $table->dropForeign(['payroll_run_id']);
                $table->dropColumn('payroll_run_id');
            });
        }

        Schema::dropIfExists('payroll_runs');
    }
};
