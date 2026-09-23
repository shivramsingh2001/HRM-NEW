<?php

namespace App\Exceptions;

/**
 * A payment batch that failed validation. Batches are ALL-OR-NOTHING: if any
 * line is invalid nothing is posted, and every problem is reported at once so the
 * user can fix the whole selection in one pass instead of one error at a time.
 */
class ExpenseBatchException extends ExpenseException
{
    /** @param  array<int, array{expense_id:int, expense_number:?string, message:string}>  $lineErrors */
    public function __construct(string $message, private array $lineErrors = [], int $httpStatus = 422)
    {
        parent::__construct($message, $httpStatus);
    }

    /** @return array<int, array{expense_id:int, expense_number:?string, message:string}> */
    public function lineErrors(): array
    {
        return $this->lineErrors;
    }
}
