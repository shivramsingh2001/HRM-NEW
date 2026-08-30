<?php
// database/migrations/2024_01_01_000003_create_expense_budgets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->string('fiscal_year', 10);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('expense_type_id')->nullable();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('used_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('set null');
                  
            $table->foreign('project_id')
                  ->references('id')
                  ->on('projects')
                  ->onDelete('set null');
                  
            $table->foreign('expense_type_id')
                  ->references('id')
                  ->on('expense_types')
                  ->onDelete('set null');

            // Unique constraint
            $table->unique(['fiscal_year', 'department_id', 'project_id', 'expense_type_id'], 'budget_unique');
            
            // Indexes
            $table->index('fiscal_year');
            $table->index('department_id');
            $table->index('project_id');
            $table->index('expense_type_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_budgets');
    }
};