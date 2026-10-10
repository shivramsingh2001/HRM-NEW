<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shifts → Add / Edit validation now lives in StoreShiftRequest /
 * UpdateShiftRequest (code-quality plan Phase 5 pilot). The page's JavaScript
 * reads `{status: false, errors: {...}}` on 422 and `{status: false, message}`
 * on 404 — those shapes must not change. Dev DB, rolled back.
 */
class ShiftDefinitionRequestTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureFeatureEnabled::class, \App\Http\Middleware\EnsureCustomShiftsEnabled::class]);
        $this->admin = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')->orderBy('id')->firstOrFail();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_store_validation_keeps_the_page_response_shape(): void
    {
        $res = $this->actingAs($this->admin)->postJson(route('shift.store'), ['name' => '', 'start_time' => '25:00', 'end_time' => '17:00'])
            ->assertStatus(422);
        $this->assertSame(false, $res->json('status'));
        $this->assertArrayHasKey('name', $res->json('errors'));
        $this->assertArrayHasKey('start_time', $res->json('errors'));
        $this->assertNull($res->json('message'), 'no Laravel default "message" key — same body as before');

        // Overnight rule: a day shift must end after it starts.
        $this->actingAs($this->admin)->postJson(route('shift.store'), ['name' => 'PHPUnit FR '.uniqid(), 'start_time' => '22:00', 'end_time' => '06:00'])
            ->assertStatus(422)->assertJsonValidationErrors('end_time', 'errors');
    }

    public function test_store_and_update_succeed_and_names_are_unique_per_company(): void
    {
        $name = 'PHPUnit FR '.uniqid();
        $this->actingAs($this->admin)->postJson(route('shift.store'), ['name' => $name, 'start_time' => '22:00', 'end_time' => '06:00', 'is_overnight' => 1])
            ->assertCreated()->assertJson(['status' => true]);
        $id = (int) DB::table('shifts')->where('tenant_id', $this->admin->tenant_id)->where('name', $name)->value('id');

        // Same name again in the same company → 422; keeping its own name on update → fine.
        $this->actingAs($this->admin)->postJson(route('shift.store'), ['name' => $name, 'start_time' => '09:00', 'end_time' => '17:00'])
            ->assertStatus(422)->assertJsonValidationErrors('name', 'errors');
        $this->actingAs($this->admin)->postJson(route('shift.update', $id), ['name' => $name, 'start_time' => '21:00', 'end_time' => '05:00', 'is_overnight' => 1, 'status' => 1])
            ->assertOk()->assertJson(['status' => true]);
        $this->assertSame('21:00:00', substr((string) DB::table('shifts')->where('id', $id)->value('start_time'), 0, 8));
    }

    public function test_update_of_a_missing_shift_is_404_even_with_bad_input(): void
    {
        $this->actingAs($this->admin)->postJson(route('shift.update', 999999999), ['name' => ''])
            ->assertStatus(404)->assertExactJson(['status' => false, 'message' => 'Shift not found']);
    }
}
