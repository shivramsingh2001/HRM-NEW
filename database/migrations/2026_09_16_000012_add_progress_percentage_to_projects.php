<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Stores project completion progress instead of recomputing it from 2 live
 * COUNT queries every time a project row is rendered (Project::
 * getProgressPercentageAttribute). Kept in sync by a model observer
 * (Task saved/deleted) — see App\Observers\TaskProgressObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects') || Schema::hasColumn('projects', 'progress_percentage')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('status');
        });

        // One-time backfill from existing task data.
        DB::statement('
            UPDATE projects p
            SET p.progress_percentage = (
                SELECT CASE WHEN COUNT(*) = 0 THEN 0
                    ELSE ROUND(SUM(CASE WHEN t.status IN (\'completed\', \'approved\') THEN 1 ELSE 0 END) / COUNT(*) * 100)
                END
                FROM tasks t WHERE t.project_id = p.id
            )
        ');
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'progress_percentage')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('progress_percentage');
            });
        }
    }
};
