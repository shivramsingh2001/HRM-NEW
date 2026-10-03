<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\User;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\UserJobDetail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreditWeeklyLeaves extends Command
{
    protected $signature = 'leaves:credit-weekly {tenant_id?} {--dry-run : Simulate without making changes}';
    protected $description = 'Credit weekly leaves to all employees based on their assigned leave types';

    public function handle()
    {
        $tenantIds = $this->argument('tenant_id');

        if (empty($tenantIds)) {
            $tenantIds = Tenant::where('leaves', 1)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();

            $this->info("Auto-fetched " . count($tenantIds) . " active tenants");
        }

        if (empty($tenantIds)) {
            $this->error("No active tenants found");
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN MODE - No actual changes will be made");
        }

        foreach ($tenantIds as $tenantId) {
            $this->info("Processing weekly leave credit for tenant: {$tenantId}");
            $this->creditWeeklyLeavesForTenant($tenantId);
        }

        return 0;
    }

    protected function creditWeeklyLeavesForTenant($tenantId)
    {
        try {
            DB::beginTransaction();

            // Get all active users for this tenant
            $users = User::where('tenant_id', $tenantId)
                ->where('role',"!=","admin")
                ->where('status', '1')
                ->get();

            if ($users->isEmpty()) {
                $this->warn("No active users found for tenant: {$tenantId}");
                DB::commit();
                return;
            }

            $creditedCount = 0;
            $skippedCount = 0;
            $transactionDate = Carbon::now();

            foreach ($users as $user) {
                // Get user's assigned leave types from jobDetail
                $assignedLeaveTypeIds = $this->getUserAssignedLeaveTypes($user, $tenantId);
                
                if (empty($assignedLeaveTypeIds)) {
                    $this->warn("No leave types assigned to user ID: {$user->id} - {$user->name}");
                    $skippedCount++;
                    continue;
                }

                // Get leave types that are active and have weekly credit_type
                $leaveTypes = LeaveType::where('tenant_id', $tenantId)
                    ->whereIn('id', $assignedLeaveTypeIds)
                    ->where('status', 1)
                    ->where('credit_type', 'weekly')
                    ->get();

                if ($leaveTypes->isEmpty()) {
                    $this->warn("No weekly leave types found for user ID: {$user->id}");
                    $skippedCount++;
                    continue;
                }

                // Credit each assigned leave type
                foreach ($leaveTypes as $leaveType) {
                    // Check if leaves already credited for this week for this specific leave type
                    $alreadyCredited = LeaveTransaction::where('user_id', $user->id)
                        ->where('tenant_id', $tenantId)
                        ->where('leave_type', $leaveType->id)
                        ->where('transaction_type', 'add')
                        ->where('remarks', 'LIKE', '%Weekly credit added%')
                        ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                        ->exists();

                    if ($alreadyCredited) {
                        $this->info("Leaves already credited for user ID: {$user->id}, leave type: {$leaveType->name} this week");
                        continue;
                    }

                    $this->creditLeaveForUser($user, $leaveType, $transactionDate);
                    $creditedCount++;
                }
            }

            if (!$this->option('dry-run')) {
                DB::commit();
                $this->info("✓ Weekly leaves credited successfully for tenant: {$tenantId}");
                $this->info("Credited count: {$creditedCount} leave entries");
                $this->info("Skipped users: {$skippedCount}");
            } else {
                DB::rollBack();
                $this->info("DRY RUN - Would have credited {$creditedCount} leave entries for tenant: {$tenantId}");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to credit leaves for tenant {$tenantId}: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }

    protected function getUserAssignedLeaveTypes($user, $tenantId)
    {
        $assignedLeaveTypeIds = [];
        
        // Get user's job detail
        $jobDetail = UserJobDetail::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();
        
        // Check if user has jobDetail and leave_assigned column
        if ($jobDetail && !empty($jobDetail->leave_assigned)) {
            $leaveAssigned = $jobDetail->leave_assigned;
            
            // Handle different JSON formats
            if (is_string($leaveAssigned)) {
                $leaveAssigned = json_decode($leaveAssigned, true);
            }
            
            // Handle both array formats: [1,2,3] or {"leave_types": [1,2,3]}
            if (is_array($leaveAssigned)) {
                if (isset($leaveAssigned['leave_types'])) {
                    $assignedLeaveTypeIds = $leaveAssigned['leave_types'];
                } else {
                    $assignedLeaveTypeIds = $leaveAssigned;
                }
            }
        }
        
        // If no leave types assigned, get all active leave types for the tenant
        if (empty($assignedLeaveTypeIds)) {
            $assignedLeaveTypeIds = LeaveType::where('tenant_id', $tenantId)
                ->where('status', 1)
                ->pluck('id')
                ->toArray();
            
            if (!empty($assignedLeaveTypeIds)) {
                $this->warn("No assigned leave types found for user ID: {$user->id}, using all active leave types");
            }
        }
        
        return $assignedLeaveTypeIds;
    }

    protected function creditLeaveForUser($user, $leaveType, $transactionDate)
    {
        // This employee's custom leave rules (Employee 360 → Policies): a type switched
        // off for them is not credited; a custom credit replaces the type's.
        $employeePolicy = app(\App\Services\EmployeePolicyService::class);
        if (! $employeePolicy->leaveAllowed((int) $user->tenant_id, (int) $user->id, (int) $leaveType->id)) {
            $this->info("Skipped {$leaveType->name} for user ID: {$user->id} — switched off for this employee");

            return;
        }

        // Get current balance for this specific user and leave type
        $balance = LeaveBalance::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('leave_type_id', $leaveType->id)
            ->first();

        if (!$balance) {
            // Create balance record for this specific leave type if doesn't exist
            $balance = LeaveBalance::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("Created new balance record for user: {$user->name} - Leave Type: {$leaveType->name}");
        }

        $beforeBalance = (float) $balance->balance;
        $creditValue = (float) $employeePolicy->leaveRule((int) $user->tenant_id, (int) $user->id, $leaveType)->credit_value;
        $afterBalance = $beforeBalance + $creditValue;

        if (!$this->option('dry-run')) {
            // Update balance for this specific leave type
            $balance->balance = $afterBalance;
            $balance->updated_at = now();
            $balance->save();

            // Create transaction record
            LeaveTransaction::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_type' => $leaveType->id,
                'transaction_type' => 'add',
                'total_leaves' => $creditValue,
                'leaves_count' => $creditValue,
                'before_leaves' => $beforeBalance,
                'after_leaves' => $afterBalance,
                'transaction_date' => $transactionDate,
                'leave_detail' => 'paid',
                'remarks' => "Weekly credit added - {$leaveType->name}",
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->info(($this->option('dry-run') ? "[DRY RUN] Would credit " : "Credited ") . 
            "{$creditValue} {$leaveType->name} to user: {$user->name} " .
            "(Balance: {$beforeBalance} → {$afterBalance})");
    }
}