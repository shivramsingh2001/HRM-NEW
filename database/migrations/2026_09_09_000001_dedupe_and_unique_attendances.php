<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make (tenant_id, user_id, date) unique on `attendances`.
 *
 * The concurrent-clock-in race + non-atomic firstOrNew paths have left historical
 * duplicate rows and the composite index was only non-unique. This migration:
 *   1. backfills any NULL tenant_id from the row's user,
 *   2. merges + removes duplicate (tenant_id, user_id, date) rows, keeping the
 *      most-complete one,
 *   3. swaps the non-unique index for a UNIQUE key.
 */
return new class extends Migration
{
    /** Presence columns worth rescuing from a duplicate before it is deleted. */
    private array $mergeable = [
        'clock_in', 'clock_out', 'clock_in_lat', 'clock_in_long', 'clock_in_address',
        'clock_out_lat', 'clock_out_long', 'clock_out_address', 'total_hours', 'worked_hours',
        'scheduled_shift_start', 'scheduled_shift_end', 'shift_id', 'branch_id',
        'regularization_id', 'regularized_by', 'regularized_at',
        'marked_by', 'effective_status', 'policy_note', 'remarks', 'metadata',
    ];

    /** Tables holding an attendance_id that must follow the kept row. */
    private const CHILD_TABLES = [
        'attendance_logs', 'attendance_tracks', 'attendance_punches',
        'biometric_punches', 'attendance_tracking_sessions',
    ];

    public function up(): void
    {
        // 1. Backfill NULL tenant_id from the user.
        DB::statement('UPDATE attendances a JOIN users u ON u.id = a.user_id
                       SET a.tenant_id = u.tenant_id
                       WHERE a.tenant_id IS NULL');

        // 2. De-duplicate.
        $groups = DB::table('attendances')
            ->select('tenant_id', 'user_id', 'date')
            ->groupBy('tenant_id', 'user_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deleted = 0;
        foreach ($groups as $g) {
            $rows = DB::table('attendances')
                ->where('tenant_id', $g->tenant_id)
                ->where('user_id', $g->user_id)
                ->where('date', $g->date)
                ->orderByRaw('(clock_out IS NOT NULL) DESC')
                ->orderByRaw('(clock_in IS NOT NULL) DESC')
                ->orderByRaw('(COALESCE(regularization_id, 0) > 0) DESC')
                ->orderByRaw('(marked_by IS NOT NULL) DESC')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $keeper = $rows->shift();
            $patch = [];
            foreach ($this->mergeable as $col) {
                if ($this->isEmptyVal($keeper->{$col} ?? null)) {
                    foreach ($rows as $loser) {
                        if (!$this->isEmptyVal($loser->{$col} ?? null)) {
                            $patch[$col] = $loser->{$col};
                            break;
                        }
                    }
                }
            }
            if ($patch) {
                DB::table('attendances')->where('id', $keeper->id)->update($patch);
            }
            $ids = $rows->pluck('id')->all();

            // Move child rows to the keeper first — attendance_tracks cascades
            // on delete and the others would be left pointing at a deleted id.
            foreach (self::CHILD_TABLES as $child) {
                if ($ids && Schema::hasTable($child) && Schema::hasColumn($child, 'attendance_id')) {
                    DB::table($child)->whereIn('attendance_id', $ids)->update(['attendance_id' => $keeper->id]);
                }
            }
            $deleted += DB::table('attendances')->whereIn('id', $ids)->delete();
        }

        // 3. Swap the index.
        if ($this->indexExists('attendances', 'attn_tenant_user_date_idx')) {
            DB::statement('ALTER TABLE attendances DROP INDEX attn_tenant_user_date_idx');
        }
        if (!$this->indexExists('attendances', 'attn_tenant_user_date_uq')) {
            DB::statement('ALTER TABLE attendances
                           ADD UNIQUE KEY attn_tenant_user_date_uq (tenant_id, user_id, date)');
        }

        echo "dedupe_and_unique_attendances: removed {$deleted} duplicate row(s).\n";
    }

    public function down(): void
    {
        if ($this->indexExists('attendances', 'attn_tenant_user_date_uq')) {
            DB::statement('ALTER TABLE attendances DROP INDEX attn_tenant_user_date_uq');
        }
        if (!$this->indexExists('attendances', 'attn_tenant_user_date_idx')) {
            DB::statement('ALTER TABLE attendances
                           ADD INDEX attn_tenant_user_date_idx (tenant_id, user_id, date)');
        }
    }

    private function isEmptyVal($v): bool
    {
        return $v === null || $v === '' || $v === 0 || $v === '0' || $v === '0000-00-00 00:00:00';
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
