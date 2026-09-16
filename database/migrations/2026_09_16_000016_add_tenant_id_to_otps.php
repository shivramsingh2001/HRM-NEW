<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * otps had no tenant_id at all — OTP issuance/verification was keyed purely
 * by mobile_no. If the same phone number is ever a contact in two different
 * tenants, that's not cross-tenant-safe at the data layer on its own (it
 * relied entirely on the downstream User lookup being correctly tenant-
 * scoped). Backfills from the matching users.contact where resolvable;
 * existing unresolvable rows (e.g. an OTP for a now-deleted user, or one
 * that matches multiple tenants) are left null rather than guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('otps') || Schema::hasColumn('otps', 'tenant_id')) {
            return;
        }

        Schema::table('otps', function (Blueprint $table) {
            $table->integer('tenant_id')->nullable()->after('mobile_no')->index();
        });

        // Backfill only where the contact number resolves to exactly one
        // tenant — an ambiguous (multi-tenant) match is left null rather
        // than guessed, since guessing wrong here is worse than leaving it
        // unscoped for this one historical row.
        // MAX(u.tenant_id), not bare u.tenant_id — MySQL's ONLY_FULL_GROUP_BY
        // mode (the default) rejects selecting a non-aggregated, non-grouped
        // column even when HAVING COUNT(DISTINCT ...) = 1 guarantees it's
        // functionally single-valued per group; wrapping it in MAX() is the
        // standard way to tell MySQL "yes, I know this is safe here."
        DB::statement('
            UPDATE otps o
            SET o.tenant_id = (
                SELECT MAX(u.tenant_id) FROM users u
                WHERE u.contact = o.mobile_no
                GROUP BY u.contact
                HAVING COUNT(DISTINCT u.tenant_id) = 1
                LIMIT 1
            )
            WHERE o.tenant_id IS NULL
        ');
    }

    public function down(): void
    {
        if (Schema::hasTable('otps') && Schema::hasColumn('otps', 'tenant_id')) {
            Schema::table('otps', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
