<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use Tests\TestCase;

/**
 * Multi reporting-head support (2026-09-19). Runs against the shared dev DB
 * (no RefreshDatabase) — mirrors the cleanup pattern in ApprovalWorkflowTest.
 */
class EmployeeMultiReportingHeadTest extends TestCase
{
    private function tenantId(): int
    {
        return (int) \DB::table('users')
            ->whereNotNull('tenant_id')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= 4')
            ->orderByRaw('count(*) desc')
            ->value('tenant_id');
    }

    private function users(int $n): array
    {
        return User::where('tenant_id', $this->tenantId())->limit($n)->pluck('id')->all();
    }

    private function clearReportingHeads(int $userId): void
    {
        \DB::table('user_reporting_heads')->where('user_id', $userId)->delete();
    }

    /**
     * sync() writes pivot rows via a raw query, bypassing
     * UserReportingHead's TenantTrait::creating() hook — tenant_id must be
     * passed explicitly, same as UserController::syncReportingHeads().
     */
    private function syncData(array $headIdsWithPrimaryFlag): array
    {
        $syncData = [];
        foreach ($headIdsWithPrimaryFlag as $headId => $isPrimary) {
            $syncData[$headId] = ['is_primary' => $isPrimary, 'tenant_id' => $this->tenantId()];
        }

        return $syncData;
    }

    public function test_sync_stores_full_set_with_first_as_primary(): void
    {
        [$employeeId, $head1, $head2, $head3] = array_pad($this->users(4), 4, $this->users(1)[0]);
        $this->clearReportingHeads($employeeId);
        $employee = User::find($employeeId);

        $employee->reportingHeads()->sync($this->syncData([
            $head1 => true,
            $head2 => false,
            $head3 => false,
        ]));

        $stored = $employee->reportingHeads()->orderByDesc('is_primary')->get();
        $this->assertSame(3, $stored->count());
        $this->assertSame($head1, $stored->first()->id);
        $this->assertTrue((bool) $stored->first()->pivot->is_primary);

        $this->clearReportingHeads($employeeId);
    }

    public function test_managed_by_recognizes_any_reporting_head_not_just_primary(): void
    {
        [$employeeId, $primaryHead, $secondaryHead] = array_pad($this->users(3), 3, $this->users(1)[0]);
        $this->clearReportingHeads($employeeId);
        $employee = User::find($employeeId);

        $employee->reportingHeads()->sync($this->syncData([
            $primaryHead => true,
            $secondaryHead => false,
        ]));

        $this->assertTrue(User::managedBy($primaryHead)->where('id', $employeeId)->exists());
        $this->assertTrue(User::managedBy($secondaryHead)->where('id', $employeeId)->exists());

        $unrelated = User::where('tenant_id', $this->tenantId())
            ->whereNotIn('id', [$employeeId, $primaryHead, $secondaryHead])
            ->value('id');
        if ($unrelated) {
            $this->assertFalse(User::managedBy($unrelated)->where('id', $employeeId)->exists());
        }

        $this->clearReportingHeads($employeeId);
    }

    public function test_approval_service_resolves_all_reporting_heads(): void
    {
        [$employeeId, $head1, $head2] = array_pad($this->users(3), 3, $this->users(1)[0]);
        $this->clearReportingHeads($employeeId);
        $employee = User::find($employeeId);

        $employee->reportingHeads()->sync($this->syncData([
            $head1 => true,
            $head2 => false,
        ]));

        $step = new ApprovalWorkflowStep(['approver_type' => 'reporting_head', 'quorum' => 'first-wins']);
        $svc = app(ApprovalService::class);

        $approvers = $svc->resolveApprovers($step, $employee->fresh());

        $this->assertTrue($approvers->contains($head1));
        $this->assertTrue($approvers->contains($head2));
        $this->assertSame(2, $approvers->count());

        $this->clearReportingHeads($employeeId);
    }
}
