<?php

namespace App\Http\Controllers\Mom;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingHistory;
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

    // Permission-based filtering: 'company' scope sees every meeting;
    // anything narrower (own/team) is restricted to meetings they
    // participate in — there's no single owner column for this
    // many-to-many relation, so scope isn't used to filter a column.
    $meetingScope = app(\App\Services\RbacService::class)->scopeFor($authUser, 'meetings', 'view');
    if ($meetingScope === null) {
        abort(403, 'You do not have permission to view meetings.');
    }
    $participantOnly = $meetingScope !== 'company';
    if ($participantOnly) {
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
    
    // Stats for dashboard with permission-based filtering
    $statsQuery = Meeting::query();

    if ($participantOnly) {
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
    $cancelledMeetings = (clone $statsQuery)->where('status', 'cancelled')->count();
    $pendingMomCount = (clone $statsQuery)->where('status', 'completed')
        ->where(function ($q) {
            $q->whereNull('mom_status')->orWhere('mom_status', '!=', 'finalized');
        })
        ->count();

    // "My Meetings" ignores the participant-only scope above (it's already
    // narrower than that) — always creator-or-participant for the current
    // user, meaningful mainly for admin/hr who otherwise see every meeting.
    $myMeetingsCount = Meeting::where(function ($q) use ($authUser) {
        $q->where('created_by', $authUser->id)
            ->orWhereHas('participants', function ($sub) use ($authUser) {
                $sub->where('user_id', $authUser->id);
            });
    })->count();

    // Needed for the Schedule/Edit drawers, which now live on this page
    // instead of separate create()/edit() pages.
    $allUsers = User::where('status', '1')->get();

    return view('client.mom.meeting.index', compact(
        'meetings',
        'todayCount',
        'totalMeetings',
        'upcomingCount',
        'completeMeetings',
        'cancelledMeetings',
        'pendingMomCount',
        'myMeetingsCount',
        'allUsers'
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

    private const LOCATION_MESSAGES = [
        'location.required_unless' => 'Location is required for a physical or hybrid meeting.',
        'virtual_meeting_link.required_if' => 'A meeting link is required for a virtual meeting.',
    ];

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
            // A fully virtual meeting needs a link, not a place; the others need a place.
            'location' => 'nullable|string|max:255|required_unless:meeting_type,virtual',
            'virtual_meeting_link' => 'nullable|string|max:500|required_if:meeting_type,virtual',
            'participants' => 'required|json|min:1',
            'participants.*' => 'exists:users,id',
            'mom_writers' => 'nullable|json', // Changed to nullable
            'mom_writers.*' => 'exists:users,id', // Added validation for mom_writers
            'reminder_minutes' => 'nullable|integer|min:0|max:1440', // Added validation
            'agenda_items' => 'nullable', // json string or array, decoded below
            'decisions' => 'nullable',
        ], self::LOCATION_MESSAGES);

        DB::beginTransaction();

        try {
            $participants = json_decode($request->participants, true);
            $momWriters = json_decode($request->mom_writers, true) ?? [];
            $agendaItems = $this->decodeJsonInput($request->agenda_items);
            $decisions = $this->decodeJsonInput($request->decisions);

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
            $meetingId = $this->generateUniqueMeetingId();

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
                'virtual_meeting_link' => $request->virtual_meeting_link,
                'created_by' => Auth::id(),
                'status' => 'scheduled',
                'reminder_minutes_before' => $request->reminder_minutes ?? 15, // Added reminder
                'agenda_items' => $agendaItems,
                'decisions' => $decisions,
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
                    'attendance_status' => 'pending',
                    'role' => $userId == Auth::id() ? 'organizer' : 'attendee' // Add role
                ]);
            }

            MeetingHistory::record($meeting, 'created', 'Meeting scheduled');

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
            report($e);
            DB::rollBack();

            return back()->with('error', 'Failed to create meeting: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Tenant-scoped, lock-protected MT-000001-style business code. Meeting
     * uses TenantTrait, so the "last meeting" lookup is already scoped to
     * the current tenant by the model's global scope — lockForUpdate()
     * closes the race where two requests in the same tenant read the same
     * "last" row and generate the same next number. Must be called inside
     * an open DB transaction (store() already opens one).
     */
    private function generateUniqueMeetingId(): string
    {
        $lastMeeting = Meeting::withTrashed()->orderBy('id', 'desc')->lockForUpdate()->first();
        $lastNumber = $lastMeeting ? intval(substr($lastMeeting->meeting_id, 3)) : 0;

        return 'MT-' . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * agenda_items/decisions arrive either as a JSON string (matching the
     * existing participants/mom_writers convention on this form) or already
     * decoded as an array — accept either, return null for empty input so
     * we never store a stray "[]" where the field was simply never touched.
     */
    private function decodeJsonInput($value): ?array
    {
        if (is_array($value)) {
            return !empty($value) ? array_values($value) : null;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && !empty($decoded)) {
                return array_values($decoded);
            }
        }

        return null;
    }

    public function edit($id)
    {
        $meeting = Meeting::with(['participants.user', 'momWriters'])
            ->findOrFail($id);

        // Check authorization (company scope, or the meeting's own creator)
        if (!$meeting->isEditableBy(Auth::user())) {
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
        if (!$meeting->isEditableBy(auth()->user())) {
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
            'location' => 'nullable|string|max:255|required_unless:meeting_type,virtual',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:users,id',
            'mom_writers' => 'nullable|array',
            'mom_writers.*' => 'exists:users,id',
            'reminder_minutes' => 'nullable|integer|min:0|max:1440',
            'virtual_meeting_link' => 'nullable|string|max:500|required_if:meeting_type,virtual',
        ], self::LOCATION_MESSAGES)->validate();

        DB::beginTransaction();

        try {
            // Get the decoded arrays
            $participants = $formData['participants'];
            $momWriters = $formData['mom_writers'] ?? [];
            $agendaItems = $this->decodeJsonInput($formData['agenda_items'] ?? null);
            $decisions = $this->decodeJsonInput($formData['decisions'] ?? null);

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
                'location' => $validated['location'] ?? null,
                'virtual_meeting_link' => $validated['virtual_meeting_link'] ?? null,
                'reminder_minutes_before' => $validated['reminder_minutes'] ?? 15,
                'agenda_items' => $agendaItems,
                'decisions' => $decisions,
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

            // Add or update participants. New participants start at
            // 'pending' RSVP; participants who were already on the meeting
            // keep whatever attendance_status they already had (don't reset
            // an existing RSVP just because the organizer re-saved the form).
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

            foreach ($meeting->toArray() as $key => $value) {
                if (isset($oldValues[$key]) && $oldValues[$key] != $value && !in_array($key, ['updated_at'])) {
                    $changedFields[$key] = [
                        'old' => $oldValues[$key],
                        'new' => $value
                    ];
                }
            }

            if (!empty($changedFields)) {
                MeetingHistory::record($meeting, 'updated', 'Meeting details updated', $oldValues, $meeting->toArray());
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
        if (!$meeting->isEditableBy(auth()->user())) {
            return redirect()->route('meetings.index')
                ->with('error', 'You are not authorized to cancel this meeting.');
        }

        // Can't cancel a meeting that's already finished/cancelled
        if (in_array($meeting->status, ['completed', 'cancelled'])) {
            return redirect()->route('meetings.show', $meeting->id)
                ->with('warning', "This meeting cannot be cancelled because it is already {$meeting->status}.");
        }

        DB::beginTransaction();

        try {
            $meeting->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason
            ]);

            MeetingHistory::record($meeting, 'cancelled', "Meeting cancelled. Reason: {$request->reason}");

            DB::commit();
            try {
                $this->meetingNotificationService->notifyMeetingCancelled($meeting, $request->reason);
            } catch (Exception $e) {
                Log::error('Failed to send leave notifications: ' . $e->getMessage());
            }
            return redirect()->route('meetings.index')
                ->with('success', 'Meeting cancelled successfully!');
        } catch (Exception $e) {
            report($e);
            DB::rollBack();
            return back()
                ->with('error', 'Failed to cancel meeting: ' . $e->getMessage());
        }
    }

    /**
     * Reschedule a meeting to a new date/time. Deliberately a distinct
     * action from update() rather than just letting the date/time fields
     * be edited silently: it fires its own "rescheduled" notification (old
     * time -> new time, not a generic "updated" message), resets
     * reminder_sent (the old reminder window no longer applies) and every
     * participant's RSVP back to pending (a prior "I'll attend" doesn't
     * carry over to a different time), and gets its own audit entry.
     */
    public function reschedule(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);

        if (!$meeting->isEditableBy(Auth::user())) {
            return redirect()->route('meetings.index')
                ->with('error', 'You are not authorized to reschedule this meeting.');
        }

        if (!in_array($meeting->status, ['scheduled', 'postponed'])) {
            return redirect()->route('meetings.show', $meeting->id)
                ->with('warning', "This meeting cannot be rescheduled because it is {$meeting->status}.");
        }

        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'location' => 'nullable|string',
            'virtual_meeting_link' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldDate = $meeting->meeting_date;
        $oldStart = $meeting->start_time;
        $oldEnd = $meeting->end_time;

        DB::beginTransaction();

        try {
            $meeting->update([
                'meeting_date' => $validated['meeting_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'location' => $validated['location'] ?? $meeting->location,
                'virtual_meeting_link' => $validated['virtual_meeting_link'] ?? $meeting->virtual_meeting_link,
                'status' => 'scheduled',
                'reminder_sent' => false,
            ]);

            $meeting->participants()->update([
                'attendance_status' => 'pending',
                'responded_at' => null,
                'response_comments' => null,
            ]);

            MeetingHistory::record(
                $meeting,
                'rescheduled',
                trim("Meeting rescheduled" . ($validated['reason'] ?? '' ? ': ' . $validated['reason'] : '')),
                ['meeting_date' => $oldDate, 'start_time' => $oldStart, 'end_time' => $oldEnd],
                ['meeting_date' => $meeting->meeting_date, 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time]
            );

            DB::commit();

            try {
                $this->meetingNotificationService->notifyMeetingRescheduled($meeting, $oldDate, $oldStart, $oldEnd);
            } catch (Exception $e) {
                Log::error('Failed to send meeting reschedule notifications: ' . $e->getMessage());
            }

            return redirect()->route('meetings.show', $meeting->id)
                ->with('success', 'Meeting rescheduled successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting reschedule failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to reschedule meeting: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Bulk-mark participant attendance after the meeting happened
     * (present/absent/late) — distinct from a participant's own pre-meeting
     * RSVP (attendance_status confirmed/declined/tentative, set via the API
     * updateAttendance endpoint). Both write the same column at different
     * points in the meeting's lifecycle. Gated the same as MOM authoring:
     * the organizer/admin, or a designated MOM writer, can record it.
     */
    public function markAttendance(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);

        if (!$meeting->isMomAuthorableBy(Auth::user())) {
            abort(403, 'You are not authorized to record attendance for this meeting.');
        }

        $validated = $request->validate([
            'attendance' => 'required|array|min:1',
            'attendance.*' => 'in:present,absent,late',
        ]);

        DB::beginTransaction();

        try {
            foreach ($validated['attendance'] as $participantId => $status) {
                $participant = MeetingParticipant::where('meeting_id', $meeting->id)
                    ->where('id', $participantId)
                    ->first();

                if (!$participant) {
                    continue;
                }

                $participant->update([
                    'attendance_status' => $status,
                    'joined_at' => $status === 'present' ? ($participant->joined_at ?? now()) : $participant->joined_at,
                    'left_at' => $status !== 'present' ? null : $participant->left_at,
                ]);
            }

            MeetingHistory::record($meeting, 'attendance_marked', 'Attendance recorded for ' . count($validated['attendance']) . ' participant(s)');

            DB::commit();

            return redirect()->route('meetings.show', $meeting->id)
                ->with('success', 'Attendance recorded successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Attendance marking failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to record attendance: ' . $e->getMessage());
        }
    }

    /**
     * Show meeting details
     */
    public function show(Request $request, $id)
    {
        $meeting = Meeting::with([
            'creator',
            'participants.user',
            'momWriters',
            'tasks' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
            'tasks.assignments.assignedTo',
            'tasks.project',
            'histories.actionBy'
        ])->findOrFail($id);

        if (!$meeting->isViewableBy(Auth::user())) {
            abort(403, 'You do not have access to this meeting.');
        }

        // The list page fetches this same content into a drawer via AJAX
        // instead of navigating — same data/authorization, only the
        // response wrapper differs (no master-layout chrome for the
        // fragment; index.blade.php's own drawer already provides that).
        if ($request->ajax()) {
            return view('client.mom.meeting._show_content', compact('meeting'));
        }

        return view('client.mom.meeting.show', compact('meeting'));
    }

    /**
     * Delete (soft) a meeting. Gated by permission:meetings,delete at the
     * route level — only admin/hr have that grant per config/rbac.php.
     */
    public function destroy($id)
    {
        $meeting = Meeting::findOrFail($id);

        DB::beginTransaction();

        try {
            MeetingHistory::record($meeting, 'deleted', 'Meeting deleted by ' . (Auth::user()->name ?? 'user'));

            try {
                $this->meetingNotificationService->notifyMeetingCancelled($meeting, 'This meeting has been removed.');
            } catch (Exception $e) {
                Log::error('Failed to send meeting deletion notifications: ' . $e->getMessage());
            }

            $meeting->delete();

            DB::commit();

            return redirect()->route('meetings.index')
                ->with('success', 'Meeting deleted successfully!');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Meeting delete failed: ' . $e->getMessage());

            return back()->with('error', 'Failed to delete meeting: ' . $e->getMessage());
        }
    }
}
