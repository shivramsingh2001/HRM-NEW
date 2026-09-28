<?php

namespace App\Http\Controllers\Api\Loan;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanCategory;
use App\Models\LoanRepayment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\RbacService;
use App\Models\User;

class LoanController extends Controller
{
    public function fetch_category(Request $request)
    {
        try {
            $data = LoanCategory::where('status', '1')->get();

            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later.",
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Loan::query();
            $user = auth()->user();
            // Was "in_array(role, [admin,hr,manager,employee])" — every
            // valid role, so this was always true and every user (including
            // plain employees) saw every tenant's loans unfiltered.
            $loanScope = app(RbacService::class)->scopeFor($user, 'loans', 'view');

            // Filter by tenant
            if ($user && $user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by scope
            if ($loanScope === 'own') {
                $query->where('user_id', $user->id);
            } elseif ($loanScope === 'team') {
                $teamIds = User::managedBy($user->id)->pluck('id')->toArray();
                $teamIds[] = $user->id;
                $query->whereIn('user_id', $teamIds);
            } elseif ($loanScope === null) {
                $query->whereRaw('1 = 0');
            }

            // Company/team scope can filter by specific user
            if ($request->has('user_id') && $request->user_id && $loanScope !== 'own') {
                $query->where('user_id', $request->user_id);
            }

            // Get paginated results with only necessary columns
            $perPage = $request->get('per_page', 15);
            $loans = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform loans data
            $transformedLoans = collect($loans->items())->map(function ($loan) {
                return $this->formatLoanData($loan);
            })->toArray();

            // Calculate statistics
            $statistics = $this->getLoanStatistics($loanScope, $user, $request);

            return response()->json([
                'success' => true,
                'message' => 'Data Fetched Successfully.',
                'data' => [
                    'loans' => $transformedLoans,
                    'pagination' => [
                        'current_page' => $loans->currentPage(),
                        'last_page' => $loans->lastPage(),
                        'per_page' => $loans->perPage(),
                        'total' => $loans->total(),
                        'next_page_url' => $loans->nextPageUrl(),
                        'prev_page_url' => $loans->previousPageUrl()
                    ],

                    // 'statistics' => $statistics,
                    'filters' => [
                        'status' => $request->status,
                        'user_id' => $request->user_id,
                        'per_page' => (int)$perPage
                    ],
                ]
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get loan statistics based on user role
     */
    private function getLoanStatistics($loanScope, $user, $request)
    {
        $query = Loan::query();

        if ($loanScope === 'own') {
            $query->where('user_id', $user->id);
        } elseif ($loanScope === 'team') {
            $teamIds = User::managedBy($user->id)->pluck('id')->toArray();
            $teamIds[] = $user->id;
            $query->whereIn('user_id', $teamIds);
        } elseif ($loanScope === null) {
            $query->whereRaw('1 = 0');
        }

        // Apply same filters as main query for stats
        if ($request->has('user_id') && $request->user_id && $loanScope !== 'own') {
            $query->where('user_id', $request->user_id);
        }


        $stats = [
            'total_loans' => (clone $query)->count(),
            'pending_loans' => (clone $query)->where('status', 'pending')->count(),
            'approved_loans' => (clone $query)->where('status', 'approved')->count(),
            'active_loans' => (clone $query)->where('status', 'active')->count(),
            'closed_loans' => (clone $query)->where('status', 'closed')->count(),
            'cancelled_loans' => (clone $query)->where('status', 'cancelled')->count(),
            'total_disbursed' => (clone $query)->whereIn('status', ['active', 'closed'])->sum('amount'),
            'total_outstanding' => (clone $query)->where('status', 'active')->sum('remaining_amount'),
            'total_interest_earned' => (clone $query)->whereIn('status', ['active', 'closed'])->sum('interest_rate')
        ];

        return $stats;
    }

    /**
     * Format single loan data with only essential columns
     */
    private function formatLoanData($loan)
    {
        return [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'amount' => number_format($loan->amount, 2),
            'emi_amount' => number_format($loan->emi_amount, 2),
            'remaining_amount' => number_format($loan->remaining_amount, 2),
            'tenure_months' => $loan->tenure_months,
            'interest_rate' => $loan->interest_rate . '%',
            'repayment_type' => $loan->repayment_type,
            'status' => $loan->status,
            'status_label' => ucfirst($loan->status),
            'purpose' => $loan->purpose,
            'loan_date' => $loan->loan_date,
            'first_emi_date' => $loan->first_emi_date,
            'lumpsum_due_date' => $loan->lumpsum_due_date,
            'closed_date' => $loan->closed_date,
            'created_at' => $loan->created_at,
            'category' =>  $loan->loanCategory->name,
            'approved_by' => $loan->approvedBy->name ?? null,
            'progress' => $loan->status == 'active' ? [
                'percentage' => $loan->progress_percentage,
                'paid_amount' => number_format($loan->paid_amount, 2),
                'remaining_amount' => number_format($loan->remaining_amount, 2)
            ] : [],

        ];
    }

    /**
     * Get loan statistics only (lightweight endpoint)
     */
    public function statistics(Request $request)
    {
        try {
            $user = auth()->user();
            $loanScope = app(RbacService::class)->scopeFor($user, 'loans', 'view');
            $statistics = $this->getLoanStatistics($loanScope, $user, $request);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
    public function store(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'loan_type_id' => 'required|exists:loan_categories,id',
            'repayment_type' => 'required|in:emi,lumpsum',
            'amount' => 'required|numeric|min:1000',
            'tenure_months' => 'required_if:repayment_type,emi|nullable|integer|min:1|max:60',
            'lumpsum_tenure_months' => 'required_if:repayment_type,lumpsum|nullable|integer|min:1|max:24',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_path' => 'nullable|file|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        DB::beginTransaction();

        try {
            $category = LoanCategory::findOrFail($request->loan_type_id);
            $repaymentType = $request->repayment_type;
            $userId = auth()->id();
            $tenantId = auth()->user()->tenant_id;

            // Validate amount against category limit
            if ($category->max_amount && $request->amount > $category->max_amount) {
                return response()->json([
                    'success' => false,
                    'message' => "Amount exceeds maximum limit of ₹" . number_format($category->max_amount, 2),
                ], 200);
            }
            $filePath = $request->hasFile('document_path')
                ? file_storage()->upload($request->file('document_path'), 'loan')->path
                : null;


            // ========== LUMP SUM LOAN ==========
            if ($repaymentType == 'lumpsum') {
                $tenureMonths = (int) $request->lumpsum_tenure_months;
                $emiAmount = 0;
                $firstEmiDate = null;

                // Calculate total payable with simple interest
                $interestAmount = ($request->amount * $category->default_interest_rate * $tenureMonths) / 1200;
                $totalPayable = $request->amount + $interestAmount;
                $lumpsumDueDate = now()->addMonths($tenureMonths)->format('Y-m-d');
                $remainingAmount = $totalPayable;


                // ========== EMI LOAN ==========
            } else {
                $tenureMonths = (int) $request->tenure_months;
                $lumpsumDueDate = null;

                // Validate tenure against category limit
                if ($category->max_tenure_months && $tenureMonths > $category->max_tenure_months) {
                    return response()->json([
                        'success' => false,
                        'message' => "Tenure exceeds maximum limit of {$category->max_tenure_months} months",
                        'max_tenure' => $category->max_tenure_months
                    ], 200);
                }

                $emiAmount = $this->calculateEmi($request->amount, $category->default_interest_rate, $tenureMonths);
                $firstEmiDate = $this->calculateFirstEmiDate();
                $totalPayable = $emiAmount * $tenureMonths;
                $remainingAmount = $request->amount;
            }

            // Determine status based on category approval requirement
            $status = $category->requires_approval ? Loan::STATUS_PENDING : Loan::STATUS_APPROVED;

            // Create loan record
            $loan = Loan::create([
                'user_id' => $userId,
                'loan_type_id' => $request->loan_type_id,
                'repayment_type' => $repaymentType,
                'amount' => $request->amount,
                'interest_rate' => $category->default_interest_rate,
                'tenure_months' => $tenureMonths,
                'emi_amount' => $emiAmount,
                'remaining_amount' => $remainingAmount,
                'loan_date' => now()->format('Y-m-d'),
                'first_emi_date' => $firstEmiDate,
                'lumpsum_due_date' => $lumpsumDueDate,
                'lumpsum_amount' => $repaymentType == 'lumpsum' ? $totalPayable : null,
                'purpose' => $request->purpose,
                'description' => $request->description,
                'document_path' =>  $filePath,
                'status' => $status
            ]);

            // If auto-approved, generate schedule based on type
            if ($status == Loan::STATUS_APPROVED) {
                if ($repaymentType == 'emi') {
                    $this->generateRepaymentSchedule($loan);
                } else {
                    $this->generateLumpsumRepayment($loan);
                }
                $loan->update([
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
            }

            DB::commit();

            $message = $status == Loan::STATUS_PENDING
                ? 'Loan request submitted successfully and pending approval'
                : 'Loan approved successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }
    /**
     * Calculate EMI
     */
    private function calculateEmi($amount, $interestRate, $tenureMonths)
    {
        if ($interestRate == 0) {
            return round($amount / $tenureMonths, 2);
        }

        $monthlyRate = $interestRate / 100 / 12;
        $emi = ($amount * $monthlyRate * pow(1 + $monthlyRate, $tenureMonths)) /
            (pow(1 + $monthlyRate, $tenureMonths) - 1);

        return round($emi, 2);
    }

    /**
     * Calculate first EMI date - ONLY for EMI loans
     * Returns string date, not Carbon instance
     */
    private function calculateFirstEmiDate()
    {
        $salaryDay = 7;
        $today = (int) date('j');
        $currentYear  = (int) date('Y');
        $currentMonth = (int) date('m');

        if ($today < $salaryDay) {
            return \Carbon\Carbon::createFromDate($currentYear, $currentMonth, $salaryDay)->format('Y-m-d');
        } else {
            $next = \Carbon\Carbon::createFromDate($currentYear, $currentMonth, $salaryDay)->addMonth();
            return $next->format('Y-m-d');
        }
    }
    /**
     * Generate lumpsum repayment (single entry)
     */
    private function generateLumpsumRepayment(Loan $loan)
    {
        // Skip if not lumpsum loan
        if ($loan->repayment_type != 'lumpsum') {
            return;
        }

        // Skip if no lumpsum_due_date
        if (empty($loan->lumpsum_due_date)) {
            return;
        }

        // Delete any existing repayments
        LoanRepayment::where('loan_id', $loan->id)->delete();

        $dueDate = $loan->lumpsum_due_date;

        LoanRepayment::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'installment_number' => 1,
            'month' => date('Y-m', strtotime($dueDate)),
            'due_date' => $dueDate,
            'emi_amount' => $loan->lumpsum_amount ?? $loan->amount,
            'principal_amount' => $loan->amount,
            'interest_amount' => ($loan->lumpsum_amount ?? $loan->amount) - $loan->amount,
            'total_amount' => $loan->lumpsum_amount ?? $loan->amount,
            'status' => 'pending'
        ]);
    }

    /**
     * Generate repayment schedule for EMI loans
     */
    private function generateRepaymentSchedule(Loan $loan)
    {
        if ($loan->repayment_type != 'emi') return;
        if (empty($loan->first_emi_date)) return;
        LoanRepayment::where('loan_id', $loan->id)->delete();
        $firstEmiDate = \Carbon\Carbon::parse($loan->first_emi_date);
        $principalPerInstallment = $loan->amount / $loan->tenure_months;

        for ($i = 1; $i <= $loan->tenure_months; $i++) {
            $dueDate = $firstEmiDate->copy()->addMonths($i - 1);
            LoanRepayment::create([
                'tenant_id'            => $loan->tenant_id,
                'loan_id'              => $loan->id,
                'installment_number'   => $i,
                'month'                => $dueDate->format('Y-m'),
                'due_date'             => $dueDate->format('Y-m-d'),
                'emi_amount'           => $loan->emi_amount,
                'principal_amount'     => round($principalPerInstallment, 2),
                'interest_amount'      => 0,
                'total_amount'         => $loan->emi_amount,
                'status'               => 'pending'
            ]);
        }
    }
    public function show($id)
    {
        try {
            $user = auth()->user();

            // Build query with necessary relationships
            $query = Loan::with([
                'user:id,name,employee_id,email',
                'loanCategory:id,name,code,max_amount,default_interest_rate',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'disbursedBy:id,name',
                'repayments' => function ($q) {
                    $q->orderBy('installment_number', 'asc')
                        ->select(
                            'id',
                            'loan_id',
                            'installment_number',
                            'month',
                            'due_date',
                            'emi_amount',
                            'principal_amount',
                            'interest_amount',
                            'total_amount',
                            'paid_amount',
                            'status',
                            'paid_date'
                        );
                }
            ]);

            // Add tenant filter
            if (auth()->user() && auth()->user()->tenant_id) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }

            // Add authorization check
            $isAdminOrManager = in_array(auth()->user()->role, ['admin', 'hr', 'manager']);
            if (!$isAdminOrManager) {
                $query->where('user_id', auth()->id());
            }

            $loan = $query->findOrFail($id);

            // Format and return response
            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully.',
                'data' => $this->formatLoanDetailData($loan)
            ]);
        } catch (Exception $e) {
            Log::error('Loan show API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Format loan detail data with specific columns
     */
    private function formatLoanDetailData($loan)
    {
        // Basic loan information
        $data = [
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'amount' => number_format($loan->amount, 2),
            'interest_rate' => $loan->interest_rate . '%',
            'tenure_months' => $loan->tenure_months,
            'emi_amount' => number_format($loan->emi_amount, 2),
            'remaining_amount' => number_format($loan->remaining_amount, 2),
            'repayment_type' => $loan->repayment_type,
            'status' => $loan->status,
            'status_label' => ucfirst($loan->status),
            'purpose' => $loan->purpose,
            'description' => $loan->description,
            'document_path' => $loan->document_path,
            'loan_date' => $loan->loan_date,
            'first_emi_date' => $loan->first_emi_date,
            'lumpsum_due_date' => $loan->lumpsum_due_date,
            'lumpsum_amount' => $loan->lumpsum_amount ? number_format($loan->lumpsum_amount, 2) : null,
            'closed_date' => $loan->closed_date,
            'created_at' => $loan->created_at,
            'updated_at' => $loan->updated_at,
        ];

        // User information (employee) - commented as requested
        // if ($loan->user) {
        //     $data['user'] = [
        //         'id' => $loan->user->id,
        //         'name' => $loan->user->name,
        //         'employee_id' => $loan->user->employee_id,
        //         'email' => $loan->user->email
        //     ];
        // }

        // Category information
        if ($loan->loanCategory) {
            $data['category'] = $loan->loanCategory->name;
        }

        // Approval information
        $data['approval'] = [
            'approved_by' => $loan->approvedBy ? $loan->approvedBy->name : null,
            'approved_at' => $loan->approved_at,
            'rejected_by' => $loan->rejectedBy ? $loan->rejectedBy->name : null,
            'rejected_at' => $loan->rejected_at,
            'rejection_reason' => $loan->rejection_reason,
            'disbursed_by' => $loan->disbursedBy ? $loan->disbursedBy->name : null,
            'disbursed_at' => $loan->disbursed_at,
            'cancelled_by' => $loan->cancelledBy ? $loan->cancelledBy->name : null,
            'cancelled_at' => $loan->cancelled_at,
            'cancellation_reason' => $loan->cancellation_reason
        ];

        // Progress for active loans
        if ($loan->status == 'active') {
            $totalPaid = $loan->amount - $loan->remaining_amount;
            $progressPercentage = $loan->amount > 0 ? ($totalPaid / $loan->amount) * 100 : 0;

            $data['progress'] = [
                'percentage' => round($progressPercentage, 2),
                'paid_amount' => number_format($totalPaid, 2),
                'remaining_amount' => number_format($loan->remaining_amount, 2),
                'next_due_emi' => $this->getNextDueEmi($loan)
            ];
        }

        // Repayment schedule
        if ($loan->repayments && $loan->repayments->isNotEmpty()) {
            $data['repayment_schedule'] = [
                'summary' => [
                    'total_installments' => $loan->repayments->count(),
                    'paid_installments' => $loan->repayments->where('status', 'paid')->count(),
                    'pending_installments' => $loan->repayments->where('status', 'pending')->count(),
                    'overdue_installments' => $loan->repayments->where('status', 'overdue')->count(),
                    'total_paid' => number_format($loan->repayments->sum('paid_amount'), 2),
                    'remaining_to_pay' => number_format($loan->remaining_amount, 2)
                ],
                'installments' => $loan->repayments->map(function ($repayment) {
                    return [
                        'installment_number' => $repayment->installment_number,
                        'month' => $repayment->month,
                        'due_date' => $repayment->due_date,
                        'emi_amount' => number_format($repayment->emi_amount, 2),
                        'principal_amount' => number_format($repayment->principal_amount, 2),
                        'interest_amount' => number_format($repayment->interest_amount, 2),
                        'total_amount' => number_format($repayment->total_amount, 2),
                        'paid_amount' => number_format($repayment->paid_amount, 2),
                        'status' => $repayment->status,
                        'status_label' => ucfirst($repayment->status),
                        'paid_date' => $repayment->paid_date,
                        'is_overdue' => $repayment->status == 'pending' && $repayment->due_date < now()->toDateString()
                    ];
                })
            ];
        } else {
            $data['repayment_schedule'] = null;
        }

        return $data;
    }

    /**
     * Get next due EMI for loan
     */
    private function getNextDueEmi($loan)
    {
        $nextEmi = $loan->repayments()
            ->where('status', 'pending')
            ->where('due_date', '>=', now()->toDateString())
            ->orderBy('due_date', 'asc')
            ->first();

        if ($nextEmi) {
            return [
                'installment_number' => $nextEmi->installment_number,
                'due_date' => $nextEmi->due_date,
                'amount' => number_format($nextEmi->emi_amount, 2)
            ];
        }

        return null;
    }
}
