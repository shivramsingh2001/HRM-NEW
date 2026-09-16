<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // Explicit total-day count for the whole application. leave_count
            // is kept in sync with the same value on the single row so
            // existing sum('leave_count') consumers keep working unchanged.
            $table->decimal('total_days', 5, 2)->nullable()->after('leave_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('total_days');
        });
    }
};
