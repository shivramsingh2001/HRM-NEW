<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_disposals')) {
            Schema::create('asset_disposals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->unsignedBigInteger('disposed_by');
                $table->timestamp('disposed_at');

                $table->enum('method', ['sold', 'scrapped', 'donated', 'write_off', 'other'])->nullable();
                $table->decimal('sale_value', 14, 2)->nullable();
                $table->string('buyer_or_recipient', 150)->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();

                $table->timestamps();

                $table->index('tenant_id');
                $table->index('asset_id');

                $table->foreign('disposed_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
