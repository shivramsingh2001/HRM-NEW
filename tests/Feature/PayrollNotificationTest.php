<?php

namespace Tests\Feature;

use App\Models\MonthlyPayroll;
use App\Models\PayrollBonus;
use App\Models\PayrollEmployeeStructure;
use App\Models\User;
use App\Notifications\CustomNotification;
use App\Services\PayrollNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Payslip / salary / bonus / revision notifications. Shared dev DB — throwaway user, unsaved models.
 */
class PayrollNotificationTest extends TestCase
{
    private ?User $employee = null;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->employee = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Pay Check', 'email' => 'payn.' . uniqid() . '@phpunit.test', 'password' => bcrypt('x'),
            'role' => 'employee', 'status' => 1,
            'tenant_id' => (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->employee?->forceDelete();
        parent::tearDown();
    }

    private function slip(array $attrs = []): MonthlyPayroll
    {
        $slip = new MonthlyPayroll();
        $slip->forceFill(array_merge([
            'id' => 0, 'user_id' => $this->employee->id, 'payroll_month' => '2026-09', 'net_payable' => 45250.5,
        ], $attrs));

        return $slip;
    }

    private function sentMessage(): array
    {
        return Notification::sent($this->employee, CustomNotification::class)->first()->toArray($this->employee);
    }

    public function test_processed_sends_payslip_ready(): void
    {
        $this->assertTrue(app(PayrollNotificationService::class)->notifyStatusChange($this->slip(), 'pending', 'processed'));

        $data = $this->sentMessage();
        $this->assertSame('📄 Payslip Ready', $data['title']);
        $this->assertSame('Your payslip for Sep 2026 is available. Net pay ₹45,250.50.', $data['message']);
        $this->assertSame('payroll', $data['type']);
        $this->assertSame('payslip_ready', $data['action']);
    }

    public function test_paid_sends_salary_credited_with_date_mode_and_reference(): void
    {
        $slip = $this->slip(['payment_date' => '2026-09-30', 'payment_mode' => 'bank_transfer', 'transaction_reference' => 'UTR123']);
        app(PayrollNotificationService::class)->notifyStatusChange($slip, 'processed', 'paid');

        $data = $this->sentMessage();
        $this->assertSame('💰 Salary Credited', $data['title']);
        $this->assertSame('Your salary of ₹45,250.50 for Sep 2026 has been paid on 30 Sep 2026 (Bank Transfer · Ref UTR123). Your payslip is available.', $data['message']);
    }

    public function test_same_status_or_hidden_transitions_send_nothing(): void
    {
        $svc = app(PayrollNotificationService::class);
        $this->assertFalse($svc->notifyStatusChange($this->slip(), 'paid', 'paid'));           // re-save
        $this->assertFalse($svc->notifyStatusChange($this->slip(), 'pending', 'cancelled'));   // never visible
        $this->assertFalse($svc->notifyStatusChange($this->slip(), 'paid', 'processed'));      // already told
        Notification::assertNothingSent();
    }

    public function test_reopen_and_cancel_of_a_visible_payslip_are_announced(): void
    {
        [$title] = PayrollNotificationService::statusMessage($this->slip(), 'paid', 'pending');
        $this->assertSame('✏️ Payslip Under Correction', $title);

        [$title] = PayrollNotificationService::statusMessage($this->slip(), 'processed', 'cancelled');
        $this->assertSame('🚫 Payslip Cancelled', $title);
    }

    public function test_bonus_and_revision_messages(): void
    {
        $svc = app(PayrollNotificationService::class);

        $bonus = new PayrollBonus();
        $bonus->forceFill(['id' => 0, 'user_id' => $this->employee->id, 'name' => 'Diwali Bonus', 'amount' => 5000, 'target_payroll_period_id' => null]);
        $svc->notifyBonusApproved($bonus);
        $this->assertSame('A Diwali Bonus of ₹5,000.00 has been approved for you.', $this->sentMessage()['message']);

        $initial = new PayrollEmployeeStructure();
        $initial->forceFill(['id' => 0, 'user_id' => $this->employee->id, 'revision_type' => 'initial', 'ctc' => 600000]);
        $this->assertFalse($svc->notifyRevisionApplied($initial));

        $raise = new PayrollEmployeeStructure();
        $raise->forceFill(['id' => 0, 'user_id' => $this->employee->id, 'revision_type' => 'increment', 'ctc' => 720000, 'effective_from' => '2026-10-01']);
        $this->assertTrue($svc->notifyRevisionApplied($raise));
        $last = Notification::sent($this->employee, CustomNotification::class)->last()->toArray($this->employee);
        $this->assertSame('Your salary has been revised (Increment) effective 01 Oct 2026. New CTC ₹720,000.00.', $last['message']);
    }
}
