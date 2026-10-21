<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceShiftSegment;
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
    /** $date: the day shown in the right-hand panel (default today), e.g. the Attendance Sessions page. */
    public function forUser(User $user, ?string $date = null): array
    {
        $job = UserJobDetail::leftJoin('designations', 'user_job_details.designation', '=', 'designations.id')
            ->leftJoin('departments', 'user_job_details.department', '=', 'departments.id')
            ->leftJoin('users as reporting_heads', 'user_job_details.reporting_head', '=', 'reporting_heads.id')
            ->where('user_job_details.user_id', $user->id)
            ->select('user_job_details.joining_date', 'designations.name as designation_name',
                'departments.name as department_name', 'reporting_heads.name as reporting_head_name')
            ->first();

        $image = UserBasicDetail::where('user_id', $user->id)->value('profile_image');

        $day = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();
        $record = app(TeamMemberAttendance::class)->getAttendanceDataForUser($day, $day, $user->id)
            ->first(fn ($r) => $r->date == $day);

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
            'shifts' => $this->shiftRows($user->id, $day),
        ];
    }

    /**
     * One row per shift worked that day (normal + extra), from the multi-shift
     * segments AttendanceRollupService writes. Empty on a single-shift day — the
     * day's In / Out / Hours already tell the whole story then.
     */
    public function shiftRows(int $userId, string $date): array
    {
        $segments = AttendanceShiftSegment::with('shift:id,name')
            ->where('user_id', $userId)->whereDate('date', $date)
            ->orderBy('scheduled_start')->orderBy('first_in')
            ->get();

        if ($segments->count() < 2) {
            return [];
        }

        return $segments->map(fn ($seg) => [
            'name' => $seg->shift->name ?? 'Shift',
            'is_extra' => (bool) $seg->is_extra,
            'scheduled' => $seg->scheduled_start && $seg->scheduled_end
                ? $seg->scheduled_start->format('h:i A') . ' – ' . $seg->scheduled_end->format('h:i A') : null,
            'clock_in' => $seg->first_in?->format('Y-m-d H:i:s'),
            'clock_out' => $seg->last_out?->format('Y-m-d H:i:s'),
            'is_open' => (bool) $seg->is_open,
            'worked_minutes' => (int) $seg->worked_minutes,
            'late_minutes' => (int) $seg->late_minutes,
            'early_minutes' => (int) $seg->early_departure_minutes,
        ])->all();
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
            'shifts' => app(self::class)->shiftRows((int) $userInfo->id, Carbon::today()->toDateString()),
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
