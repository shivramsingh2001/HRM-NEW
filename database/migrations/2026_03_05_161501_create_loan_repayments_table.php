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
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
             $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('loan_id');
            $table->string('repayment_number')->unique();
            $table->integer('installment_number');
            $table->string('month', 7); // Format: YYYY-MM
            $table->date('due_date');
            $table->decimal('emi_amount', 15, 2);
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial'])->default('pending');
            $table->string('payment_mode')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->unsignedBigInteger('payment_received_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
            $table->foreign('payment_received_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('loan_id');
            $table->index('month');
            $table->index('status');
            $table->unique(['loan_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
