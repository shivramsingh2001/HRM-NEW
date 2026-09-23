<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 7/7.
 *
 * Audit trail of every notice-period deviation (waiver / early_release /
 * extension). A request can accumulate several over its life, so this is a
 * child table, not flat columns — approving one is what actually mutates
 * offboarding_requests.last_working_date (see OffboardingService::decideNoticeOverride()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offboarding_notice_overrides')) {
            Schema::create('offboarding_notice_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('offboarding_request_id');
                $table->enum('type', ['waiver', 'early_release', 'extension']);
                $table->text('reason');
                $table->date('previous_last_working_date');
                $table->date('requested_last_working_date');
                $table->unsignedBigInteger('requested_by');
                $table->timestamp('requested_at');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('decision_notes')->nullable();
                $table->timestamps();

                $table->foreign('offboarding_request_id')->references('id')->on('offboarding_requests')->cascadeOnDelete();
                $table->index(['offboarding_request_id', 'status'], 'offnotice_request_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_notice_overrides');
    }
};
