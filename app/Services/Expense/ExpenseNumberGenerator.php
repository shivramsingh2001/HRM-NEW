<?php

namespace App\Services\Expense;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Assigns `expenses.expense_number` = 'EXP-' . LPAD(id, 6, '0').
 *
 * Replaces the BEFORE INSERT trigger `generate_expense_number`, which read
 * information_schema.TABLES.AUTO_INCREMENT (not concurrency-safe), silently
 * overwrote any number the application set, and did not exist on a fresh
 * `php artisan migrate`. The format is identical to what the trigger produced.
 *
 * The number is derived from the row's own primary key AFTER the insert, so it
 * is unique by construction — no sequence table, no lock, no retry loop — and
 * the UNIQUE (tenant_id, expense_number) index is purely a safety net.
 */
class ExpenseNumberGenerator
{
    public const PREFIX = 'EXP-';

    public static function for(int $id): string
    {
        return self::PREFIX . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /** Set the number on a freshly-created expense (no-op if it already has one). */
    public function assign(Expense $expense): void
    {
        if (! empty($expense->expense_number)) {
            return;
        }

        $number = self::for((int) $expense->id);

        // Query-builder update: no model events, no updated_at bump, no tenant-scope surprises.
        DB::table('expenses')->where('id', $expense->id)->update(['expense_number' => $number]);

        $expense->setAttribute('expense_number', $number);
        $expense->syncOriginalAttribute('expense_number');
    }
}
