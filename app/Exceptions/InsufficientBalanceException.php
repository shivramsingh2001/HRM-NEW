<?php

namespace App\Exceptions;

/**
 * A settlement is larger than the employee's remaining advance balance.
 *
 * Carries the numbers so the approval UI can offer the standard remedy — "deduct what the
 * advance covers and turn the rest into a reimbursement" — instead of a dead-end error.
 * The message is unchanged from before (callers and tests match on it).
 */
class InsufficientBalanceException extends ExpenseException
{
    public function __construct(public readonly int $availableCents, public readonly int $requestedCents)
    {
        parent::__construct(
            'Insufficient advance balance. Available: ' . number_format($availableCents / 100, 2)
            . ', Requested: ' . number_format($requestedCents / 100, 2),
            400
        );
    }

    public function shortfallCents(): int
    {
        return max(0, $this->requestedCents - $this->availableCents);
    }

    /** @return array{code:string, available:float, requested:float, shortfall:float} */
    public function toPayload(): array
    {
        return [
            'code' => 'insufficient_balance',
            'available' => $this->availableCents / 100,
            'requested' => $this->requestedCents / 100,
            'shortfall' => $this->shortfallCents() / 100,
        ];
    }
}
