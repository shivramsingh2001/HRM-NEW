<?php

namespace App\Http\Controllers\Mom;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingHistory;
use App\Models\MeetingParticipant;
use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskAssign;
use App\Services\MeetingNotificationService;
use App\Services\TaskNotificationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MeetingMinuteController extends Controller
{
    protected $notificationService;
    protected $meetingNotificationService;

    public function __construct(TaskNotificationService $notificationService, MeetingNotificationService $meetingNotificationService)
    {
        $this->notificationService = $notificationService;
        $this->meetingNotificationService = $meetingNotificationService;
    }

    public function create(Request $request, $id)
    {
        $meeting = Meeting::with(['participants.user'])->findOrFail($id);

        if (!$meeting->isMomAuthorableBy(Auth::user())) {
            abort(403, 'You are not authorized to author minutes for this meeting.');
        }

        $allUsers = User::where('status', '1')->get();
        $projects = Project::whereIn('status', ['ongoing', 'pending'])->get();

        // Get existing tasks for this meeting from the tasks table
        $existingTasks = Task::with(['project', 'assignments.assignedTo'])
            ->where('meeting_id', $meeting->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // The list/detail pages fetch this same form into a drawer via AJAX
        // instead of navigating — same data/authorization, only the
        // response wrapper differs (no master-layout chrome for the
        // fragment; the caller's own drawer already provides that).
        if ($request->ajax()) {
            return view('client.mom.mom._mom_form_content', compact('allUsers', 'projects', 'meeting', 'existingTasks'));
        }

        return view('client.mom.mom.create', compact('allUsers', 'projects', 'meeting', 'existingTasks'));
    }

    /**
     * Store meeting minutes and tasks
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();

        // Validation rules
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'nullable|exists:meetings,id',
            'mom_content' => 'required|string',
            'meeting_title' => 'nullable|string|max:255',
            'meeting_date' => 'nullable|date',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            // 'draft' saves mom_content without completing the meeting or
            // locking it; 'finalize' (default, matches the form's current
            // one-shot save behavior) completes the meeting and locks the
            // minutes. Not sent by the current UI yet — defaults preserve
            // today's behavior until a real draft/finalize UI ships.
            'action' => 'nullable|in:draft,finalize',
            'tasks' => 'nullable|array',
            'tasks.*.title' => 'required_with:tasks|string|max:255',
            'tasks.*.assigned_to' => 'required_with:tasks|exists:users,id',
            'tasks.*.deadline' => 'required_with:tasks|date|after_or_equal:today',
            'tasks.*.severity' => 'nullable|in:low,medium,high,critical',
            'tasks.*.project' => 'nullable|exists:projects,id',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.voice_data' => 'nullable|string',
            'tasks.*.attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $meeting = Meeting::find($request->meeting_id);
        if ($meeting && !$meeting->isMomAuthorableBy($authUser)) {
            abort(403, 'You are not authorized to author minutes for this meeting.');
        }

        // Finalized minutes are locked — only someone who can edit the
        // meeting itself (creator/admin/hr) may touch them again, and
        // doing so must go through reopen() first, not a plain re-save.
        if ($meeting && $meeting->mom_status === 'finalized') {
            abort(403, 'These minutes are finalized. Ask the organizer to reopen them before making changes.');
        }

        $action = $request->input('action', 'finalize');

        DB::beginTransaction();

        try {
            if ($meeting) {
                $meeting->update([
                    'mom_content' => $request->mom_content,
                    'mom_status' => $action === 'draft' ? 'draft' : 'finalized',
                    'status' => $action === 'draft' ? $meeting->status : 'completed',
                ]);

                MeetingHistory::record(
                    $meeting,
                    $action === 'draft' ? 'mom_drafted' : 'mom_finalized',
                    $action === 'draft' ? 'Minutes of meeting saved as draft' : 'Minutes of meeting saved and finalized'
                );
            }
            // Process tasks if any
            if ($request->has('tasks') && is_array($request->tasks)) {
                foreach ($request->tasks as $taskData) {
                    // Skip if task title is empty
                    if (empty($taskData['title'])) {
                        continue;
                    }

                    // Handle file attachment
                    $attachmentPath = null;
                    if (isset($taskData['attachment']) && $taskData['attachment'] instanceof \Illuminate\Http\UploadedFile) {
                        $attachmentPath = file_storage()->upload($taskData['attachment'], 'task_document')->path;
                    }

                    // Handle voice data (base64 from recording)
                    $voiceFilePath = null;
                    if (isset($taskData['voice_data']) && !empty($taskData['voice_data'])) {
                        $voiceData = $taskData['voice_data'];
                        // Check if it's base64 data
                        if (strpos($voiceData, 'data:audio/') === 0) {
                            $voiceData = explode(',', $voiceData)[1] ?? '';
                            $voiceBinary = base64_decode($voiceData);
                            $voiceFilePath = file_storage()->storeContents((string) $voiceBinary, 'task_voice', 'wav')->path;
                        }
                    }

                    // Generate unique task code using the same method as TaskController
                    $taskCode = $this->generateUniqueTaskCode();

                    // Create task in tasks table with meeting_id
                    $task = Task::create([
                        'task_code' => $taskCode,
                        'title' => $taskData['title'],
                        'meeting_id' => $meeting->id, // Link task to meeting
                        'project_id' => $taskData['project'] ?? null,
                        'description' => $taskData['description'] ?? $taskData['title'],
                        'task_date' => date('Y-m-d'),
                        'deadline_date' => $taskData['deadline'],
                        'priority' => $taskData['severity'] ?? 'medium',
                        'status' => 'pending',
                        'file' => $attachmentPath,
                        'voice_file' => $voiceFilePath,
                    ]);

                    // Assign task to user
                    $assignedBy = $authUser->id;
                    $assignedTo = $taskData['assigned_to'];

                    TaskAssign::create([
                        'task_id' => $task->id,
                        'assigned_by' => $assignedBy,
                        'assigned_to' => $assignedTo,
                        'status' => 'assigned',
                    ]);

                    // Send notification to assigned user
                    try {
                        $assignedUser = User::find($assignedTo);
                        if ($assignedUser && $this->notificationService) {
                            $this->notificationService->notifyTaskAssigned($task, $assignedUser, $authUser);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to send task notification: ' . $e->getMessage());
                    }
                }
            }

            DB::commit();

            if ($meeting && $action !== 'draft') {
                try {
                    $this->meetingNotificationService->notifyMinutesAdded($meeting, $request->has('tasks') ? count($request->tasks) : 0);
                } catch (Exception $e) {
                    Log::error('Failed to send minutes-added notification: ' . $e->getMessage());
                }
            }

            $message = $action === 'draft'
                ? 'Meeting minutes saved as draft.'
                : 'Meeting minutes and ' . ($request->has('tasks') ? count($request->tasks) : 0) . ' task(s) created successfully!';

            // Back to the meeting list (the minutes are written from its drawer).
            return redirect()->route('meetings.index')->with('success', $message);
        } catch (Exception $e) {
            report($e);
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to save meeting minutes: ' . $e->getMessage())
                ->withInput();
        }
    }
    /**
     * Re-open finalized minutes for editing. Only someone who can edit the
     * meeting itself (creator/admin/hr) — a MOM writer alone cannot reopen,
     * only author while open — since unlocking finalized minutes is a
     * bigger decision than just writing them.
     */
    public function reopen(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);

        if (!$meeting->isEditableBy(Auth::user())) {
            abort(403, 'You are not authorized to reopen this meeting\'s minutes.');
        }

        if ($meeting->mom_status !== 'finalized') {
            return back()->with('warning', 'These minutes are not finalized, so there is nothing to reopen.');
        }

        $meeting->update(['mom_status' => 'draft']);

        MeetingHistory::record($meeting, 'mom_reopened', 'Minutes reopened for editing by ' . (Auth::user()->name ?? 'user'));

        return redirect()->route('meetings.mom.create', $meeting->id)
            ->with('success', 'Minutes reopened for editing.');
    }

    private function generateUniqueTaskCode()
    {
        return DB::transaction(function () {
            // Get the last task code using Task model
            $lastTask = Task::where('task_code', 'like', 'TASK-%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(task_code, 6) AS UNSIGNED) DESC')
                ->first();

            if (!$lastTask) {
                $nextNumber = 1;
            } else {
                // Extract numeric part from TASK-00001
                $lastNumber = (int) substr($lastTask->task_code, 5);
                $nextNumber = $lastNumber + 1;

                // Reset if exceeds 99999
                if ($nextNumber > 99999) {
                    $nextNumber = 1;
                }
            }

            return 'TASK-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        });
    }

}
