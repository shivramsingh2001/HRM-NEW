<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of a loan instalment each payslip collected. Needed since payroll
 * also recovers OVERDUE instalments from earlier months (LoanDeductionService):
 * one instalment can now be paid across two payslips (part in September, the
 * rest in October), so editing / regenerating one payslip must undo only its
 * own share — loan_repayments.monthly_payroll_id can hold just one payslip.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_repayment_allocations')) {
            return;
        }

        Schema::create('loan_repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('loan_repayment_id');
            $table->unsignedBigInteger('monthly_payroll_id');
            $table->string('salary_month', 7);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'monthly_payroll_id'], 'lra_payroll_idx');
            $table->index('loan_repayment_id', 'lra_repayment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
    }
};
