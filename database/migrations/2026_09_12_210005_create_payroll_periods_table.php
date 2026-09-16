<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors attendance_period_locks — gives payroll periods the same
     * lock/close semantics attendance already has, which today's bare
     * 'YYYY-MM' varchar strings on monthly_payrolls have none of.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_periods')) {
            return;
        }

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('year_month', 7); // 'YYYY-MM'
            $table->enum('status', ['open', 'processing', 'locked', 'paid', 'reopened'])->default('open');
            $table->date('pay_date')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year_month'], 'payroll_periods_tenant_ym_unique');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
