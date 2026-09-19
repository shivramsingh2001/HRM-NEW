<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-structure-template override of which Earnings components a
     * percentage component's base sums (e.g. this template wants PF at
     * "12% of Basic + HRA" while another template wants "12% of Basic"
     * only) — mirrors payroll_employee_component_bases' per-employee
     * override, one layer up at the reusable-template level. Falls back to
     * the catalog's own payroll_component_base_components when a
     * structure component has no rows here.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_structure_component_bases')) {
            return;
        }

        Schema::create('payroll_structure_component_bases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_structure_component_id');
            $table->unsignedBigInteger('base_payroll_component_master_id');
            $table->timestamps();

            $table->unique(
                ['payroll_structure_component_id', 'base_payroll_component_master_id'],
                'pscb_structure_component_base_unique'
            );

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_structure_component_id', 'pscb_structure_component_fk')->references('id')->on('payroll_structure_components')->cascadeOnDelete();
            $table->foreign('base_payroll_component_master_id', 'pscb_base_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_structure_component_bases');
    }
};
