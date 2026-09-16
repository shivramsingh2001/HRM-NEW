<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave Management Phase 1 — `is_unpaid` becomes the single authoritative
 * paid/unpaid signal (replacing LeaveService's `code === 'lwp'` check and
 * PerformanceCalculationService's string-match on the type name). Re-run the
 * `code='lwp' -> is_unpaid=true` backfill defensively in case any row drifted
 * since the original 2026_09_12_201126 migration. Additive/idempotent only —
 * never clears is_unpaid on a row that was manually flagged true.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_types', 'is_unpaid') && Schema::hasColumn('leave_types', 'code')) {
            DB::table('leave_types')->where('code', 'lwp')->where('is_unpaid', false)->update(['is_unpaid' => true]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op — this migration only closes data drift, it
        // never introduced a column or a value that should be reverted.
    }
};
