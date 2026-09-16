<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * project_assigns.is_head and .status were enum('0','1') / enum('1','0') —
 * boolean intent expressed as string-digit enums. Converts both to real
 * tinyint(1) booleans.
 *
 * IMPORTANT: a direct `MODIFY COLUMN ... TINYINT` on an enum column is NOT
 * safe here — verified on a scratch table before writing this migration.
 * MySQL converts enum -> numeric using the enum's 1-based POSITIONAL INDEX,
 * not its string value, so is_head='1' (enum position 2 in
 * ENUM('0','1')) would silently become 2, and '0' (position 1) would become
 * 1 — inverting every flag. Instead this adds a new column, backfills it by
 * explicit string comparison, then drops the old column and renames.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_assigns')) {
            return;
        }

        if ($this->isEnum('project_assigns', 'is_head')) {
            DB::statement('ALTER TABLE project_assigns ADD COLUMN is_head_new TINYINT(1) NOT NULL DEFAULT 0');
            DB::statement("UPDATE project_assigns SET is_head_new = CASE WHEN is_head = '1' THEN 1 ELSE 0 END");
            DB::statement('ALTER TABLE project_assigns DROP COLUMN is_head');
            DB::statement('ALTER TABLE project_assigns CHANGE is_head_new is_head TINYINT(1) NOT NULL DEFAULT 0');
        }

        if ($this->isEnum('project_assigns', 'status')) {
            DB::statement('ALTER TABLE project_assigns ADD COLUMN status_new TINYINT(1) NULL DEFAULT 1');
            DB::statement("UPDATE project_assigns SET status_new = CASE WHEN status = '1' THEN 1 WHEN status = '0' THEN 0 ELSE NULL END");
            DB::statement('ALTER TABLE project_assigns DROP COLUMN status');
            DB::statement('ALTER TABLE project_assigns CHANGE status_new status TINYINT(1) NULL DEFAULT 1');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_assigns')) {
            return;
        }

        DB::statement('ALTER TABLE project_assigns ADD COLUMN is_head_old ENUM(\'0\',\'1\') NOT NULL DEFAULT \'0\'');
        DB::statement("UPDATE project_assigns SET is_head_old = CASE WHEN is_head = 1 THEN '1' ELSE '0' END");
        DB::statement('ALTER TABLE project_assigns DROP COLUMN is_head');
        DB::statement('ALTER TABLE project_assigns CHANGE is_head_old is_head ENUM(\'0\',\'1\') NOT NULL DEFAULT \'0\'');

        DB::statement('ALTER TABLE project_assigns ADD COLUMN status_old ENUM(\'1\',\'0\') NULL DEFAULT \'1\'');
        DB::statement("UPDATE project_assigns SET status_old = CASE WHEN status = 1 THEN '1' WHEN status = 0 THEN '0' ELSE NULL END");
        DB::statement('ALTER TABLE project_assigns DROP COLUMN status');
        DB::statement('ALTER TABLE project_assigns CHANGE status_old status ENUM(\'1\',\'0\') NULL DEFAULT \'1\'');
    }

    private function isEnum(string $table, string $column): bool
    {
        $type = DB::selectOne(
            'SELECT DATA_TYPE as data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $type && strtolower($type->data_type) === 'enum';
    }
};
