<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * request_types.type_name was unique across ALL companies, so only one company
 * could ever have a "WFH" / "TRAVEL" type. Make it unique per company.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasIndex('request_types', 'request_types_type_name_unique')) {
            DB::statement('ALTER TABLE `request_types` DROP INDEX `request_types_type_name_unique`');
        }
        if (! $this->hasIndex('request_types', 'request_types_tenant_type_name_unique')) {
            DB::statement('ALTER TABLE `request_types` ADD UNIQUE KEY `request_types_tenant_type_name_unique` (`tenant_id`, `type_name`)');
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('request_types', 'request_types_tenant_type_name_unique')) {
            DB::statement('ALTER TABLE `request_types` DROP INDEX `request_types_tenant_type_name_unique`');
        }
        if (! $this->hasIndex('request_types', 'request_types_type_name_unique')) {
            DB::statement('ALTER TABLE `request_types` ADD UNIQUE KEY `request_types_type_name_unique` (`type_name`)');
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)->where('INDEX_NAME', $name)->exists();
    }
};
