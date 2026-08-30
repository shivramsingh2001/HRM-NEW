<?php
// database/migrations/2024_01_01_000002_create_expense_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('expense_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('expense_id')
                  ->references('id')
                  ->on('expenses')
                  ->onDelete('cascade');
                  
            $table->foreign('uploaded_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes
            $table->index('expense_id');
            $table->index('uploaded_by');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_attachments');
    }
};