<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-attachment support for tasks (and optionally a specific comment).
 * The existing single tasks.file / tasks.voice_file columns are left as-is
 * for backward compatibility with rows created before this table existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_attachments')) {
            return;
        }

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_comment_id')->nullable()->constrained('task_comments')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('bytes');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
