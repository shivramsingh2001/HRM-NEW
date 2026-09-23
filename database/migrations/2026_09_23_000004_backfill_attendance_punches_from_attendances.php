<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration: synthesize one `in` and one `out`
 * `attendance_punches` row from every existing `attendances.clock_in` /
 * `clock_out`, tagged `source='backfill'`, so the new punch table is populated
 * from day one instead of starting empty for existing tenants.
 *
 * Idempotent: no-ops if any `source='backfill'` row already exists, so it is
 * safe to re-run (e.g. after a rolled-back deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('attendance_punches')->where('source', 'backfill')->exists()) {
            return;
        }

        DB::table('attendances')->orderBy('id')->chunkById(2000, function ($rows) {
            foreach ($rows as $a) {
                $this->backfillRow($a);
            }
        }, 'id');
    }

    private function backfillRow(object $a): void
    {
        // A handful of legacy rows predate tenant scoping and have a NULL
        // tenant_id; fall back to the owning user's tenant, and skip the row
        // entirely (no punches synthesized) if that is unresolvable too —
        // attendance_punches.tenant_id is NOT NULL.
        $tenantId = $a->tenant_id ?: DB::table('users')->where('id', $a->user_id)->value('tenant_id');
        if (!$tenantId) {
            return;
        }

        DB::transaction(function () use ($a, $tenantId) {
            $sessionCount = 0;

            $inId = null;
            if (!empty($a->clock_in)) {
                $inId = DB::table('attendance_punches')->insertGetId([
                    'tenant_id' => $tenantId,
                    'user_id' => $a->user_id,
                    'date' => $a->date,
                    'direction' => 'in',
                    'punched_at' => $a->clock_in,
                    'punched_at_utc' => $a->clock_in_utc ?? null,
                    'timezone' => $a->tz ?? null,
                    'source' => 'backfill',
                    'method' => 'backfill_synthetic',
                    'lat' => $a->clock_in_lat ?? null,
                    'long' => $a->clock_in_long ?? null,
                    'address' => $a->clock_in_address ?? null,
                    'location_verification' => $a->location_verification ?? null,
                    'status' => 'active',
                    'session_seq' => 1,
                    'is_regularized' => !empty($a->regularization_id),
                    'regularization_id' => $a->regularization_id ?? null,
                    'attendance_id' => $a->id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                ]);
                $sessionCount = 1;
            }

            $outId = null;
            if (!empty($a->clock_out)) {
                $outId = DB::table('attendance_punches')->insertGetId([
                    'tenant_id' => $tenantId,
                    'user_id' => $a->user_id,
                    'date' => $a->date,
                    'direction' => 'out',
                    'punched_at' => $a->clock_out,
                    'punched_at_utc' => $a->clock_out_utc ?? null,
                    'timezone' => $a->tz ?? null,
                    'source' => 'backfill',
                    'method' => 'backfill_synthetic',
                    'lat' => $a->clock_out_lat ?? null,
                    'long' => $a->clock_out_long ?? null,
                    'address' => $a->clock_out_address ?? null,
                    'location_verification' => $a->location_verification ?? null,
                    'status' => 'active',
                    'session_seq' => 1,
                    'paired_punch_id' => $inId,
                    'is_regularized' => !empty($a->regularization_id),
                    'regularization_id' => $a->regularization_id ?? null,
                    'attendance_id' => $a->id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                ]);
                $sessionCount = 1;
            }

            if ($inId && $outId) {
                DB::table('attendance_punches')->where('id', $inId)->update(['paired_punch_id' => $outId]);
            }

            DB::table('attendances')->where('id', $a->id)->update([
                'session_count' => $sessionCount,
                'punches_last_synced_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('attendance_punches')->where('source', 'backfill')->delete();
        DB::table('attendances')->update(['session_count' => null, 'punches_last_synced_at' => null]);
    }
};
