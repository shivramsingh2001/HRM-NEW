<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits "minutes of meeting" state out of the generic meeting `status`.
     * Today saving MOM content implicitly flips the meeting to `completed`
     * in the same write (MeetingMinuteController::store()) — there's no way
     * to distinguish "someone is still drafting minutes" from "minutes are
     * finalized." mom_status gives the MOM-authoring UI a real draft/
     * finalize workflow; `meetings.status` only becomes `completed` once
     * mom_status is `finalized`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('meetings', 'mom_status')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->enum('mom_status', ['not_started', 'draft', 'finalized'])
                    ->default('not_started')
                    ->after('mom_content');
            });
        }

        // Backfill from existing data: a meeting already marked completed
        // with mom_content present is, in effect, already finalized; one
        // with content but not completed is a draft; everything else is
        // untouched.
        DB::table('meetings')->whereNotNull('mom_content')->where('mom_content', '!=', '')
            ->where('status', 'completed')->update(['mom_status' => 'finalized']);

        DB::table('meetings')->whereNotNull('mom_content')->where('mom_content', '!=', '')
            ->where('status', '!=', 'completed')->update(['mom_status' => 'draft']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'mom_status')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropColumn('mom_status');
            });
        }
    }
};
