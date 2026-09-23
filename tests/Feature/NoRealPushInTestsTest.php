<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpensePaymentBatch;
use App\Models\User;
use App\Services\ExpensePaymentNotificationService;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\Support\NullFirebaseService;
use Tests\TestCase;

/**
 * Guards the safety net in Tests\TestCase: no test may push to a real device — and
 * exercises the voucher digest (one message per employee + one for the payer), which
 * is only observable through that stub.
 */
class NoRealPushInTestsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_firebase_is_always_the_null_stub_in_tests(): void
    {
        $fb = app(FirebaseService::class);

        $this->assertInstanceOf(NullFirebaseService::class, $fb);
        $this->assertFalse($fb->isReady());
        $this->assertFalse($fb->sendToDevice('any-real-looking-token', 't', 'b')['success']);
        $this->assertCount(1, $fb->sent, 'the attempt is recorded, never sent');
    }

    public function test_a_voucher_notifies_each_employee_once_and_the_payer_once_not_per_payment(): void
    {
        Notification::fake();

        $tenantId = (int) \DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');
        $employees = $tenantId ? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get() : collect();
        $payer = $tenantId ? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'hr'])->first() : null;
        if ($employees->count() < 2 || ! $payer) {
            $this->markTestSkipped('fixture users missing');
        }
        [$alice, $bob] = [$employees[0], $employees[1]];

        // Give both employees a (fake) device token so the push path is exercised on the stub.
        \DB::table('users')->whereIn('id', [$alice->id, $bob->id])->update(['fcm_tokens' => json_encode([['token' => 'fake-token']])]);

        $batch = new ExpensePaymentBatch([
            'voucher_number' => 'PV-TEST', 'payment_mode' => 'upi', 'payment_date' => now()->toDateString(), 'total_amount' => 600, 'line_count' => 3,
        ]);
        $batch->id = 4242;

        $mk = fn (User $u, int $id) => tap(new Expense(['user_id' => $u->id]), fn ($e) => $e->id = $id);
        $expenses = collect([1 => $mk($alice, 1), 2 => $mk($alice, 2), 3 => $mk($bob, 3)]);
        $payments = collect([1, 2, 3])->map(fn ($eid) => tap(new \App\Models\ExpensePayment(['expense_id' => $eid, 'amount' => 200]), fn ($p) => $p->id = $eid));

        $ok = app(ExpensePaymentNotificationService::class)->notifyBatchPaid($batch, $payments, $expenses, $payer);

        $this->assertTrue($ok);
        // 3 payments -> 2 employee digests + 1 payer summary = 3 notifications, NOT 3 x (employee + heads + admins + HR)
        Notification::assertSentToTimes($alice, \App\Notifications\ExpenseBatchPaymentNotification::class, 1);
        Notification::assertSentToTimes($bob, \App\Notifications\ExpenseBatchPaymentNotification::class, 1);
        Notification::assertSentToTimes($payer, \App\Notifications\ExpenseBatchPaymentNotification::class, 1);
        Notification::assertCount(3);

        // Alice's digest covers her two lines: ₹400.00 in 2 payments.
        Notification::assertSentTo($alice, \App\Notifications\ExpenseBatchPaymentNotification::class, function ($n) use ($alice) {
            $data = $n->toArray($alice);

            return $data['line_count'] === 2 && $data['total'] === '400.00' && $data['audience'] === 'employee' && $data['voucher_number'] === 'PV-TEST';
        });

        // The push side: one per employee (never per payment), and it only ever hit the stub.
        $sent = app(FirebaseService::class)->sent;
        $this->assertCount(2, $sent);
        $this->assertSame(['fake-token', 'fake-token'], array_column($sent, 'token'));
    }
}
