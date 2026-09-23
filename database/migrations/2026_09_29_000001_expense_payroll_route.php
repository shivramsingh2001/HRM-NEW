<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Phase 5 — pay approved reimbursements THROUGH PAYROLL (per company, behind the
 * `expense_payroll_link` feature flag).
 *
 *  expenses.payout_channel        NULL = normal (voucher) route; 'payroll' = sent to payroll.
 *  expenses.payroll_target_month  YYYY-MM the reimbursement should first appear in.
 *  expense_payroll_links          one row per attempt to route an expense through payroll:
 *                                   queued   sent to payroll, not yet on a payslip
 *                                   linked   on a specific (pending) payslip — monthly_payroll_id set
 *                                   paid     the payslip was paid; the expense payment exists
 *                                   released given back to the voucher route
 *                                 A partial-unique index guarantees at most ONE active
 *                                 (queued/linked/paid) link per expense, whatever the code does.
 *  expense_payments / batches     payment_mode gains 'payroll' (never selectable in the voucher UI).
 *
 * Everything defaults to "off": with the flag off, no company sees or gets any behaviour change.
 * Guarded like the other Expense migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'payout_channel')) {
                    $table->string('payout_channel', 10)->nullable();
                }
                if (! Schema::hasColumn('expenses', 'payroll_target_month')) {
                    $table->char('payroll_target_month', 7)->nullable();
                }
            });
        }

        if (! Schema::hasTable('expense_payroll_links')) {
            Schema::create('expense_payroll_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('expense_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->char('target_month', 7);
                $table->unsignedBigInteger('monthly_payroll_id')->nullable()->index();
                $table->decimal('amount', 15, 2);
                $table->enum('status', ['queued', 'linked', 'paid', 'released'])->default('queued');
                $table->unsignedBigInteger('expense_payment_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('released_reason', 255)->nullable();
                $table->timestamps();

                $table->foreign('monthly_payroll_id', 'epl_monthly_payroll_fk')->references('id')->on('monthly_payrolls')->nullOnDelete();
            });

            // At most one ACTIVE link per expense (a generated column is NULL for released rows, and
            // NULLs never collide in a unique index).
            DB::statement("ALTER TABLE expense_payroll_links
                ADD COLUMN active_expense_id BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status IN ('queued','linked','paid'), expense_id, NULL)) VIRTUAL,
                ADD UNIQUE KEY epl_one_active_per_expense (active_expense_id)");
        }

        foreach (['expense_payments', 'expense_payment_batches'] as $table) {
            $this->addPayrollMode($table);
        }
    }

    public function down(): void
    {
        foreach (['expense_payments', 'expense_payment_batches'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('payment_mode', 'payroll')->exists()) {
                throw new RuntimeException("Cannot roll back: {$table} holds payments made through payroll.");
            }
        }
        if (Schema::hasTable('expense_payroll_links') && DB::table('expense_payroll_links')->whereIn('status', ['linked', 'paid'])->exists()) {
            throw new RuntimeException('Cannot roll back: reimbursements are linked to / paid through payslips.');
        }

        foreach (['expense_payments', 'expense_payment_batches'] as $table) {
            $this->modeEnum($table, ['cash', 'bank_transfer', 'cheque', 'upi']);
        }
        Schema::dropIfExists('expense_payroll_links');

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                foreach (['payout_channel', 'payroll_target_month'] as $c) {
                    if (Schema::hasColumn('expenses', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }

    private function addPayrollMode(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'payment_mode')) {
            return;
        }

        $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE 'payment_mode'");
        if (str_contains((string) $col->Type, "'payroll'")) {
            return;
        }

        $this->modeEnum($table, ['cash', 'bank_transfer', 'cheque', 'upi', 'payroll']);
    }

    /** Rewrite the payment_mode enum, keeping the column's existing NULL-ability and default. */
    private function modeEnum(string $table, array $values): void
    {
        $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE 'payment_mode'");
        $list = implode(',', array_map(fn ($v) => "'{$v}'", $values));
        $null = $col->Null === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col->Default !== null ? "DEFAULT '" . str_replace("'", "''", $col->Default) . "'" : ($col->Null === 'YES' ? 'DEFAULT NULL' : '');

        DB::statement("ALTER TABLE `{$table}` MODIFY `payment_mode` ENUM({$list}) {$null} {$default}");
    }
};
