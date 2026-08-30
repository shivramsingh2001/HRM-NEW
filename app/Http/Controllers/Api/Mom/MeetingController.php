<?php

namespace App\Http\Controllers\Api\Mom;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\MeetingParticipant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\MeetingNotificationService;
use Exception;
use Carbon\Carbon;

class MeetingController extends Controller
{
    protected $meetingNotificationService;

    public function __construct(MeetingNotificationService $meetingNotificationService)
    {
        $this->meetingNotificationService = $meetingNotificationService;
    }

    /**
     * Get list of meetings with filters
     */
    public function index(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = Meeting::with([
                'creator:id,name,employee_id,email',
                'participants.user:id,name,employee_id,email',
                'momWriters'
            ]);

            // Filter by status
            if ($request->filled('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            // Filter by date from
            if ($request->filled('date_from')) {
                $query->whereDate('meeting_date', '>=', $request->date_from);
            }

            // Filter by date to
            if ($request->filled('date_to')) {
                $query->whereDate('meeting_date', '<=', $request->date_to);
            }

            // Filter by user's involvement (meetings where user is participant or creator)
            if ($request->boolean('my_meetings')) {
                $query->where(function ($q) use ($authUser) {
                    $q->where('created_by', $authUser->id)
                        ->orWhereHas('participants', function ($subQ) use ($authUser) {
                            $subQ->where('user_id', $authUser->id);
                        });
                });
            }

            // Sort by meeting date
            $sortBy = $request->get('sort_by', 'date_desc');
            switch ($sortBy) {
                case 'date_asc':
                    $query->orderBy('meeting_date', 'asc')->orderBy('start_time', 'asc');
                    break;
                case 'date_desc':
                default:
                    $query->orderBy('meeting_date', 'desc')->orderBy('start_time', 'asc');
                    break;
            }

            $meetings = $query->paginate($request->get('per_page', 15));

            // Format meetings for API response
            $formattedMeetings = $meetings->map(function ($meeting) use ($authUser) {
                $participants = $meeting->participants->map(function ($participant) {
                    return [
                        'id' => $participant->user->id ?? null,
                        'name' => $participant->user->name ?? null,
                        'employee_id' => $participant->user->employee_id ?? null,
                        'email' => $participant->user->email ?? null,
                        'role' => $participant->role,
                        'is_mom_writer' => $participant->is_mom_writer
                    ];
                });

                $momWriters = $meeting->momWriters->map(function ($writer) {
                    return [
                        'id' => $writer->user->id,
                        'name' => $writer->user->name,
                        'employee_id' => $writer->user->employee_id,
                        'email' => $writer->user->email
                    ];
                });

                return [
                    'id' => $meeting->id,
                    'meeting_id' => $meeting->meeting_id,
                    'title' => $meeting->title,
                    'description' => $meeting->description,
                    'meeting_date' => $meeting->meeting_date,
                    'start_time' => $meeting->start_time,
                    'end_time' => $meeting->end_time,
                    'meeting_type' => $meeting->meeting_type,
                    'location' => $meeting->location,
                    'status' => $meeting->status,
                    // 'status_display' => ucfirst($meeting->status),
                    'cancellation_reason' => $meeting->cancellation_reason,
                    'reminder_minutes_before' => $meeting->reminder_minutes_before,
                    'created_by' => [
                        'id' => $meeting->creator->id ?? null,
                        'name' => $meeting->creator->name ?? null,
                        'employee_id' => $meeting->creator->employee_id ?? null
                    ],
                    'participants' => $participants,
                    'participant_count' => $participants->count(),
                    'mom_writers' => $momWriters,
                    // 'is_creator' => $meeting->created_by == $authUser->id,
                    // 'is_participant' => $meeting->participants->contains('user_id', $authUser->id),
                    // 'is_mom_writer' => $meeting->momWriters->contains('id', $authUser->id),
                    'created_at' => $meeting->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $meeting->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            // Statistics
            $today = Carbon::today()->format('Y-m-d');
            $stats = [
                'total' => Meeting::count(),
                'upcoming' => Meeting::where('meeting_date', '>=', $today)
                    ->where('status', 'scheduled')
                    ->count(),
                'today' => Meeting::whereDate('meeting_date', $today)->count(),
                'completed' => Meeting::where('status', 'completed')->count(),
                'cancelled' => Meeting::where('status', 'cancelled')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Meetings fetched successfully',
                'data' => $formattedMeetings,
                // 'stats' => $stats,
                'pagination' => [
                    'current_page' => $meetings->currentPage(),
                    'next_page_url' => $meetings->nextPageUrl(),
                    'prev_page_url' => $meetings->previousPageUrl(),
                    'last_page' => $meetings->lastPage(),
                    'per_page' => $meetings->perPage(),
                    'total' => $meetings->total(),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Meeting index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'.$e->getMessage()
            ], 500);
        }
    }


    // public function upcoming(Request $request)
    // {
    //     try {
    //         $authUser = Auth::user();
    //         $limit = $request->get('limit', 5);

    //         $meetings = Meeting::with(['creator', 'participants.user'])
    //             ->where(function($q) use ($authUser) {
    //                 $q->where('created_by', $authUser->id)
    //                     ->orWhereHas('participants', function($subQ) use ($authUser) {
    //                         $subQ->where('user_id', $authUser->id);
    //                     });
    //             })
    //             ->where('meeting_date', '>=', Carbon::today())
    //             ->where('status', 'scheduled')
    //             ->orderBy('meeting_date', 'asc')
    //             ->orderBy('start_time', 'asc')
    //             ->limit($limit)
    //             ->get();

    //         $formattedMeetings = $meetings->map(function($meeting) {
    //             return [
    //                 'id' => $meeting->id,
    //                 'meeting_id' => $meeting->meeting_id,
    //                 'title' => $meeting->title,
    //                 'meeting_date' => $meeting->meeting_date,
    //                 'start_time' => $meeting->start_time,
    //                 'end_time' => $meeting->end_time,
    //                 'location' => $meeting->location,
    //                 'meeting_type' => $meeting->meeting_type,
    //                 'participant_count' => $meeting->participants->count(),
    //             ];
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Upcoming meetings fetched successfully',
    //             'data' => $formattedMeetings
    //         ], 200);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An error occurred. Please try again later.'
    //         ], 500);
    //     }
    // }


    public function store(Request $request)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'meeting_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'meeting_type' => 'required|in:physical,virtual,hybrid',
                'location' => 'required|string|max:255',
                'participants' => 'required|array|min:1',
                'participants.*' => 'exists:users,id',
                'mom_writers' => 'required|array',
                'mom_writers.*' => 'exists:users,id',
                'reminder_minutes' => 'nullable|integer|min:0|max:1440'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' =>  $validator->errors()->first()
                ], 200);
            }

            DB::beginTransaction();

            // Process participants and MOM writers
            $participants = $request->participants;
            $momWriters = $request->mom_writers ?? [];

            // Ensure MOM writers is an array
            if (!is_array($momWriters)) {
                $momWriters = [];
            }

            // For single MOM writer, take only the first one (or adjust as needed)
            if (count($momWriters) > 1) {
                $momWriters = [$momWriters[0]];
            }

            // Generate meeting ID
            $lastMeeting = Meeting::orderBy('id', 'desc')->first();
            $lastNumber = $lastMeeting ? intval(substr($lastMeeting->meeting_id, 3)) : 0;
            $meetingId = 'MT-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

            // Create meeting
            $meeting = Meeting::create([
                'meeting_id' => $meetingId,
                'title' => $request->title,
                'description' => $request->description,
                'meeting_date' => $request->meeting_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'meeting_type' => $request->meeting_type,
                'location' => $request->location,
                'created_by' => $authUser->id,
                'status' => 'scheduled',
                'reminder_minutes_before' => $request->reminder_minutes ?? 15
            ]);

            // Add creator as a participant if not already included
            if (!in_array($authUser->id, $participants)) {
                $participants[] = $authUser->id;
            }

            // Create participants
            foreach ($participants as $userId) {
                MeetingParticipant::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $userId,
                    'is_mom_writer' => in_array($userId, $momWriters),
                   
                    'role' => $userId == $authUser->id ? 'organizer' : 'attendee'
                ]);
            }

            DB::commit();

            // Send notifications in background (optional)
            try {
                $this->meetingNotificationService->notifyMeetingCreated($meeting);
                $this->meetingNotificationService->notifyMomWriters($meeting);
            } catch (Exception $e) {
                Log::error('Failed to send meeting notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting created successfully',

            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create meeting: ' . $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        try {
            $authUser = Auth::user();

            $meeting = Meeting::with([
                'creator:id,name,employee_id,email,contact',
                'participants.user:id,name,employee_id,email',
                'momWriters:id,name,employee_id,email',
                'tasks' => function ($query) {
                    $query->select('id', 'task_code', 'title', 'priority', 'status', 'deadline_date')
                        ->with(['assignments.assignedTo:id,name']);
                },
                'tasks.project:id,name,project_code'
            ])->find($id);

            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found.'
                ], 200);
            }

            // Check if user has access to this meeting
            $hasAccess = ($meeting->created_by == $authUser->id) ||
                $meeting->participants->contains('user_id', $authUser->id) ||
                in_array($authUser->role, ['admin', 'hr']);

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this meeting.'
                ], 200);
            }

            // Format participants
            $participants = $meeting->participants->map(function ($participant) {
                return [
                    'user_id' => $participant->user->id ?? null,
                    'name' => $participant->user->name ?? null,
                    'employee_id' => $participant->user->employee_id ?? null,
                    'email' => $participant->user->email ?? null,
                    'role' => $participant->role,
                    'is_mom_writer' => (bool)$participant->is_mom_writer,
                  
                ];
            });

            // Format MOM writers
            $momWriters = $meeting->momWriters->map(function ($writer) {
                return [
                    'id' => $writer->id,
                    'name' => $writer->name,
                    'employee_id' => $writer->employee_id,
                    'email' => $writer->email
                ];
            });

            // Format tasks
            $tasks = $meeting->tasks->map(function ($task) {
                $assignees = $task->assignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->assignedTo->id ?? null,
                        'name' => $assignment->assignedTo->name ?? null
                    ];
                });

                return [
                    'id' => $task->id,
                    'task_code' => $task->task_code,
                    'title' => $task->title,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'deadline_date' => $task->deadline_date,
                    'project_name' => $task->project->name ?? null,
                    'assignees' => $assignees
                ];
            });

            $response = [
                'id' => $meeting->id,
                'meeting_id' => $meeting->meeting_id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'meeting_date' => $meeting->meeting_date,
                'start_time' => $meeting->start_time,
                'end_time' => $meeting->end_time,
                'meeting_type' => $meeting->meeting_type,
                'location' => $meeting->location,
                'status' => $meeting->status,
                // 'status_display' => ucfirst($meeting->status),
                'cancellation_reason' => $meeting->cancellation_reason,
                'reminder_minutes_before' => $meeting->reminder_minutes_before,
                'created_by' => [
                    'id' => $meeting->creator->id ?? null,
                    'name' => $meeting->creator->name ?? null,
                    'employee_id' => $meeting->creator->employee_id ?? null,
                    'email' => $meeting->creator->email ?? null,
                ],
                'participants' => $participants,
                'participant_count' => $participants->count(),
                'mom_writers' => $momWriters,
                'tasks' => $tasks,
                'task_count' => $tasks->count(),
                // 'is_creator' => $meeting->created_by == $authUser->id,
                // 'is_participant' => $meeting->participants->contains('user_id', $authUser->id),
                // 'is_mom_writer' => $meeting->momWriters->contains('id', $authUser->id),
                // 'can_edit' => ($meeting->created_by == $authUser->id || in_array($authUser->role, ['admin'])) && $meeting->status == 'scheduled',
                // 'can_cancel' => ($meeting->created_by == $authUser->id || in_array($authUser->role, ['admin'])) && $meeting->status == 'scheduled',
                'created_at' => $meeting->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $meeting->updated_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Meeting details fetched successfully',
                'data' => $response
            ], 200);
        } catch (Exception $e) {
            Log::error('Meeting show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $authUser = Auth::user();

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found.'
                ], 200);
            }

            // Check authorization
            if ($meeting->created_by != $authUser->id && !in_array($authUser->role, ['admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to edit this meeting.'
                ], 200);
            }

            // Check if meeting can be edited
            if ($meeting->status != 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => "This meeting cannot be edited because it is {$meeting->status}."
                ], 200);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'meeting_date' => 'sometimes|required|date|after_or_equal:today',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'meeting_type' => 'sometimes|required|in:physical,virtual,hybrid',
                'location' => 'sometimes|required|string|max:255',
                'participants' => 'sometimes|required|array|min:1',
                'participants.*' => 'exists:users,id',
                'mom_writers' => 'nullable|array',
                'mom_writers.*' => 'exists:users,id',
                'reminder_minutes' => 'nullable|integer|min:0|max:1440'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            DB::beginTransaction();

            $oldValues = $meeting->toArray();

            // Update meeting fields
            $updateData = [];
            $fillableFields = ['title', 'description', 'meeting_date', 'start_time', 'end_time', 'meeting_type', 'location', 'reminder_minutes_before'];

            foreach ($fillableFields as $field) {
                $requestField = $field == 'reminder_minutes_before' ? 'reminder_minutes' : $field;
                if ($request->has($requestField)) {
                    $updateData[$field] = $request->$requestField;
                }
            }

            if (!empty($updateData)) {
                $meeting->update($updateData);
            }

            // Update participants if provided
            if ($request->has('participants')) {
                $participants = $request->participants;
                $momWriters = $request->mom_writers ?? [];

                // Add creator if not already in participants
                if (!in_array($meeting->created_by, $participants)) {
                    $participants[] = $meeting->created_by;
                }

                // Get existing participants
                $existingParticipants = $meeting->participants->pluck('user_id')->toArray();

                // Find participants to remove
                $participantsToRemove = array_diff($existingParticipants, $participants);

                // Remove participants that are no longer selected
                if (!empty($participantsToRemove)) {
                    MeetingParticipant::where('meeting_id', $meeting->id)
                        ->whereIn('user_id', $participantsToRemove)
                        ->delete();
                }

                // Add or update participants
                foreach ($participants as $userId) {
                    MeetingParticipant::updateOrCreate(
                        [
                            'meeting_id' => $meeting->id,
                            'user_id' => $userId
                        ],
                        [
                            'is_mom_writer' => in_array($userId, $momWriters),
                            'role' => $userId == $meeting->created_by ? 'organizer' : 'attendee'
                        ]
                    );
                }
            }

            // Track changed fields for notification
            $changedFields = [];
            foreach ($meeting->toArray() as $key => $value) {
                if (isset($oldValues[$key]) && $oldValues[$key] != $value && !in_array($key, ['updated_at'])) {
                    $changedFields[$key] = [
                        'old' => $oldValues[$key],
                        'new' => $value
                    ];
                }
            }

            DB::commit();

            // Send notifications
            try {
                if (!empty($changedFields)) {
                    $this->meetingNotificationService->notifyMeetingUpdated($meeting, $changedFields);
                }
            } catch (Exception $e) {
                Log::error('Failed to send meeting update notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting updated successfully',
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update meeting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel meeting
     */
    public function cancel(Request $request, $id)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|min:5|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 200);
            }

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found.'
                ], 200);
            }

            // Check authorization
            if ($meeting->created_by != $authUser->id && !in_array($authUser->role, ['admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to cancel this meeting.'
                ], 200);
            }

            // Check if meeting is already completed or cancelled
            if (in_array($meeting->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot cancel meeting that is already {$meeting->status}."
                ], 200);
            }

            DB::beginTransaction();

            $meeting->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason
            ]);

            DB::commit();

            // Send notifications
            try {
                $this->meetingNotificationService->notifyMeetingCancelled($meeting, $request->reason);
            } catch (Exception $e) {
                Log::error('Failed to send meeting cancellation notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting cancelled successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting cancel error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel meeting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark meeting as completed
     */
    public function complete(Request $request, $id)
    {
        try {
            $authUser = Auth::user();

            $meeting = Meeting::find($id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found.'
                ], 200);
            }

            // Check authorization (only creator or admin can mark as completed)
            if ($meeting->created_by != $authUser->id && !in_array($authUser->role, ['admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to complete this meeting.'
                ], 200);
            }

            // Check if meeting can be completed
            if ($meeting->status != 'scheduled') {
                return response()->json([
                    'success' => false,
                    'message' => "Meeting cannot be marked as completed because it is {$meeting->status}."
                ], 200);
            }

            DB::beginTransaction();

            $meeting->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Meeting marked as completed successfully'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting complete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete meeting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update participant attendance
     */
    public function updateAttendance(Request $request, $id)
    {
        try {
            $authUser = Auth::user();

            $validator = Validator::make($request->all(), [
                'attendance' => 'required|in:pending,accepted,declined',
                'meeting_id' => 'required|exists:meetings,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 200);
            }

            $meeting = Meeting::find($request->meeting_id);
            if (!$meeting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found.'
                ], 200);
            }

            // Find participant record
            $participant = MeetingParticipant::where('meeting_id', $request->meeting_id)
                ->where('user_id', $authUser->id)
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant of this meeting.'
                ], 200);
            }

            $participant->update([
                'attendance' => $request->attendance,
                'joined_at' => $request->attendance == 'accepted' ? now() : null
            ]);

            // Send notification to meeting creator
            try {

                // $this->meetingNotificationService->notifyAttendanceUpdated($meeting, $authUser, $request->attendance);
            } catch (Exception $e) {
                Log::error('Failed to send attendance notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error('Update attendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meetings where user is MOM writer
     */
    public function momWriterMeetings(Request $request)
    {
        try {
            $authUser = Auth::user();

            $query = Meeting::with(['creator', 'participants.user'])
                ->whereHas('momWriters', function ($q) use ($authUser) {
                    $q->where('users.id', $authUser->id);
                })
                ->where('status', '!=', 'cancelled');

            // Filter by status
            if ($request->filled('status') && $request->status != 'all') {
                $query->where('status', $request->status);
            }

            $meetings = $query->orderBy('meeting_date', 'desc')
                ->paginate($request->get('per_page', 15));

            $formattedMeetings = $meetings->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'meeting_id' => $meeting->meeting_id,
                    'title' => $meeting->title,
                    'meeting_date' => $meeting->meeting_date,
                    'start_time' => $meeting->start_time,
                    'status' => $meeting->status,
                    'participant_count' => $meeting->participants->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'MOM writer meetings fetched successfully',
                'data' => $formattedMeetings,
                'pagination' => [
                    'current_page' => $meetings->currentPage(),
                    'last_page' => $meetings->lastPage(),
                    'per_page' => $meetings->perPage(),
                    'total' => $meetings->total(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
}
