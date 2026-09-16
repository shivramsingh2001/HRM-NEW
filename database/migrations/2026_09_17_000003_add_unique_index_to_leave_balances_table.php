<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave Management Phase 1 — leave_balances was only kept unique per
 * (tenant_id, user_id, leave_type_id) by application-level firstOrCreate()
 * calls (LeaveCreditController); a race condition could create duplicate
 * rows. Verified zero existing duplicates before adding this constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexName = 'leave_balances_tenant_user_type_uq';
        $exists = collect(DB::select('SHOW INDEX FROM leave_balances'))
            ->pluck('Key_name')
            ->contains($indexName);

        if (! $exists) {
            Schema::table('leave_balances', function (Blueprint $table) use ($indexName) {
                $table->unique(['tenant_id', 'user_id', 'leave_type_id'], $indexName);
            });
        }
    }

    public function down(): void
    {
        $indexName = 'leave_balances_tenant_user_type_uq';
        $exists = collect(DB::select('SHOW INDEX FROM leave_balances'))
            ->pluck('Key_name')
            ->contains($indexName);

        if ($exists) {
            Schema::table('leave_balances', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }
};
