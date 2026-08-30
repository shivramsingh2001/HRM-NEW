<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_status_histories', function (Blueprint $table) {
           $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('expense_id');
            $table->enum('status', ['pending', 'approved', 'complete', 'cancelled']);
            $table->unsignedBigInteger('changed_by');
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('expense_id')
                  ->references('id')
                  ->on('expenses')
                  ->onDelete('cascade');
                  
            $table->foreign('changed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes
            $table->index(['expense_id', 'status']);
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_status_histories');
    }
};
