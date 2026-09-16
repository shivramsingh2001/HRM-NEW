<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `tasks` table. Schema-only, matches the live table
 * exactly, including the varchar date columns (converted to DATE by a later
 * migration, not here) and the existing group_lead_id -> users FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            return;
        }

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index('tasks_tenant_id_idx');
            $table->string('task_code', 10)->nullable();
            $table->enum('task_mode', ['individual', 'group'])->default('individual')->index('tasks_task_mode_index');
            $table->enum('group_completion_rule', ['all_must_complete', 'any_one', 'percentage', 'lead_decides'])->nullable();
            $table->foreignId('group_lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('title');
            $table->string('task_date')->nullable();
            $table->string('file')->nullable();
            $table->string('voice_file')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description')->nullable();
            $table->string('deadline_date');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->string('original_deadline_date', 100)->nullable();
            $table->integer('extension_count')->default(0);
            $table->unsignedBigInteger('parent_task_id')->nullable();
            $table->boolean('is_group_task')->default(false);
            $table->string('group_id', 50)->nullable()->index('tasks_group_id_index');
            $table->enum('completion_type', ['individual', 'collaborative', 'any_member'])->default('individual');
            $table->integer('min_members_to_complete')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->text('rejection_remarks')->nullable();
            $table->unsignedTinyInteger('completion_threshold')->nullable()->comment('For percentage rule: 1-100');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
