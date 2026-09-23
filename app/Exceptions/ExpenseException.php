<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule failure in the Expense module whose message is safe to show
 * to the caller (unlike a raw \Throwable, whose message must never be echoed
 * back). Thrown from inside a DB::transaction() closure so the transaction
 * rolls back, then mapped to a JSON response by the controller.
 */
class ExpenseException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 400)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
