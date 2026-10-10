<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomShiftsEnabled;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftChangeLog;
use App\Models\ShiftRequest;
use App\Models\ShiftRequestSetting;
use App\Models\User;
use App\Models\UserShift;
use App\Services\Shift\ShiftAssignmentService;
use App\Services\Shift\ShiftMaterializer;
use App\Services\Shift\ShiftRequestException;
use App\Services\Shift\ShiftRequestService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shift Requests module: day overrides that survive a rebuild, the change
 * log, direct swaps / changes, employee swap + change requests (colleague →
 * approval → applied), stale / leave / scope guards, revert and expiry.
 * Runs on the dev DB — everything it creates is removed in tearDown().
 */
class ShiftRequestsTest extends TestCase
{
    private User $admin;
    private User $a;
    private User $b;
    private User $manager;
    private int $tenantId;
    private string $tag;
    private int $originalCustom;
    private ?array $originalSettings;
    private array $originalPolicies = [];
    private Shift $morning;
    private Shift $night;
    private Shift $evening;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = 'PHPUnit SRQ ' . uniqid();
        $this->withoutMiddleware([EnsureCustomShiftsEnabled::class, EnsureFeatureEnabled::class]);

        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        $this->tenantId = (int) $this->admin->tenant_id;
        $this->originalCustom = (int) DB::table('tenants')->where('id', $this->tenantId)->value('custom_shifts_enabled');
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => 1]);
        $row = DB::table('shift_request_settings')->where('tenant_id', $this->tenantId)->first();
        $this->originalSettings = $row ? (array) $row : null;
        $this->originalPolicies = DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->get()->map(fn ($r) => (array) $r)->all();
        DB::table('shift_request_settings')->where('tenant_id', $this->tenantId)->delete();

        $this->a = $this->user('Alpha', 'employee');
        $this->b = $this->user('Bravo', 'employee');
        $this->manager = $this->user('Manager', 'manager');

        $this->morning = $this->shift('Morning', '06:00', '14:00');
        $this->evening = $this->shift('Evening', '14:00', '22:00');
        $this->night = $this->shift('Night', '22:00', '06:00');
    }

    protected function tearDown(): void
    {
        $ids = [$this->a->id, $this->b->id, $this->manager->id];
        $reqIds = ShiftRequest::withoutGlobalScopes()->whereIn('requester_id', $ids)->pluck('id');
        DB::table('shift_request_events')->whereIn('shift_request_id', $reqIds)->delete();
        DB::table('shift_request_items')->whereIn('shift_request_id', $reqIds)->delete();
        DB::table('audit_logs')->where('entity_type', 'shift_request')->whereIn('entity_id', $reqIds)->delete();
        ShiftRequest::withoutGlobalScopes()->whereIn('id', $reqIds)->delete();
        DB::table('shift_change_logs')->whereIn('user_id', $ids)->delete();
        UserShift::withoutGlobalScopes()->whereIn('user_id', $ids)->delete();
        ShiftAssignment::withoutGlobalScopes()->whereIn('user_id', $ids)->delete();
        DB::table('leaves')->whereIn('user_id', $ids)->delete();
        DB::table('user_reporting_heads')->whereIn('user_id', $ids)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $ids)->delete();
        Shift::withoutGlobalScopes()->where('name', 'like', $this->tag . '%')->delete();
        User::withoutGlobalScopes()->whereIn('id', $ids)->forceDelete();
        DB::table('shift_request_settings')->where('tenant_id', $this->tenantId)->delete();
        if ($this->originalSettings) {
            DB::table('shift_request_settings')->insert($this->originalSettings);
        }
        DB::table('tenants')->where('id', $this->tenantId)->update(['custom_shifts_enabled' => $this->originalCustom]);
        DB::table('attendance_policies')->where('tenant_id', $this->tenantId)->delete();
        foreach ($this->originalPolicies as $p) {
            DB::table('attendance_policies')->insert($p);
        }
        app(\App\Services\Attendance\PolicyResolver::class)->forget();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function user(string $name, string $role): User
    {
        return User::withoutGlobalScopes()->forceCreate([
            'name' => $this->tag . ' ' . $name,
            'email' => 'srq.' . uniqid() . '@phpunit.test',
            'employee_id' => 'SR' . random_int(100000, 999999),
            'password' => bcrypt('secret'),
            'role' => $role,
            'status' => 1,
            'tenant_id' => $this->tenantId,
        ]);
    }

    private function shift(string $name, string $start, string $end): Shift
    {
        return Shift::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'name' => $this->tag . ' ' . $name,
            'start_time' => $start,
            'end_time' => $end,
            'is_overnight' => $end <= $start,
            'total_hours' => 8,
            'status' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    private function day(int $offset = 5): string
    {
        return Carbon::today()->addDays($offset)->toDateString();
    }

    /** Permanent assignment from today (cache materialized to the horizon). */
    private function permanent(User $u, Shift $s): ShiftAssignment
    {
        return app(ShiftAssignmentService::class)->assignPermanent($this->tenantId, $u->id, $s->id, Carbon::today(), $this->admin->id)['assignment'];
    }

    private function shiftOn(User $u, string $date): ?int
    {
        $id = UserShift::withoutGlobalScopes()->where('user_id', $u->id)->where('date', $date)->where('is_additional', 0)->value('shift_id');

        return $id ? (int) $id : null;
    }

    private function service(): ShiftRequestService
    {
        return app(ShiftRequestService::class);
    }

    private function fresh(User $u): User
    {
        return User::withoutGlobalScopes()->find($u->id);
    }

    // ------------------------------------------------------------------ Phase 1

    public function test_roster_day_edit_needs_a_reason_survives_a_rebuild_and_is_logged(): void
    {
        $this->permanent($this->a, $this->morning);
        $date = $this->day();
        $row = UserShift::withoutGlobalScopes()->where('user_id', $this->a->id)->where('date', $date)->firstOrFail();

        $this->actingAs($this->admin)->postJson(route('shift.update-user-shift'), ['user_shift_id' => $row->id, 'shift_id' => $this->evening->id])
            ->assertStatus(422);

        $this->actingAs($this->admin)->postJson(route('shift.update-user-shift'), ['user_shift_id' => $row->id, 'shift_id' => $this->evening->id, 'reason' => 'Cover for training'])
            ->assertOk()->assertJson(['status' => true]);
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));

        // The old direct edit was wiped by any rebuild of the cache; the override is re-applied.
        app(ShiftMaterializer::class)->regenerateFrom($this->tenantId, $this->a->id, Carbon::today());
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));
        $this->assertSame($this->morning->id, $this->shiftOn($this->a, $this->day(6)));

        $log = ShiftChangeLog::withoutGlobalScopes()->where('user_id', $this->a->id)->where('source', 'roster_edit')->firstOrFail();
        $this->assertSame([$this->morning->id, $this->evening->id, 'change', 'Cover for training', (int) $this->admin->id],
            [(int) $log->from_shift_id, (int) $log->to_shift_id, $log->change_type, $log->reason, (int) $log->actor_id]);
        $this->assertSame($date, $log->date->toDateString());

        // History endpoint returns it.
        $this->actingAs($this->admin)->getJson(route('shift.change-log', ['user_id' => $this->a->id, 'date' => $date]))
            ->assertOk()->assertJsonPath('data.0.reason', 'Cover for training');
    }

    public function test_assigning_a_shift_logs_each_changed_day_once(): void
    {
        $date = $this->day(7);
        $this->actingAs($this->admin)->postJson(route('shift.assign'), [
            'assign_type' => 'user', 'user_ids' => [$this->a->id], 'type' => 'flexible',
            'shift_id' => $this->night->id, 'start_date' => $date, 'end_date' => $this->day(8),
        ])->assertOk();

        $logs = ShiftChangeLog::withoutGlobalScopes()->where('user_id', $this->a->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->change_type === 'assign' && $l->source === 'roster_assign' && (int) $l->to_shift_id === $this->night->id));
    }

    // ------------------------------------------------------------------ Phase 2

    public function test_admin_direct_swap_exchanges_shifts_and_is_traceable(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $date = $this->day();

        $res = $this->actingAs($this->admin)->postJson(route('shift.requests.direct-swap'), [
            'user_a' => $this->a->id, 'user_b' => $this->b->id, 'start_date' => $date, 'reason' => 'Doctor visit',
        ])->assertOk()->assertJson(['status' => true]);

        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));
        $this->assertSame($this->morning->id, $this->shiftOn($this->b, $date));

        $req = ShiftRequest::withoutGlobalScopes()->findOrFail($res->json('data.id'));
        $this->assertSame(['swap', 'direct', 'approved'], [$req->type, $req->mode, $req->status]);
        $this->assertMatchesRegularExpression('/^SR-\d{4}-\d{6}$/', $req->request_no);
        $this->assertSame(['created_direct', 'applied'], $req->events()->pluck('event')->all());

        $logs = ShiftChangeLog::withoutGlobalScopes()->where('shift_request_id', $req->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn ($l) => $l->change_type === 'swap' && $l->source === 'admin_swap'));

        // user_shifts → assignment → request chain
        $assignmentId = UserShift::withoutGlobalScopes()->where('user_id', $this->a->id)->where('date', $date)->value('shift_assignment_id');
        $this->assertSame($req->id, (int) ShiftAssignment::withoutGlobalScopes()->find($assignmentId)->shift_request_id);

        // Both employees were told.
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->a->id)->where('data', 'like', '%shift_changed%')->count());
    }

    public function test_manager_can_only_swap_their_own_reportees(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $payload = ['user_a' => $this->a->id, 'user_b' => $this->b->id, 'start_date' => $this->day(), 'reason' => 'x'];

        $this->actingAs($this->manager)->postJson(route('shift.requests.direct-swap'), $payload)->assertStatus(422);

        foreach ([$this->a, $this->b] as $u) {
            DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'reporting_head_id' => $this->manager->id, 'is_primary' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($this->manager)->postJson(route('shift.requests.direct-swap'), $payload)->assertOk();
    }

    public function test_direct_change_refuses_a_day_with_leave(): void
    {
        $this->permanent($this->a, $this->morning);
        $date = $this->day();
        DB::table('leaves')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->a->id, 'leave_type' => 1, 'start_date' => $date, 'end_date' => $date, 'status' => 'approved', 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(ShiftRequestException::class);
        $this->expectExceptionMessage('leave');
        $this->service()->directChange($this->admin, $this->a->id, [$date], $this->night->id, 'x');
    }

    // ------------------------------------------------------------------ Phase 3

    public function test_employee_swap_request_full_flow_then_revert(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $date = $this->day();

        $req = $this->service()->createSwapRequest($this->fresh($this->a), $this->b->id, [$date], 'Family function');
        $this->assertSame(ShiftRequest::STATUS_PENDING_PEER, $req->status);
        $this->assertSame($this->morning->id, $this->shiftOn($this->a, $date)); // nothing changes yet

        // Only the colleague can answer.
        try {
            $this->service()->respond($req, $this->fresh($this->a), true, null);
            $this->fail('requester answered own swap');
        } catch (ShiftRequestException $e) {
        }

        $this->service()->respond($req->refresh(), $this->fresh($this->b), true, 'Fine by me');
        $this->assertSame(ShiftRequest::STATUS_PENDING_APPROVAL, $req->refresh()->status);

        // The employees themselves can't approve.
        try {
            $this->service()->decide($req, $this->fresh($this->b), 'approved', null);
            $this->fail('colleague approved');
        } catch (ShiftRequestException $e) {
        }

        $this->service()->decide($req->refresh(), $this->admin, 'approved', 'OK');
        $req->refresh();
        $this->assertSame(ShiftRequest::STATUS_APPROVED, $req->status);
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));
        $this->assertSame($this->morning->id, $this->shiftOn($this->b, $date));
        $this->assertSame(['created', 'peer_accepted', 'submitted_for_approval', 'approved', 'applied'], $req->events()->pluck('event')->all());

        // Revert puts both days back.
        $this->service()->revert($req, $this->admin, 'Mistake');
        $this->assertSame(ShiftRequest::STATUS_REVERTED, $req->refresh()->status);
        $this->assertSame($this->morning->id, $this->shiftOn($this->a, $date));
        $this->assertSame($this->evening->id, $this->shiftOn($this->b, $date));
        $this->assertSame(2, ShiftChangeLog::withoutGlobalScopes()->where('shift_request_id', $req->id)->where('change_type', 'revert')->count());
    }

    public function test_approval_is_blocked_when_the_shifts_changed_since_the_request(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $date = $this->day();

        $req = $this->service()->createSwapRequest($this->fresh($this->a), $this->b->id, [$date], null);
        $this->service()->respond($req, $this->fresh($this->b), true, null);

        // HR changes Alpha's day in the meantime.
        $this->service()->directChange($this->admin, $this->a->id, [$date], $this->night->id, 'Rota change');

        try {
            $this->service()->decide($req->refresh(), $this->admin, 'approved', null);
            $this->fail('stale request was approved');
        } catch (ShiftRequestException $e) {
            $this->assertTrue($e->stale);
        }
        $this->assertSame(ShiftRequest::STATUS_PENDING_APPROVAL, $req->refresh()->status);
        $this->assertContains('stale_blocked', $req->events()->pluck('event')->all());
        $this->assertSame($this->night->id, $this->shiftOn($this->a, $date));
    }

    public function test_pending_request_blocks_a_second_one_for_the_same_day(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $date = $this->day();
        $this->service()->createSwapRequest($this->fresh($this->a), $this->b->id, [$date], null);

        $this->expectException(ShiftRequestException::class);
        $this->expectExceptionMessage('pending shift request');
        $this->service()->createChangeRequest($this->fresh($this->a), [$date], $this->night->id, null);
    }

    public function test_notice_period_is_enforced_for_employee_requests(): void
    {
        $this->permanent($this->a, $this->morning);
        $tomorrow = $this->day(1);
        ShiftRequestSetting::withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'min_notice_hours' => 72] + ShiftRequestSetting::DEFAULTS);

        $this->expectException(ShiftRequestException::class);
        $this->expectExceptionMessage('notice');
        $this->service()->createChangeRequest($this->fresh($this->a), [$tomorrow], $this->evening->id, null);
    }

    public function test_change_request_without_approval_applies_at_once(): void
    {
        $this->permanent($this->a, $this->morning);
        ShiftRequestSetting::withoutGlobalScopes()->create(['tenant_id' => $this->tenantId, 'requires_approval' => false] + ShiftRequestSetting::DEFAULTS);
        $date = $this->day();

        $req = $this->service()->createChangeRequest($this->fresh($this->a), [$date], $this->evening->id, 'Class in the morning');
        $this->assertSame(ShiftRequest::STATUS_APPROVED, $req->status);
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));
    }

    public function test_short_rest_between_shifts_is_refused_for_requests(): void
    {
        $this->permanent($this->a, $this->morning); // 06:00–14:00 every day
        $date = $this->day();
        // Rest comes from Company Policies → Working-time thresholds.
        app(\App\Services\Attendance\AttendancePolicyWriter::class)->save($this->tenantId, ['min_rest_hours' => 8], null, $this->admin->id, false);

        // Night 22:00 → 06:00 next day, then Morning 06:00: 0 h rest.
        $this->expectException(ShiftRequestException::class);
        $this->expectExceptionMessage('rest');
        $this->service()->createChangeRequest($this->fresh($this->a), [$date], $this->night->id, null);
    }

    public function test_max_daily_hours_from_working_time_thresholds(): void
    {
        $this->permanent($this->a, $this->morning);
        $long = $this->shift('Long', '08:00', '20:00'); // 12 h
        app(\App\Services\Attendance\AttendancePolicyWriter::class)->save($this->tenantId, ['max_daily_hours' => 10, 'min_rest_hours' => null], null, $this->admin->id, false);

        $check = $this->service()->check($this->tenantId, $this->service()->buildChangeItems($this->tenantId, $this->a->id, [$this->day()], $long->id), 'direct');
        $this->assertSame([], $check['errors']);
        $this->assertStringContainsString('maximum 10', implode(' ', $check['warnings']));

        $this->expectException(ShiftRequestException::class);
        $this->expectExceptionMessage('maximum 10');
        $this->service()->createChangeRequest($this->fresh($this->a), [$this->day()], $long->id, null);
    }

    // ------------------------------------------------------------------ web pages

    public function test_pages_render_and_the_employee_can_raise_and_list_a_swap_over_http(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $date = $this->day();

        $this->actingAs($this->admin)->get(route('shift.roster', ['view' => 'week', 'date' => $date]))->assertOk()->assertSee('Swap Shifts');
        $this->actingAs($this->admin)->get(route('shift.requests.index'))->assertOk()->assertSee('All Requests');
        $this->actingAs($this->fresh($this->a))->get(route('shift.requests.index'))->assertOk()->assertDontSee('All Requests');

        $this->actingAs($this->fresh($this->a))->getJson(route('shift.requests.candidates', ['date' => $date]))
            ->assertOk()->assertJsonPath('data.my_shift.id', $this->morning->id);

        $this->actingAs($this->fresh($this->a))->postJson(route('shift.requests.preview'), ['mode' => 'request', 'type' => 'swap', 'counterpart_id' => $this->b->id, 'start_date' => $date])
            ->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.errors', []);

        $id = $this->actingAs($this->fresh($this->a))->postJson(route('shift.requests.store'), ['type' => 'swap', 'counterpart_id' => $this->b->id, 'start_date' => $date, 'reason' => 'Exam'])
            ->assertOk()->json('data.id');

        $this->actingAs($this->fresh($this->b))->getJson(route('shift.requests.data', ['tab' => 'to_me']))
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.can.respond', true);
        $this->actingAs($this->fresh($this->b))->postJson(route('shift.requests.respond', $id), ['accept' => 1])->assertOk();

        $this->actingAs($this->admin)->getJson(route('shift.requests.data', ['tab' => 'approvals']))->assertOk()->assertJsonPath('data.0.can.decide', true);
        $this->actingAs($this->admin)->getJson(route('shift.requests.show', $id))->assertOk()->assertJsonCount(3, 'data.events');
        $this->actingAs($this->admin)->postJson(route('shift.requests.decide', $id), ['action' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $date));

        // An unrelated employee can't open it.
        $this->actingAs($this->fresh($this->manager))->getJson(route('shift.requests.show', $id))->assertNotFound();

        $this->actingAs($this->admin)->get(route('shift.requests.export'))->assertOk();
    }

    // ------------------------------------------------------------------ Phase 4: mobile API

    public function test_mobile_api_swap_request_answer_and_manager_decision(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        foreach ([$this->a, $this->b] as $u) {
            DB::table('user_reporting_heads')->insert(['tenant_id' => $this->tenantId, 'user_id' => $u->id, 'reporting_head_id' => $this->manager->id, 'is_primary' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $date = $this->day();
        app()->instance('current_tenant', \App\Models\Tenant::find($this->tenantId));

        $this->actingAs($this->fresh($this->a), 'api')->getJson('/api/user/shift/request-options')->assertOk()->assertJsonPath('data.swap_enabled', true);
        $this->actingAs($this->fresh($this->a), 'api')->getJson('/api/user/shift/swap-candidates?date=' . $date)->assertOk()->assertJsonPath('data.my_shift.id', $this->morning->id);
        $this->actingAs($this->fresh($this->a), 'api')->postJson('/api/user/shift/change-request', ['start_date' => $date])
            ->assertStatus(422)->assertJsonPath('success', false);

        $id = $this->actingAs($this->fresh($this->a), 'api')->postJson('/api/user/shift/swap-request', ['counterpart_id' => $this->b->id, 'start_date' => $date, 'reason' => 'Wedding'])
            ->assertOk()->assertJsonPath('data.status', 'pending_peer')->json('data.id');
        $this->assertSame('mobile', ShiftRequest::withoutGlobalScopes()->find($id)->channel);

        $this->actingAs($this->fresh($this->b), 'api')->getJson('/api/user/shift/requests?tab=to_me')->assertOk()->assertJsonPath('data.requests.0.can.respond', true);
        $this->actingAs($this->fresh($this->b), 'api')->postJson("/api/user/shift/requests/{$id}/respond", ['accept' => true])->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $this->actingAs($this->fresh($this->manager), 'api')->getJson('/api/manager/shift/requests')->assertOk()->assertJsonPath('data.requests.0.id', $id);
        $this->actingAs($this->fresh($this->manager), 'api')->postJson("/api/manager/shift/requests/{$id}/decision", ['action' => 'approved'])
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $plan = $this->actingAs($this->fresh($this->a), 'api')->getJson('/api/user/shift/plan?start_date=' . $date . '&end_date=' . $date)->assertOk()->json('data.shifts.0');
        $this->assertSame($this->evening->id, $plan['shift_id']);
        $this->assertSame(ShiftRequest::withoutGlobalScopes()->find($id)->request_no, $plan['changed']['request_no']);
        $this->assertSame('mobile', DB::table('shift_request_events')->where('shift_request_id', $id)->where('event', 'approved')->value('channel'));
    }

    // ------------------------------------------------------------------ Phase 5: rotation

    public function test_rotation_pattern_generates_shifts_and_days_off_and_can_be_ended(): void
    {
        DB::table('user_weekoffs')->insert(['tenant_id' => $this->tenantId, 'user_id' => $this->a->id, 'off_type' => 'day_based', 'day_name' => 'Sunday', 'status' => 1, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->postJson(route('shift.rotations.store'), [
            'name' => $this->tag . ' 2on1off', 'steps' => [$this->morning->id, $this->evening->id, 0],
        ])->assertOk();
        $pattern = \App\Models\ShiftRotationPattern::withoutGlobalScopes()->where('name', $this->tag . ' 2on1off')->firstOrFail();
        $this->assertSame(3, $pattern->cycle_days);

        $start = Carbon::today()->addDay();
        $this->actingAs($this->admin)->postJson(route('shift.rotations.assign'), [
            'pattern_id' => $pattern->id, 'user_ids' => [$this->a->id, $this->b->id], 'start_date' => $start->toDateString(), 'stagger_days' => 1, 'replace_weekly_offs' => 1,
        ])->assertOk();

        $d = fn ($i) => $start->copy()->addDays($i)->toDateString();
        // Alpha: Morning, Evening, OFF, Morning … (Sunday no longer forced off)
        $this->assertSame([$this->morning->id, $this->evening->id, null, $this->morning->id], [$this->shiftOn($this->a, $d(0)), $this->shiftOn($this->a, $d(1)), $this->shiftOn($this->a, $d(2)), $this->shiftOn($this->a, $d(3))]);
        $this->assertTrue(\App\Support\WeekOffPredicate::isWeekOff(\App\Models\UserWeekoffs::withoutGlobalScopes()->where('user_id', $this->a->id)->where('status', 1)->get(), Carbon::parse($d(2))));
        // Bravo staggered one day: Evening, OFF, Morning …
        $this->assertSame([$this->evening->id, null, $this->morning->id], [$this->shiftOn($this->b, $d(0)), $this->shiftOn($this->b, $d(1)), $this->shiftOn($this->b, $d(2))]);
        $this->assertGreaterThan(0, ShiftChangeLog::withoutGlobalScopes()->where('user_id', $this->a->id)->where('source', 'rotation')->count());

        // A swap on a rotation day survives the nightly roll / a rebuild.
        $this->service()->directSwap($this->admin, $this->a->id, $this->b->id, [$d(0)], 'Cover');
        app(ShiftMaterializer::class)->rollHorizon();
        app(ShiftMaterializer::class)->regenerateFrom($this->tenantId, $this->a->id, Carbon::today());
        $this->assertSame($this->evening->id, $this->shiftOn($this->a, $d(0)));

        // End Alpha's rotation after day 3: later days and generated days off are gone.
        $assignment = ShiftAssignment::withoutGlobalScopes()->where('user_id', $this->a->id)->where('type', 'rotating')->where('status', 'active')->firstOrFail();
        $this->actingAs($this->admin)->postJson(route('shift.assignments.end-permanent', $assignment->id), ['end_date' => $d(3), 'reason' => 'Back to day shift'])->assertOk();
        $this->assertNull($this->shiftOn($this->a, $d(4)));
        $this->assertSame(0, DB::table('user_weekoffs')->where('shift_assignment_id', $assignment->id)->where('start_date', '>', $d(3))->count());

        $this->actingAs($this->admin)->get(route('shift.rotations.index'))->assertOk()->assertSee($this->tag . ' 2on1off');
        DB::table('shift_rotation_steps')->where('pattern_id', $pattern->id)->delete();
        $pattern->delete();
    }

    public function test_company_policy_card_saves_the_settings(): void
    {
        $this->actingAs($this->admin)->get(route('workforce-settings.index'))->assertOk()->assertSee('shiftRequestsModal');
        $this->actingAs($this->admin)->put(route('shift-request-settings.update'), [
            'swap_enabled' => 1, 'requires_approval' => 1, 'min_notice_hours' => 12,
            'max_requests_per_month' => 0, 'peer_response_hours' => 6,
        ])->assertRedirect();

        $s = ShiftRequestSetting::forTenant($this->tenantId);
        $this->assertTrue($s->exists);
        $this->assertSame([true, false, 12, 0, 6, false], [$s->swap_enabled, $s->change_enabled, $s->min_notice_hours, $s->max_requests_per_month, $s->peer_response_hours, $s->same_department_only]);
    }

    public function test_unanswered_swap_expires(): void
    {
        $this->permanent($this->a, $this->morning);
        $this->permanent($this->b, $this->evening);
        $req = $this->service()->createSwapRequest($this->fresh($this->a), $this->b->id, [$this->day()], null);
        $req->update(['expires_at' => now()->subMinute()]);

        $this->artisan('shift-requests:expire')->assertSuccessful();
        $this->assertSame(ShiftRequest::STATUS_EXPIRED, $req->refresh()->status);
        $this->assertSame($this->morning->id, $this->shiftOn($this->a, $this->day()));
    }
}
