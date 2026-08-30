<?php
// database/migrations/2024_01_01_000004_create_expense_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('expense_id');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_mode', ['cash', 'bank_transfer', 'cheque', 'upi'])->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('paid_to')->nullable();
            $table->unsignedBigInteger('paid_by');
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('expense_id')
                  ->references('id')
                  ->on('expenses')
                  ->onDelete('cascade');
                  
            $table->foreign('paid_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes
            $table->index('expense_id');
            $table->index('payment_date');
            $table->index('payment_mode');
            $table->index('reference_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_payments');
    }
};