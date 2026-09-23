<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_repairs')) {
            Schema::create('asset_repairs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->timestamp('reported_at');
                $table->enum('status', ['reported', 'in_progress', 'completed', 'cancelled'])->default('reported');

                $table->unsignedBigInteger('reported_by')->nullable();
                $table->text('issue_description')->nullable();
                $table->unsignedBigInteger('sent_to_vendor_id')->nullable();
                $table->date('sent_at')->nullable();
                $table->date('expected_return_date')->nullable();
                $table->decimal('repair_cost', 14, 2)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');
                $table->index('status');

                $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('sent_to_vendor_id')->references('id')->on('vendors')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_repairs');
    }
};
