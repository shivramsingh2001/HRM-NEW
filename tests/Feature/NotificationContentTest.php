<?php

namespace Tests\Feature;

use App\Channels\FcmChannel;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Leave;
use App\Models\User;
use App\Notifications\AttendanceNotification;
use App\Notifications\ExpenseStatusChangedNotification;
use App\Notifications\LeaveStatusChangedNotification;
use App\Notifications\LeaveSubmittedNotification;
use App\Notifications\MissedCheckInNotification;
use App\Services\AttendanceNotificationService;
use App\Services\LeaveNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Leave / clock-in / missed check-in / expense notifications say the right
 * thing to the right people. Shared dev DB — throwaway users only.
 */
class NotificationContentTest extends TestCase
{
    private int $tenantId;
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (int) DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        User::withoutGlobalScopes()->whereIn('id', $this->userIds)->forceDelete();
        parent::tearDown();
    }

    private function user(string $name, string $role = 'employee', ?int $tenantId = null): User
    {
        $u = User::withoutGlobalScopes()->forceCreate([
            'name' => $name, 'email' => 'notif.' . uniqid() . '@phpunit.test', 'password' => bcrypt('x'),
            'role' => $role, 'status' => 1, 'tenant_id' => $tenantId ?? $this->tenantId,
        ]);
        $this->userIds[] = $u->id;

        return $u;
    }

    private function leave(User $employee, string $start, string $end, $totalDays = null): Leave
    {
        $leave = new Leave();
        $leave->forceFill([
            'id' => 0, 'tenant_id' => $this->tenantId, 'user_id' => $employee->id, 'leave_id' => 'LV-TEST',
            'start_date' => $start, 'end_date' => $end, 'total_days' => $totalDays, 'status' => 'pending',
        ]);

        return $leave;
    }

    public function test_rejected_leave_is_not_stored_as_approved(): void
    {
        $emp = $this->user('Asha');
        $leave = $this->leave($emp, '2026-10-12', '2026-10-14', 3);

        app(LeaveNotificationService::class)->notifyLeaveRejected($leave, 'Busy week');

        $data = Notification::sent($emp, LeaveStatusChangedNotification::class)->first()->toArray($emp);
        $this->assertSame('❌ Leave Rejected', $data['title']);
        $this->assertStringContainsString('3 days (12 Oct – 14 Oct 2026) has been rejected. Reason: Busy week', $data['message']);
        $this->assertSame('2026-10-14', $data['end_date']);
    }

    public function test_leave_day_count_uses_total_days_and_real_end_date(): void
    {
        $emp = $this->user('Ravi');

        [$title, $body] = LeaveNotificationService::message('approved', $this->leave($emp, '2026-10-12', '2026-10-13'));
        $this->assertSame('✅ Leave Approved', $title);
        $this->assertStringContainsString('2 days', $body);   // was always "1 day(s)"

        [, $body] = LeaveNotificationService::message('approved', $this->leave($emp, '2026-10-12', '2026-10-12', 0.5));
        $this->assertStringContainsString('0.5 days', $body);

        [$title] = LeaveNotificationService::message('cancelled', $this->leave($emp, '2026-10-12', '2026-10-12'));
        $this->assertSame('🚫 Leave Cancelled', $title);
    }

    public function test_leave_request_goes_to_own_company_hr_but_not_to_the_applicant(): void
    {
        $hrApplicant = $this->user('HR Applicant', 'hr');
        $otherHr = $this->user('Other HR', 'hr');
        $foreignTenant = (int) DB::table('tenants')->where('id', '!=', $this->tenantId)->value('id');
        $foreignHr = $foreignTenant ? $this->user('Foreign HR', 'hr', $foreignTenant) : null;

        app(LeaveNotificationService::class)->notifyLeaveSubmitted($this->leave($hrApplicant, '2026-10-20', '2026-10-20'));

        Notification::assertSentTo($otherHr, LeaveSubmittedNotification::class);
        Notification::assertNotSentTo($hrApplicant, LeaveSubmittedNotification::class);
        if ($foreignHr) {
            Notification::assertNotSentTo($foreignHr, LeaveSubmittedNotification::class);
        }
    }

    public function test_second_clock_in_reports_this_punch_time_not_the_first(): void
    {
        $emp = $this->user('Shift Worker');
        $hr = $this->user('HR Watcher', 'hr');
        $attendance = new Attendance();
        $attendance->forceFill(['id' => 0, 'date' => '2026-10-01', 'clock_in' => '2026-10-01 09:00:00', 'tenant_id' => $this->tenantId]);
        $punch = (object) ['punched_at' => '2026-10-01 14:05:00', 'session_seq' => 2, 'address' => 'Office gate'];

        app(AttendanceNotificationService::class)->notifyClockIn($attendance, $emp, $punch);

        $data = Notification::sent($hr, AttendanceNotification::class)->first()->toArray($hr);
        $this->assertSame('Shift Worker clocked in again at 02:05 PM (session 2)', $data['message']);
        Notification::assertNotSentTo($emp, AttendanceNotification::class);
    }

    public function test_missed_check_in_builds_from_the_command_arguments(): void
    {
        $emp = $this->user('Late Comer');
        $row = (object) ['user_shift_id' => null, 'user_id' => $emp->id, 'user_name' => $emp->name,
            'tenant_id' => $this->tenantId, 'start_time' => '09:30:00', 'grace_minutes' => 15];

        // Exactly how CheckMissedCheckIns calls it: an id, not a User.
        $n = new MissedCheckInNotification($emp->id, $row, 15);
        $hr = $this->user('HR Boss', 'hr');

        $this->assertSame(['database', FcmChannel::class], $n->via($hr));
        $this->assertSame("Late Comer hasn't checked in today. Shift started at 09:30 AM (15 min grace).", $n->toArray($hr)['message']);
        $this->assertSame("You haven't checked in today. Your shift started at 09:30 AM (15 min grace).", $n->toArray($emp)['message']);
        $this->assertSame('⚠️ Missed Check-In Alert', $n->toFcm($hr)['title']);
    }

    public function test_expense_status_notification_can_be_stored(): void
    {
        $emp = $this->user('Spender');
        $expense = new Expense();
        $expense->forceFill(['id' => 0, 'amount' => 1250.5, 'expense_number' => 'EXP-T']);

        $approved = (new ExpenseStatusChangedNotification($expense, 'approved'))->toArray($emp);
        $rejected = (new ExpenseStatusChangedNotification($expense, 'rejected', 'No bill'))->toArray($emp);

        $this->assertSame('✅ Expense Approved', $approved['title']);
        $this->assertSame('Your expense of ₹1,250.50 has been approved.', $approved['message']);
        $this->assertSame('❌ Expense Rejected', $rejected['title']);
        $this->assertStringEndsWith('Reason: No bill', $rejected['message']);
    }
}
