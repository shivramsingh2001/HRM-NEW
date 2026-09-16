<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * task_approvals.tenant_id was `bigint unsigned NOT NULL`, while every
 * sibling table in this feature (tasks, task_assigns, task_updates,
 * projects, project_assigns) uses `int nullable`. This is a plain numeric
 * narrowing (bigint -> int) with no positional-index reinterpretation risk
 * (unlike the enum -> tinyint conversion elsewhere in this migration set),
 * so a direct MODIFY is safe. Existing tenant_id values are preserved as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('task_approvals') || !$this->isBigintNotNull()) {
            return;
        }

        DB::statement('ALTER TABLE task_approvals MODIFY tenant_id INT(11) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('task_approvals')) {
            return;
        }

        DB::statement('ALTER TABLE task_approvals MODIFY tenant_id BIGINT(20) UNSIGNED NOT NULL');
    }

    private function isBigintNotNull(): bool
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE as data_type, IS_NULLABLE as is_nullable FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_approvals' AND COLUMN_NAME = 'tenant_id'"
        );

        return $row && strtolower($row->data_type) === 'bigint' && strtoupper($row->is_nullable) === 'NO';
    }
};
