<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tier 1 / W5 — discovery of the tables that hold a tenant's data.
 *
 * Any base table with a `tenant_id` column is considered tenant-scoped. The
 * result is cached for the process.
 */
class TenantData
{
    /** @var array<string>|null */
    private static ?array $tables = null;

    /** Never touched by export or purge. */
    private const EXCLUDE = [
        'tenant_purges',      // tombstone — must outlive a hard purge
        'migrations', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
        'sessions', 'password_reset_tokens', 'personal_access_tokens',
    ];

    /**
     * @return array<string>
     */
    public static function tables(): array
    {
        if (self::$tables !== null) {
            return self::$tables;
        }

        $db = DB::getDatabaseName();
        $rows = DB::select(
            "SELECT DISTINCT c.TABLE_NAME AS t
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES tb
                 ON tb.TABLE_SCHEMA = c.TABLE_SCHEMA AND tb.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = ?
                AND c.COLUMN_NAME = 'tenant_id'
                AND tb.TABLE_TYPE = 'BASE TABLE'
              ORDER BY c.TABLE_NAME",
            [$db]
        );

        return self::$tables = array_values(array_filter(
            array_map(fn ($r) => $r->t, $rows),
            fn ($t) => ! in_array($t, self::EXCLUDE, true)
        ));
    }
}
