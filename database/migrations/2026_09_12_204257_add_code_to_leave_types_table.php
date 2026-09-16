<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('code')->nullable()->after('status');
        });

        // One-time backfill: tag each tenant's existing "Leave Without Pay"
        // row with code='lwp' so the app can stop identifying it via
        // hardcoded, drifting numeric ids. A leave type is treated as the
        // LWP candidate if it doesn't accrue credit (credit_type='no') or
        // its name is a common LWP synonym.
        $candidates = DB::table('leave_types')
            ->where(function ($q) {
                $q->where('credit_type', 'no')
                    ->orWhereIn(DB::raw('LOWER(name)'), ['lwp', 'unpaid leave', 'leave without pay', 'loss of pay']);
            })
            ->orderBy('tenant_id')
            ->get(['id', 'tenant_id', 'name']);

        $byTenant = $candidates->groupBy('tenant_id');

        foreach ($byTenant as $tenantId => $rows) {
            if ($rows->count() === 1) {
                DB::table('leave_types')->where('id', $rows->first()->id)->update(['code' => 'lwp']);
            } else {
                // Multiple candidates for this tenant — needs manual review
                // rather than a guess; left untagged.
                Log::warning('leave_types LWP backfill: ambiguous or missing candidate for tenant', [
                    'tenant_id' => $tenantId,
                    'candidate_ids' => $rows->pluck('id')->all(),
                    'candidate_names' => $rows->pluck('name')->all(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
