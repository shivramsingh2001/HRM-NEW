<?php

namespace App\Console\Commands;

use App\Services\Expense\ExpenseAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moves legacy expense receipts out of the web-served public/uploads/expense
 * folder into private storage, re-naming them from their REAL content type
 * (never the stored extension), and repoints expenses.file at the new path.
 *
 * Anything that is missing, unreadable, or not a JPG/PNG/PDF by content is
 * left untouched and reported for manual review — it is never moved or run.
 *
 * Safe to re-run (already-migrated rows no longer match `uploads/expense/%`).
 */
class ExpenseMigrateUploads extends Command
{
    protected $signature = 'expense:migrate-uploads {--dry-run : Report what would happen without moving or changing anything}';

    protected $description = 'Move legacy public expense receipts to private storage and repoint expenses.file';

    /** content type => extension */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function handle(ExpenseAttachmentService $attachments): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Every legacy shape the old code wrote over time (uploads/expense/file/…,
        // expenses/2026/05/…, expenses/…): anything that is NOT the private layout.
        $rows = DB::table('expenses')
            ->whereNotNull('file')->where('file', '<>', '')
            ->whereRaw("file NOT REGEXP '^expense/[0-9]+/[0-9]{4}/[^/]+$'")
            ->select('id', 'tenant_id', 'file')
            ->orderBy('id')
            ->get();

        $moved = 0;
        $flagged = 0;

        foreach ($rows as $row) {
            $full = $attachments->legacyFullPath($row->file);

            if ($full === null) {
                $this->warn("#{$row->id}: file is missing, outside public/, or not a receipt type ({$row->file}) — left untouched");
                $flagged++;
                continue;
            }

            $mime = (string) (function_exists('mime_content_type') ? mime_content_type($full) : '');
            $ext = self::MIME_TO_EXT[$mime] ?? null;

            if ($ext === null) {
                $this->warn("#{$row->id}: content type '{$mime}' is not allowed ({$row->file}) — left in place for manual review");
                $flagged++;
                continue;
            }

            $target = 'expense/' . ($row->tenant_id ?: 0) . '/' . date('Y') . '/' . Str::uuid() . '.' . $ext;

            if ($dryRun) {
                $this->line("#{$row->id}: would move {$row->file} -> {$target}");
                $moved++;
                continue;
            }

            $stream = fopen($full, 'rb');
            $ok = Storage::disk(ExpenseAttachmentService::DISK)->put($target, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $ok) {
                $this->error("#{$row->id}: could not write {$target} — skipped");
                $flagged++;
                continue;
            }

            // Repoint first, delete the original only after the DB row is safe.
            DB::table('expenses')->where('id', $row->id)->update(['file' => $target]);
            @unlink($full);

            $this->line("#{$row->id}: moved -> {$target}");
            $moved++;
        }

        $this->info(sprintf('%s%d moved, %d flagged for review, %d total legacy receipt(s).', $dryRun ? '[DRY RUN] ' : '', $moved, $flagged, $rows->count()));

        return self::SUCCESS;
    }
}
