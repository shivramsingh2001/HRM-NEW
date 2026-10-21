<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\UserBasicDetail;
use App\Models\UserJobDetail;
use App\Services\Team\TeamMemberAttendance;
use Illuminate\Support\Carbon;

/**
 * Data for <x-ui.profile-header>: who the person is + how today looks for them.
 * Today's status comes from TeamMemberAttendance, so the Employee / Manager
 * dashboards show exactly the status the Team member profile page shows.
 */
class ProfileHeaderData
{
    public function forUser(User $user): array
    {
        $job = UserJobDetail::leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('users as reporting_heads', 'user_job_details.reporting_head', '=', 'reporting_heads.id')
            ->where('user_job_details.user_id', $user->id)
            ->select('user_job_details.joining_date', 'designations.name as designation_name',
                'departments.name as department_name', 'reporting_heads.name as reporting_head_name')
            ->first();

        $image = UserBasicDetail::where('user_id', $user->id)->value('profile_image');

        $today = Carbon::today()->toDateString();
        $record = app(TeamMemberAttendance::class)->getAttendanceDataForUser($today, $today, $user->id)
            ->first(fn ($r) => $r->date == $today);

        return [
            'name' => $user->name,
            'role' => $user->role,
            'image' => $image ? file_url($image, 'profile_photo') : asset('/profile2.jpg'),
            'designation' => $job->designation_name ?? null,
            'department' => $job->department_name ?? null,
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'contact' => $user->contact,
            'reporting_head' => $job->reporting_head_name ?? null,
            'joining_date' => $job->joining_date ?? null,
            'today' => $record ? self::today($record) : null,
        ];
    }

    /**
     * Same shape from data the Team member profile page already loaded
     * (TeamMemberController::getUserProfileData row + getTodayAttendance).
     */
    public static function fromProfile(object $userInfo, string $image, ?array $todayAttendance): array
    {
        return [
            'name' => $userInfo->name,
            'role' => $userInfo->role,
            'image' => $image,
            'designation' => $userInfo->designation,
            'department' => $userInfo->department,
            'employee_id' => $userInfo->employee_id,
            'email' => $userInfo->email,
            'contact' => $userInfo->contact,
            'reporting_head' => $userInfo->reporting_head,
            'joining_date' => $userInfo->joining_date,
            'today' => $todayAttendance ? self::today($todayAttendance) : null,
        ];
    }

    /** Today's part of the header from one TeamMemberAttendance day row. */
    public static function today(object|array $record): array
    {
        $r = (object) $record;

        return [
            'day_status' => $r->day_status ?? null,
            'clock_in' => $r->clock_in ?? null,
            'clock_out' => $r->clock_out ?? null,
            'total_hours' => $r->total_hours ?? null,
            'holiday_name' => $r->holiday_name ?? null,
            'leave_type' => $r->leave_type ?? null,
            'leave_reason' => $r->leave_reason ?? null,
        ];
    }
}
