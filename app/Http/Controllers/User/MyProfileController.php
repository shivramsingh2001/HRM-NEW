<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLocation;
use App\Services\AuthAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * "My Profile" — the signed-in employee / manager / HR user's own details,
 * read-only, plus changing their own password. There is no {id} anywhere:
 * it always shows Auth::user(), so nobody can open someone else's record
 * here. Editing other people (or one's own job data) stays with admin/HR on
 * the Employee 360 page (employee.show).
 */
class MyProfileController extends Controller
{
    public function __construct(private AuthAuditService $audit)
    {
    }

    public function show()
    {
        $user = Auth::user()->load([
            'basicDetails',
            'bankDetails',
            'jobDetails.designationRel',
            'jobDetails.departmentRel',
            'jobDetails.branch',
            'jobDetails.attendanceLocation',
        ]);

        $job = $user->jobDetails;

        // Reporting heads (multi-head table), falling back to the legacy single column.
        $reportingHeads = DB::table('user_reporting_heads as rh')
            ->join('users as h', 'h.id', '=', 'rh.reporting_head_id')
            ->where('rh.user_id', $user->id)
            ->pluck('h.name')
            ->all();
        if ($reportingHeads === [] && $job?->reporting_head) {
            $reportingHeads = array_filter([DB::table('users')->where('id', $job->reporting_head)->value('name')]);
        }

        return view('client.user.my-profile', [
            'user' => $user,
            'basic' => $user->basicDetails,
            'bank' => $user->bankDetails,
            'job' => $job,
            'reportingHeads' => $reportingHeads,
            'attendanceLocation' => $job ? AttendanceLocation::labelFor($job->office_branch, $job->attendanceLocation?->name) : '—',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required',
            // Same policy as the mobile API's changePassword()
            'new_password' => ['required', 'different:current_password', Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => 'required|same:new_password',
        ], [
            'new_password.different' => 'New password must be different from current password.',
            'confirm_password.same' => 'Confirm password must match new password.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->with('open_password', true);
        }

        $user->password = Hash::make($request->new_password);
        $user->must_change_password = 0;
        $user->save();
        $this->audit->logPasswordChanged($user->id, $user->tenant_id);

        return redirect()->route('my-profile.show')->with('success', 'Password changed successfully.');
    }
}
