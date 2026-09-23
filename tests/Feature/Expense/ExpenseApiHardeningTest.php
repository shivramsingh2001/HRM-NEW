<?php

namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Mobile (Flutter) expense API — exercised through the real JWT 'api' guard.
 * The API used to accept ANY file type and negative amounts; it now shares
 * StoreExpenseRequest / ExpenseService with the web panel.
 *
 * Shared dev DB, rolled back via DatabaseTransactions; private disk faked.
 */
class ExpenseApiHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private User $employee;
    private User $approver;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('users')
            ->where('status', '1')->where('role', 'employee')->whereNotNull('tenant_id')
            ->select('tenant_id')->groupBy('tenant_id')
            ->havingRaw('count(*) >= 1')->orderByRaw('count(*) desc')->value('tenant_id');

        $employee = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('role', 'employee')->where('status', '1')->first() : null;
        $approver = $this->tenantId ? User::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->whereIn('role', ['admin', 'hr'])->where('status', '1')->first() : null;
        $typeId = $this->tenantId ? ExpenseType::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('status', 1)->value('id') : null;

        if (! $employee || ! $approver || ! $typeId) {
            $this->markTestSkipped('fixture users / expense type missing in the dev DB');
        }

        [$this->employee, $this->approver, $this->typeId] = [$employee, $approver, (int) $typeId];

        app()->instance('current_tenant', Tenant::find($this->tenantId));
        Storage::fake('local');
    }

    /** Mirrors what CheckSingleDeviceLogin expects for this user. */
    private function headersFor(User $user): array
    {
        $headers = ['Authorization' => 'Bearer ' . auth('api')->login($user), 'Accept' => 'application/json'];
        if ($user->last_login_token !== null) {
            $headers['Device-Token'] = $user->last_login_token;
        }

        return $headers;
    }

    private function body(array $over = []): array
    {
        return $over + [
            'expense_type' => $this->typeId, 'requirement_type' => 'reimbursement', 'amount' => '75.25',
            'date' => now()->subDay()->toDateString(), 'description' => 'phpunit-api',
        ];
    }

    public function test_api_rejects_negative_zero_and_over_precise_amounts_with_the_legacy_envelope(): void
    {
        foreach (['-100', '0', '5.999'] as $bad) {
            $res = $this->withHeaders($this->headersFor($this->employee))->post('/api/create-expense', $this->body(['amount' => $bad]));
            $res->assertStatus(200)->assertJsonPath('success', false); // legacy API: HTTP 200 + success=false
            $this->assertNotEmpty($res->json('message'));
        }

        $this->assertSame(0, Expense::where('description', 'phpunit-api')->count());
    }

    public function test_api_rejects_non_receipt_files(): void
    {
        foreach (['shell.php' => '<?php echo 1;', 'page.html' => '<script>1</script>', 'run.exe' => 'MZ'] as $name => $content) {
            $this->withHeaders($this->headersFor($this->employee))
                ->post('/api/create-expense', $this->body(['file' => UploadedFile::fake()->createWithContent($name, $content)]))
                ->assertStatus(200)->assertJsonPath('success', false);
        }

        $this->assertSame(0, Expense::where('description', 'phpunit-api')->count());
    }

    public function test_api_accepts_a_valid_receipt_and_lists_it_with_a_signed_url_not_a_raw_path(): void
    {
        $this->withHeaders($this->headersFor($this->employee))
            ->post('/api/create-expense', $this->body(['file' => UploadedFile::fake()->create('bill.pdf', 40, 'application/pdf')]))
            ->assertStatus(200)->assertJsonPath('success', true);

        $stored = Expense::where('description', 'phpunit-api')->firstOrFail();
        Storage::disk('local')->assertExists($stored->file);

        $res = $this->withHeaders($this->headersFor($this->employee))->getJson('/api/view-expense')->assertOk();
        $row = collect($res->json('data.expenses'))->firstWhere('id', $stored->id);

        $this->assertNotNull($row);
        $this->assertStringContainsString('/expense/file/' . $stored->id, $row['file_url']);
        $this->assertStringContainsString('signature=', $row['file_url']);
        $this->assertArrayNotHasKey('file', $row, 'the raw private storage path must not be exposed');
    }

    public function test_api_approval_goes_through_the_shared_locked_service_and_cannot_repeat(): void
    {
        $expense = Expense::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'expense_type' => $this->typeId,
            'amount' => 100, 'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement',
            'status' => 'pending', 'description' => 'phpunit-api',
        ]);

        $approve = fn () => $this->withHeaders($this->headersFor($this->approver))
            ->postJson('/api/team/expense-update', ['id' => $expense->id, 'status' => 'approved']);

        $approve()->assertStatus(200)->assertJsonPath('success', true);
        $approve()->assertStatus(200)->assertJsonPath('success', false)->assertJsonPath('message', 'This expense has already been processed.');

        // `complete` is no longer a valid manual transition.
        $this->withHeaders($this->headersFor($this->approver))
            ->postJson('/api/team/expense-update', ['id' => $expense->id, 'status' => 'complete'])
            ->assertStatus(200)->assertJsonPath('success', false);

        $this->assertSame('approved', $expense->fresh()->status);
    }

    public function test_api_lists_my_payments_with_their_voucher_and_counts_only_posted_ones(): void
    {
        $expense = Expense::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'expense_type' => $this->typeId,
            'amount' => 500, 'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement',
            'status' => 'approved', 'description' => 'phpunit-api',
        ]);

        $svc = app(\App\Services\Expense\ExpensePaymentService::class);
        $meta = ['payment_date' => now()->toDateString(), 'payment_mode' => 'upi', 'reference_number' => 'UPI-77'];
        $first = $svc->payBatch($this->approver, [['expense_id' => $expense->id, 'amount' => '200']], $meta, null, fn () => true);
        $second = $svc->payBatch($this->approver, [['expense_id' => $expense->id, 'amount' => '100']], $meta, null, fn () => true);
        $svc->voidBatch($this->approver, $second['batch']->id, 'sent twice', fn () => true);

        $res = $this->withHeaders($this->headersFor($this->employee))->getJson('/api/view-expense-payments')->assertOk();
        $mine = collect($res->json('data.payments'))->where('expense_number', $expense->fresh()->expense_number);

        $this->assertCount(2, $mine, 'the voided payment is listed too, so the app can strike it through');
        $this->assertEqualsCanonicalizing(['posted', 'voided'], $mine->pluck('status')->all());
        $this->assertContains($first['batch']->voucher_number, $mine->pluck('voucher_number')->all());

        // only POSTED payments count toward the total (other real payments may exist for this user)
        $posted = collect($res->json('data.payments'))->where('status', 'posted')->sum(fn ($p) => (float) $p['amount']);
        $this->assertEquals($posted, (float) $res->json('data.total_received'));

        // strictly the caller's own payments
        $other = $this->withHeaders($this->headersFor($this->approver))->getJson('/api/view-expense-payments')->assertOk();
        $this->assertEmpty(collect($other->json('data.payments'))->where('expense_number', $expense->fresh()->expense_number));
    }

    public function test_an_employee_cannot_approve_via_the_api(): void
    {
        $expense = Expense::create([
            'tenant_id' => $this->tenantId, 'user_id' => $this->employee->id, 'expense_type' => $this->typeId,
            'amount' => 100, 'date' => now()->subDay()->toDateString(), 'requirement_type' => 'reimbursement',
            'status' => 'pending', 'description' => 'phpunit-api',
        ]);

        $res = $this->withHeaders($this->headersFor($this->employee))
            ->postJson('/api/team/expense-update', ['id' => $expense->id, 'status' => 'approved']);

        $res->assertJsonPath('success', false);
        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_team_view_is_permission_driven_and_closed_to_plain_employees(): void
    {
        $this->withHeaders($this->headersFor($this->employee))->getJson('/api/view-team-expense')->assertStatus(403);
        $this->withHeaders($this->headersFor($this->approver))->getJson('/api/view-team-expense')->assertOk()->assertJsonPath('success', true);
    }
}
