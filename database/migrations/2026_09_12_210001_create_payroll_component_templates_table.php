<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-curated component defaults offered to every tenant. Not
     * tenant-scoped (no TenantTrait on the model) — cloned into each
     * tenant's own payroll_component_master row, never queried directly by
     * the calculation engine.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_component_templates')) {
            return;
        }

        Schema::create('payroll_component_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->enum('component_type', ['earning', 'deduction', 'employer_contribution', 'reimbursement']);
            $table->boolean('is_statutory')->default(false);
            $table->enum('statutory_type', ['pf', 'esi', 'pt', 'tds', 'lwf'])->nullable();
            $table->enum('calculation_method', ['fixed_amount', 'percentage'])->default('fixed_amount');
            $table->enum('calculation_base_type', ['none', 'fixed_base', 'component'])->default('none');
            $table->enum('calculation_base', ['basic', 'gross_pass1', 'ctc'])->nullable();
            $table->string('calculation_base_component_code', 60)->nullable();
            $table->decimal('percentage_value', 6, 3)->nullable();
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->enum('proration_rule', ['prorate_by_payable_days', 'prorate_by_worked_hours', 'prorate_by_lop_days', 'no_proration'])->default('prorate_by_payable_days');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('has_wage_ceiling')->default(false);
            $table->decimal('ceiling_amount', 15, 2)->nullable();
            $table->enum('ceiling_apply_rule', ['cap_base_before_percentage', 'ceiling_exclude'])->nullable();
            $table->json('eligibility_rules')->nullable();
            $table->boolean('affects_gross')->default(true);
            $table->boolean('affects_ctc')->default(true);
            $table->boolean('affects_net')->default(true);
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('is_offered_to_new_tenants')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_templates');
    }
};
