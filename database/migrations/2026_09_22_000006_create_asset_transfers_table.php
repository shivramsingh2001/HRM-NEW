<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_transfers')) {
            Schema::create('asset_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedBigInteger('from_user_id')->nullable();
                $table->unsignedBigInteger('to_user_id');
                $table->unsignedBigInteger('from_branch_id')->nullable();
                $table->unsignedBigInteger('to_branch_id')->nullable();
                $table->unsignedBigInteger('transferred_by');
                $table->timestamp('transferred_at');
                $table->text('reason')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');

                $table->foreign('from_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('from_branch_id')->references('id')->on('company_branches')->nullOnDelete();
                $table->foreign('to_branch_id')->references('id')->on('company_branches')->nullOnDelete();
                $table->foreign('transferred_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfers');
    }
};
