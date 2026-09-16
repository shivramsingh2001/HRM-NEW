<?php

namespace App\Services;

/**
 * Auth-event audit trail. Thin wrapper over the generic AuditLogger (which
 * holds the actual audit_logs + HMAC-signing logic, extracted here so other
 * modules — Leave, Expense, Task, ... — can reuse the same mechanism without
 * going through an auth-specific service). Public method names/signatures
 * are unchanged, so every existing caller keeps working as-is. actor_type is
 * a fixed enum on the table ('super_admin'/'tenant_user'/
 * 'super_admin_impersonating'/'system') — the specific event (login
 * success/failure/lockout/etc.) goes in the free-text `action` column
 * instead, prefixed "auth." to namespace it.
 *
 * Best-effort and non-blocking throughout: a logging failure must never
 * break a login/reset/OTP flow.
 */
class AuthAuditService
{
    public function __construct(private AuditLogger $logger)
    {
    }

    public function logLoginSuccess(?int $userId, ?int $tenantId): void
    {
        $this->logger->record('tenant_user', $userId, $tenantId, 'auth.login_success', 'users', $userId);
    }

    public function logLoginFailed(?int $tenantId, string $identifier): void
    {
        $this->logger->record('system', null, $tenantId, 'auth.login_failed', 'users', null, [], ['identifier' => $identifier]);
    }

    public function logAccountLockout(?int $tenantId, string $identifier): void
    {
        $this->logger->record('system', null, $tenantId, 'auth.account_locked', 'users', null, [], ['identifier' => $identifier]);
    }

    public function logPasswordResetRequested(?int $userId, ?int $tenantId): void
    {
        $this->logger->record('tenant_user', $userId, $tenantId, 'auth.password_reset_requested', 'users', $userId);
    }

    public function logPasswordResetCompleted(?int $userId, ?int $tenantId): void
    {
        $this->logger->record('tenant_user', $userId, $tenantId, 'auth.password_reset_completed', 'users', $userId);
    }

    public function logPasswordChanged(?int $userId, ?int $tenantId): void
    {
        $this->logger->record('tenant_user', $userId, $tenantId, 'auth.password_changed', 'users', $userId);
    }

    public function logOtpRequested(?int $tenantId, string $mobile): void
    {
        $this->logger->record('system', null, $tenantId, 'auth.otp_requested', 'users', null, [], ['mobile_no' => $mobile]);
    }

    public function logOtpVerified(?int $userId, ?int $tenantId): void
    {
        $this->logger->record('tenant_user', $userId, $tenantId, 'auth.otp_login_success', 'users', $userId);
    }
}
