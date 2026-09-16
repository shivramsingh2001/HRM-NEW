<?php

namespace App\Traits;

use App\Models\PayrollAuditLog;

/**
 * Payroll rebuild — Phase 7.
 *
 * Writes an append-only payroll_audit_logs row on every create/update/delete
 * of the model it's attached to. This codebase has no generic audit
 * package/model (confirmed during the Phase 1 design pass), so this is a
 * payroll-scoped equivalent — deliberately applied only to the models that
 * represent an actual payroll decision (salary structures, the catalog,
 * bonuses, arrears, statutory rates, legacy payroll/salary records), not
 * every child/line-item table, to avoid drowning real changes in noise from
 * routine bulk component-sync operations.
 */
trait LogsPayrollActivity
{
    protected static function bootLogsPayrollActivity(): void
    {
        static::created(function ($model) {
            static::writePayrollAuditLog($model, 'created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            static::writePayrollAuditLog($model, 'updated', array_intersect_key($model->getOriginal(), $changes), $changes);
        });

        static::deleted(function ($model) {
            static::writePayrollAuditLog($model, 'deleted', $model->getOriginal(), null);
        });
    }

    protected static function writePayrollAuditLog($model, string $action, ?array $old, ?array $new): void
    {
        $tenantId = $model->tenant_id ?? (app()->bound('current_tenant') ? app('current_tenant')?->id : null);

        if (! $tenantId) {
            return; // no tenant context (e.g. a console/system operation on a global row) — nothing to scope the log to
        }

        try {
            PayrollAuditLog::create([
                'tenant_id' => $tenantId,
                'auditable_type' => static::class,
                'auditable_id' => $model->getKey(),
                'action' => $action,
                'actor_id' => auth()->id(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
                'user_agent' => app()->runningInConsole() ? null : request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break the actual payroll operation it's
            // observing — log the failure and move on.
            \Illuminate\Support\Facades\Log::warning('Payroll audit log write failed', [
                'model' => static::class,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
