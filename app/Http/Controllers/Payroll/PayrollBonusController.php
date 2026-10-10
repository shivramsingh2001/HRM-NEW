<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollBonus;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Payroll rebuild — Phase 6.
 *
 * Bonus entry + approval. A bonus targets a specific payroll month (found
 * or created as a PayrollPeriod row) so PayrollCalculationEngine can pick
 * it up as a payslip line item once approved. Kept distinct from a
 * recurring allowance component, per the original scope: one-off,
 * approval-gated, its own table.
 *
 * Add/Edit is a side drawer on the index page — only a still-`draft` bonus
 * (not yet approved/rejected) can be edited.
 */
class PayrollBonusController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollBonus::with(['user', 'targetPeriod']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bonuses = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $employees = User::where('status', 1)->orderBy('name')->get(['id', 'name', 'employee_id']);

        return view('client.payroll.bonuses.index', compact('bonuses', 'employees'));
    }

    /**
     * JSON prefill for the Edit drawer.
     */
    public function show($id)
    {
        $bonus = PayrollBonus::with('targetPeriod')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $bonus->id,
                'user_id' => $bonus->user_id,
                'bonus_type' => $bonus->bonus_type,
                'name' => $bonus->name,
                'amount' => $bonus->amount,
                'is_taxable' => $bonus->is_taxable,
                'target_month' => optional($bonus->targetPeriod)->year_month,
                'notes' => $bonus->notes,
                'status' => $bonus->status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = app('current_tenant')->id;

        $validator = $this->bonusValidator($request);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            $period = PayrollPeriod::firstOrCreate(
                ['tenant_id' => $tenantId, 'year_month' => $data['target_month']],
                ['status' => 'open']
            );

            $bonus = PayrollBonus::create([
                'tenant_id' => $tenantId,
                'user_id' => $data['user_id'],
                'bonus_type' => $data['bonus_type'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'is_taxable' => $request->boolean('is_taxable', true),
                'is_recurring' => false,
                'target_payroll_period_id' => $period->id,
                'status' => 'draft',
                'created_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            $approvalRequest = app(ApprovalService::class)->open('payroll_bonus', $bonus, auth()->user());

            if ($approvalRequest) {
                $bonus->update(['approval_request_id' => $approvalRequest->id]);
                $message = 'Bonus submitted for approval.';
            } else {
                // No payroll_bonus workflow configured — same single-approver
                // fallback contract every other request_type in this engine
                // uses: the creator's own submission stands as approved.
                $bonus->update(['status' => 'approved', 'approved_by' => auth()->id()]);
                $message = 'Bonus added and approved.';
            }

            DB::commit();

            if (! $approvalRequest) {
                app(\App\Services\PayrollNotificationService::class)->notifyBonusApproved($bonus);
            }

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add bonus: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $bonus = PayrollBonus::findOrFail($id);
        $tenantId = app('current_tenant')->id;

        if ($bonus->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'This bonus has already been decided and can no longer be edited.',
            ], 422);
        }

        $validator = $this->bonusValidator($request);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            $period = PayrollPeriod::firstOrCreate(
                ['tenant_id' => $tenantId, 'year_month' => $data['target_month']],
                ['status' => 'open']
            );

            $bonus->update([
                'user_id' => $data['user_id'],
                'bonus_type' => $data['bonus_type'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'is_taxable' => $request->boolean('is_taxable', true),
                'target_payroll_period_id' => $period->id,
                'notes' => $data['notes'] ?? null,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Bonus updated successfully.']);
        } catch (\Exception $e) {
            report($e);
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update bonus: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function decide(Request $request, $id, string $action)
    {
        if (! in_array($action, ['approve', 'reject'], true)) {
            abort(404);
        }

        $bonus = PayrollBonus::findOrFail($id);

        if ($bonus->status !== 'draft') {
            return redirect()->back()->with('error', 'This bonus has already been decided.');
        }

        $decided = app(ApprovalService::class)->decide(
            'payroll_bonus',
            $bonus,
            auth()->user(),
            $action === 'approve' ? 'approved' : 'rejected',
            $request->input('remarks')
        );

        if (! $decided) {
            // No workflow / no pending request for this bonus — act directly,
            // same single-approver fallback as store().
            $bonus->update([
                'status' => $action === 'approve' ? 'approved' : 'cancelled',
                'approved_by' => auth()->id(),
            ]);
            if ($action === 'approve') {
                app(\App\Services\PayrollNotificationService::class)->notifyBonusApproved($bonus);
            }
        }

        return redirect()->route('payroll-bonuses.index')->with('success', 'Bonus ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
    }

    private function bonusValidator(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'bonus_type' => 'required|in:performance,festival,referral,retention,statutory_annual,one_off,other',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'is_taxable' => 'nullable|boolean',
            'target_month' => 'required|date_format:Y-m',
            'notes' => 'nullable|string|max:500',
        ]);
    }
}
