<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Models\UserPayroll;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = env('APP_URL');
            $currentYear = date('Y');

            // Get only the authenticated user
            $member = User::where('status', 1)
                ->where('id', $authUser->id)
                ->with([
                    'basicDetails',
                    'bankDetails',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel',
                    'jobDetails.reportingHead',
                    'location.countryRel',
                    'location.stateRel',
                    'location.cityRel'
                ])
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

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

            // Get current payroll information
            $currentPayroll = UserPayroll::where('user_id', $member->id)
                ->where('is_current', 1)
                ->where('status', 1)
                ->first();

            // Get payroll history
            $payrollHistory = UserPayroll::where('user_id', $member->id)
                ->where('status', 1)
                ->orderBy('effective_from', 'desc')
                ->get()
                ->map(function ($payroll) {
                    return [
                        'id' => $payroll->id,
                        'payroll_code' => $payroll->payroll_code,
                        'effective_from' => $payroll->effective_from,
                        'effective_to' => $payroll->effective_to,
                        'is_current' => $payroll->is_current,
                        'basic_salary' => (float) $payroll->basic_salary,
                        'hra' => (float) $payroll->hra,
                        'conveyence' => (float) $payroll->conveyence,
                        'medical_allowance' => (float) $payroll->medical_allowance,
                        'special_allowance' => (float) $payroll->special_allowance,
                        'monthly_incentive' => (float) $payroll->monthly_incentive,
                        'provident_fund' => (float) $payroll->provident_fund,
                        'esi' => (float) $payroll->esi,
                        'professional_tax' => (float) $payroll->professional_tax,
                        'tds' => (float) $payroll->tds,
                        'gross_salary' => (float) $payroll->gross_salary,
                        'total_deductions' => (float) $payroll->total_deductions,
                        'net_salary' => (float) $payroll->net_salary,
                        'ctc' => (float) $payroll->ctc
                    ];
                });

            $teamData = [
                // Basic Information
                'id' => $member->id,
                'employee_id' => $member->employee_id,
                'name' => $member->name,
                'email' => $member->email,
                'contact' => $member->contact,
                'role' => $member->role,
                'status' => $member->status,
                'is_current_user' => true,
                'reporting_to' => $member->jobDetails?->reportingHead?->name ?? null,

                // Profile Image
                'profile_image' => $member->basicDetails?->profile_image
                    ? $baseUrl . $member->basicDetails->profile_image
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
                    'aadhaar_no' => $member->basicDetails?->aadhaar_no,
                    'pan_no' => $member->basicDetails?->pan_no,
                    'languages' => $languageNames,
                ],

                // Bank Details
                'bank_details' => [
                    'account_number' => $member->bankDetails?->account_number,
                    'ifsc' => $member->bankDetails?->ifsc,
                    'bank_name' => $member->bankDetails?->bank_name,
                    'branch_name' => $member->bankDetails?->branch_name,
                    'uan_no' => $member->bankDetails?->uan_no,
                    'pf_no' => $member->bankDetails?->pf_no,
                    'esi_no' => $member->bankDetails?->esi_no,
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
                    'office_branch' => $member->jobDetails?->office_branch,
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
                'current_payroll' => $currentPayroll ? [
                    'payroll_code' => $currentPayroll->payroll_code,
                    'effective_from' => $currentPayroll->effective_from,
                    'basic_salary' => (float) $currentPayroll->basic_salary,
                    'hra' => (float) $currentPayroll->hra,
                    'conveyence' => (float) $currentPayroll->conveyence,
                    'medical_allowance' => (float) $currentPayroll->medical_allowance,
                    'special_allowance' => (float) $currentPayroll->special_allowance,
                    'monthly_incentive' => (float) $currentPayroll->monthly_incentive,
                    'provident_fund' => (float) $currentPayroll->provident_fund,
                    'esi' => (float) $currentPayroll->esi,
                    'professional_tax' => (float) $currentPayroll->professional_tax,
                    'tds' => (float) $currentPayroll->tds,
                    'gross_salary' => (float) $currentPayroll->gross_salary,
                    'total_deductions' => (float) $currentPayroll->total_deductions,
                    'net_salary' => (float) $currentPayroll->net_salary,
                    'ctc' => (float) $currentPayroll->ctc,
                ] : null,

                // Payroll History
                'payroll_history' => $payrollHistory,

                // Documents
                'documents' => [
                    'experience_letter' => $member->basicDetails?->experience_letter ? $baseUrl . $member->basicDetails->experience_letter : null,
                    'tenth_marksheet' => $member->basicDetails?->tenth_marksheet ? $baseUrl . $member->basicDetails->tenth_marksheet : null,
                    'twelfth_marksheet' => $member->basicDetails?->twelfth_marksheet ? $baseUrl . $member->basicDetails->twelfth_marksheet : null,
                    'highest_qualification' => $member->basicDetails?->highest_qualification_certificate ? $baseUrl . $member->basicDetails->highest_qualification_certificate : null,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Profile data fetched successfully',
                'data' => $teamData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
                'error' => $e->getMessage() // Optional: for debugging
            ], 500);
        }
    }
}
