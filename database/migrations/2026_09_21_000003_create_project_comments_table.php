<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mirrors task_comments exactly, including soft-delete. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_comments')) {
            Schema::create('project_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->text('comment');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['project_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_comments');
    }
};
