<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Shift;
use Illuminate\Support\Facades\Auth;

/**
 * Shift change log + roster-change notifications for the shift controllers (uses the
 * controller's $changeRecorder). Moved out of ShiftController unchanged
 * (code-quality plan, Phase 4).
 */
trait RecordsShiftChanges
{
    /**
     * Shift change log for assignShift(): its three paths (main / set /
     * additional) each commit on their own, so the "before" snapshot is taken
     * once up front and written by flushChangeLog() right before each commit.
     */
    private ?array $pendingChangeLog = null;

    private function startChangeLog(int $tenantId, array $userIds, string $from, string $to, array $context): void
    {
        $this->pendingChangeLog = [$tenantId, $userIds, $from, $to, $context, $this->changeRecorder->snapshot($tenantId, $userIds, $from, $to)];
    }

    private function flushChangeLog(): void
    {
        if (! $this->pendingChangeLog) {
            return;
        }
        [$tenantId, $userIds, $from, $to, $context, $before] = $this->pendingChangeLog;
        $this->pendingChangeLog = null;
        $this->changeRecorder->record($tenantId, $before, $this->changeRecorder->snapshot($tenantId, $userIds, $from, $to), $context);
        $this->notifyRosterChange($tenantId);
    }

    /**
     * Tell each affected employee once about the changes logged so far in
     * this request (sent after commit; nothing is sent on rollback).
     */
    private function notifyRosterChange(int $tenantId): void
    {
        $rows = $this->changeRecorder->lastRows;
        $this->changeRecorder->lastRows = [];
        app(\App\Services\Shift\ShiftNotificationService::class)->rosterChanged($tenantId, $rows, Auth::id());
    }
}
