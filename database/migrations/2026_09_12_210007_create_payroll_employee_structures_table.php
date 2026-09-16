<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per employee per salary version — replaces the *role* of
     * user_payrolls for tenants on the new dynamic engine, without touching
     * user_payrolls itself. "Only one is_current=1 per user" is enforced by
     * a model observer (see PayrollEmployeeStructure), not a DB constraint,
     * since MySQL's unique index treats every NULL as distinct and
     * effective_to is nullable for the current row.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_employee_structures')) {
            return;
        }

        Schema::create('payroll_employee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payroll_structure_id')->nullable();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);

            $table->decimal('ctc', 15, 2)->nullable();
            $table->enum('revision_type', ['initial', 'increment', 'promotion', 'demotion', 'correction', 'transfer'])->default('initial');
            $table->string('revision_reason')->nullable();

            $table->enum('status', ['draft', 'pending_approval', 'active', 'superseded', 'cancelled'])->default('active');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();

            // Traceability for the Phase 1 one-time backfill so it can be
            // run idempotently and distinguished from genuine manual entries.
            $table->enum('source', ['manual', 'backfill_v1'])->default('manual');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id', 'effective_from'], 'pes_tenant_user_effective_unique');
            $table->index(['user_id', 'is_current']);
            $table->index(['effective_from', 'effective_to']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('payroll_structure_id')->references('id')->on('payroll_structures')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_structures');
    }
};
