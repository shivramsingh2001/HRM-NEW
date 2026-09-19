<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multi-base selection for a percentage component: which Earnings
     * components (payroll_component_master_id -> base_payroll_component_master_id,
     * one row per selected base) a PF/ESIC/PT/TDS/employer-contribution-style
     * component sums when calculating "percentage of". Catalog-level —
     * snapshotted per employee onto payroll_employee_component_bases at
     * assignment time (see 2026_09_19_100001_*).
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_component_base_components')) {
            return;
        }

        Schema::create('payroll_component_base_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_component_master_id');
            $table->unsignedBigInteger('base_payroll_component_master_id');
            $table->timestamps();

            $table->unique(
                ['payroll_component_master_id', 'base_payroll_component_master_id'],
                'pcbc_component_base_unique'
            );

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_component_master_id', 'pcbc_component_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
            $table->foreign('base_payroll_component_master_id', 'pcbc_base_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_base_components');
    }
};
