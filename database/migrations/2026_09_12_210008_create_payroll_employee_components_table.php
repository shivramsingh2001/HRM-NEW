<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The actual dynamic salary breakdown: which components apply to this
     * employee's structure version, with calculation method/base/value
     * SNAPSHOTTED at assignment time — later catalog edits never
     * retroactively change an already-approved structure.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_employee_components')) {
            return;
        }

        Schema::create('payroll_employee_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_employee_structure_id');
            $table->unsignedBigInteger('payroll_component_master_id');

            // Snapshot of the catalog's rule at assignment time.
            $table->enum('calculation_method', ['fixed_amount', 'percentage']);
            $table->enum('calculation_base_type', ['none', 'fixed_base', 'component'])->default('none');
            $table->enum('calculation_base', ['basic', 'gross_pass1', 'ctc'])->nullable();
            $table->unsignedBigInteger('calculation_base_component_id')->nullable();

            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage_value', 6, 3)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['payroll_employee_structure_id', 'payroll_component_master_id'], 'pec_structure_component_unique');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_employee_structure_id', 'pec_employee_structure_fk')->references('id')->on('payroll_employee_structures')->cascadeOnDelete();
            $table->foreign('payroll_component_master_id', 'pec_component_master_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
            $table->foreign('calculation_base_component_id', 'pec_base_component_fk')->references('id')->on('payroll_component_master')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_components');
    }
};
