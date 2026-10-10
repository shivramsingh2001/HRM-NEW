<?php

namespace App\Services\Shift;

use RuntimeException;

/** A shift swap / change request that can't go ahead — the message is shown to the user as-is. */
class ShiftRequestException extends RuntimeException
{
    public function __construct(string $message, public array $errors = [], public bool $stale = false)
    {
        parent::__construct($message);
    }
}
