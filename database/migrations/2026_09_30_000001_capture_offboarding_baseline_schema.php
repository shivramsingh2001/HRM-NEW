<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 1/7.
 *
 * `offboarding_requests`/`exit_interviews` were hand-built directly in MySQL
 * (no prior migration exists for either). This migration captures the live
 * production column set as-is so a fresh dev database matches production; on
 * any environment where the tables already exist this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offboarding_requests')) {
            Schema::create('offboarding_requests', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable()->index('offboarding_requests_tenant_id_idx');
                $table->string('request_code', 50)->nullable()->unique();
                $table->unsignedBigInteger('employee_id');
                $table->date('request_date');
                $table->date('last_working_date')->index();
                $table->date('resignation_date')->nullable();
                $table->enum('reason', ['resignation', 'retirement', 'termination', 'contract_end', 'mutual_agreement', 'other']);
                $table->text('reason_detail')->nullable();
                $table->text('feedback')->nullable();
                $table->boolean('eligible_for_rehire')->default(true);
                $table->enum('status', ['pending_approval', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending_approval')->index();

                $table->enum('manager_review_status', ['pending', 'approved', 'rejected'])->nullable()->default('pending');
                $table->unsignedBigInteger('manager_review_by')->nullable();
                $table->timestamp('manager_review_at')->nullable();
                $table->text('manager_review_comments')->nullable();

                $table->enum('hr_review_status', ['pending', 'approved', 'rejected'])->nullable()->default('pending');
                $table->unsignedBigInteger('hr_review_by')->nullable();
                $table->timestamp('hr_review_at')->nullable();
                $table->text('hr_review_comments')->nullable();

                $table->datetime('exit_interview_date')->nullable();
                $table->unsignedBigInteger('exit_interview_conducted_by')->nullable();
                $table->text('exit_interview_notes')->nullable();

                $table->text('hr_remarks')->nullable();
                $table->text('finance_remarks')->nullable();
                $table->text('it_remarks')->nullable();

                $table->enum('asset_return_status', ['pending', 'partial', 'completed'])->default('pending');
                $table->enum('document_return_status', ['pending', 'partial', 'completed'])->default('pending');
                $table->enum('clearance_status', ['pending', 'in_progress', 'completed'])->default('pending');

                $table->enum('knowledge_transfer_status', ['not_started', 'in_progress', 'completed'])->nullable()->default('not_started');
                $table->timestamp('knowledge_transfer_completed_at')->nullable();
                $table->text('knowledge_transfer_notes')->nullable();

                $table->enum('exit_interview_status', ['not_scheduled', 'scheduled', 'completed', 'cancelled'])->nullable()->default('not_scheduled');

                $table->decimal('full_final_settlement', 15, 2)->nullable();
                $table->enum('final_settlement_status', ['pending', 'processing', 'paid', 'cancelled'])->nullable()->default('pending');
                $table->timestamp('final_settlement_processed_at')->nullable();
                $table->unsignedBigInteger('final_settlement_processed_by')->nullable();
                $table->text('final_settlement_notes')->nullable();
                $table->date('settlement_paid_date')->nullable();

                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('offboarding_completed_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('users');
                $table->foreign('created_by')->references('id')->on('users');
                $table->foreign('approved_by')->references('id')->on('users');
                $table->foreign('exit_interview_conducted_by')->references('id')->on('users');
                $table->foreign('manager_review_by')->references('id')->on('users');
                $table->foreign('hr_review_by')->references('id')->on('users');
                $table->foreign('final_settlement_processed_by')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('exit_interviews')) {
            Schema::create('exit_interviews', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable();
                $table->unsignedBigInteger('offboarding_request_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('interviewer_id');
                $table->datetime('interview_date');
                $table->integer('work_environment_rating')->nullable();
                $table->integer('management_rating')->nullable();
                $table->integer('career_growth_rating')->nullable();
                $table->integer('compensation_rating')->nullable();
                $table->integer('work_life_balance_rating')->nullable();
                $table->text('primary_reason')->nullable();
                $table->text('what_would_improve')->nullable();
                $table->boolean('would_recommend')->nullable();
                $table->text('feedback_comments')->nullable();
                $table->text('suggestions')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('offboarding_request_id')->references('id')->on('offboarding_requests');
                $table->foreign('employee_id')->references('id')->on('users');
                $table->foreign('interviewer_id')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        // Baseline-capture only; the tables predate this migration in
        // production, so down() intentionally does not drop them.
    }
};
