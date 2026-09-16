<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which components a payroll_structure includes, with optional
     * per-structure overrides of the catalog's default calculation method/
     * value.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_structure_components')) {
            return;
        }

        Schema::create('payroll_structure_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('payroll_structure_id');
            $table->unsignedBigInteger('payroll_component_master_id');

            $table->enum('override_calculation_method', ['fixed_amount', 'percentage'])->nullable();
            $table->decimal('override_amount', 15, 2)->nullable();
            $table->decimal('override_percentage', 6, 3)->nullable();
            $table->unsignedBigInteger('override_calculation_base_component_id')->nullable();

            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['payroll_structure_id', 'payroll_component_master_id'], 'psc_structure_component_unique');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('payroll_structure_id')->references('id')->on('payroll_structures')->cascadeOnDelete();
            $table->foreign('payroll_component_master_id', 'psc_component_master_fk')->references('id')->on('payroll_component_master')->cascadeOnDelete();
            $table->foreign('override_calculation_base_component_id', 'psc_override_base_component_fk')->references('id')->on('payroll_component_master')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_structure_components');
    }
};
