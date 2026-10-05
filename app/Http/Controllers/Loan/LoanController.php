<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Concerns\RaisesOnBehalf;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanCategory;
use App\Models\LoanRepayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    use RaisesOnBehalf;

    private function advances(): \App\Services\Loan\SalaryAdvanceService
    {
        return app(\App\Services\Loan\SalaryAdvanceService::class);
    }

    /**
     * Salary advance request (loan_kind = salary_advance): category, salary
     * month, amount, purpose. Validated + created by SalaryAdvanceService.
     * $raisedBy = admin / HR raising it for the employee (saved approved).
     */
    private function storeSalaryAdvance(Request $request, User $employee, ?User $raisedBy = null)
    {
        $validator = Validator::make($request->all(), [
            'loan_type_id' => ['required', Rule::exists('loan_categories', 'id')->where('tenant_id', $employee->tenant_id)->where('kind', Loan::KIND_SALARY_ADVANCE)],
            'advance_month' => ['required', 'date_format:Y-m'],
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ], ['loan_type_id.exists' => 'Choose a Salary Advance category.']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = LoanCategory::withoutGlobalScopes()->findOrFail($request->loan_type_id);
        if ($error = $this->advances()->validate($employee, $category, (float) $request->amount, (string) $request->advance_month)) {
            return response()->json(['success' => false, 'message' => $error, 'errors' => ['amount' => [$error]]], 422);
        }

        try {
            $loan = DB::transaction(fn () => $this->advances()->create($employee, $category, (float) $request->amount,
                (string) $request->advance_month, (string) $request->purpose, $request->description, $raisedBy, (bool) $raisedBy));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Salary advance create failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not save the salary advance. Please try again.'], 500);
        }

        $month = \Carbon\Carbon::parse($loan->advance_month . '-01')->format('M Y');
        $message = $loan->status === Loan::STATUS_PENDING
            ? "Salary advance for {$month} submitted and pending approval."
            : "Salary advance for {$month} approved" . ($raisedBy ? " for {$employee->name}" : '') . '. Mark it paid when the money is given; it is deducted from the ' . $month . ' salary.';

        return response()->json(['success' => true, 'message' => $message, 'data' => $loan], 201);
    }

    /**
     * GET loan/advance-limit?category=&month=[&user_id=] — the months an advance
     * can be taken for and how much is still available (My Loans form; admin /
     * HR may pass user_id).
     */
    public function advanceLimit(Request $request)
    {
        $actor = auth()->user();
        $employee = $actor;
        if ($request->filled('user_id') && (int) $request->user_id !== (int) $actor->id) {
            abort_unless(in_array($actor->role, ['admin', 'hr'], true), 403);
            $employee = User::withoutGlobalScopes()->where('tenant_id', $actor->tenant_id)->where('status', 1)->findOrFail((int) $request->user_id);
        }

        $months = $this->advances()->openMonths($employee);
        $month = $request->filled('month') ? (string) $request->month : ($months[0] ?? null);
        $category = $request->filled('category')
            ? LoanCategory::where('kind', Loan::KIND_SALARY_ADVANCE)->find((int) $request->category)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'open_months' => array_map(fn ($m) => ['value' => $m, 'label' => \Carbon\Carbon::parse($m . '-01')->format('F Y')], $months),
                'month' => $month,
                'monthly_gross' => $this->advances()->monthlyGross($employee),
                'limit' => $category && $month ? $this->advances()->limit($employee, $category, $month) : null,
            ],
        ]);
    }

    /**
     * Display a listing of loans (with filters)
     */
    // public function index(Request $request)
    // {
       
    //     try {
    //         $query = Loan::with(['user', 'loanCategory', 'approvedBy']);

         
    //         if ($request->has('status') && $request->status) {
    //             $query->where('status', $request->status);
    //         }

    //         $isAdminOrManager = in_array(auth()->user()->role, ['admin', 'hr', 'manager']);

    //         if (!$isAdminOrManager) {
               
    //             $query->where('user_id', auth()->id());
    //         }

    //         if ($request->has('user_id') && $request->user_id && $isAdminOrManager) {
    //             $query->where('user_id', $request->user_id);
    //         }

    //         if ($request->has('search') && $request->search) {
    //             $query->where('loan_number', 'like', '%' . $request->search . '%');
    //         }

    //         $loans = $query->orderBy('created_at', 'desc')->paginate(15);

    //         // Calculate statistics
    //         $statistics = $this->getLoanStatistics($isAdminOrManager);

    //         $categories = LoanCategory::where('status', 1)
    //             ->orderBy('name', 'asc')
    //             ->get();

    //         return view('client.loan.loans.index', compact('loans', 'categories', 'statistics'));
    //     } catch (\Exception $e) {
    //         return back()->withErrors('An error occurred. Please try again later.');
    //     }
    // }
    public function index(Request $request)
{
    try {
        $query = Loan::with(['user', 'loanCategory', 'approvedBy']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // EVERYONE sees only their own loans - no role check needed
        $query->where('user_id', auth()->id());

        // Search by loan number
        if ($request->has('search') && $request->search) {
            $query->where('loan_number', 'like', '%' . $request->search . '%');
        }
        if (in_array($request->kind, [Loan::KIND_LOAN, Loan::KIND_SALARY_ADVANCE], true)) {
            $query->where('loan_kind', $request->kind);
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(15);

        // Calculate statistics for the logged-in user only
        $statistics = $this->getLoanStatistics();

        // Regular loan categories for the loan form; Salary Advance categories for the advance form.
        $categories = LoanCategory::where('status', 1)->where('kind', Loan::KIND_LOAN)
            ->orderBy('name', 'asc')
            ->get();
        $advanceCategories = LoanCategory::where('status', 1)->where('kind', Loan::KIND_SALARY_ADVANCE)
            ->orderBy('name', 'asc')
            ->get();

        return view('client.loan.loans.index', compact('loans', 'categories', 'advanceCategories', 'statistics'));
        
    } catch (\Exception $e) {
        return back()->withErrors('An error occurred. Please try again later.');
    }
}


    private function getLoanStatistics($isAdminOrManager = false)
    {
        $query = Loan::query();

        if (auth()->user() && auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        if (!$isAdminOrManager) {
            $query->where('user_id', auth()->id());
        }

        $statistics = [];

        // Total loans
        $statistics['total_loans'] = $query->clone()->count();

        // Pending loans
        $statistics['pending_loans'] = $query->clone()->where('status', 'pending')->count();

        // Active loans
        $statistics['active_loans'] = $query->clone()->where('status', 'active')->count();

        // Total disbursed amount
        $statistics['total_disbursed_amount'] = $query->clone()
            ->whereIn('status', ['active', 'closed'])
            ->sum('amount');

        // EMI vs Lump sum counts
        $statistics['emi_loans'] = $query->clone()
            ->where('repayment_type', 'emi')
            ->count();

        $statistics['lumpsum_loans'] = $query->clone()
            ->where('repayment_type', 'lumpsum')
            ->count();

        // Total EMI paid (from repayments)
        $statistics['total_emi_paid'] = \DB::table('loan_repayments')
            ->whereIn('loan_id', $query->clone()->pluck('id'))
            ->where('status', 'paid')
            ->sum('total_amount');

        // Outstanding amount
        $activeLoans = $query->clone()->where('status', 'active')->get();
        $outstanding = 0;

        foreach ($activeLoans as $loan) {
            if ($loan->repayment_type == 'emi') {
                $paidAmount = $loan->repayments()->where('status', 'paid')->sum('total_amount');
                $outstanding += ($loan->total_payable_amount - $paidAmount);
            } else {
                if ($loan->status == 'active' && !$loan->lumpsum_paid) {
                    $outstanding += ($loan->lumpsum_amount + $loan->interest_amount);
                }
            }
        }

        $statistics['outstanding_amount'] = $outstanding;

        return $statistics;
    }

    /**
     * Store a newly created loan request
     */
    public function store(Request $request)
    {
        if ($request->input('loan_kind') === Loan::KIND_SALARY_ADVANCE) {
            return $this->storeSalaryAdvance($request, auth()->user());
        }

        $validator = Validator::make($request->all(), [
            'loan_type_id' => ['required', Rule::exists('loan_categories', 'id')->where('kind', Loan::KIND_LOAN)],
            'repayment_type' => 'required|in:emi,lumpsum',
            'amount' => 'required|numeric|min:1000',
            'tenure_months' => 'required_if:repayment_type,emi|nullable|integer|min:1|max:60',
            'lumpsum_tenure_months' => 'required_if:repayment_type,lumpsum|nullable|integer|min:1|max:24',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_path' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $category = LoanCategory::findOrFail($request->loan_type_id);
            $repaymentType = $request->repayment_type;

            $terms = $this->loanTerms($request, $category);
            if (isset($terms['error'])) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $terms['error']], 400);
            }
            ['tenureMonths' => $tenureMonths, 'emiAmount' => $emiAmount, 'firstEmiDate' => $firstEmiDate,
                'lumpsumDueDate' => $lumpsumDueDate, 'totalPayable' => $totalPayable, 'remainingAmount' => $remainingAmount] = $terms;
            // Determine status based on category approval requirement
            $status = $category->requires_approval ? Loan::STATUS_PENDING : Loan::STATUS_APPROVED;

            $loan = Loan::create([
                'tenant_id' => auth()->user()->tenant_id,
                'user_id' => auth()->id(),
                'loan_type_id' => $request->loan_type_id,
                'repayment_type' => $repaymentType,
                'amount' => $request->amount,
                'interest_rate' => $category->default_interest_rate,
                'tenure_months' => $tenureMonths,
                'emi_amount' => $emiAmount,
                'remaining_amount' => $remainingAmount,
                'loan_date' => now()->format('Y-m-d'),
                'first_emi_date' => $firstEmiDate,  // NULL for lumpsum
                'lumpsum_due_date' => $lumpsumDueDate,  // NULL for EMI
                'lumpsum_amount' => $repaymentType == 'lumpsum' ? $totalPayable : null,
                'purpose' => $request->purpose,
                'description' => $request->description,
                'document_path' => $request->document_path,
                'status' => $status
            ]);

            // If auto-approved, generate schedule based on type
            if ($status == Loan::STATUS_APPROVED) {
                // loan_number comes from a DB trigger; without this every repayment number was "-E001"…
                $loan->refresh();
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

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $loan
                ], 201);
            }

            return redirect()->route('loan.requests.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred. Please try again later.',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->withErrors('An error occurred. Please try again later.')->withInput();
        }
    }

    /**
     * Amount / tenure checks against the category and the EMI or lump-sum terms.
     * Returns ['error' => message] when the category's limits are exceeded.
     */
    private function loanTerms(Request $request, LoanCategory $category): array
    {
        if ($category->max_amount && $request->amount > $category->max_amount) {
            return ['error' => "Amount exceeds maximum limit of ₹" . number_format($category->max_amount, 2)];
        }

        // ========== LUMP SUM LOAN ==========
        if ($request->repayment_type == 'lumpsum') {
            $tenureMonths = (int) $request->lumpsum_tenure_months;
            $interestAmount = ($request->amount * $category->default_interest_rate * $tenureMonths) / 1200;
            $totalPayable = $request->amount + $interestAmount;

            return [
                'tenureMonths' => $tenureMonths,
                'emiAmount' => 0,
                'firstEmiDate' => null,
                'lumpsumDueDate' => now()->addMonths($tenureMonths)->format('Y-m-d'),
                'totalPayable' => $totalPayable,
                'remainingAmount' => $totalPayable,
            ];
        }

        // ========== EMI LOAN ==========
        $tenureMonths = (int) $request->tenure_months;
        if ($category->max_tenure_months && $tenureMonths > $category->max_tenure_months) {
            return ['error' => "Tenure exceeds maximum limit of {$category->max_tenure_months} months"];
        }
        $emiAmount = $this->calculateEmi($request->amount, $category->default_interest_rate, $tenureMonths);

        return [
            'tenureMonths' => $tenureMonths,
            'emiAmount' => $emiAmount,
            'firstEmiDate' => $this->calculateFirstEmiDate(),
            'lumpsumDueDate' => null,
            'totalPayable' => $emiAmount * $tenureMonths,
            'remainingAmount' => $request->amount,
        ];
    }

    /**
     * Move a schedule start that is already in the past (request approved /
     * disbursed late) to the next salary date — EMI: first_emi_date; lump sum:
     * due date = now + tenure. Does not save.
     */
    private function alignScheduleStart(Loan $loan): void
    {
        $today = now()->toDateString();

        if ($loan->repayment_type == 'lumpsum') {
            if (! $loan->lumpsum_due_date || \Carbon\Carbon::parse($loan->lumpsum_due_date)->toDateString() < $today) {
                $loan->lumpsum_due_date = now()->addMonths(max(1, (int) $loan->tenure_months))->format('Y-m-d');
            }

            return;
        }

        if (! $loan->first_emi_date || \Carbon\Carbon::parse($loan->first_emi_date)->toDateString() < $today) {
            $loan->first_emi_date = $this->calculateFirstEmiDate();
        }
    }

    /**
     * Admin / HR raises a loan for an employee (Loan Management → "Create loan
     * request"). Saved APPROVED by the caller with its repayment schedule — the
     * same writes approve() makes; disbursal stays a separate step.
     */
    public function storeOnBehalf(Request $request)
    {
        $employee = $this->onBehalfEmployee($request);

        if ($request->input('loan_kind') === Loan::KIND_SALARY_ADVANCE) {
            return $this->storeSalaryAdvance($request, $employee, auth()->user());
        }

        $validator = Validator::make($request->all(), [
            'loan_type_id' => ['required', Rule::exists('loan_categories', 'id')->where('tenant_id', auth()->user()->tenant_id)->where('kind', Loan::KIND_LOAN)],
            'repayment_type' => 'required|in:emi,lumpsum',
            'amount' => 'required|numeric|min:1000',
            'tenure_months' => 'required_if:repayment_type,emi|nullable|integer|min:1|max:60',
            'lumpsum_tenure_months' => 'required_if:repayment_type,lumpsum|nullable|integer|min:1|max:24',
            'purpose' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = LoanCategory::findOrFail($request->loan_type_id);
        $terms = $this->loanTerms($request, $category);
        if (isset($terms['error'])) {
            return response()->json(['success' => false, 'message' => $terms['error']], 400);
        }

        try {
            $loan = DB::transaction(function () use ($request, $employee, $category, $terms) {
                $loan = Loan::create([
                    'tenant_id' => $employee->tenant_id,
                    'user_id' => $employee->id,
                    'created_by' => auth()->id(),
                    'loan_type_id' => $category->id,
                    'repayment_type' => $request->repayment_type,
                    'amount' => $request->amount,
                    'interest_rate' => $category->default_interest_rate,
                    'tenure_months' => $terms['tenureMonths'],
                    'emi_amount' => $terms['emiAmount'],
                    'remaining_amount' => $terms['remainingAmount'],
                    'loan_date' => now()->format('Y-m-d'),
                    'first_emi_date' => $terms['firstEmiDate'],
                    'lumpsum_due_date' => $terms['lumpsumDueDate'],
                    'lumpsum_amount' => $request->repayment_type == 'lumpsum' ? $terms['totalPayable'] : null,
                    'purpose' => $request->purpose,
                    'description' => $request->description,
                    'status' => Loan::STATUS_APPROVED,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
                // loan_number is set by the DB trigger generate_loan_number_before_insert — reload it
                // before the schedule, whose repayment numbers are built from it.
                $loan->refresh();

                $request->repayment_type == 'emi' ? $this->generateRepaymentSchedule($loan) : $this->generateLumpsumRepayment($loan);

                return $loan;
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Loan on behalf failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not create the loan. Please try again.'], 500);
        }

        $this->logOnBehalf('loan.created_on_behalf', 'Loan', (int) $loan->id, $employee, [
            'loan_number' => $loan->loan_number, 'amount' => (string) $loan->amount, 'category' => $category->name,
            'repayment_type' => $loan->repayment_type, 'tenure_months' => $loan->tenure_months, 'status' => 'approved',
        ]);

        try {
            app(\App\Services\NotificationService::class)->sendToUser($employee, 'Loan approved',
                'A ' . $category->name . ' loan of ₹' . number_format((float) $loan->amount, 2) . ' was created and approved for you by ' . auth()->user()->name . '.',
                ['type' => 'loan_approved', 'loan_id' => (string) $loan->id]);
        } catch (\Throwable $e) {
            // never block on notification
        }

        return response()->json([
            'success' => true,
            'message' => "Loan created and approved for {$employee->name}. Disburse it when the amount is paid.",
            'data' => $loan,
        ], 201);
    }

    /**
     * Display the specified loan
     */
    public function show($id)
    {
        try {
            $loan = Loan::with(['user', 'loanCategory', 'approvedBy', 'rejectedBy', 'disbursedBy', 'repayments'])
                ->findOrFail($id);

            if ($loan->user_id != auth()->id() && !in_array(auth()->user()->role, ['admin', 'hr', 'manager'])) {
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access'
                    ], 403);
                }
                abort(403, 'Unauthorized access.');
            }

            if ($loan->approved_by) {
                $loan->approved_by_name = $loan->approvedBy->name ?? null;
            }

            return response()->json([
                'success' => true,
                'data' => $loan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Loan not found'
            ], 404);
        }
    }

    /**
     * Update the specified loan
     */
    public function update(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);

        if ($loan->isSalaryAdvance()) {
            return response()->json([
                'success' => false,
                'message' => 'A salary advance cannot be edited — cancel it and request a new one.'
            ], 400);
        }

        if (!$loan->isPending()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending loans can be updated'
                ], 400);
            }
            return back()->withErrors('Only pending loans can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'loan_type_id' => 'sometimes|exists:loan_categories,id',
            'amount' => 'sometimes|numeric|min:1000',
            'tenure_months' => 'sometimes|integer|min:1|max:60',
            'purpose' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'document_path' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            if ($request->has('amount') || $request->has('tenure_months') || $request->has('loan_type_id')) {
                $amount = $request->amount ?? $loan->amount;
                $tenure = (int) ($request->tenure_months ?? $loan->tenure_months);
                $categoryId = $request->loan_type_id ?? $loan->loan_type_id;

                $category = LoanCategory::findOrFail($categoryId);

                if ($loan->repayment_type == 'lumpsum') {
                    $interestAmount = ($amount * $category->default_interest_rate * $tenure) / 1200;
                    $totalPayable = $amount + $interestAmount;

                    $loan->amount = $amount;
                    $loan->tenure_months = $tenure;
                    $loan->remaining_amount = $totalPayable;
                    $loan->lumpsum_amount = $totalPayable;
                    $loan->lumpsum_due_date = now()->addMonths($tenure)->format('Y-m-d');
                    // Ensure first_emi_date is NULL for lumpsum
                    $loan->first_emi_date = null;
                } else {
                    $emiAmount = $this->calculateEmi($amount, $category->default_interest_rate, $tenure);

                    $loan->amount = $amount;
                    $loan->tenure_months = $tenure;
                    $loan->emi_amount = $emiAmount;
                    $loan->remaining_amount = $amount;
                    $loan->lumpsum_due_date = null;
                    $loan->lumpsum_amount = null;

                    if (!$loan->first_emi_date) {
                        $loan->first_emi_date = $this->calculateFirstEmiDate();
                    }
                }

                $loan->loan_type_id = $categoryId;
                $loan->interest_rate = $category->default_interest_rate;
            }

            $loan->fill($request->only(['purpose', 'description', 'document_path']));
            $loan->save();

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loan updated successfully',
                    'data' => $loan
                ]);
            }

            return redirect()->route('loan.requests.index')->with('success', 'Loan updated successfully');
        } catch (\Exception $e) {
            DB::rollback();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred. Please try again later.'
                ], 500);
            }

            return back()->withErrors('An error occurred. Please try again later.');
        }
    }

    /**
     * Remove the specified loan
     */
    public function destroy($id)
    {
        try {
            $loan = Loan::findOrFail($id);

            if (!$loan->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending loans can be deleted'
                ], 400);
            }

            if ($loan->user_id != auth()->id() && !in_array(auth()->user()->role, ['admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $loan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Loan deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Approve a loan (Manager/HR)
     */
    public function approve(Request $request, $id)
    {
        try {
            $loan = Loan::findOrFail($id);

            if (!$loan->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan cannot be approved. Current status: ' . $loan->status
                ], 400);
            }

            DB::beginTransaction();

            $loan->status = Loan::STATUS_APPROVED;
            $loan->approved_by = auth()->id();
            $loan->approved_at = now();

            if ($request->has('comments')) {
                $loan->description = ($loan->description ? $loan->description . "\n" : '') .
                    "Approval comments: " . $request->comments;
            }

            $notice = null;
            if ($loan->isSalaryAdvance()) {
                // Salary advance: one row for its month; a month already processed moves to the next open one.
                $loan->save();
                $notice = $this->advances()->ensureRecoverableMonth($loan, auth()->user());
                $this->advances()->scheduleRow($loan);
            } else {
                // A request approved late must not start with instalments in the past.
                $this->alignScheduleStart($loan);

                $loan->save();

                if ($loan->repayment_type == 'lumpsum') {
                    $this->generateLumpsumRepayment($loan);
                } else {
                    $this->generateRepaymentSchedule($loan);
                }
            }

            $this->advances()->log(($loan->isSalaryAdvance() ? 'salary_advance' : 'loan') . '.approved', $loan, auth()->user(),
                ['comments' => $request->comments]);

            DB::commit();

            $this->advances()->notify($loan, $loan->kindLabel() . ' approved',
                'Your ' . strtolower($loan->kindLabel()) . ' of ₹' . number_format((float) $loan->amount, 2) . ' was approved by ' . auth()->user()->name . '.');

            return response()->json([
                'success' => true,
                'message' => $loan->kindLabel() . ' approved successfully' . ($notice ? '. ' . $notice : ''),
                'notice' => $notice,
                'data' => $loan
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a loan
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $loan = Loan::findOrFail($id);

            if (!$loan->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending loans can be rejected'
                ], 400);
            }

            $loan->status = Loan::STATUS_DEFAULT;
            $loan->rejected_by = auth()->id();
            $loan->rejected_at = now();
            $loan->rejection_reason = $request->rejection_reason;
            $loan->save();

            $this->advances()->log(($loan->isSalaryAdvance() ? 'salary_advance' : 'loan') . '.rejected', $loan, auth()->user(),
                ['reason' => $request->rejection_reason]);
            $this->advances()->notify($loan, $loan->kindLabel() . ' rejected',
                'Your ' . strtolower($loan->kindLabel()) . ' request was rejected: ' . $request->rejection_reason);

            return response()->json([
                'success' => true,
                'message' => $loan->kindLabel() . ' rejected successfully',
                'data' => $loan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Disburse a loan (make it active)
     */
    public function disburse(Request $request, $id)
    {
        try {
            $loan = Loan::findOrFail($id);

            if (!$loan->canBeDisbursed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan cannot be disbursed. Current status: ' . $loan->status
                ], 400);
            }

            $notice = null;
            DB::transaction(function () use ($loan, &$notice) {
                if ($loan->isSalaryAdvance()) {
                    // Paid after its month's payroll was processed → recovered from the next open month.
                    $notice = $this->advances()->ensureRecoverableMonth($loan, auth()->user());
                    $loan->status = Loan::STATUS_ACTIVE;
                    $loan->disbursed_by = auth()->id();
                    $loan->disbursed_at = now();
                    $loan->save();
                    $this->advances()->log('salary_advance.disbursed', $loan, auth()->user());

                    return;
                }

                // Disbursed after its first due date and nothing collected yet: restart the
                // schedule from the next salary date instead of starting already overdue.
                $repayments = LoanRepayment::where('loan_id', $loan->id)->get();
                $nothingPaid = $repayments->sum(fn ($r) => (float) $r->paid_amount) <= 0;
                $firstDue = $repayments->min('due_date');
                if ($repayments->isNotEmpty() && $nothingPaid && $firstDue && $firstDue < now()->toDateString()) {
                    LoanRepayment::where('loan_id', $loan->id)->delete();
                    $loan->first_emi_date = null;
                    $loan->lumpsum_due_date = null;
                    $this->alignScheduleStart($loan);
                    $loan->save();
                    $loan->repayment_type == 'lumpsum' ? $this->generateLumpsumRepayment($loan) : $this->generateRepaymentSchedule($loan);
                }

                $loan->status = Loan::STATUS_ACTIVE;
                $loan->disbursed_by = auth()->id();
                $loan->disbursed_at = now();
                $loan->save();
                $this->advances()->log('loan.disbursed', $loan, auth()->user());
            });

            $this->advances()->notify($loan, $loan->kindLabel() . ' paid',
                '₹' . number_format((float) $loan->amount, 2) . ' has been paid to you'
                . ($loan->isSalaryAdvance() ? ' and will be deducted from your ' . \Carbon\Carbon::parse($loan->advance_month . '-01')->format('M Y') . ' salary.' : '.'));

            return response()->json([
                'success' => true,
                'message' => ($loan->isSalaryAdvance() ? 'Salary advance paid' : 'Loan disbursed successfully') . ($notice ? '. ' . $notice : ''),
                'notice' => $notice,
                'data' => $loan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Cancel a loan request
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $loan = Loan::findOrFail($id);

            if (!$loan->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan cannot be cancelled. Current status: ' . $loan->status
                ], 400);
            }

            $loan->status = Loan::STATUS_CANCELLED;
            $loan->cancelled_by = auth()->id();
            $loan->cancelled_at = now();
            $loan->cancellation_reason = $request->cancellation_reason;
            $loan->save();
            // Nothing was paid out (pending/approved only) — drop the unpaid schedule so nothing is ever deducted.
            LoanRepayment::where('loan_id', $loan->id)->where('paid_amount', '<=', 0)->delete();

            $this->advances()->log(($loan->isSalaryAdvance() ? 'salary_advance' : 'loan') . '.cancelled', $loan, auth()->user(),
                ['reason' => $request->cancellation_reason]);

            return response()->json([
                'success' => true,
                'message' => 'Loan cancelled successfully',
                'data' => $loan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Process lump sum payment from employee
     */
    public function processLumpsumPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_mode' => 'required|string|in:bank_transfer,cheque,cash,online',
            'transaction_reference' => 'nullable|string|max:100',
            'remarks' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $loan = Loan::findOrFail($id);

            // Validate loan is lumpsum and active
            if (!$loan->isLumpsum()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a lump sum loan'
                ], 400);
            }

            if (!$loan->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan is not active or already closed'
                ], 400);
            }

            DB::beginTransaction();

            // Get the lumpsum repayment record
            $repayment = LoanRepayment::where('loan_id', $loan->id)->first();

            if (!$repayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment record not found'
                ], 404);
            }

            // Mark repayment as paid
            $repayment->update([
                'status' => 'paid',
                'paid_amount' => $repayment->total_amount,
                'paid_date' => now(),
                'payment_mode' => $request->payment_mode,
                'transaction_reference' => $request->transaction_reference,
                'payment_received_by' => auth()->id(),
                'remarks' => $request->remarks
            ]);

            // Close the loan
            $loan->update([
                'status' => Loan::STATUS_CLOSED,
                'closed_date' => now(),
                'remaining_amount' => 0
            ]);

            $this->advances()->log(($loan->isSalaryAdvance() ? 'salary_advance' : 'loan') . '.repaid_manually', $loan, auth()->user(), [
                'payment_mode' => $request->payment_mode, 'transaction_reference' => $request->transaction_reference,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lump sum payment processed successfully. Loan has been closed.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repayment schedule for a loan
     */
    public function getRepaymentSchedule($id)
    {
        try {
            $loan = Loan::with('repayments')->findOrFail($id);

            if ($loan->user_id != auth()->id() && !in_array(auth()->user()->role, ['admin', 'hr', 'manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $schedule = $loan->repayments()->orderBy('installment_number')->get();

            $summary = [
                'total_installments' => $schedule->count(),
                'paid_installments' => $schedule->where('status', 'paid')->count(),
                'pending_installments' => $schedule->where('status', 'pending')->count(),
                'overdue_installments' => $schedule->where('status', 'overdue')->count(),
                'total_paid' => $schedule->sum('paid_amount'),
                'remaining_amount' => $loan->remaining_amount,
                'completion_percentage' => $loan->progress_percentage
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'loan' => $loan,
                    'schedule' => $schedule,
                    'summary' => $summary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Pending approvals page
     */
    public function view_all(Request $request)
    {
        try {
            // Get BOTH pending AND approved loans (for approval and disbursement)
            $query = Loan::with(['user', 'loanCategory'])
                ->orderBy('created_at', 'asc');

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('loan_number', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%");
                        });
                });
            }

            // Apply category filter
            if ($request->has('category') && $request->category) {
                $query->where('loan_type_id', $request->category);
            }

            // Apply payment type filter (EMI/Lumpsum)
            // Loans & Advances: loan | salary_advance
            if (in_array($request->kind, [Loan::KIND_LOAN, Loan::KIND_SALARY_ADVANCE], true)) {
                $query->where('loan_kind', $request->kind);
            }

            if ($request->has('payment_type') && $request->payment_type) {
                $query->where('repayment_type', $request->payment_type);
            }

            // Apply employee filter
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $loans = $query->paginate(15);

            // Get categories for filter dropdown
            $categories = LoanCategory::where('status', 1)
                ->orderBy('name', 'asc')
                ->get();

            // Get employees for filter dropdown (only for admin/hr/manager)
            $employees = User::with('loans')
                ->where('status', '1')
                ->whereHas('loans')
                ->get();

            // Calculate stats
            $totalLoans = Loan::whereIn('status', ['pending', 'approved'])->count();
            $pendingCount = Loan::where('status', 'pending')->count();
            $approvedCount = Loan::where('status', 'approved')->count();
            $rejectedCount = Loan::where('status', 'default')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            return view('client.loan.loans.view-all', compact(
                'loans',
                'categories',
                'employees',
                'totalLoans',
                'pendingCount',
                'approvedCount',
                'rejectedCount'
            ));
        } catch (\Exception $e) {
            return back()->withErrors('An error occurred. Please try again later.')->with('error', $e->getMessage());
        }
    }

    /**
     * Get my loans summary (for employee dashboard)
     */
    public function myLoans(Request $request)
    {
        try {
            $loans = Loan::where('user_id', auth()->id())
                ->with('loanCategory')
                ->orderBy('created_at', 'desc')
                ->get();

            $summary = [
                'total_loans' => $loans->count(),
                'active_loans' => $loans->where('status', Loan::STATUS_ACTIVE)->count(),
                'pending_loans' => $loans->where('status', Loan::STATUS_PENDING)->count(),
                'approved_loans' => $loans->where('status', Loan::STATUS_APPROVED)->count(),
                'total_disbursed' => $loans->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_CLOSED])->sum('amount'),
                'total_outstanding' => $loans->where('status', Loan::STATUS_ACTIVE)->sum('remaining_amount'),
                'monthly_emi_total' => $loans->where('status', Loan::STATUS_ACTIVE)->sum('emi_amount'),
                'loans' => $loans
            ];

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => $summary
                ]);
            }

            return view('client.loan.my-loans', compact('summary', 'loans'));
        } catch (\Exception $e) {
            return back()->withErrors('An error occurred. Please try again later.');
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
            'repayment_number' => $loan->loan_number . '-L1',
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
                'repayment_number'     => $loan->loan_number . '-E' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
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
     * Calculate default rate
     */
    private function calculateDefaultRate($query)
    {
        $total = (clone $query)->count();
        if ($total == 0) return 0;

        $defaults = (clone $query)->where('status', Loan::STATUS_DEFAULT)->count();
        return round(($defaults / $total) * 100, 2);
    }

    /**
     * GET /loan/reports/summary -- tenant-wide loan summary for admin/hr/manager.
     * Routed but previously had no matching method (BadMethodCallException).
     */
    public function getStats(Request $request)
    {
        try {
            $statistics = $this->getLoanStatistics(true);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the summary.',
            ], 500);
        }
    }

    /**
     * GET /loan/reports/employee/{userId} -- one employee's loan history.
     * Routed but previously had no matching method (BadMethodCallException).
     */
    public function getEmployeeSummary(Request $request, $userId)
    {
        try {
            $query = Loan::with(['loanCategory', 'repayments'])
                ->where('user_id', $userId);

            if (auth()->user() && auth()->user()->tenant_id) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }

            $loans = $query->orderBy('created_at', 'desc')->get();

            $summary = [
                'total_loans' => $loans->count(),
                'active_loans' => $loans->where('status', Loan::STATUS_ACTIVE)->count(),
                'closed_loans' => $loans->where('status', Loan::STATUS_CLOSED)->count(),
                'total_disbursed' => $loans->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_CLOSED])->sum('amount'),
                'total_outstanding' => $loans->where('status', Loan::STATUS_ACTIVE)->sum('remaining_amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'loans' => $loans,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the employee summary.',
            ], 500);
        }
    }

    /**
     * GET /loan/reports/export -- CSV export of loans (optionally filtered
     * by status/category via query params, same filters as view_all()).
     * Routed but previously had no matching method (BadMethodCallException).
     */
    public function exportReport(Request $request)
    {
        $query = Loan::with(['user', 'loanCategory']);

        if (auth()->user() && auth()->user()->tenant_id) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('loan_type_id', $request->category);
        }

        $loans = $query->orderBy('created_at', 'desc')->get();

        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($handle, [
            'Loan Number', 'Employee', 'Category', 'Type', 'Amount',
            'Remaining Amount', 'Status', 'Applied Date',
        ]);

        foreach ($loans as $loan) {
            fputcsv($handle, [
                $loan->loan_number,
                optional($loan->user)->name,
                optional($loan->loanCategory)->name,
                strtoupper($loan->repayment_type),
                $loan->amount,
                $loan->remaining_amount,
                $loan->status,
                optional($loan->loan_date)->format('Y-m-d') ?? $loan->loan_date,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="loan_report_' . date('Y-m-d_His') . '.csv"',
        ]);
    }
}
