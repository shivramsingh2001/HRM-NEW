<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codifies the Expense core tables that were only ever created directly in the
 * live database (no migration existed), so a fresh `php artisan migrate` works.
 *
 * Dated BEFORE 2026_03_08_115754 (the first existing expense migration) because
 * those later migrations declare foreign keys onto `expenses` / `users` and
 * would fail on a fresh install without it. On an existing database every table
 * already exists, so each create is skipped — this migration is a no-op there.
 *
 * The columns/indexes/keys below reproduce the LIVE DDL (read via SHOW CREATE
 * TABLE on 2026-09-26) in its legacy shape on purpose; the follow-up migration
 * 2026_09_26_000002 upgrades both fresh and existing databases the same way
 * (paid_amount, DECIMAL(15,2) amount, DATE date, unique expense number).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_types')) {
            Schema::create('expense_types', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable()->index('expense_types_tenant_id_idx');
                $table->string('name');
                $table->text('description')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable()->index('expenses_tenant_id_idx');
                $table->string('expense_number', 50)->nullable()->index('expenses_expense_number_index');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('requirement_type', ['advance', 'settlement', 'reimbursement'])->default('settlement');
                $table->foreignId('expense_type')->constrained('expense_types')->cascadeOnDelete();
                $table->bigInteger('project_id')->nullable();
                $table->decimal('amount', 10, 2);
                $table->boolean('is_billable')->nullable()->default(true)->index();
                $table->string('date')->nullable();
                $table->string('file')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['pending', 'approved', 'complete', 'cancelled'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_remarks')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expense_transactions')) {
            Schema::create('expense_transactions', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable();
                $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('transaction_type', ['advance_credited', 'settlement_debited', 'reimbursement_paid'])->index();
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_before', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('user_expense_balances')) {
            Schema::create('user_expense_balances', function (Blueprint $table) {
                $table->id();
                $table->integer('tenant_id')->nullable()->index();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('current_balance', 15, 2)->default(0)->index();
                $table->decimal('advance_balance', 15, 2)->default(0);
                $table->decimal('settlement_balance', 15, 2)->default(0);
                $table->decimal('reimbursement_balance', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: on an existing database these tables hold real
        // financial data and pre-date this migration, so rolling it back must
        // never drop them.
    }
};
