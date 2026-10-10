<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Models\CompanyBranch;
use App\Models\Country;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployementType;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Add Employee wizard (employee.create, save-step 1-7, complete-store, load-data).
 * Moved out of UserController unchanged (code-quality plan, Phase 4); route names
 * are the same.
 */
class EmployeeCreateController extends Controller
{
    use \App\Http\Controllers\Concerns\EmployeeFormHelpers;

    /**
     * Show the form for creating a new employee
     */
    public function create()
    {
        session()->forget('employee_id');
        $data['payrollStructures'] = PayrollStructure::where('status', 1)->with('components.component')->orderBy('name')->get();
        $data['departments'] = Department::where('status', 1)->get();
        $data['designations'] = Designation::where('status', 1)->get();
        $data['employement_types'] = EmployementType::where('status', 1)->get();
        $data['employees'] = User::where('status', 1)->where('role', '!=', 'employee')->get();
        $data['countries'] = Country::where('status', 1)->get();
        $data['languages'] = Language::where('status', 1)->get();
        $data['branches'] = AttendanceLocation::where('status', 1)->get();
        $data['companyBranches'] = CompanyBranch::where('status', 1)->get();
        $data['leave_types'] = LeaveType::where('status', 1)->get();

        return view('client.user.add-user', $data);
    }

    /**
     * Save step data
     */
    public function saveStep(Request $request)
    {
        try {
            $step = $request->input('step');
            $employeeId = $request->input('employee_id');
            DB::beginTransaction();

            // Generate employee ID if not exists
            if ($step == 1) {
                $employeeId = null;
            }

            // Handle file uploads
            $filePaths = $this->handleFileUploads($request, $employeeId);

            switch ($step) {
                case 1:
                    $employeeId = $this->saveStep1($request, $employeeId, $filePaths);
                    break;
                case 2:
                    $this->saveStep2($request, $employeeId, $filePaths);
                    break;
                case 3:
                    $this->saveStep3($request, $employeeId);
                    break;
                case 4:
                    $this->saveStep4($request, $employeeId);
                    break;
                case 5:
                    $this->saveStep5($request, $employeeId);
                    break;
                case 6:
                    $this->saveStep6($request, $employeeId);
                    break;
                case 7:
                    $this->saveStep7($request, $employeeId, $filePaths);
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Step '.$step.' saved successfully',
                'employee_id' => $employeeId,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step save error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save step 1 (Login Details)
     */
    private function saveStep1($request, $employeeId, $filePaths)
    {
        $user = User::where('employee_id', $employeeId)->first();

        // Dynamic validation - if user exists, ignore their own email
        $emailRule = 'required|email';
        if ($user) {
            $emailRule .= '|unique:users,email,'.$user->id;
        } else {
            $emailRule .= '|unique:users,email';
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => $emailRule,
            'contact' => 'required|digits:10|unique:users,contact,'.($user->id ?? 'NULL'),
            'password' => $user ? 'nullable|min:6|max:10|confirmed' : 'required|min:6|max:10|confirmed',
            'role' => 'required|in:employee,manager,admin,hr',
        ]);

        // Check if user already exists
        if ($user) {
            // Update existing user
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'contact' => $validated['contact'],
                'role' => $validated['role'],
            ];

            // Only update password if provided
            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);
            $employeeId = $user->employee_id;
        } else {
            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'employee_id' => null,
                'email' => $validated['email'],
                'contact' => $validated['contact'],
                'password' => Hash::make($validated['password']),
                'status' => 0,
                'role' => $validated['role'],
            ]);
            $employeeId = \App\Services\User\EmployeeIdService::generate($user->tenant_id, $user->id);
            $user->employee_id = $employeeId;
            $user->save();
        }

        // Save profile photo if uploaded
        if (isset($filePaths['profile_photo'])) {
            $basicDetails = UserBasicDetail::firstOrNew(['user_id' => $user->id]);
            $basicDetails->profile_image = $filePaths['profile_photo'];
            $basicDetails->user_id = $user->id;
            $basicDetails->save();
        }

        // Store in session
        session(['employee_id' => $employeeId]);

        return $employeeId;
    }

    /**
     * Save step 2 (Personal Information)
     */
    private function saveStep2($request, $employeeId, $filePaths)
    {
        $user = User::where('employee_id', $employeeId)->firstOrFail();
        $basicDetails = UserBasicDetail::where('user_id', $user->id)->first();

        // Build validation rules
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

        // Add unique rule for personal_email only if it's being updated and exists
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

        // Add any file paths from this step
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
     * Save step 3 (Job Details)
     */
    private function saveStep3($request, $employeeId)
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
            'status' => 'nullable|in:0,1',
        ]);

        $user = User::where('employee_id', $employeeId)->firstOrFail();
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

        // Update user status if provided
        if (isset($validated['status'])) {
            $user->status = $validated['status'] ?? 1;
            $user->save();
        }
    }

    /**
     * Save step 4 (Location)
     */
    private function saveStep4($request, $employeeId)
    {
        $validated = $request->validate([
            'country' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'current_address' => 'nullable|string',
            'pin_code' => 'nullable|digits:6',
        ]);

        $user = User::where('employee_id', $employeeId)->firstOrFail();

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
     * Save step 5 (Bank Details & Identity)
     */
    private function saveStep5($request, $employeeId)
    {
        $user = User::where('employee_id', $employeeId)->firstOrFail();
        $bankDetails = UserBankDetail::where('user_id', $user->id)->first();
        $basicDetails = UserBasicDetail::where('user_id', $user->id)->first();

        // Build validation rules
        $rules = [
            'bank_name' => 'nullable|min:3|max:255',
            'account_number' => 'nullable|max:20',
            'branch_name' => 'nullable|min:3|max:255',
            'salary' => 'nullable|numeric',
            'esic_no' => 'nullable|min:6|max:20',
            'branch_name' => 'nullable|min:3|max:255',
            'salary' => 'nullable|numeric',
        ];

        // IFSC validation (not unique, just format)
        if ($request->has('ifsc') && ! empty($request->ifsc)) {
            $rules['ifsc'] = 'nullable|min:6|max:11';
        }

        $validated = $request->validate($rules);

        // Save bank details
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

        // Update salary in job details
        if (isset($validated['salary'])) {
            $jobDetails = UserJobDetail::firstOrNew(['user_id' => $user->id]);
            $jobDetails->salary = $validated['salary'];
            $jobDetails->user_id = $user->id;
            $jobDetails->save();
        }
    }

    /**
     * Save step 6 (Payroll Information)
     */
    private function saveStep6($request, $employeeId)
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

        $user = User::where('employee_id', $employeeId)->firstOrFail();

        $this->assignDynamicStructureFromWizardValues($tenantId, $user, $validated, 'initial', 'Created during employee registration');

        UserJobDetail::where('user_id', $user->id)->update(['salary' => $validated['gross_salary']]);

        Log::info('Payroll saved for employee: '.$user->employee_id);
    }

    private function saveStep7($request, $employeeId, $filePaths)
    {
        $user = User::where('employee_id', $employeeId)->firstOrFail();

        $this->saveEmployeeDocuments($user, $this->documentRows($request));
    }

    /**
     * Complete store after all steps
     */
    public function completeStore(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');

            DB::beginTransaction();

            // Update final status
            $user = User::where('employee_id', $employeeId)->first();

            if (! $user) {
                throw new \Exception('User not found');
            }

            $user->status = 1;
            $user->save();

            DB::commit();

            // Clear session data
            session()->forget(['employee_id', 'step_data']);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load saved data for a specific employee
     */
    public function loadSavedData(Request $request)
    {
        try {
            $employeeId = $request->employee_id;

            $user = User::where('employee_id', $employeeId)->first();

            if (! $user) {
                return response()->json(['data' => null]);
            }

            $structure = $user->currentDynamicPayrollStructure;
            $payroll = $structure ? app(PayrollStructureAssignmentService::class)->toLegacyShapedArray($structure) : null;

            $reportingHeads = $user->reportingHeads()->orderByDesc('is_primary')->get();
            $jobData = $user->jobDetails;
            if ($jobData) {
                $jobData->reporting_head_ids = $reportingHeads->pluck('id')->all();
                $jobData->reporting_head_primary_id = optional($reportingHeads->first())->id;
            }

            $documents = $user->documents()->latest()->get()->map(fn ($document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_type_other' => $document->document_type_other,
                'document_name' => $document->document_name,
                'file_path' => file_url($document->file_path, 'employee_document'),
                'filename' => $document->original_filename ?: basename($document->file_path),
            ])->values();

            $data = [
                'user' => $user,
                'basic' => $user->basicDetails,
                // Loadable URL for the stored photo (signed when on cloud storage).
                'profile_image_url' => file_url($user->basicDetails?->profile_image, 'profile_photo'),
                'job' => $jobData,
                'bank' => $user->bankDetails,
                'location' => $user->location,
                'payroll' => $payroll,
                'documents' => $documents,
                'last_step' => session('last_step', 1),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error loading data',
            ], 500);
        }
    }
}
