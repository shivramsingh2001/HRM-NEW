<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 6/7.
 *
 * The computed final-settlement worksheet. `computed_amount` is the
 * system-calculated value and is never overwritten after generation;
 * `override_amount` is an HR/Finance edit; `final_amount` (= override ??
 * computed) is written by OffboardingSettlementService on every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offboarding_settlement_items')) {
            Schema::create('offboarding_settlement_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('offboarding_request_id');
                $table->enum('line_type', [
                    'pending_salary', 'leave_encashment', 'loan_deduction',
                    'expense_advance_deduction', 'expense_reimbursement',
                    'notice_shortfall_deduction', 'severance', 'custom_addition', 'custom_deduction',
                ]);
                $table->boolean('is_addition');
                $table->string('source_type', 40)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('label');
                $table->decimal('computed_amount', 15, 2)->default(0);
                $table->decimal('override_amount', 15, 2)->nullable();
                $table->decimal('final_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('offboarding_request_id')->references('id')->on('offboarding_requests')->cascadeOnDelete();
                $table->index('offboarding_request_id', 'offsettle_item_request_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_settlement_items');
    }
};
