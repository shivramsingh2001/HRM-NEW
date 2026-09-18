<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Idempotent backfill: one employee_documents row per populated legacy
     * document column on user_basic_details. Safe to re-run — skips a
     * (user_id, document_type) pair that already has a document.
     */
    private const LEGACY_COLUMN_TYPES = [
        'experience_letter' => 'experience_letter',
        'tenth_marksheet' => 'tenth_marksheet',
        'twelfth_marksheet' => 'twelfth_marksheet',
        'highest_qualification_certificate' => 'highest_qualification_certificate',
    ];

    public function up(): void
    {
        $rows = DB::table('user_basic_details')
            ->select('user_id', 'tenant_id', ...array_keys(self::LEGACY_COLUMN_TYPES))
            ->get();

        foreach ($rows as $row) {
            foreach (self::LEGACY_COLUMN_TYPES as $column => $documentType) {
                $path = $row->$column ?? null;
                if (! $path) {
                    continue;
                }

                $exists = DB::table('employee_documents')
                    ->where('user_id', $row->user_id)
                    ->where('document_type', $documentType)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('employee_documents')->insert([
                    'tenant_id' => $row->tenant_id,
                    'user_id' => $row->user_id,
                    'document_type' => $documentType,
                    'file_path' => $path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill — not reversible without risking loss of
        // documents uploaded through the new UI after this ran.
    }
};
