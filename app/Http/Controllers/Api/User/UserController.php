<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\UserLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserBasicDetail;
use App\Models\UserBankDetail;
use App\Models\UserJobDetail;
use App\Models\Attendance;
use App\Models\AttendanceTrackingPoint;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\Language;
use App\Models\Leave;
use App\Models\Holiday;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use App\Services\RbacService;
use App\Traits\AuthorizesByScope;

class UserController extends Controller
{
    use AuthorizesByScope;

    public function index()
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $user = User::with([
                'basicDetails',
                'bankDetails',
                'jobDetails.departmentRel',
                'jobDetails.designationRel',
                'jobDetails.reportingHead',
                'location.countryRel',
                'location.stateRel',
                'location.cityRel'
            ])->find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User information not found'
                ], 404);
            }

            $baseUrl = config('app.url');

            // Handle languages
            $languageNames = [];
            if ($user->basicDetails && $user->basicDetails->language) {
                $languageIds = is_array($user->basicDetails->language)
                    ? $user->basicDetails->language
                    : json_decode($user->basicDetails->language, true) ?? [];

                if (!empty($languageIds)) {
                    $languageNames = Language::whereIn('id', $languageIds)
                        ->pluck('name')
                        ->toArray();
                }
            }

            $responseData = [
                'personal_information' => [
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'email' => $user->email,
                    'contact' => $user->contact,
                    'role' => $user->role,
                ],

                'basic_details' => [
                    'father_name' => $user->basicDetails?->father_name,
                    'mother_name' => $user->basicDetails?->mother_name,
                    'dob' => $user->basicDetails?->dob,
                    'gender' => $user->basicDetails?->gender,
                    'profile_image' => $user->basicDetails?->profile_image
                        ? file_url($user->basicDetails->profile_image, 'profile_photo')
                        : $baseUrl . '/profile2.jpg',
                    'blood_group' => $user->basicDetails?->blood_group,
                    'marital_status' => $user->basicDetails?->marital_status,
                    'nationality' => $user->basicDetails?->nationality,
                    'alternate_phone' => $user->basicDetails?->alternate_phone,
                    'personal_email' => $user->basicDetails?->personal_email,
                    'aadhaar_no' => $user->basicDetails?->aadhaar_no,
                    'pan_no' => $user->basicDetails?->pan_no,
                    'languages' => $languageNames,
                ],

                'bank_details' => [
                    'account_number' => $user->bankDetails?->account_number,
                    'ifsc' => $user->bankDetails?->ifsc,
                    'bank_name' => $user->bankDetails?->bank_name,
                    'branch_name' => $user->bankDetails?->branch_name,
                ],

                'job_details' => [
                    'designation' => $user->jobDetails?->designationRel?->name,
                    'department' => $user->jobDetails?->departmentRel?->name,
                    'joining_date' => $user->jobDetails?->joining_date,
                    'employment_type' => $user->jobDetails?->employment_type,
                    // Kept for older app builds; primary reporting head only.
                    'reporting_head' => $user->jobDetails?->reportingHead?->name,
                    // Full multi reporting-head set.
                    'reporting_heads' => $user->reportingHeads->map(fn ($head) => [
                        'id' => $head->id,
                        'name' => $head->name,
                        'is_primary' => (bool) $head->pivot->is_primary,
                    ])->values(),
                ],

                'location' => [
                    'country' => $user->location?->countryRel?->name,
                    'state' => $user->location?->stateRel?->name,
                    'city' => $user->location?->cityRel?->name,
                    'country_code' => $user->location?->country,
                    'state_code' => $user->location?->state,
                    'city_code' => $user->location?->city,
                    'address' => $user->location?->address,
                    'permanent_address' => $user->location?->permanent_address,
                    'pincode' => $user->location?->pincode,
                ],

                'documents' => [
                    'experience_letter' => $user->basicDetails?->experience_letter
                        ? file_url($user->basicDetails->experience_letter, 'employee_document')
                        : null,
                    'tenth_marksheet' => $user->basicDetails?->tenth_marksheet
                        ? file_url($user->basicDetails->tenth_marksheet, 'employee_document')
                        : null,
                    'twelfth_marksheet' => $user->basicDetails?->twelfth_marksheet
                        ? file_url($user->basicDetails->twelfth_marksheet, 'employee_document')
                        : null,
                    'highest_qualification_certificate' => $user->basicDetails?->highest_qualification_certificate
                        ? file_url($user->basicDetails->highest_qualification_certificate, 'employee_document')
                        : null,
                ],
                // Full dynamic document list (any number, any type incl. "other").
                'documents_list' => $user->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_type_label' => $document->document_type_label,
                    'document_name' => $document->document_name,
                    'file_url' => file_url($document->file_path, 'employee_document'),
                ])->values(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully',
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function team(Request $request)
    {
        try {
            $authUser = Auth::user();
            $teamScope = app(RbacService::class)->scopeFor($authUser, 'team', 'view');
            if ($teamScope === null || $teamScope === 'own') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers and Hr can view this page.'
                ], 200);
            }
            $currentDate = date('Y-m-d');
            $baseUrl = config('app.url');

            // Get team members based on scope
            if ($teamScope === 'company') {
                // Admin/HR: Get all active users
                $teamMembers = User::where('status', 1)
                    ->whereNotIn('role', ['admin'])
                    ->with([
                        'basicDetails',
                        'jobDetails.designationRel',
                        'jobDetails.departmentRel'
                    ])
                    ->get();
            } else {
                // Manager: Get users reporting to current user (any reporting head)
                $teamMembers = User::managedBy($authUser->id)
                    ->where('status', 1)
                    ->with([
                        'basicDetails',
                        'jobDetails.designationRel',
                        'jobDetails.departmentRel'
                    ])
                    ->get();
            }

            $teamData = [];
            $statusCount = [
                'present' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'weekoff' => 0,
                'total' => $teamMembers->count()
            ];

            // Check if it's a holiday
            $isHoliday = Holiday::whereDate('start_date', '<=', $currentDate)
                ->whereDate('start_date', '>=', $currentDate)
                ->exists();

            $dayName = date('l');

            foreach ($teamMembers as $member) {
                // Check if today is week off for this user from user_weekoffs table
                $isWeekOff = DB::table('user_weekoffs')
                    ->where('user_id', $member->id)
                    ->where('status', 1)
                    ->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('end_date', '>=', $currentDate)
                    ->where(function ($query) use ($dayName, $currentDate) {
                        $query->where(function ($q) use ($currentDate) {
                            // Date based week off
                            $q->where('off_type', 'date_based')
                                ->whereDate('start_date', '<=', $currentDate)
                                ->whereDate('end_date', '>=', $currentDate);
                        })->orWhere(function ($q) use ($dayName) {
                            // Day based week off
                            $q->where('off_type', 'day_based')
                                ->where('day_name', $dayName);
                        });
                    })
                    ->exists();

                // Get today's attendance
                $attendance = Attendance::where('user_id', $member->id)
                    ->whereDate('date', $currentDate)
                    ->first();

                // Check if on leave
                $onLeave = Leave::where('user_id', $member->id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $currentDate)
                    ->whereDate('start_date', '>=', $currentDate)
                    ->exists();

                // Determine status
                $status = 'Absent';

                if ($isHoliday) {
                    $status = 'Holiday';
                    $statusCount['holiday']++;
                } elseif ($onLeave) {
                    $status = 'On Leave';
                    $statusCount['on_leave']++;
                } elseif ($attendance) {
                    if ($attendance->clock_in && $attendance->clock_out) {
                        $status = 'Present';
                        $statusCount['present']++;
                    } elseif ($attendance->clock_in) {
                        $status = 'Present (Not Checked Out)';
                        $statusCount['present']++;
                    }
                } elseif ($isWeekOff) {
                    $status = 'Week Off';
                    $statusCount['weekoff']++;
                } else {
                    $statusCount['absent']++;
                }

                $teamData[] = [
                    'id' => $member->id,
                    'employee_id' => $member->employee_id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role,
                    'designation' => $member->jobDetails?->designationRel?->name,
                    'department' => $member->jobDetails?->departmentRel?->name,
                    'profile_image' => $member->basicDetails?->profile_image
                        ? file_url($member->basicDetails->profile_image, 'profile_photo')
                        : $baseUrl . '/profile2.jpg',
                    'punch_in' => $attendance?->clock_in,
                    'punch_out' => $attendance?->clock_out,
                    'clock_in_lat' => $attendance?->clock_in_lat,
                    'clock_in_long' => $attendance?->clock_in_long,
                    'clock_in_address' => $attendance?->clock_in_address,
                    'clock_out_lat' => $attendance?->clock_out_lat,
                    'clock_out_long' => $attendance?->clock_out_long,
                    'clock_out_address' => $attendance?->clock_out_address,
                    'status' => $status,
                    'is_week_off' => $isWeekOff // Optional: include this flag
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Team status fetched successfully',
                'data' => [
                    'teams' => $teamData,
                    'count' => $statusCount,
                    'date' => $currentDate,
                    'day' => $dayName
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Team attendance error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function getUserProfile($id)
    {
        try {
            $authUser = Auth::user();

            // Authorization check (was "any manager", inconsistent with
            // team()'s team-only listing — now consistently team-scoped).
            if (!$this->scopeCoversOwner($authUser, 'team', 'view', (int) $id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only managers and Hr can view this page.'
                ], 200);
            }

            // Get current month dates
            $currentMonthStart = date('Y-m-01', strtotime('-30 days'));
            $currentMonthEnd = date('Y-m-28', strtotime('+30 days'));
            $today = date('Y-m-d');



            // Get user with all relations
            $user = User::with([
                'basicDetails',
                'bankDetails',
                'jobDetails.departmentRel',
                'jobDetails.designationRel',
                'jobDetails.reportingHead',
                'location.countryRel',
                'location.stateRel',
                'location.cityRel'
            ])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 200);
            }

            $teams = [];
            if (in_array($user->role, ['manager', 'admin', 'hr'])) {
                $teamMembers = User::with([
                    'basicDetails',
                    'jobDetails.designationRel',
                    'jobDetails.departmentRel'
                ])
                    ->whereIn('users.id', function ($q) use ($user) {
                        $q->select('user_id')->from('user_reporting_heads')->where('reporting_head_id', $user->id);
                    })
                    ->where('status', 1)
                    ->where('id', '!=', $user->id)
                    ->get();

                // Get today's date for status calculation
                $currentDate = date('Y-m-d');
                $dayName = date('l');

                // Check if it's a holiday
                $isHoliday = Holiday::whereDate('start_date', '<=', $currentDate)
                    ->whereDate('start_date', '>=', $currentDate)
                    ->exists();

                foreach ($teamMembers as $member) {
                    // Check if today is week off for this user
                    $isWeekOff = DB::table('user_weekoffs')
                        ->where('user_id', $member->id)
                        ->where('status', 1)
                        ->where(function ($query) use ($dayName, $currentDate) {
                            $query->where(function ($q) use ($currentDate) {
                                // Date based week off
                                $q->where('off_type', 'date_based')
                                    ->whereDate('start_date', '<=', $currentDate)
                                    ->whereDate('end_date', '>=', $currentDate);
                            })->orWhere(function ($q) use ($dayName) {
                                // Day based week off
                                $q->where('off_type', 'day_based')
                                    ->where('day_name', $dayName);
                            });
                        })
                        ->exists();

                    // Get today's attendance
                    $attendance = Attendance::where('user_id', $member->id)
                        ->whereDate('date', $currentDate)
                        ->first();

                    // Check if on leave
                    $onLeave = Leave::where('user_id', $member->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $currentDate)
                        ->whereDate('start_date', '>=', $currentDate)
                        ->exists();

                    // Determine status
                    $status = 'Absent';

                    if ($isHoliday) {
                        $status = 'Holiday';
                    } elseif ($onLeave) {
                        $status = 'On Leave';
                    } elseif ($attendance) {
                        if ($attendance->clock_in && $attendance->clock_out) {
                            $status = 'Present';
                        } elseif ($attendance->clock_in) {
                            $status = 'Present (Not Checked Out)';
                        }
                    } elseif ($isWeekOff) {
                        $status = 'Week Off';
                    }

                    $teams[] = [
                        'id' => $member->id,
                        'employee_id' => $member->employee_id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->role,
                        'designation' => $member->jobDetails?->designationRel?->name ?? 'N/A',
                        'department' => $member->jobDetails?->departmentRel?->name ?? 'N/A',
                        'profile_image' => $member->basicDetails?->profile_image
                            ? file_url($member->basicDetails->profile_image, 'profile_photo')
                            : asset('/profile2.jpg'),
                        'punch_in' => $attendance?->clock_in,
                        'punch_out' => $attendance?->clock_out,
                        'clock_in_lat' => $attendance?->clock_in_lat,
                        'clock_in_long' => $attendance?->clock_in_long,
                        'clock_in_address' => $attendance?->clock_in_address,
                        'clock_out_lat' => $attendance?->clock_out_lat,
                        'clock_out_long' => $attendance?->clock_out_long,
                        'clock_out_address' => $attendance?->clock_out_address,
                        'status' => $status,
                        'is_week_off' => $isWeekOff
                    ];
                }
            }

            // Handle languages
            $languageNames = [];
            if ($user->basicDetails && $user->basicDetails->language) {
                $languageIds = is_array($user->basicDetails->language)
                    ? $user->basicDetails->language
                    : json_decode($user->basicDetails->language, true) ?? [];

                if (!empty($languageIds)) {
                    $languageNames = Language::whereIn('id', $languageIds)
                        ->pluck('name')
                        ->toArray();
                }
            }

            // Get holidays for the month
            $holidays = Holiday::whereDate('start_date', '<=', $currentMonthEnd)
                ->whereDate('start_date', '>=', $currentMonthStart)
                ->get()
                ->keyBy(function ($item) {
                    return $item->start_date instanceof \Carbon\Carbon
                        ? $item->start_date->format('Y-m-d')
                        : \Carbon\Carbon::parse($item->start_date)->format('Y-m-d');
                });

            // Get leaves for the user
            $leaves = Leave::where('user_id', $id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $currentMonthEnd)
                ->whereDate('start_date', '>=', $currentMonthStart)
                ->get();

            // Get attendances for the month
            $attendances = Attendance::where('user_id', $id)
                ->whereDate('date', '>=', $currentMonthStart)
                ->whereDate('date', '<=', $currentMonthEnd)
                ->get()
                ->keyBy(function ($item) {
                    return $item->date instanceof \Carbon\Carbon
                        ? $item->date->format('Y-m-d')
                        : \Carbon\Carbon::parse($item->date)->format('Y-m-d');
                });

            // Get user's weekoffs for the entire month period
            $userWeekoffs = DB::table('user_weekoffs')
                ->where('user_id', $id)
                ->where('status', 1)
                ->where(function ($query) use ($currentMonthStart, $currentMonthEnd) {
                    $query->whereBetween('start_date', [$currentMonthStart, $currentMonthEnd])
                        ->orWhereBetween('end_date', [$currentMonthStart, $currentMonthEnd])
                        ->orWhere(function ($q) use ($currentMonthStart, $currentMonthEnd) {
                            $q->where('start_date', '<=', $currentMonthStart)
                                ->where('end_date', '>=', $currentMonthEnd);
                        });
                })
                ->get();

            // Generate date range
            $period = new \DatePeriod(
                new \DateTime($currentMonthStart),
                new \DateInterval('P1D'),
                (new \DateTime($currentMonthEnd))->modify('+1 day')
            );

            $attendanceData = [];
            $attendanceSummary = [
                'total_days' => 0,
                'present' => 0,
                'absent' => 0,
                'on_leave' => 0,
                'holiday' => 0,
                'weekend' => 0, // Keeping the same key name for response
                'checked_in_only' => 0,
                'work_days' => 0,
                'upcoming' => 0,
            ];

            $todayAttendance = null;
            $todayAttendanceId = null;

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $dayName = $date->format('l');
                $currentDate = Carbon::parse($dateStr);
             $isFutureDate = $dateStr > $today;

                $taskCount = DB::selectOne("
                SELECT COUNT(DISTINCT t.id) as count
                FROM tasks t
                INNER JOIN task_assigns ta ON t.id = ta.task_id
                WHERE ta.assigned_to = ?
                    AND t.task_date <= ?
                    AND t.deadline_date >= ?
                   
            ", [$id, $dateStr, $dateStr]);

                $taskCountValue = $taskCount ? (int)$taskCount->count : 0;

                // CHANGED: Check weekoff from user_weekoffs table instead of hardcoded Saturday/Sunday
                $isWeekoff = false;
                foreach ($userWeekoffs as $weekoff) {
                    if ($weekoff->off_type == 'date_based') {
                        // Date-based weekoff
                        if ($dateStr >= $weekoff->start_date && $dateStr <= $weekoff->end_date) {
                            $isWeekoff = true;
                            break;
                        }
                    } else if ($weekoff->off_type == 'day_based') {
                        // Day-based weekoff (recurring)
                        if ($weekoff->day_name == $dayName) {
                            $isWeekoff = true;
                            break;
                        }
                    }
                }

                $attendance = $attendances[$dateStr] ?? null;
                $isHoliday = isset($holidays[$dateStr]);
                $isOnLeave = false;
                $leaveData = null;


                // Check if on leave
                foreach ($leaves as $leave) {
                    $leaveStart = $leave->start_date instanceof \Carbon\Carbon
                        ? $leave->start_date->format('Y-m-d')
                        : \Carbon\Carbon::parse($leave->start_date)->format('Y-m-d');

                    $leaveEnd = $leave->start_date instanceof \Carbon\Carbon
                        ? $leave->start_date->format('Y-m-d')
                        : \Carbon\Carbon::parse($leave->start_date)->format('Y-m-d');

                    if ($dateStr >= $leaveStart && $dateStr <= $leaveEnd) {
                        $isOnLeave = true;
                        $leaveData = $leave;
                        break;
                    }
                }

                // Determine status - USING USER_WEEKOFFS INSTEAD OF HARDCODED WEEKEND
                 if ($isFutureDate) {
                // For future dates, show "Upcoming" unless there's a scheduled event
                if ($isHoliday) {
                    $dayStatus = 'Holiday';
                    $attendanceSummary['holiday']++;
                    $attendanceSummary['work_days']++;
                } elseif ($isOnLeave) {
                    // Handle leave sessions for future dates
                    if ($leaveData->start_date == $leaveData->start_date) {
                        if ($leaveData->start_session == 1 && $leaveData->end_session == 1) {
                            $dayStatus = 'First Half Leave';
                        } elseif ($leaveData->start_session == 2 && $leaveData->end_session == 2) {
                            $dayStatus = 'Second Half Leave';
                        } else {
                            $dayStatus = 'Full Day Leave';
                        }
                    } else {
                        $dayStatus = 'Full Day Leave';
                    }
                    $attendanceSummary['on_leave']++;
                    $attendanceSummary['work_days']++;
                } elseif ($isWeekoff) {
                    $dayStatus = 'Weekend';
                    $attendanceSummary['weekend']++;
                } else {
                    $dayStatus = 'Upcoming';
                    $attendanceSummary['upcoming']++;
                    // Note: Future dates without events don't count as work days
                }
            } else {
                // Past or current date logic
                if ($isHoliday) {
                    $dayStatus = 'Holiday';
                    $attendanceSummary['holiday']++;
                } elseif ($isOnLeave) {
                    if ($leaveData->start_date == $leaveData->start_date) {
                        if ($leaveData->start_session == 1 && $leaveData->end_session == 1) {
                            $dayStatus = 'First Half Leave';
                        } elseif ($leaveData->start_session == 2 && $leaveData->end_session == 2) {
                            $dayStatus = 'Second Half Leave';
                        } else {
                            $dayStatus = 'Full Day Leave';
                        }
                    } else {
                        $dayStatus = 'Full Day Leave';
                    }
                    $attendanceSummary['on_leave']++;
                    $attendanceSummary['work_days']++;
                } elseif ($attendance) {
                    if ($attendance->clock_in && $attendance->clock_out) {
                        $dayStatus = 'Present';
                        $attendanceSummary['present']++;
                        $attendanceSummary['work_days']++;
                    } elseif ($attendance->clock_in) {
                        $dayStatus = 'Checked In Only';
                        $attendanceSummary['checked_in_only']++;
                        $attendanceSummary['work_days']++;
                    }
                } elseif ($isWeekoff) {
                    $dayStatus = 'Weekend';
                    $attendanceSummary['weekend']++;
                } else {
                    $dayStatus = 'Absent';
                    $attendanceSummary['absent']++;
                    $attendanceSummary['work_days']++;
                }
            }

                $attendanceSummary['total_days']++;

                $record = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'date' => $dateStr,
                    'day_name' => $dayName,
                    'clock_in' => $attendance?->clock_in,
                    'clock_out' => $attendance?->clock_out,
                    'total_hours' => $attendance?->total_hours,
                    'leave_type' => $leaveData?->leave_type,
                    'leave_reason' => $leaveData?->reason,
                    'leave_session' => $leaveData?->start_session,
                    'holiday_name' => isset($holidays[$dateStr]) ? $holidays[$dateStr]->name : null,
                    'message' => isset($holidays[$dateStr]) ? $holidays[$dateStr]->name : null,
                    'day_status' => $dayStatus,
                    'task_count' => $taskCountValue,
                ];

                $attendanceData[] = $record;

                // Check if this is today
                if ($dateStr == $today) {
                    $todayAttendance = $record;
                    $todayAttendanceId = $attendance?->id;
                }
            }

            // Get today's location tracks (unchanged)
            $todayLocationTracks = [];
            if ($todayAttendanceId && in_array($todayAttendance['day_status'], ['Present', 'Checked In Only'])) {
                $tracks = AttendanceTrackingPoint::forAttendanceId($todayAttendanceId, $today)->get();

                foreach ($tracks as $track) {
                    $todayLocationTracks[] = [
                        'track_time' => $track->track_time,
                        'latitude' => $track->lat,
                        'longitude' => $track->long,
                        'address' => $track->address,
                    ];
                }
            }

            if ($todayAttendance) {
                $todayAttendance['location_tracks'] = $todayLocationTracks;
                $todayAttendance['total_tracks'] = count($todayLocationTracks);
            }

            $responseData = [
                'personal_information' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'email' => $user->email,
                    'contact' => $user->contact,
                    'role' => $user->role,
                ],
                'teams' => $teams,

                'basic_details' => [
                    'father_name' => $user->basicDetails?->father_name,
                    'mother_name' => $user->basicDetails?->mother_name,
                    'dob' => $user->basicDetails?->dob,
                    'gender' => $user->basicDetails?->gender,
                    'profile_image' => $user->basicDetails?->profile_image
                        ? file_url($user->basicDetails->profile_image, 'profile_photo')
                        : asset('/profile2.jpg'),
                    'blood_group' => $user->basicDetails?->blood_group,
                    'marital_status' => $user->basicDetails?->marital_status,
                    'nationality' => $user->basicDetails?->nationality,
                    'alternate_phone' => $user->basicDetails?->alternate_phone,
                    'personal_email' => $user->basicDetails?->personal_email,
                    'aadhaar_no' => $user->basicDetails?->aadhaar_no,
                    'pan_no' => $user->basicDetails?->pan_no,
                    'languages' => $languageNames,
                ],

                'bank_details' => [
                    'account_number' => $user->bankDetails?->account_number,
                    'ifsc' => $user->bankDetails?->ifsc,
                    'bank_name' => $user->bankDetails?->bank_name,
                    'branch_name' => $user->bankDetails?->branch_name,
                ],

                'job_details' => [
                    'designation' => $user->jobDetails?->designationRel?->name,
                    'department' => $user->jobDetails?->departmentRel?->name,
                    'joining_date' => $user->jobDetails?->joining_date,
                    'employment_type' => $user->jobDetails?->employment_type,
                    // Kept for older app builds; primary reporting head only.
                    'reporting_head' => $user->jobDetails?->reportingHead?->name,
                    // Full multi reporting-head set.
                    'reporting_heads' => $user->reportingHeads->map(fn ($head) => [
                        'id' => $head->id,
                        'name' => $head->name,
                        'is_primary' => (bool) $head->pivot->is_primary,
                    ])->values(),
                ],

                'location' => [
                    'country' => $user->location?->countryRel?->name,
                    'state' => $user->location?->stateRel?->name,
                    'city' => $user->location?->cityRel?->name,
                    'country_code' => $user->location?->country,
                    'state_code' => $user->location?->state,
                    'city_code' => $user->location?->city,
                    'address' => $user->location?->address,
                    'permanent_address' => $user->location?->permanent_address,
                    'pincode' => $user->location?->pincode,
                ],

                'attendance_summary' => [
                    'month' => date('F Y'),
                    'month_start' => $currentMonthStart,
                    'month_end' => $currentMonthEnd,
                    'counts' => $attendanceSummary,
                    'today' => $todayAttendance,
                ],

                'attendance_details' => [
                    'period' => [
                        'start_date' => $currentMonthStart,
                        'end_date' => $currentMonthEnd,
                    ],
                    'attendances' => $attendanceData,
                ],

                'documents' => [
                    'experience_letter' => $user->basicDetails?->experience_letter ? file_url($user->basicDetails->experience_letter, 'employee_document') : null,
                    'tenth_marksheet' => $user->basicDetails?->tenth_marksheet ? file_url($user->basicDetails->tenth_marksheet, 'employee_document') : null,
                    'twelfth_marksheet' => $user->basicDetails?->twelfth_marksheet ? file_url($user->basicDetails->twelfth_marksheet, 'employee_document') : null,
                    'highest_qualification_certificate' => $user->basicDetails?->highest_qualification_certificate ? file_url($user->basicDetails->highest_qualification_certificate, 'employee_document') : null,
                ],
                // Full dynamic document list (any number, any type incl. "other").
                'documents_list' => $user->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_type_label' => $document->document_type_label,
                    'document_name' => $document->document_name,
                    'file_url' => file_url($document->file_path, 'employee_document'),
                ])->values(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'User profile and attendance data fetched successfully',
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error in getUserProfile: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            // Basic Details
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'alternate_phone' => 'nullable|digits:10',
            'personal_email' => 'nullable|email',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',

            // Address Details
            'country' => 'nullable|exists:countries,country_code',
            'state' => 'nullable|exists:states,state_code',
            'city' => 'nullable|exists:cities,city_code',
            'permanent_address' => 'nullable|string',
            'current_address' => 'nullable|string',
            'pin_code' => 'nullable|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        DB::beginTransaction();
        try {
            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                // New photo stored first; the old one is removed after commit.
                $profileImage = file_storage()->replace(
                    $user->basicDetails?->profile_image,
                    $request->file('profile_photo'),
                    'profile_photo',
                    ['tenant' => $user->tenant_id]
                )->path;
            }

            // Update or create basic details
            if ($user->basicDetails) {
                $user->basicDetails->personal_email = $request->personal_email ?? $user->basicDetails->personal_email;
                $user->basicDetails->alternate_phone = $request->alternate_phone ?? $user->basicDetails->alternate_phone;
                $user->basicDetails->gender = $request->gender ?? $user->basicDetails->gender;
                $user->basicDetails->dob = $request->dob ?? $user->basicDetails->dob;
                if (isset($profileImage)) {
                    $user->basicDetails->profile_image = $profileImage;
                }
                $user->basicDetails->save();
            } else {
                UserBasicDetail::create([
                    'user_id' => $user->id,
                    'personal_email' => $request->personal_email,
                    'alternate_phone' => $request->alternate_phone,
                    'gender' => $request->gender,
                    'dob' => $request->dob,
                    'profile_image' => $profileImage ?? null,
                ]);
            }

            // Update or create location
            if ($user->location) {
                $user->location->address = $request->current_address ?? $user->location->address;
                $user->location->country = $request->country ?? $user->location->country;
                $user->location->state = $request->state ?? $user->location->state;
                $user->location->city = $request->city ?? $user->location->city;
                $user->location->pincode = $request->pin_code ?? $user->location->pincode;
                $user->location->permanent_address = $request->permanent_address ?? $user->location->permanent_address;
                $user->location->save();
            } else {
                UserLocation::create([
                    'user_id' => $user->id,
                    'address' => $request->current_address,
                    'country' => $request->country,
                    'state' => $request->state,
                    'city' => $request->city,
                    'pincode' => $request->pin_code,
                    'permanent_address' => $request->permanent_address,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
            ], 200);
        } catch (\App\Exceptions\FileStorageException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->httpStatus() === 422 ? 200 : 500);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }

    public function getCountry(Request $request)
    {
        try {
            $countries = Country::where('status', 1)
                ->orderBy('name', 'asc')
                ->get(['country_code', 'name', 'short_name']);

            return response()->json([
                'success' => true,
                'message' => 'Country retrieved successfully',
                'data' => $countries
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve countries. Please try again.',
            ], 500);
        }
    }

    public function getStates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|exists:countries,country_code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        try {
            $states = State::where('country_code', $request->country_code)
                ->where('status', 1)
                ->orderBy('name', 'asc')
                ->get(['state_code', 'name', 'short_name']);

            return response()->json([
                'success' => true,
                'message' => 'States retrieved successfully',
                'data' => $states
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve states. Please try again.',
            ], 500);
        }
    }

    public function getCities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'state_code' => 'required|exists:states,state_code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        try {
            $cities = City::where('state_code', $request->state_code)
                ->where('status', 1)
                ->orderBy('name', 'asc')
                ->get(['city_code', 'name', 'short_name']);

            return response()->json([
                'success' => true,
                'message' => 'Cities retrieved successfully',
                'data' => $cities
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cities. Please try again.',
            ], 500);
        }
    }

    public function getUserLocationTracks(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'user_id' => 'nullable|exists:users,id',
                'include_heatmap' => 'nullable|boolean',
                'include_path' => 'nullable|boolean',
                'interval_minutes' => 'nullable|integer|min:5|max:60',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            // Determine which user to fetch
            if ($request->has('user_id') && $request->user_id) {
                $user = User::find($request->user_id);
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found'
                    ], 404);
                }
            } else {
                $user = Auth::user();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $date = $request->date;
            $date1 = date('Y-m-d');

            $intervalMinutes = $request->input('interval_minutes', 15);


            $tasks = DB::select("
            SELECT DISTINCT
                t.id,
                t.task_code,
                t.title,
                t.description,
                t.priority,
                t.status,
                t.deadline_date,
                t.task_date,
                t.created_at,
                p.name as project_name,
                p.project_code,
        
                CASE 
                    WHEN t.deadline_date = :deadline_equal THEN 'Due Today'
                    WHEN t.deadline_date < :deadline_less THEN 'Overdue'
                    ELSE 'In Progress'
                END as deadline_status,
        
                CASE
                    WHEN t.deadline_date < :deadline_less2 THEN ABS(DATEDIFF(t.deadline_date, :deadline_diff))
                    ELSE DATEDIFF(t.deadline_date, :deadline_diff2)
                END as days_remaining
        
            FROM tasks t
            INNER JOIN task_assigns ta ON t.id = ta.task_id
            LEFT JOIN projects p ON t.project_id = p.id
        
            WHERE ta.assigned_to = :user_id
                AND t.task_date <= :task_date_val
                AND t.deadline_date >= :deadline_date_val
              
            ORDER BY 
                FIELD(t.priority, 'critical', 'high', 'medium', 'low'),
                t.deadline_date ASC
        ", [
                'user_id' => $user->id,
                'deadline_equal' => $date1,
                'deadline_less' => $date1,
                'deadline_less2' => $date1,
                'deadline_diff' => $date1,
                'deadline_diff2' => $date1,
                'task_date_val' => $date,
                'deadline_date_val' => $date
            ]);

            // Get task counts
            $taskCounts = [
                'total' => count($tasks),
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'due_today' => 0,
                'overdue' => 0
            ];

            foreach ($tasks as $task) {
                if (isset($taskCounts[$task->priority])) {
                    $taskCounts[$task->priority]++;
                }
                if ($task->status == 'pending') {
                    $taskCounts['pending']++;
                } elseif ($task->status == 'in_progress') {
                    $taskCounts['in_progress']++;
                }
                if ($task->deadline_status == 'Due Today') {
                    $taskCounts['due_today']++;
                }
                if ($task->deadline_status == 'Overdue') {
                    $taskCounts['overdue']++;
                }
            }

            // Get attendance for that date
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->first();

            if (!$attendance) {
                return $this->noAttendanceResponse($date, $tasks, $taskCounts);
            }

            // Get location tracks
            $locationTracks = AttendanceTrackingPoint::forAttendanceId($attendance->id, $date)
                ->get()
                ->map(function ($track) {
                    return [
                        'id' => $track->id,
                        'track_time' => $track->track_time,
                        'latitude' => $track->lat,
                        'longitude' => $track->long,
                        'address' => $track->address,
                        'battery_per' => $track->battery_per,
                    ];
                });

            if ($locationTracks->isEmpty()) {
                return $this->noTracksResponse($date, $attendance, $tasks, $taskCounts);
            }

            // Calculate statistics
            $statistics = $this->calculateStatistics($locationTracks, $attendance, $intervalMinutes);

            // Prepare response data
            $responseData = [
                'date' => $date,
                'attendance' => [
                    'clock_in' => $attendance->clock_in,
                    'clock_out' => $attendance->clock_out,
                    'total_hours' => $attendance->total_hours,
                ],
                'location_tracks' => $locationTracks,
                'statistics' => $statistics,
                'tasks' => [
                    'counts' => $taskCounts,
                    'list' => $tasks
                ],

            ];

            return response()->json([
                'success' => true,
                'message' => 'Location tracks fetched successfully',
                'data' => $responseData
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location tracks: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function noTracksResponse($date, $attendance, $taskCounts, $tasks)
    {

        return response()->json([
            'success' => true,
            'message' => 'No location tracks found for this date',
            'data' => [
                'date' => $date,
                'has_attendance' => true,
                'has_tracks' => false,
                'attendance' => [
                    'clock_in' => $attendance->clock_in,
                    'clock_out' => $attendance->clock_out,
                ],
                'location_tracks' => [],
                'statistics' => [
                    'total_tracks' => 0,
                    'tracking_duration' => 0
                ],
                'tasks' => [
                    'counts' => $taskCounts,
                    'list' => $tasks
                ],

            ]
        ], 200);
    }

    private function calculateStatistics($tracks, $attendance, $intervalMinutes)
    {
        $firstTrack = $tracks->first();
        $lastTrack = $tracks->last();

        $firstTime = Carbon::parse($firstTrack['track_time']);
        $lastTime = Carbon::parse($lastTrack['track_time']);

        $totalMinutes = $firstTime->diffInMinutes($lastTime);

        return [
            'total_tracks' => $tracks->count(),
            'first_track_time' => $firstTrack['track_time'],
            'last_track_time' => $lastTrack['track_time'],
            'tracking_duration_hours' => round($totalMinutes / 60, 2),
            'tracking_duration_minutes' => $totalMinutes,
        ];
    }

    private function noAttendanceResponse($date, $tasks, $taskCounts)
    {
        $responseData = [
            'date' => $date,
            'attendance' => [
                'clock_in' => null,
                'clock_out' => null,
                'total_hours' => null,
            ],
            'location_tracks' => [],
            'statistics' => [
                'total_tracks' => null,
                'first_track_time' => null,
                'last_track_time' => null,
                'tracking_duration_hours' => null,
                'tracking_duration_minutes' => null,
            ],
            'tasks' => [
                'counts' => $taskCounts,
                'list' => $tasks
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'No attendance record found for this date',
            'data' => $responseData
        ], 200);
    }

    public function allusers()
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $users = User::with([
                'basicDetails',
                'jobDetails.departmentRel',
                'jobDetails.designationRel',
                'jobDetails.reportingHead',
            ])
                ->where('role', '!=', 'admin')
                ->where('id', '!=', $userId)
                ->where('status', 1)
                ->get();

            if (!$users || $users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No users found'
                ], 200);
            }

            $baseUrl = config('app.url');

            // Format response for all users
            $responseData = [];

            foreach ($users as $user) {

                $responseData[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'employee_id' => $user->employee_id,
                    'email' => $user->email,
                    'contact' => $user->contact,
                    // 'gender' => $user->basicDetails?->gender,
                    'profile_image' => $user->basicDetails?->profile_image
                        ? file_url($user->basicDetails->profile_image, 'profile_photo')
                        : $baseUrl . '/profile2.jpg',
                    'designation' => $user->jobDetails?->designationRel?->name,
                    'department' => $user->jobDetails?->departmentRel?->name,
                    'joining_date' => $user->jobDetails?->joining_date,
                    'reporting_head' => $user->jobDetails?->reportingHead?->name,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Data fetched successfully',
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
