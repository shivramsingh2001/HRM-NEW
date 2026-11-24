<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet visits (site_visits): the stock found at the outlet and the date that
 * stock was last received, filled by the employee when recording / editing a
 * visit. Both optional, so older app versions keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('site_visits', 'current_stock')) {
                $table->decimal('current_stock', 12, 2)->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('site_visits', 'date_of_stock_received')) {
                $table->date('date_of_stock_received')->nullable()->after('current_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropColumn(['current_stock', 'date_of_stock_received']);
        });
    }
};
