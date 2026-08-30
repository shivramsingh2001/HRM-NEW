<?php

namespace App\Http\Controllers\Mom;

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
use App\Services\MeetingNotificationService;
use Exception;

class MeetingController extends Controller
{
    protected $meetingNotificationService;

    public function __construct(MeetingNotificationService $meetingNotificationService)
    {
        $this->meetingNotificationService = $meetingNotificationService;
    }

    // public function index(Request $request)
    // {
    //     $query = Meeting::with(['creator', 'participants.user', 'momWriters']);

    //     // Filter by status
    //     if ($request->filled('status')) {
    //         $query->where('status', $request->status);
    //     }

    //     // Filter by date from
    //     if ($request->filled('date_from')) {
    //         $query->whereDate('meeting_date', '>=', $request->date_from);
    //     }

    //     // Filter by date to
    //     if ($request->filled('date_to')) {
    //         $query->whereDate('meeting_date', '<=', $request->date_to);
    //     }

    //     // Only show upcoming meetings if no filters applied
    //     // $hasFilters = $request->filled('status') || $request->filled('date_from') || $request->filled('date_to');

    //     // if (!$hasFilters) {
    //     //     $query->where('meeting_date', '>=', now()->toDateString());
    //     // }

    //     $meetings = $query->orderBy('meeting_date', 'desc')
    //         ->orderBy('start_time', 'asc')
    //         ->paginate(15)
    //         ->withQueryString();

    //     $today = date('y-m-d');
    //     // Stats for dashboard
    //     $upcomingCount = Meeting::where('meeting_date', '>=', $today)
    //         ->where('status', 'scheduled')
    //         ->count();
    //     $todayCount = Meeting::whereDate('meeting_date', $today)->count();
    //     $totalMeetings = Meeting::count();
    //     $completeMeetings = Meeting::where('status', 'completed')->count();

    //     return view('client.mom.meeting.index', compact('meetings', 'todayCount', 'totalMeetings', 'upcomingCount', 'completeMeetings'));
    // }
    public function index(Request $request)
{
    $authUser = Auth::user();
    $query = Meeting::with(['creator', 'participants.user', 'momWriters']);

    // Role-based filtering
    if (in_array($authUser->role, ['admin', 'hr'])) {
        // Admin/HR: See all meetings (no filter)
        // No additional where clause needed
    } 
    elseif (in_array($authUser->role, ['manager', 'employee'])) {
        // Manager/Employee: See only meetings they are participants of
        $query->whereHas('participants', function ($q) use ($authUser) {
            $q->where('user_id', $authUser->id);
        });
    }
    else {
        // Default: Show only meetings where user is participant
        $query->whereHas('participants', function ($q) use ($authUser) {
            $q->where('user_id', $authUser->id);
        });
    }

    // Filter by status
    if ($request->filled('status')) {
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

    // Search by meeting title or ID
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('meeting_id', 'LIKE', "%{$search}%");
        });
    }

    $meetings = $query->orderBy('meeting_date', 'desc')
        ->orderBy('start_time', 'asc')
        ->paginate(15)
        ->withQueryString();

    $today = date('Y-m-d');
    
    // Stats for dashboard with role-based filtering
    $statsQuery = Meeting::query();
    
    if (!in_array($authUser->role, ['admin', 'hr'])) {
        $statsQuery->whereHas('participants', function ($q) use ($authUser) {
            $q->where('user_id', $authUser->id);
        });
    }
    
    $upcomingCount = (clone $statsQuery)->where('meeting_date', '>=', $today)
        ->where('status', 'scheduled')
        ->count();
        
    $todayCount = (clone $statsQuery)->whereDate('meeting_date', $today)->count();
    $totalMeetings = (clone $statsQuery)->count();
    $completeMeetings = (clone $statsQuery)->where('status', 'completed')->count();

    return view('client.mom.meeting.index', compact(
        'meetings', 
        'todayCount', 
        'totalMeetings', 
        'upcomingCount', 
        'completeMeetings'
    ));
}

    /**
     * Show create form
     */
    public function create()
    {
        $allUsers = User::where('status', '1')->get();
        $projects = Project::where('status', 'ongoing')->get();

        return view('client.mom.meeting.create', compact('allUsers', 'projects'));
    }

    /**
     * Store meeting
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'meeting_type' => 'required|in:physical,virtual,hybrid', // Added validation
            'location' => 'required|string', // Added validation
            'participants' => 'required|json|min:1',
            'participants.*' => 'exists:users,id',
            'mom_writers' => 'nullable|json', // Changed to nullable
            'mom_writers.*' => 'exists:users,id', // Added validation for mom_writers
            'reminder_minutes' => 'nullable|integer|min:0|max:1440' // Added validation
        ]);

        DB::beginTransaction();

        try {
            $participants = json_decode($request->participants, true);
            $momWriters = json_decode($request->mom_writers, true) ?? [];

            // Validate that participants is an array after decoding
            if (!is_array($participants) || empty($participants)) {
                throw new \Exception('Invalid participants data');
            }

            // Ensure mom_writers is an array
            if (!is_array($momWriters)) {
                $momWriters = [];
            }

            // For single MOM writer, take only the first one
            if (count($momWriters) > 1) {
                $momWriters = [$momWriters[0]];
            }
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
                'meeting_type' => $request->meeting_type, // Added meeting type
                'location' => $request->location,
                'created_by' => Auth::id(),
                'status' => 'scheduled',
                'reminder_minutes_before' => $request->reminder_minutes ?? 15 // Added reminder
            ]);

            // Optional: Add creator as a participant if not already included
            if (!in_array(Auth::id(), $participants)) {
                $participants[] = Auth::id();
            }

            foreach ($participants as $userId) {
                MeetingParticipant::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $userId,
                    'is_mom_writer' => in_array($userId, $momWriters),
                    'attendance' => 'pending',
                    'role' => $userId == Auth::id() ? 'organizer' : 'attendee' // Add role
                ]);
            }
            try {
                $this->meetingNotificationService->notifyMeetingCreated($meeting);
                $this->meetingNotificationService->notifyMomWriters($meeting);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }

            DB::commit();
            return redirect()->route('meetings.index')
                ->with('success', 'Meeting created successfully!');
        } catch (Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create meeting: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id)
    {
        $meeting = Meeting::with(['participants.user', 'momWriters'])
            ->findOrFail($id);

        // Check authorization (only creator or admin can edit)
        if ($meeting->created_by != Auth::id() && Auth::user()->role != 'admin') {
            return redirect()->route('meetings.index')
                ->with('error', 'You are not authorized to edit this meeting.');
        }

        // Only scheduled meetings can be edited
        if ($meeting->status != 'scheduled') {
            return redirect()->route('meetings.show', $meeting->id)
                ->with('warning', 'This meeting cannot be edited because it is ' . $meeting->status . '.');
        }

        $allUsers = User::where('status', '1')->get();

        return view('client.mom.meeting.update', compact('meeting', 'allUsers'));
    }

    public function update(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);

        // Check authorization
        if ($meeting->created_by != auth()->id() && auth()->user()->role != 'admin') {
            return redirect()->route('meetings.index')
                ->with('error', 'You are not authorized to edit this meeting.');
        }

        // Check if meeting can be edited
        if ($meeting->status != 'scheduled') {
            return redirect()->route('meetings.show', $meeting->id)
                ->with('warning', 'This meeting cannot be edited because it is ' . $meeting->status . '.');
        }

        // Decode JSON strings to arrays
        $formData = $request->all();

        if (isset($formData['participants']) && is_string($formData['participants'])) {
            $formData['participants'] = json_decode($formData['participants'], true);
        }

        if (isset($formData['mom_writers']) && is_string($formData['mom_writers'])) {
            $formData['mom_writers'] = json_decode($formData['mom_writers'], true);
        }

        // Validate the decoded data
        $validated = validator($formData, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meeting_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'meeting_type' => 'required|in:physical,virtual,hybrid',
            'location' => 'required|string',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'mom_writers' => 'nullable|array',
            'mom_writers.*' => 'exists:users,id',
            'reminder_minutes' => 'nullable|integer|min:0|max:1440'
        ])->validate();

        DB::beginTransaction();

        try {
            // Get the decoded arrays
            $participants = $formData['participants'];
            $momWriters = $formData['mom_writers'] ?? [];

            // For single MOM writer, take only the first one
            if (is_array($momWriters) && count($momWriters) > 1) {
                $momWriters = [$momWriters[0]];
            }

            // Update meeting details
            $oldValues = $meeting->toArray();

            $oldValues = $meeting->toArray();
            $changedFields = [];
            $meeting->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'meeting_date' => $validated['meeting_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'meeting_type' => $validated['meeting_type'],
                'location' => $validated['location'],
                'reminder_minutes_before' => $validated['reminder_minutes'] ?? 15
            ]);

            // Get existing participants
            $existingParticipants = $meeting->participants->pluck('user_id')->toArray();

            // Add creator if not already in participants
            if (!in_array(auth()->id(), $participants)) {
                $participants[] = auth()->id();
            }

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
                        'attendance' => 'pending',
                        'role' => $userId == $meeting->created_by ? 'organizer' : 'attendee'
                    ]
                );
            }

            // // Log history
            // MeetingHistory::create([
            //     'meeting_id' => $meeting->id,
            //     'action_by' => auth()->id(),
            //     'action_type' => 'updated',
            //     'old_values' => json_encode($oldValues),
            //     'new_values' => json_encode($meeting->toArray()),
            //     'description' => 'Meeting updated'
            // ]);

            foreach ($meeting->toArray() as $key => $value) {
                if (isset($oldValues[$key]) && $oldValues[$key] != $value && !in_array($key, ['updated_at'])) {
                    $changedFields[$key] = [
                        'old' => $oldValues[$key],
                        'new' => $value
                    ];
                }
            }
            DB::commit();
            try {
                if (!empty($changedFields)) {
                    $this->meetingNotificationService->notifyMeetingUpdated($meeting, $changedFields);
                }
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }

            return redirect()->route('meetings.show', $meeting->id)
                ->with('success', 'Meeting updated successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting update failed: ' . $e->getMessage());

            return back()
                ->with('error', 'Failed to update meeting: ' . $e->getMessage())
                ->withInput();
        }
    }
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5'
        ]);

        $meeting = Meeting::findOrFail($id);

        // Check authorization
        if ($meeting->created_by != auth()->id() && auth()->user()->role != 'admin') {
            return redirect()->route('meetings.index')
                ->with('error', 'You are not authorized to cancel this meeting.');
        }

        DB::beginTransaction();

        try {
            $meeting->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason
            ]);

            // Log history
            // MeetingHistory::create([
            //     'meeting_id' => $meeting->id,
            //     'action_by' => auth()->id(),
            //     'action_type' => 'cancelled',
            //     'description' => "Meeting cancelled. Reason: {$request->reason}"
            // ]);

            DB::commit();
            try {
                $this->meetingNotificationService->notifyMeetingCancelled($meeting, $request->reason);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }
            return redirect()->route('meetings.index')
                ->with('success', 'Meeting cancelled successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Failed to cancel meeting: ' . $e->getMessage());
        }
    }

    /**
     * Show meeting details
     */
    public function show($id)
    {
        $meeting = Meeting::with([
            'creator',
            'participants.user',
            'momWriters',
            'tasks' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'tasks.assignments.assignedTo',
            'tasks.project'
        ])->findOrFail($id);

        $allUsers = User::where('status', '1')->get();

        return view('client.mom.meeting.show', compact('meeting', 'allUsers'));
    }
}
