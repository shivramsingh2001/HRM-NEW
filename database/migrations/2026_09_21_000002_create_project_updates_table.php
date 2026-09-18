<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human-authored structured progress reports: completed work, pending work,
 * issues, next actions, and free-text notes. This is the core "project
 * update" feature — separate from AuditLogger's automatic field-diff trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_updates')) {
            Schema::create('project_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedTinyInteger('reported_progress_percentage')->nullable();
                $table->text('completed_work')->nullable();
                $table->text('pending_work')->nullable();
                $table->text('issues')->nullable();
                $table->text('next_actions')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['project_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
