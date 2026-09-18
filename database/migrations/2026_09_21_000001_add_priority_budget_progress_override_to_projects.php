<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'priority')) {
                // Reuses Task's own priority vocabulary so cross-entity report
                // filters (Project Reports) stay consistent.
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])
                    ->default('medium')->after('status');
            }
            if (!Schema::hasColumn('projects', 'budget')) {
                $table->decimal('budget', 12, 2)->nullable()->after('priority');
            }
            if (!Schema::hasColumn('projects', 'progress_manual_override')) {
                // When true, TaskProgressObserver skips its auto-recalculation
                // for this project, preserving a PM-reported percentage.
                $table->boolean('progress_manual_override')->default(false)->after('progress_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['priority', 'budget', 'progress_manual_override'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
