<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_damage_reports')) {
            Schema::create('asset_damage_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->timestamp('reported_at');
                $table->enum('type', ['damaged', 'lost']);

                $table->unsignedBigInteger('reported_by')->nullable();
                $table->text('description')->nullable();
                $table->decimal('estimated_loss_value', 14, 2)->nullable();
                $table->boolean('is_chargeable')->nullable();
                $table->decimal('charged_amount', 14, 2)->nullable();
                $table->enum('resolution', ['written_off', 'repaired', 'replaced', 'recovered'])->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');
                $table->index('type');

                $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_damage_reports');
    }
};
