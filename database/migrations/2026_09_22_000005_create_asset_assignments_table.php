<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_assignments')) {
            Schema::create('asset_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('assigned_by');
                $table->timestamp('assigned_at');
                $table->enum('status', ['pending_acceptance', 'accepted', 'returned', 'transferred'])
                    ->default('pending_acceptance');

                $table->date('expected_return_date')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->unsignedBigInteger('accepted_by')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->unsignedBigInteger('returned_to')->nullable();
                $table->enum('return_condition', ['new', 'good', 'fair', 'poor', 'damaged'])->nullable();
                $table->text('remarks')->nullable();
                $table->text('acknowledgement_note')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');
                $table->index('user_id');
                $table->index('status');

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('assigned_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('accepted_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('returned_to')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
