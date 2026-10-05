<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\Payroll\PayrollStructureAssignmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = config('app.url');
            $currentYear = date('Y');

            // Payroll rebuild — Phase 9: reads payroll from
            // PayrollEmployeeStructure, not the legacy UserPayroll table.
            // Every tenant's user_payrolls rows are mirrored into
            // payroll_employee_structures by payroll:backfill-component-catalog
            // regardless of that tenant's cutover flag, so this is accurate
            // even for a tenant not yet fully cut over to dynamic generation.
            $assignmentService = app(PayrollStructureAssignmentService::class);

            // Base query for users
            $query = User::where('status', 1)
                ->with([
                    'basicDetails',
                    'bankDetails',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel',
                    'jobDetails.reportingHead',
                    'jobDetails.attendanceLocation',
                    'location.countryRel',
                    'location.stateRel',
                    'location.cityRel'
                ]);

            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    // Admin/HR: See all active users except other admins
                    $query->whereNotIn('role', ['admin']);
                    break;

                case 'manager':
                    // Manager: See team members + their own data
                    $query->where(function ($q) use ($authUser) {
                        // Users reporting to this manager (any reporting head)
                        $q->managedBy($authUser->id)
                            // OR the manager themselves
                            ->orWhere('users.id', $authUser->id);
                    });
                    break;

                case 'employee':
                    // Employee: See only their own data
                    $query->where('users.id', $authUser->id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 403);
            }

            $teamMembers = $query->get();

            // Identity numbers, bank details and salary are private: shown in full only for the
            // caller's own record or to someone with company-wide payroll access (admin / HR).
            // A manager listing their team used to receive every reportee's Aadhaar, PAN,
            // bank account and full payroll history.
            $seesPrivateOfOthers = app(\App\Services\RbacService::class)->scopeFor($authUser, 'payroll', 'view') === 'company';
            $mask = fn ($v) => ($v === null || $v === '') ? $v : '••••' . substr((string) $v, -4);

            $teamData = [];

            foreach ($teamMembers as $member) {
                $private = $seesPrivateOfOthers || $member->id == $authUser->id;

                // Handle languages
                $languageNames = [];
                if ($member->basicDetails && $member->basicDetails->language) {
                    $languageIds = is_array($member->basicDetails->language)
                        ? $member->basicDetails->language
                        : json_decode($member->basicDetails->language, true) ?? [];

                    if (!empty($languageIds)) {
                        $languageNames = Language::whereIn('id', $languageIds)
                            ->pluck('name')
                            ->toArray();
                    }
                }

                $currentPayrollData = null;
                $payrollHistory = [];
                if ($private) {
                    $currentStructure = $member->currentDynamicPayrollStructure;
                    $currentPayrollData = $currentStructure ? $assignmentService->toLegacyShapedArray($currentStructure) : null;

                    $payrollHistory = $member->dynamicPayrollStructures()
                        ->orderByDesc('effective_from')
                        ->get()
                        ->map(fn ($s) => array_merge(['id' => $s->id], $assignmentService->toLegacyShapedArray($s)));
                }

               
                $teamData[] = [
                    // Basic Information
                    'id' => $member->id,
                    'employee_id' => $member->employee_id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'contact' => $member->contact,
                    'role' => $member->role,
                    'status' => $member->status,
                    'is_current_user' => ($member->id == $authUser->id),
                    'reporting_to' => $member->id == $authUser->id ? null : ($member->jobDetails?->reportingHead?->name ?? null),

                    // Profile Image
                    'profile_image' => $member->basicDetails?->profile_image
                        ? file_url($member->basicDetails->profile_image, 'profile_photo')
                        : $baseUrl . '/profile2.jpg',

                    // Personal Information
                    'personal_details' => [
                        'father_name' => $member->basicDetails?->father_name,
                        'mother_name' => $member->basicDetails?->mother_name,
                        'dob' => $member->basicDetails?->dob,
                        'gender' => $member->basicDetails?->gender,
                        'blood_group' => $member->basicDetails?->blood_group,
                        'marital_status' => $member->basicDetails?->marital_status,
                        'nationality' => $member->basicDetails?->nationality,
                        'alternate_phone' => $member->basicDetails?->alternate_phone,
                        'personal_email' => $member->basicDetails?->personal_email,
                        'aadhaar_no' => $private ? $member->basicDetails?->aadhaar_no : $mask($member->basicDetails?->aadhaar_no),
                        'pan_no' => $private ? $member->basicDetails?->pan_no : $mask($member->basicDetails?->pan_no),
                        'languages' => $languageNames,
                    ],

                    // Bank Details (masked to the last 4 characters unless $private)
                    'bank_details' => [
                        'account_number' => $private ? $member->bankDetails?->account_number : $mask($member->bankDetails?->account_number),
                        'ifsc' => $member->bankDetails?->ifsc,
                        'bank_name' => $member->bankDetails?->bank_name,
                        'branch_name' => $member->bankDetails?->branch_name,
                        'uan_no' => $private ? $member->bankDetails?->uan_no : $mask($member->bankDetails?->uan_no),
                        'pf_no' => $private ? $member->bankDetails?->pf_no : $mask($member->bankDetails?->pf_no),
                        'esi_no' => $private ? $member->bankDetails?->esi_no : $mask($member->bankDetails?->esi_no),
                    ],

                    // Job Details
                    'job_details' => [
                        'designation' => $member->jobDetails?->designationRel?->name,
                        'designation_id' => $member->jobDetails?->designation,
                        'department' => $member->jobDetails?->departmentRel?->name,
                        'department_id' => $member->jobDetails?->department,
                        'joining_date' => $member->jobDetails?->joining_date,
                        'leaving_date' => $member->jobDetails?->leaving_date,
                        'employment_type' => $member->jobDetails?->employment_type,
                        'reporting_head' => $member->jobDetails?->reportingHead?->name,
                        'reporting_head_id' => $member->jobDetails?->reporting_head,
                        'office_branch' => $member->jobDetails?->attendanceLocation?->name ?? null,
                        'office_radius' => $member->jobDetails?->attendanceLocation?->radius ?? null,
                        'office_description' => $member->jobDetails?->attendanceLocation?->description ?? null,
                        'office_latitude' => $member->jobDetails?->attendanceLocation?->latitude ?? null,
                        'office_longitude' => $member->jobDetails?->attendanceLocation?->longitude ?? null,
                        'type' => $member->jobDetails?->type,
                    ],

                    // Location Details
                    'location' => [
                        'country' => $member->location?->countryRel?->name,
                        'state' => $member->location?->stateRel?->name,
                        'city' => $member->location?->cityRel?->name,
                        'address' => $member->location?->address,
                        'permanent_address' => $member->location?->permanent_address,
                        'pincode' => $member->location?->pincode,
                    ],

                    // Current Payroll
                    'current_payroll' => $currentPayrollData,

                    // Payroll History
                    'payroll_history' => $payrollHistory,

                    // Documents
                    'documents' => [
                        'experience_letter' => $member->basicDetails?->experience_letter ? file_url($member->basicDetails->experience_letter, 'employee_document') : null,
                        'tenth_marksheet' => $member->basicDetails?->tenth_marksheet ? file_url($member->basicDetails->tenth_marksheet, 'employee_document') : null,
                        'twelfth_marksheet' => $member->basicDetails?->twelfth_marksheet ? file_url($member->basicDetails->twelfth_marksheet, 'employee_document') : null,
                        'highest_qualification' => $member->basicDetails?->highest_qualification_certificate ? file_url($member->basicDetails->highest_qualification_certificate, 'employee_document') : null,
                    ],
                ];
            }

            // Summary statistics
            $summary = [
                'total' => $teamMembers->count(),
                'by_role' => $teamMembers->groupBy('role')->map->count(),
                'current_user' => [
                    'id' => $authUser->id,
                    'name' => $authUser->name,
                    'role' => $authUser->role
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Team data fetched successfully',
                'data' => $teamData,
                'summary' => $summary,
                'user_role' => $authUser->role
            ], 200);
        } catch (Exception $e) {
            // Was dd($e->getMessage()) — a debug dump instead of a JSON error.
            \Illuminate\Support\Facades\Log::error('AI team failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
}
