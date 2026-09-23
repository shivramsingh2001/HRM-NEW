<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Phase 3 — payment batches (vouchers), voids instead of deletes, and a
 * real "direct payment" flag.
 *
 *  - expense_payment_batches: one voucher per payment run (one or many expense
 *    lines, mode/date/reference entered once). A single-expense payment is simply
 *    a one-line batch, so there is ONE code path.
 *  - expense_voucher_sequences: gapless per-tenant voucher numbering (PV-000001…),
 *    incremented atomically inside the posting transaction (rolls back with it).
 *  - expense_payments: batch_id + status (posted|voided) + who/when/why voided +
 *    created_by. Payments are never hard-deleted any more; every "sum of payments"
 *    counts status='posted' only.
 *  - expenses.is_direct_payment: replaces sniffing `description LIKE 'Direct payment:%'`.
 *
 * Guarded/idempotent, like the other Expense migrations (the expense tables predate
 * migrations, so hasTable/hasColumn checks are mandatory).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_voucher_sequences')) {
            Schema::create('expense_voucher_sequences', function (Blueprint $table) {
                $table->integer('tenant_id')->primary();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expense_payment_batches')) {
            Schema::create('expense_payment_batches', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable()->index();
                $table->string('voucher_number', 30);
                $table->date('payment_date');
                $table->enum('payment_mode', ['cash', 'bank_transfer', 'cheque', 'upi']);
                $table->string('reference_number', 100)->nullable();
                $table->string('bank_name')->nullable();
                $table->text('remarks')->nullable();
                $table->decimal('total_amount', 15, 2);
                $table->unsignedInteger('line_count');
                $table->enum('status', ['posted', 'voided'])->default('posted');
                $table->string('idempotency_key', 64)->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 500)->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'voucher_number'], 'expense_batches_tenant_voucher_unique');
                $table->unique('idempotency_key', 'expense_payment_batches_idempotency_key_unique');
                $table->index(['tenant_id', 'payment_date'], 'expense_batches_tenant_date_index');
            });
        }

        if (Schema::hasTable('expense_payments')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('expense_payments', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable()->after('expense_id')->index('expense_payments_batch_id_index');
                }
                if (! Schema::hasColumn('expense_payments', 'status')) {
                    $table->enum('status', ['posted', 'voided'])->default('posted')->after('remarks');
                }
                if (! Schema::hasColumn('expense_payments', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('paid_by');
                }
                if (! Schema::hasColumn('expense_payments', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable();
                }
                if (! Schema::hasColumn('expense_payments', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable();
                }
                if (! Schema::hasColumn('expense_payments', 'void_reason')) {
                    $table->string('void_reason', 500)->nullable();
                }
            });

            // Existing payments were made by paid_by.
            DB::table('expense_payments')->whereNull('created_by')->update(['created_by' => DB::raw('paid_by')]);

            // "Sum of posted payments for this expense" is the hot query.
            if (! Schema::hasIndex('expense_payments', 'expense_payments_expense_status_index')) {
                Schema::table('expense_payments', fn (Blueprint $t) => $t->index(['expense_id', 'status'], 'expense_payments_expense_status_index'));
            }
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'is_direct_payment')) {
            Schema::table('expenses', fn (Blueprint $t) => $t->boolean('is_direct_payment')->default(false)->after('is_billable'));
        }

        // Backfill the flag from the marker the old code wrote into the description.
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'is_direct_payment')) {
            DB::table('expenses')->where('description', 'like', 'Direct payment:%')->where('requirement_type', 'advance')
                ->update(['is_direct_payment' => true]);
        }
    }

    public function down(): void
    {
        // Voids/batches hold real financial history once used — never drop them on rollback
        // if any exist. Only undo when the feature was never used.
        if (Schema::hasTable('expense_payment_batches') && DB::table('expense_payment_batches')->exists()) {
            throw new RuntimeException('Refusing to roll back: expense_payment_batches contains vouchers.');
        }

        if (Schema::hasTable('expense_payments')
            && DB::table('expense_payments')->where('status', 'voided')->exists()) {
            throw new RuntimeException('Refusing to roll back: expense_payments contains voided payments.');
        }

        Schema::dropIfExists('expense_payment_batches');
        Schema::dropIfExists('expense_voucher_sequences');

        if (Schema::hasTable('expense_payments')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                foreach (['expense_payments_expense_status_index', 'expense_payments_batch_id_index'] as $index) {
                    if (Schema::hasIndex('expense_payments', $index)) {
                        $table->dropIndex($index);
                    }
                }
            });
            foreach (['batch_id', 'status', 'created_by', 'voided_by', 'voided_at', 'void_reason'] as $col) {
                if (Schema::hasColumn('expense_payments', $col)) {
                    Schema::table('expense_payments', fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'is_direct_payment')) {
            Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('is_direct_payment'));
        }
    }
};
