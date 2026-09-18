<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Idempotent backfill: one primary user_reporting_heads row per
     * existing user_job_details.reporting_head value. Safe to re-run —
     * skips a (user_id, reporting_head_id) pair that already exists.
     */
    public function up(): void
    {
        $rows = DB::table('user_job_details')
            ->whereNotNull('reporting_head')
            ->select('user_id', 'reporting_head', 'tenant_id')
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('user_reporting_heads')
                ->where('user_id', $row->user_id)
                ->where('reporting_head_id', $row->reporting_head)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('user_reporting_heads')->insert([
                'tenant_id' => $row->tenant_id,
                'user_id' => $row->user_id,
                'reporting_head_id' => $row->reporting_head,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill — not reversible without risking loss of
        // manually-added reporting heads added after this ran.
    }
};
