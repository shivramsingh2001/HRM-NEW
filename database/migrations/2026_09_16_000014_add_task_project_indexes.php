<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_assigns') && !$this->hasIndex('task_assigns', 'task_assigns_task_assigned_to_idx')) {
            Schema::table('task_assigns', function (Blueprint $table) {
                $table->index(['task_id', 'assigned_to'], 'task_assigns_task_assigned_to_idx');
            });
        }

        if (Schema::hasTable('task_assigns') && !$this->hasIndex('task_assigns', 'task_assigns_assigned_to_status_idx')) {
            Schema::table('task_assigns', function (Blueprint $table) {
                $table->index(['assigned_to', 'individual_status'], 'task_assigns_assigned_to_status_idx');
            });
        }

        if (Schema::hasTable('tasks') && !$this->hasIndex('tasks', 'tasks_project_status_idx')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['project_id', 'status'], 'tasks_project_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_assigns')) {
            Schema::table('task_assigns', function (Blueprint $table) {
                $table->dropIndexIfExists('task_assigns_task_assigned_to_idx');
                $table->dropIndexIfExists('task_assigns_assigned_to_status_idx');
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndexIfExists('tasks_project_status_idx');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $row = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT COUNT(*) as cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );

        return $row && (int) $row->cnt > 0;
    }
};
