<?php

namespace App\Console\Commands;

use App\Services\Expense\ExpenseAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes claims that were soft-deleted more than N days ago (default 90), together with
 * their receipt files and attachment rows.
 *
 * A "deleted" claim is only ever a claim nobody had acted on (pending), so there is no money history to
 * keep. Until this runs the row and its receipts are retained for audit. Status history and audit-log
 * entries are NOT touched. `--dry-run` reports without deleting.
 */
class ExpensePurgeDeleted extends Command
{
    protected $signature = 'expense:purge-deleted {--days=90 : Purge claims deleted at least this many days ago} {--dry-run}';

    protected $description = 'Permanently remove long-deleted expense claims and their receipts';

    public function handle(ExpenseAttachmentService $attachments): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('expenses')->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff)->get(['id', 'file', 'expense_number']);

        foreach ($rows as $row) {
            $extras = DB::table('expense_attachments')->where('expense_id', $row->id)->get(['id', 'file_path']);

            $this->line(sprintf('%s%s (#%d): %d receipt file(s)', $dryRun ? '[dry] ' : '', $row->expense_number, $row->id, ($row->file ? 1 : 0) + $extras->count()));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($row, $extras) {
                DB::table('expense_attachments')->where('expense_id', $row->id)->delete();
                DB::table('expenses')->where('id', $row->id)->delete();
            });

            // Files go only after the rows are gone.
            $attachments->delete($row->file);
            foreach ($extras as $extra) {
                $attachments->delete($extra->file_path);
            }
        }

        $this->info(sprintf('%s%d claim(s) %s.', $dryRun ? '[DRY RUN] ' : '', $rows->count(), $dryRun ? 'would be purged' : 'purged'));

        return self::SUCCESS;
    }
}
