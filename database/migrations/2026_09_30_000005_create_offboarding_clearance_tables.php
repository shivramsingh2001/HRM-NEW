<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 5/7.
 *
 * `offboarding_clearance_templates`: per-tenant configurable checklist
 * catalog. `tenant_id = NULL` rows are the shared global default set,
 * same pattern as `onboarding_tasks.tenant_id IS NULL`.
 *
 * `offboarding_clearance_tasks`: per-request instantiated checklist state,
 * seeded from templates (source=checklist) plus one row per the employee's
 * real open `asset_assignments` row (source=asset) — completing an
 * asset-sourced task also updates the real assignment, not just this flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offboarding_clearance_templates')) {
            Schema::create('offboarding_clearance_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->enum('category', ['it', 'admin', 'finance', 'hr', 'other'])->default('other');
                $table->string('label');
                $table->string('description', 500)->nullable();
                $table->json('applies_to_reasons')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active', 'sort_order'], 'offclr_tmpl_tenant_active_idx');
            });
        }

        if (! Schema::hasTable('offboarding_clearance_tasks')) {
            Schema::create('offboarding_clearance_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('offboarding_request_id');
                $table->enum('source', ['checklist', 'asset']);
                $table->unsignedBigInteger('clearance_template_id')->nullable();
                $table->unsignedBigInteger('asset_assignment_id')->nullable();
                $table->enum('category', ['it', 'admin', 'finance', 'hr', 'other'])->default('other');
                $table->string('label');
                $table->enum('status', ['pending', 'completed', 'waived', 'not_applicable'])->default('pending');
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('offboarding_request_id')->references('id')->on('offboarding_requests')->cascadeOnDelete();
                $table->foreign('clearance_template_id')->references('id')->on('offboarding_clearance_templates')->nullOnDelete();
                $table->foreign('asset_assignment_id')->references('id')->on('asset_assignments')->nullOnDelete();
                $table->index(['offboarding_request_id', 'status'], 'offclr_task_request_status_idx');
                $table->unique(['offboarding_request_id', 'clearance_template_id'], 'offclr_task_request_template_uq');
                $table->unique(['offboarding_request_id', 'asset_assignment_id'], 'offclr_task_request_asset_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_clearance_tasks');
        Schema::dropIfExists('offboarding_clearance_templates');
    }
};
