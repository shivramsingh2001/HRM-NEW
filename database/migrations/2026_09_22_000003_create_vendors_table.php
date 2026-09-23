<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 150);
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->text('address')->nullable();
                $table->string('tax_number', 50)->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
