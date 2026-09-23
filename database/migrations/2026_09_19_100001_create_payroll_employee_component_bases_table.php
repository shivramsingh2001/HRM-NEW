<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of payroll_component_base_components onto an employee's
     * assigned component at assignment time — later catalog edits never
     * retroactively change an already-assigned employee, matching the same
     * snapshot philosophy as payroll_employee_components itself.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_employee_component_bases')) {
            return;
        }

        Schema::create('payroll_employee_component_bases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_employee_component_id');
            $table->unsignedBigInteger('base_payroll_component_master_id');
            $table->timestamps();

            $table->unique(
                ['payroll_employee_component_id', 'base_payroll_component_master_id'],
                'pecb_employee_component_base_unique'
            );

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_employee_component_id', 'pecb_employee_component_fk')->references('id')->on('payroll_employee_components')->cascadeOnDelete();
            $table->foreign('base_payroll_component_master_id', 'pecb_base_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_component_bases');
    }
};
