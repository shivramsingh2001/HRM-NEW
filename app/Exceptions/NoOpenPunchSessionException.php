<?php

namespace App\Exceptions;

/**
 * Thrown by AttendancePunchService::capture() for an `out` punch when there is
 * no open session (no prior `in`, or the day's session is already closed).
 */
class NoOpenPunchSessionException extends ApiException
{
    public function __construct(string $message = 'You have not checked in for any active shift.')
    {
        parent::__construct('attendance.no_open_punch_session', $message, 422);
    }
}
