<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Phase 4 — workflow completeness.
 *
 *  expenses
 *    deleted_at                soft deletes: an employee "deleting" a never-actioned claim keeps the row (audit).
 *    withdrawn_at/_reason      an employee pulling back their OWN claim (distinct from a manager's rejection).
 *    parent_expense_id         a reimbursement created from a settlement's shortfall points at that settlement.
 *    possible_duplicate_of     flagged (never blocked) when the same person filed the same type/amount/date.
 *  expense_types
 *    max_amount                hard cap per claim (NULL = no cap).
 *    receipt_required_above    receipt mandatory when amount > this (NULL = never, 0 = always).
 *    max_backdate_days         claim date may be at most N days old (NULL = no limit).
 *  expense_budgets
 *    enforcement               'warn' (approve, but say so) | 'block' (refuse approval) when a cap would be exceeded.
 *
 * Every new rule column defaults to NULL/"off", so existing companies behave exactly as before until an
 * admin configures a limit. Guarded like the other Expense migrations (the tables predate migrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('expenses', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (! Schema::hasColumn('expenses', 'withdrawn_at')) {
                    $table->timestamp('withdrawn_at')->nullable();
                }
                if (! Schema::hasColumn('expenses', 'withdrawn_reason')) {
                    $table->string('withdrawn_reason', 500)->nullable();
                }
                if (! Schema::hasColumn('expenses', 'parent_expense_id')) {
                    $table->unsignedBigInteger('parent_expense_id')->nullable()->index('expenses_parent_expense_id_index');
                }
                if (! Schema::hasColumn('expenses', 'possible_duplicate_of')) {
                    $table->unsignedBigInteger('possible_duplicate_of')->nullable();
                }
            });
        }

        if (Schema::hasTable('expense_types')) {
            Schema::table('expense_types', function (Blueprint $table) {
                if (! Schema::hasColumn('expense_types', 'max_amount')) {
                    $table->decimal('max_amount', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('expense_types', 'receipt_required_above')) {
                    $table->decimal('receipt_required_above', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('expense_types', 'max_backdate_days')) {
                    $table->unsignedSmallInteger('max_backdate_days')->nullable();
                }
            });
        }

        if (Schema::hasTable('expense_budgets') && ! Schema::hasColumn('expense_budgets', 'enforcement')) {
            Schema::table('expense_budgets', function (Blueprint $table) {
                $table->enum('enforcement', ['warn', 'block'])->default('warn');
            });
        }
    }

    public function down(): void
    {
        // Soft-deleted expenses and withdrawals are real history — don't drop them on rollback if any exist.
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'deleted_at')
            && \Illuminate\Support\Facades\DB::table('expenses')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Refusing to roll back: expenses contains soft-deleted rows.');
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasIndex('expenses', 'expenses_parent_expense_id_index')) {
                    $table->dropIndex('expenses_parent_expense_id_index');
                }
            });
            foreach (['deleted_at', 'withdrawn_at', 'withdrawn_reason', 'parent_expense_id', 'possible_duplicate_of'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }

        foreach (['max_amount', 'receipt_required_above', 'max_backdate_days'] as $col) {
            if (Schema::hasTable('expense_types') && Schema::hasColumn('expense_types', $col)) {
                Schema::table('expense_types', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }

        if (Schema::hasTable('expense_budgets') && Schema::hasColumn('expense_budgets', 'enforcement')) {
            Schema::table('expense_budgets', fn (Blueprint $t) => $t->dropColumn('enforcement'));
        }
    }
};
