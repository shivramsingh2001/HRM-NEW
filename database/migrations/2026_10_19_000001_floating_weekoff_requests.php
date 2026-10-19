<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floating week-offs planned ahead (plan C2, 2026-10-19): an employee picks a
 * day as one of their monthly floating week-offs; a manager / HR approves. Same
 * table as the nightly allocator's days, told apart by `source` (auto = filled
 * from a missed day, planned = asked for); a planned day goes pending →
 * approved (gets its user_weekoffs row) / rejected / cancelled. Existing rows
 * become source auto / status approved — exactly what they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floating_weekoff_days', function (Blueprint $table) {
            if (! Schema::hasColumn('floating_weekoff_days', 'source')) {
                $table->enum('source', ['auto', 'planned'])->default('auto')->after('user_weekoff_id');
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('approved')->after('source');
                $table->string('reason', 500)->nullable()->after('status');
                $table->unsignedBigInteger('decided_by')->nullable()->after('reason');
                $table->timestamp('decided_at')->nullable()->after('decided_by');
                $table->string('decision_remarks', 500)->nullable()->after('decided_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('floating_weekoff_days', function (Blueprint $table) {
            foreach (['source', 'status', 'reason', 'decided_by', 'decided_at', 'decision_remarks'] as $col) {
                if (Schema::hasColumn('floating_weekoff_days', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
