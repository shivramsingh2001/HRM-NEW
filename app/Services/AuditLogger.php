<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic, reusable "who did what when" audit trail. Reuses the exact
 * audit_logs + HMAC-signed sa_audit_signatures pattern already proven in
 * production for auth events (AuthAuditService) and Super Admin
 * impersonation, rather than inventing a per-module mechanism (like
 * Payroll's separate, unsigned payroll_audit_logs table/LogsPayrollActivity
 * trait). AuthAuditService now delegates to this — its public methods and
 * callers are unchanged. Any module (Leave, Expense, Task, ...) can call
 * `record()` directly the same way.
 *
 * Best-effort and non-blocking: a logging failure must never break the
 * business action it's recording.
 */
class AuditLogger
{
    /**
     * @param  array<string,mixed>  $old
     * @param  array<string,mixed>  $new
     */
    public function record(
        string $actorType,
        ?int $actorId,
        ?int $tenantId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $old = [],
        array $new = [],
    ): void {
        try {
            $id = DB::table('audit_logs')->insertGetId([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'tenant_id' => $tenantId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $old ? json_encode($old) : null,
                'new_values' => $new ? json_encode($new) : null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            $key = (string) config('audit.hmac_key', '');
            if ($key !== '' && $id) {
                $r = DB::table('audit_logs')->find($id);
                $canonical = implode('|', [
                    $r->id, $r->actor_type, $r->actor_id ?? '', $r->impersonating_user_id ?? '',
                    $r->tenant_id ?? '', $r->action, $r->entity_type ?? '', $r->entity_id ?? '',
                    $r->old_values ?? '', $r->new_values ?? '', (string) $r->created_at,
                ]);
                DB::table('sa_audit_signatures')->updateOrInsert(
                    ['audit_log_id' => $id],
                    ['hmac' => hash_hmac('sha256', $canonical, $key), 'created_at' => now()],
                );
            }
        } catch (\Throwable $e) {
            Log::error('AuditLogger::record failed: ' . $e->getMessage());
        }
    }
}
