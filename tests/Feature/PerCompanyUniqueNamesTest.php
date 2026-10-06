<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master-data names are unique per company only: company B can use a name
 * company A already has, but the same company cannot use it twice. Dev DB,
 * rolled back.
 */
class PerCompanyUniqueNamesTest extends TestCase
{
    private User $a;
    private User $b;

    protected function setUp(): void
    {
        parent::setUp();
        $admins = User::withoutGlobalScopes()->where('role', 'admin')->where('status', 1)->whereNotNull('tenant_id')
            ->orderBy('id')->get()->unique('tenant_id')->values();
        if ($admins->count() < 2) {
            $this->markTestSkipped('Needs admins in two companies.');
        }
        [$this->a, $this->b] = [$admins[0], $admins[1]];
        DB::beginTransaction();

        // Both companies get every module these masters need.
        $sa = DB::table('super_admins')->value('id');
        foreach ([$this->a->tenant_id, $this->b->tenant_id] as $tenantId) {
            foreach (['custom_shift', 'fixed_shift', 'leave_management', 'branches', 'expense_management', 'loan_management', 'holiday', 'asset_management', 'attendance'] as $f) {
                DB::table('tenant_feature_overrides')->where('tenant_id', $tenantId)->where('feature_key', $f)->delete();
                DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenantId, 'feature_key' => $f, 'is_enabled' => 1,
                    'reason' => 'unique-name test', 'overridden_by' => $sa, 'created_at' => now(), 'updated_at' => now()]);
            }
            app(\App\Services\FeatureService::class)->bust((int) $tenantId);
        }
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ([$this->a->tenant_id ?? null, $this->b->tenant_id ?? null] as $tenantId) {
            if ($tenantId) {
                app(\App\Services\FeatureService::class)->bust((int) $tenantId);
            }
        }
        parent::tearDown();
    }

    private function as(User $u): static
    {
        auth()->forgetGuards();
        app()->forgetInstance('current_tenant');

        return $this->actingAs($u)->withSession(['tenant_id' => $u->tenant_id]);
    }

    /** @return array<string, array{0:string, 1:callable}> route => payload(name, user) */
    public static function masters(): array
    {
        return [
            'shift' => ['/shift/store', fn ($n) => ['name' => $n, 'start_time' => '09:00', 'end_time' => '18:00', 'grace_minutes' => 10, 'break_time' => 0]],
            'leave type' => ['/leave-type/create', fn ($n) => ['name' => $n, 'credit_type' => 'yearly', 'credit_value' => 12, 'max_carry_forward' => 12]],
            'branch' => ['/branches/store', fn ($n) => ['name' => $n, 'description' => 'x']],
            'designation' => ['/designation/create', fn ($n) => ['name' => $n, 'description' => 'x']],
            'expense type' => ['/expense-type/create', fn ($n) => ['name' => $n, 'description' => 'x']],
            'attendance location' => ['/attendance-locations/store', fn ($n) => ['name' => $n, 'status' => 1, 'latitude' => 28.6, 'longitude' => 77.2, 'radius' => 100]],
            'loan category' => ['/loan/categories', fn ($n) => ['name' => $n, 'code' => 'UQ' . substr(md5($n), 0, 6), 'kind' => 'loan', 'max_amount' => 10000, 'max_tenure_months' => 12, 'requires_approval' => 1, 'status' => 1]],
            'holiday' => ['/holidays/create', fn ($n) => ['name' => $n, 'start_date' => now()->year . '-12-25', 'end_date' => now()->year . '-12-25']],
            'asset category' => ['/asset-categories', fn ($n) => ['name' => $n]],
            'asset type' => ['/asset-types', fn ($n) => ['name' => $n]],
            'vendor' => ['/asset-vendors', fn ($n) => ['name' => $n]],
        ];
    }

    private function ok($res): bool
    {
        return $res->status() < 300 || ($res->status() === 302 && ! session()->has('errors'));
    }

    public function test_same_name_allowed_in_another_company_but_not_twice_in_one(): void
    {
        $fails = [];
        foreach (self::masters() as $label => [$uri, $payload]) {
            $name = 'UQ Test ' . $label . ' ' . uniqid();

            $r1 = $this->as($this->a)->withHeader('Accept', 'application/json')->post($uri, $payload($name));
            $r2 = $this->as($this->b)->withHeader('Accept', 'application/json')->post($uri, $payload($name));
            $r3 = $this->as($this->b)->withHeader('Accept', 'application/json')->post($uri, $payload($name));

            if (! $this->ok($r1)) {
                $fails[] = "$label: company A create failed ({$r1->status()}) " . substr($r1->getContent(), 0, 200);
            }
            if (! $this->ok($r2)) {
                $fails[] = "$label: company B blocked by company A's name ({$r2->status()}) " . substr($r2->getContent(), 0, 200);
            }
            if ($this->ok($r3)) {
                $fails[] = "$label: company B could create the same name twice";
            }
        }

        $this->assertSame([], $fails, implode("\n", $fails));
    }

    public function test_loan_category_code_is_unique_per_company(): void
    {
        $code = 'LC' . substr(uniqid(), -6);
        $p = fn ($n) => ['name' => $n, 'code' => $code, 'kind' => 'loan', 'max_amount' => 10000, 'max_tenure_months' => 12, 'requires_approval' => 1, 'status' => 1];

        $this->assertTrue($this->ok($this->as($this->a)->withHeader('Accept', 'application/json')->post('/loan/categories', $p('Code A ' . uniqid()))));
        $this->assertTrue($this->ok($this->as($this->b)->withHeader('Accept', 'application/json')->post('/loan/categories', $p('Code B ' . uniqid()))), 'company B blocked by company A\'s code');
        $this->assertFalse($this->ok($this->as($this->b)->withHeader('Accept', 'application/json')->post('/loan/categories', $p('Code B2 ' . uniqid()))), 'same code twice in one company');
    }
}
