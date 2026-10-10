<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\User;
use App\Models\PayrollComponent;
use App\Services\Payroll\LoanDeductionService;
use App\Services\Payroll\PayrollEditException;
use App\Services\Payroll\PayrollEditService;
use App\Services\Payroll\PayrollEstimator;
use App\Services\Payroll\PayslipContext;
use App\Services\Payroll\PayrollRunException;
use App\Services\Payroll\PayrollRunService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Traits\ResolvesCurrentTenant;

/**
 * Monthly payroll: list, process (generate) a month, show, edit, delete.
 *
 * The work lives in services (code-quality plan, Phase 1, 2026-10-10):
 * PayrollRunService (processing a month), LegacyPayrollCalculator (legacy
 * engine maths), PayrollCalculationEngine (dynamic engine), PayslipWriter
 * (saving payslips), PayrollLoanApplier (loans
 * / advances), PayrollEditService (Edit Payroll), PayrollEstimator. Status
 * changes, payslip PDFs and the CSV export have their own controllers
 * (MonthlyPayrollStatusController, PayslipController,
 * MonthlyPayrollExportController).
 */
class MonthlyPayrollController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private LoanDeductionService $loanDeductionService,
        private RbacService $rbacService,
        private PayrollRunService $runner,
        private PayrollEditService $editor,
        private PayrollEstimator $estimator,
        private PayslipContext $context,
    ) {
    }

    /**
     * Display monthly payroll list with filters.
     * NOTE: Eloquent (MonthlyPayroll / User) is auto tenant-scoped via the
     *       BelongsToTenant trait, so no explicit tenant_id needed here.
     */
    public function index(Request $request)
    {
        try {
            $query = MonthlyPayroll::with(['user', 'processor']);

            if ($request->filled('month')) {
                $query->where('payroll_month', $request->month);
            }
            if ($request->filled('status')) {
                $query->where('payment_status', $request->status);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Aggregate against the full filtered set BEFORE pagination --
            // summing the paginated Collection itself (the previous
            // behavior) only reflects whichever 15 rows are on the current
            // page, silently understating "Total Payroll"/"Paid Amount" for
            // any filter matching more than one page.
            $totalNet = (float) (clone $query)->sum('net_payable');
            $paidAmount = (float) (clone $query)->where('payment_status', 'paid')->sum('net_payable');

            $monthlyPayrolls = $query->orderBy('payroll_month', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            $users = User::where('status', 1)
                ->where('role', '!=', 'admin')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']);

            $months = MonthlyPayroll::select('payroll_month')
                ->distinct()
                ->orderBy('payroll_month', 'desc')
                ->pluck('payroll_month');

            return view('client.payroll.monthly-payroll.index', compact('monthlyPayrolls', 'users', 'months', 'totalNet', 'paidAmount'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll index error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payroll data: ' . $e->getMessage());
        }
    }

    /**
     * Show form to process new monthly payroll
     */
    public function create()
    {
        try {
            $months = [];
            for ($i = 0; $i < 12; $i++) {
                $date = now()->subMonths($i);
                $months[$date->format('Y-m')] = $date->format('F Y');
            }

            $employees = User::where('status', 1)
                ->where('role', '!=', 'admin')
                ->whereHas('userPayrolls', fn($q) => $q->where('is_current', true))
                ->with([
                    'userPayrolls'                    => fn($q) => $q->where('is_current', true),
                    'userPayrolls.payrollMaster',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel',
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id', 'email']);

            return view('client.payroll.monthly-payroll.create', compact('months', 'employees'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll create error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading form: ' . $e->getMessage());
        }
    }

    /**
     * Process monthly payroll for one or more employees.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payroll_month'          => 'required|date_format:Y-m',
            'employee_selection'     => 'required|in:all,selected',
            'selected_employees'     => 'required_if:employee_selection,selected|array',
            'selected_employees.*'   => 'exists:users,id',
            'include_overtime'       => 'nullable|boolean',
            'include_loan_deductions' => 'nullable|boolean',
        ]);

        try {
            $result = $this->runner->run(
                $this->currentTenantId(),
                $request->payroll_month,
                $request->employee_selection,
                (array) ($request->selected_employees ?? []),
                (bool) ($request->include_overtime ?? false),
                (bool) ($request->include_loan_deductions ?? false),
                $request->has('force_reprocess')
            );
        } catch (PayrollRunException $e) {
            $back = redirect()->back()->with($e->flash, $e->getMessage());
            foreach ($e->extra as $key => $value) {
                $back->with($key, $value);
            }

            return $back->withInput();
        }

        $processedCount = $result['processed'];
        $errors = $result['errors'];
        $message = $processedCount > 0 ? "Successfully processed {$processedCount} employee(s). " : '';

        if (count($errors) > 0) {
            return redirect()
                ->route('monthly-payrolls.index', ['month' => $request->payroll_month])
                ->with('warning', $message . 'Encountered ' . count($errors) . ' error(s).')
                ->with('errors', $errors);
        }

        return redirect()
            ->route('monthly-payrolls.index', ['month' => $request->payroll_month])
            ->with('success', $message ?: "Payroll processed successfully for {$processedCount} employees.");
    }

    public function show($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.basicDetails',
                'components',
                'payrollMaster',
                'UserPayroll',
            ])->findOrFail($id);

            $bankDetails = $this->context->bankDetails((int) $monthlyPayroll->user_id, $this->currentTenantId());
            
            $userPayroll = $monthlyPayroll->user->payrolls()
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();

            // Policy alone only encodes the state precondition (not pending) --
            // the show() route itself only requires payroll,view, so also gate
            // on the same payroll,manage permission the reopen route enforces,
            // otherwise the button would appear for a viewer who'd just get a
            // permission-denied redirect on click.
            $canReopen = auth()->user()->can('reopen', $monthlyPayroll)
                && $this->rbacService->can(auth()->user(), 'payroll', 'manage');

            return view('client.payroll.monthly-payroll.show', compact('monthlyPayroll', 'bankDetails', 'userPayroll', 'canReopen'));
        } catch (\Exception $e) {
            Log::error('Monthly payroll show error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payroll record not found.');
        }
    }

    public function edit($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::with([
                'user',
                'user.payrolls' => fn($q) => $q->where('is_current', true),
            ])->findOrFail($id);

            if (auth()->user()->cannot('update', $monthlyPayroll)) {
                return redirect()->route('monthly-payrolls.index')
                    ->with('error', 'Only pending payroll records can be edited.');
            }

            return view('client.payroll.monthly-payroll.edit', $this->editor->editFormData($monthlyPayroll));
        } catch (\Exception $e) {
            Log::error('Payroll edit error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payroll record not found.');
        }
    }

    public function destroy($id)
    {
        try {
            $monthlyPayroll = MonthlyPayroll::findOrFail($id);

            if (auth()->user()->cannot('delete', $monthlyPayroll)) {
                return redirect()->back()
                    ->with('error', 'Cannot delete payroll that is already ' . $monthlyPayroll->payment_status);
            }

            $tenantId = $monthlyPayroll->tenant_id;
            if ($tenantId && app(\App\Services\Attendance\PeriodLockService::class)->isLocked($tenantId, $monthlyPayroll->payroll_month)) {
                return redirect()->back()
                    ->with('error', $monthlyPayroll->payroll_month . ' is locked. Reopen the period before deleting this payroll.');
            }

            DB::beginTransaction();
            // Reverse whatever loan-ledger allocation this payslip applied --
            // otherwise the loan_repayments row is left orphaned (FK nulled by
            // the cascade below) while still marked paid, permanently
            // desyncing the loan balance from any surviving payslip.
            $this->loanDeductionService->revokeForPayroll($monthlyPayroll->id, $tenantId);
            app(\App\Services\Expense\ExpenseReimbursementPayrollService::class)->revokeForPayroll((int) $monthlyPayroll->id, (int) $tenantId);   // reimbursements go back to the queue
            PayrollComponent::where('monthly_payroll_id', $id)->delete();
            $monthlyPayroll->delete();
            DB::commit();

            return redirect()->route('monthly-payrolls.index')
                ->with('success', 'Payroll record deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete payroll error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete payroll: ' . $e->getMessage());
        }
    }


    /**
     * Save Edit Payroll. A dynamic-engine payslip is recomputed through the
     * engine with the edited day counts; every other payslip saves the legacy
     * form's amounts (PayrollEditService).
     */
    public function update(Request $request, $id)
    {
        $monthlyPayroll = MonthlyPayroll::find($id);
        if ($this->editor->usesDynamicEdit($monthlyPayroll)) {
            return $this->updateDynamic($request, $monthlyPayroll);
        }

        $validator = Validator::make($request->all(), [
            'payroll_month'      => 'required|date_format:Y-m',
            'present_days'       => 'required|numeric|min:0',
            'paid_leaves'        => 'nullable|numeric|min:0',
            'overtime_hours'     => 'nullable|numeric|min:0',
            'shift_allowance_amount' => 'nullable|numeric|min:0',
            'basic_salary'       => 'required|numeric|min:0',
            'hra'                => 'required|numeric|min:0',
            'conveyence'         => 'required|numeric|min:0',
            'medical_allowance'  => 'required|numeric|min:0',
            'provident_fund'     => 'required|numeric|min:0',
            'esi'                => 'required|numeric|min:0',
            'professional_tax'   => 'required|numeric|min:0',
            'tds'                => 'nullable|numeric|min:0',
            'actual_worked_hours' => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
            'loan_deduction'              => 'nullable|numeric|min:0',
            'loan_deduction_enabled'      => 'nullable|boolean',
            'salary_advance_deduction'         => 'nullable|numeric|min:0',
            'salary_advance_deduction_enabled' => 'nullable|boolean',
            'confirm_negative_net_payable' => 'nullable|boolean',
            'include_pending_overtime'    => 'nullable|boolean',
            'pending_overtime_request_ids'   => 'nullable|array',
            'pending_overtime_request_ids.*' => 'integer|exists:overtime_requests,id',
        ]);

        // Computed before validation finishes so a negative net pay can be refused as a form error.
        $netPayablePreview = $this->editor->legacyNetPreview($request, $id);
        $validator->after(function ($v) use ($request, $netPayablePreview) {
            if ($netPayablePreview < 0 && ! $request->boolean('confirm_negative_net_payable')) {
                $v->errors()->add(
                    'loan_deduction',
                    'Net payable would be ₹' . number_format($netPayablePreview, 2) . ' (negative). '
                        . 'Reduce the loan deduction amount, or check "Proceed anyway" to confirm.'
                );
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $this->editor->saveLegacy($request, $id);
        } catch (PayrollEditException $e) {
            $back = redirect()->back()->with('error', $e->getMessage());

            return $e->withInput ? $back->withInput() : $back;
        }

        return redirect()->route('monthly-payrolls.show', $id)
            ->with('success', 'Payroll updated successfully.');
    }

    /** Dynamic-engine Edit/Update — entered only from update(). */
    private function updateDynamic(Request $request, MonthlyPayroll $monthlyPayroll)
    {
        if (auth()->user()->cannot('update', $monthlyPayroll)) {
            return redirect()->route('monthly-payrolls.index')
                ->with('error', 'Only pending payroll records can be edited.');
        }

        $validator = Validator::make($request->all(), [
            'present_days' => 'required|numeric|min:0',
            'paid_leaves' => 'nullable|numeric|min:0',
            'week_offs' => 'nullable|numeric|min:0',
            'holidays' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'loan_deduction_enabled' => 'nullable|boolean',
            'confirm_negative_net_payable' => 'nullable|boolean',
            'components' => 'nullable|array',
            'components.*' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $this->editor->saveDynamic($request, $monthlyPayroll, $validator->validated(), $request->boolean('loan_deduction_enabled', true));
        } catch (PayrollEditException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('monthly-payrolls.show', $monthlyPayroll->id)
            ->with('success', 'Payroll updated successfully.');
    }

    /** Read-only live preview for the dynamic Edit form (same engine call as the save). */
    public function recalculatePreview(Request $request, $id)
    {
        $monthlyPayroll = MonthlyPayroll::findOrFail($id);

        $data = $request->validate([
            'present_days' => 'required|numeric|min:0',
            'paid_leaves' => 'nullable|numeric|min:0',
            'week_offs' => 'nullable|numeric|min:0',
            'holidays' => 'nullable|numeric|min:0',
            'loan_deduction_enabled' => 'nullable|boolean',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->editor->preview($monthlyPayroll, $data, $request->boolean('loan_deduction_enabled', true)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Recalculate preview error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to recalculate: ' . $e->getMessage()], 500);
        }
    }

    /** Estimated totals on Process Payroll before running it. */
    public function calculateEstimates(Request $request)
    {
        try {
            return response()->json($this->estimator->estimate(
                $request->employee_ids,
                $request->payroll_month,
                $request->boolean('include_loans', true),
                $this->currentTenantId()
            ));
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
