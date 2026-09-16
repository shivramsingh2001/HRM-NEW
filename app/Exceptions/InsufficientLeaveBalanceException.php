<?php

namespace App\Exceptions;

/**
 * Thrown by LeaveApprovalHandler when a workflow's final approval level would
 * approve a leave whose balance is no longer sufficient (e.g. drained by a
 * concurrent approval between submission and final sign-off). Carries the
 * ApiException taxonomy (400) so LeaveController's catch block can
 * distinguish this business-rule failure from a plain RuntimeException
 * (thrown by ApprovalService for authorization/state errors, which stays a
 * 403) without string-matching the message.
 */
class InsufficientLeaveBalanceException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct('leave.insufficient_balance', $message, 400);
    }
}
