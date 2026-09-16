<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-owned, admin-editable payroll component catalog — the core of
     * the dynamic payroll rebuild. One row per component per tenant, cloned
     * from payroll_component_templates and then freely customizable.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_component_master')) {
            return;
        }

        Schema::create('payroll_component_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code', 60);
            $table->string('name');
            $table->enum('component_type', ['earning', 'deduction', 'employer_contribution', 'reimbursement']);
            $table->boolean('is_statutory')->default(false);
            $table->enum('statutory_type', ['pf', 'esi', 'pt', 'tds', 'lwf'])->nullable();

            $table->enum('calculation_method', ['fixed_amount', 'percentage'])->default('fixed_amount');
            $table->enum('calculation_base_type', ['none', 'fixed_base', 'component'])->default('none');
            $table->enum('calculation_base', ['basic', 'gross_pass1', 'ctc'])->nullable();
            $table->unsignedBigInteger('calculation_base_component_id')->nullable();
            $table->decimal('percentage_value', 6, 3)->nullable();
            $table->decimal('default_amount', 15, 2)->nullable();

            // Computation order. Must be strictly greater than the priority
            // of whatever component this one references via
            // calculation_base_component_id — validated at save time in
            // application code (see PayrollComponentMaster model), not by a
            // DB constraint, since MySQL can't express that relationally.
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
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_default')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('calculation_base_component_id')->references('id')->on('payroll_component_master')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_master');
    }
};
