<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `projects` table (previously created outside any
 * migration). Schema-only — matches the live table exactly, no structural
 * change. Guarded so it's a safe no-op on a database that already has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            return;
        }

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index('projects_tenant_id_idx');
            $table->string('project_code', 10)->nullable();
            $table->string('name');
            $table->string('start_date', 50)->nullable();
            $table->string('deadline_date', 50)->nullable();
            $table->unsignedBigInteger('project_head')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['ongoing', 'pending', 'hold', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
