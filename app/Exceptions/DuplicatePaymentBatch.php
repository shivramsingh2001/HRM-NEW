<?php

namespace App\Exceptions;

use App\Models\ExpensePaymentBatch;
use RuntimeException;

/**
 * Thrown INSIDE the posting transaction when the idempotency key turns out to
 * belong to a batch that already committed (a retry / double-click, or a
 * concurrent twin that lost the race). Throwing — rather than returning — rolls
 * the transaction back, so nothing the losing attempt created (e.g. the stand-in
 * expense of a direct payment) can be committed. The service catches it outside
 * the transaction and returns the original batch as a "duplicate" result.
 */
class DuplicatePaymentBatch extends RuntimeException
{
    public function __construct(public readonly ExpensePaymentBatch $batch)
    {
        parent::__construct('Duplicate payment batch (idempotency key already used).');
    }
}
