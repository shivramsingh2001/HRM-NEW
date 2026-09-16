<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\BlocksLegacyPayrollWrites;

class PayrollMasterController extends Controller
{
    use BlocksLegacyPayrollWrites;

    /**
     * Server-side mirror of create-payroll-master.blade.php's restrictPercentInput() cap --
     * previously only enforced in the browser, so a direct POST (curl/disabled JS) could save
     * a payroll master whose earnings or deductions percentages exceed 100%.
     */
    private function applyPercentCapValidation(\Illuminate\Contracts\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function ($v) use ($request) {
            $earningsTotal = (float) ($request->hra ?? 0)
                + (float) ($request->conveyence ?? 0)
                + (float) ($request->medical_allowance ?? 0)
                + (float) ($request->children_allowance ?? 0)
                + (float) ($request->post_allowance ?? 0)
                + (float) ($request->leave_travel_allowance ?? 0)
                + (float) ($request->monthly_incentive ?? 0);

            if ($earningsTotal > 100) {
                $v->errors()->add('hra', 'Total earnings percentage cannot exceed 100% (currently ' . round($earningsTotal, 2) . '%).');
            }

            $deductionsTotal = (float) ($request->provident_fund ?? 0)
                + (float) ($request->employer_provident_fund ?? 0)
                + (float) ($request->esi ?? 0)
                + (float) ($request->employer_esi ?? 0)
                + (float) ($request->pt ?? 0);

            if ($deductionsTotal > 100) {
                $v->errors()->add('provident_fund', 'Total deductions percentage cannot exceed 100% (currently ' . round($deductionsTotal, 2) . '%).');
            }
        });
    }

    private function generatePayrollCode()
    {
        // Get the latest payroll master record
        $latestPayroll = PayrollMaster::orderBy('id', 'desc')->first();

        if (!$latestPayroll) {
            // If no records exist, start with PM00001
            return 'PM00001';
        }

        // Extract the numeric part from the latest code
        $latestCode = $latestPayroll->payroll_code ?? 'PM00000';
        $number = intval(substr($latestCode, 2)); // Remove 'PM' and convert to number

        // Increment and format with leading zeros
        $newNumber = $number + 1;
        $newCode = 'PM' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

        // Check if the generated code already exists (safety check)
        $existingCode = PayrollMaster::where('payroll_code', $newCode)->exists();

        // If code exists, recursively generate next code
        if ($existingCode) {
            return $this->generatePayrollCode();
        }

        return $newCode;
    }
    public function index(Request $request)
    {

        $query = PayrollMaster::query();
        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $payrollMasters = $query->orderBy('created_at', 'desc')->get();

        return view('client.payroll.master.view-payroll-master', compact('payrollMasters'));
    }

    /**
     * Show the form for creating a new payroll master.
     */
    public function create()
    {
        return view('client.payroll.master.create-payroll-master');
    }

    /**
     * Store a newly created payroll master.
     */
    public function store(Request $request)
    {
        if ($blocked = $this->blockedByDynamicCutover()) {
            return $blocked;
        }

        $tenantId = app('current_tenant')->id;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('payroll_masters', 'name')->where('tenant_id', $tenantId)],
            'hra' => 'nullable|numeric|min:0',
            'conveyence' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'children_allowance' => 'nullable|numeric|min:0',
            'post_allowance' => 'nullable|numeric|min:0',
            'leave_travel_allowance' => 'nullable|numeric|min:0',
            'monthly_incentive' => 'nullable|numeric|min:0',
            'provident_fund' => 'nullable|numeric|min:0',
            'employer_provident_fund' => 'nullable|numeric|min:0',
            'esi' => 'nullable|numeric|min:0',
            'employer_esi' => 'nullable|numeric|min:0',
            'pt' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'payroll_calculation_type' => 'required|in:day_based,hour_based',
            'working_hours_per_day' => 'required_if:payroll_calculation_type,hour_based|nullable|numeric|min:0|max:24',
            'hourly_rate_applied' => 'nullable|numeric|min:0',
            'ot_rate_divisor_mode' => 'nullable|in:calendar_days,fixed_working_days',
            'ot_fixed_working_days' => 'nullable|integer|min:1|max:31',
        ]);
        $this->applyPercentCapValidation($validator, $request);
        $validator->validate();

        try {
            DB::beginTransaction();
            $payrollCode = $this->generatePayrollCode();
            PayrollMaster::create([
                'payroll_code' => $payrollCode,
                'name' => $request->name,
                'hra' => $request->hra ?? 0,
                'conveyence' => $request->conveyence ?? 0,
                'medical_allowance' => $request->medical_allowance ?? 0,
                'children_allowance' => $request->children_allowance ?? 0,
                'post_allowance' => $request->post_allowance ?? 0,
                'leave_travel_allowance' => $request->leave_travel_allowance ?? 0,
                'monthly_incentive' => $request->monthly_incentive ?? 0,
                'provident_fund' => $request->provident_fund ?? 0,
                'employer_provident_fund' => $request->employer_provident_fund ?? 0,
                'esi' => $request->esi ?? 0,
                'employer_esi' => $request->employer_esi ?? 0,
                'pt' => $request->pt ?? 0,
                'description' => $request->description,
                'status' => 1,
                'payroll_calculation_type' => $request->payroll_calculation_type,
                'working_hours_per_day' => $request->payroll_calculation_type == 'hour_based' ? ($request->working_hours_per_day ?? 8.00) : null,
                'hourly_rate_applied' => $request->hourly_rate_applied ?? null,
                'ot_rate_divisor_mode' => $request->ot_rate_divisor_mode ?? 'calendar_days',
                'ot_fixed_working_days' => $request->ot_fixed_working_days ?? 26,
            ]);

            DB::commit();

            return redirect()->route('payroll-masters.index')
                ->with('success', 'Payroll master created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create payroll master. ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified payroll master.
     */
    public function edit($id)
    {
        $payrollMaster = PayrollMaster::findOrFail($id);

        return view('client.payroll.master.update-payroll-master', compact('payrollMaster'));
    }

    /**
     * Update the specified payroll master.
     */
    public function update(Request $request, $id)
    {
        if ($blocked = $this->blockedByDynamicCutover()) {
            return $blocked;
        }

        $payrollMaster = PayrollMaster::findOrFail($id);

        $tenantId = app('current_tenant')->id;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('payroll_masters', 'name')->where('tenant_id', $tenantId)->ignore($id)],
            'hra' => 'nullable|numeric|min:0',
            'conveyence' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'children_allowance' => 'nullable|numeric|min:0',
            'post_allowance' => 'nullable|numeric|min:0',
            'leave_travel_allowance' => 'nullable|numeric|min:0',
            'monthly_incentive' => 'nullable|numeric|min:0',
            'provident_fund' => 'nullable|numeric|min:0',
            'employer_provident_fund' => 'nullable|numeric|min:0',
            'esi' => 'nullable|numeric|min:0',
            'employer_esi' => 'nullable|numeric|min:0',
            'pt' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'payroll_calculation_type' => 'required|in:day_based,hour_based',
            'working_hours_per_day' => 'required_if:payroll_calculation_type,hour_based|nullable|numeric|min:0|max:24',
            'hourly_rate_applied' => 'nullable|numeric|min:0',
            'ot_rate_divisor_mode' => 'nullable|in:calendar_days,fixed_working_days',
            'ot_fixed_working_days' => 'nullable|integer|min:1|max:31',
        ]);
        $this->applyPercentCapValidation($validator, $request);
        $validator->validate();

        try {
            DB::beginTransaction();

            $payrollMaster->update([
                'name' => $request->name,
                'hra' => $request->hra ?? 0,
                'conveyence' => $request->conveyence ?? 0,
                'medical_allowance' => $request->medical_allowance ?? 0,
                'children_allowance' => $request->children_allowance ?? 0,
                'post_allowance' => $request->post_allowance ?? 0,
                'leave_travel_allowance' => $request->leave_travel_allowance ?? 0,
                'monthly_incentive' => $request->monthly_incentive ?? 0,
                'provident_fund' => $request->provident_fund ?? 0,
                'employer_provident_fund' => $request->employer_provident_fund ?? 0,
                'esi' => $request->esi ?? 0,
                'employer_esi' => $request->employer_esi ?? 0,
                'pt' => $request->pt ?? 0,
                'description' => $request->description,
                  'payroll_calculation_type' => $request->payroll_calculation_type,
                'working_hours_per_day' => $request->payroll_calculation_type == 'hour_based' ? ($request->working_hours_per_day ?? 8.00) : null,
                'hourly_rate_applied' => $request->hourly_rate_applied ?? null,
                'ot_rate_divisor_mode' => $request->ot_rate_divisor_mode ?? 'calendar_days',
                'ot_fixed_working_days' => $request->ot_fixed_working_days ?? 26,
            ]);

            DB::commit();

            return redirect()->route('payroll-masters.index')
                ->with('success', 'Payroll master updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update payroll master. ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update status of payroll master.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|boolean'
        ]);

        try {
            $payrollMaster = PayrollMaster::findOrFail($id);

            if (!$payrollMaster) {
                return response()->json(['success' => false, 'message' => 'Payroll Master not found.'], 404);
            }
            $payrollMaster->status = $payrollMaster->status == 1 ? 0 : 1;
            $payrollMaster->save();
            return response()->json(['success' => true, 'status' => $payrollMaster->status, 'message' => 'Status Updated Successfully'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
