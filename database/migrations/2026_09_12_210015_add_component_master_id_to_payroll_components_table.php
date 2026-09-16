<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traces new payslip line items back to the catalog while the 312
     * existing rows keep working unchanged (component_master_id stays null
     * for them, rendering exactly as today by component_name string).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_components', 'payroll_component_master_id')) {
            Schema::table('payroll_components', function (Blueprint $table) {
                $table->unsignedBigInteger('payroll_component_master_id')->nullable()->after('monthly_payroll_id');
                $table->foreign('payroll_component_master_id')->references('id')->on('payroll_component_master')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_components', 'payroll_component_master_id')) {
            Schema::table('payroll_components', function (Blueprint $table) {
                $table->dropForeign(['payroll_component_master_id']);
                $table->dropColumn('payroll_component_master_id');
            });
        }
    }
};
