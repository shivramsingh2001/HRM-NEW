<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured agenda items and decisions, as JSON arrays rather than
     * dedicated tables — both are meeting-scoped planning/outcome content
     * with no independent lifecycle, no per-item status, and no cross-
     * meeting reporting requirement, so a repeater-style JSON column fully
     * captures them without join-table overhead. See the meeting-management
     * improvement plan for the full rationale.
     *
     * agenda_items: [{title, description?, presenter?, duration_minutes?}]
     * decisions:    [{decision_text, decided_by?, recorded_at}]
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'agenda_items')) {
                $table->json('agenda_items')->nullable()->after('agenda');
            }
            if (! Schema::hasColumn('meetings', 'decisions')) {
                $table->json('decisions')->nullable()->after('mom_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            foreach (['agenda_items', 'decisions'] as $column) {
                if (Schema::hasColumn('meetings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
