<?php

namespace App\Exceptions;

/**
 * Thrown by AttendancePunchService::capture() for an `in` punch while a
 * session is already open for that day (no clock-out yet). Applies under
 * both allow_multiple_punches values — multi-punch allows more than one
 * session per day, never two open sessions at once.
 */
class OpenPunchSessionException extends ApiException
{
    public function __construct(string $message = 'You have already checked in for this shift.')
    {
        parent::__construct('attendance.punch_session_open', $message, 422);
    }
}
