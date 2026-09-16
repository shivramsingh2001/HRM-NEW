<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPayroll;
use App\Models\UserPayroll;
use App\Models\User;
use App\Models\PayrollMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Traits\BlocksLegacyPayrollWrites;

class UserPayrollController extends Controller
{
    use BlocksLegacyPayrollWrites;

    public function index(Request $request)
    {
        $query = UserPayroll::with(['user', 'payrollMaster']);

        // Filter by user
        if ($request->has('user_id') && $request->user_id != '') {
            $query->where('user_id', $request->user_id);
        }

        // Filter by current status
        if ($request->has('is_current') && $request->is_current !== '') {
            $query->where('is_current', $request->is_current);
        }

        $employeePayrolls = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get users for filter dropdown
        $users = User::where('status', '1')
            ->where('role',"!=","admin")
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        return view('client.payroll.employee-payroll.index', compact('employeePayrolls', 'users'));
    }

    /**
     * Show form to assign payroll to employee
     */
    public function create(Request $request)
    {
        $selectedUser = null;
        if ($request->has('user_id')) {
            $selectedUser = User::find($request->user_id);
        }

        $users = User::where('status', '1')
            ->where('role',"!=","admin")
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        $payrollMasters = PayrollMaster::where('status', 1)
            ->orderBy('name')
            ->get();

        return view('client.payroll.employee-payroll.create', compact('users', 'payrollMasters', 'selectedUser'));
    }

    /**
     * Store employee payroll assignment (Updated with Annual CTC)
     */
    public function store(Request $request)
    {
        if ($blocked = $this->blockedByDynamicCutover()) {
            return $blocked;
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'payroll_master_id' => 'nullable|exists:payroll_masters,id',
            'annual_ctc' => 'required|numeric|min:100000',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'basic_salary' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'conveyence' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'children_allowance' => 'nullable|numeric|min:0',
            'post_allowance' => 'nullable|numeric|min:0',
            'leave_travel_allowance' => 'nullable|numeric|min:0',
            'monthly_incentive' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'provident_fund' => 'nullable|numeric|min:0',
            'employer_provident_fund' => 'nullable|numeric|min:0',
            'esi' => 'nullable|numeric|min:0',
            'employer_esi' => 'nullable|numeric|min:0',
            'professional_tax' => 'nullable|numeric|min:0',
            'tds' => 'nullable|numeric|min:0',
            'is_current' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Check if employee already has current payroll
            if ($request->has('is_current') && $request->is_current) {
                UserPayroll::where('user_id', $request->user_id)
                    ->where('is_current', true)
                    ->update([
                        'is_current' => false,
                        'effective_to' => Carbon::parse($request->effective_from)->subDay()->format('Y-m-d')
                    ]);
            }

            // Generate payroll code
            $payrollCode = $this->generatePayrollCode($request->user_id);

            // Calculate monthly CTC
            $monthlyCTC = $request->annual_ctc / 12;

            // Calculate all components
            $hra = $request->hra ?? 0;
            $conveyence = $request->conveyence ?? 0;
            $medical = $request->medical_allowance ?? 0;
            $children = $request->children_allowance ?? 0;
            $post = $request->post_allowance ?? 0;
            $lta = $request->leave_travel_allowance ?? 0;
            $incentive = $request->monthly_incentive ?? 0;
            $special = $request->special_allowance ?? 0;

            // Calculate totals
            $totalAllowances = $hra + $conveyence + $medical + $children + $post + $lta + $incentive + $special;
            $grossSalary = $request->basic_salary + $totalAllowances;

            $provident_fund = $request->provident_fund ?? 0;
            $employer_pf = $request->employer_provident_fund ?? 0;
            $esi = $request->esi ?? 0;
            $employer_esi = $request->employer_esi ?? 0;
            $pt = $request->professional_tax ?? 0;
            $tds = $request->tds ?? 0;

            $totalDeductions = $provident_fund + $esi + $pt + $tds;
            $netSalary = $grossSalary - $totalDeductions;
            $ctc = ($grossSalary) * 12;

            // Create employee payroll
            UserPayroll::create([
                'user_id' => $request->user_id,
                'payroll_master_id' => $request->payroll_master_id,
                'payroll_code' => $payrollCode,
                'effective_from' => $request->effective_from,
                'effective_to' => $request->effective_to,
                'is_current' => $request->has('is_current') ? true : false,
                'basic_salary' => $request->basic_salary,
                'hra' => $hra,
                'conveyence' => $conveyence,
                'medical_allowance' => $medical,
                'children_allowance' => $children,
                'post_allowance' => $post,
                'leave_travel_allowance' => $lta,
                'monthly_incentive' => $incentive,
                'special_allowance' => $special,
                'provident_fund' => $provident_fund,
                'employer_provident_fund' => $employer_pf,
                'esi' => $esi,
                'employer_esi' => $employer_esi,
                'professional_tax' => $pt,
                'tds' => $tds,
                'gross_salary' => $grossSalary,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'ctc' => $ctc,
                'notes' => $request->notes,
                'status' => true
            ]);

            // Update user_job_details with payroll_master_id
            DB::table('user_job_details')
                ->where('user_id', $request->user_id)
                ->update(['payroll_master_id' => $request->payroll_master_id]);

            DB::commit();

            return redirect()->route('employee-payrolls.index')
                ->with('success', 'Payroll assigned successfully. Code: ' . $payrollCode);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to assign payroll: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show form to edit employee payroll
     */
    public function edit($id)
    {
        $employeePayroll = UserPayroll::with('user')->findOrFail($id);

        $payrollMasters = PayrollMaster::where('status', 1)
            ->orderBy('name')
            ->get();

        return view('client.payroll.employee-payroll.edit', compact('employeePayroll', 'payrollMasters'));
    }

    /**
     * Update employee payroll (Updated with Annual CTC)
     */
    public function update(Request $request, $id)
    {
        if ($blocked = $this->blockedByDynamicCutover()) {
            return $blocked;
        }

        $userPayroll = UserPayroll::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'payroll_master_id' => 'nullable|exists:payroll_masters,id',
            'annual_ctc' => 'required|numeric|min:100000',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'basic_salary' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            'conveyence' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'children_allowance' => 'nullable|numeric|min:0',
            'post_allowance' => 'nullable|numeric|min:0',
            'leave_travel_allowance' => 'nullable|numeric|min:0',
            'monthly_incentive' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'provident_fund' => 'nullable|numeric|min:0',
            'employer_provident_fund' => 'nullable|numeric|min:0',
            'esi' => 'nullable|numeric|min:0',
            'employer_esi' => 'nullable|numeric|min:0',
            'professional_tax' => 'nullable|numeric|min:0',
            'tds' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Calculate monthly CTC
            $monthlyCTC = $request->annual_ctc / 12;

            // Calculate all components
            $hra = $request->hra ?? 0;
            $conveyence = $request->conveyence ?? 0;
            $medical = $request->medical_allowance ?? 0;
            $children = $request->children_allowance ?? 0;
            $post = $request->post_allowance ?? 0;
            $lta = $request->leave_travel_allowance ?? 0;
            $incentive = $request->monthly_incentive ?? 0;
            $special = $request->special_allowance ?? 0;

            // Calculate totals
            $totalAllowances = $hra + $conveyence + $medical + $children + $post + $lta + $incentive + $special;
            $grossSalary = $request->basic_salary + $totalAllowances;

            $provident_fund = $request->provident_fund ?? 0;
            $employer_pf = $request->employer_provident_fund ?? 0;
            $esi = $request->esi ?? 0;
            $employer_esi = $request->employer_esi ?? 0;
            $pt = $request->professional_tax ?? 0;
            $tds = $request->tds ?? 0;

            $totalDeductions = $provident_fund + $esi + $pt + $tds;
            $netSalary = $grossSalary - $totalDeductions;
            $ctc = ($grossSalary) * 12;

            // Update payroll
            $userPayroll->update([
                'payroll_master_id' => $request->payroll_master_id,
                'effective_from' => $request->effective_from,
                'effective_to' => $request->effective_to,
                'basic_salary' => $request->basic_salary,
                'hra' => $hra,
                'conveyence' => $conveyence,
                'medical_allowance' => $medical,
                'children_allowance' => $children,
                'post_allowance' => $post,
                'leave_travel_allowance' => $lta,
                'monthly_incentive' => $incentive,
                'special_allowance' => $special,
                'provident_fund' => $provident_fund,
                'employer_provident_fund' => $employer_pf,
                'esi' => $esi,
                'employer_esi' => $employer_esi,
                'professional_tax' => $pt,
                'tds' => $tds,
                'gross_salary' => $grossSalary,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'ctc' => $ctc,
                'notes' => $request->notes ?? $userPayroll->notes,
            ]);

            DB::commit();

            return redirect()->route('employee-payrolls.index')
                ->with('success', 'Payroll updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update payroll: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Generate unique payroll code
     */
    private function generatePayrollCode($userId)
    {
        $user = User::find($userId);
        $empCode = $user->employee_id ?? 'EMP';
        $year = date('Y');
        $month = date('m');

        $lastPayroll = UserPayroll::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPayroll) {
            $lastNumber = intval(substr($lastPayroll->payroll_code, -3));
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return "{$empCode}-{$year}{$month}-{$newNumber}";
    }

    public function destroy($id)
    {
        try {
            $userPayroll = UserPayroll::findOrFail($id);

            // Check if this payroll has any monthly payrolls
            // Fix: Use the correct column name 'employee_payroll_id'
            $monthlyPayrollsCount = MonthlyPayroll::where('employee_payroll_id', $id)->count();

            if ($monthlyPayrollsCount > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot delete payroll that has ' . $monthlyPayrollsCount . ' monthly payroll records.');
            }

            $userPayroll->delete();

            return redirect()->route('employee-payrolls.index')
                ->with('success', 'Payroll deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to delete payroll: ' . $e->getMessage());
        }
    }
}
