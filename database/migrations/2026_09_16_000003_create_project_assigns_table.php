<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formalizes the existing `project_assigns` table. Schema-only, matches the
 * live table exactly (including the enum('0','1') columns — converted to
 * proper booleans by a later migration, not here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_assigns')) {
            return;
        }

        Schema::create('project_assigns', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index('project_assigns_tenant_id_idx');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('is_head', ['0', '1'])->default('0');
            $table->enum('status', ['1', '0'])->nullable()->default('1');
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'unique_project_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assigns');
    }
};
