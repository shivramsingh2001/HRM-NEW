<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll-scoped audit trail. This codebase has no generic audit
     * package (composer.json has none) and no generic AuditLog model — its
     * own convention is bespoke per-module history tables (e.g.
     * expense_status_histories), so this is a new payroll-specific table
     * rather than a new system-wide framework.
     */
    public function up(): void
    {
        if (Schema::hasTable('payroll_audit_logs')) {
            return;
        }

        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->enum('action', ['created', 'updated', 'deleted', 'approved', 'rejected', 'locked', 'reopened', 'paid']);
            $table->unsignedBigInteger('actor_id')->nullable(); // nullable for queued/system actions
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'auditable_type', 'auditable_id'], 'pal_tenant_auditable_idx');
            $table->index(['tenant_id', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
    }
};
