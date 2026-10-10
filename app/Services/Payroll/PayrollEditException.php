<?php

namespace App\Services\Payroll;

/**
 * Edit Payroll refused or failed — the message is shown to HR as-is.
 * $withInput: keep the submitted form values on the redirect back.
 */
class PayrollEditException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $withInput = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
