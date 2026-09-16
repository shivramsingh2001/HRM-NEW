<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `task_approvals` table. Schema-only, matches the
 * live table exactly — including tenant_id as NOT NULL bigint (inconsistent
 * with its sibling tables' nullable int tenant_id; normalized by a later
 * migration, not here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_approvals')) {
            return;
        }

        Schema::create('task_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('completed_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision_requested'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_approvals');
    }
};
