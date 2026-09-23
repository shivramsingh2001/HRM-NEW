<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_attachments')) {
            Schema::create('asset_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('file_path');
                $table->unsignedBigInteger('uploaded_by');

                $table->string('context', 40)->nullable();
                $table->string('original_filename')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');

                $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
    }
};
