<?php

namespace Tests\Feature\Expense;

use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserExpenseBalance;
use App\Services\Expense\ExpenseAttachmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Expense module Phase 1 hardening: secure uploads, amount validation, row
 * locking / idempotency, authorization gaps.
 *
 * Runs against the shared dev DB like the other feature tests, but inside a
 * transaction that is rolled back (DatabaseTransactions), and with the private
 * disk faked, so nothing persists.
 */
class ExpensePhase1HardeningTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $hr;
    private User $alice;
    private User $bob;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')
            ->havingRaw('count(*) >= 2')->orderByRaw('count(*) desc')->value('tenant_id');

        if (! $this->tenantId) {
            $this->markTestSkipped('no tenant with two employees in the dev DB');
        }

        $hr = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
            ->whereIn('role', ['admin', 'hr'])->where('status', '1')->first();
        $employees = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
            ->where('role', 'employee')->where('status', '1')->orderBy('id')->limit(2)->get();
        $typeId = ExpenseType::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->value('id');

        if (! $hr || $employees->count() < 2 || ! $typeId) {
            $this->markTestSkipped('fixture users / expense type missing for the chosen tenant');
        }

        [$this->hr, $this->alice, $this->bob] = [$hr, $employees[0], $employees[1]];
        $this->typeId = (int) $typeId;

        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Storage::fake('local');
    }

    // ---------------------------------------------------------------- helpers

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['tenant_id' => $this->tenantId])->withHeaders(['Accept' => 'application/json']);
    }

    private function makeExpense(User $owner, array $overrides = []): Expense
    {
        return Expense::create($overrides + [
            'tenant_id' => $this->tenantId,
            'user_id' => $owner->id,
            'expense_type' => $this->typeId,
            'amount' => 1000,
            'date' => now()->subDay()->toDateString(),
            'requirement_type' => 'advance',
            'status' => 'pending',
            'description' => 'phpunit',
        ]);
    }

    private function setBalance(User $user, float $current, float $advance = 0, float $reimb = 0): UserExpenseBalance
    {
        return UserExpenseBalance::updateOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $this->tenantId, 'current_balance' => $current, 'advance_balance' => $advance,
                'settlement_balance' => 0, 'reimbursement_balance' => $reimb]
        );
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'expense_type' => $this->typeId,
            'requirement_type' => 'reimbursement',
            'amount' => '100.50',
            'date' => now()->subDay()->toDateString(),
            'description' => 'phpunit',
        ];
    }

    // ------------------------------------------------------------- 1. uploads

    public function test_php_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "pwned";');

        $this->as($this->alice)->post(route('expense.create'), $this->payload(['file' => $file]))
            ->assertStatus(422)->assertJsonStructure(['success', 'errors' => ['file']]);

        $this->assertSame(0, Expense::where('user_id', $this->alice->id)->where('description', 'phpunit')->count());
    }

    public function test_html_and_svg_receipts_are_rejected(): void
    {
        foreach (['x.html' => '<script>alert(1)</script>', 'x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>'] as $name => $body) {
            $this->as($this->alice)->post(route('expense.create'), $this->payload(['file' => UploadedFile::fake()->createWithContent($name, $body)]))
                ->assertStatus(422);
        }
    }

    public function test_stored_receipt_gets_server_generated_name_and_extension_never_the_clients(): void
    {
        // A genuine PNG whose CLIENT-supplied name is `receipt.html`. `mimes:` checks the
        // real content (png), so validation passes — the old code then stored it as
        // `.html` under public/ (stored XSS). It must now be stored as `.png`, named
        // by the server, on the private disk.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        // The fake upload derives its type from the NAME; mimeType() sets what a real
        // upload's finfo sniffing would report for these bytes.
        $file = UploadedFile::fake()->createWithContent('receipt.html', $png)->mimeType('image/png');

        $this->as($this->alice)->post(route('expense.create'), $this->payload(['file' => $file]))->assertOk();

        $expense = Expense::where('user_id', $this->alice->id)->latest('id')->firstOrFail();

        $this->assertStringStartsWith("expense/{$this->tenantId}/", $expense->file);
        $this->assertStringEndsWith('.png', $expense->file);
        $this->assertStringNotContainsString('html', $expense->file);
        $this->assertStringNotContainsString('receipt', $expense->file);
        Storage::disk('local')->assertExists($expense->file);
    }

    public function test_valid_pdf_is_accepted(): void
    {
        $this->as($this->alice)->post(route('expense.create'), $this->payload(['file' => UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')]))
            ->assertOk();
    }

    public function test_receipt_url_is_signed_for_private_files_and_public_for_legacy(): void
    {
        $svc = app(ExpenseAttachmentService::class);

        $private = $svc->url('expense/7/2026/abc.pdf', 5);
        $this->assertStringContainsString('/expense/file/5', $private);
        $this->assertStringContainsString('signature=', $private);

        // Every older path shape found in real data stays a plain public URL and
        // must NOT be mistaken for a private file (that would 404 the receipt).
        foreach (['uploads/expense/file/old.png', 'expenses/2026/05/old.pdf', 'expenses/old.jpeg', 'uploadsexpenses/old.png'] as $legacy) {
            $this->assertTrue($svc->isLegacy($legacy), $legacy);
            $this->assertStringEndsWith($legacy, $svc->url($legacy, 5));
            $this->assertStringNotContainsString('signature=', $svc->url($legacy, 5));
        }
        $this->assertFalse($svc->isLegacy('expense/7/2026/9b1f.pdf'));
        $this->assertNull($svc->url(null, 5));
    }

    public function test_legacy_resolution_refuses_traversal_and_non_receipt_files(): void
    {
        $svc = app(ExpenseAttachmentService::class);

        $this->assertNull($svc->legacyFullPath('../.env'));
        $this->assertNull($svc->legacyFullPath('index.php'));
        $this->assertNull($svc->legacyFullPath('uploads/expense/../../../.env'));
        $this->assertNull($svc->legacyFullPath('expenses/does-not-exist.pdf'));
    }

    public function test_receipt_download_requires_a_valid_signature(): void
    {
        $path = "expense/{$this->tenantId}/" . date('Y') . '/1.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $expense = $this->makeExpense($this->alice, ['file' => $path]);

        // No signature -> forbidden, even for the owner.
        $this->as($this->alice)->get(route('expense.file', ['id' => $expense->id]))->assertStatus(403);

        // Signed URL works with no session at all (how the mobile app loads it).
        $signed = app(ExpenseAttachmentService::class)->url($expense->file, $expense->id);
        $this->app['auth']->forgetGuards();
        $this->get($signed)->assertOk();

        // Tampering with the id or the tenant invalidates the signature.
        $this->get(str_replace("/expense/file/{$expense->id}", '/expense/file/' . ($expense->id + 1), $signed))->assertStatus(403);
        $this->get(str_replace("tenant={$this->tenantId}", 'tenant=' . ($this->tenantId + 1), $signed))->assertStatus(403);

        // A validly signed URL for the right id but a different tenant finds nothing.
        $otherTenant = URL::temporarySignedRoute('expense.file', now()->addMinutes(5), ['id' => $expense->id, 'tenant' => $this->tenantId + 999]);
        $this->get($otherTenant)->assertStatus(404);
    }

    public function test_expense_pages_render_receipt_links_for_private_and_legacy_files(): void
    {
        $private = "expense/{$this->tenantId}/" . date('Y') . '/abc.png';
        Storage::disk('local')->put($private, 'x');
        $this->makeExpense($this->alice, ['file' => $private, 'description' => 'phpunit-private']);
        $this->makeExpense($this->alice, ['file' => 'expenses/2026/05/old.pdf', 'description' => 'phpunit-legacy']);

        // Employee's own list and the manager/HR "view all" list both build file_url.
        $own = $this->as($this->alice)->get(route('expense.index'))->assertOk();
        $own->assertSee('/expense/file/', false)->assertSee('signature=', false)->assertSee('expenses/2026/05/old.pdf', false);

        $this->as($this->hr)->get(route('expense.view-all'))->assertOk()->assertSee('signature=', false);
    }

    // ------------------------------------------------------------- 2. amounts

    public function test_amount_rules_reject_zero_negative_huge_and_over_precise_values(): void
    {
        foreach (['0', '-5', '-0.01', '10000000', '1.234', 'abc'] as $bad) {
            $data = $this->payload(['amount' => $bad]);
            $req = StoreExpenseRequest::create('/', 'POST', $data);
            $v = Validator::make($data, $req->rules(), $req->messages());
            $this->assertTrue($v->errors()->has('amount'), "amount '{$bad}' should be rejected");
        }

        foreach (['0.01', '100', '100.5', '9999999.99'] as $ok) {
            $data = $this->payload(['amount' => $ok]);
            $req = StoreExpenseRequest::create('/', 'POST', $data);
            $v = Validator::make($data, $req->rules(), $req->messages());
            $this->assertFalse($v->errors()->has('amount'), "amount '{$ok}' should be accepted");
        }
    }

    public function test_web_create_rejects_a_negative_amount_and_accepts_a_valid_one(): void
    {
        $this->as($this->alice)->post(route('expense.create'), $this->payload(['amount' => '-50']))
            ->assertStatus(422)->assertJsonStructure(['success', 'errors' => ['amount']]);

        $this->as($this->alice)->post(route('expense.create'), $this->payload(['amount' => '0']))->assertStatus(422);

        $this->as($this->alice)->post(route('expense.create'), $this->payload(['amount' => '100.50']))->assertOk();
    }

    public function test_future_dated_settlement_is_rejected_but_future_advance_is_allowed(): void
    {
        $future = now()->addDays(5)->toDateString();

        $this->as($this->alice)->post(route('expense.create'), $this->payload(['requirement_type' => 'settlement', 'date' => $future]))->assertStatus(422);
        $this->as($this->alice)->post(route('expense.create'), $this->payload(['requirement_type' => 'advance', 'date' => $future]))->assertOk();
    }

    public function test_database_check_refuses_a_non_positive_expense_amount(): void
    {
        $this->expectException(QueryException::class);
        $this->makeExpense($this->alice, ['amount' => -5]);
    }

    // ------------------------------------------------- 3. locking / idempotency

    public function test_double_approving_a_settlement_deducts_the_balance_only_once(): void
    {
        $this->setBalance($this->alice, 500, 500);
        $expense = $this->makeExpense($this->alice, ['requirement_type' => 'settlement', 'amount' => 100]);

        $this->as($this->hr)->post(route('expense.update-status', $expense->id), ['status' => 'approved'])->assertOk();
        $this->as($this->hr)->post(route('expense.update-status', $expense->id), ['status' => 'approved'])->assertStatus(400);

        $this->assertEquals(400.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
        $this->assertSame(1, DB::table('expense_transactions')->where('expense_id', $expense->id)->count());
    }

    public function test_settlement_larger_than_balance_is_refused_and_leaves_no_partial_state(): void
    {
        $this->setBalance($this->alice, 50, 50);
        $expense = $this->makeExpense($this->alice, ['requirement_type' => 'settlement', 'amount' => 100]);

        $this->as($this->hr)->post(route('expense.update-status', $expense->id), ['status' => 'approved'])
            ->assertStatus(400)->assertJsonPath('success', false);

        $this->assertSame('pending', $expense->fresh()->status);
        $this->assertEquals(50.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_manual_complete_status_is_no_longer_accepted(): void
    {
        $expense = $this->makeExpense($this->alice);

        $this->as($this->hr)->post(route('expense.update-status', $expense->id), ['status' => 'complete'])->assertStatus(422);
        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_same_idempotency_key_records_the_payment_once(): void
    {
        $this->setBalance($this->alice, 0, 0);
        $expense = $this->makeExpense($this->alice, ['status' => 'approved', 'amount' => 1000]);

        $body = [
            'payment_type' => 'advance', 'advance_expense_id' => $expense->id, 'amount' => '400',
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash', 'idempotency_key' => 'phpunit-key-1',
        ];

        $this->as($this->hr)->postJson(route('expense.payments.store'), $body)->assertOk()->assertJsonPath('success', true);
        $this->as($this->hr)->postJson(route('expense.payments.store'), $body)->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, ExpensePayment::where('expense_id', $expense->id)->count());
        $this->assertEquals(400.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_overpayment_is_refused_and_full_payment_completes_the_expense(): void
    {
        $this->setBalance($this->alice, 0, 0);
        $expense = $this->makeExpense($this->alice, ['status' => 'approved', 'amount' => 1000]);

        $pay = fn (string $amount) => $this->as($this->hr)->postJson(route('expense.payments.store'), [
            'payment_type' => 'advance', 'advance_expense_id' => $expense->id, 'amount' => $amount,
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash',
        ]);

        $pay('400')->assertOk();
        $pay('700')->assertStatus(400)->assertJsonPath('success', false);      // only 600 left
        $this->assertSame('approved', $expense->fresh()->status);

        $pay('600')->assertOk()->assertJsonPath('data.is_fully_paid', true);
        $this->assertSame('complete', $expense->fresh()->status);
        $this->assertEquals(1000.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
    }

    public function test_deleting_an_advance_payment_the_employee_already_spent_is_refused(): void
    {
        $this->setBalance($this->alice, 0, 0);
        $expense = $this->makeExpense($this->alice, ['status' => 'approved', 'amount' => 1000]);

        $this->as($this->hr)->postJson(route('expense.payments.store'), [
            'payment_type' => 'advance', 'advance_expense_id' => $expense->id, 'amount' => '400',
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash',
        ])->assertOk();
        $payment = ExpensePayment::where('expense_id', $expense->id)->firstOrFail();

        // Employee has since spent the advance down to zero via settlements.
        UserExpenseBalance::where('user_id', $this->alice->id)->update(['current_balance' => 0]);

        $this->as($this->hr)->deleteJson(route('expense.payments.destroy', $payment->id))->assertStatus(409);

        $this->assertNotNull(ExpensePayment::find($payment->id));
        $this->assertEquals(0.00, (float) UserExpenseBalance::where('user_id', $this->alice->id)->value('current_balance'));
    }

    // ------------------------------------------------------- 4. authorization

    public function test_a_user_cannot_edit_someone_elses_pending_expense(): void
    {
        $expense = $this->makeExpense($this->alice, ['amount' => 100]);

        $this->as($this->bob)->post(route('expense.update', $expense->id), $this->payload(['amount' => '999']))->assertStatus(403);
        $this->assertEquals(100.00, (float) $expense->fresh()->amount);

        $this->as($this->alice)->post(route('expense.update', $expense->id), $this->payload(['amount' => '250']))->assertOk();
        $this->assertEquals(250.00, (float) $expense->fresh()->amount);
    }

    public function test_a_user_cannot_delete_someone_elses_expense(): void
    {
        $expense = $this->makeExpense($this->alice);

        $this->as($this->bob)->deleteJson(route('expense.delete', $expense->id))->assertStatus(403);
        $this->assertNotNull(Expense::find($expense->id));
    }

    public function test_payment_reads_are_scoped_to_the_expense_owner(): void
    {
        $expense = $this->makeExpense($this->alice, ['status' => 'approved']);

        foreach ([
            route('expense.payments', $expense->id),
            route('expense.payments.by-expense', $expense->id),
            route('expense.payments.advance-summary', $expense->id),
        ] as $url) {
            $this->as($this->bob)->getJson($url)->assertStatus(403);
            $this->as($this->alice)->getJson($url)->assertOk();
        }
    }

    public function test_employees_cannot_record_change_or_delete_payments(): void
    {
        $expense = $this->makeExpense($this->alice, ['status' => 'approved']);
        $payment = ExpensePayment::create([
            'tenant_id' => $this->tenantId, 'expense_id' => $expense->id, 'payment_date' => now()->toDateString(),
            'amount' => 10, 'payment_mode' => 'cash', 'paid_by' => $this->hr->id,
        ]);

        $this->as($this->alice)->postJson(route('expense.payments.store'), [
            'payment_type' => 'advance', 'advance_expense_id' => $expense->id, 'amount' => '10',
            'payment_date' => now()->toDateString(), 'payment_mode' => 'cash',
        ])->assertStatus(403);
        $this->as($this->alice)->putJson(route('expense.payments.update', $payment->id), [
            'payment_date' => now()->toDateString(), 'amount' => '5', 'payment_mode' => 'cash',
        ])->assertStatus(403);
        $this->as($this->alice)->deleteJson(route('expense.payments.destroy', $payment->id))->assertStatus(403);
        $this->as($this->alice)->getJson(route('expense.payments.user-advances'))->assertStatus(403);
    }

    public function test_payment_page_hides_company_wide_payables_from_non_payers(): void
    {
        $this->makeExpense($this->bob, ['status' => 'approved', 'amount' => 777]);

        $this->as($this->alice)->get(route('expense.payments.index'))
            ->assertOk()
            ->assertViewHas('approvedAdvances', fn ($v) => $v->isEmpty())
            ->assertViewHas('approvedReimbursements', fn ($v) => $v->isEmpty())
            ->assertViewHas('employees', fn ($v) => $v->isEmpty());
    }

    public function test_pending_advances_route_is_reachable_for_payers(): void
    {
        // Was shadowed by the /{expenseId} wildcard and unreachable.
        $this->as($this->hr)->getJson(route('expense.payments.pending-advances'))->assertOk()->assertJsonPath('success', true);
        $this->as($this->hr)->getJson(route('expense.payments.user-advances'))->assertOk()->assertJsonPath('success', true);
    }

    public function test_a_manager_scope_cannot_approve_an_employee_outside_their_team(): void
    {
        $manager = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'manager')->where('status', '1')->first();
        if (! $manager) {
            $this->markTestSkipped('no manager in the fixture tenant');
        }
        $stranger = User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')
            ->whereNotIn('id', User::managedBy($manager->id)->pluck('id'))->first();
        if (! $stranger) {
            $this->markTestSkipped('every employee reports to the fixture manager');
        }

        $expense = $this->makeExpense($stranger);

        $this->as($manager)->post(route('expense.update-status', $expense->id), ['status' => 'approved'])->assertStatus(403);
        $this->assertSame('pending', $expense->fresh()->status);
    }
}
