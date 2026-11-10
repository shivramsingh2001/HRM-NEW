<?php

namespace Tests\Feature;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\ReportScheduleRun;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\Reports\ReportScheduleRunner;
use App\Services\Reports\ReportScheduleTiming;
use App\Support\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Scheduled Reports (2026-11-10). Runs against the shared dev DB (no
 * RefreshDatabase) and removes every schedule it creates in tearDown.
 * Mail is faked and child processes are faked — nothing is really sent.
 */
class ReportScheduleTest extends TestCase
{
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('report_schedules')) {
            $this->markTestSkipped('report_schedules table not migrated on this DB.');
        }
        // An admin whose company has Reports + Leave (leave.balance is the quick test report).
        foreach (User::query()->whereNotNull('tenant_id')->where('role', 'admin')->where('status', 1)->orderBy('id')->get() as $u) {
            $tenant = Tenant::query()->find($u->tenant_id);
            if (! $tenant || ! in_array($tenant->status, ['active', 'trial'], true)) {
                continue;
            }
            app()->instance('current_tenant', $tenant);
            $f = app(FeatureService::class);
            app()->forgetScopedInstances();
            if ($f->enabledForCurrentTenant('daily_reports') && $f->enabledForCurrentTenant('leave_management')) {
                $this->admin = $u;
                break;
            }
        }
        app()->forgetInstance('current_tenant');
        if (! $this->admin) {
            $this->markTestSkipped('No active admin with Reports + Leave on this DB.');
        }
    }

    protected function tearDown(): void
    {
        ReportSchedule::query()->where('name', 'like', 'PHPUnit schedule%')->delete();
        parent::tearDown();
    }

    private function schedule(array $extra = []): ReportSchedule
    {
        return ReportSchedule::query()->create($extra + [
            'tenant_id' => $this->admin->tenant_id,
            'created_by' => $this->admin->id,
            'name' => 'PHPUnit schedule '.uniqid(),
            'report_key' => 'leave.balance',
            'frequency' => 'daily',
            'send_time' => '09:00',
            'recipients' => ['one@example.com', 'two@example.com'],
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- timing

    public function test_next_run_daily_weekly_monthly(): void
    {
        $s = new ReportSchedule(['frequency' => 'daily', 'send_time' => '09:00']);
        $mon8am = CarbonImmutable::parse('2026-11-09 08:00'); // Monday
        $this->assertSame('2026-11-09 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-10 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am->setTime(9, 0))->format('Y-m-d H:i'));

        $s->weekdays = [1, 3]; // Mon + Wed only
        $this->assertSame('2026-11-11 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am->setTime(10, 0))->format('Y-m-d H:i'));

        $s->frequency = 'weekly';
        $s->weekdays = [5]; // Friday
        $this->assertSame('2026-11-13 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am)->format('Y-m-d H:i'));

        $s->frequency = 'monthly';
        $s->day_of_month = 0; // last day
        $this->assertSame('2026-11-30 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am)->format('Y-m-d H:i'));
        $this->assertSame('2027-02-28 09:00', ReportScheduleTiming::nextRunAfter($s, CarbonImmutable::parse('2027-01-31 10:00'))->format('Y-m-d H:i'));
        $s->day_of_month = 1;
        $this->assertSame('2026-12-01 09:00', ReportScheduleTiming::nextRunAfter($s, $mon8am)->format('Y-m-d H:i'));
    }

    public function test_period_query_resolves_relative_dates(): void
    {
        $now = CarbonImmutable::parse('2026-11-10 09:00');
        $y = ReportCatalog::periodQuery('yesterday', $now);
        $this->assertSame('2026-11-09', $y['query']['date']);
        $this->assertSame('2026-11-09', $y['query']['start_date']);
        $this->assertSame('2026-11', $y['query']['month']);

        $lm = ReportCatalog::periodQuery('last_month', $now);
        $this->assertSame('2026-10', $lm['query']['month']);
        $this->assertSame('2026-10-01', $lm['query']['from_date']);
        $this->assertSame('2026-10-31', $lm['query']['to_date']);
        $this->assertSame('October 2026', $lm['label']);

        $this->assertSame([], ReportCatalog::periodQuery(null, $now)['query']);
    }

    // ---------------------------------------------------------------- web

    public function test_admin_creates_a_schedule_and_sees_it_listed(): void
    {
        $name = 'PHPUnit schedule '.uniqid();
        $this->actingAs($this->admin)->postJson(route('report.schedules.store'), [
            'name' => $name,
            'report_key' => 'leave.balance',
            'frequency' => 'weekly',
            'weekly_day' => 1,
            'send_time' => '08:30',
            'recipients_text' => "Boss@Example.com; accounts@example.com\nboss@example.com",
            'send_to_me' => 1,
        ])->assertOk()->assertJsonPath('status', true);

        $s = ReportSchedule::query()->where('name', $name)->firstOrFail();
        $expected = array_values(array_unique(['boss@example.com', 'accounts@example.com', strtolower($this->admin->email)]));
        $this->assertSame($expected, $s->recipients);
        $this->assertSame([1], $s->weekdays);
        $this->assertNull($s->period); // Leave Balance has no date
        $this->assertTrue($s->next_run_at->isMonday());
        $this->assertSame('08:30', $s->next_run_at->format('H:i'));

        $this->actingAs($this->admin)->get(route('report.schedules.index'))->assertOk()->assertSee($name);
    }

    public function test_bad_email_and_unknown_report_are_refused(): void
    {
        $this->actingAs($this->admin)->postJson(route('report.schedules.store'), [
            'name' => 'PHPUnit schedule bad',
            'report_key' => 'no.such.report',
            'frequency' => 'daily',
            'send_time' => '09:00',
            'recipients_text' => 'ok@example.com, not-an-email',
        ])->assertStatus(422)->assertJsonValidationErrors(['report_key', 'recipients_text']);
    }

    public function test_employee_cannot_open_scheduled_reports(): void
    {
        $employee = User::query()->where('tenant_id', $this->admin->tenant_id)->where('role', 'employee')->where('status', 1)->first();
        if (! $employee) {
            $this->markTestSkipped('No employee in the test company.');
        }
        $res = $this->actingAs($employee)->get(route('report.schedules.index'));
        $this->assertContains($res->status(), [302, 403]);
    }

    // ---------------------------------------------------------------- runner

    public function test_runner_emails_each_recipient_with_the_export_attached(): void
    {
        Mail::fake();
        $s = $this->schedule();

        $run = app(ReportScheduleRunner::class)->run($s, 'manual');

        $this->assertSame('sent', $run->status, (string) $run->error);
        $this->assertStringEndsWith('.csv', (string) $run->file_name);
        $this->assertGreaterThan(0, $run->file_size);
        Mail::assertSent(ScheduledReportMail::class, 2);
        Mail::assertSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('one@example.com') && $m->fileContent !== null && $m->reportLabel === 'Leave Balance Report');
        $this->assertSame('sent', $s->fresh()->last_status);
        $this->assertSame(1, ReportScheduleRun::query()->where('report_schedule_id', $s->id)->count());
    }

    public function test_runner_pauses_when_the_owner_is_gone(): void
    {
        Mail::fake();
        $s = $this->schedule(['created_by' => 999999999]);

        $run = app(ReportScheduleRunner::class)->run($s, 'schedule');

        $this->assertSame('failed', $run->status);
        Mail::assertNothingSent();
        $s->refresh();
        $this->assertFalse($s->is_active);
        $this->assertNull($s->next_run_at);
        $this->assertStringContainsString('no longer active', (string) $s->paused_reason);
    }

    // ---------------------------------------------------------------- dispatcher

    public function test_dispatcher_claims_due_and_requested_schedules_once(): void
    {
        Process::fake();
        $due = $this->schedule(['next_run_at' => now()->subMinutes(3)]);
        $asked = $this->schedule(['next_run_at' => now()->addDay(), 'run_requested_at' => now()]);
        $later = $this->schedule(['next_run_at' => now()->addHour()]);

        $this->artisan('reports:send-scheduled')->assertSuccessful();

        Process::assertRan(fn ($p) => in_array((string) $due->id, (array) $p->command, true) && in_array('--trigger=schedule', (array) $p->command, true));
        Process::assertRan(fn ($p) => in_array((string) $asked->id, (array) $p->command, true) && in_array('--trigger=manual', (array) $p->command, true));
        Process::assertDidntRun(fn ($p) => in_array((string) $later->id, (array) $p->command, true));

        $this->assertTrue($due->fresh()->next_run_at->isFuture(), 'due schedule moved to its next slot');
        $this->assertNull($asked->fresh()->run_requested_at, 'send-now request consumed');

        // A second tick right away finds nothing new for these (the fake keeps the first tick's record).
        $this->artisan('reports:send-scheduled')->assertSuccessful();
        Process::assertRanTimes(fn ($p) => in_array((string) $due->id, (array) $p->command, true), 1);
        Process::assertRanTimes(fn ($p) => in_array((string) $asked->id, (array) $p->command, true), 1);
    }
}
