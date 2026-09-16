<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Tier 2 / T2-D — a domain error with a stable machine code for the public API.
 *
 *   throw new ApiException('attendance.period_locked', 'August payroll is closed.', 409);
 */
class ApiException extends RuntimeException
{
    /**
     * @param  string  $errorCode  stable dotted code, e.g. "attendance.period_locked"
     * @param  string  $message    human-readable
     * @param  int     $status     HTTP status
     * @param  array<int|string,mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
