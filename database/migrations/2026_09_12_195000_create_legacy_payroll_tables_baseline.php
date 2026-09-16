<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These four tables (payroll_masters, user_payrolls, monthly_payrolls,
     * payroll_components) were originally created via raw SQL / DB import
     * and had NO migration history at all before this payroll rebuild.
     * This migration formalizes their schema-as-it-already-exists so a
     * fresh install/CI environment can rebuild them identically — it is a
     * documentation/portability baseline, not a structural change, and is
     * a no-op on any environment where these tables already exist (guarded
     * by hasTable() on every table).
     *
     * All later payroll migrations (half_days/engine_version additions,
     * tenant_id hardening, the new dynamic-engine tables, etc.) assume
     * these tables already exist in their pre-rebuild shape.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payroll_masters')) {
            Schema::create('payroll_masters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('payroll_code', 10)->nullable();
                $table->string('name');
                $table->double('hra')->default(0);
                $table->double('conveyence')->default(0); // [sic] historical spelling, kept as-is intentionally
                $table->double('medical_allowance')->default(0);
                $table->double('children_allowance')->default(0);
                $table->double('post_allowance')->default(0);
                $table->double('leave_travel_allowance')->default(0);
                $table->double('monthly_incentive')->default(0);
                $table->double('provident_fund')->default(0);
                $table->double('employer_provident_fund')->default(0);
                $table->double('esi')->default(0);
                $table->double('employer_esi')->default(0);
                $table->double('pt')->default(0);
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->enum('payroll_calculation_type', ['day_based', 'hour_based'])->default('day_based');
                $table->decimal('working_hours_per_day', 10, 2)->nullable();
                $table->decimal('hourly_rate_applied', 10, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_payrolls')) {
            Schema::create('user_payrolls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('payroll_master_id')->nullable();
                $table->string('payroll_code', 50);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->boolean('is_current')->default(true);
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('hra', 15, 2)->default(0);
                $table->decimal('conveyence', 15, 2)->default(0);
                $table->decimal('medical_allowance', 15, 2)->default(0);
                $table->decimal('children_allowance', 15, 2)->default(0);
                $table->decimal('post_allowance', 15, 2)->default(0);
                $table->decimal('leave_travel_allowance', 15, 2)->default(0);
                $table->decimal('monthly_incentive', 15, 2)->default(0);
                $table->decimal('special_allowance', 15, 2)->default(0);
                $table->decimal('provident_fund', 15, 2)->default(0);
                $table->decimal('employer_provident_fund', 15, 2)->default(0);
                $table->decimal('esi', 15, 2)->default(0);
                $table->decimal('employer_esi', 15, 2)->default(0);
                $table->decimal('professional_tax', 15, 2)->default(0);
                $table->decimal('tds', 15, 2)->default(0);
                $table->decimal('gross_salary', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->decimal('ctc', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'payroll_code']);
                $table->index(['user_id', 'is_current']);
                $table->index(['effective_from', 'effective_to']);

                $table->foreign('payroll_master_id')->references('id')->on('payroll_masters')->nullOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('monthly_payrolls')) {
            Schema::create('monthly_payrolls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_payroll_id');
                $table->decimal('payable_days', 10, 2)->nullable();
                $table->string('payroll_month', 7);
                $table->date('processing_date');
                $table->integer('total_working_days')->default(0);
                $table->integer('present_days')->default(0);
                $table->integer('absent_days')->default(0);
                $table->decimal('paid_leaves', 5, 2)->default(0);
                $table->decimal('unpaid_leaves', 5, 2)->default(0);
                $table->integer('holidays')->default(0);
                $table->integer('week_offs')->default(0);
                $table->decimal('overtime_hours', 10, 2)->default(0);
                $table->decimal('expected_hours', 10, 2)->default(0);
                $table->decimal('actual_worked_hours', 10, 2)->default(0);
                $table->decimal('hourly_rate', 10, 2)->default(0);
                $table->decimal('overtime_rate', 10, 2)->default(0);
                $table->decimal('overtime_hours_calculated', 10, 2)->default(0);
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('hra', 15, 2)->default(0);
                $table->decimal('conveyence', 15, 2)->default(0);
                $table->decimal('medical_allowance', 15, 2)->default(0);
                $table->decimal('children_allowance', 15, 2)->default(0);
                $table->decimal('post_allowance', 15, 2)->default(0);
                $table->decimal('leave_travel_allowance', 15, 2)->default(0);
                $table->decimal('monthly_incentive', 15, 2)->default(0);
                $table->decimal('special_allowance', 15, 2)->default(0);
                $table->decimal('overtime_amount', 15, 2)->default(0);
                $table->decimal('provident_fund', 15, 2)->default(0);
                $table->decimal('employer_provident_fund', 15, 2)->default(0);
                $table->decimal('esi', 15, 2)->default(0);
                $table->decimal('employer_esi', 15, 2)->default(0);
                $table->decimal('professional_tax', 15, 2)->default(0);
                $table->decimal('tds', 15, 2)->default(0);
                $table->decimal('loan_deduction', 15, 2)->default(0);
                $table->decimal('other_deductions', 15, 2)->default(0);
                $table->decimal('gross_earnings', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('net_payable', 15, 2)->default(0);
                $table->enum('payment_status', ['pending', 'processed', 'paid', 'cancelled'])->default('pending');
                $table->date('payment_date')->nullable();
                $table->string('payment_mode', 50)->nullable();
                $table->string('transaction_reference', 100)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'payroll_month']);
                $table->index(['payroll_month', 'payment_status']);

                $table->foreign('employee_payroll_id')->references('id')->on('user_payrolls');
                $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_components')) {
            Schema::create('payroll_components', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('monthly_payroll_id');
                $table->string('component_name');
                $table->enum('component_type', ['earning', 'deduction']);
                $table->decimal('amount', 15, 2);
                $table->boolean('is_taxable')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('monthly_payroll_id')->references('id')->on('monthly_payrolls')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Deliberately no-op: these are the tenant's live payroll tables.
        // Dropping them here would be catastrophic if this migration is
        // ever rolled back on an environment where they hold real data.
        // Use the historical DB backup to restore pre-baseline state instead.
    }
};
