<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Covers both risks and blockers via a `type` discriminator — one table, not two. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_risks')) {
            Schema::create('project_risks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->enum('type', ['risk', 'blocker']);
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('status', ['open', 'mitigated', 'resolved', 'closed'])->default('open');
                $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('raised_at')->nullable();
                $table->date('resolved_at')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['project_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risks');
    }
};
