<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\CompanyBranch;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Language;
use App\Models\LeaveType;
use App\Models\PayrollStructure;
use App\Models\User;
use App\Models\UserBankDetail;
use App\Models\UserBasicDetail;
use App\Models\UserJobDetail;
use App\Models\UserLocation;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Edit Employee (employee.edit, update, update-step 1-7, update-complete). Moved out
 * of UserController unchanged (code-quality plan, Phase 4); route names are the same.
 */
class EmployeeEditController extends Controller
{
    use \App\Http\Controllers\Concerns\EmployeeFormHelpers;

    /**
     * Show the form for editing an employee
     */
    public function edit($id)
    {
        try {
            $id = decrypt($id);
            $user = User::with(['basicDetails', 'jobDetails', 'bankDetails', 'location'])
                ->where('id', $id)
                ->where('role', '!=', 'admin')
                ->firstOrFail();

            $leave_types = LeaveType::where('status', 1)->get();

            // ✅ Add this line to decode the assigned leave types
            $selectedLeaveTypes = [];
            if ($user->jobDetails && $user->jobDetails->leave_assigned) {
                $selectedLeaveTypes = json_decode($user->jobDetails->leave_assigned, true);
            }
            $dynamicStructure = $user->currentDynamicPayrollStructure;
            $currentPayroll = $dynamicStructure
                ? (object) app(PayrollStructureAssignmentService::class)->toLegacyShapedArray($dynamicStructure)
                : null;
            $data = [
                'user' => $user,
                'currentPayroll' => $currentPayroll,
                'leave_types' => $leave_types,
                'selectedLeaveTypes' => $selectedLeaveTypes,
                'departments' => Department::where('status', 1)->get(),
                'designations' => Designation::where('status', 1)->get(),
                'employees' => User::where('role', '!=', 'employee')
                    ->where('id', '!=', $id)
                    ->where('status', 1)
                    ->get(),
                'languages' => Language::where('status', 1)->get(),
                'payrollStructures' => PayrollStructure::where('status', 1)->with('components.component')->orderBy('name')->get(),
                'countries' => Country::where('status', 1)->get(),
                'branches' => AttendanceLocation::where('status', 1)->get(),
                'companyBranches' => CompanyBranch::where('status', 1)->get(),
            ];

            return view('client.user.update-user', $data);
        } catch (\Exception $e) {
            Log::error('Error fetching employee for edit: '.$e->getMessage());

            return redirect()->route('employee')
                ->with('error', 'Employee not found or error loading data.');
        }
    }

    /**
     * Update an employee
     */
    public function update(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $user = User::findOrFail($id);

            $this->normalizeJobLocationInput($request);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,'.$id,
                'contact' => 'required|digits:10',
                'status' => 'required|in:0,1',
                'role' => 'required',
                'bank_name' => 'nullable|min:3|max:255',
                'account_number' => 'nullable|digits_between:9,18',
                'ifsc' => 'nullable|size:11',
                'branch_name' => 'nullable|min:3|max:255',
                'father_name' => 'nullable|min:3|max:255',
                'mother_name' => 'nullable|min:3|max:255',
                'language' => 'nullable|array',
                'language.*' => 'nullable|string',
                'dob' => 'nullable|date',
                'gender' => 'nullable|in:m,f,o',
                'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'blood_group' => 'nullable|max:10',
                'marital_status' => 'nullable|in:single,married,widow,divorced',
                'nationality' => 'nullable|string',
                'alternate_phone' => 'nullable|digits:10',
                'personal_email' => 'nullable|email|unique:user_basic_details,personal_email,'.($user->basicDetails->id ?? 'NULL'),
                'aadhaar_no' => 'nullable|digits:12',
                'pan_no' => 'nullable|size:10',
                'about' => 'nullable|min:5|max:255',
                'passport_number' => 'nullable',
                'designation' => 'nullable|exists:designations,id',
                'department' => 'nullable|exists:departments,id',
                'reporting_head' => 'nullable|array',
                'reporting_head.*' => 'distinct|exists:users,id',
                'joining_date' => 'nullable|date',
                'employment_type' => 'nullable',
                'salary' => 'nullable|numeric',
                ...$this->jobLocationRules(),
                'company_branch' => 'nullable|exists:company_branches,id',
                'country' => 'nullable|string',
                'state' => 'nullable|string',
                'city' => 'nullable|string',
                'permanent_address' => 'nullable|string',
                'current_address' => 'nullable|string',
                'pin_code' => 'nullable|digits:6',
            ]);

            DB::beginTransaction();

            $filePaths = $this->handleFileUploads($request, $user->employee_id);
            $this->saveEmployeeDocuments($user, $this->documentRows($request));

            // Update User
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'contact' => $validated['contact'],
                'status' => $validated['status'],
                'role' => $validated['role'],
            ]);

            // Update or Create User Basic Details
            $basicDetailsData = [
                'personal_email' => $validated['personal_email'] ?? null,
                'alternate_phone' => $validated['alternate_phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'about' => $validated['about'] ?? null,
                'father_name' => $validated['father_name'] ?? null,
                'mother_name' => $validated['mother_name'] ?? null,
                'blood_group' => $validated['blood_group'] ?? null,
                'aadhaar_no' => $validated['aadhaar_no'] ?? null,
                'pan_no' => $validated['pan_no'] ?? null,
                'passport_number' => $validated['passport_number'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'language' => json_encode($validated['language'] ?? []),
            ];

            // Add file paths to basic details
            foreach ($filePaths as $field => $path) {
                if ($field == 'profile_photo') {
                    $basicDetailsData['profile_image'] = $path;
                } else {
                    $basicDetailsData[$field] = $path;
                }
            }

            if ($user->basicDetails) {
                $user->basicDetails->update($basicDetailsData);
            } else {
                $basicDetailsData['user_id'] = $user->id;
                UserBasicDetail::create($basicDetailsData);
            }

            // Update or Create Bank Details
            $bankData = [
                'bank_name' => $validated['bank_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'ifsc' => $validated['ifsc'] ?? null,
                'branch_name' => $validated['branch_name'] ?? null,
            ];

            if ($user->bankDetails) {
                $user->bankDetails->update($bankData);
            } else {
                $bankData['user_id'] = $user->id;
                UserBankDetail::create($bankData);
            }

            // Update or Create Job Details
            $primaryReportingHeadId = $this->syncReportingHeads($user, $validated['reporting_head'] ?? []);
            $jobData = [
                'designation' => $validated['designation'] ?? null,
                'department' => $validated['department'] ?? null,
                'joining_date' => $validated['joining_date'] ?? null,
                'reporting_head' => $primaryReportingHeadId,
                'employment_type' => $validated['employment_type'] ?? null,
                'salary' => $validated['salary'] ?? null,
                'type' => $validated['type'] ?? 'office',
                'office_branch' => $validated['branch'] ?? null,
                'branch_id' => $validated['company_branch'] ?? null,
            ];

            if ($user->jobDetails) {
                $user->jobDetails->update($jobData);
            } else {
                $jobData['user_id'] = $user->id;
                UserJobDetail::create($jobData);
            }

            // Update or Create Address Details
            $locationData = [
                'address' => $validated['current_address'] ?? null,
                'country' => $validated['country'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'pincode' => $validated['pin_code'] ?? null,
                'permanent_address' => $validated['permanent_address'] ?? null,
            ];

            if ($user->location) {
                $user->location->update($locationData);
            } else {
                $locationData['user_id'] = $user->id;
                UserLocation::create($locationData);
            }

            DB::commit();

            return redirect()->route('employee')
                ->with('success', 'Employee updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee update error: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'Error updating employee: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update step data
     */
    public function updateStep(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $user = User::findOrFail($id);
            $step = $request->input('step');

            DB::beginTransaction();

            // Handle file uploads
            $filePaths = $this->handleFileUploads($request, $user->employee_id);

            switch ($step) {
                case 1:
                    $this->updateStep1($request, $user, $filePaths);
                    break;
                case 2:
                    $this->updateStep2($request, $user, $filePaths);
                    break;
                case 3:
                    $this->updateStep3($request, $user);
                    break;
                case 4:
                    $this->updateStep4($request, $user);
                    break;
                case 5:
                    $this->updateStep5($request, $user);
                    break;
                case 6:
                    $this->updateStep6($request, $user);
                    break;
                case 7:
                    $this->updateStep7($request, $user, $filePaths);
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Step '.$step.' updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update step 1 (Login Details)
     */
    private function updateStep1($request, $user, $filePaths)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'contact' => 'required|digits:10|unique:users,contact,'.$user->id,
            'role' => 'required|in:employee,manager,admin,hr',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'contact' => $validated['contact'],
            'role' => $validated['role'],
        ];

        $user->update($updateData);

        // Save profile photo if uploaded
        if (isset($filePaths['profile_photo'])) {
            $basicDetails = UserBasicDetail::firstOrNew(['user_id' => $user->id]);
            $basicDetails->profile_image = $filePaths['profile_photo'];
            $basicDetails->user_id = $user->id;
            $basicDetails->save();
        }
    }

    /**
     * Update step 2 (Personal Information)
     */
    private function updateStep2($request, $user, $filePaths)
    {
        $basicDetails = UserBasicDetail::where('user_id', $user->id)->first();

        $rules = [
            'personal_email' => 'nullable|email',
            'alternate_phone' => 'nullable|digits:10',
            'gender' => 'nullable|in:m,f,o',
            'dob' => 'nullable|date',
            'blood_group' => 'nullable|max:10',
            'marital_status' => 'nullable|in:single,married,widow,divorced',
            'father_name' => 'nullable|min:3|max:255',
            'mother_name' => 'nullable|min:3|max:255',
            'language' => 'nullable|array',
            'language.*' => 'nullable|string',
            'nationality' => 'nullable|string',
            'about' => 'nullable|min:5|max:255',
            'aadhaar_no' => 'nullable|digits:12',
            'pan_no' => 'nullable|size:10',
            'passport_number' => 'nullable|max:20',
        ];

        // Add unique rule for personal_email if provided
        if ($request->has('personal_email') && ! empty($request->personal_email)) {
            if ($basicDetails && $basicDetails->personal_email) {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email,'.$basicDetails->id;
            } else {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email';
            }
        }

        // Add unique rule for aadhaar if provided
        if ($request->has('aadhaar_no') && ! empty($request->aadhaar_no)) {
            if ($basicDetails && $basicDetails->aadhaar_no) {
                $rules['aadhaar_no'] = 'nullable|digits:12|unique:user_basic_details,aadhaar_no,'.$basicDetails->id;
            }
        }

        // Add unique rule for pan if provided
        if ($request->has('pan_no') && ! empty($request->pan_no)) {
            if ($basicDetails && $basicDetails->pan_no) {
                $rules['pan_no'] = 'nullable|size:10|unique:user_basic_details,pan_no,'.$basicDetails->id;
            }
        }

        $validated = $request->validate($rules);

        $basicDetails = UserBasicDetail::firstOrNew(['user_id' => $user->id]);
        $basicDetails->fill([
            'personal_email' => $validated['personal_email'] ?? null,
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'dob' => $validated['dob'] ?? null,
            'blood_group' => $validated['blood_group'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'father_name' => $validated['father_name'] ?? null,
            'mother_name' => $validated['mother_name'] ?? null,
            'nationality' => $validated['nationality'] ?? 'Indian',
            'about' => $validated['about'] ?? null,
            'aadhaar_no' => $validated['aadhaar_no'] ?? null,
            'pan_no' => $validated['pan_no'] ?? null,
            'passport_number' => $validated['passport_number'] ?? null,
            'language' => json_encode($validated['language'] ?? []),
        ]);

        foreach ($filePaths as $field => $path) {
            if ($field == 'profile_photo') {
                $basicDetails->profile_image = $path;
            } else {
                $basicDetails->$field = $path;
            }
        }

        $basicDetails->user_id = $user->id;
        $basicDetails->save();
    }

    /**
     * Update step 3 (Job Details)
     */
    private function updateStep3($request, $user)
    {
        $this->normalizeJobLocationInput($request);
        $validated = $request->validate([
            'department' => 'nullable|exists:departments,id',
            'designation' => 'nullable|exists:designations,id',
            'reporting_head' => 'nullable|array',
            'reporting_head.*' => 'distinct|exists:users,id',
            'employment_type' => 'nullable|in:full-time,part-time,contract',
            ...$this->jobLocationRules(),
            'company_branch' => 'nullable|exists:company_branches,id',
            'joining_date' => 'nullable|date',
            'leave_type_assigned' => 'nullable|array|min:1',
            'leave_type_assigned.*' => 'exists:leave_types,id',
        ]);

        $leaveAssignedJson = null;
        if (isset($validated['leave_type_assigned']) && is_array($validated['leave_type_assigned'])) {
            // Remove any empty values
            $leaveTypes = array_filter($validated['leave_type_assigned']);
            // ✅ Convert each value to integer
            $leaveTypes = array_map('intval', $leaveTypes);
            // Reindex the array
            $leaveTypes = array_values($leaveTypes);
            // Convert to JSON
            $leaveAssignedJson = json_encode($leaveTypes);
        }

        $primaryReportingHeadId = $this->syncReportingHeads($user, $validated['reporting_head'] ?? []);

        $jobDetails = UserJobDetail::firstOrNew(['user_id' => $user->id]);
        $jobDetails->fill([
            'department' => $validated['department'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'reporting_head' => $primaryReportingHeadId,
            'employment_type' => $validated['employment_type'] ?? 'full-time',
            'type' => $validated['type'] ?? 'office',
            'office_branch' => $validated['branch'] ?? null,
            'branch_id' => $validated['company_branch'] ?? null,
            'joining_date' => $validated['joining_date'] ?? null,
            'leave_assigned' => $leaveAssignedJson,
        ]);

        $jobDetails->user_id = $user->id;
        $jobDetails->save();
    }

    /**
     * Update step 4 (Location)
     */
    private function updateStep4($request, $user)
    {
        $validated = $request->validate([
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'current_address' => 'nullable|string',
            'pin_code' => 'nullable|digits:6',
        ]);

        $location = UserLocation::firstOrNew(['user_id' => $user->id]);
        $location->fill([
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'address' => $validated['current_address'] ?? null,
            'pincode' => $validated['pin_code'] ?? null,
        ]);

        $location->user_id = $user->id;
        $location->save();
    }

    /**
     * Update step 5 (Bank Details)
     */
    private function updateStep5($request, $user)
    {
        $validated = $request->validate([
            'bank_name' => 'nullable|min:3|max:255',
            'account_number' => 'nullable|max:20',
            'ifsc' => 'nullable|min:6|max:11',
            'branch_name' => 'nullable|min:3|max:255',
            'salary' => 'nullable|numeric',
            'uan_no' => 'nullable|min:3|max:20',
            'pf_no' => 'nullable|max:20',
            'esic_no' => 'nullable|min:6|max:20',
        ]);

        $bankDetails = UserBankDetail::firstOrNew(['user_id' => $user->id]);
        $bankDetails->fill([
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'ifsc' => $validated['ifsc'] ?? null,
            'branch_name' => $validated['branch_name'] ?? null,
            'uan_no' => $validated['uan_no'] ?? null,
            'esi_no' => $validated['esic_no'] ?? null,
            'pf_no' => $validated['pf_no'] ?? null,
        ]);
        $bankDetails->user_id = $user->id;
        $bankDetails->save();

        if (isset($validated['salary'])) {
            $jobDetails = UserJobDetail::firstOrNew(['user_id' => $user->id]);
            $jobDetails->salary = $validated['salary'];
            $jobDetails->user_id = $user->id;
            $jobDetails->save();
        }
    }

    /**
     * Update step 6 (Payroll Information)
     */
    private function updateStep6($request, $user)
    {
        $tenantId = app('current_tenant')->id;

        $validated = $request->validate([
            'payroll_structure_id' => ['nullable', 'integer', Rule::exists('payroll_structures', 'id')->where('tenant_id', $tenantId)],
            'annual_ctc' => 'required|numeric|min:100000',
            'salary_effective_date' => 'required|date',
            'basic_salary' => 'required|numeric|min:0',
            'hra' => 'required|numeric|min:0',
            'conveyence' => 'required|numeric|min:0',
            'medical_allowance' => 'required|numeric|min:0',
            'children_allowance' => 'nullable|numeric|min:0',
            'post_allowance' => 'nullable|numeric|min:0',
            'leave_travel_allowance' => 'nullable|numeric|min:0',
            'monthly_incentive' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'provident_fund' => 'required|numeric|min:0',
            'employer_provident_fund' => 'required|numeric|min:0',
            'esi' => 'required|numeric|min:0',
            'employer_esi' => 'required|numeric|min:0',
            'professional_tax' => 'required|numeric|min:0',
            'net_salary' => 'required|numeric|min:0',
            'gross_salary' => 'required|numeric|min:0',
        ]);

        $this->assignDynamicStructureFromWizardValues($tenantId, $user, $validated, 'increment', 'Updated during employee edit');

        UserJobDetail::where('user_id', $user->id)->update(['salary' => $validated['gross_salary']]);

        Log::info('Payroll updated for employee: '.$user->employee_id);
    }

    /**
     * Update step 7 (Documents)
     */
    private function updateStep7($request, $user, $filePaths)
    {
        $this->saveEmployeeDocuments($user, $this->documentRows($request));
    }

    /**
     * Complete update after all steps
     */
    public function completeUpdate(Request $request, $id)
    {
        try {
            $id = decrypt($id);
            $user = User::findOrFail($id);

            DB::beginTransaction();

            // Update final status if needed
            $user->updated_at = now();
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }
}
