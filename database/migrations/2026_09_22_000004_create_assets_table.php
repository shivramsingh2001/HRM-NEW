<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');

                // Core / required for the workflow
                $table->string('asset_code', 40);
                $table->string('name', 200);
                $table->enum('status', [
                    'available', 'pending_acceptance', 'assigned', 'in_repair',
                    'damaged', 'lost', 'retired', 'disposed',
                ])->default('available');

                // Classification (optional — not every tenant categorizes)
                $table->unsignedBigInteger('asset_category_id')->nullable();
                $table->unsignedBigInteger('asset_type_id')->nullable();

                // Identification detail (optional)
                $table->string('serial_number', 100)->nullable();
                $table->string('model_number', 100)->nullable();
                $table->string('brand', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('barcode_value', 100)->nullable();
                $table->string('image')->nullable();

                // Purchase & vendor (optional)
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_cost', 14, 2)->nullable();
                $table->string('purchase_order_number', 100)->nullable();
                $table->string('invoice_number', 100)->nullable();

                // Warranty (optional)
                $table->date('warranty_start_date')->nullable();
                $table->date('warranty_end_date')->nullable();
                $table->string('warranty_provider', 150)->nullable();

                // Branch / location (optional)
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('location_notes', 255)->nullable();

                // Condition & current holder
                $table->enum('condition', ['new', 'good', 'fair', 'poor', 'damaged'])->nullable();
                $table->unsignedBigInteger('current_assignee_id')->nullable();

                // Financial / depreciation (optional)
                $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'none'])->nullable();
                $table->decimal('salvage_value', 14, 2)->nullable();
                $table->unsignedInteger('useful_life_months')->nullable();

                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();

                // Internal lifecycle bookkeeping
                $table->string('pre_repair_status', 30)->nullable();
                $table->timestamp('retired_at')->nullable();
                $table->timestamp('disposed_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'asset_code']);
                $table->index('tenant_id');
                $table->index('status');
                $table->index('asset_category_id');
                $table->index('branch_id');
                $table->index('current_assignee_id');

                $table->foreign('asset_category_id')->references('id')->on('asset_categories')->nullOnDelete();
                $table->foreign('asset_type_id')->references('id')->on('asset_types')->nullOnDelete();
                $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
                $table->foreign('branch_id')->references('id')->on('company_branches')->nullOnDelete();
                $table->foreign('current_assignee_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
