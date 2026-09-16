<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meeting Management Phase 1 hardening:
     *  - meeting_participants was missing responded_at/joined_at/left_at/
     *    response_comments columns even though the model's confirmAttendance()/
     *    markPresent()/markAbsent() methods already write to them — those
     *    calls would fatal with an "unknown column" SQL error today. Adding
     *    them is what actually makes attendance tracking functional.
     *  - meeting_participants.tenant_id was nullable and unpopulated (the
     *    model didn't use TenantTrait until this rebuild) — backfilled from
     *    the parent meeting so the new TenantTrait global scope doesn't
     *    silently hide existing rows.
     *  - meetings.meeting_id (business code, e.g. MT-000006) had no
     *    uniqueness constraint at all — adding a per-tenant unique index as
     *    defense-in-depth alongside the locking fix to the generator code.
     */
    public function up(): void
    {
        Schema::table('meeting_participants', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_participants', 'response_comments')) {
                $table->text('response_comments')->nullable()->after('attendance_status');
            }
            if (! Schema::hasColumn('meeting_participants', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('response_comments');
            }
            if (! Schema::hasColumn('meeting_participants', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('responded_at');
            }
            if (! Schema::hasColumn('meeting_participants', 'left_at')) {
                $table->timestamp('left_at')->nullable()->after('joined_at');
            }
        });

        // Backfill tenant_id on meeting_participants from the parent meeting
        // before TenantTrait's global scope starts filtering on it.
        DB::statement(
            'UPDATE meeting_participants mp
             INNER JOIN meetings m ON m.id = mp.meeting_id
             SET mp.tenant_id = m.tenant_id
             WHERE mp.tenant_id IS NULL OR mp.tenant_id != m.tenant_id'
        );

        if (! $this->indexExists('meetings', 'meetings_tenant_id_meeting_id_unique')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->unique(['tenant_id', 'meeting_id'], 'meetings_tenant_id_meeting_id_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('meetings', 'meetings_tenant_id_meeting_id_unique')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropUnique('meetings_tenant_id_meeting_id_unique');
            });
        }

        Schema::table('meeting_participants', function (Blueprint $table) {
            foreach (['left_at', 'joined_at', 'responded_at', 'response_comments'] as $column) {
                if (Schema::hasColumn('meeting_participants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
