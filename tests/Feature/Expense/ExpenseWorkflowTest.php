<?php

namespace Tests\Feature\Expense;

use App\Exceptions\ExpenseException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\Expense\ExpensePaymentService;
use App\Services\Expense\ExpenseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Expense Phase 4 — claim policy (limits / receipts / back-dating / duplicates), multiple receipts,
 * soft delete, withdraw, and settlement-shortfall coverage.
 */
class ExpenseWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $hr;
    private User $alice;
    private User $bob;
    private ExpenseType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');

        $hr = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $emps = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get() : collect();

        if (! $hr || $emps->count() < 2) {
            $this->markTestSkipped('fixture users missing in the dev DB');
        }

        [$this->hr, $this->alice, $this->bob] = [$hr, $emps[0], $emps[1]];
        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Storage::fake('local');

        // A dedicated category so this test controls its policy without touching the company's real ones.
        $this->type = ExpenseType::create(['tenant_id' => $this->tenantId, 'name' => 'PHPUnit-' . uniqid(), 'status' => 1]);
    }

    // ---------------------------------------------------------------- helpers

    private function svc(): ExpenseService
    {
        return app(ExpenseService::class);
    }

    private function as(User $u): static
    {
        return $this->actingAs($u)->withSession(['tenant_id' => $this->tenantId])->withHeaders(['Accept' => 'application/json']);
    }

    private function data(array $o = []): array
    {
        return $o + ['expense_type' => $this->type->id, 'requirement_type' => 'reimbursement', 'amount' => '100.00',
            'date' => now()->subDay()->toDateString(), 'description' => 'phpunit-workflow'];
    }

    private function png(string $name = 'r.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))->mimeType('image/png');
    }

    private function expense(User $u, array $o = []): Expense
    {
        return Expense::create($o + [
            'tenant_id' => $this->tenantId, 'user_id' => $u->id, 'expense_type' => $this->type->id, 'amount' => 100,
            'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement', 'status' => 'pending', 'description' => 'phpunit-workflow',
        ]);
    }

    private function balance(User $u, float $current = 0): UserExpenseBalance
    {
        DB::table('expense_transactions')->where('user_id', $u->id)->delete();

        return UserExpenseBalance::updateOrCreate(['user_id' => $u->id], ['tenant_id' => $this->tenantId, 'current_balance' => $current,
            'advance_balance' => $current, 'settlement_balance' => 0, 'reimbursement_balance' => 0]);
    }

    // ================================================================== policy

    public function test_max_amount_caps_a_claim_and_the_cap_itself_is_allowed(): void
    {
        $this->type->update(['max_amount' => 500]);

        $this->assertNotNull($this->svc()->submit($this->alice, $this->data(['amount' => '500.00'])));

        try {
            $this->svc()->submit($this->alice, $this->data(['amount' => '500.01']));
            $this->fail('over the cap must be refused');
        } catch (ExpenseException $e) {
            $this->assertSame(422, $e->httpStatus());
            $this->assertStringContainsString('limit', $e->getMessage());
            $this->assertStringContainsString('500.00', $e->getMessage());
            $this->assertStringContainsString($this->type->name, $e->getMessage());
        }
    }

    public function test_a_receipt_can_be_required_above_a_threshold_or_always(): void
    {
        $this->type->update(['receipt_required_above' => 200]);

        $this->assertNotNull($this->svc()->submit($this->alice, $this->data(['amount' => '200.00'])), 'at the threshold: no receipt needed');

        try {
            $this->svc()->submit($this->alice, $this->data(['amount' => '200.01']));
            $this->fail('a receipt is required above the threshold');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('receipt is required', $e->getMessage());
            $this->assertStringContainsString('200.00', $e->getMessage());
        }
        $this->assertNotNull($this->svc()->submit($this->alice, $this->data(['amount' => '999']), $this->png()), 'with a receipt it is fine');

        $this->type->update(['receipt_required_above' => 0]);   // 0 = always
        $this->expectException(ExpenseException::class);
        $this->svc()->submit($this->alice, $this->data(['amount' => '1']));
    }

    public function test_backdating_limit_applies_to_spend_but_not_to_advances(): void
    {
        $this->type->update(['max_backdate_days' => 7]);

        $this->assertNotNull($this->svc()->submit($this->alice, $this->data(['date' => now()->subDays(7)->toDateString()])), '7 days old is allowed');

        try {
            $this->svc()->submit($this->alice, $this->data(['date' => now()->subDays(8)->toDateString()]));
            $this->fail('8 days old must be refused');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('within 7 day', $e->getMessage());
            $this->assertStringContainsString('8 day', $e->getMessage());
        }

        // An advance is a request for FUTURE money — the rule does not apply.
        $this->assertNotNull($this->svc()->submit($this->alice, $this->data(['requirement_type' => 'advance', 'date' => now()->subDays(60)->toDateString()])));
    }

    public function test_an_inactive_or_foreign_category_is_refused(): void
    {
        $this->type->update(['status' => 0]);

        try {
            $this->svc()->submit($this->alice, $this->data());
            $this->fail('inactive category');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('no longer active', $e->getMessage());
        }

        $foreign = ExpenseType::withoutGlobalScopes()->where('tenant_id', '!=', $this->tenantId)->first();
        if ($foreign) {
            $this->expectException(ExpenseException::class);
            $this->svc()->submit($this->alice, $this->data(['expense_type' => $foreign->id]));
        }
    }

    public function test_policy_rules_reach_the_user_over_http_as_a_readable_message(): void
    {
        $this->type->update(['max_amount' => 50]);

        $res = $this->as($this->alice)->post(route('expense.create'), $this->data(['amount' => '75']))->assertStatus(422);

        $res->assertJsonPath('success', false);
        $this->assertStringContainsString('limit', $res->json('message'));
        $this->assertSame(0, Expense::where('user_id', $this->alice->id)->where('expense_type', $this->type->id)->count());
    }

    public function test_a_lookalike_claim_is_flagged_as_a_possible_duplicate_but_never_blocked(): void
    {
        $first = $this->svc()->submit($this->alice, $this->data());
        $second = $this->svc()->submit($this->alice, $this->data());               // identical

        $this->assertNull($first->possible_duplicate_of);
        $this->assertSame($first->id, (int) $second->possible_duplicate_of);
        $this->assertSame($first->expense_number, $second->possibleDuplicateOf->expense_number);

        // different amount / different person / different type -> not a duplicate
        $this->assertNull($this->svc()->submit($this->alice, $this->data(['amount' => '101']))->possible_duplicate_of);
        $this->assertNull($this->svc()->submit($this->bob, $this->data())->possible_duplicate_of);
        $this->assertNull($this->svc()->submit($this->alice, $this->data(['requirement_type' => 'settlement']))->possible_duplicate_of);

        // editing the amount clears the flag
        $this->svc()->update($this->alice, $second->id, $this->data(['amount' => '250']));
        $this->assertNull($second->fresh()->possible_duplicate_of);

        // a cancelled earlier claim does not count
        $lone = $this->svc()->submit($this->alice, $this->data(['amount' => '333']));
        $lone->update(['status' => 'cancelled']);
        $this->assertNull($this->svc()->submit($this->alice, $this->data(['amount' => '333']))->possible_duplicate_of);
    }

    // ============================================================ expense types

    public function test_only_users_with_manage_can_configure_expense_types_and_creation_works_again(): void
    {
        // Regression: the route had NO permission check, and store() crashed (HTTP 500) on an undefined $tenantId.
        $this->as($this->alice)->postJson(route('expense-type.create'), ['name' => 'Sneaky'])->assertStatus(403);
        $this->as($this->alice)->getJson(route('expense-type.index'))->assertStatus(403);

        $name = 'PolicyType-' . uniqid();
        $this->as($this->hr)->postJson(route('expense-type.create'), [
            'name' => $name, 'description' => 'x', 'max_amount' => '1500.50', 'receipt_required_above' => '0', 'max_backdate_days' => '30',
        ])->assertOk()->assertJsonPath('success', true);

        $created = ExpenseType::where('name', $name)->firstOrFail();
        $this->assertSame($this->tenantId, (int) $created->tenant_id);
        $this->assertEquals(1500.50, (float) $created->max_amount);
        $this->assertEquals(0.0, (float) $created->receipt_required_above, '0 is a real rule ("always"), not "unset"');
        $this->assertSame(30, $created->max_backdate_days);

        // blank fields mean "no rule" (NULL), and updating can clear a rule
        $this->as($this->hr)->postJson(route('expense-type.update', 0), ['id' => $created->id, 'name' => $name, 'status' => 1, 'max_amount' => '', 'max_backdate_days' => ''])->assertOk();
        $created->refresh();
        $this->assertNull($created->max_amount);
        $this->assertNull($created->max_backdate_days);

        // validation
        $this->as($this->hr)->postJson(route('expense-type.create'), ['name' => $name])->assertStatus(422);          // duplicate name
        $this->as($this->hr)->postJson(route('expense-type.create'), ['name' => 'Neg', 'max_amount' => '-5'])->assertStatus(422);
        $this->as($this->hr)->postJson(route('expense-type.create'), ['name' => 'Days', 'max_backdate_days' => '2.5'])->assertStatus(422);

        $this->as($this->hr)->get(route('expense-type.index'))->assertOk()->assertSee($name);
    }

    // ================================================================= receipts

    public function test_several_receipts_are_stored_privately_first_is_primary_and_all_are_listed_with_signed_links(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data(), [$this->png('a.png'), $this->png('b.png'), $this->png('c.png')]);

        $this->assertNotNull($e->file);
        $this->assertStringStartsWith("expense/{$this->tenantId}/", $e->file);
        $extras = ExpenseAttachment::where('expense_id', $e->id)->orderBy('id')->get();
        $this->assertCount(2, $extras);
        foreach ($extras as $a) {
            $this->assertStringStartsWith("expense/{$this->tenantId}/", $a->file_path);
            Storage::disk('local')->assertExists($a->file_path);
        }

        $receipts = app(\App\Services\Expense\ExpenseAttachmentService::class)->receipts($e->fresh('attachments'));
        $this->assertCount(3, $receipts);
        $this->assertTrue($receipts[0]['primary']);
        $this->assertSame(['b.png', 'c.png'], [$receipts[1]['name'], $receipts[2]['name']]);
        foreach ($receipts as $r) {
            $this->assertStringContainsString('signature=', $r['url']);
        }
    }

    public function test_an_extra_receipt_downloads_only_through_its_signed_tenant_bound_link(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data(), [$this->png('a.png'), $this->png('b.png')]);
        $attachment = ExpenseAttachment::where('expense_id', $e->id)->firstOrFail();
        $url = $attachment->file_url;

        $this->assertStringContainsString('/expense/attachment/' . $attachment->id, $url);
        $this->get(route('expense.attachment', ['id' => $attachment->id]))->assertStatus(403);         // unsigned
        $this->get($url)->assertOk();                                                                    // signed, no session at all
        $this->get(str_replace("tenant={$this->tenantId}", 'tenant=' . ($this->tenantId + 1), $url))->assertStatus(403);   // tampered
        $wrongTenant = \Illuminate\Support\Facades\URL::temporarySignedRoute('expense.attachment', now()->addMinutes(5), ['id' => $attachment->id, 'tenant' => $this->tenantId + 999]);
        $this->get($wrongTenant)->assertStatus(404);
    }

    public function test_at_most_five_receipts_and_web_and_api_field_names_both_work(): void
    {
        // service level
        try {
            $this->svc()->submit($this->alice, $this->data(), array_map(fn ($i) => $this->png("r{$i}.png"), range(1, 6)));
            $this->fail('6 receipts must be refused');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('at most 5', $e->getMessage());
        }

        // web form: files[]
        $this->as($this->alice)->post(route('expense.create'), $this->data(['files' => [$this->png('1.png'), $this->png('2.png')]]))->assertOk();
        $web = Expense::where('user_id', $this->alice->id)->latest('id')->first();
        $this->assertSame(1, ExpenseAttachment::where('expense_id', $web->id)->count());
        $this->assertNotNull($web->file);

        // mobile app: the single legacy `file` field still works, and may be combined with files[]
        $this->as($this->alice)->post(route('expense.create'), $this->data(['amount' => '11', 'file' => $this->png('m.png'), 'files' => [$this->png('x.png')]]))->assertOk();
        $mobile = Expense::where('user_id', $this->alice->id)->latest('id')->first();
        $this->assertSame(1, ExpenseAttachment::where('expense_id', $mobile->id)->count());

        // request-level cap
        $this->as($this->alice)->post(route('expense.create'), $this->data(['amount' => '12', 'files' => array_map(fn ($i) => $this->png("z{$i}.png"), range(1, 6))]))->assertStatus(422);
        // and an attachment of a forbidden type inside files[]
        $this->as($this->alice)->post(route('expense.create'), $this->data(['amount' => '13', 'files' => [UploadedFile::fake()->createWithContent('e.php', '<?php 1;')]]))->assertStatus(422);
    }

    public function test_updating_adds_receipts_and_removing_one_promotes_the_next_and_deletes_the_file_after_commit(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data(), [$this->png('a.png'), $this->png('b.png')]);
        $primaryPath = $e->file;
        $extra = ExpenseAttachment::where('expense_id', $e->id)->firstOrFail();

        $this->svc()->update($this->alice, $e->id, $this->data(['amount' => '120']), [$this->png('c.png')]);
        $this->assertSame(2, ExpenseAttachment::where('expense_id', $e->id)->count(), 'new files are ADDED, not replacing');

        // remove the primary: the first extra is promoted, its FILE is kept, the old primary file goes
        $this->svc()->removeReceipt($this->alice, $e->id, null);
        $e->refresh();
        $this->assertSame($extra->file_path, $e->file);
        Storage::disk('local')->assertExists($e->file);
        Storage::disk('local')->assertMissing($primaryPath);
        $this->assertSame(1, ExpenseAttachment::where('expense_id', $e->id)->count());

        // remove an extra by id
        $left = ExpenseAttachment::where('expense_id', $e->id)->firstOrFail();
        $this->svc()->removeReceipt($this->alice, $e->id, $left->id);
        Storage::disk('local')->assertMissing($left->file_path);
        $this->assertSame(0, ExpenseAttachment::where('expense_id', $e->id)->count());
    }

    public function test_receipt_removal_respects_ownership_status_and_the_receipt_requirement(): void
    {
        $this->type->update(['receipt_required_above' => 50]);
        $e = $this->svc()->submit($this->alice, $this->data(['amount' => '100']), [$this->png('a.png')]);

        try {
            $this->svc()->removeReceipt($this->alice, $e->id, null);
            $this->fail('the last receipt cannot go while one is required');
        } catch (ExpenseException $ex) {
            $this->assertStringContainsString('receipt is required', $ex->getMessage());
        }
        $this->assertNotNull($e->fresh()->file);

        try {
            $this->svc()->removeReceipt($this->bob, $e->id, null);
            $this->fail('not the owner');
        } catch (ExpenseException $ex) {
            $this->assertSame(403, $ex->httpStatus());
        }

        $e->update(['status' => 'approved']);
        $this->expectException(ExpenseException::class);
        $this->svc()->removeReceipt($this->alice, $e->id, null);
    }

    public function test_receipt_endpoints_over_http(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data(), [$this->png('a.png'), $this->png('b.png')]);
        $extra = ExpenseAttachment::where('expense_id', $e->id)->firstOrFail();

        $this->as($this->bob)->deleteJson(route('expense.receipts.remove', [$e->id, $extra->id]))->assertStatus(403);
        $this->as($this->alice)->deleteJson(route('expense.receipts.remove', [$e->id, $extra->id]))->assertOk();
        $this->as($this->alice)->deleteJson(route('expense.receipts.remove', [$e->id, 'primary']))->assertOk();
        $this->as($this->alice)->deleteJson(route('expense.receipts.remove', [$e->id, 'primary']))->assertStatus(404);   // nothing left
    }

    // ============================================================ delete / withdraw

    public function test_delete_is_soft_it_keeps_the_row_and_the_receipts_and_hides_it_everywhere(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data(), [$this->png('a.png'), $this->png('b.png')]);
        $file = $e->file;

        $this->svc()->delete($this->alice, $e->id);

        $this->assertNull(Expense::find($e->id), 'gone from every normal query');
        $this->assertNotNull(Expense::withTrashed()->find($e->id)->deleted_at);
        Storage::disk('local')->assertExists($file);                                           // receipts kept for audit
        $this->assertSame(1, ExpenseAttachment::where('expense_id', $e->id)->count());
        $this->as($this->alice)->get(route('expense.index'))->assertOk()->assertDontSee($e->expense_number);

        try {
            $this->svc()->delete($this->alice, $e->id);
            $this->fail('cannot delete twice');
        } catch (ExpenseException $ex) {
            $this->assertSame(404, $ex->httpStatus());
        }
    }

    public function test_withdraw_keeps_a_visible_record_of_a_pending_claim(): void
    {
        $e = $this->svc()->submit($this->alice, $this->data());

        $r = $this->svc()->withdraw($this->alice, $e->id, 'I found the receipt was for a personal purchase');

        $r->refresh();
        $this->assertSame('cancelled', $r->status);
        $this->assertNotNull($r->withdrawn_at);
        $this->assertStringContainsString('personal purchase', $r->withdrawn_reason);
        $this->assertTrue($r->isWithdrawn());
        $this->assertNull($r->rejected_by, 'a withdrawal is not a rejection');
        $this->assertStringContainsString('Withdrawn by the employee', DB::table('expense_status_histories')->where('expense_id', $e->id)->orderByDesc('id')->value('remarks'));
        $this->assertSame(1, DB::table('audit_logs')->where('entity_type', 'Expense')->where('entity_id', $e->id)->where('action', 'expenses.withdrawn')->count());
        $this->as($this->alice)->get(route('expense.index'))->assertOk()->assertSee('Withdrawn');
    }

    public function test_withdraw_rules(): void
    {
        $pending = $this->expense($this->alice);

        foreach ([['', 422], ['ab', 422]] as [$reason, $code]) {
            try {
                $this->svc()->withdraw($this->alice, $pending->id, $reason);
                $this->fail('reason required');
            } catch (ExpenseException $e) {
                $this->assertSame($code, $e->httpStatus());
            }
        }
        try {
            $this->svc()->withdraw($this->bob, $pending->id, 'not mine');
            $this->fail('not the owner');
        } catch (ExpenseException $e) {
            $this->assertSame(403, $e->httpStatus());
        }

        // approved + completely unpaid advance / reimbursement: allowed
        $advance = $this->expense($this->alice, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 500]);
        $this->assertSame('cancelled', $this->svc()->withdraw($this->alice, $advance->id, 'no longer travelling')->status);
        $this->assertNotContains($advance->id, Expense::payable()->pluck('id')->all(), 'it drops out of the finance payable list');

        // ...but not once ANY money has moved
        $partly = $this->expense($this->alice, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 500]);
        DB::table('user_expense_balances')->where('user_id', $this->alice->id)->exists() ?: $this->balance($this->alice);
        app(ExpensePaymentService::class)->payBatch($this->hr, [['expense_id' => $partly->id, 'amount' => '100']],
            ['payment_date' => now()->toDateString(), 'payment_mode' => 'cash'], null, fn () => true);
        try {
            $this->svc()->withdraw($this->alice, $partly->id, 'changed my mind');
            $this->fail('partly paid');
        } catch (ExpenseException $e) {
            $this->assertStringContainsString('not been paid at all', $e->getMessage());
        }

        // an approved SETTLEMENT (balance already deducted) and a completed claim cannot be withdrawn
        foreach ([['settlement', 'approved'], ['reimbursement', 'complete'], ['reimbursement', 'cancelled']] as [$type, $status]) {
            $x = $this->expense($this->alice, ['requirement_type' => $type, 'status' => $status]);
            try {
                $this->svc()->withdraw($this->alice, $x->id, 'nope nope');
                $this->fail("{$type}/{$status} must not be withdrawable");
            } catch (ExpenseException $e) {
                $this->assertSame(400, $e->httpStatus());
            }
        }

        // a reimbursement split off a settlement belongs to that settlement
        $child = $this->expense($this->alice, ['status' => 'approved', 'parent_expense_id' => $pending->id]);
        $this->expectException(ExpenseException::class);
        $this->expectExceptionMessage('created from a settlement');
        $this->svc()->withdraw($this->alice, $child->id, 'let me out');
    }

    public function test_withdraw_over_http_and_the_ui_offers_it_only_where_it_is_allowed(): void
    {
        $pending = $this->expense($this->alice);
        $unpaidAdvance = $this->expense($this->alice, ['requirement_type' => 'advance', 'status' => 'approved', 'amount' => 300]);
        $settled = $this->expense($this->alice, ['requirement_type' => 'settlement', 'status' => 'approved']);

        // park the employee's real claims so only the fixtures above can offer "Withdraw" (rolled back)
        Expense::where('user_id', $this->alice->id)->whereNotIn('id', [$pending->id, $unpaidAdvance->id, $settled->id])->update(['status' => 'complete']);

        $page = $this->as($this->alice)->get(route('expense.index'))->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('data-id="' . $pending->id . '"', $html);
        $this->assertSame(2, substr_count($html, 'withdraw-expense" href="#"'), 'offered on the pending claim and the unpaid approved advance — not the settlement');

        $this->as($this->alice)->postJson(route('expense.withdraw', $pending->id), ['reason' => 'x'])->assertStatus(422);
        $this->as($this->bob)->postJson(route('expense.withdraw', $pending->id), ['reason' => 'not mine at all'])->assertStatus(403);
        $this->as($this->alice)->postJson(route('expense.withdraw', $pending->id), ['reason' => 'raised by mistake'])->assertOk()->assertJsonPath('success', true);
        $this->as($this->alice)->postJson(route('expense.withdraw', $settled->id), ['reason' => 'trying my luck'])->assertStatus(400);
        $this->assertSame('cancelled', $pending->fresh()->status);
    }

    public function test_purge_removes_only_claims_deleted_long_ago_together_with_their_receipts(): void
    {
        $old = $this->svc()->submit($this->alice, $this->data(['amount' => '11']), [$this->png('a.png'), $this->png('b.png')]);
        $recent = $this->svc()->submit($this->alice, $this->data(['amount' => '12']), [$this->png('c.png')]);
        $this->svc()->delete($this->alice, $old->id);
        $this->svc()->delete($this->alice, $recent->id);
        DB::table('expenses')->where('id', $old->id)->update(['deleted_at' => now()->subDays(120)]);
        $oldFiles = array_merge([$old->file], ExpenseAttachment::where('expense_id', $old->id)->pluck('file_path')->all());

        $this->artisan('expense:purge-deleted', ['--days' => 90, '--dry-run' => true])->expectsOutputToContain('would be purged')->assertSuccessful();
        $this->assertNotNull(Expense::withTrashed()->find($old->id), 'dry run deletes nothing');

        $this->artisan('expense:purge-deleted', ['--days' => 90])->assertSuccessful();

        $this->assertNull(Expense::withTrashed()->find($old->id));
        $this->assertSame(0, ExpenseAttachment::where('expense_id', $old->id)->count());
        foreach ($oldFiles as $f) {
            Storage::disk('local')->assertMissing($f);
        }
        $this->assertNotNull(Expense::withTrashed()->find($recent->id), 'a recent deletion is still retained');
        Storage::disk('local')->assertExists($recent->file);
    }

    // ============================================================ shortfall

    public function test_a_settlement_larger_than_the_advance_still_refuses_by_default_and_reports_the_numbers(): void
    {
        $this->balance($this->alice, 300);
        $s = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 1000]);

        try {
            $this->svc()->decide($s->id, $this->hr, 'approved', null);
            $this->fail('expected an insufficient-balance error');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame(400, $e->httpStatus());
            $this->assertSame(30000, $e->availableCents);
            $this->assertSame(100000, $e->requestedCents);
            $this->assertSame(70000, $e->shortfallCents());
            $this->assertStringContainsString('Insufficient advance balance', $e->getMessage());
        }
        $this->assertSame('pending', $s->fresh()->status);

        $res = $this->as($this->hr)->postJson(route('expense.update-status', $s->id), ['status' => 'approved'])->assertStatus(400);
        $res->assertJsonPath('code', 'insufficient_balance')->assertJsonPath('available', 300)->assertJsonPath('requested', 1000)->assertJsonPath('shortfall', 700);
    }

    public function test_covering_the_shortfall_deducts_the_advance_and_creates_a_payable_reimbursement(): void
    {
        $this->balance($this->alice, 300);
        $s = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 1000]);

        $r = $this->svc()->decide($s->id, $this->hr, 'approved', 'site visit', coverShortfall: true);

        $this->assertSame('approved', $s->fresh()->status);
        $bal = UserExpenseBalance::where('user_id', $this->alice->id)->first();
        $this->assertEquals(0.00, (float) $bal->current_balance, 'the whole advance was consumed');
        $this->assertEquals(300.00, (float) $bal->settlement_balance);

        $child = $r['child'];
        $this->assertNotNull($child);
        $this->assertSame('reimbursement', $child->requirement_type);
        $this->assertEquals(700.00, (float) $child->amount);
        $this->assertSame('approved', $child->status);
        $this->assertSame($s->id, (int) $child->parent_expense_id);
        $this->assertSame($s->id, (int) $s->fresh()->children->first()->parent_expense_id);
        $this->assertSame($this->hr->id, (int) $child->approved_by);
        $this->assertStringContainsString($s->expense_number, $child->description);
        $this->assertStringContainsString('₹700.00', $r['message']);
        $this->assertStringContainsString($child->expense_number, $r['message']);

        $ledger = DB::table('expense_transactions')->where('expense_id', $s->id)->get();
        $this->assertCount(1, $ledger);
        $this->assertEquals(300.00, (float) $ledger[0]->amount, 'the ledger shows only what the advance covered');

        // and the child is immediately payable through the normal voucher flow
        $this->assertContains($child->id, Expense::payable()->pluck('id')->all());
        $paid = app(ExpensePaymentService::class)->payBatch($this->hr, [['expense_id' => $child->id, 'amount' => '700']],
            ['payment_date' => now()->toDateString(), 'payment_mode' => 'upi'], null, fn () => true);
        $this->assertSame('complete', $child->fresh()->status);
        $this->assertEquals(700.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('reimbursement_balance'));
        $this->assertNotNull($paid['batch']->voucher_number);
    }

    public function test_with_no_advance_at_all_the_whole_settlement_becomes_a_reimbursement(): void
    {
        $this->balance($this->alice, 0);
        $s = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 450.75]);

        $r = $this->svc()->decide($s->id, $this->hr, 'approved', null, coverShortfall: true);

        $this->assertEquals(450.75, (float) $r['child']->amount);
        $this->assertSame(0, DB::table('expense_transactions')->where('expense_id', $s->id)->count(), 'nothing deducted, so no ledger row');
        $this->assertEquals(0.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_a_settlement_that_the_advance_fully_covers_is_unaffected_by_the_flag(): void
    {
        $this->balance($this->alice, 500);
        $s = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 200]);

        $r = $this->svc()->decide($s->id, $this->hr, 'approved', null, coverShortfall: true);

        $this->assertNull($r['child']);
        $this->assertEquals(300.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
        $this->assertSame(0, Expense::where('parent_expense_id', $s->id)->count());
    }

    public function test_cover_shortfall_over_http_bulk_and_the_mobile_api(): void
    {
        $this->balance($this->alice, 100);
        $viaWeb = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 400]);
        $this->as($this->hr)->postJson(route('expense.update-status', $viaWeb->id), ['status' => 'approved', 'cover_shortfall' => 1])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, Expense::where('parent_expense_id', $viaWeb->id)->count());

        $this->balance($this->bob, 0);
        $bulkA = $this->expense($this->bob, ['requirement_type' => 'settlement', 'amount' => 60]);
        $bulkB = $this->expense($this->bob, ['requirement_type' => 'settlement', 'amount' => 70, 'description' => 'other']);

        // without the flag both are skipped (and the report says why, with the numbers)
        $res = $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => [$bulkA->id, $bulkB->id], 'status' => 'approved'])->assertOk();
        $res->assertJsonPath('succeeded', 0);
        $this->assertSame('insufficient_balance', $res->json('results.0.code'));

        // with it both are approved and each gets its reimbursement
        $this->as($this->hr)->postJson(route('expense.bulk-status'), ['ids' => [$bulkA->id, $bulkB->id], 'status' => 'approved', 'cover_shortfall' => 1])
            ->assertOk()->assertJsonPath('succeeded', 2);
        $this->assertSame(2, Expense::whereIn('parent_expense_id', [$bulkA->id, $bulkB->id])->count());
    }

    public function test_shortfall_reimbursements_are_not_counted_twice_in_the_cards(): void
    {
        $this->balance($this->alice, 0);
        Expense::where('user_id', $this->alice->id)->update(['status' => 'cancelled']);   // park real data (rolled back)
        $s = $this->expense($this->alice, ['requirement_type' => 'settlement', 'amount' => 1000]);
        $this->svc()->decide($s->id, $this->hr, 'approved', null, coverShortfall: true);

        $stats = app(\App\Services\Expense\ExpenseStatsService::class);
        $v = $stats->viewData($stats->matrix(Expense::where('user_id', $this->alice->id)->where('status', '!=', 'cancelled')));

        $this->assertEquals(1000.00, $v['totalAmount'], 'the settlement is the spend; its split-off reimbursement is not added again');
        $this->assertSame(1, $v['totalExpenses']);
    }
}
