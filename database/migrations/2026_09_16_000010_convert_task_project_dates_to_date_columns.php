<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tasks.task_date/deadline_date/original_deadline_date and
 * projects.start_date/deadline_date were stored as VARCHAR, not a real date
 * type — string comparisons against these columns (overdue checks, date-range
 * filters) were comparing lexicographically, not chronologically, which is
 * only safe when every value shares the exact same format/length. Verified
 * against live data before writing this migration: all rows already matched
 * 'YYYY-MM-DD'. This migration defensively nulls out anything that doesn't
 * match that pattern (nullable columns only — deadline_date on tasks is
 * NOT NULL, so a malformed value there is left alone and will simply fail
 * the type change, surfacing the bad row instead of silently discarding it)
 * before converting each column to a native DATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks') || !Schema::hasTable('projects')) {
            return;
        }

        if ($this->isVarchar('tasks', 'task_date')) {
            DB::statement("UPDATE tasks SET task_date = NULL WHERE task_date IS NOT NULL AND task_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            DB::statement('ALTER TABLE tasks MODIFY task_date DATE NULL');
        }

        if ($this->isVarchar('tasks', 'deadline_date')) {
            DB::statement('ALTER TABLE tasks MODIFY deadline_date DATE NOT NULL');
        }

        if ($this->isVarchar('tasks', 'original_deadline_date')) {
            DB::statement("UPDATE tasks SET original_deadline_date = NULL WHERE original_deadline_date IS NOT NULL AND original_deadline_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            DB::statement('ALTER TABLE tasks MODIFY original_deadline_date DATE NULL');
        }

        if ($this->isVarchar('projects', 'start_date')) {
            DB::statement("UPDATE projects SET start_date = NULL WHERE start_date IS NOT NULL AND start_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            DB::statement('ALTER TABLE projects MODIFY start_date DATE NULL');
        }

        if ($this->isVarchar('projects', 'deadline_date')) {
            DB::statement("UPDATE projects SET deadline_date = NULL WHERE deadline_date IS NOT NULL AND deadline_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
            DB::statement('ALTER TABLE projects MODIFY deadline_date DATE NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks')) {
            DB::statement('ALTER TABLE tasks MODIFY task_date VARCHAR(255) NULL');
            DB::statement('ALTER TABLE tasks MODIFY deadline_date VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE tasks MODIFY original_deadline_date VARCHAR(100) NULL');
        }
        if (Schema::hasTable('projects')) {
            DB::statement('ALTER TABLE projects MODIFY start_date VARCHAR(50) NULL');
            DB::statement('ALTER TABLE projects MODIFY deadline_date VARCHAR(50) NULL');
        }
    }

    private function isVarchar(string $table, string $column): bool
    {
        $type = DB::selectOne(
            'SELECT DATA_TYPE as data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $type && strtolower($type->data_type) === 'varchar';
    }
};
