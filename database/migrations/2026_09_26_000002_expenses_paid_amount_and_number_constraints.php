<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Phase 2 — `expenses` table upgrade (live table; runs on production).
 *
 *  1. paid_amount DECIMAL(15,2): cached SUM(expense_payments.amount), kept in sync
 *     under the same row lock as every payment write and backfilled here. Replaces
 *     one `payments()->sum()` query per expense.
 *  2. amount DECIMAL(10,2) -> DECIMAL(15,2): matches every other money column
 *     (a ₹1 crore claim overflowed the old column).
 *  3. date VARCHAR(255) -> DATE. Values are 'YYYY-MM-DD' strings today and stay
 *     that shape when read back (no model cast is added on purpose: the mobile API
 *     serialises this column and a Carbon cast would change it to an ISO timestamp).
 *  4. UNIQUE (tenant_id, expense_number). The number now comes from
 *     ExpenseNumberGenerator ('EXP-' . LPAD(id, 6, '0'), the same format the
 *     trigger produced) instead of a BEFORE INSERT trigger that (a) overwrote any
 *     value the app set, (b) read information_schema.AUTO_INCREMENT (not
 *     concurrency-safe) and (c) is absent on a fresh `migrate`.
 *  5. DROP TRIGGER generate_expense_number.
 *
 * Every step is idempotent and guarded by a pre-check that ABORTS with the
 * offending ids instead of coercing or losing data. Run against a copy of
 * production first.
 */
return new class extends Migration
{
    private const CHECK_NAME = 'chk_expenses_amount_positive';
    private const UNIQUE_NAME = 'expenses_tenant_number_unique';

    public function up(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        $this->preflight();

        // 1. paid_amount (+ backfill — recomputed every run, so it is always correct afterwards)
        if (! Schema::hasColumn('expenses', 'paid_amount')) {
            Schema::table('expenses', fn (Blueprint $t) => $t->decimal('paid_amount', 15, 2)->default(0)->after('amount'));
        }
        if (Schema::hasTable('expense_payments')) {
            DB::statement('UPDATE expenses e SET e.paid_amount = COALESCE('
                . '(SELECT SUM(p.amount) FROM expense_payments p WHERE p.expense_id = e.id), 0)');
        }

        // 2 + 3. column types
        $columns = collect(Schema::getColumns('expenses'))->keyBy('name');

        if (($columns['amount']['type'] ?? null) !== 'decimal(15,2)') {
            $this->modifyColumn('ALTER TABLE expenses MODIFY `amount` DECIMAL(15,2) NOT NULL');
        }
        if (($columns['date']['type_name'] ?? null) !== 'date') {
            $this->modifyColumn('ALTER TABLE expenses MODIFY `date` DATE NULL');
        }

        // 4. numbers: backfill blanks, then the unique key
        DB::statement("UPDATE expenses SET expense_number = CONCAT('EXP-', LPAD(id, 6, '0')) "
            . "WHERE expense_number IS NULL OR expense_number = ''");

        if (! Schema::hasIndex('expenses', self::UNIQUE_NAME)) {
            Schema::table('expenses', fn (Blueprint $t) => $t->unique(['tenant_id', 'expense_number'], self::UNIQUE_NAME));
        }

        // 5. trigger — last, so a privilege failure leaves everything above applied and re-runnable
        try {
            DB::unprepared('DROP TRIGGER IF EXISTS generate_expense_number');
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Everything else applied, but the trigger generate_expense_number could not be dropped '
                . '(the DB user needs the TRIGGER privilege). Run `DROP TRIGGER generate_expense_number;` as a '
                . 'privileged user, then re-run this migration. Original error: ' . $e->getMessage()
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        if (Schema::hasIndex('expenses', self::UNIQUE_NAME)) {
            Schema::table('expenses', fn (Blueprint $t) => $t->dropUnique(self::UNIQUE_NAME));
        }
        if (Schema::hasColumn('expenses', 'paid_amount')) {
            Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('paid_amount'));
        }

        // Column widening / DATE are left as-is: narrowing could truncate real data.
        DB::unprepared('DROP TRIGGER IF EXISTS generate_expense_number');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER generate_expense_number BEFORE INSERT ON expenses FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    SELECT AUTO_INCREMENT INTO next_id FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses';
    SET NEW.expense_number = CONCAT('EXP-', LPAD(next_id, 6, '0'));
END
SQL);
    }

    /** Abort BEFORE touching anything if the data can't be converted safely. */
    private function preflight(): void
    {
        $columns = collect(Schema::getColumns('expenses'))->keyBy('name');

        if (($columns['date']['type_name'] ?? null) !== 'date') {
            $bad = DB::select("SELECT id, `date` FROM expenses WHERE `date` IS NOT NULL AND `date` <> '' "
                . "AND (`date` NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' OR STR_TO_DATE(`date`, '%Y-%m-%d') IS NULL) LIMIT 25");
            $blank = DB::table('expenses')->where('date', '')->count();

            if ($bad || $blank) {
                throw new RuntimeException('Cannot convert expenses.date to DATE — these rows are not valid YYYY-MM-DD: '
                    . json_encode($bad) . ($blank ? " (+ {$blank} empty-string rows)" : '')
                    . '. Correct them and re-run; nothing has been changed.');
            }
        }

        if (! Schema::hasIndex('expenses', self::UNIQUE_NAME)) {
            $dups = DB::select("SELECT tenant_id, expense_number, COUNT(*) c FROM expenses "
                . "WHERE expense_number IS NOT NULL AND expense_number <> '' "
                . "GROUP BY tenant_id, expense_number HAVING c > 1 LIMIT 25");

            // Blank numbers get 'EXP-<id>'; make sure that can't collide with an existing one.
            $collide = DB::select("SELECT e.id FROM expenses e JOIN expenses o "
                . "ON o.tenant_id <=> e.tenant_id AND o.id <> e.id "
                . "AND o.expense_number = CONCAT('EXP-', LPAD(e.id, 6, '0')) "
                . "WHERE e.expense_number IS NULL OR e.expense_number = '' LIMIT 25");

            if ($dups || $collide) {
                throw new RuntimeException('Cannot add UNIQUE (tenant_id, expense_number) — duplicates: '
                    . json_encode($dups) . ' collisions: ' . json_encode($collide)
                    . '. Resolve them and re-run; nothing has been changed.');
            }
        }
    }

    /**
     * MODIFY on a column referenced by the CHECK constraint is accepted by MariaDB/MySQL
     * when compatible; if this server refuses, drop the check, modify, and put it back.
     */
    private function modifyColumn(string $sql): void
    {
        try {
            DB::statement($sql);

            return;
        } catch (Throwable $first) {
            $ddl = (string) (((array) DB::selectOne('SHOW CREATE TABLE expenses'))['Create Table'] ?? '');
            if (! str_contains($ddl, self::CHECK_NAME)) {
                throw $first;
            }
        }

        DB::statement('ALTER TABLE expenses DROP CONSTRAINT ' . self::CHECK_NAME);
        try {
            DB::statement($sql);
        } finally {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT ' . self::CHECK_NAME . ' CHECK (amount > 0)');
        }
    }
};
