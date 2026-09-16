<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Several columns referenced by App\Models\Loan / App\Models\LoanRepayment
     * fillable arrays (and by the raw SQL in MonthlyPayrollController /
     * LoanDeductionService) were never actually added by any tracked
     * migration -- the base loans/loan_repayments migrations predate the
     * repayment_type/lumpsum/audit-trail fields the app code now assumes
     * exist. Everything here is additive and Schema::hasColumn-guarded so
     * it's safe to run whether or not the live DB already has these columns
     * applied out-of-band.
     */
    public function up(): void
    {
        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                if (! Schema::hasColumn('loans', 'loan_application_id')) {
                    // No loan_applications table exists anywhere in this
                    // codebase -- plain nullable pointer column, no FK.
                    $table->unsignedBigInteger('loan_application_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('loans', 'loan_type_id')) {
                    $table->unsignedBigInteger('loan_type_id')->nullable()->after('loan_application_id');
                }
                if (! Schema::hasColumn('loans', 'processing_fee')) {
                    $table->decimal('processing_fee', 15, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('loans', 'total_payable')) {
                    $table->decimal('total_payable', 15, 2)->nullable()->after('processing_fee');
                }
                if (! Schema::hasColumn('loans', 'repayment_type')) {
                    $table->string('repayment_type', 20)->default('emi')->after('tenure_months');
                }
                if (! Schema::hasColumn('loans', 'lumpsum_due_date')) {
                    $table->date('lumpsum_due_date')->nullable()->after('first_emi_date');
                }
                if (! Schema::hasColumn('loans', 'lumpsum_amount')) {
                    $table->decimal('lumpsum_amount', 15, 2)->nullable()->after('lumpsum_due_date');
                }
                if (! Schema::hasColumn('loans', 'disbursed_by')) {
                    $table->unsignedBigInteger('disbursed_by')->nullable();
                }
                if (! Schema::hasColumn('loans', 'disbursed_at')) {
                    $table->timestamp('disbursed_at')->nullable();
                }
                if (! Schema::hasColumn('loans', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }
                if (! Schema::hasColumn('loans', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('loans', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
                if (! Schema::hasColumn('loans', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable();
                }
                if (! Schema::hasColumn('loans', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }
                if (! Schema::hasColumn('loans', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
            });

            // loans.status was created as enum('active','closed','default') --
            // the model's STATUS_PENDING/STATUS_APPROVED/STATUS_CANCELLED
            // values don't fit. Widen only if still narrow (idempotent).
            // No doctrine/dbal in this project, so a raw ALTER is used
            // instead of Schema::table()->change().
            if (DB::getDriverName() === 'mysql') {
                $column = DB::selectOne("SHOW COLUMNS FROM loans WHERE Field = 'status'");
                if ($column && str_contains($column->Type, 'enum') && ! str_contains($column->Type, "'pending'")) {
                    DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','active','closed','default','cancelled') NOT NULL DEFAULT 'pending'");
                }
            }
        }

        if (Schema::hasTable('loan_repayments')) {
            Schema::table('loan_repayments', function (Blueprint $table) {
                if (! Schema::hasColumn('loan_repayments', 'is_auto_deducted')) {
                    $table->boolean('is_auto_deducted')->default(false)->after('payment_mode');
                }
                if (! Schema::hasColumn('loan_repayments', 'salary_month')) {
                    $table->string('salary_month', 7)->nullable()->after('is_auto_deducted');
                }
                if (! Schema::hasColumn('loan_repayments', 'processed_by')) {
                    $table->unsignedBigInteger('processed_by')->nullable()->after('salary_month');
                }
                if (! Schema::hasColumn('loan_repayments', 'late_fee')) {
                    $table->decimal('late_fee', 15, 2)->default(0)->after('penalty_amount');
                }
            });

            if (Schema::hasColumn('loan_repayments', 'processed_by')) {
                $fkExists = DB::selectOne("
                    SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'loan_repayments'
                      AND COLUMN_NAME = 'processed_by'
                      AND REFERENCED_TABLE_NAME = 'users'
                ")->c;

                if (! $fkExists) {
                    Schema::table('loan_repayments', function (Blueprint $table) {
                        $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
                    });
                }
            }

            // repayment_number is NOT NULL + UNIQUE in the base migration, but
            // LoanController's schedule generators never populate it -- make
            // it nullable so existing/legacy rows and any pre-existing app
            // code that omits it don't fail; the generators are also being
            // fixed to always supply a value going forward.
            $repaymentNumberCol = DB::selectOne("SHOW COLUMNS FROM loan_repayments WHERE Field = 'repayment_number'");
            if ($repaymentNumberCol && stripos($repaymentNumberCol->Null, 'NO') === 0) {
                DB::statement("ALTER TABLE loan_repayments MODIFY COLUMN repayment_number VARCHAR(255) NULL");
            }
        }

        if (! Schema::hasTable('loan_categories')) {
            Schema::create('loan_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name', 100);
                $table->string('code', 20)->nullable();
                $table->decimal('max_amount', 15, 2)->nullable();
                $table->decimal('default_interest_rate', 5, 2)->default(0);
                $table->integer('max_tenure_months')->nullable();
                $table->boolean('requires_approval')->default(true);
                $table->boolean('status')->default(true);
                $table->integer('sort_order')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('status');
            });
        } elseif (! Schema::hasColumn('loan_categories', 'sort_order')) {
            Schema::table('loan_categories', function (Blueprint $table) {
                $table->integer('sort_order')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * This migration reconciles pre-existing drift rather than adding a
     * reversible feature -- down() intentionally does nothing so rolling it
     * back can't drop columns/tables other code paths may depend on.
     */
    public function down(): void
    {
        //
    }
};
