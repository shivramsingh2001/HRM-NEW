<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-off or recurring bonuses, approval-gated. Kept as its own table
     * (not reused as an allowance component) since it's inherently
     * different from a recurring salary component — folds into a system
     * "Bonus" component line the same way arrears do.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_bonuses')) {
            return;
        }

        Schema::create('payroll_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');

            $table->enum('bonus_type', ['performance', 'festival', 'referral', 'retention', 'statutory_annual', 'one_off', 'other']);
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_recurring')->default(false);
            $table->unsignedTinyInteger('recurrence_month')->nullable(); // 1-12, for annual recurring bonuses

            $table->unsignedBigInteger('target_payroll_period_id')->nullable();
            $table->enum('status', ['draft', 'approved', 'included_in_payroll', 'paid', 'cancelled'])->default('draft');

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('target_payroll_period_id')->references('id')->on('payroll_periods')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bonuses');
    }
};
