<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\User;
use App\Models\AttendanceLocation;
use App\Models\CompanyBranch;
use App\Models\PayrollStructure;
use App\Models\Country;
use App\Models\Language;
use App\Models\EmployementType;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

            // Employee 360: the extra lazy tabs this company's plan includes.
            $profile = app(EmployeeProfileController::class);
            $profileTabs = $profile->enabledTabs();
            $can = $profile->abilities(); // which action buttons to show

            return view('client.user.user-detail', compact('user', 'languageNames', 'locationNames', 'profileTabs', 'can'));
        } catch (\Exception $e) {
            Log::error('Error fetching employee details: ' . $e->getMessage());
            return redirect()->route('employee.index')
                ->with('error', 'Employee not found or error loading details.');
        }
    }

    

}
