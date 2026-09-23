<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expense Phase 2 — bring the LIVE child tables in line with what their
 * original migrations intended.
 *
 * Live `expense_payments` and `expense_status_histories` were found to have only
 * a PRIMARY KEY (none of the indexes the March migrations declared), and
 * `expense_attachments` has no PRIMARY KEY / AUTO_INCREMENT at all. Every payment
 * lookup (`WHERE expense_id = ?`) and every history read is therefore a full
 * table scan today.
 *
 * Only indexes are added here — no foreign keys yet: existing rows may reference
 * hard-deleted expenses (orphans) and constraints would fail; FKs are revisited
 * together with soft deletes.
 *
 * Idempotent: each index is skipped if any index already leads with that
 * column, so it is safe on fresh installs (where the March migrations already
 * created most of them) and on the live database.
 */
return new class extends Migration
{
    /** table => [index name => column] */
    private const INDEXES = [
        'expense_payments' => [
            'expense_payments_expense_id_index' => 'expense_id',
            'expense_payments_payment_date_index' => 'payment_date',
            'expense_payments_reference_number_index' => 'reference_number',
            'expense_payments_tenant_id_index' => 'tenant_id',
        ],
        'expense_status_histories' => [
            'expense_status_histories_expense_id_index' => 'expense_id',
            'expense_status_histories_tenant_id_index' => 'tenant_id',
        ],
    ];

    public function up(): void
    {
        $this->repairAttachments();

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $column) {
                if (! Schema::hasColumn($table, $column) || $this->hasIndexLeadingWith($table, $column)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($column, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasIndexLeadingWith(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    /** Live table has no PK/AUTO_INCREMENT. It is unused (0 rows) — rebuild it properly; refuse if it ever holds data. */
    private function repairAttachments(): void
    {
        if (! Schema::hasTable('expense_attachments')) {
            return;
        }

        $hasPrimary = collect(Schema::getIndexes('expense_attachments'))->contains(fn ($i) => ! empty($i['primary']));
        if ($hasPrimary) {
            return;
        }

        $rows = DB::table('expense_attachments')->count();
        if ($rows > 0) {
            throw new RuntimeException("expense_attachments has no primary key but holds {$rows} row(s); "
                . 'refusing to rebuild it automatically. Back it up and repair the table manually.');
        }

        Schema::drop('expense_attachments');
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id')->nullable()->index();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
