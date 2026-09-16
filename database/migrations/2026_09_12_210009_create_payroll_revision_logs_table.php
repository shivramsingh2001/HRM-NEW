<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history of every salary change: who, when, why, and a
     * component-level before/after diff. Distinct from payroll_audit_logs
     * (which is a generic change trail across all payroll models) — this
     * one is specifically the human-readable "salary revision" record a
     * revision/increment screen would list.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_revision_logs')) {
            return;
        }

        Schema::create('payroll_revision_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payroll_employee_structure_id');
            $table->unsignedBigInteger('previous_payroll_employee_structure_id')->nullable();

            $table->enum('revision_type', ['initial', 'increment', 'promotion', 'demotion', 'correction', 'transfer']);
            $table->date('effective_from');
            $table->string('reason')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('approval_request_id')->nullable();

            $table->json('diff')->nullable(); // component-level before/after snapshot

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'user_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('payroll_employee_structure_id', 'prl_employee_structure_fk')->references('id')->on('payroll_employee_structures')->cascadeOnDelete();
            $table->foreign('previous_payroll_employee_structure_id', 'prl_previous_structure_fk')->references('id')->on('payroll_employee_structures')->nullOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_revision_logs');
    }
};
