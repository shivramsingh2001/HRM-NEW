<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogger;
use App\Services\RbacService;

class LeaveCreditController extends Controller
{
     public function index()
    {
        $tenantId = session('tenant_id');
        $authUser = Auth::user();
    
        // Get summary data (keep these as is)
        $totalUsers = User::where('tenant_id', $tenantId)->where('role', "!=", "admin")->where('status', 1)->count();
        $usersWithBalance = LeaveBalance::where('tenant_id', $tenantId)->distinct('user_id')->count('user_id');
        $totalBalance = LeaveBalance::where('tenant_id', $tenantId)->sum('balance');
        $totalUsed = LeaveTransaction::where('tenant_id', $tenantId)->where('transaction_type', 'sub')->sum('leaves_count');
        $totalTransactions = LeaveTransaction::where('tenant_id', $tenantId)->count();
    
        $recentTransactions = LeaveTransaction::with(['user', 'leaveType'])
            ->where('tenant_id', $tenantId)
            ->where('transaction_type', 'add')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    
        $leaveTypes = LeaveType::where('tenant_id', $tenantId)->where('status', 1)->get();

        // For the "Manual Credit" modal's employee select
        $users = User::with('jobDetails')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        // Get PAGINATED users with their leave balances
        $allUsers = User::with(['jobDetails', 'leaveBalance.leaveType'])
            ->withSum('leaveBalance as total_balance', 'balance')
            ->where('tenant_id', $tenantId)
            ->where('role', "!=", "admin")
            ->where('status', 1)
            ->orderBy('name')
            ->paginate(10);
    
        // Calculate total balance for each user (sum of all leave types)
        foreach ($allUsers as $user) {
            $user->total_balance = optional($user->leaveBalance)->sum('balance') ?? 0;
        }
    
        // Count users with balance (for the badge)
        $usersWithBalanceCount = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where('role', "!=", "admin")
            ->whereHas('leaveBalance')
            ->count();
    
        // Get users without balance (for separate section)
        $usersWithoutBalance = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where('role', "!=", "admin")
            ->whereDoesntHave('leaveBalance')
            ->get();
    
        return view('client.leave.leave-credit.index', compact(
            'totalUsers',
            'usersWithBalance',
            'totalBalance',
            'totalUsed',
            'totalTransactions',
            'recentTransactions',
            'leaveTypes',
            'users',
            'allUsers',
            'usersWithBalanceCount',
            'usersWithoutBalance'
        ));
    }

    /**
     * Show form for manual credit
     */
    public function createManual()
    {
        $tenantId = session('tenant_id');

        $users = User::with('jobDetails')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        return view('client.leave.leave-credit.manual', compact('users', 'leaveTypes'));
    }

    /**
     * Display reports page
     */
    public function reports(Request $request)
    {
        $tenantId = session('tenant_id');

        // Get filters
        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);
        $userId = $request->get('user_id');

        // One filter definition, reused for both queries below instead of
        // building the same where()/whereYear()/when() chain twice and
        // running two separate full scans per page load.
        $baseQuery = function () use ($tenantId, $year, $month, $userId) {
            return LeaveTransaction::query()
                ->where('leave_transactions.tenant_id', $tenantId)
                ->where('leave_transactions.transaction_type', 'add')
                ->whereYear('leave_transactions.created_at', $year)
                ->when($month != 'all', fn ($q) => $q->whereMonth('leave_transactions.created_at', $month))
                ->when($userId, fn ($q) => $q->where('leave_transactions.user_id', $userId));
        };

        // Summary by leave type: a grouped SQL aggregate (rows = distinct
        // leave types) instead of pulling every matching transaction into
        // PHP just to sum/group them there.
        $summaryByType = $baseQuery()
            ->join('leave_types', 'leave_transactions.leave_type', '=', 'leave_types.id')
            ->selectRaw('leave_types.id as leave_type_id, leave_types.name as type, SUM(leave_transactions.total_leaves) as total_credited, COUNT(*) as count')
            ->groupBy('leave_types.id', 'leave_types.name')
            ->get()
            ->keyBy('leave_type_id');

        $totalCreditedAmount = (float) $summaryByType->sum('total_credited');
        $totalTransactionsCount = (int) $summaryByType->sum('count');
        $averageCreditAmount = $totalTransactionsCount > 0
            ? $totalCreditedAmount / $totalTransactionsCount
            : 0;

        $paginatedTransactions = $baseQuery()
            ->with(['user', 'leaveType'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString(); // Preserve query parameters in pagination links

        // Get users for filter dropdown
        $users = User::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        // Available months for filter
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];

        return view('client.leave.leave-credit.reports', compact(
            'paginatedTransactions',  // 👈 For table display with pagination
            'summaryByType',          // 👈 For summary table
            'totalCreditedAmount',    // 👈 For stats card
            'totalTransactionsCount', // 👈 For stats card
            'averageCreditAmount',    // 👈 For stats card
            'users',
            'months',
            'month',
            'year',
            'userId'
        ));
    }

    /**
     * Display user transactions
     */
    public function userTransactions($userId)
    {
        $tenantId = session('tenant_id');

        $user = User::with('jobDetails')
            ->where('tenant_id', $tenantId)
            ->findOrFail($userId);

        $balance = LeaveBalance::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        // Get paginated transactions - THIS IS THE KEY CHANGE
        $transactions = LeaveTransaction::with('leaveType')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // 👈 Use paginate() not get()

        // Get all transactions for balance summary (keep this as Collection)
        $allTransactions = LeaveTransaction::with('leaveType')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->get();

        $balanceByType = $allTransactions->groupBy('leave_type')
            ->map(function ($items) {
                return [
                    'type' => $items->first()->leaveType->name ?? 'Unknown',
                    'total_credited' => $items->where('transaction_type', 'add')->sum('total_leaves'),
                    'total_used' => $items->where('transaction_type', 'sub')->sum('leaves_count'),
                    'balance' => $items->where('transaction_type', 'add')->sum('total_leaves') -
                        $items->where('transaction_type', 'sub')->sum('leaves_count')
                ];
            });

        return view(
            'client.leave.leave-credit.user-transactions',
            compact('user', 'balance', 'transactions', 'balanceByType')
        );
    }
    
     public function myTransactions()
    {
        $userId= Auth::id();
        $tenantId = session('tenant_id');

        $user = User::with('jobDetails')
            ->where('tenant_id', $tenantId)
            ->findOrFail($userId);

        $balance = LeaveBalance::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        // Get paginated transactions - THIS IS THE KEY CHANGE
        $transactions = LeaveTransaction::with('leaveType')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // 👈 Use paginate() not get()

        // Get all transactions for balance summary (keep this as Collection)
        $allTransactions = LeaveTransaction::with('leaveType')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->get();

        $balanceByType = $allTransactions->groupBy('leave_type')
            ->map(function ($items) {
                return [
                    'type' => $items->first()->leaveType->name ?? 'Unknown',
                    'total_credited' => $items->where('transaction_type', 'add')->sum('total_leaves'),
                    'total_used' => $items->where('transaction_type', 'sub')->sum('leaves_count'),
                    'balance' => $items->where('transaction_type', 'add')->sum('total_leaves') -
                        $items->where('transaction_type', 'sub')->sum('leaves_count')
                ];
            });

        return view(
            'client.leave.leave-credit.user-transactions',
            compact('user', 'balance', 'transactions', 'balanceByType')
        );
    }
    /**
     * Credit leaves for all employees based on frequency
     */
    public function creditLeaves(Request $request)
    {
        $frequency = $request->input('frequency'); // weekly, monthly, yearly
        $tenantId = $request->input('tenant_id') ?? session('tenant_id');
        $creditDate = $request->input('credit_date') ? Carbon::parse($request->credit_date) : Carbon::now();

        DB::beginTransaction();

        try {
            // Get all active leave types with the specified frequency
            $leaveTypes = LeaveType::where('tenant_id', $tenantId)
                ->where('status', 1)
                ->where('credit_type', $frequency)
                ->where('credit_value', '>', 0)
                ->get();
             
            if ($leaveTypes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No leave types found with frequency: {$frequency}"
                ]);
            }

            // Get all active users with their job details
            $users = User::with('jobDetails')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->get();

            $creditedCount = 0;

            foreach ($users as $user) {
                // Skip if no joining date
                if (!$user->joining_date) {
                    Log::warning("User {$user->id} has no joining date");
                    continue;
                }

                foreach ($leaveTypes as $leaveType) {
                    $credited = $this->creditLeaveForUser(
                        $user,
                        $leaveType,
                        $creditDate,
                        $frequency
                    );

                    if ($credited) {
                        $creditedCount++;
                    }
                }
            }

            DB::commit();

            Log::info("Leave credits processed", [
                'frequency' => $frequency,
                'tenant_id' => $tenantId,
                'credited_count' => $creditedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Leave credits processed successfully for {$frequency}",
                'credited_count' => $creditedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing leave credits: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error processing leave credits: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Credit leave for a specific user
     */
    private function creditLeaveForUser($user, $leaveType, $creditDate, $frequency)
    {
        // Check if user is eligible for this credit cycle
        if (!$this->isEligibleForCredit($user, $creditDate, $frequency)) {
            return false;
        }

        // This employee's custom leave rules (Employee 360 → Policies): a type switched
        // off for them is not credited; a custom credit replaces the type's.
        $employeePolicy = app(\App\Services\EmployeePolicyService::class);
        if (! $employeePolicy->leaveAllowed((int) $user->tenant_id, (int) $user->id, (int) $leaveType->id)) {
            return false;
        }
        $fullCredit = $employeePolicy->leaveRule((int) $user->tenant_id, (int) $user->id, $leaveType)->credit_value;

        // Calculate prorated credit if joining mid-cycle
        $creditValue = $this->calculateProratedCredit(
            $user->joining_date,
            $fullCredit,
            $frequency,
            $creditDate
        );

        if ($creditValue <= 0) {
            return false;
        }

        // Get or create leave balance (scoped per leave type — matching every
        // other balance lookup in this module; without leave_type_id here,
        // a tenant with 2+ auto-credited leave types would share one balance
        // row across all of them).
        $balance = LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'leave_type_id' => $leaveType->id,
            ],
            [
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        $beforeBalance = $balance->balance;
        $afterBalance = $beforeBalance + $creditValue;

        // Update balance
        $balance->update([
            'balance' => $afterBalance,
            'updated_at' => now()
        ]);

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
            'transaction_date' => $creditDate->toDateTimeString(),
            'leave_detail' => 'paid',
            'remarks' => ucfirst($frequency) . ' credit added' .
                ($creditValue != $fullCredit ? ' (prorated)' : ''),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return true;
    }

    /**
     * Check if user is eligible for credit
     */
    private function isEligibleForCredit($user, $creditDate, $frequency)
    {
        $joiningDate = Carbon::parse($user->joining_date);

        // If joining date is after credit date, not eligible
        if ($joiningDate->gt($creditDate)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate prorated credit for mid-cycle joiners
     */
    private function calculateProratedCredit($joiningDate, $fullCreditValue, $frequency, $creditDate)
    {
        $joiningDate = Carbon::parse($joiningDate);
        $cycleStartDate = $this->getCycleStartDate($creditDate, $frequency);

        // If joined before cycle start, give full credit
        if ($joiningDate->lte($cycleStartDate)) {
            return $fullCreditValue;
        }

        // Calculate prorated based on days worked in cycle
        switch ($frequency) {
            case 'weekly':
                $daysInCycle = 7;
                $daysWorked = $joiningDate->diffInDays($creditDate) + 1;
                break;

            case 'monthly':
                $daysInCycle = $creditDate->daysInMonth;
                $daysWorked = $joiningDate->diffInDays($creditDate) + 1;
                break;

            case 'yearly':
                $daysInCycle = 365; // Approximate
                $daysWorked = $joiningDate->diffInDays($creditDate) + 1;
                break;

            default:
                return $fullCreditValue;
        }

        // Ensure days worked is within cycle
        $daysWorked = max(0, min($daysWorked, $daysInCycle));

        // Calculate prorated value
        $proratedValue = ($fullCreditValue / $daysInCycle) * $daysWorked;

        return round($proratedValue, 2);
    }

    /**
     * Get cycle start date based on frequency
     */
    private function getCycleStartDate($date, $frequency)
    {
        switch ($frequency) {
            case 'weekly':
                return $date->copy()->startOfWeek();
            case 'monthly':
                return $date->copy()->startOfMonth();
            case 'yearly':
                return Carbon::create($date->year, config('leave.fiscal_year_start_month'), config('leave.fiscal_year_start_day'));
            default:
                return $date;
        }
    }

    /**
     * Handle new employee joining
     */
    public function handleNewJoiner(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::with('jobDetails')->findOrFail($request->user_id);

        if (!$user->joining_date) {
            return response()->json([
                'success' => false,
                'message' => 'User has no joining date'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Get all active leave types for this tenant
            $leaveTypes = LeaveType::where('tenant_id', $user->tenant_id)
                ->where('status', 1)
                ->where('credit_value', '>', 0)
                ->get();

            $creditedCount = 0;
            $joiningDate = Carbon::parse($user->joining_date);
            $currentDate = Carbon::now();

            foreach ($leaveTypes as $leaveType) {
                // Check if this leave type should be credited
                $nextCreditDate = $this->getNextCreditDate($joiningDate, $leaveType->credit_type);

                if ($nextCreditDate && $nextCreditDate->lte($currentDate)) {
                    $credited = $this->creditLeaveForUser(
                        $user,
                        $leaveType,
                        $nextCreditDate,
                        $leaveType->credit_type
                    );

                    if ($credited) {
                        $creditedCount++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave balance initialized for new joiner',
                'credited_count' => $creditedCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error handling new joiner: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next credit date based on joining date
     */
    private function getNextCreditDate($joiningDate, $creditType)
    {
        $joiningDate = Carbon::parse($joiningDate);

        switch ($creditType) {
            case 'weekly':
                // Next Monday after joining
                return $joiningDate->copy()->next(Carbon::MONDAY);

            case 'monthly':
                // First of next month
                return $joiningDate->copy()->addMonth()->startOfMonth();

            case 'yearly':
                // Next fiscal-year-start date (config('leave.fiscal_year_start_*'))
                $nextFiscalStart = Carbon::create($joiningDate->year, config('leave.fiscal_year_start_month'), config('leave.fiscal_year_start_day'));
                if ($joiningDate->gt($nextFiscalStart)) {
                    $nextFiscalStart->addYear();
                }
                return $nextFiscalStart;

            default:
                return null;
        }
    }

    /**
     * Manual credit adjustment
     */
    public function manualCredit(Request $request)
    {
        // Was completely ungated — any authenticated user could hit this
        // endpoint and credit anyone's leave balance. 'leave','manage' is
        // granted to admin/hr only in config/rbac.php.
        if (!app(RbacService::class)->can(Auth::user(), 'leave', 'manage')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to adjust leave balances.'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'credit_value' => 'required|numeric|min:0',
            'remarks' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $user = User::find($request->user_id);
            $leaveType = LeaveType::find($request->leave_type_id);

            $balance = LeaveBalance::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'leave_type_id' => $leaveType->id
                ],
                ['balance' => 0]
            );

            $beforeBalance = $balance->balance;
            $afterBalance = $beforeBalance + $request->credit_value;

            $balance->update(['balance' => $afterBalance]);

            LeaveTransaction::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_type' => $leaveType->id,
                'transaction_type' => 'add',
                'total_leaves' => $request->credit_value,
                'leaves_count' => $request->credit_value,
                'before_leaves' => $beforeBalance,
                'after_leaves' => $afterBalance,
                'transaction_date' => now()->toDateTimeString(),
                'leave_detail' => 'paid',
                'remarks' => $request->remarks ?? 'Manual credit adjustment',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            app(AuditLogger::class)->record(
                'tenant_user', Auth::id(), (int) $user->tenant_id, 'leave.balance_credited',
                'LeaveBalance', $balance->id, ['balance' => $beforeBalance],
                ['balance' => $afterBalance, 'leave_type_id' => $leaveType->id, 'target_user_id' => $user->id, 'remarks' => $request->remarks]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave credit added successfully',
                'new_balance' => $afterBalance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in manual credit: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual debit adjustment — the counterpart manualCredit() never had.
     * Same validation/ledger pattern, transaction_type='sub', with a floor
     * (config('leave.max_negative_balance'), default 0) so an adjustment
     * can't push a user arbitrarily far into a negative balance.
     */
    public function manualDebit(Request $request)
    {
        if (!app(RbacService::class)->can(Auth::user(), 'leave', 'manage')) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to adjust leave balances.'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'debit_value' => 'required|numeric|min:0.01',
            'remarks' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $user = User::find($request->user_id);
            $leaveType = LeaveType::find($request->leave_type_id);

            $balance = LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'leave_type_id' => $leaveType->id],
                ['balance' => 0]
            );

            $beforeBalance = (float) $balance->balance;
            $afterBalance = $beforeBalance - $request->debit_value;
            $floor = (float) config('leave.max_negative_balance', 0);

            $minAllowed = abs($floor) == 0 ? 0.0 : -abs($floor);
            if ($afterBalance < $minAllowed) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "This adjustment would take the balance to {$afterBalance}, below the allowed minimum of {$minAllowed}.",
                ], 400);
            }

            $balance->update(['balance' => $afterBalance]);

            LeaveTransaction::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'leave_type' => $leaveType->id,
                'transaction_type' => 'sub',
                'total_leaves' => $request->debit_value,
                'leaves_count' => $request->debit_value,
                'before_leaves' => $beforeBalance,
                'after_leaves' => $afterBalance,
                'transaction_date' => now()->toDateTimeString(),
                'leave_detail' => 'paid',
                'remarks' => $request->remarks,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->record(
                'tenant_user', Auth::id(), (int) $user->tenant_id, 'leave.balance_debited',
                'LeaveBalance', $balance->id, ['balance' => $beforeBalance],
                ['balance' => $afterBalance, 'leave_type_id' => $leaveType->id, 'target_user_id' => $user->id, 'remarks' => $request->remarks]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave balance debited successfully',
                'new_balance' => $afterBalance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in manual debit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
