<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_histories')) {
            Schema::create('asset_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('action', 40);
                $table->string('status', 30)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('related_user_id')->nullable();
                $table->text('remarks')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['asset_id', 'created_at']);
                $table->index('related_user_id');

                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('related_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_histories');
    }
};
