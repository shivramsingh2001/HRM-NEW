<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * ONE digest per person for a whole payment voucher — instead of the per-payment
 * notification fan-out (employee + reporting heads + every admin/HR, per payment)
 * which would turn a 50-line voucher into hundreds of messages.
 *
 *  - audience 'employee': "₹X paid to you in N payment(s) — voucher PV-…"
 *  - audience 'payer'   : "Voucher PV-… posted: N payment(s), ₹X to M employee(s)"
 *
 * Carries only scalars (safe to queue; no model serialization).
 */
class ExpenseBatchPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $batchId,
        private string $voucherNumber,
        private string $audience,          // 'employee' | 'payer'
        private int $lineCount,
        private string $totalFormatted,
        private string $paymentMode,
        private string $paymentDate,
        private int $employeeCount = 1,
    ) {
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $isEmployee = $this->audience === 'employee';

        return [
            'type' => 'expense_payment',
            'action' => 'batch_paid',
            'audience' => $this->audience,
            'batch_id' => $this->batchId,
            'voucher_number' => $this->voucherNumber,
            'line_count' => $this->lineCount,
            'employee_count' => $this->employeeCount,
            'total' => $this->totalFormatted,
            'payment_mode' => $this->paymentMode,
            'payment_date' => $this->paymentDate,
            'title' => $isEmployee ? '💰 Payment received' : '🧾 Payment voucher posted',
            'message' => $isEmployee
                ? "₹{$this->totalFormatted} has been paid to you in {$this->lineCount} payment(s) — voucher {$this->voucherNumber}."
                : "Voucher {$this->voucherNumber} posted: {$this->lineCount} payment(s), ₹{$this->totalFormatted} to {$this->employeeCount} employee(s).",
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
