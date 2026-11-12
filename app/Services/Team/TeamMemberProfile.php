<?php

namespace App\Services\Team;

use App\Models\Language;
use App\Models\User;
use App\Services\Dashboard\ProfileHeaderData;
use App\Services\RbacService;
use Illuminate\Support\Carbon;

/**
 * One team member's profile as the Team → member page shows it: who may open it,
 * the joined profile row, document links, and (for the mobile admin API) the
 * whole page as plain JSON. Shared by Team\TeamMemberController and
 * Api\Admin\TeamController.
 */
class TeamMemberProfile
{
    use \App\Http\Controllers\Concerns\TeamAttendanceStatus;
    use \App\Traits\AuthorizesByScope;

    public function __construct(private TeamMemberAttendance $attendance) {}

    /** RBAC team:view scope covers them; a team-scoped manager also sees people further down their chain. */
    public function canView(User $viewer, User $target): bool
    {
        return $this->scopeCoversOwner($viewer, 'team', 'view', $target->id)
            || (app(RbacService::class)->scopeFor($viewer, 'team', 'view') === 'team' && User::reportsUpTo((int) $target->id, (int) $viewer->id));
    }

    /** users + basic / bank / job details + names of department, designation, reporting head, location. */
    public function profileData(int $userId): ?object
    {
        return User::where('users.id', $userId)
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->leftJoin('user_bank_details', 'users.id', '=', 'user_bank_details.user_id')
            ->leftJoin('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('users as reporting_users', 'user_job_details.reporting_head', '=', 'reporting_users.id')
            ->leftJoin('user_locations', 'users.id', '=', 'user_locations.user_id')
            ->leftJoin('countries', 'user_locations.country', '=', 'countries.country_code')
            ->leftJoin('states', 'user_locations.state', '=', 'states.state_code')
            ->leftJoin('cities', 'user_locations.city', '=', 'cities.city_code')
            ->select([
                'users.id', 'users.name', 'users.employee_id', 'users.email', 'users.contact', 'users.role', 'users.created_at',

                'user_basic_details.father_name', 'user_basic_details.mother_name', 'user_basic_details.dob',
                'user_basic_details.gender', 'user_basic_details.profile_image', 'user_basic_details.blood_group',
                'user_basic_details.marital_status', 'user_basic_details.nationality', 'user_basic_details.alternate_phone',
                'user_basic_details.personal_email', 'user_basic_details.aadhaar_no', 'user_basic_details.pan_no',
                'user_basic_details.language',

                'user_basic_details.experience_letter', 'user_basic_details.tenth_marksheet',
                'user_basic_details.twelfth_marksheet', 'user_basic_details.highest_qualification_certificate',

                'user_bank_details.account_number', 'user_bank_details.ifsc', 'user_bank_details.bank_name',
                'user_bank_details.branch_name',

                'designations.name as designation', 'departments.name as department',
                'reporting_users.name as reporting_head', 'user_job_details.joining_date', 'user_job_details.employment_type',

                'countries.name as country', 'states.name as state', 'cities.name as city',
                'user_locations.address', 'user_locations.permanent_address', 'user_locations.pincode',
            ])
            ->first();
    }

    /** @return array<string, ?string> document name => link */
    public function documents(object $userInfo): array
    {
        return [
            'experience_letter' => file_url($userInfo->experience_letter, 'employee_document'),
            'tenth_marksheet' => file_url($userInfo->tenth_marksheet, 'employee_document'),
            'twelfth_marksheet' => file_url($userInfo->twelfth_marksheet, 'employee_document'),
            'highest_qualification_certificate' => file_url($userInfo->highest_qualification_certificate, 'employee_document'),
        ];
    }

    /**
     * The member page as JSON (mobile admin API): header, personal, job, address,
     * bank, documents, the month's attendance summary and — for a manager — their
     * direct reports with today's status. ID / account numbers show the last 4 only.
     */
    public function detail(User $viewer, User $target, string $month): ?array
    {
        $info = $this->profileData((int) $target->id);
        if (! $info) {
            return null;
        }

        $today = Carbon::today()->toDateString();
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $from = max($start->toDateString(), $target->created_at->format('Y-m-d'));
        $to = min($start->copy()->endOfMonth()->toDateString(), $today);
        $days = $from <= $to ? $this->attendance->getAttendanceDataForUser($from, $to, $target->id) : [];

        $todayRows = $this->attendance->getAttendanceDataForUser($today, $today, $target->id);
        $todayRow = collect($todayRows)->first();
        $image = $info->profile_image ? file_url($info->profile_image, 'profile_photo') : asset('/profile2.jpg');
        $languages = $info->language ? Language::whereIn('id', (array) json_decode($info->language, true))->pluck('name')->all() : [];
        $mask = fn ($v) => $v ? str_repeat('X', max(0, strlen((string) $v) - 4)).substr((string) $v, -4) : null;

        return [
            'header' => ProfileHeaderData::fromProfile($info, $image, $todayRow ? (array) $todayRow : null),
            'personal' => [
                'father_name' => $info->father_name, 'mother_name' => $info->mother_name, 'dob' => $info->dob,
                'age' => $info->dob ? Carbon::parse($info->dob)->age : null, 'gender' => $info->gender,
                'blood_group' => $info->blood_group, 'marital_status' => $info->marital_status,
                'nationality' => $info->nationality, 'alternate_phone' => $info->alternate_phone,
                'personal_email' => $info->personal_email, 'languages' => $languages,
                'aadhaar_no' => $mask($info->aadhaar_no), 'pan_no' => $mask($info->pan_no),
            ],
            'job' => [
                'designation' => $info->designation, 'department' => $info->department,
                'reporting_head' => $info->reporting_head, 'joining_date' => $info->joining_date,
                'employment_type' => $info->employment_type, 'role' => $info->role,
            ],
            'address' => [
                'address' => $info->address, 'permanent_address' => $info->permanent_address,
                'city' => $info->city, 'state' => $info->state, 'country' => $info->country, 'pincode' => $info->pincode,
            ],
            'bank' => [
                'bank_name' => $info->bank_name, 'branch_name' => $info->branch_name,
                'ifsc' => $info->ifsc, 'account_number' => $mask($info->account_number),
            ],
            'documents' => $this->documents($info),
            'month' => $start->format('Y-m'),
            'month_summary' => $this->attendance->calculateSummary($days),
            'direct_reports' => $info->role === 'manager'
                ? $this->getTeamMembers($viewer, $today, (int) $info->id)->map(fn ($m) => [
                    'id' => $m->id, 'employee_id' => $m->employee_id, 'name' => $m->name,
                    'designation' => $m->designation, 'status' => $m->status,
                    'profile_image_url' => $m->profile_image ? file_url($m->profile_image, 'profile_photo') : null,
                ])->values()->all()
                : [],
        ];
    }
}
