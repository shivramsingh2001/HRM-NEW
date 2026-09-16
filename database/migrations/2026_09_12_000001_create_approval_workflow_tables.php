<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2 / T2-A — configurable multi-level approval workflows.
 *
 * When a tenant has an active workflow for a request_type the engine drives the
 * approval; with none, the existing single-approver code path is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('request_type', 30); // regularization|overtime|leave|manual_attendance
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->json('applies_to')->nullable(); // {departments:[],designations:[],branches:[]} or null=all
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'request_type', 'is_active'], 'awf_tenant_type_active_idx');
            });
        }

        if (! Schema::hasTable('approval_workflow_steps')) {
            Schema::create('approval_workflow_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->unsignedSmallInteger('level'); // 1..N
                $table->string('approver_type', 20); // reporting_head|role|user|department_head
                $table->string('approver_ref', 100)->nullable(); // role name or user id
                $table->string('quorum', 5)->default('any'); // any|all
                $table->unsignedSmallInteger('sla_hours')->nullable();
                $table->string('on_breach', 12)->default('notify'); // notify|auto_approve|escalate
                $table->timestamps();
                $table->unique(['workflow_id', 'level'], 'awfs_workflow_level_uq');
            });
        }

        if (! Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('request_type', 30);
                $table->string('subject_type', 120);
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('workflow_id')->nullable();
                $table->unsignedSmallInteger('current_level')->default(1);
                $table->string('status', 15)->default('pending'); // pending|approved|rejected|cancelled|auto_approved
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'request_type', 'status'], 'areq_tenant_type_status_idx');
                $table->index(['subject_type', 'subject_id'], 'areq_subject_idx');
            });
        }

        if (! Schema::hasTable('approval_actions')) {
            Schema::create('approval_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('approval_request_id')->index();
                $table->unsignedSmallInteger('level');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 15); // approved|rejected|delegated|commented|escalated|auto_approved
                $table->string('remarks', 500)->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->json('meta')->nullable();
            });
        }

        if (! Schema::hasTable('approval_delegations')) {
            Schema::create('approval_delegations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('delegator_id');
                $table->unsignedBigInteger('delegate_id');
                $table->json('request_types')->nullable(); // null = all
                $table->date('starts_on');
                $table->date('ends_on');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['tenant_id', 'delegator_id', 'is_active'], 'adel_tenant_delegator_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
