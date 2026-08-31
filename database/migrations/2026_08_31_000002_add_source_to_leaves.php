<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance for leaves created as a side effect of manual attendance marking
 * (Feature A). Existing rows default to source = 'self'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'source')) {
                $table->string('source', 20)->default('self')->after('status');
            }
            if (!Schema::hasColumn('leaves', 'applied_by')) {
                $table->unsignedBigInteger('applied_by')->nullable()->after('source');
            }
            if (!Schema::hasColumn('leaves', 'deduct_balance')) {
                $table->boolean('deduct_balance')->default(true)->after('applied_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            foreach (['deduct_balance', 'applied_by', 'source'] as $col) {
                if (Schema::hasColumn('leaves', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
