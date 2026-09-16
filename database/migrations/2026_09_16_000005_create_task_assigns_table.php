<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `task_assigns` table (per-member task assignment
 * and progress). Schema-only, matches the live table exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_assigns')) {
            return;
        }

        Schema::create('task_assigns', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index('task_assigns_tenant_id_idx');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->enum('member_role', ['lead', 'contributor', 'reviewer', 'observer'])->default('contributor');
            $table->enum('status', ['assigned', 'accepted', 'declined'])->default('assigned');
            $table->timestamps();
            $table->text('sub_responsibility')->nullable()->comment('What this specific member is responsible for');
            $table->enum('individual_status', ['pending', 'in_progress', 'completed', 'blocked'])
                ->default('pending')->index('task_assigns_individual_status_idx');
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('individual_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assigns');
    }
};
