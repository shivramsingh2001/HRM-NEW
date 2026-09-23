<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding module rebuild — step 2/7.
 *
 * Additive only. `manager_review_status`/`hr_review_status`/their `*_by`,
 * `*_at`, `*_comments` columns stay on the table as denormalized mirrors —
 * from this point on they are written only by OffboardingApprovalHandler,
 * never read as the source of truth (that becomes `approval_requests`/
 * `approval_actions`), but keeping them lets every existing list/filter
 * query keep working unmodified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offboarding_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('offboarding_requests', 'current_stage')) {
                $table->enum('current_stage', [
                    'pending_approval', 'knowledge_transfer', 'clearance', 'exit_interview',
                    'settlement', 'ready_to_complete', 'completed', 'rejected', 'cancelled',
                ])->default('pending_approval')->after('status');
            }
            if (! Schema::hasColumn('offboarding_requests', 'notice_period_days_required')) {
                $table->unsignedSmallInteger('notice_period_days_required')->nullable()->after('last_working_date');
            }
            if (! Schema::hasColumn('offboarding_requests', 'original_last_working_date')) {
                $table->date('original_last_working_date')->nullable()->after('last_working_date');
            }
            if (! Schema::hasColumn('offboarding_requests', 'settlement_computed_total')) {
                $table->decimal('settlement_computed_total', 15, 2)->nullable()->after('full_final_settlement');
            }
            if (! Schema::hasColumn('offboarding_requests', 'settlement_final_total')) {
                $table->decimal('settlement_final_total', 15, 2)->nullable()->after('settlement_computed_total');
            }
            if (! Schema::hasColumn('offboarding_requests', 'settlement_finalized_by')) {
                $table->unsignedBigInteger('settlement_finalized_by')->nullable()->after('settlement_final_total');
            }
            if (! Schema::hasColumn('offboarding_requests', 'settlement_finalized_at')) {
                $table->timestamp('settlement_finalized_at')->nullable()->after('settlement_finalized_by');
            }
            if (! Schema::hasColumn('offboarding_requests', 'settlement_payment_reference')) {
                $table->string('settlement_payment_reference', 100)->nullable()->after('settlement_paid_date');
            }
            if (! Schema::hasColumn('offboarding_requests', 'exit_interview_skipped')) {
                $table->boolean('exit_interview_skipped')->default(false)->after('exit_interview_status');
            }
            if (! Schema::hasColumn('offboarding_requests', 'exit_interview_skip_reason')) {
                $table->string('exit_interview_skip_reason', 255)->nullable()->after('exit_interview_skipped');
            }
            if (! Schema::hasColumn('offboarding_requests', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('offboarding_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_reason');
            }
            if (! Schema::hasColumn('offboarding_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('offboarding_requests', 'cancelled_reason')) {
                $table->text('cancelled_reason')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('offboarding_requests', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_reason');
            }
            if (! Schema::hasColumn('offboarding_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('offboarding_requests', 'rehire_eligibility_notes')) {
                $table->text('rehire_eligibility_notes')->nullable()->after('eligible_for_rehire');
            }
        });

        Schema::table('offboarding_requests', function (Blueprint $table) {
            $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM offboarding_requests'))
                ->pluck('Key_name')->unique()->values()->all();

            if (! in_array('offboarding_requests_tenant_status_idx', $indexes, true)) {
                $table->index(['tenant_id', 'status'], 'offboarding_requests_tenant_status_idx');
            }
            if (! in_array('offboarding_requests_tenant_stage_idx', $indexes, true)) {
                $table->index(['tenant_id', 'current_stage'], 'offboarding_requests_tenant_stage_idx');
            }
            if (! in_array('offboarding_requests_employee_status_idx', $indexes, true)) {
                $table->index(['employee_id', 'status'], 'offboarding_requests_employee_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offboarding_requests', function (Blueprint $table) {
            $table->dropIndex('offboarding_requests_tenant_status_idx');
            $table->dropIndex('offboarding_requests_tenant_stage_idx');
            $table->dropIndex('offboarding_requests_employee_status_idx');

            $table->dropColumn([
                'current_stage', 'notice_period_days_required', 'original_last_working_date',
                'settlement_computed_total', 'settlement_final_total', 'settlement_finalized_by',
                'settlement_finalized_at', 'settlement_payment_reference',
                'exit_interview_skipped', 'exit_interview_skip_reason',
                'rejected_reason', 'rejected_by', 'rejected_at',
                'cancelled_reason', 'cancelled_by', 'cancelled_at',
                'rehire_eligibility_notes',
            ]);
        });
    }
};
