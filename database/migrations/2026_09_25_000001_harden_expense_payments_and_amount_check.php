<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense module hardening (Phase 1).
 *
 * 1. expense_payments.idempotency_key (unique, nullable): the payment form
 *    sends a client-generated key so a double-click / retried request cannot
 *    record the same payment twice.
 * 2. CHECK (amount > 0) on expenses: defence-in-depth behind the form-request
 *    validation, so a bug or a direct write can never store a zero/negative
 *    claim (a negative settlement would otherwise ADD to the balance).
 *
 * The expenses / expense_payments tables pre-date migrations in this repo (the
 * live schema is the source of truth), so every change is guarded and the
 * migration is safe to run on an environment that already has it applied.
 */
return new class extends Migration
{
    private const CHECK_NAME = 'chk_expenses_amount_positive';

    public function up(): void
    {
        if (Schema::hasTable('expense_payments') && ! Schema::hasColumn('expense_payments', 'idempotency_key')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->after('remarks');
                $table->unique('idempotency_key', 'expense_payments_idempotency_key_unique');
            });
        }

        if (Schema::hasTable('expenses') && ! $this->hasAmountCheck()) {
            $bad = DB::table('expenses')->where('amount', '<=', 0)->count();
            if ($bad > 0) {
                throw new RuntimeException(
                    "Cannot add CHECK (amount > 0): {$bad} existing expense row(s) have a zero/negative amount. "
                    . 'Review and correct them, then re-run the migration.'
                );
            }

            DB::statement('ALTER TABLE expenses ADD CONSTRAINT ' . self::CHECK_NAME . ' CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && $this->hasAmountCheck()) {
            DB::statement('ALTER TABLE expenses DROP CONSTRAINT ' . self::CHECK_NAME);
        }

        if (Schema::hasTable('expense_payments') && Schema::hasColumn('expense_payments', 'idempotency_key')) {
            Schema::table('expense_payments', function (Blueprint $table) {
                $table->dropUnique('expense_payments_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }

    private function hasAmountCheck(): bool
    {
        $row = DB::selectOne('SHOW CREATE TABLE expenses');
        $ddl = (string) (((array) $row)['Create Table'] ?? '');

        return str_contains($ddl, self::CHECK_NAME);
    }
};
