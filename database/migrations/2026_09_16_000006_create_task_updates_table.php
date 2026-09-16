<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `task_updates` table (status/deadline change audit
 * trail). Schema-only, matches the live table exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_updates')) {
            return;
        }

        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index('task_updates_tenant_id_idx');
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('deadline_extension')->default(false);
            $table->string('old_deadline', 100)->nullable();
            $table->string('new_deadline', 100)->nullable();
            $table->text('extension_reason')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved', 'rejected', 'cancelled'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_updates');
    }
};
