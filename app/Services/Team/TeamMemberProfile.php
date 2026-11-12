<?php

namespace App\Services\Team;

use App\Models\Attendance;
use App\Models\AttendanceTrackingPoint;
use App\Models\Language;
use App\Models\User;
use App\Models\UserLocation;
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
     * The member page as JSON for the mobile admin API, with the same keys as the
     * employee app's GET /api/user/team-profile/{id} (UserController::getUserProfile)
     * so the app can reuse that screen: personal_information, teams, basic_details,
     * bank_details, job_details, location, attendance_summary, attendance_details,
     * documents, documents_list. Differences: any month (?month=), Aadhaar / PAN /
     * account number show the last 4 only, and the day statuses / counts are the
     * web Team page's (TeamMemberAttendance).
     */
    public function detail(User $viewer, User $target, string $month): ?array
    {
        $info = $this->profileData((int) $target->id);
        if (! $info) {
            return null;
        }
        $target->loadMissing(['reportingHeads']);
        $codes = UserLocation::where('user_id', $target->id)->first(['country', 'state', 'city']);

        $today = Carbon::today()->toDateString();
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $languages = $info->language ? Language::whereIn('id', (array) json_decode($info->language, true))->pluck('name')->all() : [];
        $mask = fn ($v) => $v ? str_repeat('X', max(0, strlen((string) $v) - 4)).substr((string) $v, -4) : null;

        return [
            'personal_information' => [
                'id' => $info->id, 'name' => $info->name, 'employee_id' => $info->employee_id,
                'email' => $info->email, 'contact' => $info->contact, 'role' => $info->role,
            ],
            'teams' => in_array($info->role, ['manager', 'admin', 'hr'], true) ? $this->teamRows($viewer, (int) $info->id, $today) : [],
            'basic_details' => [
                'father_name' => $info->father_name, 'mother_name' => $info->mother_name, 'dob' => $info->dob,
                'gender' => $info->gender,
                'profile_image' => $info->profile_image ? file_url($info->profile_image, 'profile_photo') : asset('/profile2.jpg'),
                'blood_group' => $info->blood_group, 'marital_status' => $info->marital_status,
                'nationality' => $info->nationality, 'alternate_phone' => $info->alternate_phone,
                'personal_email' => $info->personal_email,
                'aadhaar_no' => $mask($info->aadhaar_no), 'pan_no' => $mask($info->pan_no),
                'languages' => $languages,
            ],
            'bank_details' => [
                'account_number' => $mask($info->account_number), 'ifsc' => $info->ifsc,
                'bank_name' => $info->bank_name, 'branch_name' => $info->branch_name,
            ],
            'job_details' => [
                'designation' => $info->designation, 'department' => $info->department,
                'joining_date' => $info->joining_date, 'employment_type' => $info->employment_type,
                'reporting_head' => $info->reporting_head,
                'reporting_heads' => $target->reportingHeads->map(fn ($head) => [
                    'id' => $head->id, 'name' => $head->name, 'is_primary' => (bool) $head->pivot->is_primary,
                ])->values()->all(),
            ],
            'location' => [
                'country' => $info->country, 'state' => $info->state, 'city' => $info->city,
                'country_code' => $codes?->getAttribute('country'), 'state_code' => $codes?->getAttribute('state'),
                'city_code' => $codes?->getAttribute('city'),
                'address' => $info->address, 'permanent_address' => $info->permanent_address, 'pincode' => $info->pincode,
            ],
            ...$this->monthAttendance($target, $start, $today),
            'documents' => $this->documents($info),
            'documents_list' => $target->documents()->get()->map(fn ($document) => [
                'id' => $document->id,
                'document_type' => $document->document_type,
                'document_type_label' => $document->document_type_label,
                'document_name' => $document->document_name,
                'file_url' => file_url($document->file_path, 'employee_document'),
            ])->values()->all(),
        ];
    }

    /**
     * attendance_summary + attendance_details for one month: one row per day from
     * the 1st (or the joining day) to the month end — later days "Upcoming" — and
     * the counts for the days up to today (as the web member page).
     */
    private function monthAttendance(User $target, Carbon $start, string $today): array
    {
        $from = max($start->toDateString(), $target->created_at->format('Y-m-d'));
        $to = $start->copy()->endOfMonth()->toDateString();
        $records = $from <= $to ? $this->attendance->getAttendanceDataForUser($from, $to, $target->id) : collect();
        $records = $this->attendance->addTaskCountsToAttendance(collect($records)->sortBy('date')->values(), $target->id);
        $web = $this->attendance->calculateSummary($records->filter(fn ($r) => $r->date <= $today)->values());

        $todayRecord = collect($this->attendance->getAttendanceDataForUser($today, $today, $target->id))->first();
        $todayRow = $todayRecord ? $this->dayRow($target, $this->attendance->addTaskCountsToAttendance(collect([$todayRecord]), $target->id)->first()) : null;
        if ($todayRow) {
            $tracks = $this->todayTracks((int) $target->id, $today);
            $todayRow['location_tracks'] = $tracks;
            $todayRow['total_tracks'] = count($tracks);
        }

        return [
            'attendance_summary' => [
                'month' => $start->format('F Y'),
                'month_start' => $from <= $to ? $from : null,
                'month_end' => $from <= $to ? $to : null,
                'counts' => [
                    'total_days' => $web['total_days'],
                    'present' => $web['present'],
                    'absent' => $web['absent'],
                    'on_leave' => $web['on_leave'],
                    'holiday' => $web['holiday'],
                    'weekend' => $web['week_off'],
                    'checked_in_only' => $web['checked_in_only'],
                    'half_day' => $web['halfday'],
                    'work_days' => $web['work_days'],
                    'upcoming' => $records->filter(fn ($r) => $r->day_status === 'Upcoming')->count(),
                ],
                'today' => $todayRow,
            ],
            'attendance_details' => [
                'period' => ['start_date' => $from <= $to ? $from : null, 'end_date' => $from <= $to ? $to : null],
                'attendances' => $records->map(fn ($r) => $this->dayRow($target, $r))->all(),
            ],
        ];
    }

    /** One day in team-profile's attendance keys, plus the web member page's extras (formatted times, shifts, tap detail). */
    private function dayRow(User $target, object $r): array
    {
        return [
            'user_id' => $target->id,
            'name' => $target->name,
            'email' => $target->email,
            'date' => $r->date,
            'day_name' => $r->day_name,
            'clock_in' => $r->clock_in ? (string) $r->clock_in : null,
            'clock_out' => $r->clock_out ? (string) $r->clock_out : null,
            'total_hours' => $r->total_hours,
            'leave_type' => $r->leave_type,
            'leave_type_name' => $r->leave_type,
            'leave_reason' => $r->leave_reason,
            'leave_session' => $r->leave_session,
            'holiday_name' => $r->holiday_name,
            'message' => $r->message,
            'day_status' => $r->day_status,
            'task_count' => (int) ($r->task_count ?? 0),
            'clock_in_time' => $r->clock_in ? Carbon::parse($r->clock_in)->format('h:i A') : null,
            'clock_out_time' => $r->clock_out ? clock_out_time($r->clock_out, $r->date) : null, // "07:10 AM (+1 day)" for overnight
            'day_value' => $r->day_value,
            'day_label' => $r->day_label,
            'shifts' => $r->shifts,
            'details' => $r->details ?? null,
        ];
    }

    /** Today's GPS points of the member's attendance (team-profile's location_tracks). */
    private function todayTracks(int $userId, string $today): array
    {
        $attendanceId = Attendance::where('user_id', $userId)->whereDate('date', $today)->whereNotNull('clock_in')->value('id');
        if (! $attendanceId) {
            return [];
        }

        $rows = [];
        foreach (AttendanceTrackingPoint::forAttendanceId((int) $attendanceId, $today)->get() as $track) {
            $time = $track->getAttribute('track_time');
            $rows[] = [
                'track_time' => $time ? Carbon::parse($time)->format('Y-m-d H:i:s') : null,
                'latitude' => $track->getAttribute('lat'),
                'longitude' => $track->getAttribute('long'),
                'address' => $track->getAttribute('address'),
            ];
        }

        return $rows;
    }

    /** The member's direct reports with today's status (team-profile's teams keys; status as the web Team page). */
    private function teamRows(User $viewer, int $managerId, string $today): array
    {
        $members = $this->getTeamMembers($viewer, $today, $managerId);
        if ($members->isEmpty()) {
            return [];
        }
        $ids = $members->pluck('id')->all();
        $roles = User::whereIn('id', $ids)->pluck('role', 'id');
        $attendances = Attendance::whereIn('user_id', $ids)->whereDate('date', $today)->get()->keyBy('user_id');

        return $members->map(function ($m) use ($roles, $attendances) {
            $a = $attendances->get($m->id);

            return [
                'id' => $m->id,
                'employee_id' => $m->employee_id,
                'name' => $m->name,
                'email' => $m->email,
                'role' => $roles[$m->id] ?? null,
                'designation' => $m->designation ?? 'N/A',
                'department' => $m->department ?? 'N/A',
                'profile_image' => $m->profile_image ? file_url($m->profile_image, 'profile_photo') : asset('/profile2.jpg'),
                'punch_in' => $m->punch_in ? (string) $m->punch_in : null,
                'punch_out' => $m->punch_out ? (string) $m->punch_out : null,
                'clock_in_lat' => $a?->getAttribute('clock_in_lat'),
                'clock_in_long' => $a?->getAttribute('clock_in_long'),
                'clock_in_address' => $a?->getAttribute('clock_in_address'),
                'clock_out_lat' => $a?->getAttribute('clock_out_lat'),
                'clock_out_long' => $a?->getAttribute('clock_out_long'),
                'clock_out_address' => $a?->getAttribute('clock_out_address'),
                'status' => $m->status,
                'is_week_off' => $m->status === 'Week Off',
            ];
        })->values()->all();
    }
}
