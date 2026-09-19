<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\OnboardingAssignment;
use App\Models\User;
use App\Models\UserBasicDetail;
use App\Models\UserJobDetail;
use App\Models\UserLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The missing hire() implementation. Only callable once the onboarding
 * checklist is fully complete (matches the requested flow order:
 * Onboarding -> Create Employee, not the other way around).
 *
 * Builds a real employee record from Candidate (personal data) + JobOffer
 * (job/comp data), reusing the same tables/columns as the Add Employee
 * drawer's step wizard (App\Http\Controllers\User\UserController::saveStep1-5)
 * so both creation paths stay compatible — that wizard's logic is private,
 * multi-step, and Request-bound, so it isn't directly callable here; this
 * service writes to the same tables/columns instead of invoking it.
 *
 * Fields the ATS never collects (statutory numbers, bank details, office
 * type/branch) are intentionally left for HR to fill in afterwards via the
 * existing Edit Employee drawer, rather than invented here.
 */
class EmployeeProvisioningService
{
    /**
     * Maps job_offers.employment_type (underscored, 5 values including the
     * newly-added 'temporary') to user_job_details.employment_type
     * (hyphenated, only 3 values) - a real mismatch between the two tables.
     */
    protected const EMPLOYMENT_TYPE_MAP = [
        'full_time' => 'full-time',
        'part_time' => 'part-time',
        'contract' => 'contract',
        'internship' => 'contract',
        'temporary' => 'contract',
    ];

    protected const GENDER_MAP = [
        Candidate::GENDER_MALE => 'm',
        Candidate::GENDER_FEMALE => 'f',
        Candidate::GENDER_OTHER => 'o',
    ];

    /**
     * @param  OnboardingAssignment  $assignment
     * @param  array{type:string, branch:string, company_branch?:int|null, role?:string, leave_type_assigned?:array}  $extra
     *         The handful of fields with no ATS-side source at all.
     */
    public function hire(OnboardingAssignment $assignment, array $extra): User
    {
        $assignment->loadMissing(['candidate', 'jobOffer.application', 'jobOffer.designation', 'jobOffer.department']);

        if ($assignment->onboarding_status !== OnboardingAssignment::STATUS_COMPLETED) {
            throw new \RuntimeException('Onboarding must be completed before creating the employee record.');
        }

        if ($assignment->user_id) {
            throw new \RuntimeException('An employee record has already been created for this candidate.');
        }

        $candidate = $assignment->candidate;
        $offer = $assignment->jobOffer;
        $application = $offer->application;

        if (User::where('email', $candidate->email)->exists()) {
            throw new \RuntimeException("A user with email {$candidate->email} already exists.");
        }

        $user = DB::transaction(function () use ($assignment, $candidate, $offer, $application, $extra) {
            $tempPassword = Str::random(14);

            $user = User::create([
                'name' => trim($candidate->first_name . ' ' . $candidate->last_name),
                'email' => $candidate->email,
                'contact' => $candidate->phone,
                'password' => Hash::make($tempPassword),
                'status' => 1,
                'role' => $extra['role'] ?? 'employee',
            ]);
            $user->employee_id = 'SH' . str_pad($user->id, 6, '0', STR_PAD_LEFT);
            $user->save();

            UserBasicDetail::create([
                'user_id' => $user->id,
                'alternate_phone' => $candidate->alternate_phone,
                'gender' => self::GENDER_MAP[$candidate->gender] ?? null,
                'dob' => $candidate->date_of_birth,
                'nationality' => 'Indian',
                'profile_image' => $candidate->profile_image,
            ]);

            $primaryReportingHeadId = null;
            if ($offer->reporting_head) {
                $user->reportingHeads()->sync([
                    (int) $offer->reporting_head => ['is_primary' => true, 'tenant_id' => $user->tenant_id],
                ]);
                $primaryReportingHeadId = $offer->reporting_head;
            }

            UserJobDetail::create([
                'user_id' => $user->id,
                'department' => $offer->department_id,
                'designation' => $offer->designation_id,
                'reporting_head' => $primaryReportingHeadId,
                'employment_type' => self::EMPLOYMENT_TYPE_MAP[$offer->employment_type] ?? null,
                'type' => $extra['type'],
                'office_branch' => $extra['branch'],
                'branch_id' => $extra['company_branch'] ?? null,
                'joining_date' => $offer->joining_date,
                'leave_assigned' => !empty($extra['leave_type_assigned']) ? json_encode(array_values(array_map('intval', $extra['leave_type_assigned']))) : null,
            ]);

            if ($candidate->current_location || $candidate->preferred_location) {
                UserLocation::create([
                    'user_id' => $user->id,
                    'address' => $candidate->current_location,
                    'permanent_address' => $candidate->preferred_location ?: $candidate->current_location,
                ]);
            }

            $assignment->update([
                'user_id' => $user->id,
                'employee_id_generated' => $user->employee_id,
            ]);

            $application->moveToStage(
                JobApplication::STAGE_HIRED,
                "Employee record created: {$user->employee_id}"
            );

            // Temporary credential: the new hire completes setup via the
            // existing "Forgot Password" self-service flow — no separate
            // credentials-by-email path exists in this app to hook into.
            session()->flash('temp_password_notice', "Employee {$user->name} ({$user->employee_id}) created. Ask them to use 'Forgot Password' with {$user->email} to set their own password.");

            return $user;
        });

        return $user->fresh(['basicDetails', 'jobDetails']);
    }
}
