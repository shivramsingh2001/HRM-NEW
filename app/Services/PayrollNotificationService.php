<?php

namespace App\Services;

use App\Models\MonthlyPayroll;
use App\Models\PayrollBonus;
use App\Models\PayrollEmployeeStructure;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Support\Facades\Log;

/**
 * Employee-facing payroll notifications (push + in-app): payslip ready,
 * salary credited, payslip reopened / cancelled, bonus approved and salary
 * revision applied. Only a real status change notifies — re-saving the same
 * status sends nothing.
 */
class PayrollNotificationService
{
    /** Statuses in which the employee can see the payslip (web + mobile). */
    private const VISIBLE = ['processed', 'paid'];

    public function __construct(private FirebaseService $firebaseService)
    {
    }

    /** payment_status changed from $old to $new (single, bulk, or reopen). */
    public function notifyStatusChange(MonthlyPayroll $payroll, ?string $old, string $new): bool
    {
        $message = self::statusMessage($payroll, $old, $new);
        if (! $message) {
            return false;
        }

        $employee = User::withoutGlobalScopes()->find($payroll->user_id);
        if (! $employee) {
            return false;
        }

        [$title, $body, $action] = $message;

        return $this->send($employee, $title, $body, [
            'type' => 'payroll',
            'action' => $action,
            'monthly_payroll_id' => $payroll->id,
            'payroll_month' => $payroll->payroll_month,
            'payment_status' => $new,
            'net_payable' => (string) $payroll->net_payable,
        ]);
    }

    /** @return array{0:string,1:string,2:string}|null [title, body, action] */
    public static function statusMessage(MonthlyPayroll $payroll, ?string $old, string $new): ?array
    {
        $old = $old ? strtolower($old) : null;
        $new = strtolower($new);
        if ($old === $new) {
            return null;
        }

        $month = self::monthLabel($payroll->payroll_month);
        $net = '₹' . number_format((float) $payroll->net_payable, 2);

        if ($new === 'paid') {
            $when = $payroll->payment_date ? ' on ' . \Carbon\Carbon::parse($payroll->payment_date)->format('d M Y') : '';
            $via = trim(implode(' · ', array_filter([
                $payroll->payment_mode ? ucwords(str_replace('_', ' ', $payroll->payment_mode)) : null,
                $payroll->transaction_reference ? 'Ref ' . $payroll->transaction_reference : null,
            ])));

            return ['💰 Salary Credited',
                "Your salary of {$net} for {$month} has been paid{$when}" . ($via ? " ({$via})" : '') . '. Your payslip is available.',
                'salary_paid'];
        }

        if ($new === 'processed' && $old !== 'paid') {
            return ['📄 Payslip Ready', "Your payslip for {$month} is available. Net pay {$net}.", 'payslip_ready'];
        }

        // The employee could already see it — tell them it is no longer final.
        if (in_array($old, self::VISIBLE, true) && $new === 'pending') {
            return ['✏️ Payslip Under Correction',
                "Your payslip for {$month} has been reopened for correction. You will be notified once it is updated.",
                'payslip_reopened'];
        }

        if (in_array($old, self::VISIBLE, true) && $new === 'cancelled') {
            return ['🚫 Payslip Cancelled', "Your payslip for {$month} has been cancelled. Please contact HR for details.", 'payslip_cancelled'];
        }

        return null;
    }

    public function notifyBonusApproved(PayrollBonus $bonus): bool
    {
        $employee = User::withoutGlobalScopes()->find($bonus->user_id);
        if (! $employee) {
            return false;
        }

        $period = \Illuminate\Support\Facades\DB::table('payroll_periods')->where('id', $bonus->target_payroll_period_id)->first();
        $month = $period && ! empty($period->year_month) ? ' It will be paid with your ' . self::monthLabel($period->year_month) . ' salary.' : '';

        return $this->send($employee, '🎉 Bonus Approved',
            'A ' . $bonus->name . ' of ₹' . number_format((float) $bonus->amount, 2) . ' has been approved for you.' . $month,
            ['type' => 'payroll', 'action' => 'bonus_approved', 'payroll_bonus_id' => $bonus->id, 'amount' => (string) $bonus->amount]);
    }

    /** A salary revision went live. A first ("initial") structure is not announced. */
    public function notifyRevisionApplied(PayrollEmployeeStructure $structure): bool
    {
        if ($structure->revision_type === 'initial') {
            return false;
        }
        $employee = User::withoutGlobalScopes()->find($structure->user_id);
        if (! $employee) {
            return false;
        }

        $type = ucfirst((string) $structure->revision_type);
        $from = $structure->effective_from ? \Carbon\Carbon::parse($structure->effective_from)->format('d M Y') : null;

        return $this->send($employee, '📈 Salary Revised',
            "Your salary has been revised ({$type})" . ($from ? " effective {$from}" : '') . '. New CTC ₹' . number_format((float) $structure->ctc, 2) . '.',
            ['type' => 'payroll', 'action' => 'salary_revised', 'structure_id' => $structure->id, 'revision_type' => $structure->revision_type]);
    }

    public static function monthLabel(?string $yearMonth): string
    {
        if (! $yearMonth || ! preg_match('/^\d{4}-\d{2}/', $yearMonth)) {
            return (string) $yearMonth;
        }

        return \Carbon\Carbon::createFromFormat('Y-m-d', substr($yearMonth, 0, 7) . '-01')->format('M Y');
    }

    private function send(User $user, string $title, string $body, array $data): bool
    {
        try {
            $user->notify(new CustomNotification($title, $body, $data));

            $tokens = $user->fcm_tokens ?? [];
            if (is_string($tokens)) {
                $tokens = json_decode($tokens, true) ?: [];
            }
            foreach ((array) $tokens as $t) {
                $token = is_array($t) ? ($t['token'] ?? '') : $t;
                if ($token) {
                    $this->firebaseService->sendToDevice($token, $title, $body, array_map('strval', $data));
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Payroll notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
