<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use App\Models\UserBankDetail;
use App\Models\UserBasicDetail;
use App\Models\UserJobDetail;
use App\Models\UserLocation;
use App\Models\EmployeeDocument;
use App\Models\AttendanceLocation;
use App\Models\CompanyBranch;
use App\Models\PayrollStructure;
use App\Models\Country;
use App\Models\Language;
use App\Models\EmployementType;
use App\Models\LeaveType;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of employees
     */
    public function index(Request $request)
    {
        try {
            // Get filter parameters
            $search = $request->input('search');
            $department = $request->input('department');
            $designation = $request->input('designation');
            $role = $request->input('role');
            $status = $request->input('status');
            $user_idnew = $request->input('user_id');

            // Build the query
            $query = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
                ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
                ->leftJoin('user_bank_details', 'users.id', '=', 'user_bank_details.user_id')
                ->leftJoin('user_locations', 'users.id', '=', 'user_locations.user_id')
                ->select([
                    'users.id',
                    'users.employee_id',
                    'users.name',
                    'users.email',
                    'users.contact',
                    'users.status',
                    'users.role',
                    'users.created_at',
                    'user_basic_details.profile_image',
                    'user_basic_details.personal_email',
                    'user_basic_details.alternate_phone',
                    'user_basic_details.gender',
                    'user_basic_details.dob',
                    'user_basic_details.about',
                    'user_basic_details.father_name',
                    'user_basic_details.mother_name',
                    'user_basic_details.blood_group',
                    'user_basic_details.aadhaar_no',
                    'user_basic_details.pan_no',
                    'user_basic_details.passport_number',
                    'user_basic_details.marital_status',
                    'user_basic_details.nationality',
                    'user_basic_details.experience_letter',
                    'user_basic_details.tenth_marksheet',
                    'user_basic_details.twelfth_marksheet',
                    'user_basic_details.highest_qualification_certificate',
                    'user_basic_details.language',
                    'designations.name as designation',
                    'departments.name as department',
                    'departments.id as department_id',
                    'user_job_details.joining_date',
                    'user_job_details.attendance_type',
                    'user_job_details.employment_type',
                    'user_job_details.salary',
                    'user_job_details.type',
                    'user_job_details.office_branch',
                    'user_job_details.face_register',
                    'user_job_details.location_tracking_enabled',
                    'user_bank_details.bank_name',
                    'user_bank_details.account_number',
                    'user_bank_details.ifsc',
                    'user_bank_details.branch_name',
                    'user_locations.address as current_address',
                    'user_locations.country',
                    'user_locations.state',
                    'user_locations.city',
                    'user_locations.pincode as pin_code',
                    'user_locations.permanent_address',
                ])
                ->where('users.role', '!=', 'admin');

            // Apply search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                        ->orWhere('users.email', 'LIKE', "%{$search}%")
                        ->orWhere('users.employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('users.contact', 'LIKE', "%{$search}%")
                        ->orWhere('designations.name', 'LIKE', "%{$search}%")
                        ->orWhere('departments.name', 'LIKE', "%{$search}%");
                });
            }

            // Apply department filter
            if (!empty($department)) {
                $query->where('user_job_details.department', $department);
            }
            if (!empty($designation)) {
                $query->where('user_job_details.designation', $designation);
            }

            // Apply role filter
            if (!empty($role)) {
                $query->where('users.role', $role);
            }

            // Apply status filter
            if ($status !== null && $status !== '') {
                $query->where('users.status', $status);
            }
            if ($user_idnew !== null && $user_idnew !== '') {
                $query->where('users.id', $user_idnew);
            }

            // Order by name
            $query->orderBy('users.name', 'ASC');

            // Get paginated results
            $users = $query->paginate(15)->withQueryString();

            // Decode JSON language for each user
            $users->getCollection()->transform(function ($user) {
                $user->language = $user->language ? json_decode($user->language, true) : [];
                return $user;
            });

            // Get departments for filter dropdown
            $departments = Department::where('status', 1)
                ->orderBy('name', 'ASC')
                ->get(['id', 'name']);
            $designations = Designation::where('status', 1)
                ->orderBy('name', 'ASC')
                ->get(['id', 'name']);

            $totalEmployees = User::where('role', '!=', 'admin')
                ->count();

            $activeEmployees = User::where('role', '!=', 'admin')
                ->where('status', 1)
                ->count();

            $inactiveEmployees = User::where('role', '!=', 'admin')
                ->where('status', 0)
                ->count();

            $fieldEmployees = User::where('role', '!=', 'admin')
                ->whereHas('jobDetails', fn ($q) => $q->where('type', 'field'))
                ->count();

            $officeEmployees = User::where('role', '!=', 'admin')
                ->whereHas('jobDetails', fn ($q) => $q->where('type', '!=', 'field')->orWhereNull('type'))
                ->count();

            $faceRegisteredEmployees = User::where('role', '!=', 'admin')
                ->whereHas('jobDetails', fn ($q) => $q->where('face_register', 1))
                ->count();

            $hrCount = User::where('role', 'hr')->count();
            $managerCount = User::where('role', 'manager')->count();
            $employeeCount = User::where('role', 'employee')->count();

            $allEmployees = User::where('role', '!=', 'admin')
                ->select('id', 'name', 'email', 'employee_id')
                ->orderBy('name')
                ->get();

            $selectedEmployeeData = null;
            if ($request->has('user_id') && !empty($request->user_id)) {
                $selectedEmployeeData = User::where('id', $request->user_id)
                    ->select('id', 'name', 'email', 'employee_id', 'status')
                    ->first();
            }

            // Field GPS tracking add-on (only shown when the tenant has it).
            $ftTenant = app()->bound('current_tenant') ? app('current_tenant') : null;
            $fieldTrackingEnabled = (bool) optional($ftTenant)->field_tracking_enabled;
            $fieldTrackingSeats = (int) optional($ftTenant)->field_tracking_seats;
            $fieldTrackingSeatsUsed = $fieldTrackingEnabled
                ? app(\App\Services\FieldTracking\FieldTrackingService::class)->seatsUsed((int) auth()->user()->tenant_id)
                : 0;

            // Biometric terminals to offer in the "Push to device" bulk action
            // (only shown when the tenant has the add-on and has ≥1 active device).
            $tenantId = (int) auth()->user()->tenant_id;
            $biometricEnabled = app(\App\Services\FeatureService::class)->enabledForCurrentTenant('attendance_biometric');
            $pushDevices = $biometricEnabled
                ? \App\Models\BiometricDevice::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();

            // Dropdown data for the Add/Edit Employee drawer (embedded on this
            // page so the wizard never has to navigate away from the list).
            $reportingHeads = User::where('status', 1)->where('role', '!=', 'employee')->get();
            $employementTypes = EmployementType::where('status', 1)->get();
            $languages = Language::where('status', 1)->get();
            $branches = AttendanceLocation::where('status', 1)->get();
            $companyBranches = CompanyBranch::where('status', 1)->get();
            $leave_types = LeaveType::where('status', 1)->get();
            $payrollStructures = PayrollStructure::where('status', 1)->with('components.component')->orderBy('name')->get();
            $countries = Country::where('status', 1)->get();

            return view('client.user.view-user', compact('designations', 'users', 'totalEmployees', 'activeEmployees', 'inactiveEmployees', 'fieldEmployees', 'officeEmployees', 'faceRegisteredEmployees', 'hrCount', 'managerCount', 'employeeCount', 'departments', 'allEmployees', 'selectedEmployeeData', 'fieldTrackingEnabled', 'fieldTrackingSeats', 'fieldTrackingSeatsUsed', 'pushDevices', 'reportingHeads', 'employementTypes', 'languages', 'branches', 'companyBranches', 'leave_types', 'payrollStructures', 'countries'));
        } catch (\Exception $e) {
            Log::error('Error fetching employees: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Error loading employees. Please try again.');
        }
    }

    /**
     * Export employees to CSV
     */
    public function exportToCSV(Request $request)
    {
        try {
            $search = $request->input('search');
            $department = $request->input('department');
            $role = $request->input('role');
            $status = $request->input('status');

            // Build query similar to index but get all records
            $query = User::leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
                ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
                ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
                ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
                ->select([
                    'users.employee_id',
                    'users.name',
                    'users.email',
                    'users.contact',
                    'users.role',
                    'users.status',
                    'designations.name as designation',
                    'departments.name as department',
                    'user_job_details.joining_date',
                    'user_job_details.employment_type',
                    'user_basic_details.personal_email',
                    'user_basic_details.alternate_phone',
                    'user_basic_details.gender',
                    'user_basic_details.dob',
                    'user_basic_details.father_name',
                    'user_basic_details.mother_name',
                    'user_basic_details.blood_group',
                    'user_basic_details.marital_status',
                ])
                ->where('users.role', '!=', 'admin');

            // Apply filters
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                        ->orWhere('users.email', 'LIKE', "%{$search}%")
                        ->orWhere('users.employee_id', 'LIKE', "%{$search}%");
                });
            }

            if (!empty($department)) {
                $query->where('user_job_details.department', $department);
            }

            if (!empty($role)) {
                $query->where('users.role', $role);
            }

            if ($status !== null && $status !== '') {
                $query->where('users.status', $status);
            }

            $employees = $query->orderBy('users.name', 'ASC')->get();

            // Generate CSV
            $filename = 'employees_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'w+');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Add headers
            fputcsv($handle, [
                'Employee ID',
                'Name',
                'Email',
                'Phone',
                'Role',
                'Status',
                'Designation',
                'Department',
                'Joining Date',
                'Employment Type',
                'Personal Email',
                'Alternate Phone',
                'Gender',
                'Date of Birth',
                'Father\'s Name',
                'Mother\'s Name',
                'Blood Group',
                'Marital Status'
            ]);

            // Add data
            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->employee_id,
                    $employee->name,
                    $employee->email,
                    $employee->contact,
                    ucfirst($employee->role),
                    $employee->status == 1 ? 'Active' : 'Inactive',
                    $employee->designation ?? 'N/A',
                    $employee->department ?? 'N/A',
                    $employee->joining_date ?? 'N/A',
                    $employee->employment_type ?? 'N/A',
                    $employee->personal_email ?? 'N/A',
                    $employee->alternate_phone ?? 'N/A',
                    $employee->gender == 'm' ? 'Male' : ($employee->gender == 'f' ? 'Female' : 'Other'),
                    $employee->dob ?? 'N/A',
                    $employee->father_name ?? 'N/A',
                    $employee->mother_name ?? 'N/A',
                    $employee->blood_group ?? 'N/A',
                    ucfirst($employee->marital_status ?? 'N/A')
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=utf-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Error exporting data. Please try again.');
        }
    }

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
                'message' => 'Step ' . $step . ' saved successfully',
                'employee_id' => $employeeId
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step save error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
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

            if (!$user) {
                throw new \Exception('User not found');
            }

            $user->status = 1;
            $user->save();

            DB::commit();

            // Clear session data
            session()->forget(['employee_id', 'step_data']);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete store error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle file uploads. Documents (experience letter, marksheets, etc.)
     * are handled separately by saveEmployeeDocuments() since an employee
     * can now have any number of them — see docs/modules.md.
     */
    private function handleFileUploads($request, $employeeId)
    {
        $filePaths = [];

        if ($request->hasFile('profile_photo')) {
            // Replace: the new photo is stored first, the old one removed after commit.
            $oldPhoto = $employeeId
                ? UserBasicDetail::whereHas('user', fn ($q) => $q->where('employee_id', $employeeId))->value('profile_image')
                : null;

            $filePaths['profile_photo'] = file_storage()
                ->replace($oldPhoto, $request->file('profile_photo'), 'profile_photo')
                ->path;
        }

        return $filePaths;
    }

    /**
     * Persist the full multiselect reporting-head set for an employee.
     * The first id is treated as primary; returns the primary id (or null)
     * so callers can keep user_job_details.reporting_head in sync for the
     * ~100 existing single-head read call sites. Rejects self-reporting.
     */
    private function syncReportingHeads(User $user, array $reportingHeadIds): ?int
    {
        $reportingHeadIds = array_values(array_unique(array_filter(
            $reportingHeadIds,
            fn ($id) => (int) $id !== (int) $user->id
        )));

        // sync() writes pivot rows via a raw query, bypassing UserReportingHead's
        // TenantTrait::creating() hook — tenant_id must be set explicitly here.
        $syncData = [];
        foreach ($reportingHeadIds as $index => $headId) {
            $syncData[(int) $headId] = ['is_primary' => $index === 0, 'tenant_id' => $user->tenant_id];
        }

        $user->reportingHeads()->sync($syncData);

        return $reportingHeadIds[0] ?? null;
    }

    /**
     * Persist the employee's dynamic document list. $rows is the validated
     * `documents` array (each row: optional id, document_type,
     * document_type_other, document_name, optional file). Mirrors the
     * wizard's existing "this step's payload is the full authoritative
     * state" pattern — any previously-uploaded document not represented by
     * a row in $rows is removed.
     */
    private function saveEmployeeDocuments(User $user, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $row) {
            $file = $row['file'] ?? null;
            $existingId = $row['id'] ?? null;

            if ($existingId) {
                $document = EmployeeDocument::where('user_id', $user->id)->find($existingId);
                if (!$document) {
                    continue;
                }
            } elseif ($file) {
                $document = new EmployeeDocument(['user_id' => $user->id]);
            } else {
                // New row with no file attached — nothing to store.
                continue;
            }

            $document->document_type = $row['document_type'];
            $document->document_type_other = $row['document_type'] === EmployeeDocument::TYPE_OTHER
                ? ($row['document_type_other'] ?? null)
                : null;
            $document->document_name = $row['document_name'] ?? null;

            if ($file) {
                $stored = file_storage()->replace($document->file_path, $file, 'employee_document', [
                    'tenant' => $user->tenant_id,
                    'user' => $user->id,
                ]);
                $document->file_path = $stored->path;
                $document->original_filename = $stored->originalName;
                $document->mime_type = $stored->mimeType;
                $document->file_size = $stored->size;
            }

            $document->tenant_id = $document->tenant_id ?? $user->tenant_id;
            $document->uploaded_by = auth()->id();
            $document->user_id = $user->id;
            $document->save();

            $keptIds[] = $document->id;
        }

        EmployeeDocument::where('user_id', $user->id)
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(function (EmployeeDocument $document) {
                file_storage()->deleteAfterCommit($document->file_path, 'employee_document');
                $document->delete();
            });
    }

    /**
     * Shared validation rules for the dynamic documents array, used by
     * every entry point that saves documents (wizard step 7, full update).
     */
    private function documentRows(Request $request): array
    {
        $validTypes = implode(',', array_keys(EmployeeDocument::$documentTypes));

        $validated = $request->validate([
            'documents' => 'nullable|array',
            'documents.*.id' => 'nullable|integer',
            'documents.*.document_type' => 'required_with:documents|string|in:' . $validTypes,
            'documents.*.document_type_other' => 'nullable|required_if:documents.*.document_type,other|string|max:100',
            'documents.*.document_name' => 'nullable|string|max:150',
            'documents.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        return $validated['documents'] ?? [];
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
            $emailRule .= '|unique:users,email,' . $user->id;
        } else {
            $emailRule .= '|unique:users,email';
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => $emailRule,
            'contact' => 'required|digits:10|unique:users,contact,' . ($user->id ?? 'NULL'),
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
            if (!empty($validated['password'])) {
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
        if ($request->has('personal_email') && !empty($request->personal_email)) {
            if ($basicDetails && $basicDetails->personal_email) {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email,' . $basicDetails->id;
            } else {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email';
            }
        }

        // Add unique rule for aadhaar if provided
        if ($request->has('aadhaar_no') && !empty($request->aadhaar_no)) {
            if ($basicDetails && $basicDetails->aadhaar_no) {
                $rules['aadhaar_no'] = 'nullable|digits:12|unique:user_basic_details,aadhaar_no,' . $basicDetails->id;
            }
        }

        // Add unique rule for pan if provided
        if ($request->has('pan_no') && !empty($request->pan_no)) {
            if ($basicDetails && $basicDetails->pan_no) {
                $rules['pan_no'] = 'nullable|size:10|unique:user_basic_details,pan_no,' . $basicDetails->id;
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
        $validated = $request->validate([
            'department' => 'nullable|exists:departments,id',
            'designation' => 'nullable|exists:designations,id',
            'reporting_head' => 'nullable|array',
            'reporting_head.*' => 'distinct|exists:users,id',
            'employment_type' => 'nullable|in:full-time,part-time,contract',
            'type' => 'required|in:office,field',
            'branch' => 'required',
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
            'type' => $validated['type'],
            'office_branch' => $validated['branch'],
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
        if ($request->has('ifsc') && !empty($request->ifsc)) {
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

        Log::info('Payroll saved for employee: ' . $user->employee_id);
    }

    private function saveStep7($request, $employeeId, $filePaths)
    {
        $user = User::where('employee_id', $employeeId)->firstOrFail();

        $this->saveEmployeeDocuments($user, $this->documentRows($request));
    }

    public function show($id)
    {
        try {
            $id = decrypt($id);

            $user = User::with([
                'basicDetails',
                'jobDetails',
                'bankDetails',
                'currentPayroll',
                'location.countryRel',
                'location.stateRel',
                'location.cityRel',
                'reportingHeads',
                'documents' => fn ($query) => $query->latest(),
            ])
                ->where('id', $id)
                ->where('role', '!=', 'admin')
                ->firstOrFail();

            if ($user->jobDetails) {
                if ($user->jobDetails->department) {
                    $department = Department::find($user->jobDetails->department);
                    $user->jobDetails->department_name = $department ? $department->name : null;
                }

                if ($user->jobDetails->designation) {
                    $designation = Designation::find($user->jobDetails->designation);
                    $user->jobDetails->designation_name = $designation ? $designation->name : null;
                }

                // Multi reporting-head support: primary first, then the rest.
                $user->jobDetails->reporting_heads = $user->reportingHeads
                    ->sortByDesc(fn ($head) => (bool) $head->pivot->is_primary)
                    ->map(fn ($head) => [
                        'id' => $head->id,
                        'name' => $head->name,
                        'is_primary' => (bool) $head->pivot->is_primary,
                    ])->values()->all();
            }

            $languageNames = [];
            if ($user->basicDetails && $user->basicDetails->language) {
                $languageIds = json_decode($user->basicDetails->language, true);
                if (is_array($languageIds) && !empty($languageIds)) {
                    $languages = Language::whereIn('id', $languageIds)->get();
                    $languageNames = $languages->pluck('name')->toArray();
                }
            }

            $locationNames = [];
            if ($user->location) {
                $locationNames = [
                    'country' => $user->location->countryDetail->name ?? $user->location->country,
                    'state' => $user->location->stateDetail->name ?? $user->location->state,
                    'city' => $user->location->cityDetail->name ?? $user->location->city,
                ];
            }

            $documents = $user->documents->map(fn ($document) => [
                'id' => $document->id,
                'label' => $document->document_type_label,
                'name' => $document->document_name,
                'path' => $document->file_path,
                'filename' => $document->original_filename ?: basename($document->file_path),
                'uploaded_at' => optional($document->created_at)->format('d M Y'),
            ])->values()->all();
            $user->documents = $documents;

            return view('client.user.user-detail', compact('user', 'languageNames', 'locationNames'));
        } catch (\Exception $e) {
            Log::error('Error fetching employee details: ' . $e->getMessage());
            return redirect()->route('employee')
                ->with('error', 'Employee not found or error loading details.');
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

            if (!$user) {
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
                'last_step' => session('last_step', 1)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading data'
            ], 500);
        }
    }

    public function toggleStatus(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:users,id',
                'status' => 'required|in:0,1'
            ]);

            $user = User::findOrFail($request->id);
            $newStatus = $user->status == 1 ? 0 : 1;

            $user->status = $newStatus;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'new_status' => $newStatus
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Status toggle error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating status. Please try again.'
            ], 500);
        }
    }

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
            Log::error('Error fetching employee for edit: ' . $e->getMessage());
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

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
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
                'personal_email' => 'nullable|email|unique:user_basic_details,personal_email,' . ($user->basicDetails->id ?? 'NULL'),
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
                'type' => 'required|in:office,field',
                'branch' => 'required',
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
                'type' => $validated['type'] ?? null,
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
            Log::error('Employee update error: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Error updating employee: ' . $e->getMessage())
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
           ;

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
                'message' => 'Step ' . $step . ' updated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Step update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
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
            'email' => 'required|email|unique:users,email,' . $user->id,
            'contact' => 'required|digits:10|unique:users,contact,' . $user->id,
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
        if ($request->has('personal_email') && !empty($request->personal_email)) {
            if ($basicDetails && $basicDetails->personal_email) {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email,' . $basicDetails->id;
            } else {
                $rules['personal_email'] .= '|unique:user_basic_details,personal_email';
            }
        }

        // Add unique rule for aadhaar if provided
        if ($request->has('aadhaar_no') && !empty($request->aadhaar_no)) {
            if ($basicDetails && $basicDetails->aadhaar_no) {
                $rules['aadhaar_no'] = 'nullable|digits:12|unique:user_basic_details,aadhaar_no,' . $basicDetails->id;
            }
        }

        // Add unique rule for pan if provided
        if ($request->has('pan_no') && !empty($request->pan_no)) {
            if ($basicDetails && $basicDetails->pan_no) {
                $rules['pan_no'] = 'nullable|size:10|unique:user_basic_details,pan_no,' . $basicDetails->id;
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
        $validated = $request->validate([
            'department' => 'nullable|exists:departments,id',
            'designation' => 'nullable|exists:designations,id',
            'reporting_head' => 'nullable|array',
            'reporting_head.*' => 'distinct|exists:users,id',
            'employment_type' => 'nullable|in:full-time,part-time,contract',
            'type' => 'required|in:office,field',
            'branch' => 'required',
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
            'type' => $validated['type'],
            'office_branch' => $validated['branch'],
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

        Log::info('Payroll updated for employee: ' . $user->employee_id);
    }

    /**
     * Payroll rebuild — Phase 9. Once a tenant is on the dynamic engine, the
     * wizard/profile payroll step must stop writing into the legacy
     * user_payrolls table — that write was the one remaining bypass of the
     * per-tenant cutover flag, since it happened outside
     * UserPayrollController entirely. Creates/revises a real
     * PayrollEmployeeStructure instead, using the same flat wizard fields
     * mapped onto the tenant's own component catalog.
     *
     * @param string $defaultRevisionType used only when the employee doesn't
     *   already have a current dynamic structure at a different date than
     *   the one submitted here — otherwise 'initial' (no prior structure) or
     *   an in-place update (identical effective date resubmitted) takes over.
     */
    private function assignDynamicStructureFromWizardValues(int $tenantId, User $user, array $validated, string $defaultRevisionType, string $reason): void
    {
        $service = app(PayrollStructureAssignmentService::class);
        $components = $service->componentsFromFlatValues($tenantId, $validated);
        $effectiveFrom = $validated['salary_effective_date'] ?? now()->toDateString();
        $ctc = (float) $validated['annual_ctc'];

        $existingForDate = $service->findForExactDate($tenantId, $user->id, $effectiveFrom);

        if ($existingForDate) {
            // Same date resubmitted — update the snapshot in place rather
            // than colliding with the (tenant_id, user_id, effective_from)
            // unique constraint or creating a duplicate revision.
            $service->updateComponentsInPlace($existingForDate, $components, $ctc);

            Log::info('Dynamic payroll structure updated in place for employee: ' . $user->employee_id);

            return;
        }

        $hasCurrentStructure = \App\Models\PayrollEmployeeStructure::forUser($user->id)->current()->exists();

        $structure = $service->assign($tenantId, $components, [
            'user_id' => $user->id,
            'ctc' => $ctc,
            'effective_from' => $effectiveFrom,
            'revision_type' => $hasCurrentStructure ? $defaultRevisionType : 'initial',
            'revision_reason' => $reason,
            'created_by' => auth()->id(),
            'source' => 'manual',
            'payroll_structure_id' => $validated['payroll_structure_id'] ?? null,
        ]);

        Log::info('Dynamic payroll structure ' . $structure->status . ' for employee: ' . $user->employee_id);
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
                'message' => 'Employee updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle face register status
     */
    public function toggleFaceRegister(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            $user = UserJobDetail::where('user_id', $request->id)->first();
            $newStatus = $user->face_register == 1 ? 0 : 1;

            // Update face_register in user_job_details
            UserJobDetail::where('user_id', $request->id)
                ->update(['face_register' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Face register status updated successfully',
                'new_status' => $newStatus
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating face register status. Please try again.'
            ], 500);
        }
    }
    
    public function updateAttendanceType(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:users,id',
                'attendance_type' => 'required|in:manual_attendance,face_verification'
            ]);

            // Re-check server-side: the dropdown already hides a method the
            // plan doesn't include, but the endpoint must not trust the client.
            $features = app(\App\Services\FeatureService::class);
            $requiredFeature = $request->attendance_type === 'face_verification' ? 'attendance_face' : 'attendance';
            if (! $features->enabledForCurrentTenant($requiredFeature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'That attendance method is not included in your current plan.'
                ], 403);
            }

            $user = UserJobDetail::where('user_id', $request->id)->first();
            $user->attendance_type = $request->attendance_type;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Attendance type updated successfully to ' . str_replace('_', ' ', $request->attendance_type)
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance type update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Turn continuous GPS tracking on/off for one employee. Hard-capped at the
     * tenant's purchased field-tracking seats. Mirrors toggleFaceRegister().
     */
    public function toggleLocationTracking(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($request->id);
            $svc = app(\App\Services\FieldTracking\FieldTrackingService::class);
            $jd = UserJobDetail::where('user_id', $user->id)->first();
            $turnOn = ! ($jd && $jd->location_tracking_enabled);

            if ($turnOn) {
                $gate = $svc->canEnable((int) $user->tenant_id);
                if (! $gate['ok']) {
                    return response()->json(['success' => false, 'message' => $gate['reason']], 200);
                }
                $res = $svc->assign($user);
            } else {
                $res = $svc->remove($user);
            }

            return response()->json([
                'success' => true,
                'message' => $turnOn ? 'Field tracking enabled' : 'Field tracking disabled',
                'new_status' => $turnOn ? 1 : 0,
                'seats_used' => $res['seats_used'],
                'seats_purchased' => $res['seats_purchased'],
            ]);
        } catch (\Exception $e) {
            Log::error('toggleLocationTracking failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating field tracking. Please try again.',
            ], 500);
        }
    }

    /**
     * Enable/disable field tracking for many employees. When enabling under a
     * hard seat cap, fills the remaining seats and reports the rest as skipped.
     */
    public function bulkLocationTracking(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'enabled' => 'required|boolean',
        ]);

        try {
            $tenantId = (int) auth()->user()->tenant_id;
            $svc = app(\App\Services\FieldTracking\FieldTrackingService::class);

            $users = User::where('tenant_id', $tenantId)
                ->whereIn('id', $data['user_ids'])
                ->orderBy('id')
                ->get();

            $enabling = (bool) $data['enabled'];
            $updated = 0;
            $skipped = 0;

            DB::transaction(function () use ($users, $enabling, $svc, $tenantId, &$updated, &$skipped) {
                $tenant = \App\Models\Tenant::find($tenantId);
                $remaining = $enabling
                    ? max(0, $svc->seatsPurchased($tenant) - $svc->seatsUsed($tenantId))
                    : PHP_INT_MAX;

                foreach ($users as $user) {
                    $jd = UserJobDetail::where('user_id', $user->id)->first();
                    $isOn = (bool) ($jd && $jd->location_tracking_enabled);

                    if ($enabling) {
                        if ($isOn) {
                            continue;
                        }
                        if ($remaining <= 0) {
                            $skipped++;
                            continue;
                        }
                        $svc->assign($user);
                        $remaining--;
                        $updated++;
                    } else {
                        if (! $isOn) {
                            continue;
                        }
                        $svc->remove($user);
                        $updated++;
                    }
                }
            });

            return response()->json([
                'status' => true,
                'message' => "{$updated} employee(s) updated" . ($skipped ? ", {$skipped} skipped (no seats)" : ''),
                'data' => [
                    'updated' => $updated,
                    'skipped_no_seats' => $skipped,
                    'seats_used' => $svc->seatsUsed($tenantId),
                    'seats_purchased' => $svc->seatsPurchased(\App\Models\Tenant::find($tenantId)),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('bulkLocationTracking failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error updating field tracking. Please try again.',
            ], 500);
        }
    }

    /**
     * Explicitly queue selected employees for push to a chosen biometric
     * device, regardless of that device's auto_provision setting — bypasses
     * waiting for the background roster sync. The bridge still only applies
     * it on its next poll cycle (no on-demand device write exists).
     */
    public function bulkPushToDevice(Request $request, \App\Services\Biometric\BiometricRosterService $roster)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'device_id' => 'required|integer',
        ]);

        try {
            $tenantId = (int) auth()->user()->tenant_id;

            $device = \App\Models\BiometricDevice::where('id', $data['device_id'])
                ->where('tenant_id', $tenantId)
                ->first();
            if (! $device) {
                return response()->json(['status' => false, 'message' => 'Device not found.']);
            }

            $users = User::where('tenant_id', $tenantId)->whereIn('id', $data['user_ids'])->get();
            $result = DB::transaction(fn () => $roster->pushToDevice($device, $users));

            return response()->json([
                'status' => true,
                'message' => "{$result['queued']} employee(s) queued for push to {$device->name} — applied within ~1 min once the bridge polls."
                    . ($result['skipped'] ? " {$result['skipped']} skipped." : ''),
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('bulkPushToDevice failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error pushing employees to device. Please try again.',
            ], 500);
        }
    }
}
