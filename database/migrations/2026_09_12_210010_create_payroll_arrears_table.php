<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a revision's effective_from falls inside an already-processed
     * period, the engine recomputes the delta for each affected historical
     * month and queues it here. The next run folds pending rows into a
     * system "Arrears" component line and stamps them included_in_payroll.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_arrears')) {
            return;
        }

        Schema::create('payroll_arrears', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payroll_revision_log_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable(); // payroll_component_master

            $table->enum('arrears_type', ['revision', 'correction', 'bonus_backdated', 'manual']);
            $table->string('period_from', 7); // 'YYYY-MM'
            $table->string('period_to', 7);

            $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('revised_amount', 15, 2)->default(0);
            $table->decimal('arrears_amount', 15, 2)->default(0); // can be negative = recovery

            $table->enum('status', ['pending', 'approved', 'included_in_payroll', 'paid', 'cancelled'])->default('pending');

            $table->unsignedBigInteger('target_payroll_period_id')->nullable();
            $table->unsignedBigInteger('target_monthly_payroll_id')->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('payroll_revision_log_id')->references('id')->on('payroll_revision_logs')->nullOnDelete();
            $table->foreign('component_id')->references('id')->on('payroll_component_master')->nullOnDelete();
            $table->foreign('target_payroll_period_id')->references('id')->on('payroll_periods')->nullOnDelete();
            $table->foreign('target_monthly_payroll_id')->references('id')->on('monthly_payrolls')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_arrears');
    }
};
