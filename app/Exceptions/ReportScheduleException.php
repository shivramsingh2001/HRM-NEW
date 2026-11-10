<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A scheduled report could not be produced or sent for a reason that is safe
 * to show the schedule owner (e.g. "module not in plan", "owner deactivated").
 * `$pause` = the schedule can't succeed until someone fixes it, so it is paused.
 */
class ReportScheduleException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $pause = false)
    {
        parent::__construct($message);
    }
}
