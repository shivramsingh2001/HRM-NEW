<?php

namespace App\Services\Payroll;

/**
 * Process Payroll refused or failed. Shown to HR as-is: $flash is the session
 * key ('error' or 'warning'), $extra any additional flash data (e.g. the ids of
 * employees that already have a payslip for the month).
 */
class PayrollRunException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $flash = 'error', public readonly array $extra = [])
    {
        parent::__construct($message);
    }
}
